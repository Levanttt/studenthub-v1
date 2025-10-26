<?php
include 'includes/config.php';
include 'includes/functions.php';

$is_logged_in = isLoggedIn();
$current_role = getUserRole();
$user_name = $_SESSION['name'] ?? 'User';
$profile_picture = $_SESSION['profile_picture'] ?? '';

// Mapping folder dashboard berdasarkan role
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
    <title>Informasi untuk Mitra Industri - Cakrawala Connect</title>
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
                           class="bg-cakrawala-primary text-white px-4 py-1.5 rounded-lg font-semibold hover:opacity-90 transition-opacity shadow-sm flex items-center gap-2 text-sm">
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
            <section class="bg-gradient-to-r from-[#F9A825] to-[#F57F17] text-white py-16">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <h1 class="text-4xl md:text-5xl font-bold mb-4">Informasi untuk Mitra Industri</h1>
                    <p class="text-xl text-amber-100 max-w-3xl mx-auto">
                        Akses Langsung ke Talenta Terbaik Universitas Cakrawala
                    </p>
                </div>
            </section>

            <!-- Content Section -->
            <section class="py-16">
                <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                    
                    <!-- Headline -->
                    <div class="text-center mb-12">
                        <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                            Temukan Talenta <span class="text-[#F9A825]">Siap Kerja</span> dengan Portofolio Terverifikasi
                        </h2>
                        <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                            Bergabunglah dengan jaringan mitra industri kami dan akses kumpulan talenta mahasiswa Universitas Cakrawala yang telah diverifikasi
                        </p>
                    </div>

                    <!-- Manfaat Bergabung -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 mb-12">
                        <h2 class="text-2xl font-bold text-[#2A8FA9] mb-6 flex items-center gap-3">
                            <span class="iconify" data-icon="mdi:star-circle" data-width="28"></span>
                            Mengapa Bergabung dengan Cakrawala Connect?
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="flex items-start gap-4 p-4 bg-amber-50 rounded-lg">
                                <div class="bg-[#F9A825] text-white p-2 rounded-lg mt-1">
                                    <span class="iconify" data-icon="mdi:account-check" data-width="20"></span>
                                </div>
                                <div>
                                    <h3 class="font-bold text-amber-900 text-lg mb-2">Talenta Eligible & Siap Kerja</h3>
                                    <p class="text-amber-700">Akses ke mahasiswa yang telah diverifikasi kelayakannya oleh Career Development Center</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4 p-4 bg-amber-50 rounded-lg">
                                <div class="bg-[#F9A825] text-white p-2 rounded-lg mt-1">
                                    <span class="iconify" data-icon="mdi:file-document" data-width="20"></span>
                                </div>
                                <div>
                                    <h3 class="font-bold text-amber-900 text-lg mb-2">Portofolio Berbasis Bukti</h3>
                                    <p class="text-amber-700">Lihat proyek nyata dan keterampilan yang relevan dengan kebutuhan industri</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4 p-4 bg-amber-50 rounded-lg">
                                <div class="bg-[#F9A825] text-white p-2 rounded-lg mt-1">
                                    <span class="iconify" data-icon="mdi:clock-fast" data-width="20"></span>
                                </div>
                                <div>
                                    <h3 class="font-bold text-amber-900 text-lg mb-2">Proses Rekrutmen Efisien</h3>
                                    <p class="text-amber-700">Hemat waktu dan sumber daya dengan pencarian talenta yang terarah</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4 p-4 bg-amber-50 rounded-lg">
                                <div class="bg-[#F9A825] text-white p-2 rounded-lg mt-1">
                                    <span class="iconify" data-icon="mdi:handshake" data-width="20"></span>
                                </div>
                                <div>
                                    <h3 class="font-bold text-amber-900 text-lg mb-2">Kemitraan dengan Kampus</h3>
                                    <p class="text-amber-700">Bangun hubungan strategis dengan Universitas Cakrawala dan Career Development Center</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fitur Utama -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 mb-12">
                        <h2 class="text-2xl font-bold text-[#2A8FA9] mb-6 flex items-center gap-3">
                            <span class="iconify" data-icon="mdi:feature-search" data-width="28"></span>
                            Fitur Utama untuk Mitra Industri
                        </h2>
                        <div class="space-y-4">
                            <div class="flex items-center gap-4 p-4 border border-gray-200 rounded-lg">
                                <div class="bg-blue-100 text-blue-600 p-3 rounded-lg">
                                    <span class="iconify" data-icon="mdi:filter" data-width="24"></span>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-900 text-lg">Pencarian & Filter Canggih</h3>
                                    <p class="text-gray-600">Temukan kandidat berdasarkan skill, jurusan, pengalaman, dan kriteria spesifik lainnya</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 p-4 border border-gray-200 rounded-lg">
                                <div class="bg-green-100 text-green-600 p-3 rounded-lg">
                                    <span class="iconify" data-icon="mdi:card-account-details" data-width="24"></span>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-900 text-lg">Profil Detail Mahasiswa</h3>
                                    <p class="text-gray-600">Akses informasi lengkap termasuk latar belakang pendidikan, proyek, dan pencapaian</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 p-4 border border-gray-200 rounded-lg">
                                <div class="bg-purple-100 text-purple-600 p-3 rounded-lg">
                                    <span class="iconify" data-icon="mdi:hammer-wrench" data-width="24"></span>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-900 text-lg">Lihat Proyek & Skill</h3>
                                    <p class="text-gray-600">Evaluasi kemampuan nyata melalui portofolio proyek yang telah diselesaikan</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 p-4 border border-gray-200 rounded-lg">
                                <div class="bg-orange-100 text-orange-600 p-3 rounded-lg">
                                    <span class="iconify" data-icon="mdi:contact-mail" data-width="24"></span>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-900 text-lg">Akses CV & Kontak</h3>
                                    <p class="text-gray-600">Dapatkan akses ke CV, LinkedIn, dan informasi kontak kandidat yang eligible</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cara Memulai -->
                    <div class="bg-gradient-to-r from-[#2A8FA9] to-[#409BB2] text-white rounded-2xl p-8">
                        <h2 class="text-2xl font-bold mb-8 text-center">Cara Memulai</h2>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <!-- Step 1 -->
                            <div class="bg-white/10 backdrop-blur-sm border border-white/20 rounded-xl p-6 text-center hover:bg-white/15 transition-all duration-300 hover-lift group">
                                <div class="bg-white/20 rounded-full w-14 h-14 mx-auto flex items-center justify-center font-bold text-xl mb-4 group-hover:scale-110 transition-transform duration-300">
                                    1
                                </div>
                                <h3 class="font-bold text-lg mb-2">Daftar</h3>
                                <p class="text-blue-100 text-sm leading-relaxed">Registrasi sebagai Mitra Industri di platform kami</p>
                                <div class="mt-4 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    <span class="iconify inline-block text-white/60" data-icon="mdi:account-plus" data-width="20"></span>
                                </div>
                            </div>

                            <!-- Step 2 -->
                            <div class="bg-white/10 backdrop-blur-sm border border-white/20 rounded-xl p-6 text-center hover:bg-white/15 transition-all duration-300 hover-lift group">
                                <div class="bg-white/20 rounded-full w-14 h-14 mx-auto flex items-center justify-center font-bold text-xl mb-4 group-hover:scale-110 transition-transform duration-300">
                                    2
                                </div>
                                <h3 class="font-bold text-lg mb-2">Verifikasi</h3>
                                <p class="text-blue-100 text-sm leading-relaxed">Proses verifikasi oleh tim Career Development Center</p>
                                <div class="mt-4 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    <span class="iconify inline-block text-white/60" data-icon="mdi:shield-check" data-width="20"></span>
                                </div>
                            </div>

                            <!-- Step 3 -->
                            <div class="bg-white/10 backdrop-blur-sm border border-white/20 rounded-xl p-6 text-center hover:bg-white/15 transition-all duration-300 hover-lift group">
                                <div class="bg-white/20 rounded-full w-14 h-14 mx-auto flex items-center justify-center font-bold text-xl mb-4 group-hover:scale-110 transition-transform duration-300">
                                    3
                                </div>
                                <h3 class="font-bold text-lg mb-2">Cari Talenta</h3>
                                <p class="text-blue-100 text-sm leading-relaxed">Eksplorasi database talenta dengan filter canggih</p>
                                <div class="mt-4 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    <span class="iconify inline-block text-white/60" data-icon="mdi:account-search" data-width="20"></span>
                                </div>
                            </div>

                            <!-- Step 4 -->
                            <div class="bg-white/10 backdrop-blur-sm border border-white/20 rounded-xl p-6 text-center hover:bg-white/15 transition-all duration-300 hover-lift group">
                                <div class="bg-white/20 rounded-full w-14 h-14 mx-auto flex items-center justify-center font-bold text-xl mb-4 group-hover:scale-110 transition-transform duration-300">
                                    4
                                </div>
                                <h3 class="font-bold text-lg mb-2">Hubungi</h3>
                                <p class="text-blue-100 text-sm leading-relaxed">Hubungi kandidat potensial untuk proses rekrutmen</p>
                                <div class="mt-4 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    <span class="iconify inline-block text-white/60" data-icon="mdi:message-text" data-width="20"></span>
                                </div>
                            </div>
                        </div>
                        <div class="text-center mt-8">
                            <a href="/cakrawala-connect/register.php" 
                            class="bg-[#F9A825] hover:bg-[#F57F17] text-white font-bold py-3 px-8 rounded-lg text-lg transition-all duration-300 transform hover:scale-105 inline-flex items-center gap-2 shadow-lg">
                                <span class="iconify" data-icon="mdi:rocket-launch" data-width="20"></span>
                                Daftar Sebagai Mitra Industri Sekarang
                            </a>
                        </div>
                    </div>

                </div>
            </section>
        </div>
    </main>

    <!-- Footer Manual -->
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
                        <!-- CDC Link -->
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
                    <a href="https://www.instagram.com/cakrawalauniversity/" 
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