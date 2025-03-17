<?php
session_start();
require_once 'connect.php';

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    foreach ($data['adoptions'] as $animal_id => $adoption) {
        // Update adoption record with payment details
        $stmt = $conn->prepare("
            UPDATE adoptions 
            SET status = 'active',
                payment_id = ?,
                payment_date = NOW()
            WHERE user_id = ? 
            AND animal_id = ? 
            AND status = 'pending'
        ");
        
        $stmt->bind_param("sii", 
            $data['razorpay_payment_id'],
            $_SESSION['user_id'],
            $animal_id
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error updating adoption record");
        }
        
        // Get adoption details for certificate
        $stmt = $conn->prepare("
            SELECT a.*, u.name, u.email 
            FROM adoptions a 
            JOIN users u ON a.user_id = u.id 
            WHERE a.id = LAST_INSERT_ID()
        ");
        $stmt->execute();
        $adoption_details = $stmt->get_result()->fetch_assoc();
        
        // Generate certificate
        $certificate_path = generateAdoptionCertificate($adoption_details);
        
        // Send email
        if (!sendAdoptionEmail($adoption_details, $certificate_path)) {
            // Log error but don't fail the transaction
            error_log("Failed to send adoption email for adoption ID: {$adoption_details['id']}");
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error processing adoption']);
}
?> 