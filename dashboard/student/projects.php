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

$stmt = $conn->prepare("SELECT * FROM projects WHERE student_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$projects = $result->fetch_all(MYSQLI_ASSOC);
$total_projects = count($projects);

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

.project-card {
    transition: all 0.3s ease;
}

.project-card:hover {
    transform: translateY(-4px);
}
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
            <div>
                <h1 class="text-3xl font-bold text-[#2A8FA9] flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:folder-multiple" data-width="32"></span>
                    Kelola Proyek
                </h1>
                <p class="text-gray-600 mt-2">Kelola semua proyek portofolio kamu di StudentHub</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3">
                <a href="add-project.php" class="bg-[#2A8FA9] text-white px-6 py-3 rounded-xl font-bold hover:bg-[#409BB2] transition-colors duration-300 flex items-center gap-2 shadow-md">
                    <span class="iconify" data-icon="mdi:plus-circle" data-width="20"></span>
                    Tambah Proyek Baru
                </a>
                <a href="index.php" class="bg-[#E0F7FF] text-[#2A8FA9] px-6 py-3 rounded-xl font-semibold hover:bg-[#51A3B9] hover:text-white transition-colors duration-300 border border-[#51A3B9] border-opacity-30 flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:arrow-left" data-width="18"></span>
                    Kembali
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
                        <h3 class="text-[#2A8FA9] font-bold text-2xl"><?php echo $total_projects; ?> Proyek</h3>
                        <p class="text-[#409BB2]">Total proyek yang telah kamu upload</p>
                    </div>
                </div>
                <?php if ($total_projects > 0): ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($success): ?>
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center gap-2">
            <span class="iconify" data-icon="mdi:check-circle" data-width="20"></span>
            <?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center gap-2">
            <span class="iconify" data-icon="mdi:alert-circle" data-width="20"></span>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if ($total_projects == 0): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
            <div class="max-w-md mx-auto">
                <div class="text-[#51A3B9] mb-6 flex justify-center">
                    <span class="iconify" data-icon="mdi:folder-open-outline" data-width="80"></span>
                </div>
                <h3 class="text-2xl font-bold text-[#2A8FA9] mb-3">Belum Ada Proyek</h3>
                <p class="text-gray-600 mb-2">Mulai bangun portofolio impresif kamu dengan menambahkan proyek pertama</p>
                <p class="text-gray-500 text-sm mb-8">Tunjukkan kemampuan nyata kepada recruiter dan stakeholder</p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="add-project.php" class="bg-gradient-to-r from-[#2A8FA9] to-[#51A3B9] text-white px-8 py-4 rounded-xl font-bold hover:from-[#409BB2] hover:to-[#489EB7] transition-all duration-300 flex items-center justify-center gap-2 shadow-lg">
                        <span class="iconify" data-icon="mdi:rocket-launch" data-width="20"></span>
                        Tambah Proyek Pertama
                    </a>
                    <a href="index.php" class="bg-gray-100 text-gray-700 px-8 py-4 rounded-xl font-semibold hover:bg-gray-200 transition-colors duration-300 border border-gray-200 flex items-center justify-center gap-2">
                        <span class="iconify" data-icon="mdi:home" data-width="20"></span>
                        Kembali ke Dashboard
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Projects Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($projects as $project): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 hover:shadow-lg transition-all duration-300 group overflow-hidden project-card flex flex-col h-[480px] relative">
                <a href="project-detail.php?id=<?php echo $project['id']; ?>" class="block cursor-pointer">
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
                        <span class="bg-white/90 text-[#2A8FA9] px-3 py-1 rounded-full text-xs font-semibold backdrop-blur-sm">
                            <?php echo htmlspecialchars($category_name); ?>
                        </span>
                    </div>
                        
                        <!-- Action Buttons Overlay -->
                        <div class="absolute top-3 right-3 flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            <a href="edit-project.php?id=<?php echo $project['id']; ?>" 
                            class="bg-white/90 text-gray-700 p-2 rounded-lg hover:bg-white hover:text-[#51A3B9] transition-colors"
                            title="Edit Proyek"
                            onclick="event.stopPropagation()">
                                <span class="iconify" data-icon="mdi:pencil" data-width="16"></span>
                            </a>
                            <button onclick="event.stopPropagation(); confirmDelete(<?php echo $project['id']; ?>, '<?php echo htmlspecialchars($project['title']); ?>')"
                                    class="bg-white/90 text-gray-700 p-2 rounded-lg hover:bg-red-50 hover:text-red-600 transition-colors"
                                    title="Hapus Proyek">
                                <span class="iconify" data-icon="mdi:delete" data-width="16"></span>
                            </button>
                        </div>
                    </div>

                    <!-- Project Content -->
                    <div class="p-5">
                        <!-- Judul dan Tahun -->
                        <div class="flex justify-between items-start mb-2">
                            <h3 class="font-bold text-[#2A8FA9] text-lg group-hover:text-[#51A3B9] transition-colors line-clamp-1 flex-1 mr-2">
                                <?php echo htmlspecialchars($project['title']); ?>
                            </h3>
                            <span class="text-gray-500 text-sm font-medium bg-gray-100 px-2 py-1 rounded whitespace-nowrap">
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
                        <div class="flex flex-wrap gap-1 mb-3">
                            <?php 
                            $displaySkills = array_slice($all_skills, 0, 5);
                            $totalSkills = count($all_skills);
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
                        <a href="project-detail.php?id=<?php echo $project['id']; ?>" 
                        class="text-[#2A8FA9] hover:text-[#51A3B9] font-semibold flex items-center gap-2 group/link">
                            View Details
                            <span class="iconify group-hover/link:translate-x-1 transition-transform" data-icon="mdi:arrow-right" data-width="16"></span>
                        </a>
                        
                        <div class="flex items-center gap-3 text-gray-400">
                            <?php if (!empty($project['github_url'])): ?>
                            <a href="<?php echo htmlspecialchars($project['github_url']); ?>" 
                            target="_blank"
                            class="hover:text-gray-700 transition-colors"
                            title="GitHub">
                                <span class="iconify" data-icon="mdi:github" data-width="18"></span>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($project['demo_url'])): ?>
                            <a href="<?php echo htmlspecialchars($project['demo_url']); ?>" 
                            target="_blank"
                            class="hover:text-[#2A8FA9] transition-colors"
                            title="Lihat Proyek">
                                <span class="iconify" data-icon="mdi:web" data-width="18"></span>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Bottom Action -->
        <div class="mt-8 bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                <div class="text-center sm:text-left">
                    <h3 class="text-lg font-bold text-[#2A8FA9]">Mau menambah proyek?</h3>
                    <p class="text-gray-600">Tingkatkan portofolio kamu dengan proyek-proyek terbaru</p>
                </div>
                <a href="add-project.php" class="bg-gradient-to-r from-[#2A8FA9] to-[#51A3B9] text-white px-8 py-3 rounded-xl font-bold hover:from-[#409BB2] hover:to-[#489EB7] transition-all duration-300 flex items-center gap-2 shadow-md whitespace-nowrap">
                    <span class="iconify" data-icon="mdi:plus-circle" data-width="20"></span>
                    Tambah Proyek Baru
                </a>
            </div>
        </div>
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