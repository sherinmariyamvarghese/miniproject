<header class="header">
    <a href="index.php" class="logo"> <i class="fas fa-paw"></i> SafariGate </a>

    <nav class="navbar">
        <a href="index.php"><i class="fas fa-home"></i> home</a>
        <a href="index.php#about"><i class="fas fa-info-circle"></i> about</a>
        <a href="a.php"><i class="fas fa-heart"></i> adoption</a>
        <a href="d.php"><i class="fas fa-hand-holding-usd"></i> donation</a>
        <div class="dropdown">
            <a href="#" class="dropdown-trigger"><i class="fas fa-ticket-alt"></i> booking</a>
            <div class="dropdown-content">
                <a href="booking.php"><i class="fas fa-plus"></i> New Booking</a>
                <a href="my-bookings.php"><i class="fas fa-list"></i> My Bookings</a>
            </div>
        </div>
        <a href="index.php#gallery"><i class="fas fa-images"></i> gallery</a>
        <a href="index.php#animal"><i class="fas fa-paw"></i> animal</a>
        <!-- <span  class="headin">Welcome, <?php echo htmlspecialchars($_SESSION['user']); ?></span> -->
        <span style="font-weight: bold; font-size: 16px;">
    Welcome, <?php echo htmlspecialchars($_SESSION['user']); ?>
</span>

    </nav>

    <div class="icons">
        <?php if(isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
            <div id="login-btn" class="fas fa-user">
                <form class="login-form">
                    
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

<style>
.navbar a {
    font-size: 1.7rem;
    color: var(--black);
    margin: 0 1rem;
    text-transform: capitalize;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
}

.navbar a i {
    font-size: 1.5rem;
    color: var(--main);
}

.navbar a:hover {
    color: var(--main);
    transform: translateY(-2px);
}

.navbar a:hover i {
    transform: scale(1.1);
}

@media (max-width: 768px) {
    .navbar a {
        display: block;
        margin: 1rem 0;
        text-align: left;
    }
}

.dropdown {
    position: relative;
    display: inline-block;
}

.dropdown-content {
    display: none;
    position: absolute;
    background-color: var(--white);
    min-width: 200px;
    box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    border-radius: 8px;
    z-index: 1;
    top: 100%;
    left: 0;
}

.dropdown:hover .dropdown-content {
    display: block;
}

.dropdown-content a {
    display: block;
    padding: 1rem 1.5rem;
    margin: 0;
    font-size: 1.5rem;
}

.dropdown-content a:first-child {
    border-radius: 8px 8px 0 0;
}

.dropdown-content a:last-child {
    border-radius: 0 0 8px 8px;
}

@media (max-width: 768px) {
    .dropdown-content {
        position: static;
        box-shadow: none;
        background-color: transparent;
    }
    
    .dropdown-content a {
        padding-left: 3rem;
    }
}
</style> 