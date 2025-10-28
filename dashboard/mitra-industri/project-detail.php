<?php
include '../../includes/config.php';
include '../../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../../login.php");
    exit();
}

if (getUserRole() != 'mitra_industri') {
    header("Location: ../../unauthorized.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: student-all-projects.php?error=missing_project_id");
    exit();
}

if (!isset($_GET['student_id']) || empty($_GET['student_id'])) {
    header("Location: student-all-projects.php?error=missing_student_id");
    exit();
}

$project_id = intval($_GET['id']);
$student_id = intval($_GET['student_id']);

$project = [];
try {
    $project_query = "
        SELECT p.*, u.name as student_name, u.university, u.major, u.semester 
        FROM projects p 
        JOIN users u ON p.student_id = u.id 
        WHERE p.id = ? AND p.student_id = ? AND u.role = 'student'
    ";
    $stmt = $conn->prepare($project_query);
    $stmt->bind_param("ii", $project_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $project = $result->fetch_assoc();
    $stmt->close();
    
    if (!$project) {
        header("Location: student-all-projects.php?id=" . $student_id . "&error=project_not_found");
        exit();
    }
} catch (Exception $e) {
    header("Location: student-all-projects.php?id=" . $student_id . "&error=database_error");
    exit();
}

$project_images = [];
try {
    $images_query = "
        SELECT image_path, is_primary 
        FROM project_images 
        WHERE project_id = ? 
        ORDER BY is_primary DESC, id ASC
    ";
    $images_stmt = $conn->prepare($images_query);
    $images_stmt->bind_param("i", $project_id);
    $images_stmt->execute();
    $images_result = $images_stmt->get_result();
    
    while ($image = $images_result->fetch_assoc()) {
        $project_images[] = $image;
    }
    $images_stmt->close();
} catch (Exception $e) {
}

if (empty($project_images) && !empty($project['image_path'])) {
    $project_images[] = [
        'image_path' => $project['image_path'],
        'is_primary' => true
    ];
}

$skills = [];
try {
    $skills_query = "
        SELECT s.name, s.skill_type 
        FROM skills s 
        JOIN project_skills ps ON s.id = ps.skill_id 
        WHERE ps.project_id = ?
        ORDER BY s.skill_type, s.name
    ";
    $skills_stmt = $conn->prepare($skills_query);
    $skills_stmt->bind_param("i", $project_id);
    $skills_stmt->execute();
    $skills_result = $skills_stmt->get_result();
    
    while ($skill = $skills_result->fetch_assoc()) {
        $skills[] = $skill;
    }
    $skills_stmt->close();
} catch (Exception $e) {
}

$is_liked = false;
try {
    if (isLoggedIn() && getUserRole() == 'mitra_industri') {
        $like_check_query = "SELECT id FROM project_likes WHERE project_id = ? AND mitra_industri_id = ?";
        $like_check_stmt = $conn->prepare($like_check_query);
        $like_check_stmt->bind_param("ii", $project_id, $_SESSION['user_id']);
        $like_check_stmt->execute();
        $is_liked = $like_check_stmt->get_result()->num_rows > 0;
        $like_check_stmt->close();
        
        if (!isset($_SESSION['likes_cache'])) {
            $_SESSION['likes_cache'] = [];
        }
        if ($is_liked && !in_array($project_id, $_SESSION['likes_cache'])) {
            $_SESSION['likes_cache'][] = $project_id;
        }
    }
} catch (Exception $e) {
    $is_liked = isset($_SESSION['likes_cache']) && in_array($project_id, $_SESSION['likes_cache']);
}

$cv_query = "SELECT cv_file_path FROM users WHERE id = ?";
$cv_stmt = $conn->prepare($cv_query);
$cv_stmt->bind_param("i", $student_id);
$cv_stmt->execute();
$cv_result = $cv_stmt->get_result();
$student_cv = $cv_result->fetch_assoc();
$cv_stmt->close();

$student_pic_query = "SELECT profile_picture FROM users WHERE id = ?";
$student_pic_stmt = $conn->prepare($student_pic_query);
$student_pic_stmt->bind_param("i", $student_id);
$student_pic_stmt->execute();
$student_pic_result = $student_pic_stmt->get_result();
$student_data = $student_pic_result->fetch_assoc();
$student_pic_stmt->close();
?>

<?php include '../../includes/header.php'; ?>

<style>
@media (max-width: 768px) {
    .mobile-stack {
        flex-direction: column !important;
    }
    
    .mobile-full {
        width: 100% !important;
    }
    
    .mobile-text-center {
        text-align: center !important;
    }
    
    .mobile-gap-3 {
        gap: 0.75rem !important;
    }
    
    .mobile-breadcrumb {
        font-size: 0.75rem !important;
        gap: 0.25rem !important;
    }
    
    .mobile-breadcrumb .iconify {
        width: 12px !important;
        height: 12px !important;
    }
    
    .mobile-project-header {
        padding: 1rem !important;
    }
    
    .mobile-sidebar-card {
        margin-bottom: 1rem !important;
    }
}
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
    <!-- Navigation & Header -->
    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <!-- Breadcrumb -->
            <nav class="flex items-center gap-1 text-xs text-gray-600 overflow-x-auto pb-3 mobile-breadcrumb sm:text-sm sm:gap-2 sm:pb-0 flex-1">
                <a href="index.php" class="hover:text-[#2A8FA9] transition-colors whitespace-nowrap">Dashboard</a>
                <span class="iconify" data-icon="mdi:chevron-right" data-width="10"></span>
                <a href="student-profile.php?id=<?php echo $student_id; ?>" class="hover:text-[#2A8FA9] transition-colors whitespace-nowrap">
                    Profil
                </a>
                <span class="iconify" data-icon="mdi:chevron-right" data-width="10"></span>
                <a href="student-all-projects.php?id=<?php echo $student_id; ?>" class="hover:text-[#2A8FA9] transition-colors whitespace-nowrap">Projects</a>
                <span class="iconify" data-icon="mdi:chevron-right" data-width="10"></span>
                <span class="text-gray-900 font-medium whitespace-nowrap truncate max-w-[120px]"><?php echo htmlspecialchars($project['title']); ?></span>
            </nav>
            
            <!-- Back Button - Desktop Only -->
            <div class="hidden sm:flex">
                <a href="student-all-projects.php?id=<?php echo $student_id; ?>" 
                    class="bg-[#E0F7FF] text-[#2A8FA9] px-4 sm:px-6 py-2.5 sm:py-3 rounded-xl font-semibold hover:bg-[#51A3B9] hover:text-white transition-colors duration-300 border border-[#51A3B9] border-opacity-30 flex items-center gap-2 text-sm sm:text-base whitespace-nowrap">
                    <span class="iconify" data-icon="mdi:arrow-left" data-width="16"></span>
                    Kembali ke Semua Project
                </a>
            </div>
        </div>
    </div>

    <!-- Mobile Student Info -->
    <div class="block lg:hidden space-y-4 mb-6">
        <div class="bg-[#E0F7FF] rounded-2xl border border-[#51A3B9] border-opacity-30 p-4">
            <div class="flex items-center gap-3">
                <?php if (!empty($student_data['profile_picture'])): ?>
                    <img class="h-12 w-12 rounded-full object-cover border-2 border-white shadow-sm" 
                        src="<?php echo htmlspecialchars($student_data['profile_picture']); ?>" 
                        alt="<?php echo htmlspecialchars($project['student_name']); ?>">
                <?php else: ?>
                    <div class="h-12 w-12 rounded-full bg-gradient-to-br from-[#2A8FA9] to-[#51A3B9] flex items-center justify-center border-2 border-white shadow-sm">
                        <span class="iconify text-white" data-icon="mdi:account" data-width="20"></span>
                    </div>
                <?php endif; ?>
                
                <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-[#2A8FA9] text-sm truncate"><?php echo htmlspecialchars($project['student_name']); ?></h3>
                    <div class="flex flex-wrap gap-1 text-xs text-gray-600">
                        <?php if (!empty($project['major'])): ?>
                            <span class="truncate"><?php echo htmlspecialchars($project['major']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($project['semester'])): ?>
                            <span>• Semester <?php echo htmlspecialchars($project['semester']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 sm:gap-8">
        <!-- Main Content -->
        <div class="lg:col-span-3 space-y-6">
            <!-- Project Header -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 sm:p-6 lg:p-8 mobile-project-header">
                <!-- Desktop Student Info -->
                <div class="hidden lg:flex items-center gap-4 mb-6 p-4 bg-[#E0F7FF] rounded-xl border border-[#51A3B9] border-opacity-30">
                    <?php if (!empty($student_data['profile_picture'])): ?>
                        <img class="h-12 w-12 rounded-full object-cover border-2 border-white shadow-sm" 
                            src="<?php echo htmlspecialchars($student_data['profile_picture']); ?>" 
                            alt="<?php echo htmlspecialchars($project['student_name']); ?>">
                    <?php else: ?>
                        <div class="h-12 w-12 rounded-full bg-gradient-to-br from-[#2A8FA9] to-[#51A3B9] flex items-center justify-center border-2 border-white shadow-sm">
                            <span class="iconify text-white" data-icon="mdi:account" data-width="20"></span>
                        </div>
                    <?php endif; ?>
                    
                    <div>
                        <h3 class="font-bold text-[#2A8FA9]"><?php echo htmlspecialchars($project['student_name']); ?></h3>
                        <div class="flex flex-wrap gap-1 text-sm text-gray-600">
                            <?php if (!empty($project['major'])): ?>
                                <span><?php echo htmlspecialchars($project['major']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($project['semester'])): ?>
                                <span>• Semester <?php echo htmlspecialchars($project['semester']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Project Title & Meta -->
                <div class="mb-4 sm:mb-6">
                    <h1 class="text-2xl sm:text-3xl font-bold text-[#2A8FA9] mb-3 sm:mb-4"><?php echo htmlspecialchars($project['title']); ?></h1>
                    
                    <!-- Project Meta -->
                    <div class="flex flex-wrap gap-2 sm:gap-4 text-xs sm:text-sm text-gray-600 mb-4 sm:mb-6">
                        <span class="flex items-center gap-1 sm:gap-2 bg-gray-100 px-2 sm:px-3 py-1.5 sm:py-2 rounded-lg">
                            <span class="iconify text-[#51A3B9]" data-icon="mdi:calendar" data-width="14"></span>
                            <span class="font-medium">Tahun: <?php echo htmlspecialchars($project['project_year']); ?></span>
                        </span>
                        <span class="flex items-center gap-1 sm:gap-2 bg-gray-100 px-2 sm:px-3 py-1.5 sm:py-2 rounded-lg">
                            <span class="iconify text-[#409BB2]" data-icon="mdi:tag" data-width="14"></span>
                            <span class="font-medium">Tipe: <?php echo formatText($project['project_type']); ?></span>
                        </span>
                        <span class="flex items-center gap-1 sm:gap-2 bg-gray-100 px-2 sm:px-3 py-1.5 sm:py-2 rounded-lg">
                            <span class="iconify text-[#489EB7]" data-icon="mdi:clock-outline" data-width="14"></span>
                            <span class="font-medium">Status: <?php echo formatText($project['status']); ?></span>
                        </span>
                    </div>

                    <!-- Project Description -->
                    <div class="mb-4 sm:mb-6">
                        <h2 class="text-lg sm:text-xl font-bold text-[#2A8FA9] mb-3 sm:mb-4 flex items-center gap-2">
                            <span class="iconify" data-icon="mdi:text-box-edit" data-width="18"></span>
                            Deskripsi Proyek
                        </h2>
                        
                        <div class="bg-gray-50 rounded-xl p-4 sm:p-6 border border-gray-300">
                            <div class="prose max-w-none text-gray-700 leading-relaxed text-sm sm:text-base">
                                <?php 
                                $description = htmlspecialchars($project['description']);
                                if (empty(trim($description))) {
                                    echo '<div class="text-center py-4 sm:py-8 text-gray-500">';
                                    echo '<span class="iconify inline-block mb-2" data-icon="mdi:text-box-remove" data-width="32"></span>';
                                    echo '<p class="text-base sm:text-lg font-semibold">Belum ada deskripsi</p>';
                                    echo '<p class="text-xs sm:text-sm mt-2">Mahasiswa belum menambahkan deskripsi proyek</p>';
                                    echo '</div>';
                                } else {
                                    echo nl2br($description);
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Skills Tags -->
                <?php if (!empty($skills)): ?>
                <div class="mb-4 sm:mb-6">
                    <h3 class="text-base sm:text-lg font-semibold text-gray-800 mb-2 sm:mb-3 flex items-center gap-2">
                        <span class="iconify" data-icon="mdi:code-braces" data-width="16"></span>
                        Teknologi & Tools yang Digunakan
                    </h3>
                    <div class="flex flex-wrap gap-1.5 sm:gap-2">
                        <?php foreach ($skills as $skill): 
                            $color_class = [
                                'technical' => 'bg-blue-100 text-blue-800 border border-blue-200',
                                'soft' => 'bg-green-100 text-green-800 border border-green-200',
                                'tool' => 'bg-purple-100 text-purple-800 border border-purple-200'
                            ][$skill['skill_type']] ?? 'bg-gray-100 text-gray-800 border border-gray-200';
                        ?>
                            <span class="<?php echo $color_class; ?> px-2.5 sm:px-3 py-1 sm:py-1.5 rounded-full text-xs sm:text-sm font-medium">
                                <?php echo htmlspecialchars(trim($skill['name'])); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Project Links -->
                <div class="flex flex-wrap gap-2 sm:gap-4 mobile-stack">
                    <?php if (!empty($project['github_url'])): ?>
                    <a href="<?php echo htmlspecialchars($project['github_url']); ?>" 
                        target="_blank"
                        class="flex-1 bg-gray-800 text-white px-4 sm:px-6 py-2.5 sm:py-3 rounded-xl font-semibold hover:bg-gray-900 transition-colors duration-300 flex items-center justify-center gap-2 text-sm sm:text-base mobile-full">
                        <span class="iconify" data-icon="mdi:github" data-width="16"></span>
                        <span class="hidden sm:inline">Lihat Kode di GitHub</span>
                        <span class="sm:hidden">GitHub</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['demo_url'])): ?>
                    <a href="<?php echo htmlspecialchars($project['demo_url']); ?>" 
                        target="_blank"
                        class="flex-1 bg-[#2A8FA9] text-white px-4 sm:px-6 py-2.5 sm:py-3 rounded-xl font-semibold hover:bg-[#409BB2] transition-colors duration-300 flex items-center justify-center gap-2 text-sm sm:text-base mobile-full">
                        <span class="iconify" data-icon="mdi:web" data-width="16"></span>
                        <span class="hidden sm:inline">Lihat Demo Live</span>
                        <span class="sm:hidden">Demo</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['figma_url'])): ?>
                    <a href="<?php echo htmlspecialchars($project['figma_url']); ?>" 
                        target="_blank"
                        class="flex-1 bg-green-600 text-white px-4 sm:px-6 py-2.5 sm:py-3 rounded-xl font-semibold hover:bg-green-700 transition-colors duration-300 flex items-center justify-center gap-2 text-sm sm:text-base mobile-full">
                        <span class="iconify" data-icon="mdi:palette" data-width="16"></span>
                        <span class="hidden sm:inline">Lihat Desain Figma</span>
                        <span class="sm:hidden">Figma</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['video_url'])): ?>
                    <a href="<?php echo htmlspecialchars($project['video_url']); ?>" 
                        target="_blank"
                        class="flex-1 bg-red-600 text-white px-4 sm:px-6 py-2.5 sm:py-3 rounded-xl font-semibold hover:bg-red-700 transition-colors duration-300 flex items-center justify-center gap-2 text-sm sm:text-base mobile-full">
                        <span class="iconify" data-icon="mdi:video" data-width="16"></span>
                        <span class="hidden sm:inline">Lihat Video Demo</span>
                        <span class="sm:hidden">Video</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mobile Quick Actions -->
            <div class="block lg:hidden bg-white rounded-2xl shadow-sm border border-gray-200 p-4 mobile-sidebar-card">
                <h3 class="text-base font-bold text-[#2A8FA9] mb-3 flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:rocket-launch" data-width="16"></span>
                    Quick Actions
                </h3>
                
                <div class="space-y-2">
                    <!-- Like Button -->
                    <button onclick="toggleLike(<?php echo $project_id; ?>)" 
                            id="likeButton"
                            class="w-full bg-gradient-to-r from-pink-500 to-rose-500 text-white py-3 px-4 rounded-lg font-semibold hover:from-pink-600 hover:to-rose-600 transition-all duration-300 flex items-center justify-center gap-2 shadow-md text-sm">
                        <span class="iconify" data-icon="mdi:heart<?php echo $is_liked ? '' : '-outline'; ?>" data-width="16" id="likeIcon"></span>
                        <span id="likeText"><?php echo $is_liked ? 'Disukai' : 'Suka Project'; ?></span>
                    </button>
                    
                    <!-- Lihat CV -->
                    <?php if (!empty($student_cv['cv_file_path'])): ?>
                    <a href="<?php echo htmlspecialchars($student_cv['cv_file_path']); ?>" 
                    target="_blank"
                    class="w-full bg-gradient-to-r from-green-500 to-emerald-600 text-white py-3 px-4 rounded-lg font-semibold hover:from-green-600 hover:to-emerald-700 transition-all duration-300 flex items-center justify-center gap-2 shadow-md text-sm">
                        <span class="iconify" data-icon="mdi:file-eye" data-width="16"></span>
                        Lihat CV
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Certificate Section -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 sm:p-6 lg:p-8">
                <h2 class="text-xl sm:text-2xl font-bold text-[#2A8FA9] mb-4 sm:mb-6 flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:certificate" data-width="20"></span>
                    Sertifikat Terkait
                </h2>
                
                <?php if (!empty($project['certificate_path'])): ?>
                <div class="bg-gradient-to-br from-amber-50 to-yellow-50 rounded-xl border border-amber-200 p-4 sm:p-6">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="flex items-center gap-3 sm:gap-4">
                            <div class="w-12 h-12 sm:w-16 sm:h-16 bg-amber-100 rounded-full flex items-center justify-center border-4 border-amber-200">
                                <span class="iconify text-amber-600" data-icon="mdi:certificate" data-width="24"></span>
                            </div>
                            <div>
                                <h3 class="text-base sm:text-lg font-bold text-amber-800">Sertifikat Tersedia</h3>
                                <p class="text-amber-700 text-xs sm:text-sm">
                                    <?php 
                                    $filename = basename($project['certificate_path']);
                                    echo htmlspecialchars($filename);
                                    ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="flex gap-2 sm:gap-3 w-full sm:w-auto">
                            <a href="<?php echo htmlspecialchars($project['certificate_path']); ?>" 
                            target="_blank"
                            class="flex-1 bg-amber-500 text-white px-3 sm:px-4 py-2 rounded-lg font-semibold hover:bg-amber-600 transition-colors duration-300 flex items-center justify-center gap-2 text-xs sm:text-sm mobile-full">
                                <span class="iconify" data-icon="mdi:eye" data-width="14"></span>
                                Lihat
                            </a>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="text-center py-6 sm:py-8 bg-gray-50 rounded-xl border border-gray-300">
                    <div class="text-gray-400 mb-2 sm:mb-3 flex justify-center">
                        <span class="iconify" data-icon="mdi:certificate-off" data-width="36"></span>
                    </div>
                    <h3 class="text-base sm:text-lg font-semibold text-gray-600 mb-1 sm:mb-2">Belum Ada Sertifikat</h3>
                    <p class="text-gray-500 text-xs sm:text-sm mb-3 sm:mb-4">Mahasiswa tidak memiliki sertifikat terkait proyek ini</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Project Gallery -->
            <?php if (!empty($project_images)): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 sm:p-6 lg:p-8">
                <h2 class="text-xl sm:text-2xl font-bold text-[#2A8FA9] mb-4 sm:mb-6 flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:image-multiple" data-width="20"></span>
                    Gallery Proyek
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                    <?php foreach ($project_images as $index => $image): ?>
                        <div class="bg-gray-50 rounded-xl overflow-hidden border border-gray-300 shadow-sm">
                            <img src="<?php echo htmlspecialchars($image['image_path']); ?>" 
                                alt="<?php echo htmlspecialchars($project['title']); ?> - Gambar <?php echo $index + 1; ?>" 
                                class="w-full h-48 sm:h-64 object-cover hover:scale-105 transition-transform duration-300 cursor-zoom-in"
                                onclick="openImageModal('<?php echo htmlspecialchars($image['image_path']); ?>')">
                            <div class="p-3 sm:p-4 bg-white border-t border-gray-300">
                                <p class="text-xs sm:text-sm text-gray-600 text-center font-medium">
                                    <?php echo $image['is_primary'] ? 'Gambar utama proyek' : 'Gambar ' . ($index + 1); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Desktop Sidebar -->
        <div class="hidden lg:block space-y-6">
            <!-- Quick Actions -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-bold text-[#2A8FA9] mb-4 flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:rocket-launch" data-width="20"></span>
                    Quick Actions
                </h3>
                
                <div class="space-y-3">
                    <!-- Like Button -->
                    <button onclick="toggleLike(<?php echo $project_id; ?>)" 
                            id="likeButton"
                            class="w-full bg-gradient-to-r from-pink-500 to-rose-500 text-white py-3 px-4 rounded-lg font-semibold hover:from-pink-600 hover:to-rose-600 transition-all duration-300 flex items-center justify-center gap-2 shadow-md">
                        <span class="iconify" data-icon="mdi:heart<?php echo $is_liked ? '' : '-outline'; ?>" data-width="18" id="likeIcon"></span>
                        <span id="likeText"><?php echo $is_liked ? 'Disukai' : 'Suka Project'; ?></span>
                    </button>
                    
                    <!-- Lihat CV -->
                    <?php if (!empty($student_cv['cv_file_path'])): ?>
                    <a href="<?php echo htmlspecialchars($student_cv['cv_file_path']); ?>" 
                    target="_blank"
                    class="w-full bg-gradient-to-r from-green-500 to-emerald-600 text-white py-3 px-4 rounded-lg font-semibold hover:from-green-600 hover:to-emerald-700 transition-all duration-300 flex items-center justify-center gap-2 shadow-md">
                        <span class="iconify" data-icon="mdi:file-eye" data-width="18"></span>
                        Lihat CV
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Project Details Sidebar -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-bold text-[#2A8FA9] mb-4 flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:clipboard-text" data-width="20"></span>
                    Detail Proyek
                </h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-600 mb-1">Tipe Proyek</p>
                        <p class="font-semibold text-gray-800 text-sm"><?php echo formatText($project['project_type']); ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 mb-1">Tahun</p>
                        <p class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($project['project_year']); ?></p>
                    </div>
                    <?php if (!empty($project['project_duration'])): ?>
                    <div>
                        <p class="text-xs text-gray-600 mb-1">Durasi</p>
                        <p class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($project['project_duration']); ?></p>
                    </div>
                    <?php endif; ?>
                    <div>
                        <p class="text-xs text-gray-600 mb-1">Status</p>
                        <?php echo getStatusBadge($project['status']); ?>
                    </div>
                    <div class="col-span-2">
                        <p class="text-xs text-gray-600 mb-1">Kategori</p>
                        <p class="font-semibold text-gray-800 text-sm"><?php echo formatText($project['category']); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div id="imageModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="max-w-4xl max-h-full p-4">
        <div class="bg-white rounded-lg overflow-hidden shadow-2xl">
            <div class="flex justify-between items-center p-4 border-b border-gray-300">
                <h3 class="text-lg font-semibold text-gray-800">Preview Gambar</h3>
                <button onclick="closeImageModal()" class="text-gray-500 hover:text-gray-700 transition-colors">
                    <span class="iconify" data-icon="mdi:close" data-width="24"></span>
                </button>
            </div>
            <div class="p-4">
                <img id="modalImage" src="" alt="" class="max-w-full max-h-96 object-contain">
            </div>
        </div>
    </div>
</div>

<script>
let currentLikeState = <?php echo $is_liked ? 'true' : 'false'; ?>;

function toggleLike(projectId) {
    const likeButton = document.getElementById('likeButton');
    const likeIcon = document.getElementById('likeIcon');
    const likeText = document.getElementById('likeText');
    
    const action = currentLikeState ? 'unlike' : 'like';
    
    likeButton.disabled = true;
    const originalText = likeText.textContent;
    likeText.textContent = 'Loading...';
    
    fetch('like-handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=${action}&project_id=${projectId}`
    })
    .then(response => response.json())
    .then(data => {
        likeButton.disabled = false;
        
        if (data.success) {
            currentLikeState = data.new_state;
            updateLikeUI(currentLikeState);
        } else {
            likeText.textContent = originalText;
        }
    })
    .catch(error => {
        likeButton.disabled = false;
        likeText.textContent = originalText;
    });
}

function updateLikeUI(isLiked) {
    const likeButton = document.getElementById('likeButton');
    const likeIcon = document.getElementById('likeIcon');
    const likeText = document.getElementById('likeText');
    
    if (isLiked) {
        likeIcon.setAttribute('data-icon', 'mdi:heart');
        likeText.textContent = 'Disukai';
        likeButton.classList.add('from-pink-600', 'to-rose-600');
        likeButton.classList.remove('from-pink-500', 'to-rose-500');
    } else {
        likeIcon.setAttribute('data-icon', 'mdi:heart-outline');
        likeText.textContent = 'Suka Project';
        likeButton.classList.remove('from-pink-600', 'to-rose-600');
        likeButton.classList.add('from-pink-500', 'to-rose-500');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updateLikeUI(currentLikeState);
});

function openImageModal(imageSrc) {
    document.getElementById('modalImage').src = imageSrc;
    document.getElementById('imageModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeImageModal() {
    document.getElementById('imageModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

document.getElementById('imageModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeImageModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeImageModal();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>