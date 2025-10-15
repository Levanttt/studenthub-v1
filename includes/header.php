<?php
// Include config sekali saja
include 'config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub v1.0</title>
    <link rel="stylesheet" href="/studenthub/assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="logo">StudentHub</div>
        <div class="nav-links">
            <a href="/studenthub/index.php">Beranda</a>
            <?php if(isset($_SESSION['user_id'])): ?>
                <span style="color: var(--primary-medium); font-weight: 600;">Halo, <?php echo $_SESSION['name']; ?>!</span>
                <?php if($_SESSION['role'] == 'student'): ?>
                    <a href="/studenthub/dashboard/student/">Dashboard</a>
                    <a href="/studenthub/dashboard/student/profile.php">Profil</a>
                <?php else: ?>
                    <a href="/studenthub/dashboard/stakeholder/">Dashboard</a>
                <?php endif; ?>
                <a href="/studenthub/logout.php">Logout</a>
            <?php else: ?>
                <a href="/studenthub/login.php">Login</a>
                <a href="/studenthub/register.php" class="btn btn-primary">Daftar</a>
            <?php endif; ?>
        </div>
    </nav>
    <main>