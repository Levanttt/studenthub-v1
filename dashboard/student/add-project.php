<?php
include '../../includes/config.php';

if (!isLoggedIn() || getUserRole() != 'student') {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $category = sanitize($_POST['category']);
    $status = sanitize($_POST['status']);
    $project_type = sanitize($_POST['project_type']);
    $project_year = sanitize($_POST['project_year']);
    $project_duration = sanitize($_POST['project_duration']);
    $github_url = sanitize($_POST['github_url']);
    $figma_url = sanitize($_POST['figma_url']);
    $demo_url = sanitize($_POST['demo_url']);
    $video_url = sanitize($_POST['video_url']);
    $skills_input = $_POST['skills'] ?? [];

    // Validation
    if (empty($title) || empty($description)) {
        $error = "Judul dan deskripsi wajib diisi!";
    } elseif (empty($category) || empty($status) || empty($project_type) || empty($project_year)) {
        $error = "Kategori, status, tipe proyek, dan tahun proyek wajib diisi!";
    } elseif (empty($skills_input)) {
        $error = "Pilih minimal 1 skill!";
    } else {
        // Handle main image upload (untuk image_path di projects)
        $main_image_path = '';
        if (isset($_FILES['project_image']) && $_FILES['project_image']['error'] === UPLOAD_ERR_OK) {
            $upload_result = handleFileUpload($_FILES['project_image'], $user_id);
            if ($upload_result['success']) {
                $main_image_path = $upload_result['file_path'];
            } else {
                $error = $upload_result['error'];
            }
        }

        // Handle multiple images upload (untuk project_images)
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
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Insert project dengan main image
                $stmt = $conn->prepare("INSERT INTO projects (student_id, title, description, image_path, github_url, figma_url, demo_url, video_url, category, status, project_type, project_year, project_duration) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issssssssssss", $user_id, $title, $description, $main_image_path, $github_url, $figma_url, $demo_url, $video_url, $category, $status, $project_type, $project_year, $project_duration);
                
                if ($stmt->execute()) {
                    $project_id = $conn->insert_id;
                    
                    // Insert gallery images ke tabel project_images
                    if (!empty($gallery_images)) {
                        $image_stmt = $conn->prepare("INSERT INTO project_images (project_id, image_path, is_primary) VALUES (?, ?, ?)");
                        
                        foreach ($gallery_images as $image_path) {
                            $is_primary = 0; // Semua gallery image bukan primary
                            $image_stmt->bind_param("isi", $project_id, $image_path, $is_primary);
                            $image_stmt->execute();
                        }
                        $image_stmt->close();
                    }
                    
                    // Handle skills
                    foreach ($skills_input as $skill_name) {
                        $skill_name = trim($skill_name);
                        if (!empty($skill_name)) {
                            // Check if skill exists, if not create it
                            $skill_stmt = $conn->prepare("SELECT id, skill_type FROM skills WHERE name = ?");
                            $skill_stmt->bind_param("s", $skill_name);
                            $skill_stmt->execute();
                            $skill_result = $skill_stmt->get_result();
                            
                            if ($skill_result->num_rows > 0) {
                                $skill = $skill_result->fetch_assoc();
                                $skill_id = $skill['id'];
                            } else {
                                // Create new skill (default to technical)
                                $insert_skill = $conn->prepare("INSERT INTO skills (name, skill_type) VALUES (?, 'technical')");
                                $insert_skill->bind_param("s", $skill_name);
                                $insert_skill->execute();
                                $skill_id = $conn->insert_id;
                            }
                            
                            // Link skill to project
                            $link_skill = $conn->prepare("INSERT INTO project_skills (project_id, skill_id) VALUES (?, ?)");
                            $link_skill->bind_param("ii", $project_id, $skill_id);
                            $link_skill->execute();
                        }
                    }
                    
                    $conn->commit();
                    $success = "Proyek berhasil ditambahkan!";
                    // Clear form
                    $_POST = array();
                } else {
                    throw new Exception("Gagal menambahkan proyek: " . $conn->error);
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}

// Get existing skills categorized
$skills_by_category = [
    'technical' => [],
    'soft' => [],
    'tool' => []
];

$categorized_skills_stmt = $conn->prepare("SELECT name, skill_type FROM skills ORDER BY skill_type, name");
$categorized_skills_stmt->execute();
$categorized_result = $categorized_skills_stmt->get_result();
while ($skill = $categorized_result->fetch_assoc()) {
    $skills_by_category[$skill['skill_type']][] = $skill['name'];
}

// File upload handler function
function handleFileUpload($file, $user_id) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Check file size
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'Ukuran file maksimal 5MB'];
    }
    
    // Check MIME type
    $file_info = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file['tmp_name']);
    if (!in_array($file_info, $allowed_types)) {
        return ['success' => false, 'error' => 'Hanya file gambar (JPG, PNG, GIF, WebP) yang diizinkan'];
    }
    
    // Create upload directory
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/studenthub/uploads/projects/' . $user_id . '/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'project_' . time() . '_' . uniqid() . '.' . $file_ext;
    $file_path = $upload_dir . $filename;
    
    // Move uploaded file
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
                <span class="iconify" data-icon="mdi:plus-box" data-width="32"></span>
                Tambah Proyek Baru
            </h1>
            <p class="text-gray-600 mt-2">Tunjukkan kemampuan nyata kamu melalui bukti kerja proyek</p>
        </div>
        <div class="flex-shrink-0">
            <a href="index.php" class="bg-blue-500/10 text-blue-700 px-6 py-3 rounded-xl font-semibold hover:bg-blue-500/20 transition-colors duration-300 border border-blue-200 flex items-center gap-2 whitespace-nowrap">
                <span class="iconify" data-icon="mdi:arrow-left" data-width="18"></span>
                Kembali ke Dashboard
            </a>
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
                    <input type="text" name="title" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors" 
                           placeholder="Contoh: Aplikasi E-Commerce dengan Laravel" required maxlength="255">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Deskripsi Proyek *</label>
                    <textarea name="description" rows="6" 
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors resize-none" 
                              placeholder="Jelaskan proyek menggunakan metode STAR..." required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
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
                                <span data-selected-text>Pilih Kategori</span>
                            </div>
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="20"></span>
                        </div>
                        
                        <input type="hidden" name="category" id="category-value" required>
                        
                        <div class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg hidden max-h-60 overflow-y-auto" data-options>
                            <div class="p-2 space-y-1">
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="web" data-icon="mdi:web">
                                    <span class="iconify" data-icon="mdi:web" data-width="20"></span>
                                    <span>Web Development</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="mobile" data-icon="mdi:cellphone">
                                    <span class="iconify" data-icon="mdi:cellphone" data-width="20"></span>
                                    <span>Mobile Development</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="data-science" data-icon="mdi:chart-bar">
                                    <span class="iconify" data-icon="mdi:chart-bar" data-width="20"></span>
                                    <span>Data Science & AI</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="design" data-icon="mdi:palette">
                                    <span class="iconify" data-icon="mdi:palette" data-width="20"></span>
                                    <span>UI/UX Design</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="iot" data-icon="mdi:chip">
                                    <span class="iconify" data-icon="mdi:chip" data-width="20"></span>
                                    <span>IoT & Embedded Systems</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="game" data-icon="mdi:gamepad-variant">
                                    <span class="iconify" data-icon="mdi:gamepad-variant" data-width="20"></span>
                                    <span>Game Development</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="other" data-icon="mdi:dots-horizontal">
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
                                <span data-selected-text>Pilih Status</span>
                            </div>
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="20"></span>
                        </div>
                        
                        <input type="hidden" name="status" id="status-value" required>
                        
                        <div class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg hidden" data-options>
                            <div class="p-2 space-y-1">
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="completed" data-icon="mdi:check-circle">
                                    <span class="iconify" data-icon="mdi:check-circle" data-width="20"></span>
                                    <span>Selesai</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="in-progress" data-icon="mdi:progress-clock">
                                    <span class="iconify" data-icon="mdi:progress-clock" data-width="20"></span>
                                    <span>Dalam Pengerjaan</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="prototype" data-icon="mdi:flask">
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
                                <span data-selected-text>Pilih Tahun</span>
                            </div>
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="20"></span>
                        </div>
                        
                        <input type="hidden" name="project_year" id="year-value" required>
                        
                        <div class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg hidden max-h-60 overflow-y-auto" data-options>
                            <div class="p-2 space-y-1">
                                <?php
                                $current_year = date('Y');
                                for ($year = $current_year; $year >= $current_year - 5; $year--) {
                                    echo '<div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="'.$year.'" data-icon="mdi:calendar">
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
                                <span data-selected-text>Pilih Durasi</span>
                            </div>
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="20"></span>
                        </div>
                        
                        <input type="hidden" name="project_duration" id="duration-value">
                        
                        <div class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg hidden max-h-60 overflow-y-auto" data-options>
                            <div class="p-2 space-y-1">
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="1-2 weeks" data-icon="mdi:clock-fast">
                                    <span class="iconify" data-icon="mdi:clock-fast" data-width="20"></span>
                                    <span>1-2 Minggu</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="1 month" data-icon="mdi:clock-outline">
                                    <span class="iconify" data-icon="mdi:clock-outline" data-width="20"></span>
                                    <span>1 Bulan</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="2-3 months" data-icon="mdi:clock">
                                    <span class="iconify" data-icon="mdi:clock" data-width="20"></span>
                                    <span>2-3 Bulan</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="4-6 months" data-icon="mdi:clock">
                                    <span class="iconify" data-icon="mdi:clock" data-width="20"></span>
                                    <span>4-6 Bulan</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="7-12 months" data-icon="mdi:clock">
                                    <span class="iconify" data-icon="mdi:clock" data-width="20"></span>
                                    <span>7-12 Bulan</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="1+ years" data-icon="mdi:calendar">
                                    <span class="iconify" data-icon="mdi:calendar" data-width="20"></span>
                                    <span>1+ Tahun</span>
                                </div>
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
                                <span data-selected-text>Pilih Tipe Proyek</span>
                            </div>
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="20"></span>
                        </div>
                        
                        <input type="hidden" name="project_type" id="project-type-value" required>
                        
                        <div class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg hidden max-h-60 overflow-y-auto" data-options>
                            <div class="p-2 space-y-1">
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="academic" data-icon="mdi:school">
                                    <span class="iconify" data-icon="mdi:school" data-width="20"></span>
                                    <span>Project Akademik</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="personal" data-icon="mdi:account">
                                    <span class="iconify" data-icon="mdi:account" data-width="20"></span>
                                    <span>Project Personal</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="freelance" data-icon="mdi:briefcase">
                                    <span class="iconify" data-icon="mdi:briefcase" data-width="20"></span>
                                    <span>Project Freelance</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="internship" data-icon="mdi:office-building">
                                    <span class="iconify" data-icon="mdi:office-building" data-width="20"></span>
                                    <span>Project Internship</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="competition" data-icon="mdi:trophy">
                                    <span class="iconify" data-icon="mdi:trophy" data-width="20"></span>
                                    <span>Project Kompetisi</span>
                                </div>
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
                            <!-- Selected technical skills will appear here -->
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
                            <!-- Selected soft skills will appear here -->
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
                            <!-- Selected tool skills will appear here -->
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
                    <span class="iconify" data-icon="mdi:link" data-width="24"></span>
                    Media & Tautan
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Main Project Image -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-3">Gambar Utama Proyek</label>
                        <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-cyan-400 transition-colors duration-300 bg-gray-50/50">
                            <div class="flex flex-col items-center justify-center mb-4">
                                <div class="text-gray-400 mb-3">
                                    <span class="iconify" data-icon="mdi:image" data-width="64"></span>
                                </div>
                                <p class="text-lg font-medium text-gray-700 mb-1">Gambar cover/utama proyek</p>
                                <p class="text-sm text-gray-500">Drag & drop file atau klik untuk memilih</p>
                            </div>
                            
                            <label class="cursor-pointer inline-block">
                                <span class="bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors duration-300 inline-flex items-center gap-2">
                                    <span class="iconify" data-icon="mdi:folder-open" data-width="20"></span>
                                    Pilih File
                                </span>
                                <input type="file" name="project_image" accept="image/*" 
                                    class="hidden" id="project-image-input">
                            </label>
                            
                            <p class="text-xs text-gray-500 mt-4">Max. 5MB per file (JPG, PNG, GIF, WebP)</p>
                            
                            <!-- File names display -->
                            <div id="main-file-names" class="text-sm text-gray-600 mt-3 hidden"></div>
                        </div>
                    </div>

                    <!-- Gallery Images -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-3">Gallery Proyek (Multiple)</label>
                        <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-cyan-400 transition-colors duration-300 bg-gray-50/50">
                            <div class="flex flex-col items-center justify-center mb-4">
                                <div class="text-gray-400 mb-3">
                                    <span class="iconify" data-icon="mdi:image-multiple" data-width="64"></span>
                                </div>
                                <p class="text-lg font-medium text-gray-700 mb-1">Upload screenshot atau mockup</p>
                                <p class="text-sm text-gray-500">Drag & drop file atau klik untuk memilih (max 5 files)</p>
                            </div>
                            
                            <label class="cursor-pointer inline-block">
                                <span class="bg-green-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-green-700 transition-colors duration-300 inline-flex items-center gap-2">
                                    <span class="iconify" data-icon="mdi:folder-multiple-image" data-width="20"></span>
                                    Pilih File
                                </span>
                                <input type="file" name="project_gallery[]" accept="image/*" 
                                    class="hidden" id="project-gallery-input" multiple>
                            </label>
                            
                            <p class="text-xs text-gray-500 mt-4">Max. 5MB per file, maksimal 5 file (JPG, PNG, GIF, WebP)</p>
                            
                            <!-- File names display -->
                            <div id="gallery-file-names" class="text-sm text-gray-600 mt-3 hidden"></div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- GitHub -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">GitHub Repository</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                <span class="iconify" data-icon="mdi:github" data-width="20"></span>
                            </span>
                            <input type="url" name="github_url" value="<?php echo isset($_POST['github_url']) ? htmlspecialchars($_POST['github_url']) : ''; ?>" 
                                   class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors" 
                                   placeholder="https://github.com/username/repo">
                        </div>
                    </div>

                    <!-- Figma -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Figma Design</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                <span class="iconify" data-icon="mdi:palette" data-width="20"></span>
                            </span>
                            <input type="url" name="figma_url" value="<?php echo isset($_POST['figma_url']) ? htmlspecialchars($_POST['figma_url']) : ''; ?>" 
                                   class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors" 
                                   placeholder="https://figma.com/file/...">
                        </div>
                    </div>

                    <!-- Demo -->
                    <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Project Link</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                <span class="iconify" data-icon="mdi:link" data-width="20"></span>
                            </span>
                            <input type="url" name="demo_url" value="<?php echo isset($_POST['demo_url']) ? htmlspecialchars($_POST['demo_url']) : ''; ?>" 
                                class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors" 
                                placeholder="https://your-project-link.com">
                        </div>
                    </div>
                </div>

                <!-- Video URL -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Video URL (YouTube, etc.)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <span class="iconify" data-icon="mdi:video" data-width="20"></span>
                        </span>
                        <input type="url" name="video_url" value="<?php echo isset($_POST['video_url']) ? htmlspecialchars($_POST['video_url']) : ''; ?>" 
                               class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors" 
                               placeholder="https://youtube.com/watch?v=...">
                    </div>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 pt-8 border-t border-gray-200">
                <button type="submit" class="bg-gradient-to-r from-cyan-500 to-blue-600 text-white px-8 py-4 rounded-xl font-semibold hover:from-cyan-600 hover:to-blue-700 transition-all duration-300 flex items-center justify-center gap-2 shadow-md flex-1">
                    <span class="iconify" data-icon="mdi:rocket-launch" data-width="20"></span>
                    Publikasikan Proyek
                </button>
                <a href="index.php" class="bg-gray-100 text-gray-700 px-8 py-4 rounded-xl font-semibold hover:bg-gray-200 transition-colors duration-300 border border-gray-200 flex items-center justify-center gap-2">
                    <span class="iconify" data-icon="mdi:close" data-width="20"></span>
                    Batal
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// Custom Dropdown System
class CustomDropdown {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.toggle = this.container.querySelector('[data-toggle]');
        this.options = this.container.querySelector('[data-options]');
        this.hiddenInput = this.container.querySelector('input[type="hidden"]');
        this.selectedText = this.container.querySelector('[data-selected-text]');
        this.selectedIcon = this.container.querySelector('[data-selected-icon]');
        
        this.init();
    }
    
    init() {
        // Toggle dropdown
        this.toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            this.options.classList.toggle('hidden');
        });
        
        // Close when clicking outside
        document.addEventListener('click', () => {
            this.options.classList.add('hidden');
        });
        
        // Handle option selection
        this.options.querySelectorAll('[data-option]').forEach(option => {
            option.addEventListener('click', () => {
                const value = option.getAttribute('data-value');
                const text = option.textContent.trim();
                const icon = option.getAttribute('data-icon');
                
                this.hiddenInput.value = value;
                this.selectedText.textContent = text;
                this.selectedIcon.setAttribute('data-icon', icon);
                
                // Update selected state
                this.options.querySelectorAll('[data-option]').forEach(opt => {
                    opt.classList.remove('bg-blue-50', 'text-blue-700');
                });
                option.classList.add('bg-blue-50', 'text-blue-700');
                
                this.options.classList.add('hidden');
            });
        });
        
        // Prevent options from closing when clicking inside
        this.options.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }
}

// Initialize all custom dropdowns
document.addEventListener('DOMContentLoaded', function() {
    new CustomDropdown('project-type-dropdown');
    new CustomDropdown('category-dropdown');
    new CustomDropdown('status-dropdown');
    new CustomDropdown('year-dropdown');
    new CustomDropdown('duration-dropdown');
});

// Skills Management System dengan Kategori
const skillCategories = {
    technical: { 
        color: 'blue', 
        container: 'selected-technical-skills', 
        input: 'technical-skill-input', 
        suggestions: 'technical-skill-suggestions' 
    },
    soft: { 
        color: 'green', 
        container: 'selected-soft-skills', 
        input: 'soft-skill-input', 
        suggestions: 'soft-skill-suggestions' 
    },
    tool: { 
        color: 'purple', 
        container: 'selected-tool-skills', 
        input: 'tool-skill-input', 
        suggestions: 'tool-skill-suggestions' 
    }
};

const skillsByCategory = {
    technical: <?php echo json_encode($skills_by_category['technical']); ?>,
    soft: <?php echo json_encode($skills_by_category['soft']); ?>,
    tool: <?php echo json_encode($skills_by_category['tool']); ?>
};

const selectedSkills = new Set();

// Initialize event listeners untuk setiap kategori
Object.keys(skillCategories).forEach(category => {
    const config = skillCategories[category];
    const inputElement = document.getElementById(config.input);
    
    inputElement.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const skill = this.value.trim();
            if (skill && !selectedSkills.has(skill)) {
                addSkill(skill, category);
                this.value = '';
                hideSuggestions(category);
            }
        }
    });

    inputElement.addEventListener('input', function() {
        const query = this.value.trim();
        if (query.length > 1) {
            showSuggestions(query, category);
        } else {
            hideSuggestions(category);
        }
    });
});

function addSkill(skill, category) {
    selectedSkills.add(skill);
    
    // Add to visual display
    const config = skillCategories[category];
    const skillElement = document.createElement('div');
    skillElement.className = `bg-${config.color}-100 text-${config.color}-800 px-3 py-1 rounded-full text-sm flex items-center gap-1`;
    skillElement.innerHTML = `
        ${skill}
        <button type="button" onclick="removeSkill('${skill}')" class="text-${config.color}-600 hover:text-${config.color}-800">
            <span class="iconify" data-icon="mdi:close" data-width="14"></span>
        </button>
    `;
    document.getElementById(config.container).appendChild(skillElement);
    
    // Add hidden input
    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = 'skills[]';
    hiddenInput.value = skill;
    document.getElementById('skills-hidden-container').appendChild(hiddenInput);
}

function removeSkill(skill) {
    selectedSkills.delete(skill);
    
    // Remove from all visual displays
    Object.keys(skillCategories).forEach(category => {
        const container = document.getElementById(skillCategories[category].container);
        Array.from(container.children).forEach(child => {
            if (child.textContent.includes(skill)) {
                container.removeChild(child);
            }
        });
    });
    
    // Remove hidden input
    const hiddenInputs = document.querySelectorAll('#skills-hidden-container input');
    hiddenInputs.forEach(input => {
        if (input.value === skill) {
            input.remove();
        }
    });
}

function showSuggestions(query, category) {
    const config = skillCategories[category];
    const suggestions = skillsByCategory[category].filter(skill => 
        skill.toLowerCase().includes(query.toLowerCase()) && !selectedSkills.has(skill)
    );
    
    const suggestionsContainer = document.getElementById(config.suggestions);
    suggestionsContainer.innerHTML = '';
    
    if (suggestions.length > 0) {
        suggestions.forEach(skill => {
            const suggestion = document.createElement('div');
            suggestion.className = 'p-2 hover:bg-gray-100 cursor-pointer rounded';
            suggestion.textContent = skill;
            suggestion.onclick = () => {
                addSkill(skill, category);
                document.getElementById(config.input).value = '';
                hideSuggestions(category);
            };
            suggestionsContainer.appendChild(suggestion);
        });
        suggestionsContainer.classList.remove('hidden');
    } else {
        hideSuggestions(category);
    }
}

function hideSuggestions(category) {
    const config = skillCategories[category];
    document.getElementById(config.suggestions).classList.add('hidden');
}

// Hide suggestions when clicking outside
document.addEventListener('click', function(e) {
    Object.keys(skillCategories).forEach(category => {
        const config = skillCategories[category];
        if (!e.target.closest(`#${config.input}`) && !e.target.closest(`#${config.suggestions}`)) {
            hideSuggestions(category);
        }
    });
});

// Form validation - require at least 1 technical skill
document.querySelector('form').addEventListener('submit', function(e) {
    const technicalSkills = document.getElementById('selected-technical-skills').children;
    if (technicalSkills.length === 0) {
        e.preventDefault();
        alert('Pilih minimal 1 technical skill!');
        document.getElementById('technical-skill-input').focus();
    }
});

// File upload display untuk main image
const mainFileInput = document.getElementById('project-image-input');
const mainFileNamesDisplay = document.getElementById('main-file-names');

mainFileInput.addEventListener('change', function(e) {
    if (this.files.length > 0) {
        let fileList = 'File terpilih:<br>';
        for (let i = 0; i < this.files.length; i++) {
            fileList += `• ${this.files[i].name}<br>`;
        }
        mainFileNamesDisplay.innerHTML = fileList;
        mainFileNamesDisplay.classList.remove('hidden');
    } else {
        mainFileNamesDisplay.classList.add('hidden');
    }
});

// File upload display untuk gallery images
const galleryFileInput = document.getElementById('project-gallery-input');
const galleryFileNamesDisplay = document.getElementById('gallery-file-names');

galleryFileInput.addEventListener('change', function(e) {
    if (this.files.length > 0) {
        let fileList = 'File terpilih:<br>';
        const maxFiles = 5;
        
        // Limit to 5 files
        if (this.files.length > maxFiles) {
            fileList = `Maksimal ${maxFiles} file!<br>`;
            // Reset files
            this.value = '';
        } else {
            for (let i = 0; i < this.files.length && i < maxFiles; i++) {
                fileList += `• ${this.files[i].name}<br>`;
            }
        }
        
        galleryFileNamesDisplay.innerHTML = fileList;
        galleryFileNamesDisplay.classList.remove('hidden');
    } else {
        galleryFileNamesDisplay.classList.add('hidden');
    }
});

// Drag and drop functionality untuk main image
const mainUploadArea = mainFileInput.closest('div');

mainUploadArea.addEventListener('dragover', function(e) {
    e.preventDefault();
    this.classList.add('border-cyan-400', 'bg-cyan-50');
});

mainUploadArea.addEventListener('dragleave', function(e) {
    e.preventDefault();
    this.classList.remove('border-cyan-400', 'bg-cyan-50');
});

mainUploadArea.addEventListener('drop', function(e) {
    e.preventDefault();
    this.classList.remove('border-cyan-400', 'bg-cyan-50');
    
    if (e.dataTransfer.files.length > 0) {
        mainFileInput.files = e.dataTransfer.files;
        
        let fileList = 'File terpilih:<br>';
        for (let i = 0; i < e.dataTransfer.files.length; i++) {
            fileList += `• ${e.dataTransfer.files[i].name}<br>`;
        }
        mainFileNamesDisplay.innerHTML = fileList;
        mainFileNamesDisplay.classList.remove('hidden');
    }
});

// Drag and drop functionality untuk gallery images
const galleryUploadArea = galleryFileInput.closest('div');

galleryUploadArea.addEventListener('dragover', function(e) {
    e.preventDefault();
    this.classList.add('border-cyan-400', 'bg-cyan-50');
});

galleryUploadArea.addEventListener('dragleave', function(e) {
    e.preventDefault();
    this.classList.remove('border-cyan-400', 'bg-cyan-50');
});

galleryUploadArea.addEventListener('drop', function(e) {
    e.preventDefault();
    this.classList.remove('border-cyan-400', 'bg-cyan-50');
    
    if (e.dataTransfer.files.length > 0) {
        const files = e.dataTransfer.files;
        const maxFiles = 5;
        
        if (files.length > maxFiles) {
            galleryFileNamesDisplay.innerHTML = `Maksimal ${maxFiles} file!<br>`;
            galleryFileNamesDisplay.classList.remove('hidden');
        } else {
            // Create new DataTransfer to set multiple files
            const dt = new DataTransfer();
            for (let i = 0; i < files.length && i < maxFiles; i++) {
                dt.items.add(files[i]);
            }
            galleryFileInput.files = dt.files;
            
            let fileList = 'File terpilih:<br>';
            for (let i = 0; i < files.length && i < maxFiles; i++) {
                fileList += `• ${files[i].name}<br>`;
            }
            galleryFileNamesDisplay.innerHTML = fileList;
            galleryFileNamesDisplay.classList.remove('hidden');
        }
    }
});
</script>

<?php include '../../includes/footer.php'; ?>