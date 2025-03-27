<?php
session_start();
require_once 'connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Handle category addition
if(isset($_POST['add_category'])) {
    $category_name = $_POST['category_name'];
    $description = $_POST['description'];
    
    $stmt = $conn->prepare("INSERT INTO animal_categories (name, description) VALUES (?, ?)");
    $stmt->bind_param("ss", $category_name, $description);
    
    if($stmt->execute()) {
        $_SESSION['message'] = "Category added successfully";
    } else {
        $_SESSION['error'] = "Error adding category";
    }
    header('Location: animal_categories.php');
    exit();
}

// Fetch all categories
$categories = $conn->query("SELECT * FROM animal_categories ORDER BY name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animal Categories - SafariGate Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .categories-container {
            max-width: 1200px;
            margin: 100px auto;
            padding: 20px;
        }

        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .category-card {
            background: var(--white);
            border-radius: 8px;
            padding: 20px;
            box-shadow: var(--box-shadow);
            transition: transform 0.3s ease;
        }

        .category-card:hover {
            transform: translateY(-5px);
        }

        .category-name {
            color: var(--main);
            font-size: 1.8rem;
            margin-bottom: 10px;
        }

        .add-category-form {
            background: var(--white);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--black);
            font-size: 1.6rem;
        }

        .form-group input, 
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1.4rem;
        }

        .submit-btn {
            background: var(--main);
            color: var(--white);
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1.6rem;
        }

        .submit-btn:hover {
            opacity: 0.9;
        }

        .category-icon {
            font-size: 2.4rem;
            color: var(--main);
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="categories-container">
        <h1><i class="fas fa-paw"></i> Animal Categories</h1>

        <div class="add-category-form">
            <h2>Add New Category</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Category Name:</label>
                    <input type="text" name="category_name" required>
                </div>
                <div class="form-group">
                    <label>Description:</label>
                    <textarea name="description" rows="3" required></textarea>
                </div>
                <button type="submit" name="add_category" class="submit-btn">Add Category</button>
            </form>
        </div>

        <div class="category-grid">
            <?php while($category = $categories->fetch_assoc()): ?>
                <div class="category-card">
                    <div class="category-icon">
                        <i class="fas fa-paw"></i>
                    </div>
                    <h3 class="category-name"><?php echo htmlspecialchars($category['name']); ?></h3>
                    <p><?php echo htmlspecialchars($category['description']); ?></p>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</body>
</html> 