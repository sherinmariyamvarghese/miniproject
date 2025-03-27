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
    <title>Donation - SafariGate</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* donation section */
        .donation{
            min-height: 100vh;
            background: var(--bg);
            padding-top: 8rem;
        }

        .donation .hero-section{
            text-align: center;
            padding: 4rem 7%;
        }

        .donation .hero-section h1{
            font-size: 6rem;
            color: var(--main);
            margin-bottom: 2rem;
        }

        .donation .hero-section p{
            font-size: 1.8rem;
            color: #444;
            line-height: 1.8;
            max-width: 80rem;
            margin: 0 auto;
        }

        .donation .container{
            max-width: 130rem;
            margin: 0 auto;
            padding: 0 7%;
        }

        .donation .impact-grid{
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(30rem, 1fr));
            gap: 2rem;
            margin-bottom: 4rem;
        }

        .donation .impact-box{
            background: var(--white);
            padding: 3rem;
            text-align: center;
            border-radius: .5rem;
            box-shadow: var(--box-shadow);
        }

        .donation .impact-box i{
            font-size: 4rem;
            color: var(--main);
            margin-bottom: 2rem;
        }

        .donation .impact-box h3{
            font-size: 2.2rem;
            color: var(--black);
            margin-bottom: 1.5rem;
        }

        .donation .impact-box p{
            font-size: 1.6rem;
            color: #444;
            line-height: 1.8;
        }

        .donation .benefits{
            background: var(--white);
            padding: 4rem;
            border-radius: .5rem;
            box-shadow: var(--box-shadow);
            margin-bottom: 4rem;
        }

        .donation .benefits h2{
            font-size: 3rem;
            color: var(--main);
            margin-bottom: 3rem;
            text-align: center;
        }

        .donation .benefits .content{
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(35rem, 1fr));
            gap: 3rem;
        }

        .donation .benefits .content h3{
            font-size: 2.2rem;
            color: var(--black);
            margin-bottom: 2rem;
        }

        .donation .benefits .content ul li{
            font-size: 1.6rem;
            color: #444;
            padding: 1rem 0;
            display: flex;
            align-items: center;
            list-style: none;
        }

        .donation .benefits .content ul li i{
            color: var(--main);
            padding-right: 1.5rem;
            font-size: 1.8rem;
        }

        .donation .cta-section{
            text-align: center;
            padding: 4rem 0;
        }

        .donation .cta-section h2{
            font-size: 3rem;
            color: var(--black);
            margin-bottom: 2rem;
        }

        .donation .cta-section p{
            font-size: 1.8rem;
            color: #444;
            max-width: 60rem;
            margin: 0 auto 3rem;
            line-height: 1.8;
        }

        .donation .cta-section .btn{
            font-size: 1.8rem;
        }

        /* media queries */
        @media (max-width: 991px){
            .donation .hero-section h1{
                font-size: 5rem;
            }
        }

        @media (max-width: 768px){
            .donation .hero-section h1{
                font-size: 4rem;
            }
            
            .donation .benefits{
                padding: 3rem;
            }
        }

        @media (max-width: 450px){
            .donation .hero-section h1{
                font-size: 3.5rem;
            }
            
            .donation .container{
                padding: 0 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <?php include 'header.php'; ?>
    <!-- Donation Section -->
    <section class="donation">
        <div class="hero-section">
            <h1>Support Our Wildlife Family</h1>
            <p>Your donation helps us provide essential care, nutrition, and enrichment for our amazing animals while supporting vital conservation efforts.</p>
            <?php if(isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                <a href="donation.php" class="btn">Donate Now</a>
            <?php else: ?>
                <a href="login.php" class="btn">Donate Now</a>
            <?php endif; ?>
        </div>

        <div class="container">
            <div class="impact-grid">
                <div class="impact-box">
                    <i class="fas fa-heart"></i>
                    <h3>Animal Care</h3>
                    <p>Your donation provides food, veterinary care, and habitat maintenance for our diverse animal family.</p>
                </div>

                <div class="impact-box">
                    <i class="fas fa-globe"></i>
                    <h3>Conservation</h3>
                    <p>Support our conservation programs and help protect endangered species around the world.</p>
                </div>

                <div class="impact-box">
                    <i class="fas fa-book"></i>
                    <h3>Education</h3>
                    <p>Help us educate future generations about wildlife conservation and environmental stewardship.</p>
                </div>
            </div>

            <div class="benefits">
                <h2>Why Your Donation Matters</h2>
                <div class="content">
                    <div>
                        <h3>Your Support Provides:</h3>
                        <ul>
                            <li><i class="fas fa-check"></i>Daily nutrition and specialized diets</li>
                            <li><i class="fas fa-check"></i>Regular veterinary care and health monitoring</li>
                            <li><i class="fas fa-check"></i>Habitat maintenance and enrichment</li>
                            <li><i class="fas fa-check"></i>Conservation research and breeding programs</li>
                        </ul>
                    </div>
                   <!-- <div>
                        <h3>Donor Benefits:</h3>
                        <ul>
                            <li><i class="fas fa-star"></i>Recognition on our donor wall</li>
                            <li><i class="fas fa-star"></i>Exclusive newsletter updates</li>
                            <li><i class="fas fa-star"></i>Special event invitations</li>
                            <li><i class="fas fa-star"></i>Tax-deductible contribution receipt</li>
                        </ul>
                    </div>-->
                </div>
            </div>

            <div class="cta-section">
                <h2>Make a Difference Today</h2>
                <p>Every donation, no matter the size, helps us continue our mission of wildlife conservation and education.</p>
                <?php if(isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                <a href="donation.php" class="btn">Donate Now</a>
            <?php else: ?>
                <a href="login.php" class="btn">Donate Now</a>
            <?php endif; ?>
            </div>
        </div>
    </section>

    <?php include 'footer.php'; ?>
    <!-- Footer Section -->
   
</body>
</html>