<?php
session_start();
include 'connect.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animal Adoption - SafariGate</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<link rel="stylesheet" href="https://unpkg.com/swiper@7/swiper-bundle.min.css" />

    
    <link rel="stylesheet" href="css/style.css">
    <style>
        .adoption-info-section {
            padding: 8rem 7%;
            background: var(--bg);
        }

        .adoption-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .adoption-intro {
            text-align: center;
            margin-bottom: 4rem;
        }

        .adoption-intro p {
            font-size: 1.8rem;
            color: #444;
            line-height: 1.8;
            margin-bottom: 2rem;
            max-width: 800px;
            margin: 0 auto;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(30rem, 1fr));
            gap: 3rem;
            margin: 4rem 0;
        }

        .info-box {
            background: var(--white);
            padding: 3rem;
            border-radius: 1rem;
            box-shadow: var(--box-shadow);
        }

        .info-box h3 {
            font-size: 2.2rem;
            color: var(--main);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .info-box h3 i {
            color: var(--main);
        }

        .info-box p {
            font-size: 1.6rem;
            color: #666;
            line-height: 1.8;
            margin-bottom: 1.5rem;
        }

        .adoption-cta {
            text-align: center;
            background: var(--white);
            padding: 4rem;
            border-radius: 1rem;
            margin-top: 4rem;
            box-shadow: var(--box-shadow);
        }

        .adoption-cta h3 {
            font-size: 2.5rem;
            color: var(--black);
            margin-bottom: 2rem;
        }

        .adoption-cta p {
            font-size: 1.8rem;
            color: #666;
            margin-bottom: 2rem;
        }

        .price-list {
            margin: 3rem 0;
            font-size: 1.6rem;
            color: #444;
        }

        .price-list li {
            margin: 1rem 0;
            list-style: none;
        }

        .highlights {
            background: #fff9f4;
            padding: 2rem;
            border-radius: 0.5rem;
            margin: 2rem 0;
        }

        .highlights li {
            font-size: 1.6rem;
            color: #444;
            margin: 1rem 0;
            padding-left: 2rem;
            position: relative;
        }

        .highlights li::before {
            content: "•";
            color: var(--main);
            font-size: 2rem;
            position: absolute;
            left: 0;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

    <section class="adoption-info-section">
        <h2 class="heading">Animal Adoption Program</h2>
        
        <div class="adoption-container">
            <div class="adoption-intro">
                <p>Join SafariGate's Animal Adoption Program and become a guardian for our wonderful animals. Your adoption helps provide essential care, nutrition, and enrichment for our zoo residents while supporting wildlife conservation.</p>
                <?php if(isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                <a href="adoption.php" class="btn">Adopt Now</a>
            <?php else: ?>
                <a href="login.php" class="btn">Adopt Now</a>
            <?php endif; ?>
            </div>

            <div class="info-grid">
                <div class="info-box">
                    <h3><i class="fas fa-heart"></i> Why Adopt?</h3>
                    <p>By adopting an animal, you're not just supporting one creature – you're contributing to wildlife conservation, education, and the well-being of all our zoo residents. Your adoption helps provide:</p>
                    <ul class="highlights">
                        <li>Daily food and nutrition</li>
                        <li>Veterinary care and health check-ups</li>
                        <li>Habitat maintenance and enrichment</li>
                        <li>Support for conservation programs</li>
                        <li>Educational programs for visitors</li>
                    </ul>
                </div>

                <div class="info-box">
                    <h3><i class="fas fa-gift"></i> Adoption Benefits</h3>
                    <p>As an animal adopter, you'll receive:</p>
                    <ul class="highlights">
                        <li>Personalized adoption certificate</li>
                        <!-- <li>Regular updates about your adopted animal</li>
                        <li>A fact sheet about your adopted species</li> -->
                    </ul>
                </div>
            </div>

            <div class="adoption-cta">
                <h3>Adoption Plans</h3>
                <p>Choose from our flexible adoption durations:</p>
                <ul class="price-list">
                    <li>1 Month Adoption: ₹5,000</li>
                    <li>6 Months Adoption: ₹25,000</li>
                    <li>1 Year Adoption: ₹45,000</li>
                </ul>
                <p>Your contribution is tax-deductible under Section 80G of the Income Tax Act.</p>
                <?php if(isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                <a href="adoption.php" class="btn">Adopt Now</a>
            <?php else: ?>
                <a href="login.php" class="btn">Adopt Now</a>
            <?php endif; ?>
    
    
            </div>
        </div>
    </section>
    
    <?php include 'footer.php'; ?>

   
</body>
</html>