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
        // Verify Razorpay payment ID exists
        if (!isset($_POST['razorpay_payment_id'])) {
            throw new Exception("Payment verification failed");
        }

        // Begin transaction
        $conn->begin_transaction();

        // Update ticket_rates table to increment booked_ticket count
        $totalTickets = $booking['adult_tickets'] + $booking['child_0_5_tickets'] + 
                       $booking['child_5_12_tickets'] + $booking['senior_tickets'];
        
        $updateRates = "UPDATE ticket_rates SET booked_ticket = booked_ticket + ? WHERE id = 1";
        $stmt = $conn->prepare($updateRates);
        $stmt->bind_param("i", $totalTickets);
        $stmt->execute();

        // Insert into bookings table
        $sql = "INSERT INTO bookings (
            visit_date, adult_tickets, child_0_5_tickets, child_5_12_tickets, 
            senior_tickets, camera_video, document_path, total_amount, payment_status,
            name, email, phone, city, address, razorpay_payment_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "siiiisdsssssss",
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

        if ($stmt->execute()) {
            $booking_id = $conn->insert_id;
            $conn->commit();

            // Create bills directory if it doesn't exist
            $billsDir = str_replace('\\', '/', __DIR__ . '/bills');
            if (!file_exists($billsDir)) {
                if (!mkdir($billsDir, 0777, true)) {
                    throw new Exception("Failed to create bills directory");
                }
                // Ensure proper permissions on Windows
                chmod($billsDir, 0777);
            }

            // Create booking_details array with all necessary information
            $booking_details = array_merge($booking, [
                'booking_id' => $booking_id,
                'razorpay_payment_id' => $_POST['razorpay_payment_id']
            ]);

            // Generate PDF bill
            require_once 'vendor/autoload.php';
            require_once 'generate_bill_pdf.php';
            
            try {
                $pdf_path = generateBillPDF($booking_details);
                
                // Update booking record with PDF path
                $update_pdf = "UPDATE bookings SET bill_pdf_path = ? WHERE id = ?";
                $stmt = $conn->prepare($update_pdf);
                $stmt->bind_param("si", $pdf_path, $booking_id);
                $stmt->execute();

                // Store booking data for confirmation processing
                $booking_details['bill_pdf_path'] = $pdf_path;
            } catch (Exception $e) {
                error_log("PDF Generation Error: " . $e->getMessage());
                // Continue with the booking process even if PDF generation fails
                $booking_details['bill_pdf_path'] = null;
            }

            try {
                // Include and process booking confirmation
                require_once 'booking_confirmation.php';
                $notification_status = processBookingConfirmation($booking_details);
                
                // Store notification status in session
                $_SESSION['notification_status'] = $notification_status;
                
                // Store booking data in session for booking_success.php
                $_SESSION['booking_data'] = $booking_details;
                
                // Clear the original booking session variable
                unset($_SESSION['booking']);
                
                // Redirect to success page
                header('Location: booking_success.php');
                exit;
            } catch (Exception $e) {
                error_log("Error in confirmation processing: " . $e->getMessage());
                // Still redirect to success page, but with a flag indicating notification issues
                $_SESSION['booking_data'] = $booking_details;
                $_SESSION['notification_status'] = ['email' => false, 'sms' => false];
                header('Location: booking_success.php');
                exit;
            }
        }
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error processing booking: " . $e->getMessage();
    }
}

// Add this success message display in the HTML section
if (isset($_SESSION['success_message'])): ?>
    <div class="success-message">
        <i class="fas fa-check-circle"></i>
        <?php echo $_SESSION['success_message']; ?>
    </div>
<?php 
    unset($_SESSION['success_message']);
endif;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - SafariGate Zoo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
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
                <h3><i class="fas fa-money-bill-wave"></i> Payment Method</h3>
                <div class="total-amount">
                    Total Payable: ₹<?php echo number_format($booking['total_amount'], 2); ?>
                </div>
                <button type="button" class="pay-btn" onclick="openRazorpay()">
                    <i class="fas fa-lock"></i> Pay Securely
                </button>
            </div>
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

        function openRazorpay() {
            const options = {
                key: 'rzp_test_1TSGXPk46TbXBv',
                amount: <?php echo $booking['total_amount'] * 100 ?>, // Amount in paise
                currency: 'INR',
                name: 'SafariGate Zoo',
                description: 'Zoo Ticket Booking',
                image: 'path/to/your/logo.png',
                prefill: {
                    name: '<?php echo htmlspecialchars($booking['name']) ?>',
                    email: '<?php echo htmlspecialchars($booking['email']) ?>',
                    contact: '<?php echo htmlspecialchars($booking['phone']) ?>'
                },
                theme: {
                    color: '#ff6e01'
                },
                handler: function(response) {
                    // Create hidden form for submission
                    const form = document.getElementById('payment-form');
                    
                    // Add payment ID to form
                    const paymentInput = document.createElement('input');
                    paymentInput.type = 'hidden';
                    paymentInput.name = 'razorpay_payment_id';
                    paymentInput.value = response.razorpay_payment_id;
                    form.appendChild(paymentInput);
                    
                    // Add total amount to form
                    const amountInput = document.createElement('input');
                    amountInput.type = 'hidden';
                    amountInput.name = 'total_amount';
                    amountInput.value = '<?php echo $booking['total_amount'] ?>';
                    form.appendChild(amountInput);
                    
                    // Submit the form
                    form.submit();
                }
            };

            const rzp = new Razorpay(options);
            rzp.open();
        }
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>