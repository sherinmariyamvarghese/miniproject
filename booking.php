<?php
session_start();
require_once 'connect.php';

// Function to check current bookings for a date
function getTotalBookingsForDate($conn, $date) {
    $sql = "SELECT SUM(adult_tickets + child_0_5_tickets + child_5_12_tickets + senior_tickets) as total 
            FROM bookings 
            WHERE visit_date = ? AND payment_status != 'failed'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['total'] ?? 0;
}

// Function to check ticket availability
function getAvailableTickets($conn, $date) {
    // Get max tickets per day from settings
    $maxTickets = $conn->query("SELECT max_tickets_per_day FROM ticket_rates WHERE id = 1")->fetch_assoc()['max_tickets_per_day'];
    
    // Get current bookings
    $bookedTickets = getTotalBookingsForDate($conn, $date);
    
    return $maxTickets - $bookedTickets;
}

$errors = [];
$success = false;

// Get ticket rates with error handling
$ratesQuery = $conn->query("SELECT * FROM ticket_rates WHERE id = 1");
if (!$ratesQuery) {
    die("Error fetching rates: " . $conn->error);
}

$rates = $ratesQuery->fetch_assoc();
if (!$rates) {
    // Set default rates if none found in database
    $rates = [
        'adult_rate' => 100,
        'child_5_12_rate' => 50,
        'senior_rate' => 75,
        'camera_rate' => 25,
        'max_tickets_per_day' => 1000
    ];
    
    // Optionally, insert default rates into database
    $conn->query("INSERT INTO ticket_rates (id, adult_rate, child_5_12_rate, senior_rate, camera_rate, max_tickets_per_day,) 
                 VALUES (1, 100, 50, 75, 25, 1000)");
}

// Fetch user profile data if logged in
$user_profile = null;
if (isset($_SESSION['user'])) {
    $stmt = $conn->prepare("SELECT username, email, phone, address FROM users WHERE username = ?");
    $stmt->bind_param("s", $_SESSION['user']);
    $stmt->execute();
    $user_profile = $stmt->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    
    // Check availability
    if (!empty($visitDate) && $totalTickets > 0) {
        $availableTickets = getAvailableTickets($conn, $visitDate);
        if ($totalTickets > $availableTickets) {
            $errors[] = "Sorry, only {$availableTickets} tickets are available for the selected date";
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
            'address' => $visitorAddress
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
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            font-size: 1.4rem;
        }

        .availability-alert i {
            margin-right: 10px;
            color: #1976d2;
            font-size: 1.8rem;
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
                <li><i class="fas fa-clock"></i> Opening hours: 9:00 AM - 5:00 PM (Last entry at 4:00 PM)</li>
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

                <div id="availability-info" class="availability-alert" style="display: none;">
                    <i class="fas fa-info-circle"></i>
                    <span id="availability-message"></span>
                </div>
            </div>

            <div class="ticket-section">
                <h3><i class="fas fa-ticket-alt"></i> Select Tickets</h3>
                
                <div class="ticket-row">
                    <label>Adult (₹<?php echo $rates['adult_rate']; ?>)</label>
                    <input type="number" name="adult-tickets" min="0" value="<?php echo $_POST['adult-tickets'] ?? 0; ?>" onchange="calculateTotal()">
                </div>

                <div class="ticket-row">
                    <label>Child (0-5 Years) (Free)</label>
                    <input type="number" name="child-0-5-tickets" min="0" value="<?php echo $_POST['child-0-5-tickets'] ?? 0; ?>">
                </div>

                <div class="ticket-row">
                    <label>Child (5-12 Years) (₹<?php echo $rates['child_5_12_rate']; ?>)</label>
                    <input type="number" name="child-5-12-tickets" min="0" value="<?php echo $_POST['child-5-12-tickets'] ?? 0; ?>" onchange="calculateTotal()">
                </div>

                <div class="ticket-row">
                    <label>Senior Citizen (₹<?php echo $rates['senior_rate']; ?>)</label>
                    <input type="number" name="senior-tickets" min="0" value="<?php echo $_POST['senior-tickets'] ?? 0; ?>" onchange="calculateTotal()">
                </div>

                <div class="ticket-row">
                    <label>
                        <input type="checkbox" name="camera-video" onchange="calculateTotal()" 
                              <?php echo (isset($_POST['camera-video']) && $_POST['camera-video']) ? 'checked' : ''; ?>>
                        Camera/Video Access (₹<?php echo $rates['camera_rate']; ?>)
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
            
            fetch(`check_availability.php?date=${date}`)
                .then(response => response.json())
                .then(data => {
                    const availabilityInfo = document.getElementById('availability-info');
                    const availabilityMessage = document.getElementById('availability-message');
                    
                    if (data.available_tickets <= 0) {
                        availabilityInfo.style.background = '#ffebee';
                        availabilityMessage.innerHTML = `<strong>Sorry!</strong> No tickets available for this date. Please select another date.`;
                    } else if (data.available_tickets < 20) {
                        availabilityInfo.style.background = '#fff8e1';
                        availabilityMessage.innerHTML = `<strong>Limited availability!</strong> Only ${data.available_tickets} tickets left for this date.`;
                    } else {
                        availabilityInfo.style.background = '#e8f5e9';
                        availabilityMessage.innerHTML = `<strong>Good news!</strong> ${data.available_tickets} tickets available for this date.`;
                    }
                    
                    availabilityInfo.style.display = 'flex';
                })
                .catch(error => {
                    console.error('Error checking availability:', error);
                });
        }

        // Initialize total
        calculateTotal();
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>