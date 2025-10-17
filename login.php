<?php
include 'includes/config.php';

if (isLoggedIn()) {
    if (getUserRole() == 'student') {
        header("Location: dashboard/student/");
    } else {
        header("Location: dashboard/stakeholder/");
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Email dan password wajib diisi!";
    } else {
        $stmt = $conn->prepare("SELECT id, email, password, role, name, profile_picture FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['profile_picture'] = $user['profile_picture']; 
                
                if ($user['role'] == 'student') {
                    header("Location: dashboard/student/");
                } else {
                    header("Location: dashboard/stakeholder/");
                }
                exit();
            } else {
                $error = "Password salah!";
            }
        } else {
            $error = "Email tidak ditemukan!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - StudentHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.iconify.design/2/2.1.0/iconify.min.js"></script>
    <style>
        .auth-height {
            min-height: calc(100vh - 80px);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Compact Navbar -->
    <nav class="bg-white shadow-sm border-b border-cyan-100 px-4 py-3">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="text-xl font-bold text-blue-900">StudentHub</div>
            <div class="flex items-center space-x-3">
                <a href="/studenthub/register.php" class="text-gray-600 hover:text-blue-600 transition-colors text-sm">Daftar</a>
                <a href="/studenthub/login.php" class="bg-cyan-500 text-white px-3 py-1.5 rounded-lg font-semibold hover:bg-cyan-600 transition-colors shadow-sm flex items-center gap-1 text-sm">
                    <span class="iconify" data-icon="mdi:login" data-width="16"></span>
                    Login
                </a>
            </div>
        </div>
    </nav>

    <!-- Compact Main Content -->
    <main class="auth-height flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <!-- Header Card -->
                <div class="bg-gradient-to-r from-cyan-500 to-blue-600 p-6 text-center text-white">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-white/20 mb-3">
                        <span class="iconify text-white" data-icon="mdi:login" data-width="24"></span>
                    </div>
                    <h2 class="text-2xl font-bold">Login StudentHub</h2>
                    <p class="mt-1 text-cyan-100">Masuk ke akun kamu</p>
                </div>

                <!-- Form Content -->
                <div class="p-6">
                    <!-- Alert -->
                    <?php if (!empty($error)): ?>
                        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-3 py-2 rounded-lg flex items-center gap-2 text-sm">
                            <span class="iconify" data-icon="mdi:alert-circle" data-width="16"></span>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Compact Form -->
                    <form method="POST" action="" class="space-y-4">
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

                        <button type="submit" class="w-full bg-gradient-to-r from-cyan-500 to-blue-600 text-white py-3 px-4 rounded-lg font-semibold hover:from-cyan-600 hover:to-blue-700 transition-all duration-300 shadow-md flex items-center justify-center gap-2 text-sm mt-2">
                            <span class="iconify" data-icon="mdi:login" data-width="16"></span>
                            Login
                        </button>
                    </form>

                    <!-- Compact Register Link -->
                    <div class="mt-6 pt-4 border-t border-gray-200 text-center">
                        <p class="text-gray-600 text-sm">
                            Belum punya akun? 
                            <a href="/studenthub/register.php" class="text-cyan-600 font-semibold hover:text-cyan-700 transition-colors ml-1">
                                Daftar di sini
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>