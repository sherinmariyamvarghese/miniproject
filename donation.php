<?php
session_start();
include 'connect.php';

if(isset($_POST['donate'])) {
    $amount = $_POST['amount'];
    $message = $_POST['message'];
    $date = date('Y-m-d');
    
    // If user is logged in, get their ID
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : NULL;
    
    // Store donation details in session for payment confirmation
    $_SESSION['pending_donation'] = [
        'amount' => $amount,
        'message' => $message,
        'date' => $date,
        'user_id' => $user_id
    ];
    
    // Redirect to donation confirmation page
    header('Location: donation-confirmation.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Donation - Zoo Website</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<link rel="stylesheet" href="https://unpkg.com/swiper@7/swiper-bundle.min.css" />
<link rel="stylesheet" href="css/style.css">

    <style>
        /* Main donation form styling */
        .donation-section {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f1e1d2;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .donation-header {
            text-align: center;
            color: #ff6e01;
            margin-bottom: 30px;
        }

        .donation-header h2 {
            font-size: 36px;
            margin-bottom: 10px;
        }

        .donation-header p {
            font-size: 16px;
            color: #666;
        }

        .donation-form {
            background: white;
            padding: 30px;
            border-radius: 8px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 16px;
            margin-bottom: 8px;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }

        .form-group textarea {
            height: 100px;
            resize: vertical;
        }

        .donation-options {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .amount-option {
            flex: 1;
            text-align: center;
            padding: 15px;
            border: 2px solid #ff6e01;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .amount-option:hover {
            background: #ff6e01;
            color: white;
        }

        .custom-amount {
            margin-top: 20px;
        }

        .submit-btn {
            background: #ff6e01;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 5px;
            font-size: 18px;
            cursor: pointer;
            width: 100%;
            transition: background 0.3s ease;
        }

        .submit-btn:hover {
            background: #ff9748;
        }

        .message {
            text-align: center;
            margin: 20px 0;
            padding: 10px;
            border-radius: 5px;
        }

        .success {
            background: #d4edda;
            color: #155724;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .donation-section {
                margin: 20px;
            }

            .donation-options {
                flex-direction: column;
            }

            .amount-option {
                width: 100%;
            }
        }
    </style>
</head>
<body>


<?php include 'header.php'; ?>

    <div class="donation-section">
        <div class="donation-header">
            <h2>Make a Donation</h2>
            <p>Your support helps us protect and care for our amazing animals</p>
        </div>

        <?php if(isset($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if(isset($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form class="donation-form" method="POST" action="">
            <div class="donation-options">
                <div class="amount-option" onclick="setAmount(10)">$10</div>
                <div class="amount-option" onclick="setAmount(25)">$25</div>
                <div class="amount-option" onclick="setAmount(50)">$50</div>
                <div class="amount-option" onclick="setAmount(100)">$100</div>
            </div>

            <div class="form-group custom-amount">
                <label for="amount">Custom Amount ($)</label>
                <input type="number" id="amount" name="amount" min="1" required>
            </div>

            <div class="form-group">
                <label for="message">Leave a Message (Optional)</label>
                <textarea id="message" name="message" placeholder="Share why you're making this donation..."></textarea>
            </div>

            <button type="submit" name="donate" class="submit-btn">Proceed to Payment</button>
        </form>
    </div>

    <script>
        function setAmount(amount) {
            document.getElementById('amount').value = amount;
            // Remove active class from all options
            document.querySelectorAll('.amount-option').forEach(option => {
                option.style.background = '';
                option.style.color = '';
            });
            // Add active class to selected option
            event.target.style.background = '#ff6e01';
            event.target.style.color = 'white';
        }
    </script>
    
    <?php include 'footer.php'; ?>

</body>
</html>