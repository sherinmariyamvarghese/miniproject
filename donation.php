<?php
session_start();
include 'connect.php';

if(isset($_POST['donate'])) {
    $amount = $_POST['amount'];
    $message = $_POST['message'];
    $date = date('Y-m-d');
    
    // If user is logged in, get their ID
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : NULL;
    
    $sql = "INSERT INTO donations (user_id, amount, message, donation_date) 
            VALUES (?, ?, ?, ?)";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("idss", $user_id, $amount, $message, $date);
    
    if($stmt->execute()) {
        $success_message = "Thank you for your donation!";
    } else {
        $error_message = "Error processing donation. Please try again.";
    }
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


<header class="header">
    <a href="#" class="logo"> <i class="fas fa-paw"></i> SafariGate</a>
    
    <nav class="navbar">
        <a href="index.php#home">home</a>
        <a href="index.php#about">about</a>
        <a href="index.php#gallery">gallery</a>
        <a href="index.php#animal">animal</a>
        <a href="index.php#pricing">pricing</a>
        <a href="index.php#contact">contact</a>
        <a href="d.php">donation</a>
        <a href="a.php">adoption</a>
    </nav>

    <div class="icons">
        <?php if(isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
            <div id="login-btn" class="fas fa-user">
                <form class="login-form">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user']); ?></span>
                    <a href="profile.php" class="btn">Profile</a>
                    <a href="logout.php" class="btn">Logout</a>
                </form>
            </div>
        <?php else: ?>
            <div id="login-btn" class="fas fa-user"></div>
            <div id="menu-btn" class="fas fa-bars"></div>
        <?php endif; ?>
    </div>

    <?php if(!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true): ?>
    <form action="login.php" class="login-form">
        <a href="login.php" class="btn">Login</a>
        <a href="register.php" class="btn">Register</a>
    </form>
    <?php endif; ?>
</header>

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

            <button type="submit" name="donate" class="submit-btn">Donate Now</button>
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

<section class="footer">

<div class="box-container">

    <div class="box">
        <h3><i class="fas fa-paw"></i> zoo</h3>
        <p>Lorem ipsum dolor sit amet consectetur adipisicing elit.</p>
        <p class="links"><i class="fas fa-clock"></i>monday - friday</p>
        <p class="days">7:00AM - 11:00PM</p>
    </div>

    <div class="box">
        <h3>Contact Info</h3>
        <a href="#" class="links"><i class="fas fa-phone"></i> 1245-147-2589</a>
        <a href="#" class="links"><i class="fas fa-phone"></i> 1245-147-2589</a>
        <a href="#" class="links"><i class="fas fa-envelope"></i> info@zoolife.com</a>
        <a href="#" class="links"><i class="fas fa-map-marker-alt"></i> karachi, pakistan</a>
    </div>

    <div class="box">
        <h3>quick links</h3>
        <a href="#" class="links"> <i class="fas fa-arrow-right"></i>home</a>
        <a href="#" class="links"> <i class="fas fa-arrow-right"></i>about</a>
        <a href="#" class="links"> <i class="fas fa-arrow-right"></i>gallery</a>
        <a href="#" class="links"> <i class="fas fa-arrow-right"></i>animal</a>
        <a href="#" class="links"> <i class="fas fa-arrow-right"></i>pricing</a>
    </div>

    <div class="box">
        <h3>newsletter</h3>
        <p>subscribe for latest updates</p>
        <input type="email" placeholder="Your Email" class="email">
        <a href="#" class="btn">subscribe</a>
        <div class="share">
            <a href="#" class="fab fa-facebook-f"></a>
            <a href="#" class="fab fa-twitter"></a>
            <a href="#" class="fab fa-instagram"></a>
            <a href="#" class="fab fa-linkedin"></a>
        </div>
    </div>

</div>

<div class="credit">&copy; 2022 zoolife. All rights reserved by <a href="#" class="link">ninjashub</a></div>

</section>






<!-- end -->















<script src="https://unpkg.com/swiper@7/swiper-bundle.min.js"></script>

<script src="js/script.js"></script>


<script src="js/script.js"></script>
</body>
</html>