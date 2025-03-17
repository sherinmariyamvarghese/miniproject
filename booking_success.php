<?php
session_start();

// Add debug logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
error_log("Accessing booking_success.php");
error_log("Session data: " . print_r($_SESSION, true));

// Verify required session data exists
if (!isset($_SESSION['booking_data'])) {
    error_log("No booking data found in session - redirecting to booking.php");
    header('Location: booking.php');
    exit;
}

// Add asynchronous notification status checking
if (!isset($_SESSION['notification_status'])) {
    error_log("Processing booking confirmation");
    require_once 'booking_confirmation.php';
    
    try {
        // Set a processing flag
        $_SESSION['notifications_processing'] = true;
        
        // Add debug logging
        error_log("Booking data: " . print_r($_SESSION['booking_data'], true));
        
        // Process notifications
        $notification_status = processBookingConfirmation($_SESSION['booking_data']);
        $_SESSION['notification_status'] = $notification_status;
        
        // Clear processing flag
        unset($_SESSION['notifications_processing']);
        
        error_log("Notification status: " . print_r($notification_status, true));
        
        if ($notification_status['email']) {
            $_SESSION['success_message'] = "Your booking has been confirmed successfully! Confirmation email has been sent.";
        } else {
            $_SESSION['success_message'] = "Your booking was successful! ";
            if (!$notification_status['email']) {
                $_SESSION['success_message'] .= "There might be a delay in receiving the email confirmation. ";
                error_log("Email sending failed for booking ID: " . $_SESSION['booking_data']['booking_id']);
            }
        }
        
    } catch (Exception $e) {
        error_log("Error processing booking confirmation: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        $_SESSION['success_message'] = "Your booking was successful, but there might be a delay in receiving confirmations.";
    }
}

// Add default success message if not set
if (!isset($_SESSION['success_message'])) {
    $_SESSION['success_message'] = "Your booking has been confirmed successfully!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Successful - SafariGate Zoo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .success-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 40px;
            background: var(--white);
            border-radius: 8px;
            box-shadow: var(--box-shadow);
            text-align: center;
        }

        .success-icon {
            font-size: 5rem;
            color: #4CAF50;
            margin-bottom: 20px;
            animation: bounce 1s ease infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .success-message {
            font-size: 2rem;
            color: var(--black);
            margin-bottom: 30px;
            line-height: 1.5;
        }

        .notification-info {
            background: #E3F2FD;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: left;
        }

        .notification-info p {
            font-size: 1.6rem;
            margin: 10px 0;
            color: #1565C0;
        }

        .notification-info i {
            margin-right: 10px;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 30px;
        }

        .action-button {
            padding: 15px 25px;
            border: none;
            border-radius: 4px;
            font-size: 1.6rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .download-ticket {
            background: var(--main);
            color: var(--white);
        }

        .download-ticket:hover {
            background: #ff5500;
            transform: translateY(-2px);
        }

        .return-home {
            background: var(--black);
            color: var(--white);
        }

        .return-home:hover {
            background: #333;
            transform: translateY(-2px);
        }

        .action-button i {
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="success-container">
        <i class="fas fa-check-circle success-icon"></i>
        <div class="success-message">
            <?php echo $_SESSION['success_message']; ?>
        </div>

        <div class="notification-info">
            <?php if (isset($_SESSION['notification_status'])): ?>
                <?php if ($_SESSION['notification_status']['email']): ?>
                    <p><i class="fas fa-envelope"></i> Your e-ticket has been sent to your email.</p>
                <?php else: ?>
                    <p><i class="fas fa-exclamation-triangle"></i> There was an issue sending the email. Please contact support if you don't receive it soon.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="action-buttons">
            <a href="download_ticket.php?booking_id=<?php echo urlencode($_SESSION['booking_data']['booking_id']); ?>" class="action-button download-ticket">
                <i class="fas fa-download"></i> Download E-Ticket
            </a>
            <a href="index.php" class="action-button return-home">
                <i class="fas fa-home"></i> Return to Home
            </a>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html> 