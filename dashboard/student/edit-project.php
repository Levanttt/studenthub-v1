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
    SELECT s.id, s.name, s.skill_type 
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
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $category = sanitize($_POST['category'] ?? ''); 
    $status = sanitize($_POST['status'] ?? '');
    $project_type = sanitize($_POST['project_type'] ?? '');
    $project_year = sanitize($_POST['project_year'] ?? '');
    $project_duration = sanitize($_POST['project_duration'] ?? '');
    $github_url = sanitize($_POST['github_url'] ?? '');
    $figma_url = sanitize($_POST['figma_url'] ?? '');
    $demo_url = sanitize($_POST['demo_url'] ?? '');
    $video_url = sanitize($_POST['video_url'] ?? '');
    $skills_input = isset($_POST['skills']) ? (is_array($_POST['skills']) ? $_POST['skills'] : [$_POST['skills']]) : [];
    $delete_images = $_POST['delete_images'] ?? []; 
    $delete_certificate = isset($_POST['delete_certificate']) && $_POST['delete_certificate'] == '1';

    $certificate_issue_date = null;
    $certificate_expiry_date = null;
    if (isset($_POST['certificate_issue_date']) && !empty(trim($_POST['certificate_issue_date']))) {
        $certificate_issue_date = sanitize($_POST['certificate_issue_date']);
    }
    if (isset($_POST['certificate_expiry_date']) && !empty(trim($_POST['certificate_expiry_date']))) {
        $certificate_expiry_date = sanitize($_POST['certificate_expiry_date']);
    }
    $certificate_credential_id = sanitize($_POST['certificate_credential_id'] ?? '');
    $certificate_credential_url = sanitize($_POST['certificate_credential_url'] ?? '');
    $certificate_description = sanitize($_POST['certificate_description'] ?? '');

    if (empty($title) || empty($description)) {
        $error = "Judul dan deskripsi wajib diisi!";
    } elseif (empty($category) || empty($status) || empty($project_type) || empty($project_year)) {
        $error = "Kategori, status, tipe proyek, dan tahun proyek wajib diisi!";
    } elseif (!empty($category)) {
        $validate_category = $conn->prepare("SELECT id FROM project_categories WHERE value = ?");
        if ($validate_category) {
            $validate_category->bind_param("s", $category);
            $validate_category->execute();
            $validate_result = $validate_category->get_result();
            
            if ($validate_result->num_rows == 0) {
                $error = "Kategori yang dipilih tidak valid!";
            }
            $validate_category->close();
        }
    }
    
    $technical_skills_count = 0;
    if (!empty($skills_input)) {
        $technical_skills_stmt = $conn->prepare("SELECT COUNT(*) as count FROM skills WHERE name IN (" . implode(',', array_fill(0, count($skills_input), '?')) . ") AND skill_type = 'technical'");
        if ($technical_skills_stmt) {
            $types = str_repeat('s', count($skills_input));
            $technical_skills_stmt->bind_param($types, ...$skills_input);
            $technical_skills_stmt->execute();
            $result = $technical_skills_stmt->get_result();
            $row = $result->fetch_assoc();
            $technical_skills_count = $row['count'];
            $technical_skills_stmt->close();
        }
    }
    
    if ($technical_skills_count === 0 && !$error) {
        $error = "Pilih minimal 1 technical skill!";
    }

    if (!$error) {
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

        $certificate_path = $project['certificate_path'];
        if ($delete_certificate) {
            $certificate_path = '';
            $certificate_issue_date = null;
            $certificate_expiry_date = null;
            $certificate_credential_id = '';
            $certificate_credential_url = '';
        } elseif (isset($_FILES['certificate_file']) && $_FILES['certificate_file']['error'] === UPLOAD_ERR_OK) {
            $upload_result = handleCertificateUpload($_FILES['certificate_file'], $user_id);
            if ($upload_result['success']) {
                $certificate_path = $upload_result['file_path'];
            } else {
                $error = $upload_result['error'];
            }
        }

        if (!$error) {
            $conn->begin_transaction();
            
            try {
                $update_stmt = $conn->prepare("UPDATE projects SET 
                title = ?, 
                description = ?, 
                image_path = ?, 
                certificate_path = ?, 
                github_url = ?, 
                figma_url = ?, 
                demo_url = ?, 
                video_url = ?, 
                category = ?, 
                status = ?, 
                project_type = ?, 
                project_year = ?, 
                project_duration = ?,
                certificate_credential_id = ?,
                certificate_credential_url = ?,
                certificate_issue_date = ?,
                certificate_expiry_date = ?
                WHERE id = ? AND student_id = ?");

                if (!$update_stmt) {
                    throw new Exception("Error preparing update statement: " . $conn->error);
                }

                $update_stmt->bind_param("sssssssssssssssssii", 
                    $title, 
                    $description, 
                    $main_image_path, 
                    $certificate_path, 
                    $github_url, 
                    $figma_url, 
                    $demo_url, 
                    $video_url, 
                    $category, 
                    $status, 
                    $project_type, 
                    $project_year, 
                    $project_duration,
                    $certificate_credential_id,
                    $certificate_credential_url,
                    $certificate_issue_date,
                    $certificate_expiry_date,
                    $project_id, 
                    $user_id
                );
                        
                if ($update_stmt->execute()) {
                    error_log("DEBUG: Project update successful");

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
                    
                    error_log("DEBUG: Starting skills update process");
                    
                    $delete_skills_stmt = $conn->prepare("DELETE FROM project_skills WHERE project_id = ?");
                    if ($delete_skills_stmt) {
                        $delete_skills_stmt->bind_param("i", $project_id);
                        if ($delete_skills_stmt->execute()) {
                            error_log("DEBUG: Successfully deleted all skills from project");
                        } else {
                            throw new Exception("Gagal menghapus skill lama: " . $delete_skills_stmt->error);
                        }
                        $delete_skills_stmt->close();
                    }
                    
                    if (!empty($skills_input)) {
                        error_log("DEBUG: Adding new skills: " . implode(', ', $skills_input));
                        
                        $skill_insert_stmt = $conn->prepare("INSERT INTO project_skills (project_id, skill_id) VALUES (?, ?)");
                        if ($skill_insert_stmt) {
                            $added_skills = [];
                            foreach ($skills_input as $skill_name) {
                                $skill_name = trim($skill_name);
                                if (!empty($skill_name)) {
                                    $skill_stmt = $conn->prepare("SELECT id, skill_type FROM skills WHERE name = ?");
                                    if ($skill_stmt) {
                                        $skill_stmt->bind_param("s", $skill_name);
                                        $skill_stmt->execute();
                                        $skill_result = $skill_stmt->get_result();
                                        
                                        $skill_id = null;
                                        if ($skill_result->num_rows > 0) {
                                            $skill = $skill_result->fetch_assoc();
                                            $skill_id = $skill['id'];
                                            error_log("DEBUG: Found skill '$skill_name' with ID: $skill_id, type: " . $skill['skill_type']);
                                        } else {
                                            $skill_type = 'technical';
                                            
                                            $insert_skill = $conn->prepare("INSERT INTO skills (name, skill_type) VALUES (?, ?)");
                                            if ($insert_skill) {
                                                $insert_skill->bind_param("ss", $skill_name, $skill_type);
                                                if ($insert_skill->execute()) {
                                                    $skill_id = $conn->insert_id;
                                                    error_log("DEBUG: Created new skill '$skill_name' with ID: $skill_id, type: $skill_type");
                                                } else {
                                                    error_log("DEBUG: Failed to create skill '$skill_name'");
                                                    continue;
                                                }
                                                $insert_skill->close();
                                            }
                                        }
                                        $skill_stmt->close();
                                        
                                        if ($skill_id) {
                                            $skill_insert_stmt->bind_param("ii", $project_id, $skill_id);
                                            if ($skill_insert_stmt->execute()) {
                                                $added_skills[] = $skill_name;
                                                error_log("DEBUG: Successfully added skill '$skill_name' to project");
                                            } else {
                                                error_log("DEBUG: Failed to add skill '$skill_name' to project: " . $skill_insert_stmt->error);
                                            }
                                        }
                                    }
                                }
                            }
                            $skill_insert_stmt->close();
                            error_log("DEBUG: Final added skills: " . implode(', ', $added_skills));
                        }
                    } else {
                        error_log("DEBUG: No skills to add - project will have no skills");
                    }
                    
                    $conn->commit();
                    $success = "Proyek berhasil diperbarui!";
                    error_log("DEBUG: Transaction committed successfully");
                    
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
                        error_log("DEBUG: Refreshed skills after update: " . implode(', ', $existing_skills));
                    }
                    
                } else {
                    throw new Exception("Gagal memperbarui proyek: " . $update_stmt->error);
                }
                
                $update_stmt->close();
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
                error_log("DEBUG: Transaction failed: " . $e->getMessage());
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

$categories = [];
$categories_stmt = $conn->prepare("SELECT id, value, name, icon FROM project_categories ORDER BY CASE WHEN value = 'other' THEN 1 ELSE 0 END, name ASC");
if ($categories_stmt) {
    $categories_stmt->execute();
    $categories_result = $categories_stmt->get_result();
    while ($category = $categories_result->fetch_assoc()) {
        $categories[] = $category;
    }
    $categories_stmt->close();
} else {
    error_log("Error fetching categories: " . $conn->error);
}

function handleFileUpload($file, $user_id) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024;
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'Ukuran file maksimal 5MB'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Error upload file'];
    }
    
    $file_info = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file['tmp_name']);
    
    if (!in_array($file_info, $allowed_types)) {
        return ['success' => false, 'error' => 'Hanya file gambar (JPG, PNG, GIF, WebP) yang diizinkan'];
    }
    
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/cakrawala-connect/uploads/projects/' . $user_id . '/';
    
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'project_' . time() . '_' . uniqid() . '.' . $file_ext;
    $file_path = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        $web_path = '/cakrawala-connect/uploads/projects/' . $user_id . '/' . $filename;
        return ['success' => true, 'file_path' => $web_path];
    } else {
        return ['success' => false, 'error' => 'Gagal mengupload file'];
    }
}

function handleCertificateUpload($file, $user_id) {
    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
    $max_size = 5 * 1024 * 1024;
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'Ukuran file sertifikat maksimal 5MB'];
    }
    
    $file_info = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file['tmp_name']);
    if (!in_array($file_info, $allowed_types)) {
        return ['success' => false, 'error' => 'Hanya file PDF, JPG, dan PNG yang diizinkan untuk sertifikat'];
    }
    
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/cakrawala-connect/uploads/certificates/' . $user_id . '/';
    
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'certificate_' . time() . '_' . uniqid() . '.' . $file_ext;
    $file_path = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        return ['success' => true, 'file_path' => '/cakrawala-connect/uploads/certificates/' . $user_id . '/' . $filename];
    } else {
        return ['success' => false, 'error' => 'Gagal mengupload sertifikat'];
    }
}
?>

<?php include '../../includes/header.php'; ?>

<style>
.searchable-dropdown {
    position: relative;
}

.search-input {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    background: white;
    font-size: 14px;
}

.search-input:focus {
    outline: none;
    ring: 2px;
    ring-color: #2A8FA9;
    border-color: #2A8FA9;
}

.dropdown-options {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    max-height: 200px;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    display: none;
}

.option-item {
    padding: 12px 16px;
    cursor: pointer;
    border-bottom: 1px solid #f3f4f6;
    display: flex;
    align-items: center;
    gap: 8px;
}

.option-item:last-child {
    border-bottom: none;
}

.option-item:hover {
    background-color: #f3f4f6;
}

.option-item.selected {
    background-color: #2A8FA9;
    color: white;
}

.skill-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 500;
    margin: 2px;
}

.skill-tag button {
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    opacity: 0.7;
    display: flex;
    align-items: center;
}

.skill-tag button:hover {
    opacity: 1;
}

.technical-tag { background-color: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
.soft-tag { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.tool-tag { background-color: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; }
</style>

<div class="px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center w-full mb-8">
        <div class="flex-1">
            <h1 class="text-3xl font-bold text-[#2A8FA9] flex items-center gap-3">
                <span class="iconify" data-icon="mdi:pencil-box" data-width="32"></span>
                Edit Proyek: <?php echo htmlspecialchars($project['title']); ?>
            </h1>
            <p class="text-gray-600 mt-2">Perbarui informasi proyek portofolio Anda</p>
        </div>
        <a href="project-detail.php?id=<?php echo $project_id; ?>" 
            class="bg-[#E0F7FF] text-[#2A8FA9] px-6 py-3 rounded-xl font-semibold hover:bg-[#51A3B9] hover:text-white transition-colors duration-300 border border-[#51A3B9] border-opacity-30 flex items-center gap-2">
            <span class="iconify" data-icon="mdi:arrow-left" data-width="18"></span>
            Kembali
        </a>
    </div>

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

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
        <form method="POST" action="" enctype="multipart/form-data" class="space-y-8">
            <div class="space-y-6">
                <h2 class="text-2xl font-bold text-[#2A8FA9] flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:information" data-width="24"></span>
                    Informasi Dasar Proyek
                </h2>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Judul Proyek *</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($project['title']); ?>" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors" 
                            placeholder="Contoh: Aplikasi E-Commerce dengan Laravel" required maxlength="255">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Deskripsi Proyek *</label>
                    <textarea name="description" rows="6" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors resize-none" 
                                placeholder="Jelaskan proyek menggunakan metode STAR (Situasi, Task, Aksi, Result). Tulis dalam satu paragraf." required><?php echo htmlspecialchars($project['description']); ?></textarea>
                </div>
            </div>

            <div class="space-y-6">
                <h2 class="text-2xl font-bold text-[#2A8FA9] flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:clipboard-list" data-width="24"></span>
                    Detail Proyek
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="relative" id="category-dropdown">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Kategori Proyek *</label>
                        
                        <div class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors cursor-pointer bg-white flex items-center justify-between" data-toggle>
                            <div class="flex items-center gap-3">
                                <?php 
                                $current_icon = 'mdi:tag-outline';
                                $current_category_name = formatText($project['category']);
                                
                                foreach ($categories as $cat) {
                                    if ($cat['value'] == $project['category']) {
                                        $current_icon = $cat['icon'];
                                        $current_category_name = $cat['name'];
                                        break;
                                    }
                                }
                                ?>
                                <span class="iconify" data-icon="<?php echo htmlspecialchars($current_icon); ?>" data-width="20" data-selected-icon></span>
                                <span data-selected-text><?php echo htmlspecialchars($current_category_name); ?></span>
                            </div>
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="20"></span>
                        </div>
                        
                        <input type="hidden" name="category" id="category-value" value="<?php echo htmlspecialchars($project['category']); ?>" required>
                        
                        <div class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg hidden max-h-60 overflow-y-auto" data-options>
                            <div class="p-2 space-y-1">
                                <?php if (!empty($categories)): ?>
                                    <?php foreach ($categories as $category): ?>
                                        <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer <?php echo $project['category'] == $category['value'] ? 'bg-[#E0F7FF] text-[#2A8FA9]' : ''; ?>" 
                                            data-option 
                                            data-value="<?php echo htmlspecialchars($category['value']); ?>" 
                                            data-icon="<?php echo htmlspecialchars($category['icon']); ?>">
                                            <span class="iconify" data-icon="<?php echo htmlspecialchars($category['icon']); ?>" data-width="20"></span>
                                            <span><?php echo htmlspecialchars($category['name']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="p-3 text-gray-500 text-sm text-center">
                                        <span class="iconify" data-icon="mdi:alert-circle" data-width="20"></span>
                                        Tidak ada kategori tersedia
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="relative" id="status-dropdown">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status Proyek *</label>
                        
                        <div class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors cursor-pointer bg-white flex items-center justify-between" data-toggle>
                            <div class="flex items-center gap-3">
                                <span class="iconify" data-icon="mdi:progress-clock" data-width="20" data-selected-icon></span>
                                <span data-selected-text><?php echo formatText($project['status']); ?></span>
                            </div>
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="20"></span>
                        </div>
                        
                        <input type="hidden" name="status" id="status-value" value="<?php echo htmlspecialchars($project['status']); ?>" required>
                        
                        <div class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg hidden" data-options>
                            <div class="p-2 space-y-1">
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer <?php echo $project['status'] == 'completed' ? 'bg-[#E0F7FF] text-[#2A8FA9]' : ''; ?>" data-option data-value="completed" data-icon="mdi:check-circle">
                                    <span class="iconify" data-icon="mdi:check-circle" data-width="20"></span>
                                    <span>Selesai</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer <?php echo $project['status'] == 'in-progress' ? 'bg-[#E0F7FF] text-[#2A8FA9]' : ''; ?>" data-option data-value="in-progress" data-icon="mdi:progress-clock">
                                    <span class="iconify" data-icon="mdi:progress-clock" data-width="20"></span>
                                    <span>Dalam Pengerjaan</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer <?php echo $project['status'] == 'prototype' ? 'bg-[#E0F7FF] text-[#2A8FA9]' : ''; ?>" data-option data-value="prototype" data-icon="mdi:flask">
                                    <span class="iconify" data-icon="mdi:flask" data-width="20"></span>
                                    <span>Prototype</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <h2 class="text-2xl font-bold text-[#2A8FA9] flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:calendar-clock" data-width="24"></span>
                    Timeline & Kredibilitas Proyek
                </h2>
                
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                    <p class="text-sm text-amber-800 flex items-center gap-2 mb-2">
                        <span class="iconify" data-icon="mdi:alert-circle" data-width="16"></span>
                        <strong>Transparansi untuk Industri</strong>
                    </p>
                    <p class="text-xs text-amber-700">
                        Industri akan menilai proyek berdasarkan bukti yang diberikan. Pastikan informasi akurat dan dapat diverifikasi.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="relative" id="year-dropdown">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tahun Proyek *</label>
                        
                        <div class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors cursor-pointer bg-white flex items-center justify-between" data-toggle>
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
                                    $selected = $project['project_year'] == $year ? 'bg-[#E0F7FF] text-[#2A8FA9]' : '';
                                    echo '<div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer '.$selected.'" data-option data-value="'.$year.'" data-icon="mdi:calendar">
                                        <span class="iconify" data-icon="mdi:calendar" data-width="20"></span>
                                        <span>'.$year.'</span>
                                    </div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <div class="relative" id="duration-dropdown">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Durasi Pengerjaan</label>
                        
                        <div class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors cursor-pointer bg-white flex items-center justify-between" data-toggle>
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
                                    $selected = $project['project_duration'] == $value ? 'bg-[#E0F7FF] text-[#2A8FA9]' : '';
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
                    <div class="relative" id="project-type-dropdown">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tipe Proyek *</label>
                        
                        <div class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors cursor-pointer bg-white flex items-center justify-between" data-toggle>
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
                                    $selected = $project['project_type'] == $value ? 'bg-[#E0F7FF] text-[#2A8FA9]' : '';
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

            <div class="space-y-6">
                <h2 class="text-2xl font-bold text-[#2A8FA9] flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:tag-multiple" data-width="24"></span>
                    Keterampilan yang Digunakan *
                </h2>
                <p class="text-gray-500 text-sm -mt-4">Klik dropdown dan ketik untuk mencari skill. Pilih dari daftar yang tersedia.</p>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Technical Skills *</label>
                    <div class="searchable-dropdown">
                        <div class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors cursor-pointer bg-white flex items-center justify-between skill-dropdown-toggle" data-category="technical">
                            <div class="flex items-center gap-3">
                                <span class="iconify" data-icon="mdi:code-braces" data-width="20"></span>
                                <span class="skill-placeholder">Pilih Technical Skills</span>
                            </div>
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="20"></span>
                        </div>
                        
                        <div class="dropdown-options" data-category="technical">
                            <input type="text" class="search-input" placeholder="Ketik untuk mencari..." data-category="technical">
                            <div class="options-list" data-category="technical">
                                <?php foreach ($skills_by_category['technical'] as $skill): ?>
                                    <div class="option-item" data-value="<?php echo htmlspecialchars($skill); ?>" data-category="technical">
                                        <span class="iconify" data-icon="mdi:code-braces" data-width="16"></span>
                                        <?php echo htmlspecialchars($skill); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div id="selected-technical-skills" class="flex flex-wrap gap-2 mt-3">
                        <?php
                        $technical_skills = array_filter($existing_skills, function($skill) use ($skills_by_category) {
                            return in_array($skill, $skills_by_category['technical']);
                        });
                        foreach ($technical_skills as $skill): ?>
                            <span class="skill-tag technical-tag">
                                <?php echo htmlspecialchars($skill); ?>
                                <button type="button" onclick="searchableDropdown.removeSkill('<?php echo htmlspecialchars($skill); ?>', 'technical')">
                                    <span class="iconify" data-icon="mdi:close" data-width="14"></span>
                                </button>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-gray-500 text-xs mt-1">Pilih minimal 1 technical skill</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Soft Skills</label>
                    <div class="searchable-dropdown">
                        <div class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors cursor-pointer bg-white flex items-center justify-between skill-dropdown-toggle" data-category="soft">
                            <div class="flex items-center gap-3">
                                <span class="iconify" data-icon="mdi:account-group" data-width="20"></span>
                                <span class="skill-placeholder">Pilih Soft Skills</span>
                            </div>
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="20"></span>
                        </div>
                        
                        <div class="dropdown-options" data-category="soft">
                            <input type="text" class="search-input" placeholder="Ketik untuk mencari..." data-category="soft">
                            <div class="options-list" data-category="soft">
                                <?php foreach ($skills_by_category['soft'] as $skill): ?>
                                    <div class="option-item" data-value="<?php echo htmlspecialchars($skill); ?>" data-category="soft">
                                        <span class="iconify" data-icon="mdi:account-group" data-width="16"></span>
                                        <?php echo htmlspecialchars($skill); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div id="selected-soft-skills" class="flex flex-wrap gap-2 mt-3">
                        <?php
                        $soft_skills = array_filter($existing_skills, function($skill) use ($skills_by_category) {
                            return in_array($skill, $skills_by_category['soft']);
                        });
                        foreach ($soft_skills as $skill): ?>
                            <span class="skill-tag soft-tag">
                                <?php echo htmlspecialchars($skill); ?>
                                <button type="button" onclick="searchableDropdown.removeSkill('<?php echo htmlspecialchars($skill); ?>', 'soft')">
                                    <span class="iconify" data-icon="mdi:close" data-width="14"></span>
                                </button>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-gray-500 text-xs mt-1">Soft skill yang digunakan dalam proyek</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tools & Software</label>
                    <div class="searchable-dropdown">
                        <div class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors cursor-pointer bg-white flex items-center justify-between skill-dropdown-toggle" data-category="tool">
                            <div class="flex items-center gap-3">
                                <span class="iconify" data-icon="mdi:tools" data-width="20"></span>
                                <span class="skill-placeholder">Pilih Tools & Software</span>
                            </div>
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="20"></span>
                        </div>
                        
                        <div class="dropdown-options" data-category="tool">
                            <input type="text" class="search-input" placeholder="Ketik untuk mencari..." data-category="tool">
                            <div class="options-list" data-category="tool">
                                <?php foreach ($skills_by_category['tool'] as $skill): ?>
                                    <div class="option-item" data-value="<?php echo htmlspecialchars($skill); ?>" data-category="tool">
                                        <span class="iconify" data-icon="mdi:tools" data-width="16"></span>
                                        <?php echo htmlspecialchars($skill); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div id="selected-tool-skills" class="flex flex-wrap gap-2 mt-3">
                        <?php
                        $tool_skills = array_filter($existing_skills, function($skill) use ($skills_by_category) {
                            return in_array($skill, $skills_by_category['tool']);
                        });
                        foreach ($tool_skills as $skill): ?>
                            <span class="skill-tag tool-tag">
                                <?php echo htmlspecialchars($skill); ?>
                                <button type="button" onclick="searchableDropdown.removeSkill('<?php echo htmlspecialchars($skill); ?>', 'tool')">
                                    <span class="iconify" data-icon="mdi:close" data-width="14"></span>
                                </button>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-gray-500 text-xs mt-1">Software dan tools yang digunakan</p>
                </div>

                <div id="skills-hidden-container">
                    <?php
                    foreach ($existing_skills as $skill): ?>
                        <input type="hidden" name="skills[]" value="<?php echo htmlspecialchars($skill); ?>" id="skill-<?php echo strtolower(str_replace(' ', '-', $skill)); ?>">
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="space-y-6">
                <h2 class="text-2xl font-bold text-[#2A8FA9] flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:link" data-width="24"></span>
                    Media & Links
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">GitHub Repository (Opsional)</label>
                        <input type="url" name="github_url" value="<?php echo htmlspecialchars($project['github_url'] ?? ''); ?>" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors" 
                                placeholder="https://github.com/username/repository">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Link Desain / Mockup (Opsional)</label>
                        <input type="url" name="figma_url" value="<?php echo htmlspecialchars($project['figma_url'] ?? ''); ?>" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors" 
                                placeholder="https://figma.com/file/...">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Project Link Terkait (Opsional)</label>
                        <input type="url" name="demo_url" value="<?php echo htmlspecialchars($project['demo_url'] ?? ''); ?>" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors" 
                                placeholder="https://your-demo-site.com">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Video URL (YouTube, etc.)</label>
                        <input type="url" name="video_url" value="<?php echo htmlspecialchars($project['video_url'] ?? ''); ?>" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors" 
                                placeholder="https://youtube.com/watch?v=...">
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <h2 class="text-2xl font-bold text-[#2A8FA9] flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:image-multiple" data-width="24"></span>
                    Gambar Proyek
                </h2>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Gambar Utama Proyek</label>
                    <div class="flex items-center gap-4">
                        <?php if (!empty($project['image_path'])): ?>
                            <div class="relative group">
                                <img src="<?php echo htmlspecialchars($project['image_path']); ?>" 
                                        alt="Current Project Image" 
                                        class="w-32 h-32 object-cover rounded-lg border border-gray-300">
                                <div class="absolute inset-0 bg-black bg-opacity-50 rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                    <span class="text-white text-sm">Current</span>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="flex-1">
                            <input type="file" name="project_image" accept="image/*" 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-[#E0F7FF] file:text-[#2A8FA9] hover:file:bg-[#D0F0FF]">
                            <p class="text-xs text-gray-500 mt-2">Format: JPG, PNG, GIF, WebP. Maksimal 5MB</p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Gambar Gallery</label>
                    
                    <?php if (!empty($existing_images)): ?>
                        <div class="mb-4">
                            <p class="text-sm text-gray-600 mb-2">Gambar yang sudah diupload:</p>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <?php foreach ($existing_images as $image): ?>
                                    <?php if (!$image['is_primary']): ?>
                                        <div class="relative group">
                                            <img src="<?php echo htmlspecialchars($image['image_path']); ?>" 
                                                alt="Gallery Image" 
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
                        <input type="file" name="project_gallery[]" multiple accept="image/*" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-[#E0F7FF] file:text-[#2A8FA9] hover:file:bg-[#D0F0FF]">
                        <p class="text-xs text-gray-500 mt-2">Pilih multiple files untuk upload beberapa gambar sekaligus</p>
                    </div>
                </div>
            </div>

            <div class="space-y-6 pt-6 border-t border-gray-200">
                <h2 class="text-2xl font-bold text-[#2A8FA9] flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:certificate" data-width="24"></span>
                    Informasi Sertifikat Proyek
                </h2>

                <div class="bg-[#E0F7FF] border border-[#51A3B9] border-opacity-30 rounded-lg p-4">
                    <div class="flex items-start gap-3">
                        <span class="iconify text-[#2A8FA9] mt-0.5" data-icon="mdi:information" data-width="20"></span>
                        <div>
                            <p class="text-sm text-[#2A8FA9] font-medium mb-1">Sertifikat Proyek</p>
                            <p class="text-sm text-[#409BB2]">
                                Sertifikat ini akan muncul di halaman "Semua Sertifikat" dan dapat dilihat oleh industri.
                                Lengkapi informasi untuk verifikasi yang lebih baik.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">ID Kredensial Sertifikat</label>
                        <input type="text" name="certificate_credential_id" 
                            value="<?php echo htmlspecialchars($project['certificate_credential_id'] ?? ''); ?>" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors" 
                            placeholder="Contoh: ABC123XYZ, 123-456-789">
                        <p class="text-xs text-gray-500 mt-1">ID unik untuk verifikasi sertifikat</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Link Verifikasi Sertifikat</label>
                        <input type="url" name="certificate_credential_url" 
                            value="<?php echo htmlspecialchars($project['certificate_credential_url'] ?? ''); ?>" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors" 
                            placeholder="https://credential.net/verify/12345">
                        <p class="text-xs text-gray-500 mt-1">Link untuk verifikasi online sertifikat</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Diterbitkan</label>
                        <input type="date" name="certificate_issue_date" 
                            value="<?php echo htmlspecialchars($project['certificate_issue_date'] ?? ''); ?>" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors">
                        <p class="text-xs text-gray-500 mt-1">Tanggal sertifikat diterbitkan *</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Kadaluarsa</label>
                        <input type="date" name="certificate_expiry_date" 
                            value="<?php echo htmlspecialchars($project['certificate_expiry_date'] ?? ''); ?>" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors">
                        <p class="text-xs text-gray-500 mt-1">Kosongkan jika tidak ada masa berlaku</p>
                    </div>
                </div>

                <?php if (!empty($project['certificate_path'])): ?>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="iconify text-green-600" data-icon="mdi:certificate" data-width="24"></span>
                                <div>
                                    <p class="font-medium text-green-800">File sertifikat sudah diupload</p>
                                    <p class="text-sm text-green-600">File: <?php echo basename($project['certificate_path']); ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="<?php echo htmlspecialchars($project['certificate_path']); ?>" 
                                target="_blank" 
                                class="bg-green-500 text-white px-3 py-2 rounded-lg text-sm hover:bg-green-600 transition-colors flex items-center gap-2">
                                    <span class="iconify" data-icon="mdi:eye" data-width="16"></span>
                                    Lihat
                                </a>
                                <button type="button" 
                                        onclick="confirmDeleteCertificate()"
                                        class="bg-red-500 text-white px-3 py-2 rounded-lg text-sm hover:bg-red-600 transition-colors flex items-center gap-2">
                                    <span class="iconify" data-icon="mdi:delete" data-width="16"></span>
                                    Hapus
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="delete_certificate" id="delete_certificate" value="0">
                <?php endif; ?>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <?php echo empty($project['certificate_path']) ? 'Upload File Sertifikat' : 'Ganti File Sertifikat'; ?>
                    </label>
                    <input type="file" name="certificate_file" accept=".pdf,.jpg,.jpeg,.png" 
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-[#E0F7FF] file:text-[#2A8FA9] hover:file:bg-[#D0F0FF]">
                    <p class="text-xs text-gray-500 mt-2">Format: PDF, JPG, PNG. Maksimal 5MB</p>
                </div>
            </div>

            <div class="flex justify-end gap-4 pt-6 border-t border-gray-200">
                <a href="project-detail.php?id=<?php echo $project_id; ?>" 
                    class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                    Batal
                </a>
                <button type="submit" 
                        class="px-6 py-3 bg-gradient-to-r from-[#2A8FA9] to-[#51A3B9] text-white rounded-lg hover:from-[#409BB2] hover:to-[#489EB7] transition-all duration-300 font-medium shadow-sm hover:shadow-md flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:content-save" data-width="18"></span>
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
console.log('Categories loaded:', <?php echo json_encode($categories); ?>);
console.log('Current category:', '<?php echo $project['category']; ?>');

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
        this.toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            this.options.classList.toggle('hidden');
        });
        
        document.addEventListener('click', () => {
            this.options.classList.add('hidden');
        });
        
        this.options.querySelectorAll('[data-option]').forEach(option => {
            option.addEventListener('click', () => {
                const value = option.getAttribute('data-value');
                const text = option.textContent.trim();
                const icon = option.getAttribute('data-icon');
                
                this.hiddenInput.value = value;
                this.selectedText.textContent = text;
                if (this.selectedIcon) {
                    this.selectedIcon.setAttribute('data-icon', icon);
                }
                
                this.options.querySelectorAll('[data-option]').forEach(opt => {
                    opt.classList.remove('bg-[#E0F7FF]', 'text-[#2A8FA9]');
                });
                option.classList.add('bg-[#E0F7FF]', 'text-[#2A8FA9]');
                
                this.options.classList.add('hidden');
            });
        });
        
        this.options.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }
}

class SearchableDropdown {
    constructor() {
        this.selectedSkills = new Set();
        this.init();
    }

    init() {
        document.querySelectorAll('.skill-dropdown-toggle').forEach(toggle => {
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                const category = toggle.getAttribute('data-category');
                this.toggleDropdown(category);
            });
        });

        document.querySelectorAll('.search-input').forEach(input => {
            input.addEventListener('input', (e) => {
                const category = e.target.getAttribute('data-category');
                this.filterOptions(category, e.target.value);
            });

            input.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        });

        document.querySelectorAll('.option-item').forEach(option => {
            option.addEventListener('click', (e) => {
                e.stopPropagation();
                const category = option.getAttribute('data-category');
                const value = option.getAttribute('data-value');
                this.selectSkill(value, category);
            });
        });

        document.addEventListener('click', () => {
            this.closeAllDropdowns();
        });

        this.loadExistingSkills();
    }

    toggleDropdown(category) {
        const dropdown = document.querySelector(`.dropdown-options[data-category="${category}"]`);
        const isOpen = dropdown.style.display === 'block';
        
        this.closeAllDropdowns();
        
        if (!isOpen) {
            dropdown.style.display = 'block';
            const searchInput = dropdown.querySelector('.search-input');
            searchInput.value = '';
            searchInput.focus();
            this.filterOptions(category, '');
        }
    }

    closeAllDropdowns() {
        document.querySelectorAll('.dropdown-options').forEach(dropdown => {
            dropdown.style.display = 'none';
        });
    }

    filterOptions(category, query) {
        const optionsList = document.querySelector(`.options-list[data-category="${category}"]`);
        const options = optionsList.querySelectorAll('.option-item');
        const lowerQuery = query.toLowerCase();

        options.forEach(option => {
            const text = option.textContent.toLowerCase();
            if (text.includes(lowerQuery)) {
                option.style.display = 'flex';
            } else {
                option.style.display = 'none';
            }
        });
    }

    loadExistingSkills() {
        const hiddenInputs = document.querySelectorAll('#skills-hidden-container input[name="skills[]"]');
        hiddenInputs.forEach(input => {
            const skillName = input.value;
            this.selectedSkills.add(skillName);
        });
    }

    selectSkill(skillName, category) {
        if (this.selectedSkills.has(skillName)) return;

        this.selectedSkills.add(skillName);
        
        const container = document.getElementById(`selected-${category}-skills`);
        const tag = document.createElement('span');
        tag.className = `skill-tag ${category}-tag`;
        tag.innerHTML = `
            ${skillName}
            <button type="button" onclick="searchableDropdown.removeSkill('${skillName}', '${category}')">
                <span class="iconify" data-icon="mdi:close" data-width="14"></span>
            </button>
        `;
        container.appendChild(tag);

        const hiddenContainer = document.getElementById('skills-hidden-container');
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'skills[]';
        hiddenInput.value = skillName;
        hiddenInput.id = `skill-${category}-${skillName.replace(/\s+/g, '-').toLowerCase()}`;
        hiddenContainer.appendChild(hiddenInput);

        this.closeAllDropdowns();

        const dropdownToggle = document.querySelector(`.skill-dropdown-toggle[data-category="${category}"]`);
        dropdownToggle.classList.add('border-[#2A8FA9]', 'bg-[#E0F7FF]');
        setTimeout(() => {
            dropdownToggle.classList.remove('border-[#2A8FA9]', 'bg-[#E0F7FF]');
        }, 1000);
    }

    removeSkill(skillName, category) {
        const escapedSkillName = CSS.escape(skillName);
        
        this.selectedSkills.delete(skillName);
        
        const container = document.getElementById(`selected-${category}-skills`);
        const tags = container.getElementsByClassName('skill-tag');
        
        Array.from(tags).forEach(tag => {
            const tagText = tag.childNodes[0].textContent.trim();
            if (tagText === skillName) {
                tag.remove();
            }
        });

        const hiddenInputs = document.querySelectorAll('#skills-hidden-container input[name="skills[]"]');
        hiddenInputs.forEach(input => {
            if (input.value === skillName) {
                input.remove();
            }
        });
        
        console.log('Removed skill:', skillName, 'from category:', category);
        console.log('Remaining skills:', Array.from(this.selectedSkills));
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const singleDropdowns = ['project-type-dropdown', 'category-dropdown', 'status-dropdown', 'year-dropdown', 'duration-dropdown'];
    singleDropdowns.forEach(dropdownId => {
        if (document.getElementById(dropdownId)) {
            new CustomDropdown(dropdownId);
        }
    });

    window.searchableDropdown = new SearchableDropdown();

    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const technicalSkills = document.getElementById('selected-technical-skills');
            const technicalSkillsCount = technicalSkills ? technicalSkills.children.length : 0;
            
            if (technicalSkillsCount === 0) {
                e.preventDefault();
                alert('Pilih minimal 1 technical skill!');
                const technicalDropdown = document.querySelector('.skill-dropdown-toggle[data-category="technical"]');
                technicalDropdown.scrollIntoView({ 
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        });
    }
});

function confirmDeleteCertificate() {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Hapus Sertifikat?',
            html: `<div class="text-center">
                    <p class="text-gray-600 mt-2">Sertifikat akan dihapus permanent dari proyek ini.</p>
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
                document.getElementById('delete_certificate').value = '1';
                
                const certificateSection = document.querySelector('.bg-green-50');
                if (certificateSection) {
                    certificateSection.style.opacity = '0.6';
                    certificateSection.style.backgroundColor = '#fef2f2';
                    certificateSection.style.borderColor = '#fecaca';
                    
                    const statusMessage = document.createElement('div');
                    statusMessage.className = 'mt-3 p-3 bg-red-100 text-red-700 rounded-lg text-sm flex items-center gap-2';
                    statusMessage.innerHTML = `
                        <span class="iconify" data-icon="mdi:alert-circle" data-width="16"></span>
                        <span>Sertifikat akan dihapus saat perubahan disimpan</span>
                    `;
                    certificateSection.appendChild(statusMessage);
                }
                
                Swal.fire({
                    title: 'Berhasil!',
                    text: 'Sertifikat akan dihapus saat perubahan disimpan',
                    icon: 'success',
                    confirmButtonColor: '#3085d6',
                    confirmButtonText: 'OK'
                });
            }
        });
    } else {
        if (confirm('Hapus sertifikat? Sertifikat akan dihapus permanent dari proyek ini.')) {
            document.getElementById('delete_certificate').value = '1';
            const certificateSection = document.querySelector('.bg-green-50');
            if (certificateSection) {
                certificateSection.style.opacity = '0.6';
                certificateSection.style.backgroundColor = '#fef2f2';
                certificateSection.style.borderColor = '#fecaca';
            }
            alert('Sertifikat akan dihapus saat perubahan disimpan');
        }
    }
}

const certificateFileInput = document.querySelector('input[name="certificate_file"]');
if (certificateFileInput) {
    certificateFileInput.addEventListener('change', function() {
        const deleteInput = document.getElementById('delete_certificate');
        if (deleteInput && this.files.length > 0) {
            deleteInput.value = '0';
            
            const certificateSection = document.querySelector('.bg-green-50');
            if (certificateSection) {
                certificateSection.style.opacity = '1';
                certificateSection.style.backgroundColor = '';
                certificateSection.style.borderColor = '';
                
                const statusMessage = certificateSection.querySelector('.bg-red-100');
                if (statusMessage) {
                    statusMessage.remove();
                }
            }
        }
    });
}

function formatText(text) {
    if (!text) return '';
    return text.split(/[-_]/).map(word => 
        word.charAt(0).toUpperCase() + word.slice(1)
    ).join(' ');
}
</script>

<?php include '../../includes/footer.php'; ?>