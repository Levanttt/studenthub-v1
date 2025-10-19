<?php 
include 'includes/config.php';

if (isLoggedIn()) {
    if (getUserRole() == 'student') {
        header("Location: dashboard/student/index.php");
    } else {
        header("Location: dashboard/stakeholder/index.php");
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
    
    // Stakeholder fields
    $company_name = isset($_POST['company_name']) ? sanitize($_POST['company_name']) : '';
    $position = isset($_POST['position']) ? sanitize($_POST['position']) : '';
    $company_website = isset($_POST['company_website']) ? sanitize($_POST['company_website']) : '';

    // Basic validation
    if (empty($email) || empty($password) || empty($name)) {
        $error = "Semua field wajib diisi!";
    } elseif ($password !== $confirm_password) {
        $error = "Password tidak cocok!";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter!";
    } elseif ($role == 'stakeholder' && (empty($company_name) || empty($position))) {
        $error = "Untuk stakeholder, nama perusahaan dan jabatan wajib diisi!";
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
            
            // Insert into users table with stakeholder fields
            $stmt = $conn->prepare("INSERT INTO users (email, password, role, name, company_name, position, company_website) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $email, $hashed_password, $role, $name, $company_name, $position, $company_website);
            
            if ($stmt->execute()) {
                $success = "Registrasi berhasil! Silakan login.";
                header("refresh:2;url=login.php");
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
    <title>Daftar - StudentHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.iconify.design/2/2.1.0/iconify.min.js"></script>
    <style>
        .auth-height {
            min-height: calc(100vh - 80px);
        }
        .fade-in {
            animation: fadeIn 0.3s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Compact Navbar -->
    <nav class="bg-white shadow-sm border-b border-cyan-100 px-4 py-3">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <a href="/studenthub/" 
                class="text-xl font-bold text-blue-900 flex items-center gap-2 hover:text-blue-700 transition-colors">
                <span class="iconify" data-icon="mdi:school" data-width="28"></span>
                StudentHub
            </a>
            <div class="flex items-center space-x-3">
                <a href="/studenthub/register.php" class="bg-cyan-500 text-white px-3 py-1.5 rounded-lg font-semibold hover:bg-cyan-600 transition-colors shadow-sm flex items-center gap-1 text-sm">
                    <span class="iconify" data-icon="mdi:account-plus" data-width="16"></span>
                    Daftar
                </a>
                <a href="/studenthub/login.php" class="text-gray-600 hover:text-blue-600 transition-colors text-sm">Login</a>
            </div>
        </div>
    </nav>

    <!-- Compact Main Content -->
    <main class="auth-height flex items-center justify-center p-4">
        <div class="w-full max-w-lg">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <!-- Header Card -->
                <div class="bg-gradient-to-r from-cyan-500 to-blue-600 p-6 text-center text-white">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-white/20 mb-3">
                        <span class="iconify text-white" data-icon="mdi:account-plus" data-width="24"></span>
                    </div>
                    <h2 class="text-2xl font-bold">Daftar StudentHub</h2>
                    <p class="mt-1 text-cyan-100">Bergabung dengan komunitas talenta terbaik</p>
                </div>

                <!-- Form Content -->
                <div class="p-6">
                    <!-- Alerts -->
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

                    <!-- Compact Form -->
                    <form method="POST" action="" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                        <span class="iconify" data-icon="mdi:account" data-width="18"></span>
                                    </span>
                                    <input type="text" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                           class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors text-sm" 
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
                                           class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors text-sm" 
                                           placeholder="Masukkan email" required>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                    <span class="iconify" data-icon="mdi:account-cog" data-width="18"></span>
                                </span>
                                <select name="role" id="roleSelect" class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors text-sm appearance-none" required>
                                    <option value="">Pilih Role</option>
                                    <option value="student" <?php echo (isset($_POST['role']) && $_POST['role'] == 'student') ? 'selected' : ''; ?>>Mahasiswa</option>
                                    <option value="stakeholder" <?php echo (isset($_POST['role']) && $_POST['role'] == 'stakeholder') ? 'selected' : ''; ?>>Stakeholder</option>
                                </select>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 pointer-events-none">
                                    <span class="iconify" data-icon="mdi:chevron-down" data-width="18"></span>
                                </span>
                            </div>
                        </div>

                        <!-- Stakeholder Additional Fields -->
                        <div id="stakeholderFields" class="fade-in hidden space-y-4">
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
                                           class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors text-sm" 
                                           placeholder="Nama perusahaan/organisasi">
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
                                           class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors text-sm" 
                                           placeholder="Contoh: HR Manager, Tech Lead, Founder">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Situs Web Perusahaan
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                        <span class="iconify" data-icon="mdi:web" data-width="18"></span>
                                    </span>
                                    <input type="url" name="company_website" 
                                           value="<?php echo isset($_POST['company_website']) ? htmlspecialchars($_POST['company_website']) : ''; ?>"
                                           class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors text-sm" 
                                           placeholder="https://perusahaananda.com">
                                </div>
                                <p class="text-xs text-gray-500 mt-1">
                                    Opsional, membantu verifikasi perusahaan Anda
                                </p>
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
                                           class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors text-sm" 
                                           placeholder="Masukkan password" required>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Konfirmasi Password</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                        <span class="iconify" data-icon="mdi:lock-check" data-width="18"></span>
                                    </span>
                                    <input type="password" name="confirm_password" 
                                           class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors text-sm" 
                                           placeholder="Konfirmasi password" required>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-gradient-to-r from-cyan-500 to-blue-600 text-white py-3 px-4 rounded-lg font-semibold hover:from-cyan-600 hover:to-blue-700 transition-all duration-300 shadow-md flex items-center justify-center gap-2 text-sm mt-2">
                            <span class="iconify" data-icon="mdi:account-plus" data-width="16"></span>
                            Daftar
                        </button>
                    </form>

                    <!-- Compact Login Link -->
                    <div class="mt-6 pt-4 border-t border-gray-200 text-center">
                        <p class="text-gray-600 text-sm">
                            Sudah punya akun? 
                            <a href="/studenthub/login.php" class="text-cyan-600 font-semibold hover:text-cyan-700 transition-colors ml-1">
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
        const stakeholderFields = document.getElementById('stakeholderFields');
        
        function toggleStakeholderFields() {
            if (roleSelect.value === 'stakeholder') {
                stakeholderFields.classList.remove('hidden');
                
                // Add required attribute to stakeholder fields
                const companyName = document.querySelector('input[name="company_name"]');
                const position = document.querySelector('input[name="position"]');
                
                if (companyName) companyName.required = true;
                if (position) position.required = true;
            } else {
                stakeholderFields.classList.add('hidden');
                
                // Remove required attribute from stakeholder fields
                const companyName = document.querySelector('input[name="company_name"]');
                const position = document.querySelector('input[name="position"]');
                const companyWebsite = document.querySelector('input[name="company_website"]');
                
                if (companyName) companyName.required = false;
                if (position) position.required = false;
                if (companyWebsite) companyWebsite.required = false;
            }
        }
        
        // Initial check
        toggleStakeholderFields();
        
        // Add event listener
        roleSelect.addEventListener('change', toggleStakeholderFields);
    });
    </script>
</body>
</html>