<?php 
include 'includes/config.php';

// Redirect jika sudah login
if (isLoggedIn()) {
    if (getUserRole() == 'student') {
        header("Location: dashboard/student/");
    } else {
        header("Location: dashboard/stakeholder/");
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

    // Validasi
    if (empty($email) || empty($password) || empty($name)) {
        $error = "Semua field wajib diisi!";
    } elseif ($password !== $confirm_password) {
        $error = "Password tidak cocok!";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter!";
    } else {
        // Check jika email sudah ada
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $check_email->store_result();
        
        if ($check_email->num_rows > 0) {
            $error = "Email sudah terdaftar!";
        } else {
            // Hash password dan insert user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (email, password, role, name) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $email, $hashed_password, $role, $name);
            
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
    <link rel="stylesheet" href="/studenthub/assets/css/style.css">
    <style>
        .auth-container {
            max-width: 480px;
            width: 100%;
            margin: 0 auto;
        }
        
        .compact-form .form-group {
            margin-bottom: 0.8rem;
        }
        
        .compact-form .form-control {
            padding: 0.7rem;
            font-size: 0.9rem;
        }
        
        .form-card {
            padding: 1.5rem;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
            border-radius: 12px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.8rem;
        }
        
    </style>
</head>
<body style="min-height: 100vh; display: flex; flex-direction: column;">
    <!-- Simple Header untuk Auth Pages -->
    <nav class="navbar">
        <div class="logo">StudentHub</div>
        <div class="nav-links">
            <a href="/studenthub/register.php" class="btn btn-primary">Daftar</a>
            <a href="/studenthub/login.php">Login</a>
        </div>
    </nav>

    <main style="flex: 1; display: flex; align-items: center; padding: 1rem;">
        <div class="auth-container">
            <div class="form-card">
                <div style="text-align: center; margin-bottom: 1.2rem;">
                    <h1 style="color: var(--primary-dark); margin-bottom: 0.3rem; font-size: 1.6rem;">Daftar StudentHub</h1>
                    <p style="color: var(--gray-dark); font-size: 0.85rem;">Bergabung dengan komunitas talenta terbaik</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error" style="padding: 0.6rem; margin-bottom: 0.8rem; font-size: 0.85rem;"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success" style="padding: 0.6rem; margin-bottom: 0.8rem; font-size: 0.85rem;"><?php echo $success; ?></div>
                <?php endif; ?>

                <form method="POST" action="" class="compact-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" style="font-size: 0.8rem;">Nama Lengkap</label>
                            <input type="text" name="name" class="form-control" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="font-size: 0.8rem;">Email</label>
                            <input type="email" name="email" class="form-control" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-size: 0.8rem;">Role</label>
                        <select name="role" class="form-control form-select" required style="font-size: 0.9rem;">
                            <option value="">Pilih Role</option>
                            <option value="student" <?php echo (isset($_POST['role']) && $_POST['role'] == 'student') ? 'selected' : ''; ?>>Mahasiswa</option>
                            <option value="stakeholder" <?php echo (isset($_POST['role']) && $_POST['role'] == 'stakeholder') ? 'selected' : ''; ?>>Stakeholder</option>
                        </select>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" style="font-size: 0.8rem;">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="font-size: 0.8rem;">Konfirmasi Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.3rem; padding: 0.7rem; font-size: 0.9rem;">Daftar</button>
                </form>

                <div style="text-align: center; margin-top: 1.2rem; padding-top: 0.8rem; border-top: 1px solid var(--gray-medium);">
                    <p style="color: var(--gray-dark); font-size: 0.8rem;">
                        Sudah punya akun? <a href="/studenthub/login.php" style="color: var(--accent-cyan); text-decoration: none; font-weight: 600;">Login di sini</a>
                    </p>
                </div>
            </div>
        </div>
    </main>
</body>
</html>