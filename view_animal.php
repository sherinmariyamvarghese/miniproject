<?php
session_start();
require_once 'connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get all categories
$categories = $conn->query("SELECT * FROM animal_categories ORDER BY name");
$categoriesData = [];
while ($category = $categories->fetch_assoc()) {
    $categoriesData[$category['id']] = $category;
}

// Handle animal deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM animals WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Animal deleted successfully!";
    } else {
        $_SESSION['error'] = "Error deleting animal: " . $conn->error;
    }
    
    header('Location: view_animal.php');
    exit();
}

// Handle animal archive/unarchive
if (isset($_GET['toggle_availability']) && is_numeric($_GET['toggle_availability'])) {
    $id = $_GET['toggle_availability'];
    
    // Get current availability status
    $checkStmt = $conn->prepare("SELECT available FROM animals WHERE id = ?");
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $animal = $result->fetch_assoc();
    
    // Toggle the availability
    $newStatus = $animal['available'] ? 0 : 1;
    $updateStmt = $conn->prepare("UPDATE animals SET available = ? WHERE id = ?");
    $updateStmt->bind_param("ii", $newStatus, $id);
    
    if ($updateStmt->execute()) {
        $statusText = $newStatus ? "unarchived" : "archived";
        $_SESSION['message'] = "Animal $statusText successfully!";
    } else {
        $_SESSION['error'] = "Error updating animal status: " . $conn->error;
    }
    
    header('Location: view_animal.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Animals - SafariGate Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@100;300;400;500;700&display=swap');

        :root{
            --main: #ff6e01;
            --bg: #f1e1d2;
            --black: #000;
            --white: #fff;
            --box-shadow: 0 .5rem 1rem rgba(0, 0, 0, 0.1);
            --border-color: #e1e1e1;
        }

        *{
            font-family: 'Roboto', sans-serif;
            margin: 0; padding: 0;
            box-sizing: border-box;
            outline: none; border: none;
            text-decoration: none;
        }

        body {
            background-color: #f5f5f5;
            font-size: 62.5%;
        }

        .header {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 1000;
            background: var(--white);
            box-shadow: var(--box-shadow);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 2rem 9%;
        }

        .main-content {
            padding-top: 100px;
            max-width: 1400px;
            margin: 0 auto;
            padding-left: 2rem;
            padding-right: 2rem;
        }

        .view-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 3rem;
            color: var(--black);
            margin-bottom: 2rem;
            padding: 1rem;
            border-bottom: 2px solid var(--main);
        }

        .view-header i {
            color: var(--main);
            margin-right: 1rem;
        }

        .add-button {
            background: var(--main);
            color: var(--white);
            padding: 1rem 2rem;
            border-radius: 8px;
            font-size: 1.6rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
        }

        .add-button:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        /* Category Tabs */
        .category-tabs {
            display: flex;
            overflow-x: auto;
            margin-bottom: 3rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1px;
        }
        
        .category-tab {
            padding: 1.2rem 2.5rem;
            font-size: 1.6rem;
            background: var(--white);
            border: 1px solid var(--border-color);
            border-bottom: none;
            margin-right: 0.5rem;
            cursor: pointer;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            color: var(--black);
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .category-tab.active {
            background: var(--main);
            color: var(--white);
            border-color: var(--main);
            font-weight: 500;
        }
        
        .category-tab:hover:not(.active) {
            background: var(--bg);
        }

        /* Table Styling */
        .animals-table-container {
            overflow-x: auto;
            background: var(--white);
            border-radius: 8px;
            box-shadow: var(--box-shadow);
            margin-bottom: 4rem;
        }

        .animals-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 1.5rem;
        }

        .animals-table th {
            background-color: var(--main);
            color: var(--white);
            padding: 1.5rem 1rem;
            text-align: left;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .animals-table th:first-child {
            border-top-left-radius: 8px;
        }

        .animals-table th:last-child {
            border-top-right-radius: 8px;
        }

        .animals-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .animals-table tr:hover {
            background-color: var(--bg);
        }

        .animals-table td {
            padding: 1.2rem 1rem;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        .animals-table .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 1.2rem;
            font-weight: 500;
        }

        .status-available {
            background-color: #e6f7e6;
            color: #28a745;
        }

        .status-archived {
            background-color: #fff3cd;
            color: #856404;
        }

        /* Image Cell */
        .animal-img-cell {
            width: 80px;
        }

        .animal-img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            transition: transform 0.3s ease;
        }

        .animal-img:hover {
            transform: scale(1.1);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.8rem;
        }

        .btn {
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 0.25rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1.3rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background: #c82333;
        }

        .btn-archive {
            background: #ffc107;
            color: #000;
        }

        .btn-archive:hover {
            background: #ffb300;
        }

        .btn-unarchive {
            background: #28a745;
            color: white;
        }

        .btn-unarchive:hover {
            background: #218838;
        }

        .btn-edit {
            background: #17a2b8;
            color: white;
        }

        .btn-edit:hover {
            background: #138496;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .animals-table {
                font-size: 1.4rem;
            }
            
            .btn {
                padding: 0.7rem 1.2rem;
                font-size: 1.2rem;
            }
        }

        @media (max-width: 768px) {
            .category-tab {
                padding: 1rem 1.5rem;
                font-size: 1.4rem;
            }
            
            .animals-table th,
            .animals-table td {
                padding: 1rem 0.8rem;
            }
            
            .animals-table {
                font-size: 1.3rem;
            }
            
            .animal-img-cell {
                width: 60px;
            }
            
            .animal-img {
                width: 60px;
                height: 60px;
            }
        }

        .category-header {
            color: var(--main);
            font-size: 2.4rem;
            padding: 2rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--main);
        }

        .category-stats {
            font-size: 1.6rem;
            color: var(--black);
            background: var(--bg);
            padding: 0.8rem 1.5rem;
            border-radius: 5rem;
        }

        .empty-message {
            padding: 4rem;
            text-align: center;
            font-size: 1.8rem;
            color: #666;
        }

        .alert {
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            font-size: 1.6rem;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>

<!-- header section placeholder -->
<header class="header">
    <!-- Header content here -->
</header>

<!-- main content starts -->
<div class="main-content">
    <div class="view-header">
        <div><i class="fas fa-paw"></i> View Animals</div>
        <a href="add_animal.php" class="add-button">
            <i class="fas fa-plus"></i> Add New Animal
        </a>
    </div>

    <!-- Show messages/errors if any -->
    <?php if(isset($_SESSION['message'])): ?>
        <div class="alert alert-success">
            <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
        </div>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Category Tabs Navigation -->
    <div class="category-tabs">
        <a href="?category=all" class="category-tab <?php echo (!isset($_GET['category']) || $_GET['category'] == 'all') ? 'active' : ''; ?>">All Animals</a>
        <?php foreach($categoriesData as $category): ?>
            <a href="?category=<?php echo $category['id']; ?>" class="category-tab <?php echo (isset($_GET['category']) && $_GET['category'] == $category['id']) ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($category['name']); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php
    // Determine which category to display
    $category_filter = "";
    if (isset($_GET['category']) && $_GET['category'] != 'all' && is_numeric($_GET['category'])) {
        $category_id = $_GET['category'];
        $category_filter = "WHERE category_id = $category_id";
    }

    // Loop through each category if viewing all, otherwise just show selected category
    if (!isset($_GET['category']) || $_GET['category'] == 'all') {
        $category_query = "SELECT * FROM animal_categories ORDER BY name";
        $category_result = $conn->query($category_query);
        while ($category = $category_result->fetch_assoc()) {
            displayCategorySection($conn, $category);
        }
    } else if (isset($_GET['category']) && $_GET['category'] != 'all' && is_numeric($_GET['category'])) {
        $category_id = $_GET['category'];
        $category_query = "SELECT * FROM animal_categories WHERE id = $category_id";
        $category_result = $conn->query($category_query);
        $category = $category_result->fetch_assoc();
        if ($category) {
            displayCategorySection($conn, $category);
        }
    }

    function displayCategorySection($conn, $category) {
        $category_id = $category['id'];
        $animal_query = "SELECT * FROM animals WHERE category_id = $category_id ORDER BY name";
        $animal_result = $conn->query($animal_query);
        $animal_count = $animal_result->num_rows;
        
        // Get category icon based on name
        $icon_class = 'fas fa-paw'; // Default icon
        switch(strtolower($category['name'])) {
            case 'birds':
                $icon_class = 'fas fa-feather-alt';
                break;
            case 'fish':
            case 'aquatic animals':
                $icon_class = 'fas fa-fish';
                break;
            case 'reptiles':
                $icon_class = 'fas fa-dragon';
                break;
            case 'amphibians':
                $icon_class = 'fas fa-frog';
                break;
        }
        ?>
        <div class="category-section">
            <h2 class="category-header">
                <span>
                    <i class="<?php echo $icon_class; ?>"></i> 
                    <?php echo htmlspecialchars($category['name']); ?>
                </span>
                <span class="category-stats">
                    Total Animals: <?php echo $animal_count; ?>
                </span>
            </h2>
            
            <?php if ($animal_count > 0): ?>
                <div class="animals-table-container">
                    <table class="animals-table">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th width="10%">Image</th>
                                <th width="12%">Name</th>
                                <th width="12%">Species</th>
                                <th width="6%">Gender</th>
                                <th width="6%">Age</th>
                                <th width="10%">Habitat</th>
                                <th width="10%">Health</th>
                                <th width="8%">Status</th>
                                <th width="20%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            while ($animal = $animal_result->fetch_assoc()): 
                                $image_url = !empty($animal['image_url']) ? $animal['image_url'] : "/api/placeholder/80/80";
                            ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td class="animal-img-cell">
                                        <img src="<?php echo htmlspecialchars($image_url); ?>" alt="<?php echo htmlspecialchars($animal['name']); ?>" class="animal-img">
                                    </td>
                                    <td><?php echo htmlspecialchars($animal['name']); ?></td>
                                    <td><?php echo htmlspecialchars($animal['species']); ?></td>
                                    <td><?php echo htmlspecialchars($animal['gender']); ?></td>
                                    <td><?php echo htmlspecialchars($animal['age']); ?> yrs</td>
                                    <td><?php echo htmlspecialchars($animal['habitat']); ?></td>
                                    <td><?php echo htmlspecialchars($animal['health_status']); ?></td>
                                    <td>
                                        <?php if ($animal['available']): ?>
                                            <span class="status-badge status-available">Available</span>
                                        <?php else: ?>
                                            <span class="status-badge status-archived">Archived</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="edit_animal.php?id=<?php echo $animal['id']; ?>" class="btn btn-edit">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <?php if ($animal['available']): ?>
                                                <a href="?toggle_availability=<?php echo $animal['id']; ?>" class="btn btn-archive">
                                                    <i class="fas fa-archive"></i> Archive
                                                </a>
                                            <?php else: ?>
                                                <a href="?toggle_availability=<?php echo $animal['id']; ?>" class="btn btn-unarchive">
                                                    <i class="fas fa-box-open"></i> Unarchive
                                                </a>
                                            <?php endif; ?>
                                            <a href="?delete=<?php echo $animal['id']; ?>" class="btn btn-delete" onclick="return confirm('Are you sure you want to delete this animal?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-message">
                    <i class="fas fa-info-circle"></i> No animals added to this category yet.
                </div>
            <?php endif; ?>
        </div>
    <?php
    }
    ?>
</div>
<!-- main content ends -->

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(() => alert.style.display = 'none', 500);
            });
        }, 5000);
    });
</script>

</body>
</html>