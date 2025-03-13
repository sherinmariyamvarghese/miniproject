<?php
session_start();
require_once 'connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// User status update logic
if (isset($_POST['update_status'])) {
    $user_id = $_POST['user_id'];
    $new_status = $_POST['new_status'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Prevent modifying admin account
        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user && $user['role'] !== 'admin') {
            // Update user status
            $update_status_stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
            $update_status_stmt->bind_param("si", $new_status, $user_id);
            
            if (!$update_status_stmt->execute()) {
                throw new Exception("Error updating user status: " . $update_status_stmt->error);
            }
            
            $update_status_stmt->close();
            
            // If we got here, everything worked
            $conn->commit();
            $_SESSION['message'] = "User status updated to " . $new_status . " successfully";
            
        } else {
            $conn->rollback();
            $_SESSION['error'] = "Cannot modify admin account or user not found";
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error'] = "Error during status update: " . $e->getMessage();
        error_log("Error in user status update: " . $e->getMessage());
    }
    
    // Redirect to refresh the page
    header("Location: admin_dashboard.php");
    exit();
}

// Get statistics
$stats = array();

// Total adoptions
$query = "SELECT 
    COUNT(*) as total,
    SUM(CASE 
        WHEN period_type = '1_day' THEN a.daily_rate
        WHEN period_type = '1_month' THEN a.monthly_rate
        WHEN period_type = '1_year' THEN a.yearly_rate
        ELSE 0
    END) as total_amount
    FROM adoptions ad
    JOIN animals a ON ad.animal_id = a.id";
$result = $conn->query($query);
$adoption_stats = $result->fetch_assoc();
$stats['adoptions'] = $adoption_stats;

// Total donations
$query = "SELECT COUNT(*) as total, SUM(COALESCE(amount, 0)) as total_amount FROM donations";
$result = $conn->query($query);
$donation_stats = $result->fetch_assoc();
$stats['donations'] = $donation_stats;

// Total bookings
$query = "SELECT COUNT(*) as total, SUM(COALESCE(total_amount, 0)) as total_amount FROM bookings";
$result = $conn->query($query);
$booking_stats = $result->fetch_assoc();
$stats['bookings'] = $booking_stats;

// Fetch all users
$users = $conn->query("SELECT id, username, email, role, status, created_at FROM users ORDER BY created_at DESC");
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
        :root {
            --main: #ff7b00;
            --bg: #fff9f5;
            --black: #333;
            --white: #fff;
            --shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: system-ui, -apple-system, sans-serif;
        }

        body {
            background: #f5f5f5;
            font-size: 16px;
            min-height: 100vh;
            display: flex;
        }

        .dashboard-container {
            flex: 1;
            margin-left: 250px;
            padding: 1rem;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: var(--white);
            border-radius: 0.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 1rem;
        }

        .welcome-text {
            font-size: 1.5rem;
            color: var(--main);
        }

        .logout-btn {
            padding: 0.5rem 1rem;
            background: var(--main);
            color: var(--white);
            border-radius: 2rem;
            text-decoration: none;
            font-size: 0.9rem;
            transition: background 0.3s ease;
        }

        .logout-btn:hover {
            background: #ff9748;
        }

        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 0.5rem;
            text-align: center;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-card i {
            font-size: 1.5rem;
            color: var(--main);
            margin-bottom: 0.5rem;
        }

        .stat-card h3 {
            font-size: 1.2rem;
            color: var(--black);
            margin-bottom: 0.5rem;
        }

        .stat-card p {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.5rem;
        }

        .amount {
            font-size: 1.1rem;
            color: var(--main);
            font-weight: 600;
        }

        .users-table {
            width: 100%;
            background: var(--white);
            border-radius: 0.5rem;
            box-shadow: var(--shadow);
            border-collapse: collapse;
            overflow: hidden;
        }

        .users-table th, 
        .users-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 0.9rem;
        }

        .users-table th {
            background: var(--main);
            color: var(--white);
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.8rem;
        }

        .users-table tr:hover {
            background: var(--bg);
        }

        .delete-btn, .deactivate-btn {
            background: #dc3545;
            color: var(--white);
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 2rem;
            font-size: 0.8rem;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .deactivate-btn {
            background: #ffc107;
        }

        .delete-btn:hover {
            background: #c82333;
        }

        .deactivate-btn:hover {
            background: #e0a800;
        }

        .status-active {
            color: #28a745;
            font-weight: 500;
        }

        .status-inactive {
            color: #dc3545;
            font-weight: 500;
        }

        .message {
            padding: 0.75rem;
            margin-bottom: 1rem;
            border-radius: 0.5rem;
            font-size: 0.9rem;
        }

        .success { 
            background: #d4edda; 
            color: #155724; 
        }

        .error { 
            background: #f8d7da; 
            color: #721c24; 
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background: var(--white);
            box-shadow: var(--shadow);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            left: 0;
            top: 0;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu li {
            margin: 0.5rem 0;
        }

        .sidebar-menu .menu-header {
            font-size: 0.8rem;
            text-transform: uppercase;
            color: #666;
            padding: 1rem 1.5rem 0.5rem;
            font-weight: 500;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 0.8rem 1.5rem;
            color: var(--black);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .sidebar-menu a:hover {
            background: var(--bg);
            color: var(--main);
        }

        .sidebar-menu a i {
            margin-right: 0.8rem;
            width: 20px;
            text-align: center;
        }

        .sidebar-menu a.active {
            background: var(--bg);
            color: var(--main);
            border-right: 3px solid var(--main);
        }

        /* Dropdown menu for animals */
        .dropdown {
            position: relative;
        }

        .dropdown-toggle {
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }

        .dropdown-menu {
            display: none;
            list-style: none;
            padding-left: 2rem;
        }

        .dropdown-menu.show {
            display: block;
        }

        .dropdown-menu a {
            padding: 0.6rem 1.5rem;
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: static;
                height: auto;
                margin-bottom: 1rem;
            }
            
            .dashboard-container {
                margin-left: 0;
            }
            
            body {
                flex-direction: column;
            }
            
            .dashboard-header {
                flex-direction: column;
                gap: 0.5rem;
                text-align: center;
            }
            
            .welcome-text {
                font-size: 1.2rem;
            }

            .users-table {
                display: block;
                overflow-x: auto;
            }

            .dashboard-container {
                padding: 0.5rem;
            }

            .stat-card {
                padding: 1rem;
            }
        }

        .activate-btn {
            background: #28a745;
            color: var(--white);
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 2rem;
            font-size: 0.8rem;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .activate-btn:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <ul class="sidebar-menu">
            <li class="menu-header">Dashboard</li>
            <li>
                <a href="admin_dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-tachometer-alt"></i> Overview
                </a>
            </li>

            <li class="menu-header">Animals</li>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle">
                    <i class="fas fa-paw"></i> Animals
                    <i class="fas fa-chevron-down"></i>
                </a>
                <ul class="dropdown-menu <?= in_array(basename($_SERVER['PHP_SELF']), ['add_animal.php', 'view_animal.php']) ? 'show' : '' ?>">
                    <li>
                        <a href="add_animal.php" class="<?= basename($_SERVER['PHP_SELF']) == 'add_animal.php' ? 'active' : '' ?>">
                            <i class="fas fa-plus"></i> Add Animal
                        </a>
                    </li>
                    <li>
                        <a href="view_animal.php" class="<?= basename($_SERVER['PHP_SELF']) == 'view_animal.php' ? 'active' : '' ?>">
                            <i class="fas fa-list"></i> View Animals
                        </a>
                    </li>
                </ul>
            </li>

            <li class="menu-header">Bookings</li>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle">
                    <i class="fas fa-ticket-alt"></i> Bookings
                    <i class="fas fa-chevron-down"></i>
                </a>
                <ul class="dropdown-menu <?= in_array(basename($_SERVER['PHP_SELF']), ['manage_ticket_rates.php', 'view_bookings.php']) ? 'show' : '' ?>">
                    <li>
                        <a href="manage_ticket_rates.php" class="<?= basename($_SERVER['PHP_SELF']) == 'manage_ticket_rates.php' ? 'active' : '' ?>">
                            <i class="fas fa-money-bill"></i> Manage Ticket Rates
                        </a>
                    </li>
                    <li>
                        <a href="view_bookings.php" class="<?= basename($_SERVER['PHP_SELF']) == 'view_bookings.php' ? 'active' : '' ?>">
                            <i class="fas fa-list"></i> View Bookings
                        </a>
                    </li>
                </ul>
            </li>

            <li class="menu-header">Adoptions</li>
            <li>
                <a href="view_adoptions.php" class="<?= basename($_SERVER['PHP_SELF']) == 'view_adoptions.php' ? 'active' : '' ?>">
                    <i class="fas fa-heart"></i> View Adoptions
                </a>
            </li>

            <li class="menu-header">Donations</li>
            <li>
                <a href="admin_donations.php" class="<?= basename($_SERVER['PHP_SELF']) == 'admin_donations.php' ? 'active' : '' ?>">
                    <i class="fas fa-hand-holding-heart"></i> View Donations
                </a>
            </li>

            <li class="menu-header">Settings</li>
            <li>
                <a href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1 class="welcome-text">Welcome, <?= htmlspecialchars($_SESSION['user']) ?></h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="message success">
                <?= htmlspecialchars($_SESSION['message']) ?>
                <?php unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error">
                <?= htmlspecialchars($_SESSION['error']) ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

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

        <table class="users-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
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
                        <td class="status-<?= strtolower($user['status']) ?>">
                            <?= htmlspecialchars($user['status']) ?>
                        </td>
                        <td><?= date('Y-m-d H:i', strtotime($user['created_at'])) ?></td>
                        <td>
                            <?php if ($user['role'] !== 'admin'): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to <?= $user['status'] === 'active' ? 'deactivate' : 'activate' ?> this user?');">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <input type="hidden" name="new_status" value="<?= $user['status'] === 'active' ? 'inactive' : 'active' ?>">
                                    <button type="submit" name="update_status" class="<?= $user['status'] === 'active' ? 'deactivate-btn' : 'activate-btn' ?>">
                                        <?= $user['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <script>
        // Toggle dropdown menu
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
            
            dropdownToggles.forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    const dropdownMenu = this.nextElementSibling;
                    dropdownMenu.classList.toggle('show');
                });
            });
            
            // Auto-hide messages after 5 seconds
            setTimeout(function() {
                var messages = document.getElementsByClassName('message');
                for(var i = 0; i < messages.length; i++) {
                    messages[i].style.display = 'none';
                }
            }, 5000);
        });
    </script>
</body>
</html>