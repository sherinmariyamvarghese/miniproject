<?php
session_start();
require_once 'connect.php';

// Fetch categories for the tab navigation
$categories = $conn->query("SELECT * FROM animal_categories ORDER BY name");
$categoriesData = [];
while ($category = $categories->fetch_assoc()) {
    $categoriesData[$category['id']] = $category;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process adoption form submission
    $animal_id = $_POST['animal_id'];
    $period_type = $_POST['period_type'];
    $quantity = $_POST['quantity'];
    $user_id = $_SESSION['user_id'];
    
    // Calculate total amount based on period type and quantity
    $rate_field = $period_type . '_rate';
    $stmt = $conn->prepare("SELECT $rate_field FROM animals WHERE id = ?");
    $stmt->bind_param("i", $animal_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rate = $result->fetch_assoc()[$rate_field];
    $total_amount = $rate * $quantity;
    
    // Insert adoption record
    $insert_stmt = $conn->prepare("
        INSERT INTO adoptions 
        (user_id, animal_id, period_type, quantity, total_amount, status) 
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    $insert_stmt->bind_param("iisid", $user_id, $animal_id, $period_type, $quantity, $total_amount);
    
    if ($insert_stmt->execute()) {
        $_SESSION['message'] = "Adoption request submitted successfully!";
    } else {
        $_SESSION['error'] = "Error submitting adoption request";
    }
    
    header('Location: adoption-confirmation.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animal Adoption Program - SafariGate</title>
    
    <head>
    <!-- Other head elements -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/adoption.css">

</head>
<body>
<?php include 'header.php'; ?>

<div class="main-content">
    <div class="header-section">
        <div class="view-header">
            <div><i class="fas fa-heart"></i> Animal Adoption Program</div>
        </div>
        
        <div class="summary-section">
            <h2 class="summary-title"><i class="fas fa-clipboard-list"></i> Adoption Summary</h2>
            <table class="summary-table">
                <thead>
                    <tr>
                        <th>Animal</th>
                        <th>Duration</th>
                        <th>Amount</th>
                        <th>Action</th>
                    </tr>
                </thead>
                
                <tbody id="summaryTableBody">
                    <!-- Adoption summary rows will be inserted here by JavaScript -->
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="2" class="total-label">Total Amount:</td>
                        <td id="totalAmount" class="total-amount">₹0</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            
            <button type="submit" class="adopt-button" id="completeAdoptionBtn" disabled>
                <i class="fas fa-check-circle"></i> Complete Adoption
            </button>
        </div>
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
        <a href="?category=all" class="category-tab <?php echo (!isset($_GET['category']) || $_GET['category'] == 'all') ? 'active' : ''; ?>">
            <i class="fas fa-paw"></i> All Animals
        </a>
        <?php foreach($categoriesData as $category): ?>
            <a href="?category=<?php echo $category['id']; ?>" class="category-tab <?php echo (isset($_GET['category']) && $_GET['category'] == $category['id']) ? 'active' : ''; ?>">
                <?php
                // Assign appropriate icon based on category name
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
                    case 'mammals':
                        $icon_class = 'fas fa-horse';
                        break;
                }
                ?>
                <i class="<?php echo $icon_class; ?>"></i> <?php echo htmlspecialchars($category['name']); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <form id="adoptionForm" method="post">
        <?php
        // Determine which category to display
        $category_filter = "";
        if (isset($_GET['category']) && $_GET['category'] != 'all' && is_numeric($_GET['category'])) {
            $category_id = $_GET['category'];
            $category_filter = "AND category_id = $category_id";
        }

        // Loop through each category if viewing all, otherwise just show selected category
        if (!isset($_GET['category']) || $_GET['category'] == 'all') {
            foreach($categoriesData as $category) {
                displayCategoryAnimals($conn, $category);
            }
        } else if (isset($_GET['category']) && $_GET['category'] != 'all' && is_numeric($_GET['category'])) {
            $category_id = $_GET['category'];
            if (isset($categoriesData[$category_id])) {
                displayCategoryAnimals($conn, $categoriesData[$category_id]);
            }
        }

        function displayCategoryAnimals($conn, $category) {
            $category_id = $category['id'];
            
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
                case 'mammals':
                    $icon_class = 'fas fa-horse';
                    break;
            }
            
     $query = "
    SELECT id, name, image_url as image, description, 
           daily_rate, monthly_rate, yearly_rate,
           age, birth_year as birth_date, health_status,
           species, gender, weight, height, color,
           diet_type, habitat, vaccination_status,
           special_notes
    FROM animals 
    WHERE available = 1 AND category_id = $category_id
    ORDER BY name
";
            $result = $conn->query($query);
            $animal_count = $result->num_rows;
            
            if ($animal_count > 0) {
                ?>
                <div class="category-section">
                    <h2 class="category-header">
                        <span>
                            <i class="<?php echo $icon_class; ?>"></i> 
                            <?php echo htmlspecialchars($category['name']); ?>
                        </span>
                        <span class="category-stats">
                            Available for Adoption: <?php echo $animal_count; ?>
                        </span>
                    </h2>
                    
                    <div class="animals-grid">
                        <?php while ($animal = $result->fetch_assoc()): 
                            $image_url = !empty($animal['image']) ? $animal['image'] : "/api/placeholder/300/200";
                        ?>
                            <div class="animal-card">
                                <img src="<?php echo htmlspecialchars($image_url); ?>" alt="<?php echo htmlspecialchars($animal['name']); ?>" class="animal-image">
                                <div class="animal-info">
                                    <h3 class="animal-name"><?php echo htmlspecialchars($animal['name']); ?></h3>
                                    
                                    <!-- Animal Stats in One Line -->
                                    <div class="animal-stats" style="font-size: 1.2em;">
                                        <span class="stat-item">
                                            <i class="fas fa-hourglass-half" style="color: #ff6e01;"></i>
                                            <strong>Age:</strong> <?php echo isset($animal['age']) ? htmlspecialchars($animal['age']) . ' years' : 'N/A'; ?>
                                        </span>
                                        <span class="stat-item">
                                            <i class="fas fa-birthday-cake" style="color: #ff6e01;"></i>
                                            <strong>Birth Date:</strong> <?php echo isset($animal['birth_date']) ? htmlspecialchars($animal['birth_date']) : 'N/A'; ?>
                                        </span>
                                        <span class="stat-item">
                                            <i class="fas fa-heartbeat" style="color: #ff6e01;"></i>
                                            <strong>Health Status:</strong> <?php echo isset($animal['health_status']) ? htmlspecialchars($animal['health_status']) : 'N/A'; ?>
                                        </span>
                                    </div>

                                    <p class="animal-description"><?php echo htmlspecialchars($animal['description']); ?></p>
                                    
                                    <div class="adoption-options">
                                        <div class="option-group">
                                            <label><i class="fas fa-clock" style="color: #ff6e01;"></i> Duration:</label>
                                            <select name="period_type" 
                                                    class="period-select"
                                                    data-animal-id="<?php echo $animal['id']; ?>"
                                                    data-daily="<?php echo $animal['daily_rate']; ?>"
                                                    data-monthly="<?php echo $animal['monthly_rate']; ?>"
                                                    data-yearly="<?php echo $animal['yearly_rate']; ?>">
                                                <option value="daily">Daily (₹<?php echo number_format($animal['daily_rate']); ?>)</option>
                                                <option value="monthly">Monthly (₹<?php echo number_format($animal['monthly_rate']); ?>)</option>
                                                <option value="yearly">Yearly (₹<?php echo number_format($animal['yearly_rate']); ?>)</option>
                                            </select>
                                        </div>
                                        <div class="button-group">
                                            <button type="button" 
                                                    class="adopt-button"
                                                    onclick="addToAdoption(
                                                        <?php echo $animal['id']; ?>, 
                                                        '<?php echo htmlspecialchars($animal['name']); ?>'
                                                    )">
                                                <i class="fas fa-heart" style="color: #fff;"></i> Add to Adoption
                                            </button>
                                            <button type="button" 
                                                    class="details-button"
                                                    onclick="showAnimalDetailsModal(<?php echo $animal['id']; ?>)">
                                                <i class="fas fa-info-circle" style="color: #fff;"></i> More Details
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php
            } else {
                ?>
                <div class="category-section">
                    <h2 class="category-header">
                        <span>
                            <i class="<?php echo $icon_class; ?>"></i> 
                            <?php echo htmlspecialchars($category['name']); ?>
                        </span>
                    </h2>
                    <div class="empty-message">
                        <i class="fas fa-info-circle"></i> No animals available for adoption in this category.
                    </div>
                </div>
                <?php
            }
        }
        ?>

        <!-- Hidden fields for form submission -->
        <input type="hidden" id="animal_id" name="animal_id" value="">
        <input type="hidden" id="period_type" name="period_type" value="">
        <input type="hidden" id="quantity" name="quantity" value="1">
    </form>
</div>

<!-- Add this modal structure before the script tag -->
<div id="animalDetailsModal" class="modal">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <div id="animalDetailsContent" class="animal-details">
            <div class="animal-detail-card">
                <div class="detail-header">
                    <img src="${animal.image_url || '/api/placeholder/300/200'}" 
                         alt="${animal.name}" 
                         class="detail-image">
                    <h2><i class="fas fa-paw" style="color: #ff6e01;"></i> ${animal.name}</h2>
                </div>
                <div class="detail-grid">
                    <div class="detail-item">
                        <i class="fas fa-dna" style="color: #ff6e01;"></i>
                        <strong>Species:</strong> ${animal.species || 'N/A'}
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-venus-mars" style="color: #ff6e01;"></i>
                        <strong>Gender:</strong> ${animal.gender || 'N/A'}
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-hourglass-half" style="color: #ff6e01;"></i>
                        <strong>Age:</strong> ${animal.age ? animal.age + ' years' : 'N/A'}
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-birthday-cake" style="color: #ff6e01;"></i>
                        <strong>Birth Year:</strong> ${animal.birth_year || 'N/A'}
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-weight" style="color: #ff6e01;"></i>
                        <strong>Weight:</strong> ${animal.weight} kg
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-ruler-vertical" style="color: #ff6e01;"></i>
                        <strong>Height:</strong> ${animal.height} cm
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-palette" style="color: #ff6e01;"></i>
                        <strong>Color:</strong> ${animal.color || 'N/A'}
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-utensils" style="color: #ff6e01;"></i>
                        <strong>Diet Type:</strong> ${animal.diet_type || 'N/A'}
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-tree" style="color: #ff6e01;"></i>
                        <strong>Habitat:</strong> ${animal.habitat || 'N/A'}
                    </div>
                    <div class="detail-item health-status" style="color: ${healthColor};">
                        <i class="fas ${healthIcon}" style="color: ${healthColor};"></i>
                        <strong>Health Status:</strong> ${animal.health_status || 'N/A'}
                    </div>
                </div>
                <div class="detail-section">
                    <h3><i class="fas fa-info-circle" style="color: #ff6e01;"></i> Description</h3>
                    <p>${animal.description}</p>
                </div>
                <div class="detail-section">
                    <h3><i class="fas fa-sticky-note" style="color: #ff6e01;"></i> Special Notes</h3>
                    <p>${animal.special_notes || 'N/A'}</p>
                </div>
                <div class="detail-section">
                    <h3><i class="fas fa-tags" style="color: #ff6e01;"></i> Adoption Rates</h3>
                    <div class="rates-grid">
                        <div class="rate-item">
                            <i class="fas fa-sun" style="color: #ff6e01;"></i>
                            <strong>Daily:</strong> ₹${animal.daily_rate.toLocaleString()}
                        </div>
                        <div class="rate-item">
                            <i class="fas fa-moon" style="color: #ff6e01;"></i>
                            <strong>Monthly:</strong> ₹${animal.monthly_rate.toLocaleString()}
                        </div>
                        <div class="rate-item">
                            <i class="fas fa-calendar" style="color: #ff6e01;"></i>
                            <strong>Yearly:</strong> ₹${animal.yearly_rate.toLocaleString()}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Global object to track adoptions
    let adoptions = {};
    
    // Load adoptions from localStorage when the page loads
    document.addEventListener('DOMContentLoaded', function() {
        // Load saved adoptions from localStorage
        const savedAdoptions = localStorage.getItem('adoptions');
        if (savedAdoptions) {
            adoptions = JSON.parse(savedAdoptions);
            updateSummaryTable();
        }

        document.querySelectorAll('.period-select').forEach(select => {
            updateRateDisplay(select);
        });
        
        // Form submission handling for multiple animals
        document.getElementById('adoptionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (Object.keys(adoptions).length > 1) {
                alert("Multiple animal adoptions are not yet supported. Please adopt one animal at a time.");
                return;
            }
            
            // Clear adoptions from localStorage after successful submission
            localStorage.removeItem('adoptions');
            this.submit();
        });
    });

    // Updates the rate display for an animal
    function updateRateDisplay(element) {
        const animalId = element.dataset.animalId;
        const periodSelect = document.querySelector(`.period-select[data-animal-id="${animalId}"]`);
        
        const period = periodSelect.value;
        
        let rate;
        switch(period) {
            case 'daily':
                rate = parseFloat(periodSelect.dataset.daily);
                break;
            case 'monthly':
                rate = parseFloat(periodSelect.dataset.monthly);
                break;
            case 'yearly':
                rate = parseFloat(periodSelect.dataset.yearly);
                break;
        }
        
        const rateDisplay = document.getElementById(`rate-${animalId}`);
        rateDisplay.textContent = `Subtotal: ₹${rate.toLocaleString()}`;
    }
    
    // Adds an animal to the adoption summary
    function addToAdoption(animalId, animalName) {
        const periodSelect = document.querySelector(`.period-select[data-animal-id="${animalId}"]`);
        const period = periodSelect.value;
        
        // Get the rate based on the period
        let rate;
        switch(period) {
            case 'daily':
                rate = parseFloat(periodSelect.dataset.daily);
                break;
            case 'monthly':
                rate = parseFloat(periodSelect.dataset.monthly);
                break;
            case 'yearly':
                rate = parseFloat(periodSelect.dataset.yearly);
                break;
        }
        
        // Add to adoptions object
        adoptions[animalId] = {
            name: animalName,
            period: period,
            periodDisplay: period.charAt(0).toUpperCase() + period.slice(1),
            rate: rate,
            quantity: 1  // Default quantity
        };
        
        // Save to localStorage
        localStorage.setItem('adoptions', JSON.stringify(adoptions));
        
        // Update summary table
        updateSummaryTable();
        
        // Show success message
        alert(`${animalName} has been added to your adoption list!`);
    }
    
    // Updates the adoption summary table
    function updateSummaryTable() {
        const summaryTableBody = document.getElementById('summaryTableBody');
        summaryTableBody.innerHTML = '';
        let hasItems = false;
        let totalAmount = 0;
        
        for (const [animalId, adoption] of Object.entries(adoptions)) {
            hasItems = true;
            const subtotal = adoption.rate * adoption.quantity;
            totalAmount += subtotal;
            
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${adoption.name}</td>
                <td>${adoption.periodDisplay}</td>
                <td>₹${subtotal.toLocaleString()}</td>
                <td>
                    <button type="button" class="btn btn-delete" onclick="removeFromAdoption(${animalId})">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            `;
            summaryTableBody.appendChild(row);
        }
        
        // Update total amount
        document.getElementById('totalAmount').textContent = `₹${totalAmount.toLocaleString()}`;
        
        // Enable/disable submit button and update form fields
        const completeBtn = document.getElementById('completeAdoptionBtn');
        completeBtn.disabled = !hasItems;
        
        // If we have exactly one adoption, set the hidden form fields
        if (Object.keys(adoptions).length === 1) {
            const animalId = Object.keys(adoptions)[0];
            const adoption = adoptions[animalId];
            
            document.getElementById('animal_id').value = animalId;
            document.getElementById('period_type').value = adoption.period;
            document.getElementById('quantity').value = adoption.quantity;
        }
    }
    
    // Removes an animal from the adoption summary
    function removeFromAdoption(animalId) {
        delete adoptions[animalId];
        
        // Update localStorage
        if (Object.keys(adoptions).length > 0) {
            localStorage.setItem('adoptions', JSON.stringify(adoptions));
        } else {
            localStorage.removeItem('adoptions');
        }
        
        updateSummaryTable();
    }

    // Function to get health status color
    function getHealthStatusColor(status) {
        const statusLower = status.toLowerCase();
        if (statusLower.includes('healthy')) return '#2ecc71'; // Green
        if (statusLower.includes('critical')) return '#e74c3c'; // Red
        if (statusLower.includes('sick')) return '#f1c40f'; // Yellow
        if (statusLower.includes('recovering')) return '#3498db'; // Blue
        return '#ff6e01'; // Default orange
    }

    // Function to get health status icon
    function getHealthStatusIcon(status) {
        const statusLower = status.toLowerCase();
        if (statusLower.includes('healthy')) return 'fa-heart';
        if (statusLower.includes('critical')) return 'fa-exclamation-circle';
        if (statusLower.includes('sick')) return 'fa-thermometer-half';
        if (statusLower.includes('recovering')) return 'fa-plus-circle';
        return 'fa-heartbeat';
    }

    function showAnimalDetailsModal(animalId) {
        const modal = document.getElementById('animalDetailsModal');
        const contentDiv = document.getElementById('animalDetailsContent');
        
        contentDiv.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
        modal.style.display = 'block';
        
        fetch('get_animal_details.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${animalId}`
        })
            .then(response => response.json())
            .then(animal => {
                const healthColor = getHealthStatusColor(animal.health_status || '');
                const healthIcon = getHealthStatusIcon(animal.health_status || '');
                
                contentDiv.innerHTML = `
                    <div class="animal-detail-card">
                        <div class="detail-header">
                            <img src="${animal.image_url || '/api/placeholder/300/200'}" 
                                 alt="${animal.name}" 
                                 class="detail-image">
                            <h2><i class="fas fa-paw" style="color: #ff6e01;"></i> ${animal.name}</h2>
                        </div>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <i class="fas fa-dna" style="color: #ff6e01;"></i>
                                <strong>Species:</strong> ${animal.species || 'N/A'}
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-venus-mars" style="color: #ff6e01;"></i>
                                <strong>Gender:</strong> ${animal.gender || 'N/A'}
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-hourglass-half" style="color: #ff6e01;"></i>
                                <strong>Age:</strong> ${animal.age ? animal.age + ' years' : 'N/A'}
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-birthday-cake" style="color: #ff6e01;"></i>
                                <strong>Birth Year:</strong> ${animal.birth_year || 'N/A'}
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-weight" style="color: #ff6e01;"></i>
                                <strong>Weight:</strong> ${animal.weight} kg
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-ruler-vertical" style="color: #ff6e01;"></i>
                                <strong>Height:</strong> ${animal.height} cm
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-palette" style="color: #ff6e01;"></i>
                                <strong>Color:</strong> ${animal.color || 'N/A'}
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-utensils" style="color: #ff6e01;"></i>
                                <strong>Diet Type:</strong> ${animal.diet_type || 'N/A'}
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-tree" style="color: #ff6e01;"></i>
                                <strong>Habitat:</strong> ${animal.habitat || 'N/A'}
                            </div>
                            <div class="detail-item health-status" style="color: ${healthColor};">
                                <i class="fas ${healthIcon}" style="color: ${healthColor};"></i>
                                <strong>Health Status:</strong> ${animal.health_status || 'N/A'}
                            </div>
                        </div>
                        <div class="detail-section">
                            <h3><i class="fas fa-info-circle" style="color: #ff6e01;"></i> Description</h3>
                            <p>${animal.description}</p>
                        </div>
                        <div class="detail-section">
                            <h3><i class="fas fa-sticky-note" style="color: #ff6e01;"></i> Special Notes</h3>
                            <p>${animal.special_notes || 'N/A'}</p>
                        </div>
                        <div class="detail-section">
                            <h3><i class="fas fa-tags" style="color: #ff6e01;"></i> Adoption Rates</h3>
                            <div class="rates-grid">
                                <div class="rate-item">
                                    <i class="fas fa-sun" style="color: #ff6e01;"></i>
                                    <strong>Daily:</strong> ₹${animal.daily_rate.toLocaleString()}
                                </div>
                                <div class="rate-item">
                                    <i class="fas fa-moon" style="color: #ff6e01;"></i>
                                    <strong>Monthly:</strong> ₹${animal.monthly_rate.toLocaleString()}
                                </div>
                                <div class="rate-item">
                                    <i class="fas fa-calendar" style="color: #ff6e01;"></i>
                                    <strong>Yearly:</strong> ₹${animal.yearly_rate.toLocaleString()}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            })
            .catch(error => {
                contentDiv.innerHTML = '<div class="error"><i class="fas fa-exclamation-circle"></i> Error loading animal details</div>';
            });
    }

    // Close modal when clicking the close button or outside the modal
    document.querySelector('.close-modal').onclick = function() {
        document.getElementById('animalDetailsModal').style.display = 'none';
    }

    window.onclick = function(event) {
        const modal = document.getElementById('animalDetailsModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

    // Update the completeAdoptionBtn click handler in your script section
    document.getElementById('completeAdoptionBtn').addEventListener('click', function() {
        if (Object.keys(adoptions).length === 0) {
            alert('Please add at least one animal to your adoption list.');
            return;
        }
        
        // Store adoptions in session storage for the payment page
        sessionStorage.setItem('adoptions', JSON.stringify(adoptions));
        
        // Redirect to adoption payment page
        window.location.href = 'adoption-payment.php';
    });
</script>

<!-- Add this CSS to your style.css file -->
<style>
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
    overflow-y: auto;
    justify-content: center; /* Center the modal */
    align-items: center; /* Center the modal */
}

.modal-content {
    background: linear-gradient(to bottom right, #ffffff, #f8f9fa);
    border-radius: 15px; /* Rounded corners */
    padding: 40px; /* Increased padding inside the modal */
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.5); /* Enhanced shadow */
    max-width: 800px; /* Maximum width */
    margin: auto; /* Center the modal */
}

.animal-details {
    font-size: 1.4em; /* Increased font size for better readability */
}

.detail-header {
    text-align: center;
    margin-bottom: 20px;
}

.detail-image {
    max-width: 250px; /* Increased image size */
    height: auto;
    border-radius: 10px;
    margin-bottom: 15px;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.detail-item {
    background-color: rgba(255, 255, 255, 0.9);
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 10px;
}

.detail-item i {
    font-size: 1.2em;
    width: 20px;
    text-align: center;
}

.detail-section {
    margin: 20px 0;
}

.detail-section h3 {
    color: #333;
    margin-bottom: 10px;
    font-size: 1.6em; /* Increased section title size */
}

.rates-grid {
    display: flex;
    justify-content: space-between;
    margin: 20px 0;
}

.rate-item {
    padding: 15px;
    background-color: #f0f0f0;
    border-radius: 8px;
    text-align: center;
    flex: 1;
    margin: 0 10px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.rate-item i {
    font-size: 1.4em;
    margin-bottom: 5px;
}

.loading, .error {
    text-align: center;
    padding: 40px;
    font-size: 18px;
}

.loading i, .error i {
    margin-right: 10px;
}

.error {
    color: #dc3545;
}

.animal-card {
    background-color: #fff; /* White background */
    border-radius: 10px; /* Rounded corners */
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); /* Shadow effect */
    overflow: hidden; /* Prevent overflow */
    margin: 20px; /* Margin around the card */
    transition: transform 0.2s; /* Smooth hover effect */
}

.animal-card:hover {
    transform: scale(1.02); /* Slightly enlarge on hover */
}

.animal-image {
    width: 100%; /* Full width */
    height: auto; /* Maintain aspect ratio */
}

.animal-info {
    padding: 15px; /* Padding inside the card */
}

.animal-name {
    font-size: 1.5em; /* Larger font size */
    color: #333; /* Dark color */
    margin: 0; /* Remove default margin */
}

.animal-description {
    color: #666; /* Lighter color for description */
    margin: 10px 0; /* Margin */
}

.animal-stats {
    display: flex; /* Flex layout for stats */
    align-items: center; /* Center items vertically */
    gap: 20px; /* Space between items */
    margin: 10px 0; /* Margin */
}

.stat-item {
    display: flex; /* Flex layout */
    align-items: center; /* Center items vertically */
    color: #333; /* Text color */
}

.adoption-options {
    margin-top: 15px; /* Margin */
}

.option-group {
    margin-bottom: 10px; /* Margin */
}

.button-group {
    display: flex; /* Flex layout for buttons */
    justify-content: space-between; /* Space between buttons */
}

.adopt-button, .details-button {
    background-color: #ff6e01;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
    transition: background-color 0.3s;
}

.adopt-button:hover, .details-button:hover {
    background-color: #ff5500;
}

.adopt-button:disabled {
    background-color: #ccc;
    cursor: not-allowed;
}

.btn-delete {
    background-color: #dc3545;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 3px;
    cursor: pointer;
    transition: background-color 0.3s;
}

.btn-delete:hover {
    background-color: #c82333;
}

/* Add these styles for health status colors */
.health-status {
    padding: 8px 15px;
    border-radius: 20px;
    background-color: rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.health-status i {
    margin-right: 8px;
}

.health-status:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

/* Specific health status colors */
.health-status.healthy {
    color: #2ecc71;
}

.health-status.critical {
    color: #e74c3c;
}

.health-status.sick {
    color: #f1c40f;
}

.health-status.recovering {
    color: #3498db;
}

/* Enhanced modal styles */
.modal-content {
    background: linear-gradient(to bottom right, #ffffff, #f8f9fa);
}

.animal-detail-card {
    background: linear-gradient(to bottom right, #ffffff, #f8f9fa);
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.2);
    transition: transform 0.3s;
}

.animal-detail-card:hover {
    transform: scale(1.02);
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.detail-item {
    background-color: rgba(255, 255, 255, 0.9);
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 10px;
}

.health-status {
    padding: 8px 15px;
    border-radius: 20px;
    background-color: rgba(0, 0, 0, 0.05);
}

.vaccination-badge {
    padding: 5px 10px;
    border-radius: 12px;
    color: #fff;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.vaccination-badge.completed {
    background-color: #2ecc71; /* Green */
}

.vaccination-badge.not-completed {
    background-color: #e74c3c; /* Red */
}

.rates-grid {
    display: flex;
    justify-content: space-between;
    margin: 20px 0;
}

.rate-item {
    padding: 15px;
    background-color: #f0f0f0;
    border-radius: 8px;
    text-align: center;
    flex: 1;
    margin: 0 10px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.rate-item i {
    font-size: 1.4em;
    margin-bottom: 5px;
}

.total-row {
    background-color: #f8f9fa;
    font-weight: bold;
}

.total-label {
    text-align: right;
    padding-right: 15px;
}

.total-amount {
    color: #ff6e01;
    font-size: 1.1em;
}

.summary-table tfoot td {
    border-top: 2px solid #ddd;
    padding: 12px;
}
</style>

<?php include 'footer.php'; ?>
</body>
</html>