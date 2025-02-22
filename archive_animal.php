<?php
session_start();
require_once 'connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['animal_id'])) {
    $animal_id = $_POST['animal_id'];
    
    // Get current status
    $stmt = $conn->prepare("SELECT status FROM animals WHERE id = ?");
    $stmt->bind_param("i", $animal_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $animal = $result->fetch_assoc();
    
    if ($animal) {
        $new_status = $animal['status'] === 'archived' ? 'available' : 'archived';
        
        // Update status
        $update_stmt = $conn->prepare("UPDATE animals SET status = ? WHERE id = ?");
        $update_stmt->bind_param("si", $new_status, $animal_id);
        
        if ($update_stmt->execute()) {
            $action = $new_status === 'archived' ? 'archived' : 'unarchived';
            $_SESSION['message'] = "Animal $action successfully";
        } else {
            $_SESSION['error'] = "Error updating animal status";
        }
    } else {
        $_SESSION['error'] = "Animal not found";
    }
}

header('Location: view_animal.php');
exit();
?>
