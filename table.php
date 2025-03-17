<?php
require_once 'connect.php';

// Function to check if a column exists in a table
function columnExists($conn, $table, $column) {
    $query = "SHOW COLUMNS FROM $table LIKE '$column'";
    $result = mysqli_query($conn, $query);
    return mysqli_num_rows($result) > 0;
}

// Create users table
$sql_users = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $sql_users)) {
    echo "Table `users` created successfully.<br>";
} else {
    echo "Error creating users table: " . mysqli_error($conn) . "<br>";
}

// Create animal_categories table
$sql_categories = "CREATE TABLE IF NOT EXISTS animal_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $sql_categories)) {
    echo "Table `animal_categories` created successfully.<br>";
    
    // Add default categories if table is empty
    $check_categories = mysqli_query($conn, "SELECT COUNT(*) as count FROM animal_categories");
    $row = mysqli_fetch_assoc($check_categories);
    
    if ($row['count'] == 0) {
        $default_categories = [
            ['Mammals', 'Warm-blooded vertebrate animals including lions, tigers, elephants, etc.'],
            ['Birds', 'Feathered, winged animals including eagles, parrots, peacocks, etc.'],
            ['Reptiles', 'Cold-blooded animals including snakes, lizards, crocodiles, etc.'],
            ['Amphibians', 'Dual-living animals including frogs, toads, salamanders, etc.'],
            ['Aquatic Animals', 'Water-dwelling animals including fish, dolphins, sea turtles, etc.']
        ];
        
        $stmt = mysqli_prepare($conn, "INSERT INTO animal_categories (name, description) VALUES (?, ?)");
        
        foreach ($default_categories as $category) {
            mysqli_stmt_bind_param($stmt, "ss", $category[0], $category[1]);
            mysqli_stmt_execute($stmt);
        }
        
        echo "Default categories added.<br>";
    }
} else {
    echo "Error creating animal_categories table: " . mysqli_error($conn) . "<br>";
}

// Create animals table with improved structure
$sql_animals = "CREATE TABLE IF NOT EXISTS animals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    species VARCHAR(100) NOT NULL,
    category_id INT NOT NULL,
    birth_date DATE NOT NULL,
    gender ENUM('Male', 'Female') NOT NULL,
    description TEXT,
    special_notes TEXT,
    image_url VARCHAR(255),
    weight DECIMAL(6,2) COMMENT 'in kg',
    height DECIMAL(6,2) COMMENT 'in cm',
    color VARCHAR(50),
    diet_type ENUM('Carnivore', 'Herbivore', 'Omnivore') NOT NULL,
    habitat VARCHAR(100),
    health_status ENUM('Healthy', 'Under Treatment', 'Critical') DEFAULT 'Healthy',
    vaccination_status BOOLEAN DEFAULT 0,
    adoption_price_day DECIMAL(10,2) NOT NULL,
    adoption_price_month DECIMAL(10,2) NOT NULL,
    adoption_price_year DECIMAL(10,2) NOT NULL,
    one_day_rate DECIMAL(10,2) NOT NULL,
    one_month_rate DECIMAL(10,2) NOT NULL,
    one_year_rate DECIMAL(10,2) NOT NULL,
    available BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES animal_categories(id),
    INDEX idx_birth_date (birth_date),
    INDEX idx_category (category_id)
)";

if (mysqli_query($conn, $sql_animals)) {
    echo "Table `animals` created successfully.<br>";
    
    // Create VIEW for animal details including calculated age
    $sql_view = "CREATE OR REPLACE VIEW animal_details AS
    SELECT 
        a.*,
        ac.name as category_name,
        TIMESTAMPDIFF(YEAR, a.birth_year, CURDATE()) as age_years,
        TIMESTAMPDIFF(MONTH, a.birth_year, CURDATE()) % 12 as age_months
    FROM animals a
    JOIN animal_categories ac ON a.category_id = ac.id";
    
    if (mysqli_query($conn, $sql_view)) {
        echo "View `animal_details` created successfully.<br>";
    } else {
        echo "Error creating view: " . mysqli_error($conn) . "<br>";
    }
} else {
    echo "Error creating animals table: " . mysqli_error($conn) . "<br>";
}

// Create bookings table
// First drop the existing bookings table
$sql_drop = "DROP TABLE IF EXISTS bookings";
if (mysqli_query($conn, $sql_drop)) {
    echo "Old bookings table dropped successfully.<br>";
}

$sql_bookings = "CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visit_date DATE NOT NULL,
    adult_tickets INT DEFAULT 0 CHECK (adult_tickets >= 0),
    child_0_5_tickets INT DEFAULT 0 CHECK (child_0_5_tickets >= 0),
    child_5_12_tickets INT DEFAULT 0 CHECK (child_5_12_tickets >= 0), 
    senior_tickets INT DEFAULT 0 CHECK (senior_tickets >= 0),
    camera_video BOOLEAN DEFAULT 0,
    document_path VARCHAR(255),
    total_amount DECIMAL(10,2) NOT NULL,
    payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    city VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT check_total_tickets CHECK (
        (adult_tickets + child_0_5_tickets + child_5_12_tickets + senior_tickets) <= 100
    ),
    INDEX idx_visit_date (visit_date),
    INDEX idx_email (email),
    bill_pdf_path VARCHAR(255)
)";

if (mysqli_query($conn, $sql_bookings)) {
    echo "Table `bookings` created successfully.<br>";
    
    // Check and add razorpay_payment_id column
    if (!columnExists($conn, 'bookings', 'razorpay_payment_id')) {
        $alter_table_query = "ALTER TABLE bookings ADD COLUMN razorpay_payment_id VARCHAR(255)";
        if (mysqli_query($conn, $alter_table_query)) {
            echo "Added razorpay_payment_id column successfully.<br>";
        } else {
            echo "Error adding razorpay_payment_id column: " . mysqli_error($conn) . "<br>";
        }
    } else {
        echo "razorpay_payment_id column already exists.<br>";
    }

    // Check and add bill_pdf_path column
    if (!columnExists($conn, 'bookings', 'bill_pdf_path')) {
        $alter_table_query = "ALTER TABLE bookings ADD COLUMN bill_pdf_path VARCHAR(255)";
        if (mysqli_query($conn, $alter_table_query)) {
            echo "Added bill_pdf_path column successfully.<br>";
        } else {
            echo "Error adding bill_pdf_path column: " . mysqli_error($conn) . "<br>";
        }
    }
} else {
    echo "Error creating bookings table: " . mysqli_error($conn) . "<br>";
}

// First, drop the existing adoptions table if it exists
$sql_drop = "DROP TABLE IF EXISTS adoptions";
if (mysqli_query($conn, $sql_drop)) {
    echo "Old adoptions table dropped successfully.<br>";
}

// Create adoptions table with new structure
$sql_adoptions = "CREATE TABLE IF NOT EXISTS adoptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    animal_id INT NOT NULL,
    animal_name VARCHAR(100) NOT NULL,
    period_type ENUM('1_day', '1_month', '1_year') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    adoption_date DATE NOT NULL,
    status ENUM('pending', 'active', 'completed', 'cancelled') DEFAULT 'pending',
    payment_id VARCHAR(255),
    payment_date TIMESTAMP NULL,
    name VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),
    city VARCHAR(100),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (animal_id) REFERENCES animals(id),
    INDEX idx_user (user_id),
    INDEX idx_animal (animal_id),
    INDEX idx_adoption_date (adoption_date)
)";

if (mysqli_query($conn, $sql_adoptions)) {
    echo "Table `adoptions` created successfully with new structure.<br>";
} else {
    echo "Error creating adoptions table: " . mysqli_error($conn) . "<br>";
}

// Create ticket_rates table
$sql_ticket_rates = "CREATE TABLE IF NOT EXISTS ticket_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    adult_rate DECIMAL(10,2) NOT NULL,
    child_5_12_rate DECIMAL(10,2) NOT NULL,
    senior_rate DECIMAL(10,2) NOT NULL,
    camera_rate DECIMAL(10,2) NOT NULL,
    max_tickets_per_day INT NOT NULL DEFAULT 100,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $sql_ticket_rates)) {
    echo "Table `ticket_rates` created successfully.<br>";
    
    // Insert default rates if table is empty
    $check_rates = mysqli_query($conn, "SELECT COUNT(*) as count FROM ticket_rates");
    $row = mysqli_fetch_assoc($check_rates);
    
    if ($row['count'] == 0) {
        $sql_default_rates = "INSERT INTO ticket_rates 
            (adult_rate, child_5_12_rate, senior_rate, camera_rate, max_tickets_per_day) 
            VALUES (80.00, 40.00, 40.00, 100.00, 100)";
        mysqli_query($conn, $sql_default_rates);
        echo "Default ticket rates added.<br>";
    }
} else {
    echo "Error creating ticket_rates table: " . mysqli_error($conn) . "<br>";
}

// Check if booked_ticket column exists before adding it
if (!columnExists($conn, 'ticket_rates', 'booked_ticket')) {
    $alter_table_query = "ALTER TABLE ticket_rates ADD COLUMN booked_ticket INT NOT NULL DEFAULT 0";
    if (mysqli_query($conn, $alter_table_query)) {
        echo "Added booked_ticket column successfully.<br>";
    } else {
        echo "Error adding booked_ticket column: " . mysqli_error($conn) . "<br>";
    }
} else {
    echo "booked_ticket column already exists.<br>";
}

// Create daily_tickets table
$sql_daily_tickets = "CREATE TABLE IF NOT EXISTS daily_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL UNIQUE,
    max_tickets INT NOT NULL DEFAULT 100,
    booked_tickets INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_date (date)
)";

if (mysqli_query($conn, $sql_daily_tickets)) {
    echo "Table `daily_tickets` created successfully.<br>";
} else {
    echo "Error creating daily_tickets table: " . mysqli_error($conn) . "<br>";
}

echo "<br>All tables have been created successfully. You can now:";
echo "<br>1. <a href='register.php'>Register users</a>";
echo "<br>2. <a href='add_animal.php'>Add animals</a>";
echo "<br>3. <a href='animal_categories.php'>Manage categories</a>";
echo "<br>4. <a href='view_animal.php'>View animals</a>";
?>