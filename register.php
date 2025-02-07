<?php
session_start(); // Start session for storing messages

// Include database connection
include 'connect.php';

// Initialize error variables
$usernameErr = $emailErr = $passwordErr = $confirmPasswordErr = "";
$username = $email = $password = $confirmPassword = "";

// Validate inputs after form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $isValid = true;

    // Username validation
    if (empty($_POST["username"])) {
        $usernameErr = "Username is required";
        $isValid = false;
    } else {
        $username = test_input($_POST["username"]);
        if (!preg_match("/^[a-zA-Z-' ]*$/", $username)) {
            $usernameErr = "Only letters and white space are allowed in the username";
            $isValid = false;
        }
    }

    // Email validation
    if (empty($_POST["email"])) {
        $emailErr = "Email is required";
        $isValid = false;
    } else {
        $email = test_input($_POST["email"]);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emailErr = "Invalid email format";
            $isValid = false;
        } else {
            // Check if the email already exists
            $checkEmailStmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $checkEmailStmt->bind_param("s", $email);
            $checkEmailStmt->execute();
            $checkEmailResult = $checkEmailStmt->get_result();
            if ($checkEmailResult->num_rows > 0) {
                $emailErr = "Email is already registered.";
                $isValid = false;
            }
            $checkEmailStmt->close();
        }
    }

    // Password validation
    if (empty($_POST["password"])) {
        $passwordErr = "Password is required";
        $isValid = false;
    } else {
        $password = test_input($_POST["password"]);
        if (!preg_match("/[a-z]/", $password)) {
            $passwordErr = "Password must include at least one lowercase letter.";
            $isValid = false;
        } elseif (!preg_match("/[A-Z]/", $password)) {
            $passwordErr = "Password must include at least one uppercase letter.";
            $isValid = false;
        } elseif (!preg_match("/[\W_]/", $password)) {
            $passwordErr = "Password must include at least one special character.";
            $isValid = false;
        }
    }

    // Confirm Password validation
    if (empty($_POST["confirm_password"])) {
        $confirmPasswordErr = "Please confirm your password";
        $isValid = false;
    } else {
        $confirmPassword = test_input($_POST["confirm_password"]);
        if ($confirmPassword !== $password) {
            $confirmPasswordErr = "Passwords do not match";
            $isValid = false;
        }
    }

    // If all inputs are valid
    if ($isValid) {
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'user')");
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT); // Secure password hashing
        $stmt->bind_param("sss", $username, $email, $hashedPassword);

        if ($stmt->execute()) {
            $_SESSION["message"] = "Registration successful! Please log in.";
            header("Location: login.php"); // Redirect to login page
            exit();
        } else {
            $emailErr = "Error: " . $stmt->error;
        }

        // Close the statement
        $stmt->close();
    }
}

// Function to sanitize input
function test_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Page</title>
    <link rel="stylesheet" href="css/login.css">
    <style>
        .error { color: red; font-size: 0.9em; }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const usernameInput = document.getElementById("username");
            const emailInput = document.getElementById("email");
            const passwordInput = document.getElementById("password");
            const confirmPasswordInput = document.getElementById("confirm_password");
            
            const usernameError = document.getElementById("username-error");
            const emailError = document.getElementById("email-error");
            const passwordError = document.getElementById("password-error");
            const confirmPasswordError = document.getElementById("confirm-password-error");

            // Username live validation
            usernameInput.addEventListener("input", () => {
                const usernameValue = usernameInput.value.trim();
                if (!usernameValue) {
                    usernameError.textContent = "Username is required.";
                } else if (!/^[a-zA-Z-' ]*$/.test(usernameValue)) {
                    usernameError.textContent = "Only letters and white space are allowed.";
                } else {
                    usernameError.textContent = "";
                }
            });

            // Email live validation
            emailInput.addEventListener("input", () => {
                const emailValue = emailInput.value.trim();
                if (!emailValue) {
                    emailError.textContent = "Email is required.";
                } else if (!/\S+@\S+\.\S+/.test(emailValue)) {
                    emailError.textContent = "Invalid email format.";
                } else {
                    emailError.textContent = "";
                }
            });

            // Password live validation
            passwordInput.addEventListener("input", () => {
                const passwordValue = passwordInput.value.trim();
                if (!passwordValue) {
                    passwordError.textContent = "Password is required.";
                } else if (!/[a-z]/.test(passwordValue)) {
                    passwordError.textContent = "Password must include at least one lowercase letter.";
                } else if (!/[A-Z]/.test(passwordValue)) {
                    passwordError.textContent = "Password must include at least one uppercase letter.";
                } else if (!/[\W_]/.test(passwordValue)) {
                    passwordError.textContent = "Password must include at least one special character.";
                } else {
                    passwordError.textContent = "";
                }
            });

            // Confirm Password live validation
            confirmPasswordInput.addEventListener("input", () => {
                const confirmPasswordValue = confirmPasswordInput.value.trim();
                const passwordValue = passwordInput.value.trim();

                if (!confirmPasswordValue) {
                    confirmPasswordError.textContent = "Please confirm your password.";
                } else if (confirmPasswordValue !== passwordValue) {
                    confirmPasswordError.textContent = "Passwords do not match.";
                } else {
                    confirmPasswordError.textContent = "";
                }
            });
        });
    </script>
</head>
<body>
    <div class="login-container">
        <h1>Register</h1>
        <form action="register.php" method="POST">
            <!-- Username -->
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                <span id="username-error" class="error"><?php echo $usernameErr; ?></span>
            </div>

            <!-- Email -->
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                <span id="email-error" class="error"><?php echo $emailErr; ?></span>
            </div>

            <!-- Password -->
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
                <span id="password-error" class="error"><?php echo $passwordErr; ?></span>
            </div>

            <!-- Confirm Password -->
            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
                <span id="confirm-password-error" class="error"><?php echo $confirmPasswordErr; ?></span>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="login-btn">Register</button>
        </form>

        <!-- Link to Login Page -->
        <p class="register-link">
            Already have an account? <a href="login.php">Login</a>
        </p>
    </div>
</body>
</html>
