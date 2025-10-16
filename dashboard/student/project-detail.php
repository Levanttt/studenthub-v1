<?php
include '../../includes/config.php';
include '../../includes/functions.php';

if (!isLoggedIn() || getUserRole() != 'student') {
    header("Location: ../../login.php");
    exit();
}

// Check if project ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: projects.php");
    exit();
}

$project_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Get project data
$stmt = $conn->prepare("SELECT * FROM projects WHERE id = ? AND student_id = ?");
$stmt->bind_param("ii", $project_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: projects.php?error=project_not_found");
    exit();
}

$project = $result->fetch_assoc();
$stmt->close();

// Get project images from project_images table
$images_stmt = $conn->prepare("SELECT image_path, is_primary FROM project_images WHERE project_id = ? ORDER BY is_primary DESC, id ASC");
$images_stmt->bind_param("i", $project_id);
$images_stmt->execute();
$images_result = $images_stmt->get_result();
$project_images = [];

// Jika ada gambar di project_images, gunakan itu
while ($image = $images_result->fetch_assoc()) {
    $project_images[] = $image;
}
$images_stmt->close();

// Jika tidak ada gambar di project_images, gunakan image_path dari projects sebagai fallback
if (empty($project_images) && !empty($project['image_path'])) {
    $project_images[] = [
        'image_path' => $project['image_path'],
        'is_primary' => true
    ];
}

// Get skills for this project dengan kategori
$skills_stmt = $conn->prepare("
    SELECT s.name, s.skill_type 
    FROM skills s 
    JOIN project_skills ps ON s.id = ps.skill_id 
    WHERE ps.project_id = ?
");
$skills_stmt->bind_param("i", $project_id);
$skills_stmt->execute();
$skills_result = $skills_stmt->get_result();
$skills = [];
while ($skill = $skills_result->fetch_assoc()) {
    $skills[] = $skill;
}
$skills_stmt->close();
?>


<?php include '../../includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Navigation & Header -->
        <div class="flex justify-between items-center mb-6">
            <!-- Breadcrumb -->
            <nav class="flex items-center gap-2 text-sm text-gray-600">
                <a href="index.php" class="hover:text-cyan-600 transition-colors">Dashboard</a>
                <span class="iconify" data-icon="mdi:chevron-right" data-width="16"></span>
                <a href="projects.php" class="hover:text-cyan-600 transition-colors">Proyek</a>
                <span class="iconify" data-icon="mdi:chevron-right" data-width="16"></span>
                <span class="text-gray-900 font-medium"><?php echo htmlspecialchars($project['title']); ?></span>
            </nav>
            
            <!-- Tombol Kembali di pojok kanan atas -->
            <a href="projects.php" 
            class="bg-blue-500/10 text-blue-700 px-6 py-3 rounded-xl font-semibold hover:bg-blue-500/20 transition-colors duration-300 border border-blue-200 flex items-center gap-2">
                <span class="iconify" data-icon="mdi:arrow-left" data-width="18"></span>
                Kembali ke Proyek
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- Main Content - Lebih Luas -->
            <div class="lg:col-span-3 space-y-6">
            <!-- Project Header dengan Deskripsi -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-8">
                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6 mb-6">
                    <div class="flex-1">
                        <h1 class="text-3xl font-bold text-blue-900 mb-4"><?php echo htmlspecialchars($project['title']); ?></h1>
                        
                        <!-- Project Meta -->
                        <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600 mb-6">
                            <span class="flex items-center gap-2 bg-gray-100 px-3 py-2 rounded-lg">
                                <span class="iconify text-cyan-600" data-icon="mdi:calendar" data-width="16"></span>
                                <span class="font-medium">Dibuat: <?php echo date('d M Y', strtotime($project['created_at'])); ?></span>
                            </span>
                            <span class="flex items-center gap-2 bg-gray-100 px-3 py-2 rounded-lg">
                                <span class="iconify text-blue-600" data-icon="mdi:tag" data-width="16"></span>
                                <span class="font-medium">Kategori: <?php echo formatText($project['category']); ?></span>
                            </span>
                            <span class="flex items-center gap-2 bg-gray-100 px-3 py-2 rounded-lg">
                                <span class="iconify text-green-600" data-icon="mdi:clock-outline" data-width="16"></span>
                                <span class="font-medium">Status: <?php echo formatText($project['status']); ?></span>
                            </span>
                        </div>

                        <!-- Project Description -->
                        <div class="mb-6">
                            <h2 class="text-xl font-bold text-blue-900 mb-4 flex items-center gap-2">
                                <span class="iconify" data-icon="mdi:text-box-edit" data-width="20"></span>
                                Deskripsi Proyek
                            </h2>
                            
                            <div class="bg-gray-50 rounded-xl p-6 border border-gray-300">
                                <div class="prose max-w-none text-gray-700 leading-relaxed">
                                    <?php 
                                    $description = htmlspecialchars($project['description']);
                                    if (empty(trim($description))) {
                                        echo '<div class="text-center py-8 text-gray-500">';
                                        echo '<span class="iconify inline-block mb-2" data-icon="mdi:text-box-remove" data-width="48"></span>';
                                        echo '<p class="text-lg font-semibold">Belum ada deskripsi</p>';
                                        echo '<p class="text-sm mt-2">Tambahkan deskripsi proyek untuk menjelaskan detail karya Anda</p>';
                                        echo '<a href="edit-project.php?id=' . $project['id'] . '" class="inline-block mt-4 bg-cyan-500 text-white px-6 py-2 rounded-lg font-semibold hover:bg-cyan-600 transition-colors duration-300 shadow-md">';
                                        echo 'Tambahkan Deskripsi';
                                        echo '</a>';
                                        echo '</div>';
                                    } else {
                                        $description = nl2br($description);
                                        echo $description;
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Skills Tags -->
                <?php if (!empty($skills)): ?>
                <div class="mb-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Keterampilan yang Digunakan:</h3>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($skills as $skill): 
                            $color_class = [
                                'technical' => 'bg-blue-100 text-blue-800',
                                'soft' => 'bg-green-100 text-green-800',
                                'tool' => 'bg-purple-100 text-purple-800'
                            ][$skill['skill_type']] ?? 'bg-gray-100 text-gray-800';
                        ?>
                            <span class="<?php echo $color_class; ?> px-3 py-1 rounded-lg text-xs font-medium">
                                <?php echo htmlspecialchars(trim($skill['name'])); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Project Links -->
                <div class="flex flex-wrap gap-4">
                    <?php if (!empty($project['github_url'])): ?>
                    <a href="<?php echo htmlspecialchars($project['github_url']); ?>" 
                       target="_blank"
                       class="bg-gray-800 text-white px-6 py-3 rounded-xl font-semibold hover:bg-gray-900 transition-colors duration-300 flex items-center gap-2 shadow-md">
                        <span class="iconify" data-icon="mdi:github" data-width="20"></span>
                        Lihat Kode di GitHub
                    </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['demo_url'])): ?>
                    <a href="<?php echo htmlspecialchars($project['demo_url']); ?>" 
                       target="_blank"
                       class="bg-blue-600 text-white px-6 py-3 rounded-xl font-semibold hover:bg-blue-700 transition-colors duration-300 flex items-center gap-2 shadow-md">
                        <span class="iconify" data-icon="mdi:web" data-width="20"></span>
                        Lihat Demo Live
                    </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['figma_url'])): ?>
                    <a href="<?php echo htmlspecialchars($project['figma_url']); ?>" 
                       target="_blank"
                       class="bg-green-600 text-white px-6 py-3 rounded-xl font-semibold hover:bg-green-700 transition-colors duration-300 flex items-center gap-2 shadow-md">
                        <span class="iconify" data-icon="mdi:palette" data-width="20"></span>
                        Lihat Desain Figma
                    </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['video_url'])): ?>
                    <a href="<?php echo htmlspecialchars($project['video_url']); ?>" 
                       target="_blank"
                       class="bg-red-600 text-white px-6 py-3 rounded-xl font-semibold hover:bg-red-700 transition-colors duration-300 flex items-center gap-2 shadow-md">
                        <span class="iconify" data-icon="mdi:video" data-width="20"></span>
                        Lihat Video
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Project Gallery -->
            <?php if (!empty($project_images)): ?>
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-8">
                <h2 class="text-2xl font-bold text-blue-900 mb-6 flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:image-multiple" data-width="24"></span>
                    Gallery Proyek
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($project_images as $index => $image): ?>
                        <div class="bg-gray-50 rounded-xl overflow-hidden border border-gray-300 shadow-sm">
                            <img src="<?php echo htmlspecialchars($image['image_path']); ?>" 
                                 alt="<?php echo htmlspecialchars($project['title']); ?> - Gambar <?php echo $index + 1; ?>" 
                                 class="w-full h-64 object-cover hover:scale-105 transition-transform duration-300 cursor-zoom-in"
                                 onclick="openImageModal('<?php echo htmlspecialchars($image['image_path']); ?>')">
                            <div class="p-4 bg-white border-t border-gray-300">
                                <p class="text-sm text-gray-600 text-center font-medium">
                                    <?php echo $image['is_primary'] ? 'Gambar utama proyek' : 'Gambar ' . ($index + 1); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <!-- Placeholder jika tidak ada gambar -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-8">
                <h2 class="text-2xl font-bold text-blue-900 mb-6 flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:image-multiple" data-width="24"></span>
                    Gallery Proyek
                </h2>
                
                <div class="text-center py-12 bg-gray-50 rounded-xl border border-gray-300">
                    <div class="text-gray-400 mb-4 flex justify-center">
                        <span class="iconify" data-icon="mdi:image-off" data-width="64"></span>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-600 mb-2">Belum Ada Gambar</h3>
                    <p class="text-gray-500 text-sm">Edit proyek untuk menambahkan screenshot atau mockup</p>
                    <a href="edit-project.php?id=<?php echo $project['id']; ?>" class="inline-block mt-4 bg-cyan-500 text-white px-6 py-2 rounded-lg font-semibold hover:bg-cyan-600 transition-colors duration-300 shadow-md">
                        Tambah Gambar
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar - Lebih Sempit dan Simple -->
        <div class="space-y-6">
            <!-- Project Details Sidebar -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6">
                <h3 class="text-lg font-bold text-blue-900 mb-4 flex items-center gap-2">
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

            <!-- Action Buttons -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6">
                <h3 class="text-lg font-bold text-blue-900 mb-4 flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:cog" data-width="20"></span>
                    Aksi
                </h3>
                <div class="space-y-3">
                    <a href="edit-project.php?id=<?php echo $project['id']; ?>" 
                    class="w-full bg-cyan-500 text-white py-3 px-4 rounded-lg font-semibold hover:bg-cyan-600 transition-colors duration-300 flex items-center justify-center gap-2 shadow-md">
                        <span class="iconify" data-icon="mdi:pencil" data-width="18"></span>
                        Edit Proyek
                    </a>
                
                    <button onclick="confirmDelete(<?php echo $project['id']; ?>)" 
                            class="w-full bg-red-500 text-white py-3 px-4 rounded-lg font-semibold hover:bg-red-600 transition-colors duration-300 flex items-center justify-center gap-2 shadow-md">
                        <span class="iconify" data-icon="mdi:delete" data-width="18"></span>
                        Hapus Proyek
                    </button>
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
// Image Modal Functions
function openImageModal(imageSrc) {
    document.getElementById('modalImage').src = imageSrc;
    document.getElementById('imageModal').classList.remove('hidden');
}

function closeImageModal() {
    document.getElementById('imageModal').classList.add('hidden');
}

// Close modal when clicking outside
document.getElementById('imageModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeImageModal();
    }
});

// Delete Confirmation
function confirmDelete(projectId) {
    if (confirm('Apakah Anda yakin ingin menghapus proyek ini? Tindakan ini tidak dapat dibatalkan.')) {
        window.location.href = 'delete-project.php?id=' + projectId;
    }
}

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeImageModal();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>