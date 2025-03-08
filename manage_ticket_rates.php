<?php
session_start();
require_once 'connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();

        $stmt = $conn->prepare("UPDATE ticket_rates SET 
            adult_rate = ?,
            child_5_12_rate = ?,
            senior_rate = ?,
            camera_rate = ?,
            max_tickets_per_day = ?
            WHERE id = 1");
        
        $stmt->bind_param("ddddi", 
            $_POST['adult_rate'],
            $_POST['child_5_12_rate'],
            $_POST['senior_rate'],
            $_POST['camera_rate'],
            $_POST['max_tickets_per_day']
        );

        if ($stmt->execute()) {
            $conn->commit();
            $_SESSION['message'] = "Ticket rates updated successfully!";
        } else {
            throw new Exception("Error updating rates");
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }

    header('Location: manage_ticket_rates.php');
    exit();
}

// Get current rates
$rates = $conn->query("SELECT * FROM ticket_rates WHERE id = 1")->fetch_assoc();

if (isset($_GET['date'])) {
    $date = $_GET['date'];
    
    // Get max tickets per day
    $rates = $conn->query("SELECT max_tickets_per_day FROM ticket_rates WHERE id = 1")->fetch_assoc();
    $max_tickets = $rates['max_tickets_per_day'];
    
    // Get booked tickets for the date
    $stmt = $conn->prepare("SELECT SUM(adult_tickets + child_0_5_tickets + child_5_12_tickets + senior_tickets) 
                           as total_booked FROM bookings 
                           WHERE visit_date = ? AND payment_status != 'failed'");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    $booked_tickets = $result['total_booked'] ?? 0;
    $available_tickets = $max_tickets - $booked_tickets;
    
    header('Content-Type: application/json');
    echo json_encode([
        'available_tickets' => $available_tickets,
        'max_tickets' => $max_tickets,
        'booked_tickets' => $booked_tickets
    ]);
    exit();
}

http_response_code(400);
echo json_encode(['error' => 'Date parameter is required']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Ticket Rates - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .rates-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: var(--white);
            border-radius: 8px;
            box-shadow: var(--box-shadow);
        }

        .rate-form {
            display: grid;
            gap: 20px;
        }

        .rate-group {
            display: grid;
            grid-template-columns: 1fr 2fr;
            align-items: center;
            gap: 10px;
            padding: 15px;
            background: var(--bg);
            border-radius: 8px;
        }

        .rate-group label {
            font-size: 1.6rem;
            color: var(--black);
        }

        .rate-group input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1.6rem;
        }

        .submit-btn {
            background: var(--main);
            color: var(--white);
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 1.6rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            opacity: 0.9;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
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
        <div class="rates-container">
            <h2><i class="fas fa-money-bill"></i> Manage Ticket Rates</h2>

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

            <form method="POST" class="rate-form">
                <div class="rate-group">
                    <label>Adult Rate (₹)</label>
                    <input type="number" name="adult_rate" value="<?php echo $rates['adult_rate']; ?>" required min="0" step="0.01">
                </div>

                <div class="rate-group">
                    <label>Child Rate (5-12 years) (₹)</label>
                    <input type="number" name="child_5_12_rate" value="<?php echo $rates['child_5_12_rate']; ?>" required min="0" step="0.01">
                </div>

                <div class="rate-group">
                    <label>Senior Rate (₹)</label>
                    <input type="number" name="senior_rate" value="<?php echo $rates['senior_rate']; ?>" required min="0" step="0.01">
                </div>

                <div class="rate-group">
                    <label>Camera/Video Rate (₹)</label>
                    <input type="number" name="camera_rate" value="<?php echo $rates['camera_rate']; ?>" required min="0" step="0.01">
                </div>

                <div class="rate-group">
                    <label>Maximum Tickets Per Day</label>
                    <input type="number" name="max_tickets_per_day" value="<?php echo $rates['max_tickets_per_day']; ?>" required min="1">
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-save"></i> Update Rates
                </button>
            </form>
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