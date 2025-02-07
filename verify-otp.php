<?php
session_start();
include 'connect.php';

// Check if essential session variables exist
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_otp'])) {
    header("Location: forgot-password.php");
    exit();
}

// Add session debugging (remove in production)
error_log("Session data: " . print_r($_SESSION, true));


$error = '';
$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $entered_otp = trim($_POST['otp']);
    
    // Debug information (remove in production)
    error_log("Entered OTP: " . $entered_otp);
    error_log("Stored OTP: " . $_SESSION['reset_otp']);
    
    // Enhanced OTP verification
    if (empty($entered_otp)) {
        $error = "Please enter the OTP.";
    } elseif (strlen($entered_otp) !== 6 || !ctype_digit($entered_otp)) {
        $error = "Invalid OTP format. Please enter a 6-digit code.";
    } elseif ($entered_otp === $_SESSION['reset_otp']) {
        // Strict comparison with session OTP
        $otp_created_time = $_SESSION['otp_created_time'] ?? 0;
        $current_time = time();
        
        if ($current_time - $otp_created_time > 600) { // 10 minutes
            $error = "OTP has expired. Please request a new one.";
            unset($_SESSION['reset_otp']);
            unset($_SESSION['otp_created_time']);
        } else {
            $_SESSION['otp_verified'] = true;
            header("Location: change-password.php");
            exit();
        }
    } else {
        $error = "Invalid OTP. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - SafariGate</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .verify-otp-container {
            max-width: 500px;
            margin: 100px auto;
            background: var(--bg);
            padding: 30px;
            border-radius: 10px;
            box-shadow: var(--box-shadow);
        }
        .verify-otp-form input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-align: center;
            letter-spacing: 10px;
        }
        .verify-otp-form .message {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
        }
        .verify-otp-form .error {
            background-color: #ffdddd;
            color: #ff0000;
        }
    </style>
</head>
<body>
    <header class="header">
        <a href="index.php" class="logo"><i class="fas fa-paw"></i> SafariGate</a>
    </header>

    <div class="verify-otp-container">
        <form method="POST" class="verify-otp-form">
            <h2 class="heading">Verify OTP</h2>
            
            <?php if ($error): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-circle"></i> 
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <p style="text-align: center; margin-bottom: 20px;">
                An OTP has been sent to <?php echo htmlspecialchars($_SESSION['reset_email']); ?>
            </p>
            
            <input 
                type="text" 
                name="otp" 
                placeholder="Enter 6-Digit OTP" 
                maxlength="6" 
                required 
                pattern="\d{6}"
            >
            
            <button type="submit" class="btn">
                <i class="fas fa-check"></i> Verify OTP
            </button>
        </form>
    </div>
</body>
</html>