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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Email dan password wajib diisi!";
    } else {
        $stmt = $conn->prepare("SELECT id, email, password, role, name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['name'];
                
                // Redirect berdasarkan role
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
    <link rel="stylesheet" href="/studenthub/assets/css/style.css">
    <style>
        .auth-container {
            max-width: 400px;
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
    </style>
</head>
<body style="min-height: 100vh; display: flex; flex-direction: column;">
    <!-- Simple Header untuk Auth Pages -->
    <nav class="navbar">
        <div class="logo">StudentHub</div>
        <div class="nav-links">
            <a href="/studenthub/register.php">Daftar</a>
            <a href="/studenthub/login.php" class="btn btn-primary">Login</a>
        </div>
    </nav>

    <main style="flex: 1; display: flex; align-items: center; padding: 1rem;">
        <div class="auth-container">
            <div class="form-card">
                <div style="text-align: center; margin-bottom: 1.2rem;">
                    <h1 style="color: var(--primary-dark); margin-bottom: 0.3rem; font-size: 1.6rem;">Login StudentHub</h1>
                    <p style="color: var(--gray-dark); font-size: 0.85rem;">Masuk ke akun kamu</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error" style="padding: 0.6rem; margin-bottom: 0.8rem; font-size: 0.85rem;"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST" action="" class="compact-form">
                    <div class="form-group">
                        <label class="form-label" style="font-size: 0.8rem;">Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-size: 0.8rem;">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.3rem; padding: 0.7rem; font-size: 0.9rem;">Login</button>
                </form>

                <div style="text-align: center; margin-top: 1.2rem; padding-top: 0.8rem; border-top: 1px solid var(--gray-medium);">
                    <p style="color: var(--gray-dark); font-size: 0.8rem;">
                        Belum punya akun? <a href="/studenthub/register.php" style="color: var(--accent-cyan); text-decoration: none; font-weight: 600;">Daftar di sini</a>
                    </p>
                </div>
            </div>
        </div>
    </main>
</body>
</html>