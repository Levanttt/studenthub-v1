<?php
include '../../includes/config.php';
include '../../includes/functions.php';

if (!isLoggedIn() || getUserRole() != 'student') {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

$projects_per_page = 6;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $projects_per_page;

if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    $check_stmt = $conn->prepare("SELECT id FROM projects WHERE id = ? AND student_id = ?");
    $check_stmt->bind_param("ii", $delete_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $delete_stmt = $conn->prepare("DELETE FROM projects WHERE id = ?");
        $delete_stmt->bind_param("i", $delete_id);
        
        if ($delete_stmt->execute()) {
            $success = "Proyek berhasil dihapus!";
        } else {
            $error = "Gagal menghapus proyek: " . $conn->error;
        }
        $delete_stmt->close();
    } else {
        $error = "Proyek tidak ditemukan atau tidak memiliki akses";
    }
    $check_stmt->close();
}

$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM projects WHERE student_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_projects = $count_result->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_projects / $projects_per_page);

$stmt = $conn->prepare("SELECT * FROM projects WHERE student_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $user_id, $projects_per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();
$projects = $result->fetch_all(MYSQLI_ASSOC);

$student_stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$student_stmt->bind_param("i", $user_id);
$student_stmt->execute();
$student_result = $student_stmt->get_result();
$student = $student_result->fetch_assoc();
?>

<?php include '../../includes/header.php'; ?>

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

.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.project-card {
    transition: all 0.3s ease;
}

.project-card:hover {
    transform: translateY(-4px);
}

@media (max-width: 768px) {
    .mobile-stack {
        flex-direction: column;
    }
    
    .mobile-full {
        width: 100%;
    }
    
    .mobile-text-center {
        text-align: center;
    }
    
    .mobile-padding {
        padding: 1rem;
    }
    
    .mobile-card-height {
        height: auto;
        min-height: 400px;
    }
}

@media (max-width: 640px) {
    .mobile-buttons {
        flex-direction: row !important;
        gap: 0.5rem;
    }
    
    .mobile-button {
        padding: 0.5rem 0.75rem !important;
        font-size: 0.75rem !important;
        white-space: nowrap;
    }
}

/* Untuk screen sangat kecil */
@media (max-width: 380px) {
    .mobile-button {
        padding: 0.5rem !important;
        font-size: 0.7rem !important;
    }
}
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
    <!-- Header -->
    <div class="mb-6 sm:mb-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4 mobile-stack">
            <div class="mobile-text-center sm:text-left">
                <h1 class="text-2xl sm:text-3xl font-bold text-[#2A8FA9] flex items-center gap-2 sm:gap-3 justify-center sm:justify-start">
                    <span class="iconify" data-icon="mdi:folder-multiple" data-width="28"></span>
                    Kelola Proyek
                </h1>
                <p class="text-gray-600 mt-2 text-sm sm:text-base">Kelola semua proyek portofolio kamu di Cakrawala Connect</p>
            </div>
            <div class="flex flex-row gap-2 sm:gap-3 w-full sm:w-auto mobile-full mobile-buttons">
                <a href="add-project.php" class="bg-[#2A8FA9] text-white px-3 sm:px-6 py-2 sm:py-3 rounded-xl font-bold hover:bg-[#409BB2] transition-colors duration-300 flex items-center justify-center gap-2 shadow-md text-xs sm:text-base mobile-button flex-1 sm:flex-none text-center">
                    <span class="iconify mobile-button-icon" data-icon="mdi:plus-circle" data-width="16"></span>
                    <span class="hidden xs:inline">Tambah Proyek</span>
                    <span class="xs:hidden">Tambah Proyek</span>
                </a>
                <a href="index.php" class="bg-[#E0F7FF] text-[#2A8FA9] px-3 sm:px-6 py-2 sm:py-3 rounded-xl font-semibold hover:bg-[#51A3B9] hover:text-white transition-colors duration-300 border border-[#51A3B9] border-opacity-30 flex items-center justify-center gap-2 text-xs sm:text-base mobile-button flex-1 sm:flex-none text-center">
                    <span class="iconify mobile-button-icon" data-icon="mdi:arrow-left" data-width="16"></span>
                    Kembali
                </a>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="bg-[#E0F7FF] rounded-2xl p-4 sm:p-6 border border-[#51A3B9] border-opacity-30">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3 sm:gap-4">
                    <div class="bg-[#51A3B9] p-2 sm:p-3 rounded-xl">
                        <span class="iconify text-white" data-icon="mdi:folder-star" data-width="24"></span>
                    </div>
                    <div>
                        <h3 class="text-[#2A8FA9] font-bold text-xl sm:text-2xl"><?php echo $total_projects; ?> Project</h3>
                        <p class="text-[#409BB2] text-sm sm:text-base">Total project yang telah kamu upload</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($success): ?>
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center gap-2 text-sm sm:text-base">
            <span class="iconify" data-icon="mdi:check-circle" data-width="18"></span>
            <?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center gap-2 text-sm sm:text-base">
            <span class="iconify" data-icon="mdi:alert-circle" data-width="18"></span>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if ($total_projects == 0): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-12 text-center">
            <div class="max-w-md mx-auto">
                <div class="text-[#51A3B9] mb-4 sm:mb-6 flex justify-center">
                    <span class="iconify" data-icon="mdi:folder-open-outline" data-width="60"></span>
                </div>
                <h3 class="text-xl sm:text-2xl font-bold text-[#2A8FA9] mb-3">Belum Ada Proyek</h3>
                <p class="text-gray-600 mb-2 text-sm sm:text-base">Mulai bangun portofolio impresif kamu dengan menambahkan proyek pertamamu</p>
                <p class="text-gray-500 text-xs sm:text-sm mb-6 sm:mb-8">Tunjukkan kemampuan nyata kepada recruiter dan industri</p>
                <div class="flex flex-col sm:flex-row gap-2 mobile-gap-2">
                    <a href="add-project.php" class="bg-gradient-to-r from-[#2A8FA9] to-[#51A3B9] text-white px-4 py-3 rounded-lg font-bold hover:from-[#409BB2] hover:to-[#489EB7] transition-all duration-300 flex items-center justify-center gap-2 shadow text-center whitespace-nowrap text-sm sm:text-base">
                        <span class="iconify" data-icon="mdi:rocket-launch" data-width="16"></span>
                        Tambah Proyek pertama
                    </a>
                    <a href="index.php" class="bg-gray-100 text-gray-700 px-4 py-3 rounded-lg font-semibold hover:bg-gray-200 transition-colors duration-300 border border-gray-200 flex items-center justify-center gap-2 text-center whitespace-nowrap text-sm sm:text-base">
                        <span class="iconify" data-icon="mdi:home" data-width="16"></span>
                        Dashboard
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Projects Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 lg:gap-8 mobile-grid">
            <?php foreach ($projects as $project): ?>
            <div class="bg-white rounded-xl sm:rounded-2xl shadow-sm border border-gray-200 hover:shadow-lg transition-all duration-300 group overflow-hidden project-card flex flex-col h-auto sm:h-[480px] mobile-card-height relative">
                <a href="project-detail.php?id=<?php echo $project['id']; ?>" class="block cursor-pointer flex-1 flex flex-col">
                    <!-- Project Thumbnail -->
                    <div class="relative overflow-hidden bg-gray-100 flex-shrink-0">
                        <?php if (!empty($project['image_path'])): ?>
                            <img src="<?php echo htmlspecialchars($project['image_path']); ?>" 
                                alt="<?php echo htmlspecialchars($project['title']); ?>" 
                                class="w-full h-40 sm:h-48 object-cover group-hover:scale-105 transition-transform duration-300">
                        <?php else: ?>
                            <div class="w-full h-40 sm:h-48 bg-gradient-to-br from-gray-200 to-gray-300 flex items-center justify-center">
                                <span class="iconify text-gray-400" data-icon="mdi:image-off" data-width="36"></span>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Category Badge -->
                        <div class="absolute top-2 left-2 sm:top-3 sm:left-3">
                        <?php
                        $category_mapping = [
                        'web' => 'Web Development',
                        'mobile' => 'Mobile Development',
                        'data' => 'Data Science & AI',
                        'design' => 'UI/UX & Graphic Design', 
                        'game' => 'Game Development',
                        'iot' => 'IoT & Embedded Systems', 
                        'cybersecurity' => 'Cybersecurity', 
                        'digital_marketing' => 'Digital Marketing',
                        'finance' => 'Finance & Investment',
                        'business' => 'Business Strategy',
                        'industrial_ops' => 'Industrial Ops', 
                        'communication' => 'Public Relations',
                        'content' => 'Content Creation', 
                        'branding' => 'Branding',
                        'legal' => 'Legal Analysis', 
                        'research' => 'Research', 
                        'education' => 'Education Material', 
                        'other' => 'Lainnya'
                        ];

                        $enum_category = $project['category']; 
                        $category_name = $category_mapping[$enum_category] ?? formatText($enum_category);
                        ?>
                        <span class="bg-white/90 text-[#2A8FA9] px-2 sm:px-3 py-1 rounded-full text-xs font-semibold backdrop-blur-sm">
                            <?php echo htmlspecialchars($category_name); ?>
                        </span>
                    </div>
                        
                        <!-- Action Buttons Overlay -->
                        <div class="absolute top-2 right-2 sm:top-3 sm:right-3 flex gap-1 sm:gap-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            <a href="edit-project.php?id=<?php echo $project['id']; ?>" 
                            class="bg-white/90 text-gray-700 p-1 sm:p-2 rounded-lg hover:bg-white hover:text-[#51A3B9] transition-colors"
                            title="Edit Proyek"
                            onclick="event.stopPropagation()">
                                <span class="iconify" data-icon="mdi:pencil" data-width="14"></span>
                            </a>
                            <button onclick="event.stopPropagation(); confirmDelete(<?php echo $project['id']; ?>, '<?php echo htmlspecialchars($project['title']); ?>')"
                                    class="bg-white/90 text-gray-700 p-1 sm:p-2 rounded-lg hover:bg-red-50 hover:text-red-600 transition-colors"
                                    title="Hapus Proyek">
                                <span class="iconify" data-icon="mdi:delete" data-width="14"></span>
                            </button>
                        </div>
                    </div>

                    <!-- Project Content -->
                    <div class="p-3 sm:p-5 flex-1 flex flex-col">
                        <div class="flex justify-between items-start mb-2">
                            <h3 class="font-bold text-[#2A8FA9] text-base sm:text-lg group-hover:text-[#51A3B9] transition-colors line-clamp-1 flex-1 mr-2">
                                <?php echo htmlspecialchars($project['title']); ?>
                            </h3>
                            <span class="text-gray-500 text-xs sm:text-sm font-medium bg-gray-100 px-2 py-1 rounded whitespace-nowrap flex-shrink-0">
                                <?php echo htmlspecialchars($project['project_year']); ?>
                            </span>
                        </div>
                        
                        <!-- Skills Tags -->
                        <?php 
                        $skills_stmt = $conn->prepare("
                            SELECT s.name, s.skill_type 
                            FROM skills s 
                            JOIN project_skills ps ON s.id = ps.skill_id 
                            WHERE ps.project_id = ?
                        ");
                        $skills_stmt->bind_param("i", $project['id']);
                        $skills_stmt->execute();
                        $skills_result = $skills_stmt->get_result();
                        $all_skills = [];
                        while ($skill = $skills_result->fetch_assoc()) {
                            $all_skills[] = $skill;
                        }
                        $skills_stmt->close();

                        if (!empty($all_skills)): 
                        ?>
                        <div class="flex flex-wrap gap-1 mb-2 sm:mb-3">
                            <?php 
                            $displaySkills = array_slice($all_skills, 0, 3);
                            $totalSkills = count($all_skills);
                            $remaining = $totalSkills - 3;
                            
                            foreach($displaySkills as $skill): 
                                $color_class = [
                                    'technical' => 'bg-blue-100 text-blue-800',
                                    'soft' => 'bg-green-100 text-green-800', 
                                    'tool' => 'bg-purple-100 text-purple-800'
                                ][$skill['skill_type']] ?? 'bg-gray-100 text-gray-800';
                            ?>
                                <span class="<?php echo $color_class; ?> px-2 sm:px-3 py-1 rounded text-xs font-medium">
                                    <?php echo htmlspecialchars($skill['name']); ?>
                                </span>
                            <?php endforeach; ?>
                            
                            <?php if ($remaining > 0): ?>
                                <span class="bg-gray-100 text-gray-500 px-2 sm:px-3 py-1 rounded text-xs font-medium">
                                    +<?php echo $remaining; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Project Description -->
                        <div class="min-h-[60px] mb-3 sm:mb-4 flex-1"> 
                            <p class="text-gray-600 text-xs sm:text-sm line-clamp-3 leading-relaxed">
                                <?php 
                                $description = htmlspecialchars($project['description']);
                                echo strlen($description) > 100 ? substr($description, 0, 100) . '...' : $description;
                                ?>
                            </p>
                        </div>

                        <div class="flex items-center justify-between min-h-[24px] flex-shrink-0">
                            <span class="text-gray-500 text-xs sm:text-sm">
                                <?php echo date('M Y', strtotime($project['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                </a>
                
                <!-- ACTION LINKS -->
                <div class="bg-white border-t border-gray-200 px-3 sm:px-5 py-3 flex-shrink-0">
                    <div class="flex items-center justify-between">
                        <a href="project-detail.php?id=<?php echo $project['id']; ?>" 
                        class="text-[#2A8FA9] hover:text-[#51A3B9] font-semibold flex items-center gap-1 sm:gap-2 group/link text-sm sm:text-base">
                            View Details
                            <span class="iconify group-hover/link:translate-x-1 transition-transform" data-icon="mdi:arrow-right" data-width="14"></span>
                        </a>
                        
                        <div class="flex items-center gap-2 sm:gap-3 text-gray-400">
                            <?php if (!empty($project['github_url'])): ?>
                            <a href="<?php echo htmlspecialchars($project['github_url']); ?>" 
                            target="_blank"
                            class="hover:text-gray-700 transition-colors"
                            title="GitHub">
                                <span class="iconify" data-icon="mdi:github" data-width="16"></span>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($project['demo_url'])): ?>
                            <a href="<?php echo htmlspecialchars($project['demo_url']); ?>" 
                            target="_blank"
                            class="hover:text-[#2A8FA9] transition-colors"
                            title="Lihat Proyek">
                                <span class="iconify" data-icon="mdi:web" data-width="16"></span>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination Navigation -->
        <?php if ($total_pages > 1): ?>
        <div class="flex flex-col sm:flex-row justify-center items-center gap-3 sm:gap-4 mt-6 sm:mt-8 mb-6 sm:mb-8">
            <!-- Previous Button -->
            <a href="?page=<?php echo max(1, $current_page - 1); ?>" 
            class="flex items-center justify-center gap-2 px-4 sm:px-6 py-2 sm:py-3 bg-white border border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-50 transition-colors duration-300 w-full sm:w-auto text-sm sm:text-base <?php echo $current_page == 1 ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                <span class="iconify" data-icon="mdi:chevron-left" data-width="18"></span>
                Sebelumnya
            </a>

            <!-- Page Info -->
            <div class="flex items-center gap-2 bg-[#E0F7FF] rounded-xl px-4 sm:px-6 py-2 sm:py-3 w-full sm:w-auto justify-center">
                <span class="text-[#2A8FA9] font-bold text-sm sm:text-base">Halaman <?php echo $current_page; ?> dari <?php echo $total_pages; ?></span>
            </div>

            <!-- Next Button -->
            <a href="?page=<?php echo min($total_pages, $current_page + 1); ?>" 
            class="flex items-center justify-center gap-2 px-4 sm:px-6 py-2 sm:py-3 bg-white border border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-50 transition-colors duration-300 w-full sm:w-auto text-sm sm:text-base <?php echo $current_page == $total_pages ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                Selanjutnya
                <span class="iconify" data-icon="mdi:chevron-right" data-width="18"></span>
            </a>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function confirmDelete(projectId, projectTitle) {
    Swal.fire({
        title: 'Hapus Proyek?',
        html: `<div class="text-middle">
                <p class="text-gray-600 mt-2">Proyek akan dihapus permanent dari portofoliomu.</p>
                </div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        background: '#ffffff'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `projects.php?delete_id=${projectId}`;
        }
    });
}

if (typeof Swal === 'undefined') {
    function confirmDelete(projectId, projectTitle) {
        if (confirm(`Hapus proyek "${projectTitle}"?`)) {
            window.location.href = `projects.php?delete_id=${projectId}`;
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.project-card a, .project-card button').forEach(element => {
        element.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>