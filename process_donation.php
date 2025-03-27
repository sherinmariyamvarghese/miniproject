<?php
session_start();
require_once 'connect.php';
require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['razorpay_payment_id'])) {
    try {
        $conn->begin_transaction();
        
        $payment_id = $_POST['razorpay_payment_id'];
        $amount = $_POST['amount'];
        $donation = json_decode($_POST['donation'], true);
        
        // Get user details
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $name = isset($_POST['name']) ? $_POST['name'] : null;
        $email = isset($_POST['email']) ? $_POST['email'] : null;
        $phone = isset($_POST['phone']) ? $_POST['phone'] : null;
        
        // If user is logged in, get their details from the database
        if ($user_id) {
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            
            $name = $user['username'];
            $email = $user['email'];
            $phone = $user['phone'] ?? null;
        }
        
        // Create donations table if it doesn't exist
        $sql_create_table = "CREATE TABLE IF NOT EXISTS donations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            amount DECIMAL(10,2) NOT NULL,
            message TEXT,
            donation_date DATE NOT NULL,
            payment_id VARCHAR(255),
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(20),
            status ENUM('completed', 'failed') DEFAULT 'completed',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )";
        
        $conn->query($sql_create_table);
        
        // Check if required columns exist, if not add them
        $required_columns = ['payment_id', 'name', 'email', 'phone', 'status'];
        foreach ($required_columns as $column) {
            $check_column = "SHOW COLUMNS FROM donations LIKE '$column'";
            $result = $conn->query($check_column);
            if ($result->num_rows == 0) {
                $add_column = "ALTER TABLE donations ADD COLUMN $column ";
                
                // Define column types based on column name
                switch ($column) {
                    case 'payment_id':
                        $add_column .= "VARCHAR(255) AFTER donation_date";
                        break;
                    case 'name':
                        $add_column .= "VARCHAR(100) NOT NULL AFTER payment_id";
                        break;
                    case 'email':
                        $add_column .= "VARCHAR(100) NOT NULL AFTER name";
                        break;
                    case 'phone':
                        $add_column .= "VARCHAR(20) AFTER email";
                        break;
                    case 'status':
                        $add_column .= "ENUM('completed', 'failed') DEFAULT 'completed' AFTER phone";
                        break;
                }
                
                $conn->query($add_column);
            }
        }
        
        // Insert donation into database
        $stmt = $conn->prepare("INSERT INTO donations (user_id, amount, message, donation_date, payment_id, name, email, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("idssssss", $user_id, $amount, $donation['message'], $donation['date'], $payment_id, $name, $email, $phone);
        $stmt->execute();
        $donation_id = $conn->insert_id;
        
        // Function to send confirmation email
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
        
        // Prepare donation data for session and email
        $donation_data = array(
            'donation_id' => $donation_id,
            'amount' => $amount,
            'date' => $donation['date'],
            'payment_id' => $payment_id,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'message' => $donation['message']
        );
        
        if ($conn->commit()) {
            // Store donation details in session
            $_SESSION['donation_data'] = $donation_data;
            
            // Send confirmation email
            $email_status = sendDonationConfirmationEmail($donation_data, $conn);
            
            $_SESSION['notification_status'] = ['email' => $email_status];
            
            if ($email_status) {
                $_SESSION['success_message'] = "Thank you for your donation! A confirmation email has been sent.";
            } else {
                $_SESSION['success_message'] = "Thank you for your donation! However, there might be a delay in receiving the email confirmation.";
            }
            
            // Redirect directly to success page
            header('Location: donation_success.php');
            exit;
        } else {
            throw new Exception("Error completing donation transaction");
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Donation Error: " . $e->getMessage());
        $_SESSION['error_message'] = "Error processing donation: " . $e->getMessage();
        header('Location: donation.php');
        exit;
    }
} else {
    header('Location: donation.php');
    exit;
}
?> 