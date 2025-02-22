<div class="sidebar">
    <ul class="sidebar-menu">
        <li class="menu-header">Dashboard</li>
        <li>
            <a href="admin_dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i> Overview
            </a>
        </li>

        <li class="menu-header">Animals</li>
        <li>
            <a href="add_animal.php" class="<?= basename($_SERVER['PHP_SELF']) == 'add_animal.php' ? 'active' : '' ?>">
                <i class="fas fa-plus"></i> Add Animal
            </a>
        </li>
        <li>
            <a href="view_animal.php" class="<?= basename($_SERVER['PHP_SELF']) == 'view_animal.php' ? 'active' : '' ?>">
                <i class="fas fa-list"></i> View Animals
            </a>
        </li>

        <li class="menu-header">Adoptions</li>
        <li>
            <a href="view_adoptions.php" class="<?= basename($_SERVER['PHP_SELF']) == 'view_adoptions.php' ? 'active' : '' ?>">
                <i class="fas fa-heart"></i> View Adoptions
            </a>
        </li>

        <li class="menu-header">Users</li>
        <li>
            <a href="view_users.php" class="<?= basename($_SERVER['PHP_SELF']) == 'view_users.php' ? 'active' : '' ?>">
                <i class="fas fa-users"></i> Manage Users
            </a>
        </li>

        <li class="menu-header">Settings</li>
        <li>
            <a href="profile.php" class="<?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>">
                <i class="fas fa-user-cog"></i> Profile
            </a>
        </li>
        <li>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </li>
    </ul>
</div>