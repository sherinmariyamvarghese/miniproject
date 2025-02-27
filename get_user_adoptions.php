<?php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT animal_id, animal_name, period_type, amount 
    FROM adoptions 
    WHERE user_id = ? AND status = 'pending'
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$adoptions = [];
while ($row = $result->fetch_assoc()) {
    $adoptions[] = $row;
}

echo json_encode(['success' => true, 'adoptions' => $adoptions]); 