<?php
session_start();
require_once 'connect.php';

// Add detailed debugging function
function detailed_debug($title, $data) {
    $log_file = "detailed_booking_debug.log";
    $log_message = date('Y-m-d H:i:s') . " - " . $title . "\n";
    $log_message .= print_r($data, true) . "\n";
    $log_message .= "------------------------------------------\n";
    error_log($log_message, 3, $log_file);
}

// Function to check current bookings for a date
function getTotalBookingsForDate($conn, $date) {
    // Start transaction for consistent read
    $conn->begin_transaction();
    try {
        // First check/initialize daily_tickets record
        $stmt = $conn->prepare("INSERT IGNORE INTO daily_tickets (date, max_tickets) 
                               SELECT ?, max_tickets_per_day 
                               FROM ticket_rates WHERE id = 1");
        $stmt->bind_param("s", $date);
        $stmt->execute();
        
        // Get current booking count with row lock
        $sql = "SELECT booked_tickets, max_tickets 
                FROM daily_tickets 
                WHERE date = ? 
                FOR UPDATE";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $conn->commit();
        return [
            'booked' => $row['booked_tickets'] ?? 0,
            'max' => $row['max_tickets'] ?? 0
        ];
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

// Function to check ticket availability
function getAvailableTickets($conn, $date) {
    $bookings = getTotalBookingsForDate($conn, $date);
    return $bookings['max'] - $bookings['booked'];
}

$errors = [];
$success = false;

// Get ticket rates with error handling and caching
function getTicketRates($conn) {
    // Try to get rates from session cache first
    if (isset($_SESSION['ticket_rates']) && 
        (time() - $_SESSION['ticket_rates_cached'] < 300)) { // Cache for 5 minutes
        return $_SESSION['ticket_rates'];
    }

    $ratesQuery = $conn->query("SELECT * FROM ticket_rates WHERE id = 1");
    if (!$ratesQuery) {
        // Set default rates if query fails
        $rates = [
            'adult_rate' => 100,
            'child_5_12_rate' => 50,
            'senior_rate' => 75,
            'camera_rate' => 25,
            'max_tickets_per_day' => 1000
        ];
    } else {
        $rates = $ratesQuery->fetch_assoc();
        if (!$rates) {
            // Insert default rates if none found
            $conn->query("INSERT INTO ticket_rates (id, adult_rate, child_5_12_rate, senior_rate, camera_rate, max_tickets_per_day) 
                         VALUES (1, 100, 50, 75, 25, 1000)");
            $rates = [
                'adult_rate' => 100,
                'child_5_12_rate' => 50,
                'senior_rate' => 75,
                'camera_rate' => 25,
                'max_tickets_per_day' => 1000
            ];
        }
    }

    // Cache the rates in session
    $_SESSION['ticket_rates'] = $rates;
    $_SESSION['ticket_rates_cached'] = time();

    return $rates;
}

// Get current rates
$rates = getTicketRates($conn);

// Fetch user profile data if logged in
$user_profile = null;
if (isset($_SESSION['user'])) {
    $stmt = $conn->prepare("SELECT username, email, phone, address FROM users WHERE username = ?");
    $stmt->bind_param("s", $_SESSION['user']);
    $stmt->execute();
    $user_profile = $stmt->get_result()->fetch_assoc();
}

// Add more detailed debug logging
function debug_log($message, $data = null) {
    $log_message = date('Y-m-d H:i:s') . " - " . $message;
    if ($data !== null) {
        $log_message .= "\nData: " . print_r($data, true);
    }
    error_log($log_message . "\n", 3, "booking_debug.log");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Log payment ID if present
    if (isset($_POST['razorpay_payment_id'])) {
        detailed_debug("Payment processing started", [
            'payment_id' => $_POST['razorpay_payment_id'],
            'post_data' => $_POST,
            'session_data' => $_SESSION
        ]);
        
        try {
            // Handle document upload if needed
            if (isset($_FILES['document']) && $_FILES['document']['error'] === 0) {
                detailed_debug("Document upload attempt", [
                    'file_data' => $_FILES['document'],
                    'current_document_path' => $_SESSION['booking']['document_path'] ?? 'not set'
                ]);
                
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                $fileType = $_FILES['document']['type'];
                
                if (!in_array($fileType, $allowedTypes)) {
                    throw new Exception("Invalid document type. Please upload PDF, JPG, or PNG files");
                } else {
                    $uploadDir = 'uploads/documents/';
                    
                    // Create directory if not exists
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $fileName = time() . '_' . basename($_FILES['document']['name']);
                    $uploadFile = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['document']['tmp_name'], $uploadFile)) {
                        $_SESSION['booking']['document_path'] = $uploadFile;
                    } else {
                        throw new Exception("Failed to upload document");
                    }
                }
                
                detailed_debug("Document upload result", [
                    'upload_path' => $uploadFile,
                    'success' => isset($_SESSION['booking']['document_path'])
                ]);
            }
            
            // If form data is submitted directly with payment ID
            if (!isset($_SESSION['booking']) && isset($_POST['visit-date'])) {
                detailed_debug("Creating booking session from form", $_POST);
                
                // Create session booking data from form
                $visitDate = $_POST['visit-date'] ?? '';
                $adultTickets = intval($_POST['adult-tickets'] ?? 0);
                $child0_5Tickets = intval($_POST['child-0-5-tickets'] ?? 0);
                $child5_12Tickets = intval($_POST['child-5-12-tickets'] ?? 0);
                $seniorTickets = intval($_POST['senior-tickets'] ?? 0);
                $cameraVideo = isset($_POST['camera-video']) ? 1 : 0;
                $documentPath = $_SESSION['booking']['document_path'] ?? ''; // Use uploaded document path
                $visitorName = trim($_POST['visitor-name'] ?? '');
                $visitorEmail = trim($_POST['visitor-email'] ?? '');
                $visitorPhone = trim($_POST['visitor-phone'] ?? '');
                $visitorCity = trim($_POST['visitor-city'] ?? '');
                $visitorAddress = trim($_POST['visitor-address'] ?? '');
                
                // Calculate total amount
                $rates = getTicketRates($conn);
                $totalAmount = ($adultTickets * $rates['adult_rate']) + 
                              ($child5_12Tickets * $rates['child_5_12_rate']) + 
                              ($seniorTickets * $rates['senior_rate']) + 
                              ($cameraVideo ? $rates['camera_rate'] : 0);
                
                $_SESSION['booking'] = [
                    'visit_date' => $visitDate,
                    'adult_tickets' => $adultTickets,
                    'child_0_5_tickets' => $child0_5Tickets,
                    'child_5_12_tickets' => $child5_12Tickets,
                    'senior_tickets' => $seniorTickets,
                    'camera_video' => $cameraVideo,
                    'document_path' => $documentPath,
                    'total_amount' => $totalAmount,
                    'name' => $visitorName,
                    'email' => $visitorEmail,
                    'phone' => $visitorPhone,
                    'city' => $visitorCity,
                    'address' => $visitorAddress,
                    'razorpay_payment_id' => $_POST['razorpay_payment_id']
                ];
                
                detailed_debug("Created booking session", $_SESSION['booking']);
            }
            
            $conn->begin_transaction();
            detailed_debug("Transaction started", ['autocommit' => $conn->autocommit]);
            
            // Get booking data
            if (!isset($_SESSION['booking'])) {
                throw new Exception("No booking data found in session");
            }
            
            $booking = $_SESSION['booking'];
            detailed_debug("Retrieved booking data", $booking);
            
            // Verify payment amount
            detailed_debug("Payment amount verification", [
                'posted_amount' => $_POST['total_amount'] ?? 'not set',
                'session_amount' => $booking['total_amount']
            ]);
            
            if (!isset($_POST['total_amount']) || 
                floatval($_POST['total_amount']) !== floatval($booking['total_amount'])) {
                throw new Exception("Payment amount mismatch");
            }
            
            // Calculate total tickets
            $totalTickets = intval($booking['adult_tickets']) + 
                           intval($booking['child_0_5_tickets']) + 
                           intval($booking['child_5_12_tickets']) + 
                           intval($booking['senior_tickets']);
            
            detailed_debug("Ticket calculation", [
                'total_tickets' => $totalTickets,
                'breakdown' => [
                    'adult' => $booking['adult_tickets'],
                    'child_0_5' => $booking['child_0_5_tickets'],
                    'child_5_12' => $booking['child_5_12_tickets'],
                    'senior' => $booking['senior_tickets']
                ]
            ]);
            
            // Lock and verify ticket availability
            $stmt = $conn->prepare("SELECT booked_tickets, max_tickets 
                                  FROM daily_tickets 
                                  WHERE date = ? 
                                  FOR UPDATE");
            $stmt->bind_param("s", $booking['visit_date']);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            detailed_debug("Ticket availability check", [
                'date' => $booking['visit_date'],
                'current_data' => $row
            ]);
            
            if (!$row) {
                detailed_debug("Initializing daily_tickets record", [
                    'date' => $booking['visit_date']
                ]);
                
                // Initialize daily_tickets record if it doesn't exist
                $stmt = $conn->prepare("INSERT INTO daily_tickets (date, max_tickets) 
                                      SELECT ?, max_tickets_per_day 
                                      FROM ticket_rates WHERE id = 1");
                $stmt->bind_param("s", $booking['visit_date']);
                $stmt->execute();
                
                // Re-fetch with lock
                $stmt = $conn->prepare("SELECT booked_tickets, max_tickets 
                                      FROM daily_tickets 
                                      WHERE date = ? 
                                      FOR UPDATE");
                $stmt->bind_param("s", $booking['visit_date']);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                
                detailed_debug("Daily tickets initialized", [
                    'success' => $stmt->affected_rows > 0
                ]);
            }
            
            $availableTickets = $row['max_tickets'] - $row['booked_tickets'];
            
            if ($totalTickets > $availableTickets) {
                throw new Exception("Sorry, only {$availableTickets} tickets are available");
            }
            
            // About to execute booking INSERT
            detailed_debug("About to execute booking INSERT", [
                'query_params' => [
                    'visit_date' => $booking['visit_date'],
                    'adult_tickets' => $booking['adult_tickets'],
                    'child_0_5_tickets' => $booking['child_0_5_tickets'],
                    'child_5_12_tickets' => $booking['child_5_12_tickets'],
                    'senior_tickets' => $booking['senior_tickets'],
                    'camera_video' => $booking['camera_video'],
                    'document_path' => $booking['document_path'],
                    'total_amount' => $booking['total_amount'],
                    'name' => $booking['name'],
                    'email' => $booking['email'],
                    'phone' => $booking['phone'],
                    'city' => $booking['city'],
                    'address' => $booking['address'],
                    'payment_id' => $_POST['razorpay_payment_id']
                ]
            ]);
            
            // Insert booking record
            $stmt = $conn->prepare("INSERT INTO bookings (
                visit_date, adult_tickets, child_0_5_tickets, child_5_12_tickets, 
                senior_tickets, camera_video, document_path, total_amount, payment_status,
                name, email, phone, city, address, razorpay_payment_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, ?, ?, ?, ?, ?)");
            
            $stmt->bind_param("siiiisdsssssss",
                $booking['visit_date'],
                $booking['adult_tickets'],
                $booking['child_0_5_tickets'],
                $booking['child_5_12_tickets'],
                $booking['senior_tickets'],
                $booking['camera_video'],
                $booking['document_path'],
                $booking['total_amount'],
                $booking['name'],
                $booking['email'],
                $booking['phone'],
                $booking['city'],
                $booking['address'],
                $_POST['razorpay_payment_id']
            );
            
            if (!$stmt->execute()) {
                detailed_debug("INSERT query failed", [
                    'error' => $stmt->error,
                    'errno' => $stmt->errno
                ]);
                throw new Exception("Failed to save booking: " . $stmt->error);
            }
            detailed_debug("INSERT query succeeded", ['insert_id' => $conn->insert_id]);
            
            // Update daily_tickets count
            detailed_debug("Updating daily tickets count", [
                'total_tickets' => $totalTickets,
                'date' => $booking['visit_date']
            ]);
            
            $stmt = $conn->prepare("UPDATE daily_tickets 
                                  SET booked_tickets = booked_tickets + ? 
                                  WHERE date = ?");
            $stmt->bind_param("is", $totalTickets, $booking['visit_date']);
            
            if (!$stmt->execute()) {
                detailed_debug("Failed to update ticket count", [
                    'error' => $stmt->error,
                    'errno' => $stmt->errno
                ]);
                throw new Exception("Failed to update ticket count");
            }
            
            $conn->commit();
            detailed_debug("Transaction committed successfully", [
                'booking_id' => $conn->insert_id
            ]);
            
            // Clear session booking data
            unset($_SESSION['booking']);
            
            $_SESSION['booking_details'] = [
                'reference' => $conn->insert_id,
                'date' => $booking['visit_date'],
                'visitors' => $totalTickets,
                'amount' => $booking['total_amount']
            ];
            $_SESSION['success_message'] = "Your booking was successful!";
            header('Location: booking_success.php');
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            detailed_debug("Error in booking process", [
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $_SESSION['error'] = "Error processing booking: " . $e->getMessage();
            header('Location: booking.php');
            exit;
        }
    }

    // Validate inputs
    $visitDate = $_POST['visit-date'] ?? '';
    $adultTickets = intval($_POST['adult-tickets'] ?? 0);
    $child0_5Tickets = intval($_POST['child-0-5-tickets'] ?? 0);
    $child5_12Tickets = intval($_POST['child-5-12-tickets'] ?? 0);
    $seniorTickets = intval($_POST['senior-tickets'] ?? 0);
    $cameraVideo = isset($_POST['camera-video']) ? 1 : 0;
    $visitorName = trim($_POST['visitor-name'] ?? '');
    $visitorEmail = trim($_POST['visitor-email'] ?? '');
    $visitorPhone = trim($_POST['visitor-phone'] ?? '');
    $visitorCity = trim($_POST['visitor-city'] ?? '');
    $visitorAddress = trim($_POST['visitor-address'] ?? '');

    // Basic validation
    if (empty($visitDate)) {
        $errors[] = "Please select a visit date";
    } else {
        $currentDate = date('Y-m-d');
        if ($visitDate < $currentDate) {
            $errors[] = "Please select a future date";
        }
        
        // Check if it's a Monday (day of week = 1)
        $dayOfWeek = date('N', strtotime($visitDate));
        if ($dayOfWeek == 1) {
            $errors[] = "The zoo is closed on Mondays. Please select another day";
        }
    }

    $totalTickets = $adultTickets + $child0_5Tickets + $child5_12Tickets + $seniorTickets;
    if ($totalTickets <= 0) {
        $errors[] = "Please select at least one ticket";
    }
    
    // Enhanced availability check with transaction
    if (!empty($visitDate) && $totalTickets > 0) {
        $conn->begin_transaction();
        try {
            // First check/initialize daily_tickets record
            $stmt = $conn->prepare("INSERT IGNORE INTO daily_tickets (date, max_tickets) 
                                   SELECT ?, max_tickets_per_day 
                                   FROM ticket_rates WHERE id = 1");
            $stmt->bind_param("s", $visitDate);
            $stmt->execute();
            
            // Get current booking count with row lock
            $sql = "SELECT booked_tickets, max_tickets 
                    FROM daily_tickets 
                    WHERE date = ? 
                    FOR UPDATE";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $visitDate);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if (!$row) {
                throw new Exception("Error fetching ticket information");
            }
            
            $available_tickets = $row['max_tickets'] - $row['booked_tickets'];
            
            if ($totalTickets > $available_tickets) {
                $conn->rollback();
                $errors[] = "Sorry, only {$available_tickets} tickets are available for the selected date";
            } else {
                // Update daily_tickets count
                $stmt = $conn->prepare("UPDATE daily_tickets 
                                      SET booked_tickets = booked_tickets + ? 
                                      WHERE date = ?");
                $stmt->bind_param("is", $totalTickets, $visitDate);
                $stmt->execute();
                
                // Verify the update was successful
                if ($stmt->affected_rows !== 1) {
                    throw new Exception("Error updating ticket count");
                }
                
                $conn->commit();
            }
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error processing booking: " . $e->getMessage();
        }
    }
    
    // Validate visitor information
    if (empty($visitorName)) {
        $errors[] = "Please enter visitor name";
    }
    
    if (empty($visitorEmail)) {
        $errors[] = "Please enter email address";
    } elseif (!filter_var($visitorEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }
    
    if (empty($visitorPhone)) {
        $errors[] = "Please enter phone number";
    }
    
    if (empty($visitorCity)) {
        $errors[] = "Please enter city";
    }
    
    if (empty($visitorAddress)) {
        $errors[] = "Please enter address";
    }
    
    // Document upload validation
    $documentPath = '';
    if (isset($_FILES['document']) && $_FILES['document']['error'] === 0) {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        $fileType = $_FILES['document']['type'];
        
        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = "Invalid document type. Please upload PDF, JPG, or PNG files";
        } else {
            $uploadDir = 'uploads/documents/';
            
            // Create directory if not exists
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileName = time() . '_' . basename($_FILES['document']['name']);
            $uploadFile = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['document']['tmp_name'], $uploadFile)) {
                $documentPath = $uploadFile;
            } else {
                $errors[] = "Failed to upload document";
            }
        }
    } else {
        $errors[] = "Please upload a valid ID proof";
    }
    
    // Calculate total amount
    $totalAmount = ($adultTickets * $rates['adult_rate']) + 
                  ($child5_12Tickets * $rates['child_5_12_rate']) + 
                  ($seniorTickets * $rates['senior_rate']) + 
                  ($cameraVideo ? $rates['camera_rate'] : 0);
    
    // If no errors, proceed with booking
    if (empty($errors)) {
        // Store booking data in session
        $_SESSION['booking'] = [
            'visit_date' => $visitDate,
            'adult_tickets' => $adultTickets,
            'child_0_5_tickets' => $child0_5Tickets,
            'child_5_12_tickets' => $child5_12Tickets,
            'senior_tickets' => $seniorTickets,
            'camera_video' => $cameraVideo,
            'document_path' => $documentPath,
            'total_amount' => $totalAmount,
            'name' => $visitorName,
            'email' => $visitorEmail,
            'phone' => $visitorPhone,
            'city' => $visitorCity,
            'address' => $visitorAddress,
            'payment_id' => $_SESSION['razorpay_payment_id'] ?? null,
            'payment_status' => isset($_SESSION['razorpay_payment_id']) ? 'completed' : 'pending'
        ];
        
        // Redirect to payment page
        header('Location: payment.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Tickets - SafariGate Zoo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .booking-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: var(--white);
            border-radius: 8px;
            box-shadow: var(--box-shadow);
        }

        .booking-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .booking-header h2 {
            font-size: 3rem;
            color: var(--main);
        }

        .booking-header p {
            font-size: 1.6rem;
            color: #666;
        }

        .ticket-section {
            background: var(--bg);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }

        .ticket-section h3 {
            font-size: 2rem;
            margin-bottom: 15px;
            color: var(--black);
            display: flex;
            align-items: center;
        }

        .ticket-section h3 i {
            margin-right: 10px;
            color: var(--main);
        }

        .ticket-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: var(--white);
            margin-bottom: 10px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .ticket-row:hover {
            transform: translateY(-2px);
            box-shadow: var(--box-shadow);
        }

        .ticket-row label {
            font-size: 1.6rem;
            display: flex;
            align-items: center;
        }

        .ticket-row input[type="number"] {
            width: 80px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1.5rem;
        }

        .ticket-row input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.5);
        }

        .availability-alert {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
            display: flex;
            align-items: flex-start;
            font-size: 1.4rem;
            line-height: 1.5;
        }

        .availability-alert i {
            margin-right: 10px;
            color: #1976d2;
            font-size: 1.8rem;
            margin-top: 2px;
        }

        .availability-alert strong {
            display: inline-block;
            margin-right: 5px;
        }

        .total-section {
            background: var(--main);
            color: var(--white);
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
            text-align: right;
            font-size: 1.8rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .visitor-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .input-group {
            margin-bottom: 15px;
        }

        .input-group label {
            display: block;
            font-size: 1.5rem;
            margin-bottom: 5px;
            color: var(--black);
        }

        .input-group input,
        .input-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1.5rem;
        }

        .input-group textarea {
            height: 100px;
            resize: vertical;
        }

        .input-group.full-width {
            grid-column: span 2;
        }

        .booking-btn {
            width: 100%;
            padding: 15px;
            background: var(--main);
            color: var(--white);
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1.8rem;
            font-weight: bold;
            margin-top: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: all 0.3s ease;
        }

        .booking-btn i {
            margin-right: 10px;
        }

        .booking-btn:hover {
            background: #ff5500;
            transform: translateY(-2px);
        }

        .booking-btn.disabled {
            background-color: #cccccc;
            cursor: not-allowed;
            opacity: 0.7;
        }

        .error-messages {
            background: #ffebee;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #ffcdd2;
        }

        .error {
            color: #c62828;
            font-size: 1.4rem;
            margin: 5px 0;
            display: flex;
            align-items: center;
        }

        .error i {
            margin-right: 10px;
        }

        .zoo-info {
            background: linear-gradient(135deg, var(--bg), #f8e9d9);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 1.5rem;
            color: var(--black);
            text-align: center;
            border-left: 4px solid var(--main);
        }

        .zoo-info h3 {
            color: var(--main);
            margin-bottom: 10px;
            font-size: 2rem;
        }

        .zoo-info p {
            margin-bottom: 10px;
        }

        .zoo-info ul {
            list-style-type: none;
            text-align: left;
            display: inline-block;
            margin: 10px auto;
        }

        .zoo-info ul li {
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }

        .zoo-info ul li i {
            margin-right: 10px;
            color: var(--main);
        }

        @media (max-width: 768px) {
            .visitor-details {
                grid-template-columns: 1fr;
            }
            
            .input-group.full-width {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="booking-container">
        <div class="booking-header">
            <h2><i class="fas fa-ticket-alt"></i> Book Your Zoo Adventure</h2>
            <p>Experience wildlife up close and create memories that will last a lifetime</p>
        </div>

        <div class="zoo-info">
            <h3><i class="fas fa-info-circle"></i> Important Information</h3>
            <p>Before you book, please note:</p>
            <ul>
                <li><i class="fas fa-times-circle"></i> The zoo is closed every Monday for maintenance</li>
                <li><i class="fas fa-clock"></i> Opening hours: 9:00 AM - 5:00 PM</li>
                <li><i class="fas fa-id-card"></i> Please bring a valid photo ID proof for verification</li>
                <li><i class="fas fa-child"></i> Children below 12 years must be accompanied by an adult</li>
            </ul>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-messages">
                <?php foreach ($errors as $error): ?>
                    <div class="error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <div class="ticket-section">
                <h3><i class="fas fa-calendar-alt"></i> Select Visit Date</h3>
                
                <div class="ticket-row">
                    <label for="visit-date">Visit Date</label>
                    <input type="date" name="visit-date" id="visit-date" required
                           value="<?php echo $_POST['visit-date'] ?? ''; ?>">
                </div>

                <div id="availability-info" class="availability-alert">
                    <i class="fas fa-info-circle"></i>
                    <span id="availability-message">Please select a date to check ticket availability</span>
                </div>
            </div>

            <div class="ticket-section">
                <h3><i class="fas fa-ticket-alt"></i> Select Tickets</h3>
                
                <div class="ticket-row">
                    <label>Adult (<span data-rate="adult">₹<?php echo $rates['adult_rate']; ?></span>)</label>
                    <input type="number" name="adult-tickets" min="0" value="<?php echo $_POST['adult-tickets'] ?? 0; ?>" onchange="calculateTotal()">
                </div>

                <div class="ticket-row">
                    <label>Child (0-5 Years) (Free)</label>
                    <input type="number" name="child-0-5-tickets" min="0" value="<?php echo $_POST['child-0-5-tickets'] ?? 0; ?>">
                </div>

                <div class="ticket-row">
                    <label>Child (5-12 Years) (<span data-rate="child-5-12">₹<?php echo $rates['child_5_12_rate']; ?></span>)</label>
                    <input type="number" name="child-5-12-tickets" min="0" value="<?php echo $_POST['child-5-12-tickets'] ?? 0; ?>" onchange="calculateTotal()">
                </div>

                <div class="ticket-row">
                    <label>Senior Citizen (<span data-rate="senior">₹<?php echo $rates['senior_rate']; ?></span>)</label>
                    <input type="number" name="senior-tickets" min="0" value="<?php echo $_POST['senior-tickets'] ?? 0; ?>" onchange="calculateTotal()">
                </div>

                <div class="ticket-row">
                    <label>
                        <input type="checkbox" name="camera-video" onchange="calculateTotal()" 
                              <?php echo (isset($_POST['camera-video']) && $_POST['camera-video']) ? 'checked' : ''; ?>>
                        Camera/Video Access (<span data-rate="camera">₹<?php echo $rates['camera_rate']; ?></span>)
                    </label>
                </div>
            </div>

            <div class="ticket-section">
                <h3><i class="fas fa-user"></i> Visitor Information</h3>
                
                <div class="visitor-details">
                    <div class="input-group">
                        <label for="visitor-name">Full Name</label>
                        <input type="text" name="visitor-name" id="visitor-name" required
                               value="<?php echo isset($user_profile) ? htmlspecialchars($user_profile['username']) : ($_POST['visitor-name'] ?? ''); ?>">
                    </div>
                    
                    <div class="input-group">
                        <label for="visitor-email">Email Address</label>
                        <input type="email" name="visitor-email" id="visitor-email" required
                               value="<?php echo isset($user_profile) ? htmlspecialchars($user_profile['email']) : ($_POST['visitor-email'] ?? ''); ?>">
                    </div>
                    
                    <div class="input-group">
                        <label for="visitor-phone">Phone Number</label>
                        <input type="text" name="visitor-phone" id="visitor-phone" required
                               value="<?php echo isset($user_profile) ? htmlspecialchars($user_profile['phone']) : ($_POST['visitor-phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="input-group">
                        <label for="visitor-city">City</label>
                        <input type="text" name="visitor-city" id="visitor-city" required
                               value="<?php echo $_POST['visitor-city'] ?? ''; ?>">
                    </div>
                    <div class="input-group full-width">
                        <label for="visitor-address">Address</label>
                        <textarea name="visitor-address" id="visitor-address" required><?php echo isset($user_profile) ? htmlspecialchars($user_profile['address']) : ($_POST['visitor-address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="input-group full-width">
                        <label for="document">Upload ID Proof (PDF, JPG, PNG)</label>
                        <input type="file" name="document" id="document" accept=".pdf,.jpg,.jpeg,.png" required>
                    </div>
                </div>
            </div>

            <div class="total-section">
                <span>Total Amount:</span>
                <span>₹<span id="totalAmount">0</span></span>
            </div>
        
            <button type="submit" class="booking-btn">
                
                <i class="fas fa-shopping-cart"></i> Proceed to Payment
            </button>
        </form>
    </div>

    <script>
        const rates = {
            adult: <?php echo $rates['adult_rate']; ?>,
            child_5_12: <?php echo $rates['child_5_12_rate']; ?>,
            senior: <?php echo $rates['senior_rate']; ?>,
            camera: <?php echo $rates['camera_rate']; ?>
        };

        function calculateTotal() {
            const adultTickets = parseInt(document.querySelector('[name="adult-tickets"]').value) || 0;
            const child5Tickets = parseInt(document.querySelector('[name="child-5-12-tickets"]').value) || 0;
            const seniorTickets = parseInt(document.querySelector('[name="senior-tickets"]').value) || 0;
            const cameraVideo = document.querySelector('[name="camera-video"]').checked;

            const total = (adultTickets * rates.adult) + 
                         (child5Tickets * rates.child_5_12) + 
                         (seniorTickets * rates.senior) + 
                         (cameraVideo ? rates.camera : 0);
            
            document.getElementById('totalAmount').textContent = total.toFixed(2);
        }

        function validateTicketSelection() {
            const adultTickets = parseInt(document.querySelector('[name="adult-tickets"]').value) || 0;
            const child0_5Tickets = parseInt(document.querySelector('[name="child-0-5-tickets"]').value) || 0;
            const child5_12Tickets = parseInt(document.querySelector('[name="child-5-12-tickets"]').value) || 0;
            const seniorTickets = parseInt(document.querySelector('[name="senior-tickets"]').value) || 0;
            
            const totalTickets = adultTickets + child0_5Tickets + child5_12Tickets + seniorTickets;
            
            const availabilityInfo = document.getElementById('availability-info');
            const availabilityMessage = document.getElementById('availability-message');
            const submitButton = document.querySelector('.booking-btn');
            
            // If we have available tickets info from the server
            if (window.availableTickets !== undefined) {
                if (totalTickets > window.availableTickets) {
                    availabilityInfo.style.background = '#ffebee';
                    availabilityMessage.innerHTML = `
                        <strong>Too many tickets!</strong> You've selected ${totalTickets} tickets, but only ${window.availableTickets} are available.
                        <br>Please reduce your selection.
                    `;
                    submitButton.disabled = true;
                    submitButton.classList.add('disabled');
                    return false;
                } else if (totalTickets > 0) {
                    if (window.availableTickets < 20) {
                        availabilityInfo.style.background = '#fff8e1';
                        availabilityMessage.innerHTML = `
                            <strong>Limited availability!</strong> Only ${window.availableTickets} tickets left.
                            <br>You've selected ${totalTickets} tickets.
                        `;
                    } else {
                        availabilityInfo.style.background = '#e8f5e9';
                        availabilityMessage.innerHTML = `
                            <strong>Available!</strong> ${window.availableTickets} tickets available.
                            <br>You've selected ${totalTickets} tickets.
                        `;
                    }
                    submitButton.disabled = false;
                    submitButton.classList.remove('disabled');
                    return true;
                }
            }
            
            return true;
        }

        // Add event listeners to all ticket input fields
        document.querySelectorAll('[name="adult-tickets"], [name="child-0-5-tickets"], [name="child-5-12-tickets"], [name="senior-tickets"]').forEach(input => {
            input.addEventListener('change', function() {
                calculateTotal();
                validateTicketSelection();
            });
        });

        // Set minimum date to today
        const dateInput = document.getElementById('visit-date');
        const today = new Date();
        const formattedToday = today.toISOString().split('T')[0];
        dateInput.min = formattedToday;

        // Prevent selecting Mondays and check availability
        dateInput.addEventListener('input', function(e) {
            const selected = new Date(this.value);
            
            // Check if Monday (day 1)
            if (selected.getDay() === 1) {
                alert('The zoo is closed on Mondays. Please select a different date.');
                this.value = '';
                document.getElementById('availability-info').style.display = 'none';
                return;
            }
            
            // Check availability for the selected date
            checkAvailability(this.value);
        });
        
        function checkAvailability(date) {
            if (!date) return;
            
            const availabilityInfo = document.getElementById('availability-info');
            const availabilityMessage = document.getElementById('availability-message');
            
            // Show loading state
            availabilityInfo.style.display = 'flex';
            availabilityMessage.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking availability...';
            
            fetch(`check_availability.php?date=${date}`)
                .then(response => response.json())
                .then(data => {
                    if (data.available_tickets <= 0) {
                        availabilityInfo.style.background = '#ffebee';
                        availabilityMessage.innerHTML = `
                            <strong>Sold Out!</strong> No tickets available for this date. 
                            <br>Total capacity: ${data.max_tickets} | Booked: ${data.booked_tickets}
                        `;
                        
                        // Disable the submit button
                        document.querySelector('.booking-btn').disabled = true;
                        document.querySelector('.booking-btn').classList.add('disabled');
                    } else if (data.available_tickets < 20) {
                        availabilityInfo.style.background = '#fff8e1';
                        availabilityMessage.innerHTML = `
                            <strong>Limited availability!</strong> Only ${data.available_tickets} tickets left.
                            <br>Total capacity: ${data.max_tickets} | Booked: ${data.booked_tickets}
                        `;
                        
                        // Enable the submit button
                        document.querySelector('.booking-btn').disabled = false;
                        document.querySelector('.booking-btn').classList.remove('disabled');
                    } else {
                        availabilityInfo.style.background = '#e8f5e9';
                        availabilityMessage.innerHTML = `
                            <strong>Available!</strong> ${data.available_tickets} tickets available.
                            <br>Total capacity: ${data.max_tickets} | Booked: ${data.booked_tickets}
                        `;
                        
                        // Enable the submit button
                        document.querySelector('.booking-btn').disabled = false;
                        document.querySelector('.booking-btn').classList.remove('disabled');
                    }
                    
                    // Store the available tickets for validation
                    window.availableTickets = data.available_tickets;
                })
                .catch(error => {
                    console.error('Error checking availability:', error);
                    availabilityInfo.style.background = '#ffebee';
                    availabilityMessage.innerHTML = '<strong>Error!</strong> Unable to check availability. Please try again.';
                });
        }

        // Add this to check availability when the page loads if a date is already selected
        window.addEventListener('load', function() {
            const selectedDate = document.getElementById('visit-date').value;
            if (selectedDate) {
                checkAvailability(selectedDate);
            }
        });

        // Initialize total
        calculateTotal();

        // Add this to your existing script section
        function updateAvailability() {
            const dateInput = document.getElementById('visit-date');
            const date = dateInput.value;
            
            if (!date) return;
            
            const availabilityInfo = document.getElementById('availability-info');
            const availabilityMessage = document.getElementById('availability-message');
            const submitButton = document.querySelector('.booking-btn');
            
            // Show loading state
            availabilityInfo.style.display = 'flex';
            availabilityMessage.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking availability...';
            
            fetch(`check_availability.php?date=${date}`)
                .then(response => response.json())
                .then(data => {
                    if (data.available_tickets <= 0) {
                        availabilityInfo.style.background = '#ffebee';
                        availabilityMessage.innerHTML = `
                            <strong>Sold Out!</strong> No tickets available for this date.
                            <br>Total capacity: ${data.max_tickets} | Booked: ${data.booked_tickets}
                        `;
                        submitButton.disabled = true;
                        submitButton.classList.add('disabled');
                    } else if (data.available_tickets < 20) {
                        availabilityInfo.style.background = '#fff8e1';
                        availabilityMessage.innerHTML = `
                            <strong>Limited availability!</strong> Only ${data.available_tickets} tickets left.
                            <br>Total capacity: ${data.max_tickets} | Booked: ${data.booked_tickets}
                        `;
                        submitButton.disabled = false;
                        submitButton.classList.remove('disabled');
                    } else {
                        availabilityInfo.style.background = '#e8f5e9';
                        availabilityMessage.innerHTML = `
                            <strong>Available!</strong> ${data.available_tickets} tickets available.
                            <br>Total capacity: ${data.max_tickets} | Booked: ${data.booked_tickets}
                        `;
                        submitButton.disabled = false;
                        submitButton.classList.remove('disabled');
                    }
                    
                    // Store the available tickets for validation
                    window.availableTickets = data.available_tickets;
                    validateTicketSelection();
                })
                .catch(error => {
                    console.error('Error checking availability:', error);
                    availabilityInfo.style.background = '#ffebee';
                    availabilityMessage.innerHTML = '<strong>Error!</strong> Unable to check availability. Please try again.';
                });
        }

        // Add event listeners
        document.getElementById('visit-date').addEventListener('change', updateAvailability);
        document.querySelectorAll('input[type="number"]').forEach(input => {
            input.addEventListener('change', validateTicketSelection);
        });

        // Check availability on page load if date is selected
        window.addEventListener('load', () => {
            const selectedDate = document.getElementById('visit-date').value;
            if (selectedDate) {
                updateAvailability();
            }
        });
    </script>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
    const razorpayKeyId = 'rzp_test_1TSGXPk46TbXBv';

    document.querySelector('.booking-btn').addEventListener('click', function(e) {
        e.preventDefault();
        
        // Get the total amount from the display
        const amount = parseFloat(document.getElementById('totalAmount').textContent) * 100; // Convert to paise
        const visitorName = document.getElementById('visitor-name').value;
        const visitorEmail = document.getElementById('visitor-email').value;
        const visitorPhone = document.getElementById('visitor-phone').value;

        const options = {
            key: razorpayKeyId,
            amount: amount,
            currency: 'INR',
            name: 'SafariGate Zoo',
            description: 'Zoo Ticket Booking',
            image: 'path/to/your/logo.png',
            prefill: {
                name: visitorName,
                email: visitorEmail,
                contact: visitorPhone
            },
            theme: {
                color: '#ff6e01'
            },
            handler: function(response) {
                console.log('Payment successful:', response);
                
                // Add all form fields to a hidden form
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'booking.php';
                
                // Add all existing form fields
                const formData = new FormData(document.querySelector('form'));
                for (const [key, value] of formData.entries()) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = value;
                    form.appendChild(input);
                }
                
                // Add payment ID
                const paymentInput = document.createElement('input');
                paymentInput.type = 'hidden';
                paymentInput.name = 'razorpay_payment_id';
                paymentInput.value = response.razorpay_payment_id;
                form.appendChild(paymentInput);
                
                // Add total amount
                const amountInput = document.createElement('input');
                amountInput.type = 'hidden';
                amountInput.name = 'total_amount';
                amountInput.value = document.getElementById('totalAmount').textContent;
                form.appendChild(amountInput);
                
                // Add to document and submit
                document.body.appendChild(form);
                form.submit();
            },
            modal: {
                ondismiss: function() {
                    console.log('Payment modal closed');
                    // Handle modal dismissal if needed
                }
            },
            notes: {
                booking_date: document.getElementById('visit-date').value
            }
        };

        document.querySelector('.booking-btn').addEventListener('click', function(e) {
            e.preventDefault();
            
            // Validate form before opening Razorpay
            if (document.querySelector('form').checkValidity()) {
                const rzp = new Razorpay(options);
                rzp.open();
            } else {
                document.querySelector('form').reportValidity();
            }
        });
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>