<?php
include '../../includes/config.php';
include '../../includes/functions.php';

if (!isLoggedIn() || getUserRole() != 'student') {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: projects.php");
    exit();
}

$project_id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT * FROM projects WHERE id = ? AND student_id = ?");
if (!$stmt) {
    die("Error preparing statement: " . $conn->error);
}
$stmt->bind_param("ii", $project_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: projects.php?error=project_not_found");
    exit();
}

$project = $result->fetch_assoc();
$stmt->close();

$images_stmt = $conn->prepare("SELECT id, image_path, is_primary FROM project_images WHERE project_id = ? ORDER BY is_primary DESC, id ASC");
if ($images_stmt) {
    $images_stmt->bind_param("i", $project_id);
    $images_stmt->execute();
    $images_result = $images_stmt->get_result();
    $existing_images = [];
    while ($image = $images_result->fetch_assoc()) {
        $existing_images[] = $image;
    }
    $images_stmt->close();
} else {
    $existing_images = [];
}

$existing_skills = [];
$skills_stmt = $conn->prepare("
    SELECT s.name, s.skill_type 
    FROM skills s 
    JOIN project_skills ps ON s.id = ps.skill_id 
    WHERE ps.project_id = ?
");
if ($skills_stmt) {
    $skills_stmt->bind_param("i", $project_id);
    $skills_stmt->execute();
    $skills_result = $skills_stmt->get_result();
    while ($skill = $skills_result->fetch_assoc()) {
        $existing_skills[] = $skill['name'];
    }
    $skills_stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $category = sanitize($_POST['category']);
    $status = sanitize($_POST['status']);
    $project_type = sanitize($_POST['project_type']);
    $project_year = sanitize($_POST['project_year']);
    $project_duration = sanitize($_POST['project_duration'] ?? '');
    $github_url = sanitize($_POST['github_url'] ?? '');
    $figma_url = sanitize($_POST['figma_url'] ?? '');
    $demo_url = sanitize($_POST['demo_url'] ?? '');
    $video_url = sanitize($_POST['video_url'] ?? '');
    $skills_input = $_POST['skills'] ?? [];
    $delete_images = $_POST['delete_images'] ?? [];

    // Validation
    if (empty($title) || empty($description)) {
        $error = "Judul dan deskripsi wajib diisi!";
    } elseif (empty($category) || empty($status) || empty($project_type) || empty($project_year)) {
        $error = "Kategori, status, tipe proyek, dan tahun proyek wajib diisi!";
    } elseif (empty($skills_input)) {
        $error = "Pilih minimal 1 skill!";
    } else {
        $main_image_path = $project['image_path'];
        
        if (isset($_FILES['project_image']) && $_FILES['project_image']['error'] === UPLOAD_ERR_OK) {
            $upload_result = handleFileUpload($_FILES['project_image'], $user_id);
            if ($upload_result['success']) {
                $main_image_path = $upload_result['file_path'];
            } else {
                $error = $upload_result['error'];
            }
        }

        $gallery_images = [];
        if (isset($_FILES['project_gallery']) && !empty($_FILES['project_gallery']['name'][0])) {
            foreach ($_FILES['project_gallery']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['project_gallery']['error'][$key] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['project_gallery']['name'][$key],
                        'type' => $_FILES['project_gallery']['type'][$key],
                        'tmp_name' => $_FILES['project_gallery']['tmp_name'][$key],
                        'error' => $_FILES['project_gallery']['error'][$key],
                        'size' => $_FILES['project_gallery']['size'][$key]
                    ];
                    
                    $upload_result = handleFileUpload($file, $user_id);
                    if ($upload_result['success']) {
                        $gallery_images[] = $upload_result['file_path'];
                    } else {
                        $error = $upload_result['error'];
                        break;
                    }
                }
            }
        }

        if (!$error) {
            $conn->begin_transaction();
            
            try {
                $update_stmt = $conn->prepare("UPDATE projects SET title = ?, description = ?, image_path = ?, github_url = ?, figma_url = ?, demo_url = ?, video_url = ?, category = ?, status = ?, project_type = ?, project_year = ?, project_duration = ? WHERE id = ? AND student_id = ?");
                
                if (!$update_stmt) {
                    throw new Exception("Error preparing update statement: " . $conn->error);
                }
                
                $update_stmt->bind_param("ssssssssssssii", $title, $description, $main_image_path, $github_url, $figma_url, $demo_url, $video_url, $category, $status, $project_type, $project_year, $project_duration, $project_id, $user_id);
                
                if ($update_stmt->execute()) {
                    if (!empty($delete_images)) {
                        $delete_stmt = $conn->prepare("DELETE FROM project_images WHERE id = ? AND project_id = ?");
                        if ($delete_stmt) {
                            foreach ($delete_images as $image_id) {
                                $image_id = intval($image_id);
                                $delete_stmt->bind_param("ii", $image_id, $project_id);
                                $delete_stmt->execute();
                            }
                            $delete_stmt->close();
                        }
                    }
                    
                    if (!empty($gallery_images)) {
                        $image_stmt = $conn->prepare("INSERT INTO project_images (project_id, image_path, is_primary) VALUES (?, ?, ?)");
                        if ($image_stmt) {
                            foreach ($gallery_images as $image_path) {
                                $is_primary = 0;
                                $image_stmt->bind_param("isi", $project_id, $image_path, $is_primary);
                                $image_stmt->execute();
                            }
                            $image_stmt->close();
                        }
                    }
                    
                    $delete_skills_stmt = $conn->prepare("DELETE FROM project_skills WHERE project_id = ?");
                    if ($delete_skills_stmt) {
                        $delete_skills_stmt->bind_param("i", $project_id);
                        $delete_skills_stmt->execute();
                        $delete_skills_stmt->close();
                    }
                    
                    foreach ($skills_input as $skill_name) {
                        $skill_name = trim($skill_name);
                        if (!empty($skill_name)) {
                            $skill_stmt = $conn->prepare("SELECT id FROM skills WHERE name = ?");
                            if ($skill_stmt) {
                                $skill_stmt->bind_param("s", $skill_name);
                                $skill_stmt->execute();
                                $skill_result = $skill_stmt->get_result();
                                
                                if ($skill_result->num_rows > 0) {
                                    $skill = $skill_result->fetch_assoc();
                                    $skill_id = $skill['id'];
                                } else {
                                    $insert_skill = $conn->prepare("INSERT INTO skills (name, skill_type) VALUES (?, 'technical')");
                                    if ($insert_skill) {
                                        $insert_skill->bind_param("s", $skill_name);
                                        $insert_skill->execute();
                                        $skill_id = $conn->insert_id;
                                        $insert_skill->close();
                                    } else {
                                        continue; 
                                    }
                                }
                                $skill_stmt->close();
                                
                                $link_skill = $conn->prepare("INSERT INTO project_skills (project_id, skill_id) VALUES (?, ?)");
                                if ($link_skill) {
                                    $link_skill->bind_param("ii", $project_id, $skill_id);
                                    $link_skill->execute();
                                    $link_skill->close();
                                }
                            }
                        }
                    }
                    
                    $conn->commit();
                    $success = "Proyek berhasil diperbarui!";
                    
                    $refresh_stmt = $conn->prepare("SELECT * FROM projects WHERE id = ? AND student_id = ?");
                    if ($refresh_stmt) {
                        $refresh_stmt->bind_param("ii", $project_id, $user_id);
                        $refresh_stmt->execute();
                        $result = $refresh_stmt->get_result();
                        $project = $result->fetch_assoc();
                        $refresh_stmt->close();
                    }
                    
                    $refresh_images_stmt = $conn->prepare("SELECT id, image_path, is_primary FROM project_images WHERE project_id = ? ORDER BY is_primary DESC, id ASC");
                    if ($refresh_images_stmt) {
                        $refresh_images_stmt->bind_param("i", $project_id);
                        $refresh_images_stmt->execute();
                        $images_result = $refresh_images_stmt->get_result();
                        $existing_images = [];
                        while ($image = $images_result->fetch_assoc()) {
                            $existing_images[] = $image;
                        }
                        $refresh_images_stmt->close();
                    }
                    
                    $refresh_skills_stmt = $conn->prepare("
                        SELECT s.name
                        FROM skills s 
                        JOIN project_skills ps ON s.id = ps.skill_id 
                        WHERE ps.project_id = ?
                    ");
                    if ($refresh_skills_stmt) {
                        $refresh_skills_stmt->bind_param("i", $project_id);
                        $refresh_skills_stmt->execute();
                        $skills_result = $refresh_skills_stmt->get_result();
                        $existing_skills = [];
                        while ($skill = $skills_result->fetch_assoc()) {
                            $existing_skills[] = $skill['name'];
                        }
                        $refresh_skills_stmt->close();
                    }
                    
                } else {
                    throw new Exception("Gagal memperbarui proyek: " . $conn->error);
                }
                
                $update_stmt->close();
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}

$skills_by_category = [
    'technical' => [],
    'soft' => [],
    'tool' => []
];

$categorized_skills_stmt = $conn->prepare("SELECT name, skill_type FROM skills ORDER BY skill_type, name");
if ($categorized_skills_stmt) {
    $categorized_skills_stmt->execute();
    $categorized_result = $categorized_skills_stmt->get_result();
    while ($skill = $categorized_result->fetch_assoc()) {
        $skills_by_category[$skill['skill_type']][] = $skill['name'];
    }
    $categorized_skills_stmt->close();
}

function handleFileUpload($file, $user_id) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024;
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'Ukuran file maksimal 5MB'];
    }
    
    $file_info = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file['tmp_name']);
    if (!in_array($file_info, $allowed_types)) {
        return ['success' => false, 'error' => 'Hanya file gambar (JPG, PNG, GIF, WebP) yang diizinkan'];
    }
    
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/studenthub/uploads/projects/' . $user_id . '/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'project_' . time() . '_' . uniqid() . '.' . $file_ext;
    $file_path = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        return ['success' => true, 'file_path' => '/studenthub/uploads/projects/' . $user_id . '/' . $filename];
    } else {
        return ['success' => false, 'error' => 'Gagal mengupload file'];
    }
}
?>

<?php include '../../includes/header.php'; ?>

<div class="px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="flex justify-between items-center w-full mb-8">
        <div class="flex-1">
            <h1 class="text-3xl font-bold text-blue-900 flex items-center gap-3">
                <span class="iconify" data-icon="mdi:pencil-box" data-width="32"></span>
                Edit Proyek: <?php echo htmlspecialchars($project['title']); ?>
            </h1>
            <p class="text-gray-600 mt-2">Perbarui informasi proyek portofolio Anda</p>
        </div>
        <div class="flex-shrink-0">
            <button
                type="button"
                class="bg-blue-500/10 text-blue-700 px-6 py-3 rounded-xl font-semibold hover:bg-blue-500/20 transition-colors duration-300 border border-blue-200 flex items-center gap-2 whitespace-nowrap"
                aria-label="Kembali"
                onclick="(function(){ if (history.length > 1) { history.back(); } else { window.location.href = 'project-detail.php?id=<?php echo $project_id; ?>'; } })()"
            >
                <span class="iconify" data-icon="mdi:arrow-left" data-width="18"></span>
                Kembali
            </button>
            <noscript>
                <a href="project-detail.php?id=<?php echo $project_id; ?>" class="bg-blue-500/10 text-blue-700 px-6 py-3 rounded-xl font-semibold hover:bg-blue-500/20 transition-colors duration-300 border border-blue-200 flex items-center gap-2 whitespace-nowrap">
                    <span class="iconify" data-icon="mdi:arrow-left" data-width="18"></span>
                    Kembali ke Detail
                </a>
            </noscript>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($error): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center gap-2">
            <span class="iconify" data-icon="mdi:alert-circle" data-width="20"></span>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center gap-2">
            <span class="iconify" data-icon="mdi:check-circle" data-width="20"></span>
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <!-- Project Form -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
        <form method="POST" action="" enctype="multipart/form-data" class="space-y-8">
            <!-- Basic Information -->
            <div class="space-y-6">
                <h2 class="text-2xl font-bold text-blue-900 flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:information" data-width="24"></span>
                    Informasi Dasar Proyek
                </h2>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Judul Proyek *</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($project['title']); ?>" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors" 
                           placeholder="Contoh: Aplikasi E-Commerce dengan Laravel" required maxlength="255">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Deskripsi Proyek *</label>
                    <textarea name="description" rows="6" 
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors resize-none" 
                              placeholder="Jelaskan proyek menggunakan metode STAR..." required><?php echo htmlspecialchars($project['description']); ?></textarea>
                </div>
            </div>

            <!-- Project Details -->                      
            <div class="space-y-6">
                <h2 class="text-2xl font-bold text-blue-900 flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:clipboard-list" data-width="24"></span>
                    Detail Proyek
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Project Category - Custom Dropdown -->
                    <div class="relative" id="category-dropdown">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Kategori Proyek *</label>
                        
                        <div class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors cursor-pointer bg-white flex items-center justify-between" data-toggle>
                            <div class="flex items-center gap-3">
                                <span class="iconify" data-icon="mdi:tag-outline" data-width="20" data-selected-icon></span>
                                <span data-selected-text><?php echo formatText($project['category']); ?></span>
                            </div>
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="20"></span>
                        </div>
                        
                        <input type="hidden" name="category" id="category-value" value="<?php echo htmlspecialchars($project['category']); ?>" required>
                        
                        <div class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg hidden max-h-60 overflow-y-auto" data-options>
                            <div class="p-2 space-y-1">
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer <?php echo $project['category'] == 'web' ? 'bg-blue-50 text-blue-700' : ''; ?>" data-option data-value="web" data-icon="mdi:web">
                                    <span class="iconify" data-icon="mdi:web" data-width="20"></span>
                                    <span>Web Development</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer <?php echo $project['category'] == 'mobile' ? 'bg-blue-50 text-blue-700' : ''; ?>" data-option data-value="mobile" data-icon="mdi:cellphone">
                                    <span class="iconify" data-icon="mdi:cellphone" data-width="20"></span>
                                    <span>Mobile Development</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer <?php echo $project['category'] == 'data-science' ? 'bg-blue-50 text-blue-700' : ''; ?>" data-option data-value="data-science" data-icon="mdi:chart-bar">
                                    <span class="iconify" data-icon="mdi:chart-bar" data-width="20"></span>
                                    <span>Data Science & AI</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer <?php echo $project['category'] == 'design' ? 'bg-blue-50 text-blue-700' : ''; ?>" data-option data-value="design" data-icon="mdi:palette">
                                    <span class="iconify" data-icon="mdi:palette" data-width="20"></span>
                                    <span>UI/UX Design</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer <?php echo $project['category'] == 'iot' ? 'bg-blue-50 text-blue-700' : ''; ?>" data-option data-value="iot" data-icon="mdi:chip">
                                    <span class="iconify" data-icon="mdi:chip" data-width="20"></span>
                                    <span>IoT & Embedded Systems</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer <?php echo $project['category'] == 'game' ? 'bg-blue-50 text-blue-700' : ''; ?>" data-option data-value="game" data-icon="mdi:gamepad-variant">
                                    <span class="iconify" data-icon="mdi:gamepad-variant" data-width="20"></span>
                                    <span>Game Development</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer <?php echo $project['category'] == 'other' ? 'bg-blue-50 text-blue-700' : ''; ?>" data-option data-value="other" data-icon="mdi:dots-horizontal">
                                    <span class="iconify" data-icon="mdi:dots-horizontal" data-width="20"></span>
                                    <span>Lainnya</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Project Status - Custom Dropdown -->
                    <div class="relative" id="status-dropdown">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status Proyek *</label>
                        
                        <div class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors cursor-pointer bg-white flex items-center justify-between" data-toggle>
                            <div class="flex items-center gap-3">
                                <span class="iconify" data-icon="mdi:progress-clock" data-width="20" data-selected-icon></span>
                                <span data-selected-text><?php echo formatText($project['status']); ?></span>
                            </div>
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="20"></span>
                        </div>
                        
                        <input type="hidden" name="status" id="status-value" value="<?php echo htmlspecialchars($project['status']); ?>" required>
                        
                        <div class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg hidden" data-options>
                            <div class="p-2 space-y-1">
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer <?php echo $project['status'] == 'completed' ? 'bg-blue-50 text-blue-700' : ''; ?>" data-option data-value="completed" data-icon="mdi:check-circle">
                                    <span class="iconify" data-icon="mdi:check-circle" data-width="20"></span>
                                    <span>Selesai</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer <?php echo $project['status'] == 'in-progress' ? 'bg-blue-50 text-blue-700' : ''; ?>" data-option data-value="in-progress" data-icon="mdi:progress-clock">
                                    <span class="iconify" data-icon="mdi:progress-clock" data-width="20"></span>
                                    <span>Dalam Pengerjaan</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer <?php echo $project['status'] == 'prototype' ? 'bg-blue-50 text-blue-700' : ''; ?>" data-option data-value="prototype" data-icon="mdi:flask">
                                    <span class="iconify" data-icon="mdi:flask" data-width="20"></span>
                                    <span>Prototype</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Project Timeline & Credibility -->
            <div class="space-y-4">
                <h2 class="text-2xl font-bold text-blue-900 flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:calendar-clock" data-width="24"></span>
                    Timeline & Kredibilitas Proyek
                </h2>
                
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                    <p class="text-sm text-amber-800 flex items-center gap-2 mb-2">
                        <span class="iconify" data-icon="mdi:alert-circle" data-width="16"></span>
                        <strong>Transparansi untuk Stakeholder</strong>
                    </p>
                    <p class="text-xs text-amber-700">
                        Stakeholder akan menilai proyek berdasarkan bukti yang diberikan. Pastikan informasi akurat dan dapat diverifikasi.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Project Year - Custom Dropdown -->
                    <div class="relative" id="year-dropdown">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tahun Proyek *</label>
                        
                        <div class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors cursor-pointer bg-white flex items-center justify-between" data-toggle>
                            <div class="flex items-center gap-3">
                                <span class="iconify" data-icon="mdi:calendar" data-width="20" data-selected-icon></span>
                                <span data-selected-text><?php echo htmlspecialchars($project['project_year']); ?></span>
                            </div>
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="20"></span>
                        </div>
                        
                        <input type="hidden" name="project_year" id="year-value" value="<?php echo htmlspecialchars($project['project_year']); ?>" required>
                        
                        <div class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg hidden max-h-60 overflow-y-auto" data-options>
                            <div class="p-2 space-y-1">
                                <?php
                                $current_year = date('Y');
                                for ($year = $current_year; $year >= $current_year - 5; $year--) {
                                    $selected = $project['project_year'] == $year ? 'bg-blue-50 text-blue-700' : '';
                                    echo '<div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer '.$selected.'" data-option data-value="'.$year.'" data-icon="mdi:calendar">
                                        <span class="iconify" data-icon="mdi:calendar" data-width="20"></span>
                                        <span>'.$year.'</span>
                                    </div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <!-- Project Duration - Custom Dropdown -->
                    <div class="relative" id="duration-dropdown">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Durasi Pengerjaan</label>
                        
                        <div class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors cursor-pointer bg-white flex items-center justify-between" data-toggle>
                            <div class="flex items-center gap-3">
                                <span class="iconify" data-icon="mdi:clock-outline" data-width="20" data-selected-icon></span>
                                <span data-selected-text><?php echo !empty($project['project_duration']) ? htmlspecialchars($project['project_duration']) : 'Pilih Durasi'; ?></span>
                            </div>
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="20"></span>
                        </div>
                        
                        <input type="hidden" name="project_duration" id="duration-value" value="<?php echo htmlspecialchars($project['project_duration']); ?>">
                        
                        <div class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg hidden max-h-60 overflow-y-auto" data-options>
                            <div class="p-2 space-y-1">
                                <?php
                                $durations = [
                                    '1-2 weeks' => '1-2 Minggu',
                                    '1 month' => '1 Bulan', 
                                    '2-3 months' => '2-3 Bulan',
                                    '4-6 months' => '4-6 Bulan',
                                    '7-12 months' => '7-12 Bulan',
                                    '1+ years' => '1+ Tahun'
                                ];
                                
                                foreach ($durations as $value => $label) {
                                    $selected = $project['project_duration'] == $value ? 'bg-blue-50 text-blue-700' : '';
                                    $icon = $value == '1+ years' ? 'mdi:calendar' : 'mdi:clock';
                                    echo '<div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer '.$selected.'" data-option data-value="'.$value.'" data-icon="'.$icon.'">
                                        <span class="iconify" data-icon="'.$icon.'" data-width="20"></span>
                                        <span>'.$label.'</span>
                                    </div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Project Type - Custom Dropdown -->
                    <div class="relative" id="project-type-dropdown">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tipe Proyek *</label>
                        
                        <div class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors cursor-pointer bg-white flex items-center justify-between" data-toggle>
                            <div class="flex items-center gap-3">
                                <span class="iconify" data-icon="mdi:folder-outline" data-width="20" data-selected-icon></span>
                                <span data-selected-text><?php echo formatText($project['project_type']); ?></span>
                            </div>
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="20"></span>
                        </div>
                        
                        <input type="hidden" name="project_type" id="project-type-value" value="<?php echo htmlspecialchars($project['project_type']); ?>" required>
                        
                        <div class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg hidden max-h-60 overflow-y-auto" data-options>
                            <div class="p-2 space-y-1">
                                <?php
                                $project_types = [
                                    'academic' => ['icon' => 'mdi:school', 'label' => 'Project Akademik'],
                                    'personal' => ['icon' => 'mdi:account', 'label' => 'Project Personal'],
                                    'freelance' => ['icon' => 'mdi:briefcase', 'label' => 'Project Freelance'],
                                    'internship' => ['icon' => 'mdi:office-building', 'label' => 'Project Internship'],
                                    'competition' => ['icon' => 'mdi:trophy', 'label' => 'Project Kompetisi']
                                ];
                                
                                foreach ($project_types as $value => $data) {
                                    $selected = $project['project_type'] == $value ? 'bg-blue-50 text-blue-700' : '';
                                    echo '<div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer '.$selected.'" data-option data-value="'.$value.'" data-icon="'.$data['icon'].'">
                                        <span class="iconify" data-icon="'.$data['icon'].'" data-width="20"></span>
                                        <span>'.$data['label'].'</span>
                                    </div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Skills Section dengan Kategori -->
            <div class="space-y-6">
                <h2 class="text-2xl font-bold text-blue-900 flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:tag-multiple" data-width="24"></span>
                    Keterampilan yang Digunakan *
                </h2>
                
                <!-- Technical Skills -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Technical Skills *</label>
                    <div class="border border-gray-300 rounded-lg p-4 min-h-[100px] bg-blue-50/30">
                        <div id="selected-technical-skills" class="flex flex-wrap gap-2 mb-3">
                            <!-- Selected technical skills will be populated by JavaScript -->
                        </div>
                        <input type="text" id="technical-skill-input" 
                               class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                               placeholder="Ketik technical skill (PHP, React, etc)...">
                        <div id="technical-skill-suggestions" class="hidden mt-2 p-2 bg-white border border-gray-300 rounded max-h-32 overflow-y-auto">
                            <!-- Technical suggestions will appear here -->
                        </div>
                    </div>
                    <p class="text-gray-500 text-xs mt-1">Pilih minimal 1 technical skill</p>
                </div>

                <!-- Soft Skills -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Soft Skills</label>
                    <div class="border border-gray-300 rounded-lg p-4 min-h-[100px] bg-green-50/30">
                        <div id="selected-soft-skills" class="flex flex-wrap gap-2 mb-3">
                            <!-- Selected soft skills will be populated by JavaScript -->
                        </div>
                        <input type="text" id="soft-skill-input" 
                               class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500 focus:border-green-500" 
                               placeholder="Ketik soft skill (Leadership, Communication, etc)...">
                        <div id="soft-skill-suggestions" class="hidden mt-2 p-2 bg-white border border-gray-300 rounded max-h-32 overflow-y-auto">
                            <!-- Soft skill suggestions will appear here -->
                        </div>
                    </div>
                    <p class="text-gray-500 text-xs mt-1">Soft skill yang digunakan dalam proyek</p>
                </div>

                <!-- Tools -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tools & Software</label>
                    <div class="border border-gray-300 rounded-lg p-4 min-h-[100px] bg-purple-50/30">
                        <div id="selected-tool-skills" class="flex flex-wrap gap-2 mb-3">
                            <!-- Selected tool skills will be populated by JavaScript -->
                        </div>
                        <input type="text" id="tool-skill-input" 
                               class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-purple-500 focus:border-purple-500" 
                               placeholder="Ketik tools (Figma, Git, VS Code, etc)...">
                        <div id="tool-skill-suggestions" class="hidden mt-2 p-2 bg-white border border-gray-300 rounded max-h-32 overflow-y-auto">
                            <!-- Tool suggestions will appear here -->
                        </div>
                    </div>
                    <p class="text-gray-500 text-xs mt-1">Software dan tools yang digunakan</p>
                </div>
                
                <!-- Hidden input untuk menyimpan semua skills -->
                <div id="skills-hidden-container">
                    <!-- Dynamic hidden inputs will be added here -->
                </div>
            </div>

            <!-- Media & Links -->
            <div class="space-y-6">
                <h2 class="text-2xl font-bold text-blue-900 flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:link-variant" data-width="24"></span>
                    Media & Links
                </h2>

                <!-- Project Images -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Gambar Utama Proyek</label>
                        <div class="flex items-center gap-4">
                            <?php if (!empty($project['image_path'])): ?>
                                <div class="relative">
                                    <img src="<?php echo htmlspecialchars($project['image_path']); ?>" 
                                         alt="Current project image" 
                                         class="w-32 h-32 object-cover rounded-lg border border-gray-300">
                                </div>
                            <?php endif; ?>
                            <div class="flex-1">
                                <input type="file" name="project_image" accept="image/*" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                <p class="text-gray-500 text-xs mt-1">Format: JPG, PNG, GIF, WebP (Maks. 5MB)</p>
                            </div>
                        </div>
                    </div>

                    <!-- Existing Gallery Images -->
                    <?php if (!empty($existing_images)): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Galeri Gambar Saat Ini</label>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <?php foreach ($existing_images as $image): ?>
                                    <?php if (!$image['is_primary']): ?>
                                        <div class="relative group">
                                            <img src="<?php echo htmlspecialchars($image['image_path']); ?>" 
                                                 alt="Project gallery image" 
                                                 class="w-full h-32 object-cover rounded-lg border border-gray-300">
                                            <div class="absolute inset-0 bg-black bg-opacity-50 rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                                <label class="flex items-center gap-1 text-white text-sm cursor-pointer">
                                                    <input type="checkbox" name="delete_images[]" value="<?php echo $image['id']; ?>" class="rounded">
                                                    Hapus
                                                </label>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tambah Gambar ke Galeri</label>
                        <input type="file" name="project_gallery[]" multiple accept="image/*" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-gray-500 text-xs mt-1">Pilih beberapa gambar untuk galeri proyek</p>
                    </div>
                </div>

                <!-- Project Links -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">GitHub Repository</label>
                        <input type="url" name="github_url" value="<?php echo htmlspecialchars($project['github_url']); ?>" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors" 
                               placeholder="https://github.com/username/repository">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Figma Design</label>
                        <input type="url" name="figma_url" value="<?php echo htmlspecialchars($project['figma_url']); ?>" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors" 
                               placeholder="https://figma.com/file/...">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Demo/Live URL</label>
                        <input type="url" name="demo_url" value="<?php echo htmlspecialchars($project['demo_url']); ?>" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors" 
                               placeholder="https://your-project.com">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Video Demo (YouTube/Vimeo)</label>
                        <input type="url" name="video_url" value="<?php echo htmlspecialchars($project['video_url']); ?>" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors" 
                               placeholder="https://youtube.com/watch?v=...">
                    </div>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="flex justify-end gap-4 pt-6 border-t border-gray-200">
                <a href="project-detail.php?id=<?php echo $project_id; ?>" 
                   class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                    Batal
                </a>
                <button type="submit" 
                        class="px-6 py-3 bg-gradient-to-r from-cyan-500 to-blue-600 text-white rounded-lg hover:from-cyan-600 hover:to-blue-700 transition-all duration-300 font-medium shadow-sm hover:shadow-md">
                    <span class="iconify inline mr-2" data-icon="mdi:content-save" data-width="18"></span>
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize existing skills
    const existingSkills = <?php echo json_encode($existing_skills); ?>;
    const skillsByCategory = <?php echo json_encode($skills_by_category); ?>;
    
    // Initialize skills management
    initializeSkillsManagement(existingSkills, skillsByCategory);
    
    // Initialize dropdowns
    initializeDropdowns();
});

function initializeSkillsManagement(existingSkills, skillsByCategory) {
    const skillCategories = {
        'technical': {
            container: document.getElementById('selected-technical-skills'),
            input: document.getElementById('technical-skill-input'),
            suggestions: document.getElementById('technical-skill-suggestions'),
            hiddenContainer: document.getElementById('skills-hidden-container'),
            color: 'blue'
        },
        'soft': {
            container: document.getElementById('selected-soft-skills'),
            input: document.getElementById('soft-skill-input'),
            suggestions: document.getElementById('soft-skill-suggestions'),
            hiddenContainer: document.getElementById('skills-hidden-container'),
            color: 'green'
        },
        'tool': {
            container: document.getElementById('selected-tool-skills'),
            input: document.getElementById('tool-skill-input'),
            suggestions: document.getElementById('tool-skill-suggestions'),
            hiddenContainer: document.getElementById('skills-hidden-container'),
            color: 'purple'
        }
    };

    // Add existing skills on page load
    existingSkills.forEach(skillName => {
        let category = 'technical'; // default
        
        // Determine category based on existing data
        if (skillsByCategory.soft.includes(skillName)) {
            category = 'soft';
        } else if (skillsByCategory.tool.includes(skillName)) {
            category = 'tool';
        }
        
        addSkill(skillName, category);
    });

    // Setup input handlers for each category
    Object.keys(skillCategories).forEach(category => {
        const { input, suggestions } = skillCategories[category];
        
        input.addEventListener('input', function() {
            const value = this.value.trim();
            if (value.length > 0) {
                showSuggestions(value, category);
            } else {
                suggestions.classList.add('hidden');
            }
        });

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const value = this.value.trim();
                if (value && !isSkillSelected(value)) {
                    addSkill(value, category);
                    this.value = '';
                    suggestions.classList.add('hidden');
                }
            }
        });

        // Close suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!input.contains(e.target) && !suggestions.contains(e.target)) {
                suggestions.classList.add('hidden');
            }
        });
    });

    function showSuggestions(value, category) {
        const { suggestions } = skillCategories[category];
        const availableSkills = skillsByCategory[category] || [];
        
        const filtered = availableSkills.filter(skill => 
            skill.toLowerCase().includes(value.toLowerCase()) && !isSkillSelected(skill)
        );

        if (filtered.length > 0) {
            suggestions.innerHTML = filtered.map(skill => `
                <div class="p-2 hover:bg-gray-100 cursor-pointer rounded skill-suggestion" data-skill="${skill}">
                    ${skill}
                </div>
            `).join('');
            
            suggestions.classList.remove('hidden');
            
            // Add click handlers to suggestions
            suggestions.querySelectorAll('.skill-suggestion').forEach(item => {
                item.addEventListener('click', function() {
                    const skillName = this.getAttribute('data-skill');
                    addSkill(skillName, category);
                    skillCategories[category].input.value = '';
                    suggestions.classList.add('hidden');
                });
            });
        } else {
            suggestions.classList.add('hidden');
        }
    }

    function addSkill(skillName, category) {
        if (isSkillSelected(skillName)) return;
        
        const { container, hiddenContainer, color } = skillCategories[category];
        const skillId = `skill-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
        
        // Create visual tag
        const tag = document.createElement('div');
        tag.className = `inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium bg-${color}-100 text-${color}-800 border border-${color}-200`;
        tag.innerHTML = `
            ${skillName}
            <button type="button" class="text-${color}-600 hover:text-${color}-800" onclick="removeSkill('${skillId}')">
                <span class="iconify" data-icon="mdi:close" data-width="14"></span>
            </button>
        `;
        tag.setAttribute('data-skill-id', skillId);
        container.appendChild(tag);
        
        // Create hidden input
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'skills[]';
        hiddenInput.value = skillName;
        hiddenInput.id = skillId;
        hiddenContainer.appendChild(hiddenInput);
    }

    window.removeSkill = function(skillId) {
        const tag = document.querySelector(`[data-skill-id="${skillId}"]`);
        const hiddenInput = document.getElementById(skillId);
        
        if (tag) tag.remove();
        if (hiddenInput) hiddenInput.remove();
    };

    function isSkillSelected(skillName) {
        return document.querySelectorAll(`input[name="skills[]"][value="${skillName}"]`).length > 0;
    }
}

function initializeDropdowns() {
    // Initialize all dropdowns
    document.querySelectorAll('[id$="-dropdown"]').forEach(dropdown => {
        const toggle = dropdown.querySelector('[data-toggle]');
        const options = dropdown.querySelector('[data-options]');
        const selectedText = dropdown.querySelector('[data-selected-text]');
        const selectedIcon = dropdown.querySelector('[data-selected-icon]');
        const hiddenInput = dropdown.querySelector('input[type="hidden"]');
        
        if (!toggle || !options || !selectedText || !hiddenInput) return;
        
        // Toggle dropdown
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            const isHidden = options.classList.contains('hidden');
            
            // Close all other dropdowns
            document.querySelectorAll('[data-options]').forEach(opt => {
                if (opt !== options) opt.classList.add('hidden');
            });
            
            // Toggle current dropdown
            options.classList.toggle('hidden', !isHidden);
        });
        
        // Handle option selection
        options.querySelectorAll('[data-option]').forEach(option => {
            option.addEventListener('click', function() {
                const value = this.getAttribute('data-value');
                const text = this.textContent.trim();
                const icon = this.getAttribute('data-icon');
                
                // Update display
                selectedText.textContent = text;
                if (selectedIcon) {
                    selectedIcon.setAttribute('data-icon', icon);
                }
                
                // Update hidden input
                hiddenInput.value = value;
                
                // Update visual state
                options.querySelectorAll('[data-option]').forEach(opt => {
                    opt.classList.remove('bg-blue-50', 'text-blue-700');
                });
                this.classList.add('bg-blue-50', 'text-blue-700');
                
                // Close dropdown
                options.classList.add('hidden');
            });
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            options.classList.add('hidden');
        });
    });
}

// Helper function to format text (convert hyphens to spaces and capitalize)
function formatText(text) {
    return text.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}
</script>

<?php include '../../includes/footer.php'; ?>