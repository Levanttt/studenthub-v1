<?php
include '../../includes/config.php';

if (!isLoggedIn() || getUserRole() != 'student') {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get student data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

// Get project count
$project_stmt = $conn->prepare("SELECT COUNT(*) as total_projects FROM projects WHERE student_id = ?");
$project_stmt->bind_param("i", $user_id);
$project_stmt->execute();
$project_result = $project_stmt->get_result();
$project_count = $project_result->fetch_assoc()['total_projects'];

// Get profile views (placeholder - implement later)
$total_views = 0;

// Get total likes (placeholder - implement later)
$total_likes = 0;

// Get recent projects
$recent_projects_stmt = $conn->prepare("SELECT * FROM projects WHERE student_id = ? ORDER BY created_at DESC LIMIT 3");
$recent_projects_stmt->bind_param("i", $user_id);
$recent_projects_stmt->execute();
$recent_projects_result = $recent_projects_stmt->get_result();
?>

<?php include '../../includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-gradient-to-r from-blue-800 to-blue-600 text-white rounded-2xl p-8 mb-8 shadow-lg">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
            <div class="flex-1">
                <h1 class="text-3xl lg:text-4xl font-bold mb-3 flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:hand-wave" data-width="40"></span>
                    Halo, <?php echo htmlspecialchars($student['name']); ?>!
                </h1>
                <p class="text-blue-100 text-lg opacity-90">Selamat datang di dashboard StudentHub - platform untuk menunjukkan kemampuan nyata kamu!</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3">
                <a href="profile.php" class="bg-white/20 text-white px-6 py-3 rounded-xl border border-white/30 hover:bg-white/30 transition-all duration-300 font-semibold text-center flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:account-edit" data-width="20"></span>
                    Edit Profil
                </a>
                <a href="add-project.php" class="bg-white text-blue-800 px-6 py-3 rounded-xl font-bold hover:bg-blue-50 transition-all duration-300 shadow-sm text-center flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:plus-circle" data-width="20"></span>
                    Tambah Proyek
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-2xl p-6 text-center shadow-sm border border-gray-100 hover:shadow-md transition-all duration-300 hover-lift">
            <div class="text-cyan-500 mb-3 flex justify-center">
                <span class="iconify" data-icon="mdi:chart-box" data-width="48"></span>
            </div>
            <h3 class="text-blue-900 font-bold text-2xl mb-2"><?php echo $project_count; ?></h3>
            <p class="text-gray-600 font-medium">Total Proyek</p>
            <p class="text-gray-500 text-sm mt-1">Yang sudah diunggah</p>
        </div>
        
        <!-- Profile Views -->
        <div class="bg-white rounded-2xl p-6 text-center shadow-sm border border-gray-100 hover:shadow-md transition-all duration-300 hover-lift">
            <div class="text-blue-500 mb-3 flex justify-center">
                <span class="iconify" data-icon="mdi:eye" data-width="48"></span>
            </div>
            <h3 class="text-blue-900 font-bold text-2xl mb-2"><?php echo $total_views; ?></h3>
            <p class="text-gray-600 font-medium">Profil Dilihat</p>
            <p class="text-gray-500 text-sm mt-1">Oleh stakeholder</p>
        </div>
        
        <!-- Total Likes -->
        <div class="bg-white rounded-2xl p-6 text-center shadow-sm border border-gray-100 hover:shadow-md transition-all duration-300 hover-lift">
            <div class="text-blue-400 mb-3 flex justify-center">
                <span class="iconify" data-icon="mdi:star" data-width="48"></span>
            </div>
            <h3 class="text-blue-900 font-bold text-2xl mb-2"><?php echo $total_likes; ?></h3>
            <p class="text-gray-600 font-medium">Suka</p>
            <p class="text-gray-500 text-sm mt-1">Proyek kamu disukai</p>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 mb-8">
        <h2 class="text-2xl font-bold text-blue-900 mb-6 flex items-center gap-2">
            <span class="iconify" data-icon="mdi:lightning-bolt" data-width="28"></span>
            Aksi Cepat
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Add Project -->
            <a href="add-project.php" class="group bg-gradient-to-br from-cyan-50 to-blue-50 rounded-2xl p-6 text-center border-2 border-cyan-100 hover:border-cyan-300 transition-all duration-300 transform hover:scale-[1.02]">
                <div class="text-cyan-500 mb-3 group-hover:scale-110 transition-transform duration-300 flex justify-center">
                    <span class="iconify" data-icon="mdi:plus-box" data-width="48"></span>
                </div>
                <h3 class="text-blue-900 font-semibold mb-1">Tambah Proyek</h3>
                <p class="text-gray-600 text-sm">Upload proyek terbaru</p>
            </a>
            
            <!-- Edit Profile -->
            <a href="profile.php" class="group bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl p-6 text-center border-2 border-blue-100 hover:border-blue-300 transition-all duration-300 transform hover:scale-[1.02]">
                <div class="text-blue-500 mb-3 group-hover:scale-110 transition-transform duration-300 flex justify-center">
                    <span class="iconify" data-icon="mdi:account-edit" data-width="48"></span>
                </div>
                <h3 class="text-blue-900 font-semibold mb-1">Edit Profil</h3>
                <p class="text-gray-600 text-sm">Perbarui informasi</p>
            </a>
            
            <!-- Manage Projects -->
            <a href="projects.php" class="group bg-gradient-to-br from-green-50 to-emerald-50 rounded-2xl p-6 text-center border-2 border-green-100 hover:border-green-300 transition-all duration-300 transform hover:scale-[1.02]">
                <div class="text-green-500 mb-3 group-hover:scale-110 transition-transform duration-300 flex justify-center">
                    <span class="iconify" data-icon="mdi:folder-open" data-width="48"></span>
                </div>
                <h3 class="text-blue-900 font-semibold mb-1">Kelola Proyek</h3>
                <p class="text-gray-600 text-sm">Lihat dan edit proyek</p>
            </a>
            
            <!-- Skills Management -->
            <a href="skills.php" class="group bg-gradient-to-br from-teal-50 to-cyan-50 rounded-2xl p-6 text-center border-2 border-teal-100 hover:border-teal-300 transition-all duration-300 transform hover:scale-[1.02]">
                <div class="text-teal-500 mb-3 group-hover:scale-110 transition-transform duration-300 flex justify-center">
                    <span class="iconify" data-icon="mdi:tag-multiple" data-width="48"></span>
                </div>
                <h3 class="text-blue-900 font-semibold mb-1">Kelola Skill</h3>
                <p class="text-gray-600 text-sm">Tambah keterampilan</p>
            </a>
        </div>
    </div>

    <!-- Recent Projects -->
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <h2 class="text-2xl font-bold text-blue-900 flex items-center gap-2">
                <span class="iconify" data-icon="mdi:clock" data-width="28"></span>
                Proyek Terbaru
            </h2>
            <?php if ($project_count > 0): ?>
                <a href="projects.php" class="bg-gray-100 text-gray-700 px-6 py-2 rounded-xl font-semibold hover:bg-gray-200 transition-colors duration-300 border border-gray-200 flex items-center gap-2">
                    Lihat Semua
                    <span class="iconify" data-icon="mdi:chevron-right" data-width="20"></span>
                </a>
            <?php endif; ?>
        </div>
        
        <?php if ($project_count == 0): ?>
            <!-- Empty State - No Projects -->
            <div class="text-center py-12">
                <div class="text-cyan-500 mb-4 flex justify-center">
                    <span class="iconify" data-icon="mdi:folder-open-outline" data-width="80"></span>
                </div>
                <h3 class="text-blue-900 font-bold text-xl mb-2">Belum Ada Proyek</h3>
                <p class="text-gray-600 mb-6">Mulai bangun portofolio kamu dengan menambahkan proyek pertama</p>
                <a href="add-project.php" class="bg-cyan-500 text-white px-8 py-3 rounded-xl font-bold hover:bg-cyan-600 transition-colors duration-300 inline-flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:rocket-launch" data-width="20"></span>
                    Tambah Proyek Pertama
                </a>
            </div>
        <?php else: ?>
            <!-- Projects Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php while($project = $recent_projects_result->fetch_assoc()): ?>
                <div class="bg-gray-50 rounded-xl p-6 border border-gray-200 hover:shadow-md transition-all duration-300 group">
                    <div class="flex items-start justify-between mb-4">
                        <h3 class="font-bold text-blue-900 text-lg group-hover:text-cyan-600 transition-colors"><?php echo htmlspecialchars($project['title']); ?></h3>
                        <span class="iconify text-gray-400 group-hover:text-cyan-500 transition-colors" data-icon="mdi:arrow-top-right" data-width="20"></span>
                    </div>
                    
                    <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?php echo htmlspecialchars($project['description']); ?></p>
                    
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500"><?php echo date('d M Y', strtotime($project['created_at'])); ?></span>
                        <a href="project-detail.php?id=<?php echo $project['id']; ?>" class="text-cyan-600 hover:text-cyan-700 font-medium flex items-center gap-1">
                            Lihat Detail
                            <span class="iconify" data-icon="mdi:chevron-right" data-width="16"></span>
                        </a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            
            <?php if ($project_count > 3): ?>
                <div class="text-center mt-8">
                    <a href="projects.php" class="bg-cyan-500 text-white px-8 py-3 rounded-xl font-semibold hover:bg-cyan-600 transition-colors duration-300 inline-flex items-center gap-2">
                        <span class="iconify" data-icon="mdi:folder-open" data-width="20"></span>
                        Lihat Semua Proyek (<?php echo $project_count; ?>)
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>