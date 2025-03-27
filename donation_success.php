<?php
session_start();
require_once 'connect.php';

// Verify required session data exists
if (!isset($_SESSION['donation_data'])) {
    header('Location: donation.php');
    exit;
}

$donation = $_SESSION['donation_data'];
$email_status = isset($_SESSION['notification_status']['email']) ? $_SESSION['notification_status']['email'] : false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donation Successful - SafariGate Zoo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .success-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background: var(--white);
            border-radius: 8px;
            box-shadow: var(--box-shadow);
            text-align: center;
        }
        
        .success-icon {
            font-size: 5rem;
            color: #28a745;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .success-title {
            font-size: 3rem;
            color: var(--main);
            margin-bottom: 20px;
        }
        
        .success-message {
            font-size: 1.8rem;
            color: var(--black);
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .donation-details {
            background: var(--bg);
            padding: 20px;
            border-radius: 8px;
            margin: 30px 0;
            text-align: left;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .donation-details:hover {
            transform: translateY(-5px);
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px dashed #ddd;
            font-size: 1.6rem;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .btn-home {
            display: inline-block;
            padding: 12px 30px;
            background: var(--main);
            color: var(--white);
            border-radius: 4px;
            font-size: 1.8rem;
            text-decoration: none;
            margin-top: 20px;
            transition: all 0.3s ease;
        }
        
        .btn-home:hover {
            background: #ff5500;
            transform: translateY(-2px);
        }
        
        .thank-you-note {
            font-style: italic;
            color: #666;
            margin-top: 30px;
            padding: 15px;
            border-left: 4px solid var(--main);
            background: #fff8e1;
            text-align: left;
        }
        
        .notification-status {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 1.4rem;
            font-weight: bold;
        }
        
        .status-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        .details-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 2px solid var(--main);
            padding-bottom: 10px;
        }
        
        .details-header i {
            margin-right: 10px;
            color: var(--main);
        }
        
        .details-header h3 {
            margin: 0;
            font-size: 2rem;
        }
        
        .confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            background-color: #f0f;
            animation: confetti 5s ease-in-out infinite;
            z-index: -1;
        }
        
        @keyframes confetti {
            0% { transform: translateY(0) rotateZ(0); opacity: 1; }
            100% { transform: translateY(1000px) rotateZ(720deg); opacity: 0; }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <!-- Confetti animation -->
    <script>
        function createConfetti() {
            const colors = ['#ff6e01', '#ffcc00', '#ff3366', '#33cc33', '#3399ff'];
            for (let i = 0; i < 100; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = Math.random() * 100 + 'vw';
                confetti.style.top = -10 + 'px';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.width = Math.random() * 10 + 5 + 'px';
                confetti.style.height = Math.random() * 10 + 5 + 'px';
                confetti.style.animationDuration = Math.random() * 3 + 2 + 's';
                confetti.style.animationDelay = Math.random() * 5 + 's';
                document.body.appendChild(confetti);
                
                // Remove confetti after animation
                setTimeout(() => {
                    confetti.remove();
                }, 7000);
            }
        }
        
        window.onload = createConfetti;
    </script>
    
    <div class="success-container">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        
        <h1 class="success-title">Thank You for Your Donation!</h1>
        
        <p class="success-message">
            Your generous contribution will help us continue our mission of wildlife conservation and education.
            <?php if (isset($_SESSION['success_message'])): ?>
                <br><?php echo $_SESSION['success_message']; ?>
            <?php endif; ?>
        </p>
        
        <!-- Email notification status -->
        <?php if ($email_status): ?>
            <div class="notification-status status-success">
                <i class="fas fa-envelope"></i> Confirmation email has been sent to your email address
            </div>
        <?php else: ?>
            <div class="notification-status status-pending">
                <i class="fas fa-clock"></i> Your confirmation email will be sent shortly
            </div>
        <?php endif; ?>
        
        <div class="donation-details">
            <div class="details-header">
                <i class="fas fa-info-circle fa-2x"></i>
                <h3>Donation Details</h3>
            </div>
            
            <div class="detail-row">
                <span><i class="fas fa-rupee-sign"></i> Donation Amount:</span>
                <span>₹<?php echo number_format($donation['amount'], 2); ?></span>
            </div>
            
            <div class="detail-row">
                <span><i class="far fa-calendar-alt"></i> Date:</span>
                <span><?php echo date('F j, Y', strtotime($donation['date'])); ?></span>
            </div>
            
            <div class="detail-row">
                <span><i class="fas fa-receipt"></i> Transaction ID:</span>
                <span><?php echo $donation['payment_id']; ?></span>
            </div>
            
            <?php if (!empty($donation['message'])): ?>
                <div class="detail-row">
                    <span><i class="fas fa-comment-alt"></i> Your Message:</span>
                    <span><?php echo htmlspecialchars($donation['message']); ?></span>
                </div>
            <?php endif; ?>
        </div>
        
        <p class="thank-you-note">
            <i class="fas fa-quote-left" style="color: var(--main); margin-right: 10px;"></i>
            Your support makes a significant difference in the lives of our animals and helps us maintain our conservation efforts. We are deeply grateful for your generosity.
        </p>
        
        <a href="index.php" class="btn-home">
            <i class="fas fa-home"></i> Return to Home
        </a>
    </div>
    
    <?php 
    // Clear session variables
    unset($_SESSION['donation_data']);
    unset($_SESSION['success_message']);
    unset($_SESSION['notification_status']);
    ?>
    
    <?php include 'footer.php'; ?>
</body>
</html> 