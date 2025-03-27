<?php
session_start();
require_once 'connect.php';

// Only process API requests when receiving POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Check if admin is logged in
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }

    // Get the QR data from the request
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // Check if we have ticket_id (individual ticket), ticket_type (type ticket) or booking_id (legacy)
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Invalid QR code data']);
        exit;
    }

    if (isset($data['ticket_id'])) {
        // Process individual ticket
        processIndividualTicket($data, $conn);
    } else if (isset($data['ticket_type']) && isset($data['booking_id'])) {
        // Process ticket type
        processTicketType($data, $conn);
    } else if (isset($data['booking_id'])) {
        // Process legacy booking (whole booking)
        processBooking($data, $conn);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid QR code format']);
        exit;
    }
}

/**
 * Process individual ticket scan
 */
function processIndividualTicket($data, $conn) {
    // Validate ticket exists
    $stmt = $conn->prepare("
        SELECT t.*, b.visit_date, b.name, b.status as booking_status 
        FROM individual_tickets t
        JOIN bookings b ON t.booking_id = b.id
        WHERE t.id = ?
    ");
    $stmt->bind_param("i", $data['ticket_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $ticket = $result->fetch_assoc();

    if (!$ticket) {
        echo json_encode(['success' => false, 'message' => 'Ticket not found']);
        exit;
    }

    // Check if booking is cancelled
    if ($ticket['booking_status'] === 'cancelled') {
        echo json_encode(['success' => false, 'message' => 'This booking has been cancelled']);
        exit;
    }

    // Check if ticket is already used
    if ($ticket['status'] === 'used') {
        echo json_encode([
            'success' => false, 
            'message' => 'This ticket has already been used',
            'used_at' => $ticket['used_at']
        ]);
        exit;
    }

    // Verify ticket date
    $today = date('Y-m-d');
    $visit_date = $ticket['visit_date'];

    if ($visit_date < $today) {
        echo json_encode(['success' => false, 'message' => 'This ticket has expired']);
        exit;
    }

    if ($visit_date > $today) {
        echo json_encode(['success' => false, 'message' => 'This ticket is for a future date: ' . date('F d, Y', strtotime($visit_date))]);
        exit;
    }

    // Mark ONLY this ticket as used
    $now = date('Y-m-d H:i:s');
    $admin_id = $_SESSION['user_id'] ?? null;
    $stmt = $conn->prepare("UPDATE individual_tickets SET status = 'used', used_at = ?, used_by = ? WHERE id = ?");
    $stmt->bind_param("sii", $now, $admin_id, $data['ticket_id']);
    
    if ($stmt->execute()) {
        // NEW CODE: Check if all tickets for this booking are now used
        $booking_id = $ticket['booking_id'];
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_tickets,
                SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) as used_tickets
            FROM individual_tickets
            WHERE booking_id = ?
        ");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $ticket_counts = $result->fetch_assoc();
        
        // If all tickets are now used, update the booking status
        if ($ticket_counts['total_tickets'] > 0 && $ticket_counts['total_tickets'] == $ticket_counts['used_tickets']) {
            $stmt = $conn->prepare("UPDATE bookings SET status = 'used', used_at = ?, used_by = ? WHERE id = ?");
            $stmt->bind_param("sii", $now, $admin_id, $booking_id);
            $stmt->execute();
        }
        
        // Get ticket type display name
        $ticketTypeNames = [
            'adult' => 'Adult',
            'child_0_5' => 'Child (0-5)',
            'child_5_12' => 'Child (5-12)',
            'senior' => 'Senior'
        ];
        
        $ticketTypeName = $ticketTypeNames[$ticket['ticket_type']] ?? $ticket['ticket_type'];
        
        // Return success with ticket details
        echo json_encode([
            'success' => true,
            'message' => 'Ticket validated successfully',
            'ticket' => [
                'id' => $ticket['id'],
                'booking_id' => $ticket['booking_id'],
                'ticket_type' => $ticketTypeName,
                'ticket_number' => $data['ticket_number'],
                'used_at' => $now
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating ticket status']);
    }
    exit;
}

/**
 * Process legacy booking scan (whole booking)
 */
function processBooking($data, $conn) {
    // Validate booking exists
    $stmt = $conn->prepare("SELECT b.* FROM bookings b WHERE b.id = ?");
    $stmt->bind_param("i", $data['booking_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();

    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit;
    }

    // Check if booking is already used
    if ($booking['status'] === 'used') {
        echo json_encode([
            'success' => false, 
            'message' => 'This ticket has already been used',
            'used_at' => $booking['used_at']
        ]);
        exit;
    }

    // Verify booking date
    $today = date('Y-m-d');
    $visit_date = $booking['visit_date'];

    if ($visit_date < $today) {
        echo json_encode(['success' => false, 'message' => 'This ticket has expired']);
        exit;
    }

    if ($visit_date > $today) {
        echo json_encode(['success' => false, 'message' => 'This ticket is for a future date: ' . date('F d, Y', strtotime($visit_date))]);
        exit;
    }

    // Mark booking as used
    $now = date('Y-m-d H:i:s');
    $admin_id = $_SESSION['user_id'] ?? null;
    $stmt = $conn->prepare("UPDATE bookings SET status = 'used', used_at = ?, used_by = ? WHERE id = ?");
    $stmt->bind_param("sii", $now, $admin_id, $data['booking_id']);

    if ($stmt->execute()) {
        // Return success with booking details
        echo json_encode([
            'success' => true,
            'message' => 'Ticket validated successfully',
            'booking' => [
                'id' => $booking['id'],
                'name' => $booking['name'],
                'visit_date' => date('F d, Y', strtotime($booking['visit_date'])),
                'total_tickets' => $booking['adult_tickets'] + $booking['child_0_5_tickets'] + 
                                $booking['child_5_12_tickets'] + $booking['senior_tickets'],
                'used_at' => $now
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating booking status']);
    }
    exit;
}

/**
 * Process ticket type scan
 */
function processTicketType($data, $conn) {
    // Validate booking exists
    $stmt = $conn->prepare("
        SELECT b.*, 
            COALESCE(it.used_adult, 0) as used_adult,
            COALESCE(it.used_child_0_5, 0) as used_child_0_5,
            COALESCE(it.used_child_5_12, 0) as used_child_5_12,
            COALESCE(it.used_senior, 0) as used_senior
        FROM bookings b 
        LEFT JOIN (
            SELECT booking_id,
                SUM(CASE WHEN ticket_type = 'adult' AND status = 'used' THEN 1 ELSE 0 END) as used_adult,
                SUM(CASE WHEN ticket_type = 'child_0_5' AND status = 'used' THEN 1 ELSE 0 END) as used_child_0_5,
                SUM(CASE WHEN ticket_type = 'child_5_12' AND status = 'used' THEN 1 ELSE 0 END) as used_child_5_12,
                SUM(CASE WHEN ticket_type = 'senior' AND status = 'used' THEN 1 ELSE 0 END) as used_senior
            FROM individual_tickets
            GROUP BY booking_id
        ) it ON b.id = it.booking_id
        WHERE b.id = ?
    ");
    
    $stmt->bind_param("i", $data['booking_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();

    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit;
    }

    // Check if booking is already used
    if ($booking['status'] === 'used') {
        echo json_encode([
            'success' => false, 
            'message' => 'This ticket has already been used',
            'used_at' => $booking['used_at']
        ]);
        exit;
    }

    // Verify booking date
    $today = date('Y-m-d');
    $visit_date = $booking['visit_date'];
    
    if ($visit_date < $today) {
        echo json_encode(['success' => false, 'message' => 'This ticket has expired']);
        exit;
    }

    if ($visit_date > $today) {
        echo json_encode(['success' => false, 'message' => 'This ticket is for a future date: ' . date('F d, Y', strtotime($visit_date))]);
        exit;
    }

    // Get the ticket type and validate
    $ticket_type = $data['ticket_type'];
    $ticket_column = $ticket_type . '_tickets';
    $used_column = 'used_' . $ticket_type;

    // Check if all tickets of this type are already used
    if ($booking[$used_column] >= $booking[$ticket_column]) {
        echo json_encode([
            'success' => false,
            'message' => 'All tickets of this type have already been used'
        ]);
        exit;
    }

    // Create a new individual ticket record
    $now = date('Y-m-d H:i:s');
    $admin_id = $_SESSION['user_id'] ?? null;
    
    $stmt = $conn->prepare("
        INSERT INTO individual_tickets 
        (booking_id, ticket_type, ticket_price, status, used_at, used_by)
        VALUES (?, ?, ?, 'used', ?, ?)
    ");

    // Get ticket price based on type (you should adjust these values)
    $ticket_prices = [
        'adult' => 200,
        'child_0_5' => 0,
        'child_5_12' => 100,
        'senior' => 150
    ];

    $ticket_price = $ticket_prices[$ticket_type] ?? 0;
    
    $stmt->bind_param("isdsi", 
        $data['booking_id'],
        $ticket_type,
        $ticket_price,
        $now,
        $admin_id
    );

    if ($stmt->execute()) {
        // Check if all tickets in the booking are now used
        $stmt = $conn->prepare("
            SELECT 
                b.*,
                COUNT(CASE WHEN it.status = 'used' THEN 1 END) as used_tickets,
                (b.adult_tickets + b.child_0_5_tickets + b.child_5_12_tickets + b.senior_tickets) as total_tickets
            FROM bookings b
            LEFT JOIN individual_tickets it ON b.id = it.booking_id
            WHERE b.id = ?
            GROUP BY b.id
        ");
        
        $stmt->bind_param("i", $data['booking_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $ticket_counts = $result->fetch_assoc();

        // If all tickets are used, update booking status
        if ($ticket_counts['used_tickets'] == $ticket_counts['total_tickets']) {
            $stmt = $conn->prepare("
                UPDATE bookings 
                SET status = 'used', used_at = ?, used_by = ? 
                WHERE id = ?
            ");
            $stmt->bind_param("sii", $now, $admin_id, $data['booking_id']);
            $stmt->execute();
        }

        // Get ticket type display name
        $ticketTypeNames = [
            'adult' => 'Adult',
            'child_0_5' => 'Child (0-5)',
            'child_5_12' => 'Child (5-12)',
            'senior' => 'Senior'
        ];
        
        $ticketTypeName = $ticketTypeNames[$ticket_type] ?? $ticket_type;

        echo json_encode([
            'success' => true,
            'message' => 'Ticket validated successfully',
            'ticket' => [
                'booking_id' => $data['booking_id'],
                'ticket_type' => $ticketTypeName,
                'used_count' => $ticket_counts['used_tickets'],
                'total_tickets' => $ticket_counts['total_tickets'],
                'all_used' => ($ticket_counts['used_tickets'] == $ticket_counts['total_tickets']),
                'used_at' => $now
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating ticket status']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Tickets - SafariGate Zoo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://unpkg.com/html5-qrcode"></script>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="scanner-container">
        <h2><i class="fas fa-qrcode"></i> Scan Visitor Tickets</h2>
        
        <?php if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin'): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> You must be logged in as an admin to use this feature.
            </div>
        <?php else: ?>
            <div id="reader"></div>
            <div id="result"></div>
            <div id="scan-history">
                <h3>Recent Scans</h3>
                <div id="history-list"></div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Only initialize scanner if admin is logged in
        <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && $_SESSION['role'] === 'admin'): ?>
        
        // Keep track of scan history
        const scanHistory = [];
        
        function updateScanHistory(result) {
            // Add to history
            scanHistory.unshift({
                time: new Date(),
                result: result
            });
            
            // Limit history to 10 items
            if (scanHistory.length > 10) {
                scanHistory.pop();
            }
            
            // Update display
            const historyList = document.getElementById('history-list');
            historyList.innerHTML = '';
            
            scanHistory.forEach(item => {
                const timeString = item.time.toLocaleTimeString();
                const historyItem = document.createElement('div');
                historyItem.className = `history-item ${item.result.success ? 'success' : 'error'}`;
                historyItem.innerHTML = `
                    <span class="time">${timeString}</span>
                    <span class="message">${item.result.message}</span>
                `;
                historyList.appendChild(historyItem);
            });
        }

        function onScanSuccess(decodedText, decodedResult) {
            console.log(`QR Code detected: ${decodedText}`);
            
            // Try to parse the QR code data
            try {
                let qrData;
                
                // Try to parse as JSON first
                try {
                    qrData = JSON.parse(decodedText);
                } catch (e) {
                    // If not JSON, assume it's just a booking ID
                    qrData = { booking_id: parseInt(decodedText) };
                }
                
                // Ensure booking_id or ticket_id exists and is valid
                if ((!qrData.booking_id && !qrData.ticket_id) || 
                    (qrData.booking_id && isNaN(qrData.booking_id)) ||
                    (qrData.ticket_id && isNaN(qrData.ticket_id))) {
                    throw new Error("Invalid QR code format");
                }
                
                // Process the scan
                fetch('scan_ticket.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(qrData)
                })
                .then(response => response.json())
                .then(result => {
                    console.log('Success:', result);
                    const resultDiv = document.getElementById('result');
                    
                    if (result.success) {
                        // Check if we have ticket or booking data
                        const isIndividualTicket = result.ticket !== undefined;
                        
                        resultDiv.innerHTML = `
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i>
                                ${result.message}
                            </div>
                            <div class="booking-details">
                                <h3>${isIndividualTicket ? 'Ticket Details' : 'Booking Details'}</h3>
                                ${isIndividualTicket ? `
                                    <p><strong>Ticket ID:</strong> ${result.ticket.id}</p>
                                    <p><strong>Booking ID:</strong> ${result.ticket.booking_id}</p>
                                    <p><strong>Visitor:</strong> ${result.ticket.visitor_name}</p>
                                    <p><strong>Ticket Type:</strong> ${result.ticket.ticket_type}</p>
                                    <p><strong>Price:</strong> ₹${result.ticket.ticket_price}</p>
                                    <p><strong>Visit Date:</strong> ${result.ticket.visit_date}</p>
                                ` : `
                                    <p><strong>Booking ID:</strong> ${result.booking.id}</p>
                                    <p><strong>Name:</strong> ${result.booking.name}</p>
                                    <p><strong>Visit Date:</strong> ${result.booking.visit_date}</p>
                                    <p><strong>Total Tickets:</strong> ${result.booking.total_tickets}</p>
                                `}
                            </div>
                        `;
                        
                        // Play success sound
                        new Audio('sounds/success.mp3').play();
                    } else {
                        resultDiv.innerHTML = `
                            <div class="alert alert-error">
                                <i class="fas fa-exclamation-circle"></i>
                                ${result.message}
                            </div>
                        `;
                        
                        // Play error sound
                        new Audio('sounds/error.mp3').play();
                    }
                    
                    // Update scan history
                    updateScanHistory(result);
                })
                .catch(error => {
                    console.error('Error:', error);
                    const resultDiv = document.getElementById('result');
                    resultDiv.innerHTML = `
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            Error processing request. Please try again.
                        </div>
                    `;
                });
            } catch (error) {
                console.error('Invalid QR code data:', error);
                const resultDiv = document.getElementById('result');
                resultDiv.innerHTML = `
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        Invalid QR code format.
                    </div>
                `;
            }
        }

        const html5QrcodeScanner = new Html5QrcodeScanner(
            "reader", { fps: 10, qrbox: 250 }
        );
        html5QrcodeScanner.render(onScanSuccess);
        
        <?php endif; ?>
    </script>

    <style>
        .scanner-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        #reader {
            width: 100%;
            margin: 2rem 0;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            text-align: center;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .booking-details {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1.5rem;
        }
        
        .booking-details h3 {
            margin-top: 0;
            color: var(--main);
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }
        
        #scan-history {
            margin-top: 2rem;
            border-top: 1px solid #dee2e6;
            padding-top: 1rem;
        }
        
        #scan-history h3 {
            color: var(--main);
            margin-bottom: 1rem;
        }
        
        .history-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 0.5rem;
        }
        
        .history-item.success {
            background: #f0f8f1;
            border-left: 3px solid #28a745;
        }
        
        .history-item.error {
            background: #fff5f5;
            border-left: 3px solid #dc3545;
        }
        
        .history-item .time {
            font-weight: bold;
            color: #6c757d;
        }
    </style>
</body>
</html>