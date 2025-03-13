<?php
session_start();
require_once 'connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Update adoption status if requested
if (isset($_POST['update_status'])) {
    $adoption_id = $_POST['adoption_id'];
    $new_status = $_POST['new_status'];
    
    $stmt = $conn->prepare("UPDATE adoptions SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $adoption_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Adoption status updated successfully";
    } else {
        $_SESSION['error'] = "Error updating adoption status";
    }
    
    header("Location: view_adoptions.php");
    exit();
}

// Fetch all adoptions with user and animal details
$query = "SELECT 
    a.id, 
    a.user_id,
    a.animal_id,
    a.animal_name,
    a.period_type,
    a.amount,
    a.adoption_date,
    a.status,
    u.username,
    u.email
    FROM adoptions a
    JOIN users u ON a.user_id = u.id
    ORDER BY a.adoption_date DESC";

$adoptions = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Adoptions - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Reuse the existing admin dashboard styles */
        .adoptions-table {
            width: 100%;
            background: var(--white);
            border-radius: 0.5rem;
            box-shadow: var(--shadow);
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .adoptions-table th, 
        .adoptions-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 0.9rem;
        }

        .adoptions-table th {
            background: var(--main);
            color: var(--white);
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.8rem;
        }

        .status-badge {
            padding: 0.3rem 0.6rem;
            border-radius: 1rem;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .action-btn {
            padding: 0.4rem 0.8rem;
            border: none;
            border-radius: 2rem;
            font-size: 0.8rem;
            cursor: pointer;
            transition: background 0.3s ease;
            color: white;
        }

        .approve-btn {
            background: #28a745;
        }

        .reject-btn {
            background: #dc3545;
        }

        .action-btn:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <!-- Include the sidebar -->
    <div class="sidebar">
        <!-- Same sidebar code as in admin_dashboard.php -->
        <ul class="sidebar-menu">
            <!-- ... existing sidebar menu items ... -->
        </ul>
    </div>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1 class="welcome-text">Adoption Management</h1>
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

        <table class="adoptions-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Animal</th>
                    <th>Period</th>
                    <th>Amount</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($adoption = $adoptions->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($adoption['id']) ?></td>
                        <td>
                            <?= htmlspecialchars($adoption['username']) ?><br>
                            <small><?= htmlspecialchars($adoption['email']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($adoption['animal_name']) ?></td>
                        <td><?= htmlspecialchars($adoption['period_type']) ?></td>
                        <td>₹<?= number_format($adoption['amount'], 2) ?></td>
                        <td><?= date('Y-m-d', strtotime($adoption['adoption_date'])) ?></td>
                        <td>
                            <span class="status-badge status-<?= strtolower($adoption['status']) ?>">
                                <?= htmlspecialchars($adoption['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($adoption['status'] === 'pending'): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to approve this adoption?');">
                                    <input type="hidden" name="adoption_id" value="<?= $adoption['id'] ?>">
                                    <input type="hidden" name="new_status" value="approved">
                                    <button type="submit" name="update_status" class="action-btn approve-btn">
                                        Approve
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to reject this adoption?');">
                                    <input type="hidden" name="adoption_id" value="<?= $adoption['id'] ?>">
                                    <input type="hidden" name="new_status" value="rejected">
                                    <button type="submit" name="update_status" class="action-btn reject-btn">
                                        Reject
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
        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            var messages = document.getElementsByClassName('message');
            for(var i = 0; i < messages.length; i++) {
                messages[i].style.display = 'none';
            }
        }, 5000);
    </script>
</body>
</html> 