<?php
session_start();
require_once 'connect.php';
require_once 'vendor/autoload.php'; // Make sure to install Razorpay PHP SDK
use Razorpay\Api\Api;

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

// Initialize Razorpay
$api = new Api('rzp_test_1TSGXPk46TbXBv', 'YOUR_RAZORPAY_SECRET_KEY'); // Replace with your secret key

try {
    // Verify payment signature
    $payment = $api->payment->fetch($data['payment_id']);
    
    if ($payment->status === 'captured') {
        // Start transaction
        $conn->begin_transaction();

        // Update adoptions table
        $sql = "UPDATE adoptions 
                SET status = 'completed',
                    payment_status = 'completed',
                    payment_id = ?,
                    payment_date = NOW()
                WHERE user_id = ? AND status = 'pending'";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $data['payment_id'], $_SESSION['user_id']);
        $stmt->execute();

        // Create payment record
        $sql = "INSERT INTO payments (user_id, amount, payment_id, payment_status) 
                VALUES (?, ?, ?, 'completed')";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ids", $_SESSION['user_id'], $data['amount'], $data['payment_id']);
        $stmt->execute();

        $conn->commit();
        unset($_SESSION['pending_adoptions']);
        
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Payment not captured');
    }
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 