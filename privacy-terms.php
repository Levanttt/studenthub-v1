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
$dashboard_url = $is_logged_in ? "/studenthub/dashboard/{$folder_name}/index.php" : "/studenthub/";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kebijakan Privasi & Syarat Ketentuan - Cakrawala Connect</title>
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
                <img src="/studenthub/assets/images/Logo Universitas Cakrawala1.png" 
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
                            <a href="/studenthub/dashboard/<?php echo $folder_name; ?>/profile.php"
                                class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                <span class="iconify" data-icon="mdi:account-cog" data-width="16"></span>
                                Profile Saya
                            </a>
                            <a href="/studenthub/dashboard/<?php echo $folder_name; ?>/index.php"
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
                        <a href="/studenthub/register.php" 
                           class="text-gray-600 hover:text-cakrawala-primary transition-colors text-sm flex items-center gap-1">
                            <span class="iconify" data-icon="mdi:account-plus" data-width="16"></span>
                            Daftar
                        </a>
                        <a href="/studenthub/login.php" 
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
            <section class="bg-gradient-to-r from-[#495057] to-[#6C757D] text-white py-16">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <h1 class="text-4xl md:text-5xl font-bold mb-4">Kebijakan Privasi & Syarat Ketentuan</h1>
                    <p class="text-xl text-gray-300 max-w-3xl mx-auto">
                        Informasi mengenai penggunaan data dan aturan platform Cakrawala Connect
                    </p>
                </div>
            </section>

            <!-- Content Section -->
            <section class="py-16">
                <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 space-y-12">
                        
                        <!-- Kebijakan Privasi -->
                        <div class="space-y-6">
                            <h2 class="text-3xl font-bold text-[#2A8FA9] border-b border-gray-200 pb-4">
                                Kebijakan Privasi
                            </h2>
                            
                            <!-- Pengantar -->
                            <div class="space-y-3">
                                <h3 class="text-xl font-semibold text-gray-900">Pengantar</h3>
                                <p class="text-gray-700 leading-relaxed">
                                    Bagian ini menjelaskan komitmen Cakrawala Connect dalam melindungi privasi dan data pribadi pengguna platform.
                                    Kami berdedikasi untuk memastikan keamanan informasi yang Anda percayakan kepada kami.
                                </p>
                            </div>

                            <!-- Data yang Dikumpulkan -->
                            <div class="space-y-3">
                                <h3 class="text-xl font-semibold text-gray-900">Data Apa yang Dikumpulkan</h3>
                                <p class="text-gray-700 leading-relaxed">
                                    Bagian ini menjelaskan data apa saja yang kami kumpulkan, termasuk informasi pribadi seperti nama, 
                                    alamat email, NIM, foto profil, serta informasi akademik dan profesional seperti proyek yang telah 
                                    diselesaikan, keterampilan, dan pengalaman.
                                </p>
                            </div>

                            <!-- Penggunaan Data -->
                            <div class="space-y-3">
                                <h3 class="text-xl font-semibold text-gray-900">Bagaimana Data Digunakan</h3>
                                <p class="text-gray-700 leading-relaxed">
                                    Data yang dikumpulkan digunakan untuk menampilkan profil pengguna di platform, melakukan analisis 
                                    internal untuk meningkatkan layanan, serta memfasilitasi koneksi antara mahasiswa dan mitra industri 
                                    yang terverifikasi.
                                </p>
                            </div>

                            <!-- Berbagi Data -->
                            <div class="space-y-3">
                                <h3 class="text-xl font-semibold text-gray-900">Berbagi Data</h3>
                                <p class="text-gray-700 leading-relaxed">
                                    Informasi profil mahasiswa dapat dilihat oleh mitra industri terverifikasi yang bergabung dengan 
                                    platform. Data sensitif tidak akan dibagikan tanpa persetujuan eksplisit dari pengguna.
                                </p>
                            </div>

                            <!-- Keamanan Data -->
                            <div class="space-y-3">
                                <h3 class="text-xl font-semibold text-gray-900">Keamanan Data</h3>
                                <p class="text-gray-700 leading-relaxed">
                                    Kami menerapkan berbagai upaya perlindungan data termasuk enkripsi, kontrol akses, dan pemantauan 
                                    keamanan untuk melindungi informasi pengguna dari akses tidak sah atau penyalahgunaan.
                                </p>
                            </div>

                            <!-- Hak Pengguna -->
                            <div class="space-y-3">
                                <h3 class="text-xl font-semibold text-gray-900">Hak Pengguna</h3>
                                <p class="text-gray-700 leading-relaxed">
                                    Pengguna memiliki hak untuk mengakses, memperbaiki, atau menghapus data pribadi mereka. Untuk 
                                    menggunakan hak-hak ini, pengguna dapat menghubungi tim administrasi platform.
                                </p>
                            </div>
                        </div>

                        <!-- Syarat & Ketentuan -->
                        <div class="space-y-6">
                            <h2 class="text-3xl font-bold text-[#2A8FA9] border-b border-gray-200 pb-4">
                                Syarat & Ketentuan
                            </h2>
                            
                            <!-- Pengantar -->
                            <div class="space-y-3">
                                <h3 class="text-xl font-semibold text-gray-900">Pengantar</h3>
                                <p class="text-gray-700 leading-relaxed">
                                    Dengan menggunakan platform Cakrawala Connect, Anda menyetujui syarat dan ketentuan yang 
                                    dijelaskan di bawah ini. Harap baca dengan seksama sebelum menggunakan layanan kami.
                                </p>
                            </div>

                            <!-- Aturan Penggunaan -->
                            <div class="space-y-3">
                                <h3 class="text-xl font-semibold text-gray-900">Aturan Penggunaan</h3>
                                <ul class="list-disc list-inside text-gray-700 space-y-2 ml-4">
                                    <li>Dilarang keras melakukan plagiarisme atau mengklaim karya orang lain sebagai milik sendiri</li>
                                    <li>Pengguna bertanggung jawab atas keakuratan informasi yang diunggah</li>
                                    <li>Dilarang mengunggah konten yang mengandung data palsu atau menyesatkan</li>
                                    <li>Pengguna harus menghormati hak cipta dan kekayaan intelektual pihak lain</li>
                                    <li>Dilarang menggunakan platform untuk kegiatan yang melanggar hukum</li>
                                </ul>
                            </div>

                            <!-- Tanggung Jawab Pengguna -->
                            <div class="space-y-3">
                                <h3 class="text-xl font-semibold text-gray-900">Tanggung Jawab Pengguna</h3>
                                <p class="text-gray-700 leading-relaxed">
                                    Pengguna bertanggung jawab penuh atas konten yang diunggah ke platform dan memastikan bahwa 
                                    semua informasi yang dibagikan adalah akurat dan tidak melanggar hak pihak ketiga.
                                </p>
                            </div>

                            <!-- Batasan Tanggung Jawab Platform -->
                            <div class="space-y-3">
                                <h3 class="text-xl font-semibold text-gray-900">Batasan Tanggung Jawab Platform</h3>
                                <p class="text-gray-700 leading-relaxed">
                                    Cakrawala Connect tidak bertanggung jawab atas kerugian yang timbul dari penggunaan platform, 
                                    termasuk namun tidak terbatas pada ketidakakuratan informasi yang diunggah oleh pengguna atau 
                                    interaksi antara pengguna platform.
                                </p>
                            </div>

                            <!-- Perubahan Syarat -->
                            <div class="space-y-3">
                                <h3 class="text-xl font-semibold text-gray-900">Perubahan Syarat</h3>
                                <p class="text-gray-700 leading-relaxed">
                                    Kami berhak mengubah syarat dan ketentuan ini kapan saja. Perubahan akan diumumkan melalui 
                                    platform dan pengguna diharapkan untuk secara berkala meninjau ketentuan yang berlaku.
                                </p>
                            </div>

                        </div>

                        <!-- Contact Information -->
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                            <h3 class="text-xl font-bold text-blue-900 mb-3">Pertanyaan atau Kekhawatiran?</h3>
                            <p class="text-blue-700">
                                Jika Anda memiliki pertanyaan mengenai Kebijakan Privasi atau Syarat & Ketentuan ini, 
                                silakan hubungi tim administrasi Cakrawala Connect melalui Career Development Center Universitas Cakrawala.
                            </p>
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
                        <img src="/studenthub/assets/images/Logo Universitas Cakrawala1.png" 
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
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3 mt-1"> 
                    <div>
                        <a href="/studenthub/about.php" 
                        class="inline-flex items-center text-[#2A8FA9] hover:text-[#409BB2] font-medium text-xs group transition-all duration-200"> 
                            Tentang Platform
                            <span class="iconify ml-1 transition-transform duration-200 group-hover:translate-x-1" 
                                data-icon="mdi:arrow-right" data-width="14"></span> 
                        </a>
                    </div>
                    <div>
                        <a href="/studenthub/for-partners.php" 
                        class="inline-flex items-center text-[#2A8FA9] hover:text-[#409BB2] font-medium text-xs group transition-all duration-200">
                            Untuk Mitra Industri
                            <span class="iconify ml-1 transition-transform duration-200 group-hover:translate-x-1" 
                                data-icon="mdi:arrow-right" data-width="14"></span>
                        </a>
                    </div>
                    <div>
                        <!-- CDC Link - Arahkan ke landing page utama -->
                        <a href="/studenthub/" 
                        class="inline-flex items-center text-[#2A8FA9] hover:text-[#409BB2] font-medium text-xs group transition-all duration-200">
                            Career Center (CDC)
                            <span class="iconify ml-1 transition-transform duration-200 group-hover:translate-x-1" 
                                data-icon="mdi:arrow-right" data-width="14"></span>
                        </a>
                    </div>
                    <div>
                        <a href="/studenthub/privacy-terms.php" 
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