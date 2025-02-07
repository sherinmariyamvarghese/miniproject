<?php
session_start();
require_once 'connect.php';

$animals = [
    [
        'id' => 1,
        'name' => 'Lion',
        'daily_rate' => 1000,
        'monthly_rate' => 25000,
        'yearly_rate' => 250000,
        'image' => 'images/lions.png',
        'description' => 'Majestic lion living in our African savanna exhibit.'
    ],
    [
        'id' => 2,
        'name' => 'Tiger',
        'daily_rate' => 1000,
        'monthly_rate' => 25000,
        'yearly_rate' => 250000,
        'image' => 'images/tigers.png',
        'description' => 'Bengal tiger from our Asian wilderness section.'
    ],
    [
        'id' => 3,
        'name' => 'Elephant',
        'daily_rate' => 1500,
        'monthly_rate' => 35000,
        'yearly_rate' => 350000,
        'image' => 'images/elephant.jpg',
        'description' => 'Asian elephant residing in our sanctuary.'
    ],
    [
        'id' => 4,
        'name' => 'Giraffe',
        'daily_rate' => 800,
        'monthly_rate' => 20000,
        'yearly_rate' => 200000,
        'image' => 'images/giraffe.jpg',
        'description' => 'Tall friend from our African plains exhibit.'
    ],
    [
        'id' => 5,
        'name' => 'hippopotamus ',
        'daily_rate' => 500,
        'monthly_rate' => 12000,
        'yearly_rate' => 120000,
        'image' => 'images/penguin.jpg',
        'description' => 'Emperor  hippopotamus  from our exhibit.'
    ],
    [
        'id' => 6,
        'name' => 'Red Panda',
        'daily_rate' => 600,
        'monthly_rate' => 15000,
        'yearly_rate' => 150000,
        'image' => 'images/red-panda.jpg',
        'description' => 'Adorable red panda from our Asian habitat.'
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process adoption form submission
    // Add your database insertion logic here
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
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<link rel="stylesheet" href="https://unpkg.com/swiper@7/swiper-bundle.min.css" />
<link rel="stylesheet" href="css/style.css">


    <style>
        :root {
            --primary-color: #ff6e01;
            --secondary-color: #f1e1d2;
            --text-color: #333;
            --white: #fff;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .adoption-container {
            max-width: 1200px;
            margin: 100px auto;
            padding: 20px;
        }

        .adoption-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .adoption-header h1 {
            color: var(--primary-color);
            font-size: 2.5rem;
            margin-bottom: 15px;
        }

        .adoption-header p {
            color: var(--text-color);
            font-size: 1.1rem;
            max-width: 800px;
            margin: 0 auto;
        }

        .animals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .animal-card {
            background: var(--white);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
        }

        .animal-card:hover {
            transform: translateY(-5px);
        }

        .animal-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .animal-info {
            padding: 20px;
        }

        .animal-name {
            font-size: 1.5rem;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .animal-description {
            color: var(--text-color);
            margin-bottom: 15px;
            font-size: 0.9rem;
        }

        .adoption-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .adoption-period {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: var(--secondary-color);
            border-radius: 5px;
        }

        .period-input {
            width: 60px;
            padding: 5px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 3px;
        }

        .summary-section {
            background: var(--white);
            padding: 20px;
            border-radius: 15px;
            box-shadow: var(--shadow);
            margin-top: 30px;
        }

        .summary-title {
            color: var(--primary-color);
            font-size: 1.8rem;
            margin-bottom: 20px;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .summary-table th,
        .summary-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .total-row {
            font-weight: bold;
            background: var(--secondary-color);
        }

        .adopt-button {
            display: block;
            width: 100%;
            padding: 15px;
            background: var(--primary-color);
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .adopt-button:hover {
            background: #ff8f2a;
        }

        @media (max-width: 768px) {
            .animals-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<header class="header">
    <a href="#" class="logo"> <i class="fas fa-paw"></i> SafariGate</a>
    
    <nav class="navbar">
        <a href="index.php#home">home</a>
        <a href="index.php#about">about</a>
        <a href="index.php#gallery">gallery</a>
        <a href="index.php#animal">animal</a>
        <a href="index.php#pricing">pricing</a>
        <a href="index.php#contact">contact</a>
        <a href="">donation</a>
        <a href="#adoption">adoption</a>
    </nav>

    <div class="icons">
        <?php if(isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
            <div id="login-btn" class="fas fa-user">
                <form class="login-form">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user']); ?></span>
                    <a href="profile.php" class="btn">Profile</a>
                    <a href="logout.php" class="btn">Logout</a>
                </form>
            </div>
        <?php else: ?>
            <div id="login-btn" class="fas fa-user"></div>
            <div id="menu-btn" class="fas fa-bars"></div>
        <?php endif; ?>
    </div>

    <?php if(!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true): ?>
    <form action="login.php" class="login-form">
        <a href="login.php" class="btn">Login</a>
        <a href="register.php" class="btn">Register</a>
    </form>
    <?php endif; ?>
</header>

    <div class="adoption-container">
        <div class="adoption-header">
            <h1><i class="fas fa-heart"></i> Animal Adoption Program</h1>
            <p>Support our zoo animals by becoming an adoptive parent. Your contribution helps us provide the best care for our animal family.</p>
        </div>

        <form id="adoptionForm" method="post">
            <div class="animals-grid">
                <?php foreach ($animals as $animal): ?>
                <div class="animal-card">
                    <img src="<?php echo htmlspecialchars($animal['image']); ?>" alt="<?php echo htmlspecialchars($animal['name']); ?>" class="animal-image">
                    <div class="animal-info">
                        <h3 class="animal-name"><?php echo htmlspecialchars($animal['name']); ?></h3>
                        <p class="animal-description"><?php echo htmlspecialchars($animal['description']); ?></p>
                        <div class="adoption-options">
                            <div class="adoption-period">
                                <label>Days (₹<?php echo number_format($animal['daily_rate']); ?>/day):</label>
                                <input type="number" min="0" value="0" 
                                    class="period-input" 
                                    data-daily-rate="<?php echo $animal['daily_rate']; ?>"
                                    data-animal-id="<?php echo $animal['id']; ?>"
                                    data-type="daily"
                                    onchange="calculateTotal()">
                            </div>
                            <div class="adoption-period">
                                <label>Months (₹<?php echo number_format($animal['monthly_rate']); ?>/month):</label>
                                <input type="number" min="0" value="0" 
                                    class="period-input"
                                    data-monthly-rate="<?php echo $animal['monthly_rate']; ?>"
                                    data-animal-id="<?php echo $animal['id']; ?>"
                                    data-type="monthly"
                                    onchange="calculateTotal()">
                            </div>
                            <div class="adoption-period">
                                <label>Years (₹<?php echo number_format($animal['yearly_rate']); ?>/year):</label>
                                <input type="number" min="0" value="0" 
                                    class="period-input"
                                    data-yearly-rate="<?php echo $animal['yearly_rate']; ?>"
                                    data-animal-id="<?php echo $animal['id']; ?>"
                                    data-type="yearly"
                                    onchange="calculateTotal()">
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="summary-section">
                <h2 class="summary-title">Adoption Summary</h2>
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th>Animal</th>
                            <th>Period</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody id="summaryTableBody">
                        <!-- JavaScript will populate this -->
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="2">Total Amount:</td>
                            <td id="totalAmount">₹0</td>
                        </tr>
                    </tfoot>
                </table>
                <button type="submit" class="adopt-button">Complete Adoption</button>
            </div>
        </form>
    </div>

    <script>
        const animals = <?php echo json_encode($animals); ?>;

        function calculateTotal() {
            let total = 0;
            const summaryTableBody = document.getElementById('summaryTableBody');
            summaryTableBody.innerHTML = '';

            animals.forEach(animal => {
                const dailyInput = document.querySelector(`input[data-animal-id="${animal.id}"][data-type="daily"]`);
                const monthlyInput = document.querySelector(`input[data-animal-id="${animal.id}"][data-type="monthly"]`);
                const yearlyInput = document.querySelector(`input[data-animal-id="${animal.id}"][data-type="yearly"]`);

                const days = parseInt(dailyInput.value) || 0;
                const months = parseInt(monthlyInput.value) || 0;
                const years = parseInt(yearlyInput.value) || 0;

                const dailyAmount = days * animal.daily_rate;
                const monthlyAmount = months * animal.monthly_rate;
                const yearlyAmount = years * animal.yearly_rate;

                const animalTotal = dailyAmount + monthlyAmount + yearlyAmount;

                if (animalTotal > 0) {
                    let periods = [];
                    if (days > 0) periods.push(`${days} days`);
                    if (months > 0) periods.push(`${months} months`);
                    if (years > 0) periods.push(`${years} years`);

                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${animal.name}</td>
                        <td>${periods.join(', ')}</td>
                        <td>₹${animalTotal.toLocaleString()}</td>
                    `;
                    summaryTableBody.appendChild(row);
                }

                total += animalTotal;
            });

            document.getElementById('totalAmount').textContent = `₹${total.toLocaleString()}`;
        }

        // Initialize calculation
        calculateTotal();
    </script>

<section class="footer">

<div class="box-container">

    <div class="box">
        <h3><i class="fas fa-paw"></i> zoo</h3>
        <p>Lorem ipsum dolor sit amet consectetur adipisicing elit.</p>
        <p class="links"><i class="fas fa-clock"></i>monday - friday</p>
        <p class="days">7:00AM - 11:00PM</p>
    </div>

    <div class="box">
        <h3>Contact Info</h3>
        <a href="#" class="links"><i class="fas fa-phone"></i> 1245-147-2589</a>
        <a href="#" class="links"><i class="fas fa-phone"></i> 1245-147-2589</a>
        <a href="#" class="links"><i class="fas fa-envelope"></i> info@zoolife.com</a>
        <a href="#" class="links"><i class="fas fa-map-marker-alt"></i> karachi, pakistan</a>
    </div>

    <div class="box">
        <h3>quick links</h3>
        <a href="#" class="links"> <i class="fas fa-arrow-right"></i>home</a>
        <a href="#" class="links"> <i class="fas fa-arrow-right"></i>about</a>
        <a href="#" class="links"> <i class="fas fa-arrow-right"></i>gallery</a>
        <a href="#" class="links"> <i class="fas fa-arrow-right"></i>animal</a>
        <a href="#" class="links"> <i class="fas fa-arrow-right"></i>pricing</a>
    </div>

    <div class="box">
        <h3>newsletter</h3>
        <p>subscribe for latest updates</p>
        <input type="email" placeholder="Your Email" class="email">
        <a href="#" class="btn">subscribe</a>
        <div class="share">
            <a href="#" class="fab fa-facebook-f"></a>
            <a href="#" class="fab fa-twitter"></a>
            <a href="#" class="fab fa-instagram"></a>
            <a href="#" class="fab fa-linkedin"></a>
        </div>
    </div>

</div>

<div class="credit">&copy; 2022 zoolife. All rights reserved by <a href="#" class="link">ninjashub</a></div>

</section>






<!-- end -->















<script src="https://unpkg.com/swiper@7/swiper-bundle.min.js"></script>

<script src="js/script.js"></script>


<script src="js/script.js"></script>
</body>
</html>