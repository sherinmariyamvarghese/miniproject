<?php
session_start();
require_once 'connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['booking_id']) && isset($data['image_data'])) {
        $booking_id = $data['booking_id'];
        $image_data = $data['image_data'];
        
        // Remove the data URL prefix
        $image_data = str_replace('data:image/png;base64,', '', $image_data);
        $image_data = base64_decode($image_data);
        
        // Save the image
        $dir = 'uploads/qrcodes';
        $file_path = $dir . '/booking_' . $booking_id . '.png';
        file_put_contents($file_path, $image_data);
        
        // Clean up the temporary JSON file
        @unlink($dir . '/qr_data_' . $booking_id . '.json');
        
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required data']);
    }
}
?> 