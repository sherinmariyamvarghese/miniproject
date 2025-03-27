<?php
session_start();
require_once 'connect.php';
require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Check if pending donation exists in session
if (!isset($_SESSION['pending_donation'])) {
    header('Location: donation.php');
    exit;
}

$donation = $_SESSION['pending_donation'];

// Get user details if logged in
$user = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
}

// Function to send donation confirmation email
function sendDonationConfirmationEmail($donation, $conn) {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'sherinmariyamvarghese@gmail.com'; // Replace with your email
        $mail->Password = 'uhfo rkzj aewm kdpb'; // Replace with your password or app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Recipients
        $mail->setFrom('sherinmariyamvarghese@gmail.com', 'SafariGate Zoo');
        $mail->addAddress($donation['email']);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Thank You for Your Donation to SafariGate Zoo';
        
        $mail->Body = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                <div style="text-align: center; margin-bottom: 20px;">
                    <h1 style="color: #ff6e01;">Thank You for Your Donation!</h1>
                </div>
                
                <p>Dear ' . htmlspecialchars($donation['name']) . ',</p>
                
                <p>Thank you for your generous donation of ₹' . number_format($donation['amount'], 2) . ' to SafariGate Zoo. Your contribution will help us continue our mission of wildlife conservation and education.</p>
                
                <div style="background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <h3 style="margin-top: 0; color: #333;">Donation Details:</h3>
                    <p><strong>Amount:</strong> ₹' . number_format($donation['amount'], 2) . '</p>
                    <p><strong>Date:</strong> ' . date('F j, Y', strtotime($donation['date'])) . '</p>
                    <p><strong>Transaction ID:</strong> ' . $donation['payment_id'] . '</p>
                </div>
                
                <p>Your support makes a significant difference in the lives of our animals and helps us maintain our conservation efforts.</p>
                
                <p>Best regards,<br>SafariGate Zoo Team</p>
            </div>
        ';
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Donation - SafariGate</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .confirmation-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: var(--white);
            border-radius: 8px;
            box-shadow: var(--box-shadow);
        }

        .confirmation-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .confirmation-header h2 {
            font-size: 3rem;
            color: var(--main);
        }

        .donor-details, .donation-summary {
            background: var(--bg);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 2rem;
            margin-bottom: 15px;
            color: var(--black);
            display: flex;
            align-items: center;
        }

        .section-title i {
            margin-right: 10px;
            color: var(--main);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px dashed #ddd;
        }

        .detail-row:last-child {
            border-bottom: none;
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

        .btn-proceed {
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

        .btn-proceed:hover {
            background: #ff5500;
            transform: translateY(-2px);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1.6rem;
        }

        .message-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-style: italic;
            color: #666;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="confirmation-container">
        <div class="confirmation-header">
            <h2><i class="fas fa-hand-holding-heart"></i> Confirm Your Donation</h2>
        </div>

        <div class="donor-details">
            <h3 class="section-title"><i class="fas fa-user"></i> Donor Details</h3>
            
            <?php if ($user): ?>
                <!-- If user is logged in, display their details -->
                <div class="detail-row">
                    <span>Name:</span>
                    <span><?php echo htmlspecialchars($user['username']); ?></span>
                </div>
                <div class="detail-row">
                    <span>Email:</span>
                    <span><?php echo htmlspecialchars($user['email']); ?></span>
                </div>
                <div class="detail-row">
                    <span>Phone:</span>
                    <span><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></span>
                </div>
            <?php else: ?>
                <!-- If user is not logged in, show form to collect details -->
                <form id="donor-form">
                    <div class="form-group">
                        <label for="name">Name:</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone:</label>
                        <input type="tel" id="phone" name="phone" required>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <div class="donation-summary">
            <h3 class="section-title"><i class="fas fa-donate"></i> Donation Summary</h3>
            <div class="detail-row">
                <span>Donation Amount:</span>
                <span>₹<?php echo number_format($donation['amount'], 2); ?></span>
            </div>
            
            <?php if (!empty($donation['message'])): ?>
                <div class="message-box">
                    <p><strong>Your Message:</strong></p>
                    <p><?php echo htmlspecialchars($donation['message']); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="total-amount">
                Total Amount: ₹<?php echo number_format($donation['amount'], 2); ?>
            </div>
        </div>

        <form id="payment-form" method="POST" action="process_donation.php">
            <input type="hidden" name="donation" value='<?php echo json_encode($donation); ?>'>
            <button type="button" class="btn-proceed" id="proceedToPayment">
                <i class="fas fa-lock"></i> Proceed to Payment
            </button>
        </form>
    </div>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        document.getElementById('proceedToPayment').addEventListener('click', function() {
            // If user is not logged in, validate the form
            <?php if (!$user): ?>
            const name = document.getElementById('name').value;
            const email = document.getElementById('email').value;
            const phone = document.getElementById('phone').value;
            
            if (!name || !email || !phone) {
                alert('Please fill in all required fields');
                return;
            }
            <?php endif; ?>
            
            const options = {
                key: 'rzp_test_1TSGXPk46TbXBv', // Replace with your Razorpay key
                amount: <?php echo $donation['amount'] * 100; ?>,
                currency: 'INR',
                name: 'SafariGate Zoo',
                description: 'Donation',
                image: 'path/to/your/logo.png',
                handler: function(response) {
                    const form = document.getElementById('payment-form');
                    
                    // Add payment ID to form
                    const paymentInput = document.createElement('input');
                    paymentInput.type = 'hidden';
                    paymentInput.name = 'razorpay_payment_id';
                    paymentInput.value = response.razorpay_payment_id;
                    form.appendChild(paymentInput);
                    
                    // Add amount to form
                    const amountInput = document.createElement('input');
                    amountInput.type = 'hidden';
                    amountInput.name = 'amount';
                    amountInput.value = '<?php echo $donation['amount']; ?>';
                    form.appendChild(amountInput);
                    
                    // If user is not logged in, add form data
                    <?php if (!$user): ?>
                    const nameInput = document.createElement('input');
                    nameInput.type = 'hidden';
                    nameInput.name = 'name';
                    nameInput.value = document.getElementById('name').value;
                    form.appendChild(nameInput);
                    
                    const emailInput = document.createElement('input');
                    emailInput.type = 'hidden';
                    emailInput.name = 'email';
                    emailInput.value = document.getElementById('email').value;
                    form.appendChild(emailInput);
                    
                    const phoneInput = document.createElement('input');
                    phoneInput.type = 'hidden';
                    phoneInput.name = 'phone';
                    phoneInput.value = document.getElementById('phone').value;
                    form.appendChild(phoneInput);
                    <?php endif; ?>
                    
                    // Submit the form to process_donation.php
                    form.submit();
                },
                prefill: {
                    <?php if ($user): ?>
                    name: '<?php echo htmlspecialchars($user['username']); ?>',
                    email: '<?php echo htmlspecialchars($user['email']); ?>',
                    contact: '<?php echo htmlspecialchars($user['phone'] ?? ''); ?>'
                    <?php else: ?>
                    name: '',
                    email: '',
                    contact: ''
                    <?php endif; ?>
                },
                theme: {
                    color: '#ff6e01'
                }
            };

            const rzp = new Razorpay(options);
            rzp.open();
        });
    </script>

    <?php include 'footer.php'; ?>
</body>
</html> 