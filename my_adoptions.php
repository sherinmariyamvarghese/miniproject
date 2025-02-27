<?php
session_start();
require_once 'connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch user's adoptions
$stmt = $conn->prepare("
    SELECT a.*, an.name as animal_name, an.image_url 
    FROM adoptions a 
    JOIN animals an ON a.animal_id = an.id 
    WHERE a.user_id = ? 
    ORDER BY a.created_at DESC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Adoptions</title>
    <!-- Add your CSS here -->
</head>
<body>
    <h1>My Adoptions</h1>
    
    <div class="adoptions-container">
        <?php while ($adoption = $result->fetch_assoc()): ?>
            <div class="adoption-card">
                <img src="<?php echo htmlspecialchars($adoption['image_url']); ?>" alt="<?php echo htmlspecialchars($adoption['animal_name']); ?>">
                <h3><?php echo htmlspecialchars($adoption['animal_name']); ?></h3>
                <p>Period: <?php echo htmlspecialchars($adoption['period_type']); ?></p>
                <p>Start Date: <?php echo htmlspecialchars($adoption['start_date']); ?></p>
                <p>End Date: <?php echo htmlspecialchars($adoption['end_date']); ?></p>
                <p>Status: <?php echo htmlspecialchars($adoption['status']); ?></p>
            </div>
        <?php endwhile; ?>
    </div>
</body>
</html> 