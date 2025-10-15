<?php
include '../../includes/config.php';

if (!isLoggedIn() || getUserRole() != 'student') {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($_POST['name']);
    $university = sanitize($_POST['university']);
    $major = sanitize($_POST['major']);
    $bio = sanitize($_POST['bio']);
    $linkedin = sanitize($_POST['linkedin']);

    $stmt = $conn->prepare("UPDATE users SET name = ?, university = ?, major = ?, bio = ?, linkedin = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $name, $university, $major, $bio, $linkedin, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['name'] = $name;
        $success = "Profil berhasil diperbarui!";
    } else {
        $error = "Terjadi kesalahan: " . $conn->error;
    }
}

// Get current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
?>

<?php include '../../includes/header.php'; ?>

<div class="container">
    <div style="max-width: 800px; margin: 0 auto;">
        <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 2rem;">
            <h1 style="color: var(--primary-dark);">Edit Profil</h1>
            <a href="index.php" class="btn btn-outline">← Kembali ke Dashboard</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST" action="">
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Universitas</label>
                        <input type="text" name="university" class="form-control" value="<?php echo htmlspecialchars($user['university'] ?? ''); ?>">
                    </div>
                </div>

                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">Jurusan</label>
                        <input type="text" name="major" class="form-control" value="<?php echo htmlspecialchars($user['major'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">LinkedIn Profile</label>
                        <input type="url" name="linkedin" class="form-control" value="<?php echo htmlspecialchars($user['linkedin'] ?? ''); ?>" placeholder="https://linkedin.com/in/username">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Bio / Deskripsi Diri</label>
                    <textarea name="bio" class="form-control" rows="4" placeholder="Ceritakan tentang diri kamu, minat, dan tujuan karir..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Simpan Perubahan</button>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>