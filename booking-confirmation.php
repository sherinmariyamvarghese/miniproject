<?php
session_start();

// Redirect if no success message
if (!isset($_SESSION['success_message'])) {
    header('Location: booking.php');
    exit;
}

$success_message = $_SESSION['success_message'];
unset($_SESSION['success_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation - Zoo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .confirmation-container {
            max-width: 600px;
            margin: 100px auto;
            padding: 30px;
            text-align: center;
            background: var(--white);
            border-radius: 8px;
            box-shadow: var(--box-shadow);
        }

        .success-icon {
            color: #28a745;
            font-size: 5rem;
            margin-bottom: 20px;
        }

        .home-btn {
            display: inline-block;
            padding: 12px 30px;
            background: var(--main);
            color: var(--white);
            border-radius: 5px;
            text-decoration: none;
            margin-top: 20px;
            transition: all 0.3s ease;
        }

        .home-btn:hover {
            background: #ff5500;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="confirmation-container">
        <i class="fas fa-check-circle success-icon"></i>
        <h2>Booking Confirmed!</h2>
        <p><?php echo $success_message; ?></p>
        <a href="index.php" class="home-btn">
            <i class="fas fa-home"></i> Return to Home
        </a>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html> 