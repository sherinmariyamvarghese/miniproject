<?php
session_start();
require_once 'connect.php';

// Redirect if no booking session exists
if (!isset($_SESSION['booking'])) {
    header('Location: booking.php');
    exit;
}

$booking = $_SESSION['booking'];

// Calculate totals
$adultAmount = $booking['adult_tickets'] * 80;
$child5Amount = $booking['child_5_12_tickets'] * 40;
$seniorAmount = $booking['senior_tickets'] * 40;
$cameraAmount = $booking['camera_video'] ? 100 : 0;
$totalAmount = $adultAmount + $child5Amount + $seniorAmount + $cameraAmount;

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
            // Commit transaction
            $conn->commit();
            
            // Clear booking session
            unset($_SESSION['booking']);
            
            // Set success message
            $_SESSION['success_message'] = "Booking confirmed successfully!";
            
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
    <title>Payment Details - Zoo Ticket Booking</title>
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

        .booking-summary {
            background: var(--bg);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .payment-method {
            padding: 15px;
            border: 2px solid var(--main);
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
        }

        .payment-method.selected {
            background: var(--main);
            color: var(--white);
        }

        .payment-details {
            margin: 20px 0;
        }

        .payment-details input {
            width: 100%;
            padding: 10px;
            margin: 8px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1.6rem;
        }

        .confirm-btn {
            width: 100%;
            padding: 12px;
            background: var(--main);
            color: var(--white);
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1.6rem;
            font-weight: bold;
        }

        .personal-details {
            margin: 20px 0;
            padding: 20px;
            background: var(--bg);
            border-radius: 8px;
        }

        .personal-details input,
        .personal-details textarea {
            width: 100%;
            padding: 10px;
            margin: 8px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1.6rem;
        }

        .personal-details textarea {
            height: 100px;
            resize: vertical;
        }

        .validation-message {
            color: red;
            font-size: 1.4rem;
            margin-top: 5px;
        }
        
        input:valid, textarea:valid {
            border-color: #28a745 !important;
        }
        
        input:invalid, textarea:invalid {
            border-color: #dc3545 !important;
        }

        .page-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            gap: 20px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            background: var(--bg);
            color: var(--main);
            border-radius: 5px;
            text-decoration: none;
            font-size: 1.6rem;
            transition: all 0.3s ease;
        }

        .back-btn i {
            margin-right: 8px;
        }

        .back-btn:hover {
            background: var(--main);
            color: var(--white);
            transform: translateX(-5px);
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <br><br><br><br><br><br><br><br><br>
    
    <div class="payment-container">
        <div class="page-header">
            <a href="booking.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Booking
            </a>
            <h2><i class="fas fa-credit-card"></i> Payment Details</h2>
        </div>
        
        <div class="booking-summary">
            <h3>Booking Summary</h3>
            <p>Visit Date: <?php echo date('d M Y', strtotime($booking['visit_date'])); ?></p>
            <p>Adult Tickets: <?php echo $booking['adult_tickets']; ?> (₹<?php echo $adultAmount; ?>)</p>
            <p>Child 0-5 Tickets: <?php echo $booking['child_0_5_tickets']; ?> (Free)</p>
            <p>Child 5-12 Tickets: <?php echo $booking['child_5_12_tickets']; ?> (₹<?php echo $child5Amount; ?>)</p>
            <p>Senior Tickets: <?php echo $booking['senior_tickets']; ?> (₹<?php echo $seniorAmount; ?>)</p>
            <?php if ($booking['camera_video']): ?>
                <p>Camera/Video Access: Yes (₹<?php echo $cameraAmount; ?>)</p>
            <?php endif; ?>
            <h4>Total Amount: ₹<?php echo $totalAmount; ?></h4>
        </div>

        <form method="post" id="paymentForm">
            <div class="personal-details">
                <h3>Personal Details</h3>
                <input type="text" name="name" placeholder="Full Name" required>
                <input type="email" name="email" placeholder="Email Address" required>
                <input type="tel" name="phone" placeholder="Phone Number" pattern="[0-9]{10}" required>
                <input type="text" name="city" placeholder="City" required>
                <textarea name="address" placeholder="Full Address" required></textarea>
            </div>

            <div class="payment-methods">
                <div class="payment-method" onclick="selectPayment('card')">
                    <i class="fas fa-credit-card fa-2x"></i>
                    <p>Credit/Debit Card</p>
                </div>
                <div class="payment-method" onclick="selectPayment('upi')">
                    <i class="fas fa-mobile-alt fa-2x"></i>
                    <p>UPI Payment</p>
                </div>
                <div class="payment-method" onclick="selectPayment('netbanking')">
                    <i class="fas fa-university fa-2x"></i>
                    <p>Net Banking</p>
                </div>
            </div>

            <div class="payment-details" id="cardDetails" style="display: none;">
                <input type="text" placeholder="Card Number" pattern="[0-9]{16}" maxlength="16">
                <div style="display: flex; gap: 10px;">
                    <input type="text" placeholder="MM/YY" pattern="[0-9]{2}/[0-9]{2}" maxlength="5">
                    <input type="text" placeholder="CVV" pattern="[0-9]{3}" maxlength="3">
                </div>
                <input type="text" placeholder="Card Holder Name">
            </div>

            <div class="payment-details" id="upiDetails" style="display: none;">
                <input type="text" placeholder="Enter UPI ID">
            </div>

            <div class="payment-details" id="netbankingDetails" style="display: none;">
                <select class="form-control">
                    <option>Select Bank</option>
                    <option>State Bank of India</option>
                    <option>HDFC Bank</option>
                    <option>ICICI Bank</option>
                    <option>Axis Bank</option>
                </select>
            </div>

            <button type="submit" class="confirm-btn">Confirm Payment</button>
        </form>
    </div>

    <script>
        function selectPayment(method) {
            document.querySelectorAll('.payment-method').forEach(el => el.classList.remove('selected'));
            document.querySelectorAll('.payment-details').forEach(el => el.style.display = 'none');
            
            event.currentTarget.classList.add('selected');
            document.getElementById(method + 'Details').style.display = 'block';
        }

        // Live validation for payment form
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('paymentForm');
            
            // Validate name
            const nameInput = form.querySelector('input[name="name"]');
            nameInput.addEventListener('input', function() {
                const isValid = /^[a-zA-Z\s]{3,50}$/.test(this.value);
                this.setCustomValidity(isValid ? '' : 'Please enter a valid name )');
                showValidationMessage(this);
            });

            // Validate email
            const emailInput = form.querySelector('input[name="email"]');
            emailInput.addEventListener('input', function() {
                const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value);
                this.setCustomValidity(isValid ? '' : 'Please enter a valid email address');
                showValidationMessage(this);
            });

            // Validate phone
            const phoneInput = form.querySelector('input[name="phone"]');
            phoneInput.addEventListener('input', function() {
                const isValid = /^[0-9]{10}$/.test(this.value);
                this.setCustomValidity(isValid ? '' : 'Please enter a valid 10-digit phone number');
                showValidationMessage(this);
            });

            // Validate city
            const cityInput = form.querySelector('input[name="city"]');
            cityInput.addEventListener('input', function() {
                const isValid = /^[a-zA-Z\s]{2,50}$/.test(this.value);
                this.setCustomValidity(isValid ? '' : 'Please enter a valid city name');
                showValidationMessage(this);
            });

            // Validate address
            const addressInput = form.querySelector('textarea[name="address"]');
            addressInput.addEventListener('input', function() {
                const isValid = this.value.length >= 10;
                this.setCustomValidity(isValid ? '' : 'Address must be at least 10 characters long');
                showValidationMessage(this);
            });

            // Card validation
            const cardNumberInput = document.querySelector('input[placeholder="Card Number"]');
            if (cardNumberInput) {
                cardNumberInput.addEventListener('input', function() {
                    this.value = this.value.replace(/\D/g, '').slice(0, 16);
                    const isValid = /^[0-9]{16}$/.test(this.value);
                    this.setCustomValidity(isValid ? '' : 'Please enter a valid 16-digit card number');
                    showValidationMessage(this);
                });
            }

            // CVV validation
            const cvvInput = document.querySelector('input[placeholder="CVV"]');
            if (cvvInput) {
                cvvInput.addEventListener('input', function() {
                    this.value = this.value.replace(/\D/g, '').slice(0, 3);
                    const isValid = /^[0-9]{3}$/.test(this.value);
                    this.setCustomValidity(isValid ? '' : 'Please enter a valid 3-digit CVV');
                    showValidationMessage(this);
                });
            }

            function showValidationMessage(element) {
                const existingMessage = element.nextElementSibling;
                if (existingMessage && existingMessage.classList.contains('validation-message')) {
                    existingMessage.remove();
                }

                if (element.validationMessage) {
                    const messageDiv = document.createElement('div');
                    messageDiv.className = 'validation-message';
                    messageDiv.style.color = 'red';
                    messageDiv.style.fontSize = '1.4rem';
                    messageDiv.style.marginTop = '5px';
                    messageDiv.textContent = element.validationMessage;
                    element.parentNode.insertBefore(messageDiv, element.nextSibling);
                }
            }
        });
    </script>

    <?php include 'footer.php'; ?>
</body>
</html> 