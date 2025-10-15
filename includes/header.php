<?php
include 'config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub v1.0</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.iconify.design/2/2.1.0/iconify.min.js"></script>
    <style>
        .hover-lift:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navbar -->
    <nav class="bg-white shadow-sm border-b border-cyan-100 px-4 py-3">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="text-xl font-bold text-blue-900">StudentHub</div>
            <div class="flex items-center space-x-3">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="/studenthub/dashboard/<?php echo $_SESSION['role']; ?>/profile.php" 
                       class="text-gray-600 hover:text-blue-600 transition-colors duration-300 p-2 rounded-lg hover:bg-blue-50 flex items-center gap-1 text-sm">
                       <span class="iconify" data-icon="mdi:account-circle" data-width="20"></span>
                       Profile
                    </a>
                <?php else: ?>
                    <a href="/studenthub/register.php" class="text-gray-600 hover:text-blue-600 transition-colors text-sm">Daftar</a>
                    <a href="/studenthub/login.php" class="bg-cyan-500 text-white px-3 py-1.5 rounded-lg font-semibold hover:bg-cyan-600 transition-colors shadow-sm flex items-center gap-1 text-sm">
                        <span class="iconify" data-icon="mdi:login" data-width="16"></span>
                        Login
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <main>