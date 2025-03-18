<?php
session_start();
require_once 'connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $booking_id = $data['booking_id'] ?? null;
        
        if (!$booking_id) {
            throw new Exception('Invalid booking ID');
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        // Check if booking exists and is valid with row lock
        $stmt = $conn->prepare("
            SELECT b.*, u.username as used_by_name 
            FROM bookings b 
            LEFT JOIN users u ON b.used_by = u.id 
            WHERE b.id = ?
            FOR UPDATE
        ");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $booking = $result->fetch_assoc();
        
        if (!$booking) {
            throw new Exception('Booking not found');
        }
        
        // Check status directly from database
        if ($booking['status'] === 'used' && $booking['used_at'] !== null) {
            $used_date = date('d M Y H:i', strtotime($booking['used_at']));
            throw new Exception("Ticket already used by {$booking['used_by_name']} on {$used_date}");
        }
        
        if (strtotime($booking['visit_date']) !== strtotime('today')) {
            throw new Exception('Ticket is only valid for today\'s date: ' . date('d M Y', strtotime('today')));
        }
        
        // Update booking status
        $stmt = $conn->prepare("
            UPDATE bookings 
            SET status = 'used', 
                used_at = NOW(), 
                used_by = (SELECT id FROM users WHERE username = ?) 
            WHERE id = ? 
            AND status = 'pending'
            AND used_at IS NULL
            AND used_by IS NULL
        ");
        $stmt->bind_param("si", $_SESSION['user'], $booking_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $conn->commit();
            $response['success'] = true;
            $response['message'] = 'Ticket validated successfully for ' . $booking['name'];
        } else {
            $conn->rollback();
            throw new Exception('Failed to update ticket status or ticket already used');
        }
        
    } catch (Exception $e) {
        if (isset($conn)) $conn->rollback();
        $response['message'] = $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Tickets - SafariGate Zoo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://unpkg.com/html5-qrcode"></script>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="scanner-container">
        <h2><i class="fas fa-qrcode"></i> Scan Visitor Tickets</h2>
        <div id="reader"></div>
        <div id="result"></div>
    </div>

    <script>
        function onScanSuccess(decodedText, decodedResult) {
            try {
                const data = JSON.parse(decodedText);
                
                // Send to backend for validation
                fetch('scan_ticket.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(result => {
                    const resultDiv = document.getElementById('result');
                    resultDiv.innerHTML = `
                        <div class="alert ${result.success ? 'alert-success' : 'alert-error'}">
                            ${result.message}
                        </div>
                    `;
                    
                    if (result.success) {
                        // Play success sound
                        new Audio('sounds/success.mp3').play();
                    } else {
                        // Play error sound
                        new Audio('sounds/error.mp3').play();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            } catch (error) {
                console.error('Invalid QR code data:', error);
            }
        }

        const html5QrcodeScanner = new Html5QrcodeScanner(
            "reader", { fps: 10, qrbox: 250 }
        );
        html5QrcodeScanner.render(onScanSuccess);
    </script>

    <style>
        .scanner-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        #reader {
            width: 100%;
            margin: 2rem 0;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            text-align: center;
            font-size: 1.2rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</body>
</html>