<?php
include "connect.php";

// Function to check if column exists
function columnExists($conn, $table, $column) {
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return mysqli_num_rows($result) > 0;
}

// Check if users table exists
$result = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
if(mysqli_num_rows($result) == 0) {
    // Create users table if it doesn't exist
    $sql_users = "CREATE TABLE users (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NULL,
        address TEXT NULL,
        role ENUM('admin', 'user') DEFAULT 'user',
        otp VARCHAR(6) DEFAULT NULL,
        otp_expiry DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (mysqli_query($conn, $sql_users)) {
        echo "Table `users` created successfully.<br>";
        
        // Add default admin only if table was just created
        $hashpass = password_hash('#admin54321', PASSWORD_DEFAULT);
        $sql_admin = "INSERT INTO users (username, email, password, role)
                      VALUES ('Admin', 'admin@gmail.com', '$hashpass', 'admin')";
        
        if (mysqli_query($conn, $sql_admin)) {
            echo "Admin user added successfully.<br>";
            echo "Admin Email: admin@gmail.com<br>";
            echo "Admin Password: #admin54321<br>";
        } else {
            echo "Error inserting admin: " . mysqli_error($conn) . "<br>";
        }
    } else {
        echo "Error creating users table: " . mysqli_error($conn) . "<br>";
    }
} else {
    // Add columns to existing users table if they don't exist
    
    if (!columnExists($conn, 'users', 'phone')) {
        mysqli_query($conn, "ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL");
        echo "Added phone column to users table.<br>";
    }
    if (!columnExists($conn, 'users', 'address')) {
        mysqli_query($conn, "ALTER TABLE users ADD COLUMN address TEXT NULL");
        echo "Added address column to users table.<br>";
    }
}

// Check if adoptions table exists
$result = mysqli_query($conn, "SHOW TABLES LIKE 'adoptions'");
if(mysqli_num_rows($result) == 0) {
    $sql_adoptions = "CREATE TABLE adoptions (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11),
        animal_id INT(11),
        adoption_type VARCHAR(20),
        duration INT,
        amount DECIMAL(10,2),
        adoption_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )";
    
    if (mysqli_query($conn, $sql_adoptions)) {
        echo "Table `adoptions` created successfully.<br>";
    } else {
        echo "Error creating adoptions table: " . mysqli_error($conn) . "<br>";
    }
}

// Check if donations table exists
$result = mysqli_query($conn, "SHOW TABLES LIKE 'donations'");
if(mysqli_num_rows($result) == 0) {
    $sql_donations = "CREATE TABLE donations (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11),
        amount DECIMAL(10,2),
        message TEXT,
        donation_date DATE,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )";
    
    if (mysqli_query($conn, $sql_donations)) {
        echo "Table `donations` created successfully.<br>";
    } else {
        echo "Error creating donations table: " . mysqli_error($conn) . "<br>";
    }
}

// Check if bookings table exists
$result = mysqli_query($conn, "SHOW TABLES LIKE 'bookings'");
if(mysqli_num_rows($result) == 0) {
    $sql_bookings = "CREATE TABLE bookings (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11),
        visit_date DATE,
        adult_tickets INT,
        child_0_5_tickets INT,
        child_5_12_tickets INT,
        senior_tickets INT,
        camera_video BOOLEAN,
        total_amount DECIMAL(10,2),
        booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )";
    
    if (mysqli_query($conn, $sql_bookings)) {
        echo "Table `bookings` created successfully.<br>";
    } else {
        echo "Error creating bookings table: " . mysqli_error($conn) . "<br>";
    }
}

$conn->close();
?>