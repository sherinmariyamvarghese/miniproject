<?php
session_start();
require_once 'connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// First, get all categories
$categories = $conn->query("SELECT * FROM animal_categories ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $species = $_POST['species'];
    $category_id = $_POST['category_id'];
    $gender = $_POST['gender'];
    $age = $_POST['age'];
    $birth_year = $_POST['birth_year'];
    $weight = $_POST['weight'];
    $height = $_POST['height'];
    $color = $_POST['color'];
    $diet_type = $_POST['diet_type'];
    $habitat = $_POST['habitat'];
    $health_status = $_POST['health_status'];
    $description = $_POST['description'];
    $special_notes = $_POST['special_notes'];
    $daily_rate = $_POST['daily_rate'];
    $monthly_rate = $_POST['monthly_rate'];
    $yearly_rate = $_POST['yearly_rate'];
    
    // Handle file upload
    $target_dir = "uploads/animals/";
    $image_url = "";
    
    if (isset($_FILES["image"]) && $_FILES["image"]["error"] == 0) {
        $file_extension = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
        $new_filename = uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image_url = $target_file;
        }
    }

    $sql = "INSERT INTO animals (name, species, category_id, gender, age, birth_year, 
            weight, height, color, diet_type, habitat, health_status,
            description, special_notes, image_url, daily_rate, monthly_rate, yearly_rate) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssissddsssssisssddd", 
        $name, $species, $category_id, $gender, $age, $birth_year, 
        $weight, $height, $color, $diet_type, $habitat, $health_status, 
        $description, $special_notes, $image_url, 
        $daily_rate, $monthly_rate, $yearly_rate
    );

    if ($stmt->execute()) {
        $_SESSION['message'] = "Animal added successfully!";
    } else {
        $_SESSION['error'] = "Error adding animal: " . $conn->error;
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
    <title>Add Animal - SafariGate Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .add-animal-container {
            max-width: 800px;
            margin: 100px auto;
            padding: 20px;
        }

        .form-container {
            background: var(--white);
            padding: 30px;
            border-radius: 8px;
            box-shadow: var(--box-shadow);
        }

        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .form-header h1 {
            color: var(--main);
            font-size: 2.4rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--black);
            font-size: 1.6rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1.4rem;
        }

        .form-group textarea {
            height: 100px;
            resize: vertical;
        }

        .rate-inputs {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .submit-btn {
            background: var(--main);
            color: var(--white);
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1.6rem;
            width: 100%;
            transition: opacity 0.3s ease;
        }

        .submit-btn:hover {
            opacity: 0.9;
        }

        .preview-image {
            max-width: 200px;
            margin-top: 10px;
            display: none;
            border-radius: 4px;
        }

        .dashboard-link {
            position: fixed;
            top: 20px;
            left: 20px;
            background: var(--main);
            color: var(--white);
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 1.6rem;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 1000;
            box-shadow: var(--box-shadow);
            transition: all 0.3s ease;
        }

        .dashboard-link:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <a href="admin_dashboard.php" class="dashboard-link">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
    <div class="add-animal-container">
        <div class="form-container">
            <div class="form-header">
                <h1><i class="fas fa-paw"></i> Add New Animal</h1>
            </div>

            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Category:</label>
                    <select name="category_id" required>
                        <option value="">Select Category</option>
                        <?php while($category = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $category['id']; ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Animal Name:</label>
                    <input type="text" name="name" required>
                </div>

                <div class="form-group">
                    <label>Species:</label>
                    <input type="text" name="species" required>
                </div>

                <div class="form-group">
                    <label>Gender:</label>
                    <select name="gender" required>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Age (years):</label>
                    <input type="number" name="age" required>
                </div>

                <div class="form-group">
                    <label>Birth Year:</label>
                    <input type="number" name="birth_year" min="1900" max="<?php echo date('Y'); ?>" required>
                </div>

                <div class="form-group">
                    <label>Weight (kg):</label>
                    <input type="number" step="0.01" name="weight" required>
                </div>

                <div class="form-group">
                    <label>Height (cm):</label>
                    <input type="number" step="0.01" name="height" required>
                </div>

                <div class="form-group">
                    <label>Color:</label>
                    <input type="text" name="color" required>
                </div>

                <div class="form-group">
                    <label>Diet Type:</label>
                    <input type="text" name="diet_type" required>
                </div>

                <div class="form-group">
                    <label>Habitat:</label>
                    <input type="text" name="habitat" required>
                </div>

                <div class="form-group">
                    <label>Health Status:</label>
                    <select name="health_status" required>
                        <option value="Healthy">Healthy</option>
                        <option value="Under Treatment">Under Treatment</option>
                        <option value="Critical">Critical</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Description:</label>
                    <textarea name="description" required></textarea>
                </div>

                <div class="form-group">
                    <label>Special Notes:</label>
                    <textarea name="special_notes"></textarea>
                </div>

                <div class="form-group">
                    <label>Image:</label>
                    <input type="file" name="image" accept="image/*" required onchange="previewImage(this)">
                    <img id="preview" class="preview-image">
                </div>

                <div class="form-group">
                    <label>Adoption Rates:</label>
                    <div class="rate-inputs">
                        <input type="number" name="daily_rate" placeholder="Day Rate" required>
                        <input type="number" name="monthly_rate" placeholder="1 Month Rate" required>
                        <input type="number" name="yearly_rate" placeholder="1 Year Rate" required>
                    </div>
                </div>

                <button type="submit" class="submit-btn">Add Animal</button>
            </form>
        </div>
    </div>

    <script>
    function previewImage(input) {
        const preview = document.getElementById('preview');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    </script>
</body>
</html>
