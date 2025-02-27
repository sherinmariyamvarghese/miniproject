<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Responsive Zoo Website</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/swiper@7/swiper-bundle.min.css" />
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <section class="home" id="home">
        <div class="content">
            <h3>enjoy the wonderful <br>
                adventure of the <br> animals</h3>
            <?php if(isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                <div class="cta-buttons">
                    <a href="booking.php" class="btn btn-primary pulse-button">
                        <i class="fas fa-ticket-alt"></i>
                        Book Your Tickets Now!
                    </a>
                </div>
            <?php else: ?>
                <div class="cta-buttons">
                    <a href="login.php" class="btn btn-primary pulse-button">
                        <i class="fas fa-ticket-alt"></i>
                        Book Your Tickets Now!
                    </a>
                </div>
            <?php endif; ?>
            <img src="images/bottom_wave.png" alt="" class="wave">
        </div>
    </section>

    <!-- about -->

    <section class="about" id="about">

        <h2 class="deco-title">About us</h2>

        <div class="box-container">

            <div class="image">
                <img src="images/about.png" alt="">
            </div>

            <div class="content">
                <h3 class="title">you can find all the most popular species</h3>
                <p>Lorem, ipsum dolor sit amet consectetur adipisicing elit. 
                    Nesciunt temporibus ipsum consectetur asperiores modi ratione. 
                    Sit, dolores voluptas consequuntur dolor tempore quibusdam est 
                    obcaecati possimus omnis, officiis molestias et sapiente.</p>
                
                <div class="icons-container">
                    <div class="icons">
                        <i class="fas fa-graduation-cap"></i>
                        <h3>we educate</h3>
                    </div>
                    <div class="icons">
                        <i class="fas fa-bullhorn"></i>
                        <h3>we play</h3>
                    </div>
                    <div class="icons">
                        <i class="fas fa-book-open"></i>
                        <h3>getting to know</h3>
                    </div>
                </div>
            </div>

        </div>

    </section>

    <!-- end -->

    <!-- gallery -->

    <section class="gallery" id="gallery">

        <h2 class="heading">gallery</h2>

        <div class="swiper gallery-slider">

            <div class="swiper-wrapper">

                <div class="swiper-slide slide">
                    <div class="image">
                        <img src="images/gallery-1.jpg" alt="">
                    </div>
                </div>

                <div class="swiper-slide slide">
                    <div class="image">
                        <img src="images/gallery-2.jpg" alt="">
                    </div>
                </div>

                <div class="swiper-slide slide">
                    <div class="image">
                        <img src="images/gallery-3.jpg" alt="">
                    </div>
                </div>

                <div class="swiper-slide slide">
                    <div class="image">
                        <img src="images/gallery-4.jpg" alt="">
                    </div>
                </div>

            </div>

            <div class="swiper-button-next"></div>
            <div class="swiper-button-prev"></div>

        </div>

    </section>

    <!-- end -->

    <!-- animals -->

    <section class="animal" id="animal">

        <h2 class="heading">animals</h2>

        <div class="box-container">

            <div class="box">
                <img src="images/animal_1.jpg" alt="">
                <div class="content">
                    <h3>chameleon</h3>
                    <a href="#" class="btn">see details</a>
                </div>
            </div>

            <div class="box">
                <img src="images/animals_2.jpg" alt="">
                <div class="content">
                    <h3>zebra</h3>
                    <a href="#" class="btn">see details</a>
                </div>
            </div>

            <div class="box">
                <img src="images/animals_3.jpg" alt="">
                <div class="content">
                    <h3>giraffe</h3>
                    <a href="#" class="btn">see details</a>
                </div>
            </div>

            <div class="box">
                <img src="images/animals_4.jpg" alt="">
                <div class="content">
                    <h3>monkey</h3>
                    <a href="#" class="btn">see details</a>
                </div>
            </div>

        </div>

    </section>

    <!-- end -->

    <!-- banner -->

    <section class="banner">

        <div class="row">
            
            <div class="content">
                <h3>stay with pets</h3>
            <p>Lorem ipsum dolor sit amet consectetur adipisicing elit. 
                Saepe doloremque rem reiciendis beatae, ut tempora. Et dolorem enim, iusto autem eaque harum. 
                Ex praesentium commodi sequi culpa eius fugit vel.</p> 
            </div>

            <div class="image">
                <img src="images/banner_1.png" alt="">
            </div>
            
        </div>

    </section>

    <!-- end -->

    <!-- pricing -->

    <section class="pricing" id="pricing">

        <h2 class="heading">pricing</h2>

        <div class="box-container">

            <a href="booking.php" class="box-link">
                <div class="box">
                    <img src="images/adults.jpg" alt="">
                    <h3>ADULT</h3>
                    <h4 class="price">$ 80</h4>
                    <p>the entrance is from 7 to 11:00PM</p>
                    <span class="book-now">Book Now <i class="fas fa-arrow-right"></i></span>
                </div>
            </a>

            <a href="booking.php" class="box-link">
                <div class="box">
                    <img src="images/child.jpg" alt="">
                    <h3>CHILD</h3>
                    <h4 class="price">$ 40</h4>
                    <p>the entrance is from  7 to 11:00PM</p>
                    <span class="book-now">Book Now <i class="fas fa-arrow-right"></i></span>
                </div>
            </a>

            <a href="booking.php" class="box-link">
                <div class="box">
                    <img src="images/SENIOR_CITIZEN.jpg" alt="">
                    <h3>SENIOR CITIZEN</h3>
                    <h4 class="price">$ 40</h4>
                    <p>the entrance is from 7 to 11:00PM</p>
                    <span class="book-now">Book Now <i class="fas fa-arrow-right"></i></span>
                </div>
            </a>

        </div>

    </section>

    <!-- end -->

    <!-- contact -->
<!-- 
        <section class="contact" id="contact">

            <h2 class="heading">contact</h2>

            <form action="">

                <div class="inputBox">
                    <input type="text" placeholder="name">
                    <input type="email" placeholder="email">
                </div>

                <div class="inputBox">
                    <input type="number" placeholder="number">
                    <input type="text" placeholder="subject">
                </div>

                <textarea name="" id="" cols="30" rows="10" placeholder="meassage"></textarea>

                <a href="#" class="btn">send message</a>

            </form>

        </section> -->

        <!-- end -->

        <?php include 'footer.php'; ?>

    <script src="https://unpkg.com/swiper@7/swiper-bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>