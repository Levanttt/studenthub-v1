<?php
include 'includes/config.php';
include 'includes/functions.php';

$is_logged_in = isLoggedIn();
$current_role = getUserRole();
$user_name = $_SESSION['name'] ?? 'User';
$profile_picture = $_SESSION['profile_picture'] ?? '';

$role_folder_map = [
    'mitra_industri' => 'mitra-industri',
    'student' => 'student', 
    'admin' => 'admin'
];
$folder_name = $role_folder_map[$current_role] ?? $current_role;
$dashboard_url = $is_logged_in ? "/cakrawala-connect/dashboard/{$folder_name}/index.php" : "/cakrawala-connect/";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tentang Platform - Cakrawala Connect</title>
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
    <nav class="sticky top-0 z-50 bg-white shadow-sm border-b border-gray-200 px-4 py-2">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <!-- Logo -->
            <a href="<?php echo $dashboard_url; ?>"
                class="flex items-center gap-2 transition-opacity hover:opacity-80">
                <img src="/cakrawala-connect/assets/images/Logo Universitas Cakrawala1.png" 
                    alt="Logo Universitas Cakrawala"
                    class="h-6">
                <span class="text-lg font-bold text-cakrawala-primary hidden sm:inline-block">
                    Cakrawala Connect
                </span>
            </a>

            <!-- Right Side - Login/Profile -->
            <div class="flex items-center space-x-4">
                <?php if($is_logged_in): ?>
                    <div class="relative" id="profile-dropdown">
                        <button id="profile-toggle" class="flex items-center gap-2 p-1 rounded-full hover:bg-gray-100 transition-colors">
                            <?php if(!empty($profile_picture)): ?>
                                <img src="<?php echo htmlspecialchars($profile_picture); ?>"
                                    alt="Profile"
                                    class="w-8 h-8 rounded-full object-cover border-2 border-cakrawala-primary shadow-sm">
                            <?php else: ?>
                                <div class="w-8 h-8 rounded-full bg-cakrawala-primary flex items-center justify-center shadow-sm">
                                    <span class="iconify text-white" data-icon="mdi:account" data-width="18"></span>
                                </div>
                            <?php endif; ?>
                            <span class="text-sm font-medium text-gray-700 hidden sm:block">
                                <?php echo htmlspecialchars($user_name); ?>
                            </span>
                            <span class="iconify text-gray-500" data-icon="mdi:chevron-down" data-width="16"></span>
                        </button>

                        <div class="dropdown-menu absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
                            <a href="/cakrawala-connect/dashboard/<?php echo $folder_name; ?>/profile.php"
                                class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                <span class="iconify" data-icon="mdi:account-cog" data-width="16"></span>
                                Profile Saya
                            </a>
                            <a href="/cakrawala-connect/dashboard/<?php echo $folder_name; ?>/index.php"
                                class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                <span class="iconify" data-icon="mdi:view-dashboard" data-width="16"></span>
                                Dashboard
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
                        <a href="/cakrawala-connect/register.php" 
                            class="text-gray-600 hover:text-cakrawala-primary transition-colors text-sm flex items-center gap-1">
                            <span class="iconify" data-icon="mdi:account-plus" data-width="16"></span>
                            Daftar
                        </a>
                        <a href="/cakrawala-connect/login.php" 
                            class="bg-cakrawala-primary text-white px-4 py-2 rounded-lg font-semibold hover:opacity-90 transition-opacity shadow-sm flex items-center gap-2 text-sm">
                            <span class="iconify" data-icon="mdi:login" data-width="16"></span>
                            Login
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main>
        <div class="min-h-screen bg-gray-50">
            <!-- Header Section -->
            <section class="bg-gradient-to-r from-[#2A8FA9] to-[#409BB2] text-white py-16">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="text-center">
                        <h1 class="text-4xl md:text-5xl font-bold mb-4">Tentang Cakrawala Connect</h1>
                        <p class="text-xl text-blue-100 max-w-3xl mx-auto">
                            Platform portofolio resmi Universitas Cakrawala yang menghubungkan talenta mahasiswa dengan peluang industri
                        </p>
                    </div>
                </div>
            </section>

            <!-- Content Section -->
            <section class="py-16">
                <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 space-y-8">
                        <div class="space-y-4">
                            <h2 class="text-2xl font-bold text-[#2A8FA9] flex items-center gap-3">
                                <span class="iconify" data-icon="mdi:information" data-width="28"></span>
                                Apa itu Cakrawala Connect?
                            </h2>
                            <p class="text-gray-700 leading-relaxed">
                                Cakrawala Connect adalah platform portofolio digital berbasis bukti yang dikembangkan khusus untuk 
                                mahasiswa Universitas Cakrawala. Platform ini dirancang sebagai jembatan antara dunia akademik 
                                dan industri profesional, memungkinkan mahasiswa menunjukkan kemampuan nyata mereka melalui 
                                proyek-proyek yang telah diselesaikan.
                            </p>
                        </div>

                        <!-- Misi & Tujuan -->
                        <div class="space-y-4">
                            <h2 class="text-2xl font-bold text-[#2A8FA9] flex items-center gap-3">
                                <span class="iconify" data-icon="mdi:target" data-width="28"></span>
                                Misi & Tujuan
                            </h2>
                            <p class="text-gray-700 leading-relaxed">
                                Platform ini dibuat dengan misi utama untuk menjembatani kesenjangan antara talenta mahasiswa 
                                dan kebutuhan industri. Kami bertujuan mendukung Career Development Center (CDC) dalam memvalidasi 
                                skill mahasiswa serta memfasilitasi proses rekrutmen yang lebih efisien dan terarah.
                            </p>
                        </div>

                        <!-- Cara Kerja -->
                        <div class="space-y-4">
                            <h2 class="text-2xl font-bold text-[#2A8FA9] flex items-center gap-3">
                                <span class="iconify" data-icon="mdi:cogs" data-width="28"></span>
                                Cara Kerja Singkat
                            </h2>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="text-center p-4 bg-[#E0F2F7] rounded-lg">
                                    <div class="bg-[#2A8FA9] text-white p-3 rounded-full w-12 h-12 mx-auto mb-3 flex items-center justify-center">
                                        <span class="iconify" data-icon="mdi:account-school" data-width="24"></span>
                                    </div>
                                    <h3 class="font-bold text-[#2A8FA9] mb-2">Mahasiswa</h3>
                                    <p class="text-sm text-gray-600">Membuat portofolio dengan proyek dan skill nyata</p>
                                </div>
                                <div class="text-center p-4 bg-[#FFF8E1] rounded-lg">
                                    <div class="bg-[#F9A825] text-white p-3 rounded-full w-12 h-12 mx-auto mb-3 flex items-center justify-center">
                                        <span class="iconify" data-icon="mdi:shield-check" data-width="24"></span>
                                    </div>
                                    <h3 class="font-bold text-[#F9A825] mb-2">CDC Validasi</h3>
                                    <p class="text-sm text-gray-600">CDC memverifikasi eligibilitas dan kelayakan mahasiswa</p>
                                </div>
                                <div class="text-center p-4 bg-[#E8F5E8] rounded-lg">
                                    <div class="bg-green-600 text-white p-3 rounded-full w-12 h-12 mx-auto mb-3 flex items-center justify-center">
                                        <span class="iconify" data-icon="mdi:briefcase-search" data-width="24"></span>
                                    </div>
                                    <h3 class="font-bold text-green-600 mb-2">Mitra Cari</h3>
                                    <p class="text-sm text-gray-600">Mitra industri mencari dan merekrut talenta terverifikasi</p>
                                </div>
                            </div>
                        </div>

                        <!-- Untuk Siapa -->
                        <div class="space-y-4">
                            <h2 class="text-2xl font-bold text-[#2A8FA9] flex items-center gap-3">
                                <span class="iconify" data-icon="mdi:account-group" data-width="28"></span>
                                Untuk Siapa Platform Ini?
                            </h2>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <h3 class="font-bold text-blue-900 mb-2 flex items-center gap-2">
                                        <span class="iconify" data-icon="mdi:account-school" data-width="20"></span>
                                        Mahasiswa Cakrawala
                                    </h3>
                                    <p class="text-sm text-blue-700">Membangun portofolio profesional dan meningkatkan employability</p>
                                </div>
                                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                                    <h3 class="font-bold text-amber-900 mb-2 flex items-center gap-2">
                                        <span class="iconify" data-icon="mdi:office-building" data-width="20"></span>
                                        Mitra Industri
                                    </h3>
                                    <p class="text-sm text-amber-700">Menemukan talenta siap kerja dengan skill terverifikasi</p>
                                </div>
                                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                    <h3 class="font-bold text-green-900 mb-2 flex items-center gap-2">
                                        <span class="iconify" data-icon="mdi:account-tie" data-width="20"></span>
                                        Career Center (CDC)
                                    </h3>
                                    <p class="text-sm text-green-700">Memvalidasi dan memfasilitasi penempatan mahasiswa</p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </section>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-[#E0F2F7] border-t border-[#ABD0D8]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6"> 
            <div class="flex flex-col md:flex-row justify-between items-start gap-6"> 
                
                <!-- Logo & Address -->
                <div class="space-y-2"> 
                    <a href="<?php echo $dashboard_url; ?>" class="inline-flex items-center gap-2 mb-1 group"> 
                        <img src="/cakrawala-connect/assets/images/Logo Universitas Cakrawala1.png" 
                            alt="Logo Universitas Cakrawala" 
                            class="h-8 group-hover:opacity-80 transition-opacity"> 
                        <div class="flex flex-col leading-tight">
                            <span class="font-bold text-[#2A8FA9] text-base group-hover:opacity-80 transition-opacity">Cakrawala</span> 
                            <span class="font-bold text-[#2A8FA9] text-base group-hover:opacity-80 transition-opacity">Connect</span> 
                        </div>
                    </a>
                    <p class="text-xs text-[#495057] max-w-xs leading-relaxed"> 
                        Jl. Kemang Timur No.1, RT.14/RW.8, Pejaten Bar., Ps. Minggu, 
                        Kota Jakarta Selatan, DKI Jakarta 12510
                    </p>
                </div>

                <!-- Quick Links -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3 mt-5"> 
                    <div>
                        <a href="/cakrawala-connect/about.php" 
                        class="inline-flex items-center text-[#2A8FA9] hover:text-[#409BB2] font-medium text-xs group transition-all duration-200"> 
                            Tentang Platform
                            <span class="iconify ml-1 transition-transform duration-200 group-hover:translate-x-1" 
                                data-icon="mdi:arrow-right" data-width="14"></span> 
                        </a>
                    </div>
                    <div>
                        <a href="/cakrawala-connect/for-partners.php" 
                        class="inline-flex items-center text-[#2A8FA9] hover:text-[#409BB2] font-medium text-xs group transition-all duration-200">
                            Untuk Mitra Industri
                            <span class="iconify ml-1 transition-transform duration-200 group-hover:translate-x-1" 
                                data-icon="mdi:arrow-right" data-width="14"></span>
                        </a>
                    </div>
                    <div>
                        <a href="/cakrawala-connect/" 
                        class="inline-flex items-center text-[#2A8FA9] hover:text-[#409BB2] font-medium text-xs group transition-all duration-200">
                            Career Center (CDC)
                            <span class="iconify ml-1 transition-transform duration-200 group-hover:translate-x-1" 
                                data-icon="mdi:arrow-right" data-width="14"></span>
                        </a>
                    </div>
                    <div>
                        <a href="/cakrawala-connect/privacy-terms.php" 
                        class="inline-flex items-center text-[#2A8FA9] hover:text-[#409BB2] font-medium text-xs group transition-all duration-200">
                            Kebijakan Privasi
                            <span class="iconify ml-1 transition-transform duration-200 group-hover:translate-x-1" 
                                data-icon="mdi:arrow-right" data-width="14"></span>
                        </a>
                    </div>
                </div>

            </div>

            <!-- Bottom Section -->
            <div class="flex flex-col md:flex-row justify-between items-center text-xs text-[#495057] border-t border-[#ABD0D8] mt-6 pt-4"> <!-- text-sm jadi text-xs, mt-8 jadi mt-6, pt-6 jadi pt-4 -->
                <span>© <?php echo date('Y'); ?> Cakrawala Connect - Universitas Cakrawala. All Rights Reserved.</span>
                <div class="flex space-x-3 mt-3 md:mt-0"> 
                    <a href="https://www.tiktok.com/@cakrawalauniversity" 
                    class="text-[#495057] hover:text-[#2A8FA9] transition-colors p-1 rounded-full hover:bg-white/50"> 
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"> 
                            <path d="M19.589 6.686a4.793 4.793 0 0 1-3.77-4.245V2h-3.445v13.672a2.896 2.896 0 0 1-5.201 1.743l-.002-.001.002.001a2.895 2.895 0 0 1 3.183-4.51v-3.5a6.329 6.329 0 0 0-5.394 10.692 6.33 6.33 0 0 0 10.857-4.424V8.687a8.182 8.182 0 0 0 4.773 1.526V6.79a4.831 4.831 0 0 1-1.003-.104z"/>
                        </svg>
                    </a>
                    <a href="https://x.com/CakrawalaUniv" 
                    class="text-[#495057] hover:text-[#2A8FA9] transition-colors p-1 rounded-full hover:bg-white/50">
                        <span class="iconify" data-icon="mdi:twitter" data-width="16"></span> 
                    </a>
                    <a href="https://www.instagram.com/cdccakrawala/" 
                    class="text-[#495057] hover:text-[#2A8FA9] transition-colors p-1 rounded-full hover:bg-white/50">
                        <span class="iconify" data-icon="mdi:instagram" data-width="16"></span> 
                    </a>
                </div>
            </div>
        </div>
    </footer>

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