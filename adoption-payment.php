<?php
session_start();
require_once 'connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process payment and update adoption
    $sql = "UPDATE adoptions 
            SET status = 'completed',
                payment_status = 'completed',
                name = ?,
                email = ?,
                phone = ?,
                city = ?,
                address = ?
            WHERE user_id = ? AND status = 'pending'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssi", 
        $_POST['name'],
        $_POST['email'],
        $_POST['phone'],
        $_POST['city'],
        $_POST['address'],
        $_SESSION['user_id']
    );

    if ($stmt->execute()) {
        // Clear adoption session
        echo "<script>sessionStorage.removeItem('adoptions');</script>";
        header('Location: adoption-confirmation.php');
        exit;
    }
}

// Fetch pending adoptions for this user
$stmt = $conn->prepare("
    SELECT a.id, a.period_type, a.amount,
           a.animal_name, a.animal_id
    FROM adoptions a
    WHERE a.user_id = ? AND a.status = 'pending'
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$pendingAdoptions = $result->fetch_all(MYSQLI_ASSOC);

if (empty($pendingAdoptions)) {
    header('Location: adoption.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adoption Payment - SafariGate</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .payment-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: var(--white);
            border-radius: 8px;
            box-shadow: var(--box-shadow);
        }

        .booking-summary {
            background: var(--bg);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .booking-summary h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.8rem;
        }

        .booking-summary p {
            font-size: 1.6rem;
            margin: 10px 0;
            color: #666;
        }

        .booking-summary h4 {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #ddd;
            color: #ff6e01;
            font-size: 1.8rem;
        }

        .personal-details {
            margin: 20px 0;
            padding: 20px;
            background: var(--bg);
            border-radius: 8px;
        }

        .personal-details h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.8rem;
        }

        .personal-details input,
        .personal-details textarea {
            width: 100%;
            padding: 12px;
            margin: 8px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1.6rem;
            transition: all 0.3s ease;
        }

        .personal-details input:focus,
        .personal-details textarea:focus {
            border-color: #ff6e01;
            box-shadow: 0 0 5px rgba(255, 110, 1, 0.2);
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .payment-method {
            padding: 20px;
            border: 2px solid var(--main);
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s ease;
        }

        .payment-method:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .payment-method.selected {
            background: var(--main);
            color: var(--white);
        }

        .payment-method i {
            margin-bottom: 10px;
            color: #ff6e01;
        }

        .payment-method.selected i {
            color: var(--white);
        }

        .payment-method p {
            font-size: 1.6rem;
            margin: 10px 0 0;
        }

        .payment-details {
            margin: 20px 0;
            display: none;
        }

        .payment-details input,
        .payment-details select {
            width: 100%;
            padding: 12px;
            margin: 8px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1.6rem;
        }

        .confirm-btn {
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
            transition: all 0.3s ease;
        }

        .confirm-btn:hover {
            background: #ff5500;
            transform: translateY(-2px);
        }

        /* Validation styles */
        input:valid,
        textarea:valid {
            border-color: #28a745;
        }

        input:invalid,
        textarea:invalid {
            border-color: #dc3545;
        }

        .validation-message {
            color: #dc3545;
            font-size: 1.4rem;
            margin-top: 5px;
        }

        /* Card details specific styles */
        #cardDetails {
            background: var(--bg);
            padding: 20px;
            border-radius: 8px;
        }

        #cardDetails .card-row {
            display: flex;
            gap: 15px;
        }

        #cardDetails .card-row input {
            flex: 1;
        }

        /* UPI and Netbanking specific styles */
        #upiDetails,
        #netbankingDetails {
            background: var(--bg);
            padding: 20px;
            border-radius: 8px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .payment-container {
                margin: 10px;
                padding: 15px;
            }

            .payment-methods {
                grid-template-columns: 1fr;
            }

            #cardDetails .card-row {
                flex-direction: column;
                gap: 8px;
            }
        }

        .page-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            gap: 20px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            background: var(--bg);
            color: var(--main);
            border-radius: 5px;
            text-decoration: none;
            font-size: 1.6rem;
            transition: all 0.3s ease;
        }

        .back-btn i {
            margin-right: 8px;
        }

        .back-btn:hover {
            background: var(--main);
            color: var(--white);
            transform: translateX(-5px);
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <br><br><br><br><br><br><br><br><br>
    
    <div class="payment-container">
        <div class="page-header">
            <a href="adoption.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Adoption
            </a>
            <h2><i class="fas fa-credit-card"></i> Adoption Payment Details</h2>
        </div>
        
        <div class="booking-summary">
            <h3>Adoption Summary</h3>
            <div id="adoptionSummary">
                <?php foreach ($pendingAdoptions as $adoption): ?>
                    <p><?php echo $adoption['animal_name']; ?> - 
                       <?php echo ucfirst(str_replace('_', ' ', $adoption['period_type'])); ?> 
                       (₹<?php echo number_format($adoption['amount']); ?>)</p>
                <?php endforeach; ?>
                <h4>Total Amount: ₹<?php 
                    $total = array_sum(array_column($pendingAdoptions, 'amount'));
                    echo number_format($total); 
                ?></h4>
            </div>
        </div>

        <form method="post" id="paymentForm">
            <div class="personal-details">
                <h3>Personal Details</h3>
                <input type="text" name="name" placeholder="Full Name" required>
                <input type="email" name="email" placeholder="Email Address" required>
                <input type="tel" name="phone" placeholder="Phone Number" pattern="[0-9]{10}" required>
                <input type="text" name="city" placeholder="City" required>
                <textarea name="address" placeholder="Full Address" required></textarea>
            </div>

            <div class="payment-methods">
                <div class="payment-method" onclick="selectPayment('card')">
                    <i class="fas fa-credit-card fa-2x"></i>
                    <p>Credit/Debit Card</p>
                </div>
                <div class="payment-method" onclick="selectPayment('upi')">
                    <i class="fas fa-mobile-alt fa-2x"></i>
                    <p>UPI Payment</p>
                </div>
                <div class="payment-method" onclick="selectPayment('netbanking')">
                    <i class="fas fa-university fa-2x"></i>
                    <p>Net Banking</p>
                </div>
            </div>

            <!-- Hidden fields for adoption details -->
            <input type="hidden" name="animal_id" id="animal_id">
            <input type="hidden" name="period_type" id="period_type">
            <input type="hidden" name="total_amount" id="total_amount">

            <div class="payment-details" id="cardDetails" style="display: none;">
                <input type="text" placeholder="Card Number" pattern="[0-9]{16}" maxlength="16">
                <div style="display: flex; gap: 10px;">
                    <input type="text" placeholder="MM/YY" pattern="[0-9]{2}/[0-9]{2}" maxlength="5">
                    <input type="text" placeholder="CVV" pattern="[0-9]{3}" maxlength="3">
                </div>
                <input type="text" placeholder="Card Holder Name">
            </div>

            <div class="payment-details" id="upiDetails" style="display: none;">
                <input type="text" placeholder="Enter UPI ID">
            </div>

            <div class="payment-details" id="netbankingDetails" style="display: none;">
                <select class="form-control">
                    <option>Select Bank</option>
                    <option>State Bank of India</option>
                    <option>HDFC Bank</option>
                    <option>ICICI Bank</option>
                    <option>Axis Bank</option>
                </select>
            </div>

            <button type="submit" class="confirm-btn">Confirm Payment</button>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const summaryDiv = document.getElementById('adoptionSummary');
            let totalAmount = 0;

            // Display pending adoptions from PHP
            <?php foreach ($pendingAdoptions as $adoption): ?>
                totalAmount += <?php echo $adoption['amount']; ?>;
                summaryDiv.innerHTML += `
                    <p><?php echo $adoption['animal_name']; ?> - 
                       <?php echo ucfirst(str_replace('_', ' ', $adoption['period_type'])); ?> 
                       (₹<?php echo number_format($adoption['amount']); ?>)</p>
                `;
            <?php endforeach; ?>

            summaryDiv.innerHTML += `<h4>Total Amount: ₹${totalAmount.toLocaleString()}</h4>`
        });
    </script>
</body>
</html>