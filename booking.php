<?php
session_start();
require_once 'connect.php';

// Function to check current bookings for a date
function getTotalBookingsForDate($conn, $date) {
    $sql = "SELECT SUM(adult_tickets + child_0_5_tickets + child_5_12_tickets + senior_tickets) as total 
            FROM bookings 
            WHERE visit_date = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['total'] ?? 0;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Store booking data in session
    $_SESSION['booking'] = [
        'visit_date' => $_POST['visit-date'],
        'adult_tickets' => intval($_POST['adult-tickets']),
        'child_0_5_tickets' => intval($_POST['child-0-5-tickets']),
        'child_5_12_tickets' => intval($_POST['child-5-12-tickets']),
        'senior_tickets' => intval($_POST['senior-tickets']),
        'camera_video' => isset($_POST['camera-video']) ? 1 : 0,
        'document' => $_FILES['document']['name']
    ];

    // Upload document
    if ($_FILES['document']['error'] === 0) {
        $uploadDir = 'uploads/documents/';
        $uploadFile = $uploadDir . basename($_FILES['document']['name']);
        move_uploaded_file($_FILES['document']['tmp_name'], $uploadFile);
    }

    // Redirect to payment page
    header('Location: payment.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Tickets - Zoo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .booking-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: var(--white);
            border-radius: 8px;
            box-shadow: var(--box-shadow);
        }

        .ticket-section {
            background: var(--bg);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .ticket-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: var(--white);
            margin-bottom: 10px;
            border-radius: 4px;
        }

        .total-section {
            background: var(--main);
            color: var(--white);
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
            text-align: right;
            font-size: 1.8rem;
        }

        .booking-btn {
            width: 100%;
            padding: 15px;
            background: var(--main);
            color: var(--white);
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1.8rem;
            font-weight: bold;
            margin-top: 20px;
        }

        .booking-btn:hover {
            background: #ff5500;
        }

        .error-messages {
            background: #ffebee;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #ffcdd2;
        }

        .error {
            color: #c62828;
            font-size: 1.4rem;
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="booking-container">
        <h2><i class="fas fa-ticket-alt"></i> Book Your Tickets</h2>

        <form method="post" enctype="multipart/form-data">
            <div class="ticket-section">
                <h3>Select Tickets</h3>
                
                <div class="ticket-row">
                    <label>Adult (₹80)</label>
                    <input type="number" name="adult-tickets" min="0" value="0" onchange="calculateTotal()">
                </div>

                <div class="ticket-row">
                    <label>Child (0-5 Years) (Free)</label>
                    <input type="number" name="child-0-5-tickets" min="0" value="0">
                </div>

                <div class="ticket-row">
                    <label>Child (5-12 Years) (₹40)</label>
                    <input type="number" name="child-5-12-tickets" min="0" value="0" onchange="calculateTotal()">
                </div>

                <div class="ticket-row">
                    <label>Senior Citizen (₹40)</label>
                    <input type="number" name="senior-citizen-tickets" min="0" value="0" onchange="calculateTotal()">
                </div>

                <div class="ticket-row">
                    <label>
                        <input type="checkbox" name="camera-video" onchange="calculateTotal()">
                        Camera/Video Access (₹100)
                    </label>
                </div>
            </div>

            <div class="ticket-section">
                <h3>Visit Details</h3>
                
                <div class="ticket-row">
                    <label>Visit Date</label>
                    <input type="date" name="visit-date" id="visit-date" required>
                </div>

                <div class="ticket-row">
                    <label>Upload ID Proof</label>
                    <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required>
                </div>
            </div>

            <div class="total-section">
                Total Amount: ₹<span id="totalAmount">0</span>
            </div>

            <button type="submit" class="booking-btn">
                <i class="fas fa-arrow-right"></i> Proceed to Payment
            </button>
        </form>
    </div>

    <script>
        function calculateTotal() {
            const adultTickets = parseInt(document.querySelector('[name="adult-tickets"]').value) || 0;
            const child5Tickets = parseInt(document.querySelector('[name="child-5-12-tickets"]').value) || 0;
            const seniorTickets = parseInt(document.querySelector('[name="senior-citizen-tickets"]').value) || 0;
            const cameraVideo = document.querySelector('[name="camera-video"]').checked;

            const total = (adultTickets * 80) + (child5Tickets * 40) + (seniorTickets * 40) + (cameraVideo ? 100 : 0);
            document.getElementById('totalAmount').textContent = total;
        }

        // Set minimum date to today
        const dateInput = document.getElementById('visit-date');
        dateInput.min = new Date().toISOString().split('T')[0];

        // Prevent selecting Mondays
        dateInput.addEventListener('input', function(e) {
            const selected = new Date(this.value);
            if (selected.getDay() === 1) { // Monday is 1
                alert('Bookings are not available on Mondays. Please select a different date.');
                this.value = '';
            }
        });

        // Initialize total
        calculateTotal();
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>