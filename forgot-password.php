<?php
session_start();
include 'connect.php';
include 'send-otp.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$message = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_var($_POST["email"], FILTER_SANITIZE_EMAIL);
    
    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $otp = sprintf("%06d", mt_rand(1, 999999));
            $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            $updateStmt = $conn->prepare("UPDATE users SET otp = ?, otp_expiry = ? WHERE email = ?");
            $updateStmt->bind_param("sss", $otp, $expiry, $email);
            
            if ($result->num_rows > 0) {
                // Use the generateVerificationCode function from send-otp.php
                $verificationCode = generateVerificationCode();
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_otp'] = $verificationCode;
                $_SESSION['otp_created_time'] = time();
                
                // Remove the database OTP storage since we're using session
                if (sendVerificationEmail($email, $verificationCode)) {
                    header("Location: verify-otp.php");
                    exit();
                } else {
                    $error = "Failed to send verification code.";
                }
            }
            $updateStmt->close();
        } else {
            $error = "No account found with this email address.";
        }
        $stmt->close();
    } else {
        $error = "Please enter a valid email address.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - SafariGate</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .forgot-password-container {
            max-width: 500px;
            margin: 100px auto;
            background: var(--bg);
            padding: 30px;
            border-radius: 10px;
            box-shadow: var(--box-shadow);
        }
        .forgot-password-form input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .forgot-password-form .message {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
        }
        .forgot-password-form .error {
            background-color: #ffdddd;
            color: #ff0000;
        }
        .forgot-password-form .success {
            background-color: #ddffdd;
            color: #000;
        }
    </style>
</head>
<body>
    <header class="header">
        <a href="index.php" class="logo"><i class="fas fa-paw"></i> SafariGate</a>
    </header>

    <div class="forgot-password-container">
        <form method="POST" class="forgot-password-form">
            <h2 class="heading">Forgot Password</h2>
            
            <?php if ($error): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-circle"></i> 
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($message): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i> 
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <input 
                type="email" 
                name="email" 
                placeholder="Enter your email" 
                required 
                value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>"
            >
            
            <button type="submit" class="btn">
                <i class="fas fa-paper-plane"></i> Send Reset Link
            </button>
        </form>
    </div>
</body>
</html>
