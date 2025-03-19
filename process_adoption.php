<?php
session_start();
require_once 'connect.php';
require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['razorpay_payment_id'])) {
    try {
        $conn->begin_transaction();
        
        $payment_id = $_POST['razorpay_payment_id'];
        $total_amount = $_POST['total_amount'];
        $adoptions = json_decode($_POST['adoptions'], true);
        $user_id = $_SESSION['user_id'];
        
        // Get user details
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        $adoption_details = [];
        
        foreach ($adoptions as $animal_id => $adoption) {
            $stmt = $conn->prepare("
                INSERT INTO adoptions 
                (user_id, animal_id, animal_name, period_type, amount, adoption_date, 
                status, payment_id, name, email, phone, city, address) 
                VALUES (?, ?, ?, ?, ?, CURDATE(), 'completed', ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param("iissdssssss", 
                $user_id,
                $animal_id,
                $adoption['name'],
                $adoption['period'],
                $adoption['rate'],
                $payment_id,
                $user['username'],
                $user['email'],
                $user['phone'],
                $user['city'],
                $user['address']
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Error processing adoption for " . $adoption['name']);
            }
            
            $adoption_id = $conn->insert_id;
            
            // Get animal image
            $stmt = $conn->prepare("SELECT image_url FROM animals WHERE id = ?");
            $stmt->bind_param("i", $animal_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $animal = $result->fetch_assoc();
            
            // Prepare adoption data for email
            $adoption_data = array(
                'adoption_id' => $adoption_id,
                'animal_name' => $adoption['name'],
                'period_type' => $adoption['periodDisplay'],
                'amount' => $adoption['rate'],
                'adopter_name' => $user['username'],
                'adopter_email' => $user['email'],
                'phone' => $user['phone'],
                'address' => $user['address'],
                'payment_id' => $payment_id,
                'animal_image' => $animal['image_url'] ?? null
            );
            
            $adoption_details[] = $adoption_data;
        }
        
        if ($conn->commit()) {
            // Store adoption details in session
            $_SESSION['adoption_data'] = $adoption_details[0]; // Store first adoption for backward compatibility
            $_SESSION['all_adoptions'] = $adoption_details; // Store all adoptions
            $_SESSION['payment_id'] = $payment_id;
            $_SESSION['total_amount'] = $total_amount;
            
            // Redirect to success page
            header('Location: adoption_success.php');
            exit;
        } else {
            throw new Exception("Error completing adoption transaction");
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Adoption Error: " . $e->getMessage());
        $_SESSION['error'] = "Error processing adoption: " . $e->getMessage();
        header('Location: adoption.php');
        exit;
    }
}