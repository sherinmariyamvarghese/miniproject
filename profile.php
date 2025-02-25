<?php
session_start();
require_once 'connect.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

// Initialize message variables
$success_msg = $error_msg = "";

// Fetch current user data with additional profile fields
$stmt = $conn->prepare("SELECT username, email, phone, address FROM users WHERE username = ?");
$stmt->bind_param("s", $_SESSION['user']);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize and validate input functions
    function sanitizeInput($input) {
        return htmlspecialchars(trim($input));
    }

    function validatePhone($phone) {
        // Remove any non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Check if exactly 10 digits and doesn't start with 0
        if (!preg_match('/^[1-9][0-9]{9}$/', $phone)) {
            return false;
        }
        
        // Check for repeated digits (more than 7 times)
        if (preg_match('/(.)\1{7,}/', $phone)) {
            return false;
        }
        
        // Check for sequential numbers (ascending or descending)
        if (preg_match('/(0123456789|9876543210)/', $phone)) {
            return false;
        }
        
        return true;
    }

    function validateName($name) {
        return preg_match('/^[A-Za-z\s\'-]+$/', $name);
    }

    if (isset($_POST['update_profile'])) {
        // Collect and validate profile information
        $new_username = sanitizeInput($_POST['new_username']);
        $new_email = sanitizeInput($_POST['email']);
        $phone = sanitizeInput($_POST['phone']);
        $address = sanitizeInput($_POST['address']);

        $errors = [];

        // Validate username
        if ($new_username !== $_SESSION['user']) {
            $check_stmt = $conn->prepare("SELECT username FROM users WHERE username = ? AND username != ?");
            $check_stmt->bind_param("ss", $new_username, $_SESSION['user']);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                $errors[] = "Username already exists.";
            }
        }

        // Validate email
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        } else {
            $check_email_stmt = $conn->prepare("SELECT email FROM users WHERE email = ? AND username != ?");
            $check_email_stmt->bind_param("ss", $new_email, $_SESSION['user']);
            $check_email_stmt->execute();
            if ($check_email_stmt->get_result()->num_rows > 0) {
                $errors[] = "Email already exists.";
            }
        }

        // Validate phone number
        if (!validatePhone($phone)) {
            $errors[] = "Invalid phone number. Must be 10 digits, cannot start with 0, and cannot contain repeated or sequential numbers.";
        }

        // Validate address
        if (empty($address) || strlen($address) < 5) {
            $errors[] = "Please provide a valid address.";
        }

        // If no errors, update profile
        if (empty($errors)) {
            $update_stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, phone = ?, address = ? WHERE username = ?");
            $update_stmt->bind_param("sssss", $new_username, $new_email, $phone, $address, $_SESSION['user']);
            
            if ($update_stmt->execute()) {
                $_SESSION['user'] = $new_username;
                $success_msg = "Profile updated successfully!";
                
                // Refresh user data
                $user_data['username'] = $new_username;
                $user_data['email'] = $new_email;
                $user_data['phone'] = $phone;
                $user_data['address'] = $address;
            } else {
                $error_msg = "Error updating profile: " . $update_stmt->error;
            }
            $update_stmt->close();
        } else {
            $error_msg = implode("<br>", $errors);
        }
    } elseif (isset($_POST['change_password'])) {
        // Password change logic remains the same as your original code
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE username = ?");
        $stmt->bind_param("s", $_SESSION['user']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        $password_errors = [];

        if (!password_verify($current_password, $user['password'])) {
            $password_errors[] = "Current password is incorrect.";
        }

        if ($new_password !== $confirm_password) {
            $password_errors[] = "New passwords do not match.";
        }

        if (strlen($new_password) < 8) {
            $password_errors[] = "New password must be at least 8 characters long.";
        }

        if (!preg_match('/[A-Z]/', $new_password)) {
            $password_errors[] = "Password must contain at least one uppercase letter.";
        }

        if (!preg_match('/[0-9]/', $new_password)) {
            $password_errors[] = "Password must contain at least one number.";
        }

        if (empty($password_errors)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
            $update_stmt->bind_param("ss", $hashed_password, $_SESSION['user']);
            
            if ($update_stmt->execute()) {
                $success_msg = "Password updated successfully!";
            } else {
                $error_msg = "Error updating password.";
            }
        } else {
            $error_msg = implode("<br>", $password_errors);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo htmlspecialchars($user_data['username']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<link rel="stylesheet" href="https://unpkg.com/swiper@7/swiper-bundle.min.css" />
<link rel="stylesheet" href="css/style.css">

    <style>
        .profile-section {
            padding: 8rem 7%;
            background-color: #f7f7f7;
        }
        
        .profile-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background-color: #e1e1e1;
            margin: 0 auto 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: #666;
        }

        .profile-name {
            font-size: 2.8rem;
            color: #333;
            margin-bottom: 1rem;
        }

        .profile-info {
            background: var(--white);
            padding: 3rem;
            margin-bottom: 3rem;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
        }

        .info-item {
            padding: 1.5rem;
            background: #f9f9f9;
            border-radius: 0.8rem;
        }

        .info-label {
            font-size: 1.6rem;
            color: #666;
            margin-bottom: 0.5rem;
        }

        .info-value {
            font-size: 1.8rem;
            color: #333;
            font-weight: 600;
        }

        .profile-form {
            background: var(--white);
            padding: 3rem;
            margin-bottom: 3rem;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1);
        }

        .form-group {
            margin-bottom: 2rem;
        }

        .form-group label {
            display: block;
            font-size: 1.6rem;
            color: #666;
            margin-bottom: 1rem;
        }

        .form-group input {
            width: 100%;
            padding: 1.2rem;
            border: 1px solid #ddd;
            border-radius: 0.8rem;
            font-size: 1.6rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            border-color: var(--green);
            box-shadow: 0 0 0 2px rgba(0,128,0,0.1);
        }

        .error-message {
            color: #dc3545;
            font-size: 1.4rem;
            margin-top: 0.5rem;
        }

        .alert {
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-radius: 0.8rem;
            font-size: 1.6rem;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .btn {
            padding: 1.2rem 2.4rem;
            font-size: 1.6rem;
            text-transform: uppercase;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.2);
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-container {
                padding: 1rem;
            }
            
            .profile-info,
            .profile-form {
                padding: 2rem;
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

    <section class="profile-section">
        <div class="profile-container">
            <div class="profile-header">
                <div class="profile-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <h1 class="profile-name"><?php echo htmlspecialchars($user_data['username']); ?></h1>
            </div>

            <?php if ($success_msg): ?>
                <div class="alert success"><?php echo $success_msg; ?></div>
            <?php endif; ?>
            
            <?php if ($error_msg): ?>
                <div class="alert error"><?php echo $error_msg; ?></div>
            <?php endif; ?>

            <div class="profile-info">
                <h2>Current Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($user_data['username']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($user_data['email']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Phone</div>
                        <div class="info-value"><?php echo htmlspecialchars($user_data['phone'] ?? 'Not set'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Address</div>
                        <div class="info-value"><?php echo htmlspecialchars($user_data['address'] ?? 'Not set'); ?></div>
                    </div>
                </div>
            </div>

            <div class="profile-form">
                <h2>Update Profile</h2>
                <form method="POST" action="" id="profileForm">
                    <div class="form-group">
                        <label for="new_username">Name:</label>
                        <input type="text" id="new_username" name="new_username" 
                               value="<?php echo htmlspecialchars($user_data['username']); ?>" 
                               required minlength="3" maxlength="50">
                        <span class="error-message" id="username-error"></span>
                    </div>

                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($user_data['email']); ?>" 
                               required>
                        <span class="error-message" id="email-error"></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number:</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>" 
                               required pattern="[0-9]{10}" title="10 digit phone number">
                        <span class="error-message" id="phone-error"></span>
                    </div>

                    <div class="form-group">
                        <label for="address">Address:</label>
                        <input type="text" id="address" name="address" 
                               value="<?php echo htmlspecialchars($user_data['address'] ?? ''); ?>" 
                               required minlength="5">
                        <span class="error-message" id="address-error"></span>
                    </div>

                    <button type="submit" name="update_profile" class="btn">Update Profile</button>
                </form>
            </div>

            <div class="profile-form">
                <h2>Change Password</h2>
                <form method="POST" action="" id="passwordForm">
                    <div class="form-group">
                        <label for="current_password">Current Password:</label>
                        <input type="password" id="current_password" name="current_password" required>
                        <span class="error-message" id="current-password-error"></span>
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password:</label>
                        <input type="password" id="new_password" name="new_password" 
                               required minlength="8" 
                               pattern="(?=.*\d)(?=.*[A-Z]).{8,}" 
                               title="At least 8 characters, one uppercase letter, and one number">
                        <span class="error-message" id="new-password-error"></span>
                    </div>

                    <div class="form-group">
                    <label for="confirm_password">Confirm New Password:</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                        <span class="error-message" id="confirm-password-error"></span>
                    </div>

                    <button type="submit" name="change_password" class="btn">Change Password</button>
                </form>
            </div>
        </div>
    </section>

    <script>
       // Get all form elements
const form = document.getElementById('profileForm');
const usernameInput = document.getElementById('new_username');
const phoneInput = document.getElementById('phone');
const addressInput = document.getElementById('address');

// Real-time username validation
usernameInput.addEventListener('input', function() {
    const usernameError = document.getElementById('username-error');
    if (this.value.length < 3) {
        usernameError.textContent = 'Username must be at least 3 characters long';
        this.classList.add('invalid');
    } else if (this.value.length > 50) {
        usernameError.textContent = 'Username must be less than 50 characters';
        this.classList.add('invalid');
    } else if (!/^[A-Za-z\s\'-]+$/.test(this.value)) {
        usernameError.textContent = 'Username can only contain letters, spaces, hyphens, and apostrophes';
        this.classList.add('invalid');
    } else {
        usernameError.textContent = '';
        this.classList.remove('invalid');
    }
});

// Real-time phone validation
phoneInput.addEventListener('input', function() {
    const phoneError = document.getElementById('phone-error');
    // Remove non-digits
    this.value = this.value.replace(/\D/g, '');
    
    const phone = this.value;
    let errorMessage = '';
    
    if (phone.length !== 10) {
        errorMessage = 'Phone number must be exactly 10 digits';
    } else if (phone.charAt(0) === '0') {
        errorMessage = 'Phone number cannot start with 0';
    } else if (/(.)\1{7,}/.test(phone)) {
        errorMessage = 'Phone number cannot contain excessive repeated digits';
    } else if (/(0123456789|9876543210)/.test(phone)) {
        errorMessage = 'Phone number cannot be sequential';
    }
    
    if (errorMessage) {
        phoneError.textContent = errorMessage;
        this.classList.add('invalid');
    } else {
        phoneError.textContent = '';
        this.classList.remove('invalid');
    }
});

// Real-time address validation
addressInput.addEventListener('input', function() {
    const addressError = document.getElementById('address-error');
    if (this.value.length < 5) {
        addressError.textContent = 'Address must be at least 5 characters long';
        this.classList.add('invalid');
    } else {
        addressError.textContent = '';
        this.classList.remove('invalid');
    }
});

// Password form validation
const passwordForm = document.getElementById('passwordForm');
const currentPasswordInput = document.getElementById('current_password');
const newPasswordInput = document.getElementById('new_password');
const confirmPasswordInput = document.getElementById('confirm_password');

// Real-time current password validation
currentPasswordInput.addEventListener('input', function() {
    const currentPasswordError = document.getElementById('current-password-error');
    if (this.value.length < 1) {
        currentPasswordError.textContent = 'Current password is required';
        this.classList.add('invalid');
    } else {
        currentPasswordError.textContent = '';
        this.classList.remove('invalid');
    }
});

// Real-time new password validation
newPasswordInput.addEventListener('input', function() {
    const newPasswordError = document.getElementById('new-password-error');
    const password = this.value;
    
    if (password.length < 8) {
        newPasswordError.textContent = 'Password must be at least 8 characters long';
        this.classList.add('invalid');
    } else if (!/[A-Z]/.test(password)) {
        newPasswordError.textContent = 'Password must contain at least one uppercase letter';
        this.classList.add('invalid');
    } else if (!/[0-9]/.test(password)) {
        newPasswordError.textContent = 'Password must contain at least one number';
        this.classList.add('invalid');
    } else {
        newPasswordError.textContent = '';
        this.classList.remove('invalid');
    }
    
    // Update confirm password validation
    if (confirmPasswordInput.value) {
        confirmPasswordInput.dispatchEvent(new Event('input'));
    }
});

// Real-time confirm password validation
confirmPasswordInput.addEventListener('input', function() {
    const confirmPasswordError = document.getElementById('confirm-password-error');
    if (this.value !== newPasswordInput.value) {
        confirmPasswordError.textContent = 'Passwords do not match';
        this.classList.add('invalid');
    } else {
        confirmPasswordError.textContent = '';
        this.classList.remove('invalid');
    }
});
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
<script src="https://unpkg.com/swiper@7/swiper-bundle.min.js"></script>

<script src="js/script.js"></script>


<script src="js/script.js"></script>
</body>
</html>