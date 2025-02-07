<?php
session_start();
include 'connect.php';

// Initialize variables
$emailErr = $passwordErr = "";
$email = $password = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Email validation
    if (empty($_POST["email"])) {
        $emailErr = "Email is required";
    } else {
        $email = test_input($_POST["email"]);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emailErr = "Invalid email format";
        }
    }

    // Password validation
    if (empty($_POST["password"])) {
        $passwordErr = "Password is required";
    } else {
        $password = test_input($_POST["password"]);
    }

    // Proceed if no validation errors
    if (empty($emailErr) && empty($passwordErr)) {
        // Prepare SQL statement
        $stmt = $conn->prepare("SELECT id, username, role, password FROM users WHERE email = ? LIMIT 1");

        if ($stmt === false) {
            error_log("Database prepare error: " . $conn->error);
            die("Database error. Please try again.");
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            // Verify password
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header("Location:dashboard.php");
                } else {
                    header("Location: index.php");
                }
                exit();
            } else {
                $passwordErr = "Invalid email or password";
            }
        } else {
            $passwordErr = "Account does not exist. Please register.";
        }

        $stmt->close();
    }
}

// Sanitize input
function test_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Page</title>
    <link rel="stylesheet" href="css/login.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .error {
            color: red;
            font-size: 0.9em;
        }
        .success {
            color: green;
            font-size: 0.9em;
        }
        <style>
    input[type="password"] {
        text-transform: none;
        -webkit-text-transform: none;
        -moz-text-transform: none;
    }
</style>
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <h1>Login</h1>
            <form action="" method="POST" id="loginForm">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($email); ?>" required>
                    <span class="error" id="emailError"><?= $emailErr; ?></span>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                    <span class="error" id="passwordError"><?= $passwordErr; ?></span>
                </div>
                <button type="submit" class="login-btn">Login</button>
            </form>
            <p class="register-link">Don't have an account? <a href="register.php">Register</a></p>
            <p class="forgot-password-link"><a href="forgot-password.php">Forgot Password?</a></p>
        </div>
    </div>

    <script>
        // Live validation for email
        document.getElementById("email").addEventListener("input", function () {
    // Preserve original capitalization
    this.value = this.value.toLowerCase();
            const emailError = document.getElementById("emailError");

            if (emailInput === "") {
                emailError.textContent = "Email is required";
            } else if (!/^\S+@\S+\.\S+$/.test(emailInput)) {
                emailError.textContent = "Invalid email format";
            } else {
                emailError.textContent = "";
            }
        });

        // Live validation for password
        document.getElementById("password").addEventListener("input", function () {

            const passwordInput = this.value;
            const passwordError = document.getElementById("passwordError");

            if (passwordInput === "") {
                passwordError.textContent = "Password is required";
            } else if (passwordInput.length < 6) {
                passwordError.textContent = "Password must be at least 6 characters";
            } else {
                passwordError.textContent = "";
            }
        });

        // Form submission validation
        document.getElementById("loginForm").addEventListener("submit", function (e) {
            const emailError = document.getElementById("emailError").textContent;
            const passwordError = document.getElementById("passwordError").textContent;

            if (emailError || passwordError) {
                e.preventDefault(); // Prevent form submission
                alert("Please fix errors before submitting");
            }
        });
    </script>
</body>
</html>