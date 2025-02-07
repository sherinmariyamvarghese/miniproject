<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';


function generateVerificationCode($length = 6) {
    $digits = '0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $digits[random_int(0, strlen($digits) - 1)];
    }
    return $code;
}


function sendVerificationEmail($recipientEmail, $verificationCode) {
    $mail = new PHPMailer(true);

    try {
       
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';  
        $mail->SMTPAuth   = true;
        $mail->Username   = 'sherinmariyamvarghese@gmail.com';  
        $mail->Password   = 'uhfo rkzj aewm kdpb';     
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

       
        $mail->setFrom('sherinmariyamvarghese2027@mca.ajce.in', 'sherin');
        $mail->addAddress('sherinmariyamvarghese@gmail.com');
        $mail->Subject = 'Your Verification Code';
        $mail->Body    = "Your verification code is: $verificationCode\n\nThis code will expire in 10 minutes.";

        
        $mail->send();
        return true;
    } catch (Exception $e) {
    
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}


try {
    $email = ''; 
    $verificationCode = generateVerificationCode();

   
    if (sendVerificationEmail($email, $verificationCode)) {
        echo "Verification code sent successfully to $email.";
    } else {
        echo "Failed to send verification code.";
    }
} catch (Exception $e) {
    echo "An error occurred: " . $e->getMessage();
}
?>