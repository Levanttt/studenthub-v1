<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub v1.0</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.iconify.design/2/2.1.0/iconify.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .hover-lift:hover {
            transform: translateY(-2px);
        }
        
        .dropdown-menu {
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }
        
        .dropdown-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navbar -->
    <nav class="sticky top-0 z-50 bg-white shadow-sm border-b border-cyan-100 px-4 py-3">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <!-- Logo yang bisa diklik -->
            <a href="/studenthub/dashboard/<?php echo $_SESSION['role'] ?? ''; ?>/index.php" 
                class="text-xl font-bold text-blue-900 flex items-center gap-2 hover:text-blue-700 transition-colors">
                <span class="iconify" data-icon="mdi:school" data-width="28"></span>
                StudentHub
            </a>
            
            <!-- Navigation Links -->
            <div class="flex items-center space-x-4">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <!-- User Menu -->
                    <div class="relative" id="profile-dropdown">
                        <!-- Foto Profil -->
                        <button id="profile-toggle" class="flex items-center gap-2 p-1 rounded-full hover:bg-gray-100 transition-colors">
                            <?php if(!empty($_SESSION['profile_picture'])): ?>
                                <img src="<?php echo htmlspecialchars($_SESSION['profile_picture']); ?>" 
                                        alt="Profile" 
                                        class="w-8 h-8 rounded-full object-cover border-2 border-cyan-200 shadow-sm">
                            <?php else: ?>
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-cyan-400 to-blue-500 flex items-center justify-center shadow-sm">
                                    <span class="iconify text-white" data-icon="mdi:account" data-width="18"></span>
                                </div>
                            <?php endif; ?>
                            <span class="text-sm font-medium text-gray-700 hidden sm:block">
                                <?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?>
                            </span>
                            <span class="iconify text-gray-500" data-icon="mdi:chevron-down" data-width="16"></span>
                        </button>
                        
                        <!-- Dropdown Menu -->
                        <div class="dropdown-menu absolute right-0 mt-2 w-40 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
                            <a href="/studenthub/dashboard/<?php echo $_SESSION['role']; ?>/profile.php" 
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
                    <!-- Guest Menu -->
                    <div class="flex items-center space-x-3">
                        <a href="/studenthub/register.php" 
                            class="text-gray-600 hover:text-blue-600 transition-colors text-sm flex items-center gap-1">
                            <span class="iconify" data-icon="mdi:account-plus" data-width="16"></span>
                            Daftar
                        </a>
                        <a href="/studenthub/login.php" 
                            class="bg-cyan-500 text-white px-4 py-2 rounded-lg font-semibold hover:bg-cyan-600 transition-colors shadow-sm flex items-center gap-2 text-sm">
                            <span class="iconify" data-icon="mdi:login" data-width="16"></span>
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

// Konfirmasi Logout
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
                window.location.href = '/studenthub/logout.php';
            }
        });
    } else {
        if (confirm('Yakin ingin logout?')) {
            window.location.href = '/studenthub/logout.php';
        }
    }
}
</script>
</body>
</html>