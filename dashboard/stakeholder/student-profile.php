<?php
include '../../includes/config.php';
include '../../includes/functions.php';

// 1. Autentikasi: Pastikan hanya stakeholder yang bisa mengakses
if (!isLoggedIn() || getUserRole() != 'stakeholder') {
    header("Location: ../../login.php");
    exit();
}

// 2. Ambil ID student dari parameter URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$student_id = intval($_GET['id']);

// 3. Ambil data student
$student = [];
try {
    $student_query = "
        SELECT id, name, email, profile_picture, university, major, bio, specializations, 
               cv_file_path, linkedin, created_at
        FROM users 
        WHERE id = ? AND role = 'student'
    ";
    $stmt = $conn->prepare($student_query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
    
    if (!$student) {
        header("Location: index.php?error=student_not_found");
        exit();
    }
} catch (Exception $e) {
    header("Location: index.php?error=database_error");
    exit();
}

// 4. Ambil semua skills student (agregat dari semua project)
$all_skills = [
    'technical' => [],
    'soft' => [],
    'tool' => []
];

try {
    $skills_query = "
        SELECT DISTINCT s.name, s.skill_type, COUNT(DISTINCT p.id) as project_count 
        FROM project_skills ps 
        JOIN projects p ON ps.project_id = p.id
        JOIN skills s ON ps.skill_id = s.id 
        WHERE p.student_id = ?
        GROUP BY s.id
        ORDER BY project_count DESC, s.skill_type, s.name
    ";
    $skills_stmt = $conn->prepare($skills_query);
    $skills_stmt->bind_param("i", $student_id);
    $skills_stmt->execute();
    $skills_result = $skills_stmt->get_result();
    
    while ($skill = $skills_result->fetch_assoc()) {
        $skill_type = $skill['skill_type'];
        if (isset($all_skills[$skill_type])) {
            $all_skills[$skill_type][] = $skill;
        }
    }
    $skills_stmt->close();
} catch (Exception $e) {}

// 5. Ambil semua projects student dengan gambar
$projects = [];
try {
    $projects_query = "
        SELECT p.*, 
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
    ";
    $projects_stmt = $conn->prepare($projects_query);
    $projects_stmt->bind_param("i", $student_id);
    $projects_stmt->execute();
    $projects_result = $projects_stmt->get_result();
    $projects = $projects_result->fetch_all(MYSQLI_ASSOC);
    $projects_stmt->close();
} catch (Exception $e) {}

// 6. Ambil skills untuk setiap project secara detail
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
    } catch (Exception $e) {}
    
    $project['skills_detail'] = $project_skills;
}
unset($project); // Unset reference

// 7. Hitung total projects
$total_projects = count($projects);
?>

<?php include '../../includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
            <div>
                <h1 class="text-3xl font-bold text-blue-900 flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:account-circle" data-width="32"></span>
                    Profil Talenta
                </h1>
                <p class="text-gray-600 mt-2">Lihat detail lengkap talenta dan portofolio project mereka</p>
            </div>
            <a href="index.php" class="bg-blue-500/10 text-blue-700 px-6 py-3 rounded-xl font-semibold hover:bg-blue-500/20 transition-colors duration-300 border border-blue-200 flex items-center gap-2">
                <span class="iconify" data-icon="mdi:arrow-left" data-width="18"></span>
                Kembali ke Pencarian
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Sidebar - Profile Info -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 sticky top-8">
                <!-- Profile Photo & Basic Info -->
                <div class="text-center mb-6">
                    <?php if (!empty($student['profile_picture'])): ?>
                        <img class="h-32 w-32 rounded-full object-cover border-4 border-cyan-100 mx-auto mb-4 shadow-lg" 
                            src="<?php echo htmlspecialchars($student['profile_picture']); ?>" 
                            alt="<?php echo htmlspecialchars($student['name']); ?>">
                    <?php else: ?>
                        <div class="h-32 w-32 rounded-full bg-gradient-to-br from-cyan-400 to-blue-500 flex items-center justify-center border-4 border-cyan-100 mx-auto mb-4 shadow-lg">
                            <span class="iconify text-white" data-icon="mdi:account" data-width="48"></span>
                        </div>
                    <?php endif; ?>
                    
                    <h1 class="text-2xl font-bold text-blue-900 mb-2"><?php echo htmlspecialchars($student['name']); ?></h1>
                    
                    <?php if (!empty($student['major'])): ?>
                        <p class="text-gray-600 font-medium mb-1"><?php echo htmlspecialchars($student['major']); ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($student['university'])): ?>
                        <p class="text-gray-500 text-sm mb-4"><?php echo htmlspecialchars($student['university']); ?></p>
                    <?php endif; ?>
                    
                    <!-- Specializations -->
                    <?php if (!empty($student['specializations'])): ?>
                        <div class="flex flex-wrap gap-2 justify-center mb-6 mt-2">
                            <?php 
                            $specs = explode(',', $student['specializations']);
                            foreach ($specs as $spec):
                                $spec = trim($spec);
                                if (!empty($spec)):
                            ?>
                                <span class="bg-indigo-100 text-indigo-800 px-3 py-1.5 rounded-full text-xs font-medium my-1">
                                    <?php echo htmlspecialchars($spec); ?>
                                </span>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Action Buttons -->
                <div class="space-y-3 mb-6">
                    <?php if (!empty($student['cv_file_path'])): ?>
                        <a href="<?php echo htmlspecialchars($student['cv_file_path']); ?>" 
                           download="<?php echo htmlspecialchars($student['name'] . '_CV.pdf'); ?>"
                           class="w-full bg-cyan-500 text-white py-3 px-4 rounded-xl font-bold hover:bg-cyan-600 transition-colors duration-300 flex items-center justify-center gap-2 shadow-sm">
                            <span class="iconify" data-icon="mdi:file-download" data-width="18"></span>
                            Download CV
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($student['linkedin'])): ?>
                        <a href="<?php echo htmlspecialchars($student['linkedin']); ?>" 
                           target="_blank"
                           class="w-full bg-blue-600 text-white py-3 px-4 rounded-xl font-bold hover:bg-blue-700 transition-colors duration-300 flex items-center justify-center gap-2 shadow-sm">
                            <span class="iconify" data-icon="mdi:linkedin" data-width="18"></span>
                            LinkedIn Profile
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Contact Info -->
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-semibold text-blue-900 mb-4 flex items-center gap-2">
                        <span class="iconify" data-icon="mdi:contact-mail" data-width="20"></span>
                        Kontak
                    </h3>
                    
                    <div class="space-y-3">
                        <!-- Email -->
                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                            <div class="w-10 h-10 bg-cyan-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <span class="iconify text-cyan-600" data-icon="mdi:email" data-width="18"></span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-gray-600">Email</p>
                                <a href="mailto:<?php echo htmlspecialchars($student['email']); ?>" 
                                   class="text-cyan-600 hover:text-cyan-700 font-medium text-sm truncate block">
                                    <?php echo htmlspecialchars($student['email']); ?>
                                </a>
                            </div>
                        </div>
                        
                        <!-- University -->
                        <?php if (!empty($student['university'])): ?>
                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <span class="iconify text-green-600" data-icon="mdi:school" data-width="18"></span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-gray-600">Universitas</p>
                                <p class="text-gray-900 font-medium text-sm"><?php echo htmlspecialchars($student['university']); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Member Since -->
                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                            <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <span class="iconify text-purple-600" data-icon="mdi:calendar" data-width="18"></span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-gray-600">Bergabung</p>
                                <p class="text-gray-900 font-medium text-sm">
                                    <?php echo date('M Y', strtotime($student['created_at'])); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Skills Summary -->
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-semibold text-blue-900 mb-4 flex items-center gap-2">
                        <span class="iconify" data-icon="mdi:code-braces" data-width="20"></span>
                        Ringkasan Skills
                    </h3>
                    
                    <div class="space-y-4">
                        <!-- Technical Skills -->
                        <?php if (!empty($all_skills['technical'])): ?>
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-2 flex items-center gap-2 text-sm">
                                <span class="iconify text-blue-600" data-icon="mdi:cog" data-width="16"></span>
                                Technical Skills
                                <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                                    <?php echo count($all_skills['technical']); ?>
                                </span>
                            </h4>
                            <div class="flex flex-wrap gap-1">
                                <?php foreach ($all_skills['technical'] as $skill): ?>
                                    <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs" 
                                          title="Digunakan di <?php echo $skill['project_count']; ?> project">
                                        <?php echo htmlspecialchars($skill['name']); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Soft Skills -->
                        <?php if (!empty($all_skills['soft'])): ?>
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-2 flex items-center gap-2 text-sm">
                                <span class="iconify text-green-600" data-icon="mdi:account-group" data-width="16"></span>
                                Soft Skills
                                <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">
                                    <?php echo count($all_skills['soft']); ?>
                                </span>
                            </h4>
                            <div class="flex flex-wrap gap-1">
                                <?php foreach ($all_skills['soft'] as $skill): ?>
                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs"
                                          title="Digunakan di <?php echo $skill['project_count']; ?> project">
                                        <?php echo htmlspecialchars($skill['name']); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Tools -->
                        <?php if (!empty($all_skills['tool'])): ?>
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-2 flex items-center gap-2 text-sm">
                                <span class="iconify text-purple-600" data-icon="mdi:tools" data-width="16"></span>
                                Tools
                                <span class="bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded-full">
                                    <?php echo count($all_skills['tool']); ?>
                                </span>
                            </h4>
                            <div class="flex flex-wrap gap-1">
                                <?php foreach ($all_skills['tool'] as $skill): ?>
                                    <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded text-xs"
                                          title="Digunakan di <?php echo $skill['project_count']; ?> project">
                                        <?php echo htmlspecialchars($skill['name']); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-3">
            <!-- Bio Section -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-8">
                <h2 class="text-2xl font-bold text-blue-900 mb-4 flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:account-circle" data-width="24"></span>
                    Tentang Saya
                </h2>
                
                <?php if (!empty($student['bio'])): ?>
                    <div class="prose max-w-none text-gray-700 leading-relaxed">
                        <?php echo nl2br(htmlspecialchars($student['bio'])); ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 italic">Mahasiswa ini belum menambahkan deskripsi tentang diri mereka.</p>
                <?php endif; ?>
            </div>

            <!-- Projects Portfolio -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-blue-900 flex items-center gap-2">
                        <span class="iconify" data-icon="mdi:folder-multiple" data-width="24"></span>
                        Portofolio Project
                    </h2>
                    <span class="bg-cyan-100 text-cyan-800 px-3 py-1 rounded-full text-sm font-semibold">
                        <?php echo $total_projects; ?> Project
                    </span>
                </div>

                <?php if ($total_projects > 0): ?>
                    <div class="space-y-6">
                        <?php foreach ($projects as $project): ?>
                            <div class="border border-gray-200 rounded-xl overflow-hidden hover:shadow-lg transition-all duration-300 project-card">
                                <!-- Project Header -->
                                <div class="bg-gradient-to-r from-gray-50 to-gray-100 p-6 border-b border-gray-200">
                                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                                        <div class="flex-1">
                                            <h3 class="text-xl font-bold text-blue-900 mb-2"><?php echo htmlspecialchars($project['title']); ?></h3>
                                            
                                            <div class="flex flex-wrap gap-4 text-sm text-gray-600 mb-3">
                                                <?php if (!empty($project['project_year'])): ?>
                                                    <span class="flex items-center gap-1">
                                                        <span class="iconify" data-icon="mdi:calendar" data-width="14"></span>
                                                        <?php echo htmlspecialchars($project['project_year']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($project['project_type'])): ?>
                                                    <span class="flex items-center gap-1">
                                                        <span class="iconify" data-icon="mdi:tag" data-width="14"></span>
                                                        <?php echo htmlspecialchars($project['project_type']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($project['category'])): ?>
                                                    <span class="flex items-center gap-1">
                                                        <span class="iconify" data-icon="mdi:folder" data-width="14"></span>
                                                        <?php echo htmlspecialchars($project['category']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Project Links -->
                                        <?php if (!empty($project['github_url']) || !empty($project['demo_url']) || !empty($project['figma_url'])): ?>
                                        <div class="flex flex-wrap gap-2">
                                            <?php if (!empty($project['github_url'])): ?>
                                                <a href="<?php echo htmlspecialchars($project['github_url']); ?>" 
                                                   target="_blank"
                                                   class="bg-gray-800 text-white px-3 py-2 rounded-lg text-sm font-semibold hover:bg-gray-900 transition-colors flex items-center gap-1">
                                                    <span class="iconify" data-icon="mdi:github" data-width="14"></span>
                                                    Code
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($project['demo_url'])): ?>
                                                <a href="<?php echo htmlspecialchars($project['demo_url']); ?>" 
                                                   target="_blank"
                                                   class="bg-cyan-500 text-white px-3 py-2 rounded-lg text-sm font-semibold hover:bg-cyan-600 transition-colors flex items-center gap-1">
                                                    <span class="iconify" data-icon="mdi:play" data-width="14"></span>
                                                    Demo
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($project['figma_url'])): ?>
                                                <a href="<?php echo htmlspecialchars($project['figma_url']); ?>" 
                                                   target="_blank"
                                                   class="bg-purple-500 text-white px-3 py-2 rounded-lg text-sm font-semibold hover:bg-purple-600 transition-colors flex items-center gap-1">
                                                    <span class="iconify" data-icon="mdi:palette" data-width="14"></span>
                                                    Design
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Project Content -->
                                <div class="p-6">
                                    <!-- Project Description -->
                                    <?php if (!empty($project['description'])): ?>
                                        <div class="mb-4">
                                            <h4 class="font-semibold text-gray-800 mb-2">Deskripsi Project</h4>
                                            <p class="text-gray-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Project Skills -->
                                    <?php if (!empty($project['skills_detail'])): ?>
                                        <div class="mb-4">
                                            <h4 class="font-semibold text-gray-800 mb-2">Teknologi & Tools</h4>
                                            <div class="flex flex-wrap gap-2">
                                                <?php foreach ($project['skills_detail'] as $skill): 
                                                    $color_class = [
                                                        'technical' => 'bg-blue-100 text-blue-800 border border-blue-200',
                                                        'soft' => 'bg-green-100 text-green-800 border border-green-200',
                                                        'tool' => 'bg-purple-100 text-purple-800 border border-purple-200'
                                                    ][$skill['skill_type']] ?? 'bg-gray-100 text-gray-800 border border-gray-200';
                                                ?>
                                                    <span class="inline-block <?php echo $color_class; ?> px-3 py-1 rounded-full text-xs font-medium">
                                                        <?php echo htmlspecialchars($skill['name']); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Project Images Gallery -->
                                    <?php 
                                    // Gabungkan gambar utama dengan gallery images
                                    $all_project_images = [];
                                    if (!empty($project['image_path'])) {
                                        $all_project_images[] = $project['image_path'];
                                    }
                                    if (!empty($project['gallery_images'])) {
                                        $gallery_images = explode('|||', $project['gallery_images']);
                                        $all_project_images = array_merge($all_project_images, $gallery_images);
                                    }
                                    $all_project_images = array_unique(array_filter($all_project_images)); // Hapus duplikat dan empty
                                    ?>

                                    <?php if (!empty($all_project_images)): ?>
                                        <div class="mb-4">
                                            <h4 class="font-semibold text-gray-800 mb-3">Galeri Project</h4>
                                            <div class="relative">
                                                <!-- Gallery Container -->
                                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                                    <!-- Main Image Display -->
                                                    <div class="mb-4 flex justify-center">
                                                        <img id="mainImage-<?php echo $project['id']; ?>" 
                                                             src="<?php echo htmlspecialchars($all_project_images[0]); ?>" 
                                                             alt="Project Image" 
                                                             class="max-w-full max-h-80 object-contain rounded-lg border border-gray-300 cursor-pointer"
                                                             onclick="openImageModal('<?php echo htmlspecialchars($all_project_images[0]); ?>')">
                                                    </div>
                                                    
                                                    <!-- Navigation & Thumbnails -->
                                                    <?php if (count($all_project_images) > 1): ?>
                                                        <div class="flex items-center justify-center gap-4">
                                                            <!-- Left Arrow -->
                                                            <button onclick="prevImage(<?php echo $project['id']; ?>, <?php echo count($all_project_images); ?>)"
                                                                    class="bg-white border border-gray-300 rounded-full p-2 hover:bg-gray-100 transition-colors shadow-sm">
                                                                <span class="iconify text-gray-600" data-icon="mdi:chevron-left" data-width="20"></span>
                                                            </button>
                                                            
                                                            <!-- Thumbnails -->
                                                            <div class="flex gap-2 overflow-x-auto py-2 px-4 justify-center flex-1 max-w-2xl">
                                                                <?php foreach ($all_project_images as $index => $image): ?>
                                                                    <img src="<?php echo htmlspecialchars($image); ?>" 
                                                                         alt="Thumbnail <?php echo $index + 1; ?>"
                                                                         class="w-16 h-16 object-cover rounded border-2 cursor-pointer transition-all <?php echo $index === 0 ? 'border-cyan-500' : 'border-gray-300'; ?>"
                                                                         onclick="changeMainImage(<?php echo $project['id']; ?>, '<?php echo htmlspecialchars($image); ?>', <?php echo $index; ?>)"
                                                                         data-project-id="<?php echo $project['id']; ?>"
                                                                         data-image-index="<?php echo $index; ?>">
                                                                <?php endforeach; ?>
                                                            </div>
                                                            
                                                            <!-- Right Arrow -->
                                                            <button onclick="nextImage(<?php echo $project['id']; ?>, <?php echo count($all_project_images); ?>)"
                                                                    class="bg-white border border-gray-300 rounded-full p-2 hover:bg-gray-100 transition-colors shadow-sm">
                                                                <span class="iconify text-gray-600" data-icon="mdi:chevron-right" data-width="20"></span>
                                                            </button>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Project Video -->
                                    <?php if (!empty($project['video_url'])): ?>
                                        <div class="mb-4">
                                            <h4 class="font-semibold text-gray-800 mb-2">Video Demo</h4>
                                            <div class="border border-gray-200 rounded-lg overflow-hidden">
                                                <video controls class="w-full max-w-2xl mx-auto" poster="<?php echo !empty($project['image_path']) ? htmlspecialchars(explode(',', $project['image_path'])[0]) : ''; ?>">
                                                    <source src="<?php echo htmlspecialchars($project['video_url']); ?>" type="video/mp4">
                                                    Browser Anda tidak mendukung tag video.
                                                </video>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Project Details -->
                                    <div class="flex flex-wrap justify-between items-center gap-4 text-sm">
                                        <?php if (!empty($project['project_duration'])): ?>
                                            <div class="flex items-center gap-2">
                                                <span class="iconify text-gray-400" data-icon="mdi:clock-outline" data-width="16"></span>
                                                <span class="text-gray-600"><?php echo htmlspecialchars($project['project_duration']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($project['status'])): ?>
                                        <div> <span class="inline-flex items-center gap-1 bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-medium">
                                                <span class="iconify" data-icon="mdi:check-circle" data-width="12"></span>
                                                Status: <?php echo htmlspecialchars($project['status']); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12 bg-gray-50 rounded-xl">
                        <span class="iconify text-gray-400 mx-auto mb-4" data-icon="mdi:folder-open" data-width="64"></span>
                        <h3 class="text-xl font-bold text-blue-900 mb-2">Belum Ada Project</h3>
                        <p class="text-gray-600 max-w-md mx-auto">Mahasiswa ini belum menambahkan project ke portofolio mereka.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
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

<style>
.prose {
    line-height: 1.6;
}

.project-card {
    transition: all 0.3s ease;
}

.project-card:hover {
    transform: translateY(-4px);
}

/* Style untuk thumbnail aktif */
.thumbnail-active {
    border-color: #06b6d4 !important;
    transform: scale(1.05);
}
</style>

<script>
// Gallery Navigation Functions
let currentImageIndex = {};

function changeMainImage(projectId, imageSrc, index) {
    // Update main image
    document.getElementById('mainImage-' + projectId).src = imageSrc;
    
    // Update current index
    currentImageIndex[projectId] = index;
    
    // Update thumbnail borders
    updateThumbnailBorders(projectId, index);
}

function prevImage(projectId, totalImages) {
    let currentIndex = currentImageIndex[projectId] || 0;
    let newIndex = (currentIndex - 1 + totalImages) % totalImages;
    
    // Get the image source from thumbnail
    const thumbnails = document.querySelectorAll(`img[data-project-id="${projectId}"]`);
    if (thumbnails[newIndex]) {
        changeMainImage(projectId, thumbnails[newIndex].src, newIndex);
    }
}

function nextImage(projectId, totalImages) {
    let currentIndex = currentImageIndex[projectId] || 0;
    let newIndex = (currentIndex + 1) % totalImages;
    
    // Get the image source from thumbnail
    const thumbnails = document.querySelectorAll(`img[data-project-id="${projectId}"]`);
    if (thumbnails[newIndex]) {
        changeMainImage(projectId, thumbnails[newIndex].src, newIndex);
    }
}

function updateThumbnailBorders(projectId, activeIndex) {
    const thumbnails = document.querySelectorAll(`img[data-project-id="${projectId}"]`);
    thumbnails.forEach((thumb, index) => {
        if (index === activeIndex) {
            thumb.classList.remove('border-gray-300');
            thumb.classList.add('border-cyan-500');
            thumb.classList.add('thumbnail-active');
        } else {
            thumb.classList.remove('border-cyan-500');
            thumb.classList.remove('thumbnail-active');
            thumb.classList.add('border-gray-300');
        }
    });
}

// Initialize first image as active for each project
document.addEventListener('DOMContentLoaded', function() {
    const projects = document.querySelectorAll('[id^="mainImage-"]');
    projects.forEach(mainImage => {
        const projectId = mainImage.id.split('-')[1];
        currentImageIndex[projectId] = 0;
        updateThumbnailBorders(projectId, 0);
    });
});

// Image Modal Functions
function openImageModal(imageSrc) {
    document.getElementById('modalImage').src = imageSrc;
    document.getElementById('imageModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeImageModal() {
    document.getElementById('imageModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeImageModal();
    }
});

// Close modal when clicking outside image
document.getElementById('imageModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeImageModal();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>