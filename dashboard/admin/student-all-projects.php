<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../includes/config.php';
include '../../includes/functions.php';

if (!isLoggedIn() || getUserRole() != 'admin') {
    header("Location: ../../login.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: students.php");
    exit();
}

$student_id = intval($_GET['id']);

$student = [];
try {
    $student_query = "SELECT id, name, university, major FROM users WHERE id = ? AND role = 'student'";
    $stmt = $conn->prepare($student_query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
    
    if (!$student) {
        header("Location: students.php?error=student_not_found");
        exit();
    }
} catch (Exception $e) {
    header("Location: students.php?error=database_error");
    exit();
}

$projects_per_page = 6;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $projects_per_page;

$total_projects = 0;
try {
    $count_query = "SELECT COUNT(*) as total FROM projects WHERE student_id = ?";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param("i", $student_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_projects = $count_result->fetch_assoc()['total'];
    $count_stmt->close();
} catch (Exception $e) {
    error_log("Error counting projects: " . $e->getMessage());
}

$total_pages = ceil($total_projects / $projects_per_page);

$projects = [];
try {
    $projects_query = "
        SELECT 
            p.*,
            GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') as skill_names,
            GROUP_CONCAT(DISTINCT s.skill_type ORDER BY s.skill_type SEPARATOR ', ') as skill_types,
            GROUP_CONCAT(DISTINCT pi.image_path ORDER BY pi.id SEPARATOR '|||') as gallery_images
        FROM projects p
        LEFT JOIN project_skills ps ON p.id = ps.project_id
        LEFT JOIN skills s ON ps.skill_id = s.id
        LEFT JOIN project_images pi ON p.id = pi.project_id
        WHERE p.student_id = ?
        GROUP BY p.id
        ORDER BY p.project_year DESC, p.created_at DESC
        LIMIT ? OFFSET ?
    ";
    $projects_stmt = $conn->prepare($projects_query);
    $projects_stmt->bind_param("iii", $student_id, $projects_per_page, $offset);
    $projects_stmt->execute();
    $projects_result = $projects_stmt->get_result();
    $projects = $projects_result->fetch_all(MYSQLI_ASSOC);
    $projects_stmt->close();
} catch (Exception $e) {
    error_log("Error fetching projects: " . $e->getMessage());
}

foreach ($projects as &$project) {
    $project_skills = [];
    try {
        $project_skills_query = "
            SELECT s.name, s.skill_type 
            FROM project_skills ps 
            JOIN skills s ON ps.skill_id = s.id 
            WHERE ps.project_id = ?
            ORDER BY s.skill_type, s.name
        ";
        $project_skills_stmt = $conn->prepare($project_skills_query);
        $project_skills_stmt->bind_param("i", $project['id']);
        $project_skills_stmt->execute();
        $project_skills_result = $project_skills_stmt->get_result();
        
        while ($skill = $project_skills_result->fetch_assoc()) {
            $project_skills[] = $skill;
        }
        $project_skills_stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching project skills: " . $e->getMessage());
    }
    
    $project['skills_detail'] = $project_skills;
}
unset($project); 
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

.thumbnail-active {
    border-color: #2A8FA9 !important;
    transform: scale(1.05);
}
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
            <div>
                <h1 class="text-3xl font-bold text-[#2A8FA9] flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:folder-multiple" data-width="32"></span>
                    Semua Project Mahasiswa
                </h1>
                <p class="text-gray-600 mt-2">
                    Portofolio lengkap project dari 
                    <span class="font-semibold text-[#2A8FA9]"><?php echo htmlspecialchars($student['name']); ?></span>
                    <?php if (!empty($student['major'])): ?>
                        - <?php echo htmlspecialchars($student['major']); ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3">
                <a href="students.php" 
                class="bg-[#E0F7FF] text-[#2A8FA9] px-6 py-3 rounded-xl font-semibold hover:bg-[#51A3B9] hover:text-white transition-colors duration-300 border border-[#51A3B9] border-opacity-30 flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:arrow-left" data-width="18"></span>
                    Kembali Ke Profile
                </a>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="bg-[#E0F7FF] rounded-2xl p-6 border border-[#51A3B9] border-opacity-30">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="bg-[#51A3B9] p-3 rounded-xl">
                        <span class="iconify text-white" data-icon="mdi:folder-star" data-width="32"></span>
                    </div>
                    <div>
                        <h3 class="text-[#2A8FA9] font-bold text-2xl"><?php echo $total_projects; ?> Project</h3>
                        <p class="text-[#409BB2]">Total project yang telah dikerjakan</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($total_projects == 0): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
            <div class="max-w-md mx-auto">
                <div class="text-[#51A3B9] mb-6 flex justify-center">
                    <span class="iconify" data-icon="mdi:folder-open-outline" data-width="80"></span>
                </div>
                <h3 class="text-2xl font-bold text-[#2A8FA9] mb-3">Belum Ada Project</h3>
                <p class="text-gray-600 mb-2">Mahasiswa ini belum menambahkan project ke portofolio mereka.</p>
                <p class="text-gray-500 text-sm mb-8">Silakan kembali untuk melihat mahasiswa lainnya</p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="student-profile.php?id=<?php echo $student_id; ?>" 
                        class="bg-[#2A8FA9] text-white px-8 py-4 rounded-xl font-bold hover:bg-[#409BB2] transition-all duration-300 flex items-center justify-center gap-2 shadow-lg">
                        <span class="iconify" data-icon="mdi:account-circle" data-width="20"></span>
                        Kembali ke Profil
                    </a>
                    <a href="students.php" 
                        class="bg-gray-100 text-gray-700 px-8 py-4 rounded-xl font-semibold hover:bg-gray-200 transition-colors duration-300 border border-gray-200 flex items-center justify-center gap-2">
                        <span class="iconify" data-icon="mdi:account-multiple" data-width="20"></span>
                        Lihat Mahasiswa Lain
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Page Info for Mobile -->
        <?php if ($total_pages > 1): ?>
        <div class="sm:hidden mb-6 bg-[#E0F7FF] rounded-xl p-4 text-center border border-[#51A3B9] border-opacity-30">
            <span class="text-[#2A8FA9] font-bold">Halaman <?php echo $current_page; ?> dari <?php echo $total_pages; ?></span>
        </div>
        <?php endif; ?>

        <!-- Projects Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($projects as $project): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 hover:shadow-lg transition-all duration-300 group overflow-hidden project-card flex flex-col h-[480px] relative">
                <a href="project-detail.php?id=<?php echo $project['id']; ?>&student_id=<?php echo $student_id; ?>" class="block cursor-pointer">
                    <!-- Project Thumbnail -->
                    <div class="relative overflow-hidden bg-gray-100">
                        <?php if (!empty($project['image_path'])): ?>
                            <img src="<?php echo htmlspecialchars($project['image_path']); ?>" 
                                alt="<?php echo htmlspecialchars($project['title']); ?>" 
                                class="w-full h-48 object-cover group-hover:scale-105 transition-transform duration-300">
                        <?php else: ?>
                            <div class="w-full h-48 bg-gradient-to-br from-gray-200 to-gray-300 flex items-center justify-center">
                                <span class="iconify text-gray-400" data-icon="mdi:image-off" data-width="48"></span>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Category Badge -->
                        <div class="absolute top-3 left-3">
                        <?php
                        $category_mapping = [
                        // IT & Desain
                        'web' => 'Web Development',
                        'mobile' => 'Mobile Development',
                        'data' => 'Data Science & AI',
                        'design' => 'UI/UX & Graphic Design', 
                        'game' => 'Game Development',
                        'iot' => 'IoT & Embedded Systems', 
                        'cybersecurity' => 'Cybersecurity', 

                        // Bisnis & Manajemen
                        'digital_marketing' => 'Digital Marketing',
                        'finance' => 'Finance & Investment',
                        'business' => 'Business Strategy',
                        'industrial_ops' => 'Industrial Ops', 

                        // Komunikasi & Konten
                        'communication' => 'Public Relations',
                        'content' => 'Content Creation', 
                        'branding' => 'Branding',

                        // Sosial & Humaniora
                        'legal' => 'Legal Analysis', 
                        'research' => 'Research', 
                        'education' => 'Education Material', 

                        // Lainnya
                        'other' => 'Lainnya'
                        ];

                        $enum_category = $project['category']; 
                        $category_name = $category_mapping[$enum_category] ?? $enum_category;
                        ?>
                        <span class="bg-white/90 text-[#2A8FA9] px-3 py-1 rounded-full text-xs font-semibold backdrop-blur-sm">
                            <?php echo htmlspecialchars($category_name); ?>
                        </span>
                    </div>
                    </div>

                    <!-- Project Content -->
                    <div class="p-5">
                        <div class="flex justify-between items-start mb-2">
                            <h3 class="font-bold text-[#2A8FA9] text-lg group-hover:text-[#51A3B9] transition-colors line-clamp-1 flex-1 mr-2">
                                <?php echo htmlspecialchars($project['title']); ?>
                            </h3>
                            <span class="text-gray-500 text-sm font-medium bg-gray-100 px-2 py-1 rounded whitespace-nowrap">
                                <?php echo htmlspecialchars($project['project_year']); ?>
                            </span>
                        </div>
                        
                        <!-- Skills Tags -->
                        <?php if (!empty($project['skills_detail'])): ?>
                        <div class="flex flex-wrap gap-1 mb-3">
                            <?php 
                            $displaySkills = array_slice($project['skills_detail'], 0, 5);
                            $totalSkills = count($project['skills_detail']);
                            $remaining = $totalSkills - 5;
                            
                            foreach($displaySkills as $skill): 
                                $color_class = [
                                    'technical' => 'bg-blue-100 text-blue-800',
                                    'soft' => 'bg-green-100 text-green-800', 
                                    'tool' => 'bg-purple-100 text-purple-800'
                                ][$skill['skill_type']] ?? 'bg-gray-100 text-gray-800';
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
                        
                        <!-- Project Description -->
                        <div class="min-h-[60px] mb-4"> 
                            <p class="text-gray-600 text-sm line-clamp-3 leading-relaxed">
                                <?php 
                                $description = htmlspecialchars($project['description']);
                                echo strlen($description) > 100 ? substr($description, 0, 100) . '...' : $description;
                                ?>
                            </p>
                        </div>

                        <div class="flex items-center justify-between min-h-[24px]">
                            <span class="text-gray-500 text-sm">
                                <?php echo date('M Y', strtotime($project['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                </a>
                
                <!-- ACTION LINKS -->
                <div class="absolute bottom-0 left-0 right-0 bg-white border-t border-gray-200 px-5 py-4 flex-shrink-0">
                    <div class="flex items-center justify-between">
                        <a href="project-detail.php?id=<?php echo $project['id']; ?>&student_id=<?php echo $student_id; ?>" 
                        class="text-[#2A8FA9] hover:text-[#51A3B9] font-semibold flex items-center gap-2 group/link">
                            View Details
                            <span class="iconify group-hover/link:translate-x-1 transition-transform" data-icon="mdi:arrow-right" data-width="16"></span>
                        </a>
                        
                        <div class="flex items-center gap-3 text-gray-400">
                            <?php if (!empty($project['github_url'])): ?>
                            <a href="<?php echo htmlspecialchars($project['github_url']); ?>" 
                            target="_blank"
                            class="hover:text-gray-700 transition-colors p-2 bg-white rounded-lg border border-gray-200 shadow-sm"
                            title="GitHub Repository">
                                <span class="iconify" data-icon="mdi:github" data-width="16"></span>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($project['demo_url'])): ?>
                            <a href="<?php echo htmlspecialchars($project['demo_url']); ?>" 
                            target="_blank"
                            class="hover:text-[#2A8FA9] transition-colors p-2 bg-white rounded-lg border border-gray-200 shadow-sm"
                            title="Live Demo">
                                <span class="iconify" data-icon="mdi:web" data-width="16"></span>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($project['figma_url'])): ?>
                            <a href="<?php echo htmlspecialchars($project['figma_url']); ?>" 
                            target="_blank"
                            class="hover:text-purple-600 transition-colors p-2 bg-white rounded-lg border border-gray-200 shadow-sm"
                            title="Figma Design">
                                <span class="iconify" data-icon="mdi:palette" data-width="16"></span>
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
        <div class="flex justify-center items-center space-x-4 mt-8 mb-8">
            <!-- Previous Button -->
            <a href="?id=<?php echo $student_id; ?>&page=<?php echo max(1, $current_page - 1); ?>" 
            class="flex items-center gap-2 px-6 py-3 bg-white border border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-50 transition-colors duration-300 <?php echo $current_page == 1 ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                <span class="iconify" data-icon="mdi:chevron-left" data-width="20"></span>
                Sebelumnya
            </a>

            <!-- Page Info -->
            <div class="hidden sm:flex items-center gap-2 bg-[#E0F7FF] rounded-xl px-6 py-3">
                <span class="text-[#2A8FA9] font-bold">Halaman <?php echo $current_page; ?> dari <?php echo $total_pages; ?></span>
            </div>

            <!-- Next Button -->
            <a href="?id=<?php echo $student_id; ?>&page=<?php echo min($total_pages, $current_page + 1); ?>" 
            class="flex items-center gap-2 px-6 py-3 bg-white border border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-50 transition-colors duration-300 <?php echo $current_page == $total_pages ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                Selanjutnya
                <span class="iconify" data-icon="mdi:chevron-right" data-width="20"></span>
            </a>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Image Modal -->
<div id="imageModal" class="hidden fixed inset-0 bg-black bg-opacity-90 z-50 flex items-center justify-center p-4">
    <div class="relative max-w-4xl max-h-full">
        <button onclick="closeImageModal()" class="absolute -top-12 right-0 text-white hover:text-gray-300 transition-colors">
            <span class="iconify" data-icon="mdi:close" data-width="32"></span>
        </button>
        <img id="modalImage" src="" alt="Project Image" class="max-w-full max-h-full object-contain">
    </div>
</div>

<script>
function openImageModal(imageSrc) {
    const modal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    modalImage.src = imageSrc;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeImageModal() {
    const modal = document.getElementById('imageModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeImageModal();
    }
});

document.getElementById('imageModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeImageModal();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>