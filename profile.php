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
        // Basic phone number validation (adjust regex as needed)
        return preg_match('/^[0-9]{10}$/', $phone);
    }

    function validateName($name) {
        // Allow letters, spaces, and hyphens
        return preg_match('/^[A-Za-z\s\'-]+$/', $name);
    }

    if (isset($_POST['update_profile'])) {
        // Collect and validate profile information
        $new_username = sanitizeInput($_POST['new_username']);
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


        // Validate phone number
        if (!validatePhone($phone)) {
            $errors[] = "Invalid phone number. Use 10 digits.";
        }

        // Validate address (basic check)
        if (empty($address) || strlen($address) < 5) {
            $errors[] = "Please provide a valid address.";
        }

        // If no errors, update profile
        if (empty($errors)) {
            $update_stmt = $conn->prepare("UPDATE users SET username = ?, phone = ?, address = ? WHERE username = ?");
            $update_stmt->bind_param("ssss", $new_username, $phone, $address, $_SESSION['user']);
            
            if ($update_stmt->execute()) {
                $_SESSION['user'] = $new_username;
                $success_msg = "Profile updated successfully!";
                
                // Refresh user data
                $user_data['username'] = $new_username;
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
        // Existing password change logic with additional validation
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

        if (strlen($new_password) < 3) {
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
    <title>Complete Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<link rel="stylesheet" href="https://unpkg.com/swiper@7/swiper-bundle.min.css" />
    <link rel="stylesheet" href="css/style.css">
    <style>
        .profile-section {
            padding: 8rem 7%;
        }
        .profile-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }
        .profile-info, .profile-form {
            background: var(--white);
            padding: 2rem;
            margin-bottom: 2rem;
            border-radius: 0.5rem;
            box-shadow: var(--box-shadow);
        }
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 1.6rem;
        }
        .form-group input {
            width: 100%;
            padding: 1rem;
            border: 1px solid #ddd;
            border-radius: 0.5rem;
            font-size: 1.6rem;
        }
        .form-group .error-message {
            color: red;
            font-size: 1.4rem;
            position: absolute;
            bottom: -1.5rem;
            left: 0;
        }
        .alert {
            padding: 1rem;
            margin-bottom: 2rem;
            border-radius: 0.5rem;
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
        <div class="container">
            <h1 class="heading">Complete Your Profile</h1>

            <?php if ($success_msg): ?>
                <div class="alert success"><?php echo $success_msg; ?></div>
            <?php endif; ?>
            
            <?php if ($error_msg): ?>
                <div class="alert error"><?php echo $error_msg; ?></div>
            <?php endif; ?>

            <div class="profile-container">
                <!-- Current Information Display -->
                <div class="profile-info">
                    <h2>Current Information</h2>
                    <p><strong>Name</strong> <?php echo htmlspecialchars($user_data['username']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user_data['email']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($user_data['phone'] ?? 'Not set'); ?></p>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($user_data['address'] ?? 'Not set'); ?></p>
                </div>

                <!-- Update Profile Form -->
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

                <!-- Change Password Form -->
                <div class="profile-form">
                    <h2>Change Password</h2>
                    <form method="POST" action="" id="passwordForm">
                        <div class="form-group">
                            <label for="current_password">Current Password:</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password">New Password:</label>
                            <input type="password" id="new_password" name="new_password" 
                                   required minlength="8" 
                                   pattern="(?=.*\d)(?=.*[A-Z]).{8,}" 
                                   title="At least 8 characters, one uppercase letter, and one number">
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password:</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" name="change_password" class="btn">Change Password</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const profileForm = document.getElementById('profileForm');
        const passwordForm = document.getElementById('passwordForm');

        // Live validation for profile form
        const fields = {
            'new_username': {
                element: document.getElementById('new_username'),
                errorElement: document.getElementById('username-error'),
                validate: function(value) {
                    if (value.length < 3) return 'Username must be at least 3 characters';
                    if (value.length > 50) return 'Username must be less than 50 characters';
                    return '';
                }
            },
          
            },
            'phone': {
                element: document.getElementById('phone'),
                errorElement: document.getElementById('phone-error'),
                validate: function(value) {
                    const phoneRegex = /^[0-9]{10}$/;
                    if (!phoneRegex.test(value)) return 'Enter a valid 10-digit phone number';
                    return '';
                }
            },
            'address': {
                element: document.getElementById('address'),
                errorElement: document.getElementById('address-error'),
                validate: function(value) {
                    if (value.length < 5) return 'Address must be at least 5 characters';
                    return '';
                }
            }
        };

        // Live validation event listeners
        Object.values(fields).forEach(field => {
            field.element.addEventListener('input', function() {
                const errorMessage = field.validate(this.value);
                field.errorElement.textContent = errorMessage;
                this.setCustomValidity(errorMessage);
            });
        });

        // Password matching validation
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');

        confirmPassword.addEventListener('input', function() {
            if (newPassword.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
            } else {
                confirmPassword.setCustomValidity('');
            }
        });
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
                <a href="#" class="links"><i class="fas fa-arrow-right"></i>home</a>
                <a href="#" class="links"><i class="fas fa-arrow-right"></i>about</a>
                <a href="#" class="links"><i class="fas fa-arrow-right"></i>gallery</a>
                <a href="#" class="links"><i class="fas fa-arrow-right"></i>animal</a>
                <a href="#" class="links"><i class="fas fa-arrow-right"></i>pricing</a>
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
</body>
</html>
</body>
</html>