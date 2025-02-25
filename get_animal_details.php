<?php
require_once 'connect.php';

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid animal ID']);
    exit;
}

$animal_id = $_POST['id'];
$stmt = $conn->prepare("
    SELECT a.*, c.name as category_name 
    FROM animals a 
    LEFT JOIN animal_categories c ON a.category_id = c.id 
    WHERE a.id = ?
");
$stmt->bind_param("i", $animal_id);
$stmt->execute();
$result = $stmt->get_result();

if ($animal = $result->fetch_assoc()) {
    // Clean the data before sending
    $animal = array_map('htmlspecialchars', $animal);
    echo json_encode($animal);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Animal not found']);
}

$stmt->close();
$conn->close(); 