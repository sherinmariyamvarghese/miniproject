<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "safarigate";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if database exists
$db_check = $conn->query("SHOW DATABASES LIKE '$dbname'");
if ($db_check->num_rows == 0) {
    // Create database
    if ($conn->query("CREATE DATABASE $dbname")) {
        echo "Database created successfully\n";
    } else {
        echo "Error creating database: " . $conn->error;
    }
}

// Select the database
$conn->select_db($dbname);

// Close the connection
$conn->close();

// Include table creation script
require_once "table.php";
?>