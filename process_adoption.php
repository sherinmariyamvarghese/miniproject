<?php
session_start();
require_once 'connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to adopt animals']);
    exit;
}

// Validate input
if (!isset($_POST['animal_id']) || !isset($_POST['period_type'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
    exit;
}

$animal_id = intval($_POST['animal_id']);
$period_type = $_POST['period_type'];
$user_id = $_SESSION['user_id'];

// Validate period type
if (!in_array($period_type, ['1_day', '1_month', '1_year'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid period type']);
    exit;
}

try {
    // Get animal details and rate
    $rate_field = 'one_' . str_replace('1_', '', $period_type) . '_rate';
    $stmt = $conn->prepare("SELECT name, $rate_field FROM animals WHERE id = ? AND available = 1");
    $stmt->bind_param("i", $animal_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Animal not found or not available']);
        exit;
    }
    
    $animal = $result->fetch_assoc();
    $rate = $animal[$rate_field];
    
    // Insert into adoptions table
    $stmt = $conn->prepare("
        INSERT INTO adoptions 
        (user_id, animal_id, period_type, total_amount, status) 
        VALUES (?, ?, ?, ?, 'pending')
    ");
    $stmt->bind_param("iisi", $user_id, $animal_id, $period_type, $rate);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Animal added to adoption list successfully',
            'data' => [
                'animal_id' => $animal_id,
                'name' => $animal['name'],
                'period_type' => $period_type,
                'rate' => $rate
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding adoption to database']);
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing your request']);
}
?> 