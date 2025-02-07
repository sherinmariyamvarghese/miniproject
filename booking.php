<?php
session_start();
require_once 'connect.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visitDate = $_POST['visit-date'];
    $adultTickets = intval($_POST['adult-tickets']);
    $child0Tickets = intval($_POST['child-0-5-tickets']);
    $child5Tickets = intval($_POST['child-5-12-tickets']);
    $seniorTickets = intval($_POST['senior-citizen-tickets']);
    $cameraVideo = isset($_POST['camera-video']) ? 1 : 0;
    $document = $_FILES['document'];

    // Validation
    if (empty($visitDate)) $errors['visit-date'] = 'Please select a visit date.';
    if ($adultTickets < 0) $errors['adult-tickets'] = 'Invalid adult ticket quantity.';
    if ($child0Tickets < 0) $errors['child-0-5-tickets'] = 'Invalid child (0-5 years) ticket quantity.';
    if ($child5Tickets < 0) $errors['child-5-12-tickets'] = 'Invalid child (5-12 years) ticket quantity.';
    if ($seniorTickets < 0) $errors['senior-citizen-tickets'] = 'Invalid senior citizen ticket quantity.';
    if (empty($document['name'])) $errors['document'] = 'Please upload a document.';

    if (empty($errors)) {
        // Process booking
        header('Location: confirmation.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zoo Ticket Booking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --primary-color: #ff6e01;
            --secondary-color: #f1e1d2;
            --text-color: #333;
        }

        body {
            font-family: 'Arial', sans-serif;
            background-color: var(--secondary-color);
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .booking-container {
            max-width: 800px;
            margin: 100px auto;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .booking-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .booking-header h1 {
            color: var(--primary-color);
            font-size: 2.5rem;
        }

        .ticket-section {
            margin-bottom: 20px;
        }

        .ticket-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .ticket-row label {
            flex-grow: 1;
            font-size: 1rem;
        }

        .ticket-row input[type="number"] {
            width: 80px;
            padding: 5px;
            text-align: center;
        }

        .total-section {
            background-color: var(--secondary-color);
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .booking-btn {
            display: block;
            width: 100%;
            padding: 12px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .booking-btn:hover {
            background-color: #ff8f2a;
        }
    </style>
</head>
<body>
    <div class="booking-container">
        <div class="booking-header">
            <h1><i class="fas fa-ticket-alt"></i> Zoo Ticket Booking</h1>
        </div>
        <form id="bookingForm" method="post" enctype="multipart/form-data">
            <div class="ticket-section">
                <div class="ticket-row">
                    <label>Adult (₹80)</label>
                    <input type="number" name="adult-tickets" min="0" value="0" onchange="calculateTotal()">
                </div>
                <div class="ticket-row">
                    <label>Child (0-5 Years) (Free)</label>
                    <input type="number" name="child-0-5-tickets" min="0" value="0" onchange="calculateTotal()">
                </div>
                <div class="ticket-row">
                    <label>Child (5-12 Years) (₹40)</label>
                    <input type="number" name="child-5-12-tickets" min="0" value="0" onchange="calculateTotal()">
                </div>
                <div class="ticket-row">
                    <label>Senior Citizen (₹40)</label>
                    <input type="number" name="senior-citizen-tickets" min="0" value="0" onchange="calculateTotal()">
                </div>
            </div>

            <div class="ticket-row">
                <label>
                    <input type="checkbox" name="camera-video" onchange="calculateTotal()"> 
                    Camera/Video Access (₹100 per person)
                </label>
            </div>

            <div class="total-section">
                <div class="total-row">
                    <strong>Total Tickets:</strong>
                    <span id="totalTickets">0</span>
                </div>
                <div class="total-row">
                    <strong>Total Amount:</strong>
                    <span id="totalAmount">₹0</span>
                </div>
            </div>

            <div class="ticket-row">
                <label>Visit Date</label>
                <input type="date" name="visit-date" required>
            </div>

            <div class="ticket-row">
                <label>Upload Document</label>
                <input type="file" name="document" required>
            </div>

            <button type="submit" class="booking-btn">Book Tickets</button>
        </form>
    </div>

    <script>
        function calculateTotal() {
            const form = document.getElementById('bookingForm');
            
            const adultTickets = parseInt(form['adult-tickets'].value) || 0;
            const child0Tickets = parseInt(form['child-0-5-tickets'].value) || 0;
            const child5Tickets = parseInt(form['child-5-12-tickets'].value) || 0;
            const seniorTickets = parseInt(form['senior-citizen-tickets'].value) || 0;
            const cameraVideo = form['camera-video'].checked;

            const totalTickets = adultTickets + child0Tickets + child5Tickets + seniorTickets;
            const adultAmount = adultTickets * 80;
            const child5Amount = child5Tickets * 40;
            const seniorAmount = seniorTickets * 40;
            const cameraAmount = cameraVideo ? totalTickets * 100 : 0;

            const totalAmount = adultAmount + child5Amount + seniorAmount + cameraAmount;

            document.getElementById('totalTickets').textContent = totalTickets;
            document.getElementById('totalAmount').textContent = `₹${totalAmount}`;
        }

        // Initial calculation
        calculateTotal();
    </script>
</body>
</html>