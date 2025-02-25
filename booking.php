<?php
session_start();
require_once 'connect.php';

// Function to check current bookings for a date
function getTotalBookingsForDate($conn, $date) {
    $sql = "SELECT SUM(adult_tickets + child_0_5_tickets + child_5_12_tickets + senior_tickets) as total 
            FROM bookings 
            WHERE visit_date = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['total'] ?? 0;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visitDate = $_POST['visit-date'];
    $adultTickets = intval($_POST['adult-tickets']);
    $child0Tickets = intval($_POST['child-0-5-tickets']);
    $child5Tickets = intval($_POST['child-5-12-tickets']);
    $seniorTickets = intval($_POST['senior-citizen-tickets']);
    $cameraVideo = isset($_POST['camera-video']) ? 1 : 0;
    $document = $_FILES['document'];
    
    $totalNewTickets = $adultTickets + $child0Tickets + $child5Tickets + $seniorTickets;
    
    // Check current bookings
    $currentBookings = getTotalBookingsForDate($conn, $visitDate);
    $remainingCapacity = 100 - $currentBookings;
    
    // Validation
    if (empty($visitDate)) {
        $errors['visit-date'] = 'Please select a visit date.';
    }
    if ($adultTickets < 0) {
        $errors['adult-tickets'] = 'Invalid adult ticket quantity.';
    }
    if ($child0Tickets < 0) {
        $errors['child-0-5-tickets'] = 'Invalid child (0-5 years) ticket quantity.';
    }
    if ($child5Tickets < 0) {
        $errors['child-5-12-tickets'] = 'Invalid child (5-12 years) ticket quantity.';
    }
    if ($seniorTickets < 0) {
        $errors['senior-tickets'] = 'Invalid senior citizen ticket quantity.';
    }
    if ($totalNewTickets > $remainingCapacity) {
        $errors['capacity'] = "Sorry, only $remainingCapacity tickets available for this date.";
    }
    if (empty($document['name'])) {
        $errors['document'] = 'Please upload a document.';
    }

    $selectedDate = new DateTime($visitDate);
    if ($selectedDate->format('N') == 1) {
        $errors['visit-date'] = 'Bookings are not available on Mondays.';
    }

    if (empty($errors)) {
        // Store booking data in session
        $_SESSION['booking'] = [
            'visit_date' => $visitDate,
            'adult_tickets' => $adultTickets,
            'child_0_5_tickets' => $child0Tickets,
            'child_5_12_tickets' => $child5Tickets,
            'senior_tickets' => $seniorTickets,
            'camera_video' => $cameraVideo,
            'document' => $document['name']
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
    <title>Zoo Ticket Booking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .booking-container {
            max-width: 600px;
            margin: 20px auto;
            background: var(--white);
            padding: 20px;
            border-radius: 8px;
            box-shadow: var(--box-shadow);
        }
        
        .booking-header {
            text-align: center;
            margin-bottom: 30px;
            color: var(--main);
        }
        
        .ticket-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            margin-bottom: 10px;
            background: var(--bg);
            border-radius: 4px;
        }
        
        .ticket-row input[type="number"] {
            width: 70px;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1.6rem;
        }
        
        .total-section {
            background: var(--bg);
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
            font-size: 1.6rem;
        }
        
        .camera-section {
            padding: 10px;
            background: var(--bg);
            border-radius: 4px;
            margin: 10px 0;
            font-size: 1.6rem;
        }
        
        .booking-btn {
            width: 100%;
            padding: 10px;
            background: var(--main);
            color: var(--white);
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1.6rem;
        }
        
        .booking-btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        
        .error-message {
            color: #dc3545;
            font-size: 1.4rem;
            margin-top: 5px;
        }
        
        .success-message {
            color: #28a745;
            font-size: 1.4rem;
            margin-top: 5px;
        }
        
        .availability-status {
            margin-top: 10px;
            padding: 5px;
            font-size: 1.6rem;
            text-align: center;
        }
        
        label {
            font-size: 1.6rem;
            color: var(--black);
        }
        
        input[type="date"] {
            font-size: 1.6rem;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 100%;
        }
        
        input[type="file"] {
            font-size: 1.6rem;
            width: 100%;
        }
        
        @media (max-width: 768px) {
            .booking-container {
                padding: 15px;
                margin: 10px;
            }
            .ticket-row {
                flex-direction: column;
                gap: 5px;
            }
            .ticket-row input {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>
    
    <br><br><br><br><br><br><br><br><br>
    
    <div class="booking-container">
        <div class="booking-header">
            <h1><i class="fas fa-ticket-alt"></i> Book Your Ticket</h1>
            <p>Operating Hours: 9:00 AM - 6:00 PM</p>
        </div>
        
        <form id="bookingForm" method="post" enctype="multipart/form-data">
            <div class="ticket-section">
                <div class="ticket-row">
                    <label>Adult (₹80)</label>
                    <input type="number" name="adult-tickets" min="0" value="0" onchange="calculateTotal()">
                    <?php if (isset($errors['adult-tickets'])): ?>
                        <div class="error-message"><?php echo $errors['adult-tickets']; ?></div>
                    <?php endif; ?>
                </div>

                <div class="ticket-row">
                    <label>Child (0-5 Years) (Free)</label>
                    <input type="number" name="child-0-5-tickets" min="0" value="0" onchange="calculateTotal()">
                    <?php if (isset($errors['child-0-5-tickets'])): ?>
                        <div class="error-message"><?php echo $errors['child-0-5-tickets']; ?></div>
                    <?php endif; ?>
                </div>

                <div class="ticket-row">
                    <label>Child (5-12 Years) (₹40)</label>
                    <input type="number" name="child-5-12-tickets" min="0" value="0" onchange="calculateTotal()">
                    <?php if (isset($errors['child-5-12-tickets'])): ?>
                        <div class="error-message"><?php echo $errors['child-5-12-tickets']; ?></div>
                    <?php endif; ?>
                </div>

                <div class="ticket-row">
                    <label>Senior Citizen (₹40)</label>
                    <input type="number" name="senior-citizen-tickets" min="0" value="0" onchange="calculateTotal()">
                    <?php if (isset($errors['senior-tickets'])): ?>
                        <div class="error-message"><?php echo $errors['senior-tickets']; ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="camera-section">
                <label>
                    <input type="checkbox" name="camera-video" onchange="calculateTotal()">
                    Camera/Video Access (₹100 per booking)
                </label>
            </div>

            <div class="total-section">
                <div class="total-row">
                    <strong>Total Tickets:</strong> <span id="totalTickets">0</span>
                </div>
                <div class="total-row">
                    <strong>Ticket Amount:</strong> <span id="ticketAmount">₹0</span>
                </div>
                <div class="total-row">
                    <strong>Camera Fee:</strong> <span id="cameraAmount">₹0</span>
                </div>
                <div class="total-row">
                    <strong>Total Amount:</strong> <span id="totalAmount">₹0</span>
                </div>
            </div>

            <div class="ticket-row">
                <label>Visit Date</label>
                <input type="date" name="visit-date" id="visit-date" required>
                <?php if (isset($errors['visit-date'])): ?>
                    <div class="error-message"><?php echo $errors['visit-date']; ?></div>
                <?php endif; ?>
            </div>

            <div id="availability-status" class="availability-status"></div>

            <div class="ticket-row">
                <label>Please upload a valid ID proof</label>
                <input type="file" name="document" required accept=".pdf,.jpg,.jpeg,.png">
                <?php if (isset($errors['document'])): ?>
                    <div class="error-message"><?php echo $errors['document']; ?></div>
                <?php endif; ?>
            </div>

            <?php if (isset($errors['capacity'])): ?>
                <div class="error-message"><?php echo $errors['capacity']; ?></div>
            <?php endif; ?>

            <button type="submit" class="booking-btn">Book Tickets</button>
        </form>
    </div>


    <script>
        function calculateTotal() {
            const form = document.getElementById('bookingForm');
            const adultTickets = parseInt(form['adult-tickets'].value) || 0;
            const child0Tickets = parseInt(form['child-0-5-tickets'].value) || 0;
            const child5Tickets = parseInt(form['child-5-12-tickets'].value) || 0;
            const seniorTickets = parseInt(form['senior-citizen-tickets'].value) || 0;
            const cameraVideo = form['camera-video'].checked;

            const totalTickets = adultTickets + child0Tickets + child5Tickets + seniorTickets;
            const adultAmount = adultTickets * 80;
            const child5Amount = child5Tickets * 40;
            const seniorAmount = seniorTickets * 40;
            const cameraAmount = cameraVideo ? 100 : 0;
            const totalAmount = adultAmount + child5Amount + seniorAmount + cameraAmount;

            document.getElementById('totalTickets').textContent = totalTickets;
            document.getElementById('ticketAmount').textContent = `₹${adultAmount + child5Amount + seniorAmount}`;
            document.getElementById('cameraAmount').textContent = `₹${cameraAmount}`;
            document.getElementById('totalAmount').textContent = `₹${totalAmount}`;
        }

        const dateInput = document.getElementById('visit-date');
        dateInput.min = new Date().toISOString().split('T')[0];

        dateInput.addEventListener('input', function(e) {
            const selectedDate = new Date(this.value);
            if (selectedDate.getDay() === 1) {
                alert('Bookings are not available on Mondays. Please select a different date.');
                this.value = '';
            } else {
                checkAvailability(this.value);
            }
        });

        dateInput.addEventListener('keydown', e => e.preventDefault());
        calculateTotal();
    </script>
    
    <?php include 'footer.php'; ?>

</body>
</html>