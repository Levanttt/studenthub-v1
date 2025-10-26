<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cakrawala Connect - Universitas Cakrawala</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.iconify.design/2/2.1.0/iconify.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --cakrawala-primary: #2A8FA9; 
            --cakrawala-primary-hover: #409BB2;
            --cakrawala-secondary: #4CA1B6;
            --cakrawala-secondary-light: #E0F2F7;
            --cakrawala-cta: #F9A825;
            --cakrawala-cta-hover: #F57F17;
            --cakrawala-light-gray: #F1F3F5;
            --cakrawala-medium-gray: #CED4DA;
            --cakrawala-dark-gray: #495057;
            --cakrawala-text: #212529;
        }

        .bg-cakrawala-primary { background-color: var(--cakrawala-primary); }
        .text-cakrawala-primary { color: var(--cakrawala-primary); }
        .border-cakrawala-primary { border-color: var(--cakrawala-primary); }
        .hover\:bg-cakrawala-primary-hover:hover { background-color: var(--cakrawala-primary-hover); }

        .bg-cakrawala-secondary-light { background-color: var(--cakrawala-secondary-light); }
        .text-cakrawala-secondary { color: var(--cakrawala-secondary); }

        .bg-cakrawala-cta { background-color: var(--cakrawala-cta); }
        .hover\:bg-cakrawala-cta-hover:hover { background-color: var(--cakrawala-cta-hover); }

        .text-cakrawala-text { color: var(--cakrawala-text); }
        .text-cakrawala-dark-gray { color: var(--cakrawala-dark-gray); }

        .hover-lift:hover { transform: translateY(-2px); }

        .dropdown-menu {
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.25s ease;
        }

        .dropdown-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php
    $role_folder_map = [
        'mitra_industri' => 'mitra-industri',
        'student' => 'student', 
        'admin' => 'admin'
    ];

    $current_role = $_SESSION['role'] ?? '';
    $folder_name = $role_folder_map[$current_role] ?? $current_role;
    ?>

    <nav class="sticky top-0 z-50 bg-white shadow-sm border-b border-gray-200 px-4 py-3">
        <div class="max-w-7xl mx-auto flex justify-between items-center">

            <a href="/cakrawala-connect/dashboard/<?php echo $folder_name; ?>/index.php"
                class="flex items-center gap-2 transition-opacity hover:opacity-80">
                <img src="/cakrawala-connect/assets/images/Logo Universitas Cakrawala1.png" alt="Logo Universitas Cakrawala"
                    class="h-6">
                <span class="text-lg font-bold text-cakrawala-primary sm:hidden lg:inline-block">
                    Cakrawala Connect
                </span>
            </a>

            <div class="flex items-center space-x-4">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <div class="relative" id="profile-dropdown">
                        <button id="profile-toggle" class="flex items-center gap-2 p-1 rounded-full hover:bg-gray-100 transition-colors">
                            <?php if(!empty($_SESSION['profile_picture'])): ?>
                                <img src="<?php echo htmlspecialchars($_SESSION['profile_picture']); ?>"
                                        alt="Profile"
                                        class="w-8 h-8 rounded-full object-cover border-2 border-cakrawala-primary shadow-sm">
                            <?php else: ?>
                                <div class="w-8 h-8 rounded-full bg-cakrawala-primary flex items-center justify-center shadow-sm">
                                    <span class="iconify text-white" data-icon="mdi:account" data-width="18"></span>
                                </div>
                            <?php endif; ?>
                            <span class="text-sm font-medium text-gray-700 sm:hidden lg:inline-block">
                                <?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?>
                            </span>
                            <span class="iconify text-gray-500" data-icon="mdi:chevron-down" data-width="16"></span>
                        </button>

                        <div class="dropdown-menu absolute right-0 mt-2 w-40 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
                            <a href="/cakrawala-connect/dashboard/<?php echo $folder_name; ?>/profile.php"
                                class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                <span class="iconify" data-icon="mdi:account-cog" data-width="16"></span>
                                Profile
                            </a>
                            <hr class="my-1">
                            <button onclick="confirmLogout()"
                                    class="w-full text-left flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-red-50 hover:text-red-600 transition-colors">
                                <span class="iconify" data-icon="mdi:logout" data-width="16"></span>
                                Logout
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="flex items-center space-x-3">
                        <a href="/cakrawala-connect/register.php" class="text-gray-600 hover:text-cakrawala-primary transition-colors text-sm flex items-center gap-1">
                            <span class="iconify" data-icon="mdi:account-plus" data-width="18"></span>
                            Daftar
                        </a>
                        <a href="/cakrawala-connect/login.php" class="bg-cakrawala-primary text-white px-4 py-1.5 rounded-lg font-semibold hover:opacity-90 transition-opacity shadow-sm flex items-center gap-2 text-sm">
                            <span class="iconify" data-icon="mdi:login" data-width="18"></span>
                            Login
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main>
    </main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const profileToggle = document.getElementById('profile-toggle');
    const dropdownMenu = document.querySelector('.dropdown-menu');

    if (profileToggle && dropdownMenu) {
        profileToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdownMenu.classList.toggle('show');
        });

        document.addEventListener('click', function() {
            dropdownMenu.classList.remove('show');
        });

        dropdownMenu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
});

function confirmLogout() {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Yakin ingin logout?',
            text: "Anda akan keluar dari sesi saat ini",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, Logout!',
            cancelButtonText: 'Batal',
            background: '#ffffff'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '/cakrawala-connect/logout.php';
            }
        });
    } else {
        if (confirm('Yakin ingin logout?')) {
            window.location.href = '/cakrawala-connect/logout.php';
        }
    }
}
</script>
</body>
</html>