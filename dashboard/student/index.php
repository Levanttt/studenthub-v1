<?php
include '../../includes/config.php';

// Check if user is logged in and is student
if (!isLoggedIn() || getUserRole() != 'student') {
    header("Location: ../../login.php");
    exit();
}

// Get student data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

// Get student's projects count
$project_stmt = $conn->prepare("SELECT COUNT(*) as total_projects FROM projects WHERE student_id = ?");
$project_stmt->bind_param("i", $user_id);
$project_stmt->execute();
$project_result = $project_stmt->get_result();
$project_count = $project_result->fetch_assoc()['total_projects'];
?>

<?php include '../../includes/header.php'; ?>

<div class="container">
    <!-- Welcome Section -->
    <div class="dashboard-header" style="background: linear-gradient(135deg, var(--primary-medium) 0%, var(--accent-cyan) 100%); color: white; padding: 3rem 2rem; border-radius: 16px; margin-bottom: 2rem;">
        <div style="display: flex; justify-content: between; align-items: center; flex-wrap: wrap; gap: 2rem;">
            <div>
                <h1 style="font-size: 2.5rem; margin-bottom: 0.5rem;">Halo, <?php echo $student['name']; ?>! 👋</h1>
                <p style="font-size: 1.2rem; opacity: 0.9;">Selamat datang di dashboard StudentHub</p>
            </div>
            <div style="display: flex; gap: 1rem;">
                <a href="profile.php" class="btn" style="background: rgba(255,255,255,0.2); color: white; border: 2px solid white;">Edit Profil</a>
                <a href="add-project.php" class="btn" style="background: white; color: var(--primary-dark);">+ Tambah Proyek</a>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-3" style="margin-bottom: 3rem;">
        <div class="card" style="text-align: center;">
            <div style="font-size: 2.5rem; color: var(--accent-cyan); margin-bottom: 0.5rem;">📊</div>
            <h3 style="color: var(--primary-dark); margin-bottom: 0.5rem;"><?php echo $project_count; ?> Proyek</h3>
            <p style="color: var(--gray-dark);">Total proyek yang sudah diunggah</p>
        </div>
        
        <div class="card" style="text-align: center;">
            <div style="font-size: 2.5rem; color: var(--primary-medium); margin-bottom: 0.5rem;">👁️</div>
            <h3 style="color: var(--primary-dark); margin-bottom: 0.5rem;">0 Dilihat</h3>
            <p style="color: var(--gray-dark);">Profil kamu dilihat stakeholder</p>
        </div>
        
        <div class="card" style="text-align: center;">
            <div style="font-size: 2.5rem; color: var(--primary-light); margin-bottom: 0.5rem;">⭐</div>
            <h3 style="color: var(--primary-dark); margin-bottom: 0.5rem;">0 Suka</h3>
            <p style="color: var(--gray-dark);">Proyek kamu disukai</p>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <h2 style="color: var(--primary-dark); margin-bottom: 1.5rem;">Aksi Cepat</h2>
        <div class="grid grid-2">
            <a href="add-project.php" class="card" style="text-decoration: none; text-align: center; padding: 2rem; background: var(--gray-light); border: 2px dashed var(--gray-medium); transition: all 0.3s ease;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">➕</div>
                <h3 style="color: var(--primary-dark); margin-bottom: 0.5rem;">Tambah Proyek</h3>
                <p style="color: var(--gray-dark);">Upload proyek terbaru kamu</p>
            </a>
            
            <a href="projects.php" class="card" style="text-decoration: none; text-align: center; padding: 2rem; background: var(--gray-light); transition: all 0.3s ease;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📁</div>
                <h3 style="color: var(--primary-dark); margin-bottom: 0.5rem;">Kelola Proyek</h3>
                <p style="color: var(--gray-dark);">Lihat dan edit proyek kamu</p>
            </a>
        </div>
    </div>

    <!-- Recent Projects (akan diisi nanti) -->
    <div class="card" style="margin-top: 2rem;">
        <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 1.5rem;">
            <h2 style="color: var(--primary-dark);">Proyek Terbaru</h2>
            <a href="projects.php" class="btn btn-outline">Lihat Semua</a>
        </div>
        
        <?php if ($project_count > 0): ?>
            <p style="text-align: center; color: var(--gray-dark); padding: 2rem;">
                Proyek akan ditampilkan di sini. <a href="add-project.php" style="color: var(--accent-cyan);">Tambah proyek pertama kamu!</a>
            </p>
        <?php else: ?>
            <div style="text-align: center; padding: 3rem;">
                <div style="font-size: 4rem; margin-bottom: 1rem;">🎯</div>
                <h3 style="color: var(--primary-dark); margin-bottom: 1rem;">Belum Ada Proyek</h3>
                <p style="color: var(--gray-dark); margin-bottom: 2rem;">Mulai bangun portofolio kamu dengan menambahkan proyek pertama</p>
                <a href="add-project.php" class="btn btn-primary">+ Tambah Proyek Pertama</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>