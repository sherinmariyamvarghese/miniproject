<?php 
session_start(); 
include 'connect.php';

if (!isset($_SESSION['otp_verified']) || !isset($_SESSION['reset_email'])) {
    header("Location: forgot-password.php");
    exit();
}

$message = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    $email = $_SESSION['reset_email'];
    
    if (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashed_password, $email);
        
        if ($stmt->execute()) {
            $message = "Password updated successfully! Redirecting to login...";
            unset($_SESSION['reset_email']);
            unset($_SESSION['otp_verified']);
            header("refresh:3;url=login.php");
        } else {
            $error = "Failed to update password.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Additional styles specific to password reset */
        .reset-container {
            background: var(--white);
            border-radius: 1rem;
            box-shadow: var(--box-shadow);
            padding: 3rem;
            width: 100%;
            max-width: 40rem;
            margin: 8rem auto;
        }

        .password-input {
            position: relative;
            margin-bottom: 2rem;
        }

        .password-input input {
            width: 100%;
            font-size: 1.5rem;
            padding: 1rem 1.2rem;
            border: .1rem solid rgba(0, 0, 0, 0.1);
            margin: .7rem 0;
            color: var(--black);
            text-transform: none;
            border-radius: .5rem;
        }

        .toggle-password {
            position: absolute;
            right: 1.2rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            font-size: 1.6rem;
        }

        .validation-message {
            font-size: 1.4rem;
            margin: .5rem 0;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .valid {
            color: #2ecc71;
        }

        .invalid {
            color: #e74c3c;
        }

        .message {
            padding: 1.5rem;
            border-radius: .5rem;
            margin-bottom: 2rem;
            text-align: center;
            font-size: 1.6rem;
        }

        .success {
            background: #d4edda;
            color: #155724;
        }

        .error {
            background: #fed7d7;
            color: #c53030;
        }

        .reset-heading {
            text-align: center;
            font-size: 2.5rem;
            color: var(--main);
            margin-bottom: 2rem;
        }

        .form-group label {
            display: block;
            font-size: 1.6rem;
            color: var(--black);
            margin-bottom: .5rem;
        }

        .reset-btn {
            width: 100%;
            text-align: center;
            margin-top: 1rem;
        }

        .reset-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .reset-btn:disabled:hover::before {
            width: 0;
        }

        section.reset-section {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: var(--bg);
        }
    </style>
</head>
<body>
    <section class="reset-section">
        <div class="reset-container">
            <h2 class="reset-heading">Reset Password</h2>
            
            <?php if ($message): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form id="resetForm" method="POST">
                <div class="form-group">
                    <label><i class="fas fa-key"></i> New Password</label>
                    <div class="password-input">
                        <input type="password" id="new_password" name="new_password" required>
                        <i class="toggle-password fas fa-eye" onclick="togglePassword('new_password')"></i>
                    </div>
                    <div id="lengthValidation" class="validation-message invalid">
                        <i class="fas fa-times"></i> 
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-key"></i> Confirm Password</label>
                    <div class="password-input">
                        <input type="password" id="confirm_password" name="confirm_password" required>
                        <i class="toggle-password fas fa-eye" onclick="togglePassword('confirm_password')"></i>
                    </div>
                    <div id="matchValidation" class="validation-message invalid">
                        <i class="fas fa-times"></i> Passwords match
                    </div>
                </div>
                
                <button type="submit" id="submitBtn" class="btn reset-btn" disabled>
                    <i class="fas fa-save"></i> Update Password
                </button>
            </form>
        </div>
    </section>

    <script>
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const lengthValidation = document.getElementById('lengthValidation');
        const matchValidation = document.getElementById('matchValidation');
        const submitBtn = document.getElementById('submitBtn');

        function validatePasswords() {
            const password = newPassword.value;
            const confirm = confirmPassword.value;
            let isValid = true;

            // Check length
            if (password.length >= 6) {
                lengthValidation.classList.add('valid');
                lengthValidation.classList.remove('invalid');
                lengthValidation.querySelector('i').className = 'fas fa-check';
            } else {
                lengthValidation.classList.remove('valid');
                lengthValidation.classList.add('invalid');
                lengthValidation.querySelector('i').className = 'fas fa-times';
                isValid = false;
            }

            // Check match
            if (password === confirm && password !== '') {
                matchValidation.classList.add('valid');
                matchValidation.classList.remove('invalid');
                matchValidation.querySelector('i').className = 'fas fa-check';
            } else {
                matchValidation.classList.remove('valid');
                matchValidation.classList.add('invalid');
                matchValidation.querySelector('i').className = 'fas fa-times';
                isValid = false;
            }

            submitBtn.disabled = !isValid;
        }

        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling;
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        newPassword.addEventListener('input', validatePasswords);
        confirmPassword.addEventListener('input', validatePasswords);
    </script>
</body>
</html>