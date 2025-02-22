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

// Check if an ID was provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid animal ID";
    header('Location: view_animal.php');
    exit();
}

$animal_id = $_GET['id'];

// Fetch the animal's current data
$stmt = $conn->prepare("SELECT * FROM animals WHERE id = ?");
$stmt->bind_param("i", $animal_id);
$stmt->execute();
$result = $stmt->get_result();
$animal = $result->fetch_assoc();

if (!$animal) {
    $_SESSION['error'] = "Animal not found";
    header('Location: view_animal.php');
    exit();
}

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
    $vaccination_status = isset($_POST['vaccination_status']) ? 1 : 0;
    $description = $_POST['description'];
    $special_notes = $_POST['special_notes'];
    $daily_rate = $_POST['daily_rate'];
    $monthly_rate = $_POST['monthly_rate'];
    $yearly_rate = $_POST['yearly_rate'];
    
    // Handle file upload if a new image is provided
    if (isset($_FILES["image"]) && $_FILES["image"]["error"] == 0) {
        $target_dir = "uploads/animals/";
        $file_extension = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
        $new_filename = uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            // Delete old image if it exists
            if (!empty($animal['image_url']) && file_exists($animal['image_url'])) {
                unlink($animal['image_url']);
            }
            $image_url = $target_file;
        }
    } else {
        $image_url = $animal['image_url']; // Keep existing image
    }

    $sql = "UPDATE animals SET 
            name=?, species=?, category_id=?, gender=?, age=?, birth_year=?,
            weight=?, height=?, color=?, diet_type=?, habitat=?, health_status=?,
            vaccination_status=?, description=?, special_notes=?, image_url=?,
            daily_rate=?, monthly_rate=?, yearly_rate=?
            WHERE id=?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssissddsssssisssdddi", 
        $name, $species, $category_id, $gender, $age, $birth_year,
        $weight, $height, $color, $diet_type, $habitat, $health_status,
        $vaccination_status, $description, $special_notes, $image_url,
        $daily_rate, $monthly_rate, $yearly_rate, $animal_id
    );

    if ($stmt->execute()) {
        $_SESSION['message'] = "Animal updated successfully!";
        header('Location: view_animal.php');
        exit();
    } else {
        $_SESSION['error'] = "Error updating animal: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Animal - SafariGate Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .edit-animal-container {
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

        .current-image {
            max-width: 200px;
            margin-top: 10px;
            border-radius: 4px;
        }

        .preview-image {
            max-width: 200px;
            margin-top: 10px;
            display: none;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="edit-animal-container">
        <div class="form-container">
            <div class="form-header">
                <h1><i class="fas fa-edit"></i> Edit Animal</h1>
            </div>

            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Category:</label>
                    <select name="category_id" required>
                        <?php while($category = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $category['id']; ?>" 
                                <?php echo ($category['id'] == $animal['category_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Animal Name:</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($animal['name']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Species:</label>
                    <input type="text" name="species" value="<?php echo htmlspecialchars($animal['species']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Gender:</label>
                    <select name="gender" required>
                        <option value="Male" <?php echo ($animal['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo ($animal['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Age (years):</label>
                    <input type="number" name="age" value="<?php echo htmlspecialchars($animal['age']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Birth Year:</label>
                    <input type="number" name="birth_year" value="<?php echo htmlspecialchars($animal['birth_year']); ?>" 
                           min="1900" max="<?php echo date('Y'); ?>" required>
                </div>

                <div class="form-group">
                    <label>Weight (kg):</label>
                    <input type="number" step="0.01" name="weight" value="<?php echo htmlspecialchars($animal['weight']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Height (cm):</label>
                    <input type="number" step="0.01" name="height" value="<?php echo htmlspecialchars($animal['height']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Color:</label>
                    <input type="text" name="color" value="<?php echo htmlspecialchars($animal['color']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Diet Type:</label>
                    <input type="text" name="diet_type" value="<?php echo htmlspecialchars($animal['diet_type']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Habitat:</label>
                    <input type="text" name="habitat" value="<?php echo htmlspecialchars($animal['habitat']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Health Status:</label>
                    <select name="health_status" required>
                        <option value="Healthy" <?php echo ($animal['health_status'] == 'Healthy') ? 'selected' : ''; ?>>Healthy</option>
                        <option value="Under Treatment" <?php echo ($animal['health_status'] == 'Under Treatment') ? 'selected' : ''; ?>>Under Treatment</option>
                        <option value="Critical" <?php echo ($animal['health_status'] == 'Critical') ? 'selected' : ''; ?>>Critical</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="vaccination_status" <?php echo $animal['vaccination_status'] ? 'checked' : ''; ?>>
                        Vaccination Complete
                    </label>
                </div>

                <div class="form-group">
                    <label>Description:</label>
                    <textarea name="description" required><?php echo htmlspecialchars($animal['description']); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Special Notes:</label>
                    <textarea name="special_notes"><?php echo htmlspecialchars($animal['special_notes']); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Current Image:</label>
                    <?php if (!empty($animal['image_url'])): ?>
                        <img src="<?php echo htmlspecialchars($animal['image_url']); ?>" class="current-image" alt="Current Image">
                    <?php else: ?>
                        <p>No image currently set</p>
                    <?php endif; ?>
                    <label style="margin-top: 10px;">Upload New Image (optional):</label>
                    <input type="file" name="image" accept="image/*" onchange="previewImage(this)">
                    <img id="preview" class="preview-image">
                </div>

                <div class="form-group">
                    <label>Adoption Rates:</label>
                    <div class="rate-inputs">
                        <input type="number" name="daily_rate" value="<?php echo htmlspecialchars($animal['daily_rate']); ?>" 
                               placeholder="Day Rate" required>
                        <input type="number" name="monthly_rate" value="<?php echo htmlspecialchars($animal['monthly_rate']); ?>" 
                               placeholder="1 Month Rate" required>
                        <input type="number" name="yearly_rate" value="<?php echo htmlspecialchars($animal['yearly_rate']); ?>" 
                               placeholder="1 Year Rate" required>
                    </div>
                </div>

                <button type="submit" class="submit-btn">Update Animal</button>
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