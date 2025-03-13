<?php
session_start();
require_once 'connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Fetch all donations with user details
$query = "SELECT d.*, u.username 
          FROM donations d 
          LEFT JOIN users u ON d.user_id = u.id 
          ORDER BY d.donation_date DESC";
$donations = $conn->query($query);

// Calculate statistics
$stats_query = "SELECT 
    COUNT(*) as total_donations,
    SUM(amount) as total_amount,
    MAX(amount) as highest_donation
    FROM donations";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donation Management - SafariGate</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .donation-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-box {
            background: linear-gradient(135deg, #ff7b00, #ff9748);
            color: white;
            padding: 1.5rem;
            border-radius: 1rem;
            text-align: center;
            box-shadow: 0 4px 15px rgba(255, 123, 0, 0.2);
            transition: transform 0.3s ease;
        }

        .stat-box:hover {
            transform: translateY(-5px);
        }

        .stat-box i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .stat-box h3 {
            font-size: 2rem;
            margin: 0.5rem 0;
        }

        .stat-box p {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .donations-table {
            width: 100%;
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .donations-table th {
            background: #ff7b00;
            color: white;
            padding: 1rem;
            text-align: left;
        }

        .donations-table td {
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }

        .donations-table tr:hover {
            background: #fff9f5;
        }

        .amount-cell {
            font-weight: bold;
            color: #ff7b00;
        }

        .message-cell {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .message-cell:hover {
            white-space: normal;
            overflow: visible;
        }

        .anonymous {
            color: #666;
            font-style: italic;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .dashboard-header {
            margin-top: 4rem;
            margin-bottom: 2rem;
        }

        .welcome-text {
            font-size: 2.5rem;
            color: var(--black);
            border-bottom: 2px solid var(--main);
            padding-bottom: 1rem;
        }

        body {
            padding-top: 0;
        }

        .dashboard-link {
            position: fixed;
            top: 20px;
            left: 20px;
            background: var(--main);
            color: var(--white);
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 1.6rem;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 1000;
            box-shadow: 0 .5rem 1rem rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .dashboard-link:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
    </style>
</head>
<body><br><br><br><br>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1 class="welcome-text">Donation Management</h1>
        </div>

        <div class="donation-stats">
            <div class="stat-box">
                <i class="fas fa-gift"></i>
                <h3><?= number_format($stats['total_donations']) ?></h3>
                <p>Total Donations</p>
            </div>
            <div class="stat-box">
                <i class="fas fa-rupee-sign"></i>
                <h3>₹<?= number_format($stats['total_amount']) ?></h3>
                <p>Total Amount</p>
            </div>
            <div class="stat-box">
                <i class="fas fa-trophy"></i>
                <h3>₹<?= number_format($stats['highest_donation']) ?></h3>
                <p>Highest Donation</p>
            </div>
        </div>

        <table class="donations-table">
            <thead>
                <tr>
                    <th>Donor</th>
                    <th>Amount</th>
                    <th>Message</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($donation = $donations->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?php if ($donation['username']): ?>
                                <?= htmlspecialchars($donation['username']) ?>
                            <?php else: ?>
                                <span class="anonymous">Anonymous Donor</span>
                            <?php endif; ?>
                        </td>
                        <td class="amount-cell">₹<?= number_format($donation['amount'], 2) ?></td>
                        <td class="message-cell">
                            <?= $donation['message'] ? htmlspecialchars($donation['message']) : '<em>No message</em>' ?>
                        </td>
                        <td><?= date('M d, Y', strtotime($donation['donation_date'])) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <a href="admin_dashboard.php" class="dashboard-link">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>

    <script>
        // Add any JavaScript functionality here
    </script>
</body>
</html> 