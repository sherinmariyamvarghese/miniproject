<?php
session_start();
require_once 'connect.php';

// Check if booking data exists in session
if (!isset($_SESSION['booking'])) {
    header('Location: booking.php');
    exit();
}

$booking = $_SESSION['booking'];
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Begin transaction
        $conn->begin_transaction();

        // Insert into bookings table
        $sql = "INSERT INTO bookings (
            visit_date, adult_tickets, child_0_5_tickets, child_5_12_tickets, 
            senior_tickets, camera_video, document_path, total_amount, payment_status,
            name, email, phone, city, address
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "siiiiisdsssss",
            $booking['visit_date'],
            $booking['adult_tickets'],
            $booking['child_0_5_tickets'],
            $booking['child_5_12_tickets'],
            $booking['senior_tickets'],
            $booking['camera_video'],
            $booking['document'],
            $totalAmount,
            $_POST['name'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['city'],
            $_POST['address']
        );

        if ($stmt->execute()) {
            // Get booking ID
            $booking_id = $conn->insert_id;
            
            // Commit transaction
            $conn->commit();
            
            // Clear booking session
            unset($_SESSION['booking']);
            
            // Set success message with booking details
            $_SESSION['success_message'] = "Thank you for your booking! Your booking ID is #" . $booking_id . 
                                         ". A confirmation email has been sent to " . $_POST['email'];
            
            // Redirect to confirmation page
            header('Location: booking-confirmation.php');
            exit;
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error_message = "Error processing booking. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - SafariGate Zoo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .payment-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: var(--white);
            border-radius: 8px;
            box-shadow: var(--box-shadow);
        }

        .payment-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .payment-header h2 {
            font-size: 3rem;
            color: var(--main);
        }

        .payment-section {
            background: var(--bg);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .payment-section h3 {
            font-size: 2rem;
            margin-bottom: 15px;
            color: var(--black);
            display: flex;
            align-items: center;
        }

        .payment-section h3 i {
            margin-right: 10px;
            color: var(--main);
        }

        .booking-summary {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px dashed #ddd;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .payment-method {
            background: var(--white);
            padding: 15px;
            border-radius: 8px;
            border: 2px solid #ddd;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .payment-method:hover {
            border-color: var(--main);
            transform: translateY(-2px);
        }

        .payment-method.selected {
            border-color: var(--main);
            background: #fff8e1;
        }

        .payment-method i {
            font-size: 2.5rem;
            margin-bottom: 10px;
            color: var(--main);
        }

        .payment-method p {
            font-size: 1.5rem;
            color: var(--black);
        }

        .total-amount {
            font-size: 2.2rem;
            color: var(--main);
            font-weight: bold;
            text-align: right;
            padding: 15px;
            background: #fff8e1;
            border-radius: 8px;
            margin-top: 20px;
        }

        .pay-btn {
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

        .pay-btn:hover {
            background: #ff5500;
            transform: translateY(-2px);
        }

        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 1.4rem;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="payment-container">
        <div class="payment-header">
            <h2><i class="fas fa-credit-card"></i> Payment Details</h2>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="payment-section">
            <h3><i class="fas fa-receipt"></i> Booking Summary</h3>
            <div class="booking-summary">
                <div class="summary-row">
                    <span>Visit Date</span>
                    <span><?php echo date('d M Y', strtotime($booking['visit_date'])); ?></span>
                </div>
                <?php if ($booking['adult_tickets'] > 0): ?>
                    <div class="summary-row">
                        <span>Adult Tickets</span>
                        <span><?php echo $booking['adult_tickets']; ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($booking['child_0_5_tickets'] > 0): ?>
                    <div class="summary-row">
                        <span>Child (0-5) Tickets</span>
                        <span><?php echo $booking['child_0_5_tickets']; ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($booking['child_5_12_tickets'] > 0): ?>
                    <div class="summary-row">
                        <span>Child (5-12) Tickets</span>
                        <span><?php echo $booking['child_5_12_tickets']; ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($booking['senior_tickets'] > 0): ?>
                    <div class="summary-row">
                        <span>Senior Tickets</span>
                        <span><?php echo $booking['senior_tickets']; ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($booking['camera_video']): ?>
                    <div class="summary-row">
                        <span>Camera/Video Access</span>
                        <span>Yes</span>
                    </div>
                <?php endif; ?>
                <div class="summary-row">
                    <span>Total Amount</span>
                    <span>₹<?php echo number_format($booking['total_amount'], 2); ?></span>
                </div>
            </div>
        </div>

        <form method="post" id="payment-form">
            <div class="payment-section">
                <h3><i class="fas fa-money-bill-wave"></i> Select Payment Method</h3>
                <div class="payment-methods">
                    <div class="payment-method" onclick="selectPayment('credit-card')">
                        <input type="radio" name="payment-method" value="credit-card" id="credit-card" hidden>
                        <i class="fas fa-credit-card"></i>
                        <p>Credit Card</p>
                    </div>
                    <div class="payment-method" onclick="selectPayment('debit-card')">
                        <input type="radio" name="payment-method" value="debit-card" id="debit-card" hidden>
                        <i class="fas fa-credit-card"></i>
                        <p>Debit Card</p>
                    </div>
                    <div class="payment-method" onclick="selectPayment('upi')">
                        <input type="radio" name="payment-method" value="upi" id="upi" hidden>
                        <i class="fas fa-mobile-alt"></i>
                        <p>UPI</p>
                    </div>
                </div>
            </div>

            <div class="total-amount">
                Total Payable: ₹<?php echo number_format($booking['total_amount'], 2); ?>
            </div>

            <button type="submit" class="pay-btn">
                <i class="fas fa-lock"></i> Pay Securely
            </button>
        </form>
    </div>

    <script>
        function selectPayment(method) {
            // Remove selected class from all payment methods
            document.querySelectorAll('.payment-method').forEach(el => {
                el.classList.remove('selected');
            });
            
            // Add selected class to clicked method
            document.getElementById(method).closest('.payment-method').classList.add('selected');
            
            // Check the radio button
            document.getElementById(method).checked = true;
        }
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>