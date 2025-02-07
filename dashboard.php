<?php
session_start();
require_once 'connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get statistics
$stats = array();

// Total adoptions
$query = "SELECT COUNT(*) as total, SUM(amount) as total_amount FROM adoptions";
$result = $conn->query($query);
$adoption_stats = $result->fetch_assoc();
$stats['adoptions'] = $adoption_stats;

// Total donations
$query = "SELECT COUNT(*) as total, SUM(amount) as total_amount FROM donations";
$result = $conn->query($query);
$donation_stats = $result->fetch_assoc();
$stats['donations'] = $donation_stats;

// Total bookings
$query = "SELECT COUNT(*) as total, SUM(total_amount) as total_amount FROM bookings";
$result = $conn->query($query);
$booking_stats = $result->fetch_assoc();
$stats['bookings'] = $booking_stats;

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
if (isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    
    // Prevent deleting admin account
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user['role'] !== 'admin') {
        $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $delete_stmt->bind_param("i", $user_id);
        if ($delete_stmt->execute()) {
            $_SESSION['message'] = "User deleted successfully";
        } else {
            $_SESSION['error'] = "Error deleting user";
        }
        $delete_stmt->close();
    } else {
        $_SESSION['error'] = "Cannot delete admin account";
    }
}

// Fetch all users
$users = $conn->query("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC");
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SafariGate</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
         .dashboard-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            background: var(--white);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--box-shadow);
        }
        .welcome-text {
            font-size: 2.4rem;
            color: var(--main);
            font-weight: bold;
        }
        .logout-btn {
            font-size: 1.6rem;
            padding: 1rem 2rem;
            background: var(--main);
            color: var(--white);
            border-radius: 5rem;
            text-decoration: none;
            transition: .3s linear;
        }
        .logout-btn:hover {
            background: #ff9748;
        }
        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: var(--white);
            box-shadow: var(--box-shadow);
            border-radius: 1rem;
            overflow: hidden;
        }
        .users-table th,
        .users-table td {
            padding: 1.5rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
            font-size: 1.6rem;
        }
        .users-table th {
            background-color: var(--main);
            color: var(--white);
            text-transform: uppercase;
        }
        .users-table tr:hover {
            background-color: var(--bg);
        }
        .delete-btn {
            background-color: var(--main);
            color: var(--white);
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 5rem;
            cursor: pointer;
            font-size: 1.4rem;
            transition: .3s linear;
        }
        .delete-btn:hover {
            background: #ff9748;
        }
        .message {
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-radius: 1rem;
            font-size: 1.6rem;
            box-shadow: var(--box-shadow);
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            .users-table {
                overflow-x: auto;
                display: block;
            }
            .welcome-text {
                font-size: 2rem;
            }
        }

        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            padding: 2rem;
            margin-top: 100px;
        }

        .stat-card {
            background: var(--bg);
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            box-shadow: var(--box-shadow);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card i {
            font-size: 3rem;
            color: var(--main);
            margin-bottom: 1rem;
        }

        .stat-card h3 {
            font-size: 2.5rem;
            color: var(--black);
            margin-bottom: 0.5rem;
        }

        .stat-card p {
            font-size: 1.6rem;
            color: #666;
        }

        .amount {
            font-size: 1.8rem;
            color: var(--main);
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1 class="welcome-text">Welcome, <?= htmlspecialchars($_SESSION['user']) ?></h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    <div class="dashboard-stats">
        <div class="stat-card">
            <i class="fas fa-heart"></i>
            <h3>Adoptions</h3>
            <p>Total: <?php echo number_format($stats['adoptions']['total']); ?></p>
            <div class="amount">
                ₹<?php echo number_format($stats['adoptions']['total_amount'], 2); ?>
            </div>
        </div>

        <div class="stat-card">
            <i class="fas fa-gift"></i>
            <h3>Donations</h3>
            <p>Total: <?php echo number_format($stats['donations']['total']); ?></p>
            <div class="amount">
                ₹<?php echo number_format($stats['donations']['total_amount'], 2); ?>
            </div>
        </div>

        <div class="stat-card">
            <i class="fas fa-ticket-alt"></i>
            <h3>Bookings</h3>
            <p>Total: <?php echo number_format($stats['bookings']['total']); ?></p>
            <div class="amount">
                ₹<?php echo number_format($stats['bookings']['total_amount'], 2); ?>
            </div>
        </div>
    </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="message success">
                <?= $_SESSION['message'] ?>
                <?php unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error">
                <?= $_SESSION['error'] ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <table class="users-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Created At</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $users->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($user['role']) ?></td>
                        <td><?= date('Y-m-d H:i', strtotime($user['created_at'])) ?></td>
                        <td>
                            <?php if ($user['role'] !== 'admin'): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" name="delete_user" class="delete-btn">Delete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

</body>
</html>