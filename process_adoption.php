<?php
session_start();
require_once 'connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adoptions'])) {
    try {
        $conn->begin_transaction();
        
        foreach ($_POST['adoptions'] as $adoption) {
            $animal_id = $adoption['animal_id'];
            $period_type = $adoption['period_type'];
            $quantity = $adoption['quantity'];
            $rate = $adoption['rate'];
            $user_id = $_SESSION['user_id'];
            
            $stmt = $conn->prepare("INSERT INTO adoptions (user_id, animal_id, period_type, quantity, rate, total_amount) VALUES (?, ?, ?, ?, ?, ?)");
            $total_amount = $quantity * $rate;
            $stmt->bind_param("iisidi", $user_id, $animal_id, $period_type, $quantity, $rate, $total_amount);
            $stmt->execute();
        }
        
        $conn->commit();
        $_SESSION['message'] = "Animals adopted successfully!";
        header('Location: adoption.php');
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error processing adoption: " . $e->getMessage();
        header('Location: adoption.php');
        exit();
    }
} 