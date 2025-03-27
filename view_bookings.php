<?php
session_start();
require_once 'connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get all bookings with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$total_query = "SELECT COUNT(*) as count FROM bookings";
$total_result = $conn->query($total_query);
$total_bookings = $total_result->fetch_assoc()['count'];
$total_pages = ceil($total_bookings / $per_page);

// Modify the query to include more details
$query = "SELECT b.*, 
          dt.max_tickets, 
          dt.booked_tickets,
          (dt.max_tickets - dt.booked_tickets) as available_tickets,
          CONCAT(
              CASE WHEN b.adult_tickets > 0 
                   THEN CONCAT(b.adult_tickets, ' Adult', IF(b.adult_tickets > 1, 's', ''), ' ') 
                   ELSE '' END,
              CASE WHEN b.child_0_5_tickets > 0 
                   THEN CONCAT(b.child_0_5_tickets, ' Child(0-5)', IF(b.child_0_5_tickets > 1, 's', ''), ' ') 
                   ELSE '' END,
              CASE WHEN b.child_5_12_tickets > 0 
                   THEN CONCAT(b.child_5_12_tickets, ' Child(5-12)', IF(b.child_5_12_tickets > 1, 's', ''), ' ') 
                   ELSE '' END,
              CASE WHEN b.senior_tickets > 0 
                   THEN CONCAT(b.senior_tickets, ' Senior', IF(b.senior_tickets > 1, 's', '')) 
                   ELSE '' END
          ) as ticket_details
          FROM bookings b
          LEFT JOIN daily_tickets dt ON DATE(b.visit_date) = dt.date
          ORDER BY b.booking_date DESC 
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $per_page, $offset);
$stmt->execute();
$bookings = $stmt->get_result();

// Add after the query execution
if ($bookings->num_rows === 0) {
    echo '<div class="alert alert-info">No bookings found.</div>';
}

// Add debug information (remove in production)
$debug = $conn->query("SELECT COUNT(*) as count FROM bookings");
$count = $debug->fetch_assoc()['count'];
echo "<div class='alert alert-info'>Total bookings in database: $count</div>";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Bookings - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .bookings-container {
            padding: 20px;
        }

        .bookings-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: var(--white);
            box-shadow: var(--box-shadow);
            border-radius: 8px;
            overflow: hidden;
        }

        .bookings-table th,
        .bookings-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .bookings-table th {
            background: var(--main);
            color: var(--white);
            font-weight: 500;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9em;
        }

        .status-pending {
            background: #ffeeba;
            color: #856404;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        .pagination a {
            padding: 8px 16px;
            background: var(--white);
            border: 1px solid var(--main);
            color: var(--main);
            text-decoration: none;
            border-radius: 4px;
        }

        .pagination a.active {
            background: var(--main);
            color: var(--white);
        }

        /* Add new styles for dashboard link */
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
            box-shadow: var(--box-shadow);
            transition: all 0.3s ease;
        }

        .dashboard-link:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        /* Adjust container padding to account for removed sidebar */
        .dashboard-container {
            padding: 20px;
            padding-top: 80px;  /* Add space for fixed dashboard link */
        }

        .warning-text {
            color: #ff6b6b;
            font-weight: bold;
        }
        
        .bookings-table td {
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <!-- Remove the sidebar include -->
    <!-- <?php include 'admin_sidebar.php'; ?> -->

    <!-- Add the dashboard link -->
    <a href="admin_dashboard.php" class="dashboard-link">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>

    <div class="dashboard-container">
        <div class="bookings-container">
            <h2><i class="fas fa-ticket-alt"></i> View Bookings</h2>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <table class="bookings-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Visit Date</th>
                        <th>booked Tickets</th>
                        <th>Available Tickets</th>
                        <th>Max Tickets</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Booking Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($booking = $bookings->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo $booking['id']; ?></td>
                            <td><?php echo htmlspecialchars($booking['name']); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($booking['visit_date'])); ?></td>
                            <td>
                                <?php 
                                $totalTickets = $booking['adult_tickets'] + 
                                              $booking['child_0_5_tickets'] + 
                                              $booking['child_5_12_tickets'] + 
                                              $booking['senior_tickets'];
                                echo $totalTickets;
                                ?>
                            </td> tickets
                            <td>
                                <?php 
                                echo isset($booking['available_tickets']) ? $booking['available_tickets'] : 'N/A';
                                if (isset($booking['available_tickets']) && $booking['available_tickets'] < 20) {
                                    echo ' <span class="warning-text">(Limited)</span>';
                                }
                                ?>
                            </td>
                            <td><?php echo isset($booking['max_tickets']) ? $booking['max_tickets'] : 'N/A'; ?></td>
                            <td>₹<?php echo number_format($booking['total_amount'], 2); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($booking['payment_status']); ?>">
                                    <?php echo ucfirst($booking['payment_status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($booking['booking_date'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="<?php echo $page == $i ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html> 