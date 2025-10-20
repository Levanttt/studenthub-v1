<?php
include '../../includes/config.php';
include '../../includes/functions.php';

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

// Get recent projects with skills data
$recent_projects_stmt = $conn->prepare("
    SELECT p.*, GROUP_CONCAT(DISTINCT s.name) as skill_names, GROUP_CONCAT(DISTINCT s.skill_type) as skill_types
    FROM projects p 
    LEFT JOIN project_skills ps ON p.id = ps.project_id 
    LEFT JOIN skills s ON ps.skill_id = s.id 
    WHERE p.student_id = ? 
    GROUP BY p.id 
    ORDER BY p.created_at DESC 
    LIMIT 3
");
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
            <a href="add-project.php" class="group bg-gradient-to-br from-teal-50 to-cyan-50 rounded-2xl p-6 text-center border-2 border-teal-100 hover:border-teal-300 transition-all duration-300 transform hover:scale-[1.02]">
                <div class="text-cyan-500 mb-3 group-hover:scale-110 transition-transform duration-300 flex justify-center">
                    <span class="iconify" data-icon="mdi:plus-box" data-width="48"></span>
                </div>
                <h3 class="text-blue-900 font-semibold mb-1">Tambah Proyek</h3>
                <p class="text-gray-600 text-sm">Upload proyek terbaru</p>
            </a>

            <!-- Manage Projects -->
            <a href="projects.php" class="group bg-gradient-to-br from-teal-50 to-cyan-50 rounded-2xl p-6 text-center border-2 border-teal-100 hover:border-teal-300 transition-all duration-300 transform hover:scale-[1.02]">
                <div class="text-green-500 mb-3 group-hover:scale-110 transition-transform duration-300 flex justify-center">
                    <span class="iconify" data-icon="mdi:folder-open" data-width="48"></span>
                </div>
                <h3 class="text-blue-900 font-semibold mb-1">Kelola Proyek</h3>
                <p class="text-gray-600 text-sm">Lihat dan edit proyek</p>
            </a>
            
            <!-- CERTIFICATES -->
            <a href="certificates.php" class="group bg-gradient-to-br from-teal-50 to-cyan-50 rounded-2xl p-6 text-center border-2 border-teal-100 hover:border-teal-300 transition-all duration-300 transform hover:scale-[1.02]">
                <div class="text-indigo-500 mb-3 group-hover:scale-110 transition-transform duration-300 flex justify-center">
                    <span class="iconify" data-icon="mdi:certificate" data-width="48"></span>
                </div>
                <h3 class="text-blue-900 font-semibold mb-1">Sertifikat</h3>
                <p class="text-gray-600 text-sm">Kelola sertifikat</p>
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
            <!-- Projects Grid dengan Style projects.php -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php while($project = $recent_projects_result->fetch_assoc()): 
                    // Parse skills data
                    $skill_names = !empty($project['skill_names']) ? explode(',', $project['skill_names']) : [];
                    $skill_types = !empty($project['skill_types']) ? explode(',', $project['skill_types']) : [];
                    
                    // Combine skills with their types
                    $skills_with_types = [];
                    foreach ($skill_names as $index => $skill_name) {
                        $skill_type = $skill_types[$index] ?? 'technical';
                        $skills_with_types[] = [
                            'name' => $skill_name,
                            'type' => $skill_type
                        ];
                    }
                ?>
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-lg transition-all duration-300 group project-card">
                    <!-- Thumbnail -->
                    <div class="h-48 bg-gradient-to-br from-cyan-500 to-blue-600 relative overflow-hidden">
                        <?php if (!empty($project['image_path'])): ?>
                            <img src="<?php echo htmlspecialchars($project['image_path']); ?>" 
                                 alt="<?php echo htmlspecialchars($project['title']); ?>" 
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        <?php else: ?>
                            <div class="w-full h-48 bg-gradient-to-br from-gray-200 to-gray-300 flex items-center justify-center">
                                <span class="iconify text-gray-400" data-icon="mdi:image-off" data-width="48"></span>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Category Badge di pojok kiri atas -->
                        <div class="absolute top-3 left-3">
                        <?php
                        // Mapping LENGKAP antara value singkat dan nama kategori dari tabel
                        $category_mapping = [
                            'web' => 'Web Development',
                            'mobile' => 'Mobile Development',
                            'data' => 'Data Science & AI', // Asumsi value singkatnya 'data'
                            'design' => 'UI/UX & Graphic Design',
                            'game' => 'Game Development',
                            'digital_marketing' => 'Digital Marketing & E-commerce', // Asumsi value singkatnya 'digital_marketing'
                            'finance' => 'Finance & Investment Analysis',
                            'business' => 'Business Strategy & Management', // Asumsi value singkatnya 'business'
                            'communication' => 'Communication & Public Relations',
                            'content' => 'Content Creation',
                            'branding' => 'Branding & Visual Identity', // Asumsi value singkatnya 'branding'
                            'iot' => 'IoT & Embedded Systems',
                            'other' => 'Lainnya' // Asumsi value singkatnya 'other'
                        ];

                        $enum_category = $project['category']; // Ambil value singkat dari database proyek
                        // Cari nama lengkap di mapping, jika tidak ada, gunakan formatText() sebagai fallback
                        $category_name = $category_mapping[$enum_category] ?? formatText($enum_category);
                        ?>
                        <span class="bg-white/90 text-blue-900 px-3 py-1 rounded-full text-xs font-semibold backdrop-blur-sm">
                            <?php echo htmlspecialchars($category_name); ?>
                        </span>
                    </div>
                    </div>
                    
                    <!-- Content -->
                    <div class="p-5">
                        <!-- Judul dan Tahun dalam satu baris -->
                        <div class="flex justify-between items-start mb-2">
                            <h3 class="font-bold text-blue-900 text-lg group-hover:text-cyan-600 transition-colors line-clamp-1 flex-1 mr-2">
                                <?php echo htmlspecialchars($project['title']); ?>
                            </h3>
                            <span class="text-gray-500 text-sm font-medium bg-gray-100 px-2 py-1 rounded whitespace-nowrap">
                                <?php echo htmlspecialchars($project['project_year']); ?>
                            </span>
                        </div>
                        
                        <!-- Skills/Tags dengan warna konsisten seperti di projects.php -->
                        <?php if (!empty($skills_with_types)): ?>
                        <div class="flex flex-wrap gap-1 mb-3">
                            <?php 
                            $displaySkills = array_slice($skills_with_types, 0, 3);
                            $remaining = count($skills_with_types) - 3;
                            
                            foreach($displaySkills as $skill): 
                                $color_class = [
                                    'technical' => 'bg-blue-100 text-blue-800',
                                    'soft' => 'bg-green-100 text-green-800',
                                    'tool' => 'bg-purple-100 text-purple-800'
                                ][$skill['type']] ?? 'bg-gray-100 text-gray-800';
                            ?>
                                <span class="<?php echo $color_class; ?> px-3 py-1 rounded-lg text-xs font-medium">
                                    <?php echo htmlspecialchars($skill['name']); ?>
                                </span>
                            <?php endforeach; ?>
                            
                            <?php if ($remaining > 0): ?>
                                <span class="bg-gray-100 text-gray-500 px-3 py-1 rounded-lg text-xs font-medium">
                                    +<?php echo $remaining; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <p class="text-gray-600 text-sm mb-4 line-clamp-2">
                            <?php echo htmlspecialchars($project['description']); ?>
                        </p>
                        
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 text-sm">
                                <?php echo date('M Y', strtotime($project['created_at'])); ?>
                            </span>
                            <a href="project-detail.php?id=<?php echo $project['id']; ?>" 
                               class="text-cyan-600 hover:text-cyan-700 font-semibold text-sm flex items-center gap-1 group-hover:gap-2 transition-all duration-300">
                                View Details
                                <span class="iconify" data-icon="mdi:arrow-right" data-width="16"></span>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.project-card {
    transition: all 0.3s ease;
}

.project-card:hover {
    transform: translateY(-4px);
}
</style>

<?php include '../../includes/footer.php'; ?>