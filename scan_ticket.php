<?php
session_start();
require_once 'connect.php';

// Only process API requests when receiving POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Check if admin is logged in
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }

    // Get the QR data from the request
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data || !isset($data['booking_id'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid QR code data']);
        exit;
    }

    // Validate booking exists
    $stmt = $conn->prepare("SELECT b.* FROM bookings b WHERE b.id = ?");
    $stmt->bind_param("i", $data['booking_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();

    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit;
    }

    // Check if booking is already used
    if ($booking['status'] === 'used') {
        echo json_encode([
            'success' => false, 
            'message' => 'This ticket has already been used',
            'used_at' => $booking['used_at']
        ]);
        exit;
    }

    // Verify booking date
    $today = date('Y-m-d');
    $visit_date = $booking['visit_date'];

    if ($visit_date < $today) {
        echo json_encode(['success' => false, 'message' => 'This ticket has expired']);
        exit;
    }

    if ($visit_date > $today) {
        echo json_encode(['success' => false, 'message' => 'This ticket is for a future date: ' . date('F d, Y', strtotime($visit_date))]);
        exit;
    }

    // Mark booking as used
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("UPDATE bookings SET status = 'used', used_at = ? WHERE id = ?");
    $stmt->bind_param("si", $now, $data['booking_id']);

    if ($stmt->execute()) {
        // Return success with booking details
        echo json_encode([
            'success' => true,
            'message' => 'Ticket validated successfully',
            'booking' => [
                'id' => $booking['id'],
                'name' => $booking['name'],
                'visit_date' => date('F d, Y', strtotime($booking['visit_date'])),
                'total_tickets' => $booking['adult_tickets'] + $booking['child_0_5_tickets'] + 
                                $booking['child_5_12_tickets'] + $booking['senior_tickets'],
                'used_at' => $now
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating booking status']);
    }
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
        
        <?php if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin'): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> You must be logged in as an admin to use this feature.
            </div>
        <?php else: ?>
            <div id="reader"></div>
            <div id="result"></div>
            <div id="scan-history">
                <h3>Recent Scans</h3>
                <div id="history-list"></div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Only initialize scanner if admin is logged in
        <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && $_SESSION['role'] === 'admin'): ?>
        
        // Keep track of scan history
        const scanHistory = [];
        
        function updateScanHistory(result) {
            // Add to history
            scanHistory.unshift({
                time: new Date(),
                result: result
            });
            
            // Limit history to 10 items
            if (scanHistory.length > 10) {
                scanHistory.pop();
            }
            
            // Update display
            const historyList = document.getElementById('history-list');
            historyList.innerHTML = '';
            
            scanHistory.forEach(item => {
                const timeString = item.time.toLocaleTimeString();
                const historyItem = document.createElement('div');
                historyItem.className = `history-item ${item.result.success ? 'success' : 'error'}`;
                historyItem.innerHTML = `
                    <span class="time">${timeString}</span>
                    <span class="message">${item.result.message}</span>
                `;
                historyList.appendChild(historyItem);
            });
        }

        function onScanSuccess(decodedText, decodedResult) {
            console.log(`QR Code detected: ${decodedText}`);
            
            // Try to parse the QR code data
            try {
                let qrData;
                
                // Try to parse as JSON first
                try {
                    qrData = JSON.parse(decodedText);
                } catch (e) {
                    // If not JSON, assume it's just a booking ID
                    qrData = { booking_id: parseInt(decodedText) };
                }
                
                // Ensure booking_id exists and is valid
                if (!qrData.booking_id || isNaN(qrData.booking_id)) {
                    throw new Error("Invalid QR code format");
                }
                
                // Process the scan
                fetch('scan_ticket.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(qrData)
                })
                .then(response => response.json())
                .then(result => {
                    console.log('Success:', result);
                    const resultDiv = document.getElementById('result');
                    
                    if (result.success) {
                        resultDiv.innerHTML = `
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i>
                                ${result.message}
                            </div>
                            <div class="booking-details">
                                <h3>Booking Details</h3>
                                <p><strong>Booking ID:</strong> ${result.booking.id}</p>
                                <p><strong>Name:</strong> ${result.booking.name}</p>
                                <p><strong>Visit Date:</strong> ${result.booking.visit_date}</p>
                                <p><strong>Total Tickets:</strong> ${result.booking.total_tickets}</p>
                            </div>
                        `;
                        
                        // Play success sound
                        new Audio('sounds/success.mp3').play();
                    } else {
                        resultDiv.innerHTML = `
                            <div class="alert alert-error">
                                <i class="fas fa-exclamation-circle"></i>
                                ${result.message}
                            </div>
                        `;
                        
                        // Play error sound
                        new Audio('sounds/error.mp3').play();
                    }
                    
                    // Update scan history
                    updateScanHistory(result);
                })
                .catch(error => {
                    console.error('Error:', error);
                    const resultDiv = document.getElementById('result');
                    resultDiv.innerHTML = `
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            Error processing request. Please try again.
                        </div>
                    `;
                });
            } catch (error) {
                console.error('Invalid QR code data:', error);
                const resultDiv = document.getElementById('result');
                resultDiv.innerHTML = `
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        Invalid QR code format.
                    </div>
                `;
            }
        }

        const html5QrcodeScanner = new Html5QrcodeScanner(
            "reader", { fps: 10, qrbox: 250 }
        );
        html5QrcodeScanner.render(onScanSuccess);
        
        <?php endif; ?>
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .booking-details {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1.5rem;
        }
        
        .booking-details h3 {
            margin-top: 0;
            color: var(--main);
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }
        
        #scan-history {
            margin-top: 2rem;
            border-top: 1px solid #dee2e6;
            padding-top: 1rem;
        }
        
        #scan-history h3 {
            color: var(--main);
            margin-bottom: 1rem;
        }
        
        .history-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 0.5rem;
        }
        
        .history-item.success {
            background: #f0f8f1;
            border-left: 3px solid #28a745;
        }
        
        .history-item.error {
            background: #fff5f5;
            border-left: 3px solid #dc3545;
        }
        
        .history-item .time {
            font-weight: bold;
            color: #6c757d;
        }
    </style>
</body>
</html>