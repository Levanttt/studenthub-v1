<?php
include 'includes/config.php';
// --- PERBAIKI INCLUDE functions.php JIKA DIPERLUKAN ---
// include 'includes/functions.php'; // Pastikan path ini benar jika file ada

// --- FUNGSI sanitize() jika belum ada di config.php ---
if (!function_exists('sanitize')) {
    function sanitize($input) {
        // Implementasi sanitasi sederhana, sesuaikan jika perlu
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}
if (!function_exists('getUserRole')) {
    function getUserRole() {
        return $_SESSION['role'] ?? null;
    }
}
// --- Akhir fungsi tambahan ---

if (isLoggedIn()) {
    $role = getUserRole();
    if ($role == 'student') {
        header("Location: dashboard/student/index.php");
    } elseif ($role == 'mitra_industri') { // <-- Ganti ke mitra_industri
        // Arahkan ke folder yang benar (meskipun namanya mungkin masih stakeholder)
        header("Location: dashboard/mitra-industri/index.php");
    } elseif ($role == 'admin') {
        header("Location: dashboard/admin/index.php");
    }
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = sanitize($_POST['role']);
    $name = sanitize($_POST['name']);

    // Mitra Industri fields
    $company_name = isset($_POST['company_name']) ? sanitize($_POST['company_name']) : '';
    $position = isset($_POST['position']) ? sanitize($_POST['position']) : '';
    $company_website = isset($_POST['company_website']) ? sanitize($_POST['company_website']) : '';

    // Basic validation
    if (empty($email) || empty($password) || empty($name) || empty($role)) {
        $error = "Semua field wajib diisi!";
    } elseif ($password !== $confirm_password) {
        $error = "Password tidak cocok!";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter!";
    }
    // --- GANTI PENGECEKAN ROLE ---
    elseif ($role == 'mitra_industri' && (empty($company_name) || empty($position))) {
        $error = "Untuk Mitra Industri, nama perusahaan dan jabatan wajib diisi!";
    } else {
        // Check if email already exists
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $check_email->store_result();

        if ($check_email->num_rows > 0) {
            $error = "Email sudah terdaftar!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert into users table
            $stmt = $conn->prepare("INSERT INTO users (email, password, role, name, company_name, position, company_website) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $email, $hashed_password, $role, $name, $company_name, $position, $company_website);

            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Registrasi berhasil! Silakan login.";
                header("Location: login.php");
                exit();
            } else {
                $error = "Terjadi kesalahan: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - Cakrawala Connect</title> <script src="https://cdn.tailwindcss.com"></script>
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
        .focus\:ring-cakrawala-primary:focus { --tw-ring-color: var(--cakrawala-primary); }
        .focus\:border-cakrawala-primary:focus { border-color: var(--cakrawala-primary); }

        .auth-height { min-height: calc(100vh - 64px); } 
        .fade-in { animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-cakrawala-light-gray"> <nav class="sticky top-0 z-50 bg-white shadow-sm border-b border-gray-200 px-4 py-2">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <a href="/studenthub/" class="flex items-center gap-2 transition-opacity hover:opacity-80">
                <img src="/studenthub/assets/images/Logo Universitas Cakrawala1.png" alt="Logo Universitas Cakrawala" class="h-8"> <span class="text-lg font-bold text-cakrawala-primary hidden sm:inline">Cakrawala Connect</span> </a>
            <div class="flex items-center space-x-3">
                <a href="/studenthub/register.php" class="bg-cakrawala-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-cakrawala-primary-hover transition-colors shadow-sm flex items-center gap-2 text-sm">
                    <span class="iconify" data-icon="mdi:account-plus" data-width="16"></span>
                    Daftar
                </a>
                <a href="/studenthub/login.php" class="text-gray-600 hover:text-cakrawala-primary transition-colors text-sm font-medium flex items-center gap-1">
                    <span class="iconify" data-icon="mdi:login" data-width="16"></span>
                    Login
                </a>
            </div>
        </div>
    </nav>

    <main class="auth-height flex items-center justify-center p-4">
        <div class="w-full max-w-lg">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="bg-gradient-to-r from-[var(--cakrawala-primary)] to-[var(--cakrawala-secondary)] p-6 text-center text-white">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-white/20 mb-3">
                        <span class="iconify text-white" data-icon="mdi:account-plus" data-width="24"></span>
                    </div>
                    <h2 class="text-2xl font-bold">Daftar Cakrawala Connect</h2> <p class="mt-1 text-blue-100">Bergabung dengan platform karir Universitas Cakrawala</p>
                </div>

                <div class="p-6">
                    <?php if (!empty($error)): ?>
                        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-3 py-2 rounded-lg flex items-center gap-2 text-sm">
                            <span class="iconify" data-icon="mdi:alert-circle" data-width="16"></span>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($success)): ?>
                        <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-3 py-2 rounded-lg flex items-center gap-2 text-sm">
                            <span class="iconify" data-icon="mdi:check-circle" data-width="16"></span>
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                        <span class="iconify" data-icon="mdi:account" data-width="18"></span>
                                    </span>
                                    <input type="text" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                                           class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cakrawala-primary focus:border-cakrawala-primary transition-colors text-sm"
                                           placeholder="Masukkan nama lengkap" required>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                        <span class="iconify" data-icon="mdi:email" data-width="18"></span>
                                    </span>
                                    <input type="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                           class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cakrawala-primary focus:border-cakrawala-primary transition-colors text-sm"
                                           placeholder="Masukkan email" required>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Daftar Sebagai *</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                    <span class="iconify" data-icon="mdi:account-cog" data-width="18"></span>
                                </span>
                                <select name="role" id="roleSelect" class="w-full pl-10 pr-10 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cakrawala-primary focus:border-cakrawala-primary transition-colors text-sm appearance-none bg-white" required>
                                    <option value="">Pilih Role Anda</option>
                                    <option value="student" <?php echo (isset($_POST['role']) && $_POST['role'] == 'student') ? 'selected' : ''; ?>>Mahasiswa</option>
                                    <option value="mitra_industri" <?php echo (isset($_POST['role']) && $_POST['role'] == 'mitra_industri') ? 'selected' : ''; ?>>Mitra Industri</option>
                                </select>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 pointer-events-none">
                                    <span class="iconify" data-icon="mdi:chevron-down" data-width="18"></span>
                                </span>
                            </div>
                        </div>

                        <div id="mitraFields" class="fade-in hidden space-y-4"> <div class="bg-cakrawala-secondary-light border border-[#ABD0D8] rounded-lg p-4"> <h3 class="text-sm font-semibold text-cakrawala-primary mb-3 flex items-center gap-2">
                                    <span class="iconify" data-icon="mdi:office-building" data-width="16"></span>
                                    Informasi Profesional Mitra
                                </h3>
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Nama Perusahaan <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                                <span class="iconify" data-icon="mdi:office-building" data-width="18"></span>
                                            </span>
                                            <input type="text" name="company_name"
                                                   value="<?php echo isset($_POST['company_name']) ? htmlspecialchars($_POST['company_name']) : ''; ?>"
                                                   class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cakrawala-primary focus:border-cakrawala-primary transition-colors text-sm"
                                                   placeholder="Nama perusahaan/organisasi Anda">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Jabatan/Posisi <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                                <span class="iconify" data-icon="mdi:badge-account" data-width="18"></span>
                                            </span>
                                            <input type="text" name="position"
                                                   value="<?php echo isset($_POST['position']) ? htmlspecialchars($_POST['position']) : ''; ?>"
                                                   class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cakrawala-primary focus:border-cakrawala-primary transition-colors text-sm"
                                                   placeholder="Contoh: HR Manager, Tech Lead">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Situs Web Perusahaan <span class="text-xs text-gray-500 font-normal">(Opsional)</span>
                                        </label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                                <span class="iconify" data-icon="mdi:web" data-width="18"></span>
                                            </span>
                                            <input type="url" name="company_website"
                                                   value="<?php echo isset($_POST['company_website']) ? htmlspecialchars($_POST['company_website']) : ''; ?>"
                                                   class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cakrawala-primary focus:border-cakrawala-primary transition-colors text-sm"
                                                   placeholder="https://perusahaananda.com">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                        <span class="iconify" data-icon="mdi:lock" data-width="18"></span>
                                    </span>
                                    <input type="password" name="password"
                                           class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cakrawala-primary focus:border-cakrawala-primary transition-colors text-sm"
                                           placeholder="Minimal 6 karakter" required>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Konfirmasi Password</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                        <span class="iconify" data-icon="mdi:lock-check" data-width="18"></span>
                                    </span>
                                    <input type="password" name="confirm_password"
                                           class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cakrawala-primary focus:border-cakrawala-primary transition-colors text-sm"
                                           placeholder="Ulangi password" required>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-gradient-to-r from-[var(--cakrawala-primary)] to-[var(--cakrawala-secondary)] text-white py-3 px-4 rounded-lg font-semibold hover:opacity-90 transition-opacity duration-300 shadow-md flex items-center justify-center gap-2 text-sm mt-2">
                            <span class="iconify" data-icon="mdi:account-plus" data-width="16"></span>
                            Daftar Sekarang
                        </button>
                    </form>

                    <div class="mt-6 pt-4 border-t border-gray-200 text-center">
                        <p class="text-gray-600 text-sm">
                            Sudah punya akun?
                            <a href="/studenthub/login.php" class="text-cakrawala-primary font-semibold hover:text-cakrawala-primary-hover transition-colors ml-1">
                                Login di sini
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const roleSelect = document.getElementById('roleSelect');
        const mitraFields = document.getElementById('mitraFields'); 

        function toggleMitraFields() { 
            if (roleSelect.value === 'mitra_industri') {
                mitraFields.classList.remove('hidden');

                // Add required attribute
                const companyName = document.querySelector('input[name="company_name"]');
                const position = document.querySelector('input[name="position"]');

                if (companyName) companyName.required = true;
                if (position) position.required = true;
            } else {
                mitraFields.classList.add('hidden');

                // Remove required attribute
                const companyName = document.querySelector('input[name="company_name"]');
                const position = document.querySelector('input[name="position"]');
                const companyWebsite = document.querySelector('input[name="company_website"]');

                if (companyName) companyName.required = false;
                if (position) position.required = false;
                if (companyWebsite) companyWebsite.required = false; 
            }
        }

        // Initial check
        toggleMitraFields(); // Ganti Nama Fungsi

        // Add event listener
        roleSelect.addEventListener('change', toggleMitraFields); // Ganti Nama Fungsi
    });
    </script>
</body>
</html>