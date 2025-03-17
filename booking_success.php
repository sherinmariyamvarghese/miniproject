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
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .success-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #4CAF50, #45a049);
            animation: loadingBar 1.5s ease-out;
        }

        @keyframes loadingBar {
            from { width: 0; }
            to { width: 100%; }
        }

        .success-icon {
            font-size: 6rem;
            color: #4CAF50;
            margin-bottom: 20px;
            opacity: 0;
            transform: scale(0.5);
            animation: popIn 0.5s ease-out forwards 0.5s;
        }

        @keyframes popIn {
            from {
                opacity: 0;
                transform: scale(0.5);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .success-message {
            font-size: 2.2rem;
            color: var(--black);
            margin-bottom: 30px;
            line-height: 1.5;
            opacity: 0;
            transform: translateY(20px);
            animation: slideUp 0.5s ease-out forwards 0.8s;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .notification-info {
            background: linear-gradient(135deg, #E3F2FD, #BBDEFB);
            padding: 25px;
            border-radius: 12px;
            margin: 20px 0;
            text-align: left;
            opacity: 0;
            animation: fadeIn 0.5s ease-out forwards 1.1s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .notification-info p {
            font-size: 1.6rem;
            margin: 10px 0;
            color: #1565C0;
            display: flex;
            align-items: center;
        }

        .notification-info i {
            margin-right: 15px;
            font-size: 2rem;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 40px;
            opacity: 0;
            animation: fadeIn 0.5s ease-out forwards 1.4s;
        }

        .action-button {
            padding: 18px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1.6rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .download-ticket {
            background: linear-gradient(135deg, var(--main), #ff5500);
            color: var(--white);
            box-shadow: 0 4px 15px rgba(255, 110, 1, 0.3);
        }

        .download-ticket:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(255, 110, 1, 0.4);
        }

        .return-home {
            background: linear-gradient(135deg, #333333, #222222);
            color: var(--white);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .return-home:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }

        .action-button i {
            margin-right: 12px;
            font-size: 1.8rem;
        }

        /* Confetti animation */
        .confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            background-color: #f0f;
            position: absolute;
            animation: confetti 5s ease-in-out infinite;
        }

        @keyframes confetti {
            0% { transform: translateY(0) rotate(0deg); }
            100% { transform: translateY(100vh) rotate(720deg); }
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
                    <p><i class="fas fa-envelope-open-text"></i> Your e-ticket has been sent to your email.</p>
                <?php else: ?>
                    <p><i class="fas fa-exclamation-circle"></i> There was an issue sending the email. Please contact support if you don't receive it soon.</p>
                <?php endif; ?>
            <?php endif; ?>
            <p><i class="fas fa-info-circle"></i> Please keep your e-ticket safe for entry.</p>
        </div>

        <div class="action-buttons">
            <a href="generate_ticket.php?booking_id=<?php echo urlencode($_SESSION['booking_data']['booking_id']); ?>" 
               class="action-button download-ticket" target="_blank">
                <i class="fas fa-ticket-alt"></i> Download E-Ticket
            </a>
            <a href="index.php" class="action-button return-home">
                <i class="fas fa-home"></i> Return to Home
            </a>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        // Create confetti effect
        function createConfetti() {
            const colors = ['#ff6e01', '#4CAF50', '#1565C0', '#FFC107', '#9C27B0'];
            for (let i = 0; i < 50; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = Math.random() * 100 + 'vw';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.animationDuration = (Math.random() * 3 + 2) + 's';
                confetti.style.animationDelay = Math.random() * 2 + 's';
                document.body.appendChild(confetti);
                
                // Remove confetti after animation
                setTimeout(() => {
                    confetti.remove();
                }, 5000);
            }
        }

        // Run confetti when page loads
        window.addEventListener('load', createConfetti);
    </script>
</body>
</html> 