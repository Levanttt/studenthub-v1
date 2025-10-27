<?php
include 'includes/config.php';
include 'includes/functions.php';

if (isLoggedIn()) {
    $role = getUserRole();
    if ($role == 'student') {
        header("Location: dashboard/student/index.php");
    } elseif ($role == 'mitra_industri') {
        header("Location: dashboard/mitra-industri/index.php"); 
    } elseif ($role == 'admin') {
        header("Location: dashboard/admin/index.php");
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
        $stmt = $conn->prepare("SELECT id, email, password, role, name, profile_picture, verification_status FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                
                if ($user['role'] == 'mitra_industri' && $user['verification_status'] != 'verified') {
                    $status_text = $user['verification_status'] == 'pending' ? 'menunggu verifikasi' : 'ditolak';
                    $error = "Akun mitra industri Anda {$status_text}. Silakan tunggu verifikasi admin.";
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role']; 
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['profile_picture'] = $user['profile_picture'];

                    if ($user['role'] == 'student') {
                        header("Location: dashboard/student/index.php");
                    } elseif ($user['role'] == 'mitra_industri') { 
                        header("Location: dashboard/mitra-industri/index.php"); 
                    } elseif ($user['role'] == 'admin') {
                        header("Location: dashboard/admin/index.php"); 
                    } else {
                        session_destroy(); 
                        $error = "Role pengguna tidak dikenali.";
                    }
                    exit();
                }

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
    <title>Login - Cakrawala Connect</title> 
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.iconify.design/2/2.1.0/iconify.min.js"></script>

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
    </style>
</head>
<body class="bg-cakrawala-light-gray"> 
    <nav class="sticky top-0 z-50 bg-white shadow-sm border-b border-gray-200 px-4 py-3">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <a href="/cakrawala-connect/" class="flex items-center gap-2 transition-opacity hover:opacity-80">
                <img src="/cakrawala-connect/assets/images/Logo Universitas Cakrawala1.png" alt="Logo Universitas Cakrawala" class="h-6"> 
                <span class="text-lg font-bold text-cakrawala-primary hidden sm:inline">Cakrawala Connect</span> 
            </a>
            <div class="flex items-center space-x-3">
                <a href="/cakrawala-connect/register.php" class="text-gray-600 hover:text-cakrawala-primary transition-colors text-sm font-medium flex items-center gap-1">
                    <span class="iconify" data-icon="mdi:account-plus" data-width="16"></span>
                    Daftar
                </a>
                <a href="/cakrawala-connect/login.php" class="bg-cakrawala-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-cakrawala-primary-hover transition-colors shadow-sm flex items-center gap-2 text-sm">
                    <span class="iconify" data-icon="mdi:login" data-width="16"></span>
                    Login
                </a>
            </div>
        </div>
    </nav>

    <main class="auth-height flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="bg-gradient-to-r from-[var(--cakrawala-primary)] to-[var(--cakrawala-secondary)] p-6 text-center text-white">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-white/20 mb-3">
                        <span class="iconify text-white" data-icon="mdi:login" data-width="24"></span>
                    </div>
                    <h2 class="text-2xl font-bold">Login Cakrawala Connect</h2> 
                    <p class="mt-1 text-blue-100">Masuk ke akun Anda</p>
                </div>

                <div class="p-6">
                    <?php if (!empty($error)): ?>
                        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-3 py-2 rounded-lg flex items-center gap-2 text-sm">
                            <span class="iconify" data-icon="mdi:alert-circle" data-width="16"></span>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                    <span class="iconify" data-icon="mdi:email" data-width="18"></span>
                                </span>
                                <input type="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                        class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cakrawala-primary focus:border-cakrawala-primary transition-colors text-sm"
                                        placeholder="Masukkan email Anda" required>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                    <span class="iconify" data-icon="mdi:lock" data-width="18"></span>
                                </span>
                                <input type="password" name="password"
                                        class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cakrawala-primary focus:border-cakrawala-primary transition-colors text-sm"
                                        placeholder="Masukkan password Anda" required>
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-gradient-to-r from-[var(--cakrawala-primary)] to-[var(--cakrawala-secondary)] text-white py-3 px-4 rounded-lg font-semibold hover:opacity-90 transition-opacity duration-300 shadow-md flex items-center justify-center gap-2 text-sm mt-2">
                            <span class="iconify" data-icon="mdi:login" data-width="16"></span>
                            Login
                        </button>
                    </form>

                    <div class="mt-6 pt-4 border-t border-gray-200 text-center">
                        <p class="text-gray-600 text-sm">
                            Belum punya akun?
                            <a href="/cakrawala-connect/register.php" class="text-cakrawala-primary font-semibold hover:text-cakrawala-primary-hover transition-colors ml-1">
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