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
    $skills_input = $_POST['skills'] ?? [];

    $certificate_credential_id = !empty($_POST['certificate_credential_id']) ? sanitize($_POST['certificate_credential_id']) : null;
    $certificate_credential_url = !empty($_POST['certificate_credential_url']) ? sanitize($_POST['certificate_credential_url']) : null;
    $raw_issue_date = sanitize($_POST['certificate_issue_date'] ?? '');
    $raw_expiry_date = sanitize($_POST['certificate_expiry_date'] ?? '');
    $certificate_issue_date = ($raw_issue_date === '') ? null : $raw_issue_date;
    $certificate_expiry_date = ($raw_expiry_date === '') ? null : $raw_expiry_date;
    $certificate_description = !empty($_POST['certificate_description']) ? sanitize($_POST['certificate_description']) : null;

    if (empty($title) || empty($description)) {
        $error = "Judul dan deskripsi wajib diisi!";
    } elseif (empty($category) || empty($status) || empty($project_type) || empty($project_year)) {
        $error = "Kategori, status, tipe proyek, dan tahun proyek wajib diisi!";
    } elseif (empty($skills_input)) {
        $error = "Pilih minimal 1 skill!";
    } else {
        $main_image_path = '';
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

        $certificate_path = '';
        if (isset($_FILES['certificate_file']) && $_FILES['certificate_file']['error'] === UPLOAD_ERR_OK) {
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
                $stmt = $conn->prepare("INSERT INTO projects (student_id, title, description, image_path, certificate_path, certificate_credential_id, certificate_credential_url, certificate_issue_date, certificate_expiry_date, certificate_description, github_url, figma_url, demo_url, video_url, category, status, project_type, project_year, project_duration) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issssssssssssssssss", $user_id, $title, $description, $main_image_path, $certificate_path, $certificate_credential_id, $certificate_credential_url, $certificate_issue_date, $certificate_expiry_date, $certificate_description, $github_url, $figma_url, $demo_url, $video_url, $category, $status, $project_type, $project_year, $project_duration);
                
                if ($stmt->execute()) {
                    $project_id = $conn->insert_id;
                    
                    if (!empty($gallery_images)) {
                        $image_stmt = $conn->prepare("INSERT INTO project_images (project_id, image_path, is_primary) VALUES (?, ?, ?)");
                        
                        foreach ($gallery_images as $image_path) {
                            $is_primary = 0;
                            $image_stmt->bind_param("isi", $project_id, $image_path, $is_primary);
                            $image_stmt->execute();
                        }
                        $image_stmt->close();
                    }
                    
                    foreach ($skills_input as $skill_name) {
                        $skill_name = trim($skill_name);
                        if (!empty($skill_name)) {
                            $skill_stmt = $conn->prepare("SELECT id, skill_type FROM skills WHERE name = ?");
                            $skill_stmt->bind_param("s", $skill_name);
                            $skill_stmt->execute();
                            $skill_result = $skill_stmt->get_result();
                            
                            if ($skill_result->num_rows > 0) {
                                $skill = $skill_result->fetch_assoc();
                                $skill_id = $skill['id'];
                            } else {
                                $insert_skill = $conn->prepare("INSERT INTO skills (name, skill_type) VALUES (?, 'technical')");
                                $insert_skill->bind_param("s", $skill_name);
                                $insert_skill->execute();
                                $skill_id = $conn->insert_id;
                            }
                            
                            $link_skill = $conn->prepare("INSERT INTO project_skills (project_id, skill_id) VALUES (?, ?)");
                            $link_skill->bind_param("ii", $project_id, $skill_id);
                            $link_skill->execute();
                        }
                    }
                    
                    $conn->commit();
                    $success = "Proyek berhasil ditambahkan!";
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
$categorized_skills_stmt->close();

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
    error_log("=== FILE UPLOAD DEBUG START ===");
    error_log("User ID: " . $user_id);
    error_log("File name: " . $file['name']);
    error_log("File size: " . $file['size']);
    error_log("File tmp_name: " . $file['tmp_name']);
    error_log("File error: " . $file['error']);
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024;
    
    if ($file['size'] > $max_size) {
        error_log("ERROR: File too large");
        return ['success' => false, 'error' => 'Ukuran file maksimal 5MB'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log("ERROR: Upload error code: " . $file['error']);
        return ['success' => false, 'error' => 'Error upload file: ' . $file['error']];
    }
    
    $file_info = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file['tmp_name']);
    error_log("MIME type: " . $file_info);
    
    if (!in_array($file_info, $allowed_types)) {
        error_log("ERROR: Invalid file type");
        return ['success' => false, 'error' => 'Hanya file gambar (JPG, PNG, GIF, WebP) yang diizinkan'];
    }
    
    $upload_dir_options = [
        $_SERVER['DOCUMENT_ROOT'] . '/cakrawala-connect/uploads/projects/' . $user_id . '/',
        __DIR__ . '/../../../uploads/projects/' . $user_id . '/',
        'D:/LocalXampp/htdocs/cakrawala-connect/uploads/projects/' . $user_id . '/'
    ];
    
    $upload_dir = $upload_dir_options[0]; 
    error_log("Upload dir: " . $upload_dir);
    
    if (!file_exists($upload_dir)) {
        $created = mkdir($upload_dir, 0755, true);
        error_log("Directory created: " . ($created ? 'YES' : 'NO'));
    } else {
        error_log("Directory already exists");
    }
    
    error_log("Directory writable: " . (is_writable($upload_dir) ? 'YES' : 'NO'));
    
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'project_' . time() . '_' . uniqid() . '.' . $file_ext;
    $file_path = $upload_dir . $filename;
    
    error_log("Final file path: " . $file_path);
    
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        error_log("SUCCESS: File moved successfully");
        
        if (file_exists($file_path)) {
            $file_size = filesize($file_path);
            error_log("File verified - exists, size: " . $file_size . " bytes");
            
            $web_path = '/cakrawala-connect/uploads/projects/' . $user_id . '/' . $filename;
            error_log("Web path: " . $web_path);
            error_log("=== FILE UPLOAD DEBUG END - SUCCESS ===");
            
            return ['success' => true, 'file_path' => $web_path];
        } else {
            error_log("ERROR: File not found after move_uploaded_file");
            error_log("=== FILE UPLOAD DEBUG END - FAILED ===");
            return ['success' => false, 'error' => 'File tidak ditemukan setelah upload'];
        }
    } else {
        $last_error = error_get_last();
        error_log("ERROR: move_uploaded_file failed");
        error_log("Last error: " . print_r($last_error, true));
        error_log("=== FILE UPLOAD DEBUG END - FAILED ===");
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
    ring-color: #3b82f6;
    border-color: #3b82f6;
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
    background-color: #3b82f6;
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

@media (max-width: 768px) {
    .mobile-project-container {
        padding-left: 1rem;
        padding-right: 1rem;
    }
    
    .mobile-project-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .mobile-project-title {
        font-size: 1.75rem;
    }
    
    .mobile-project-back-button {
        width: 100%;
        justify-content: center;
    }
    
    .mobile-project-card {
        padding: 1.5rem;
    }
    
    .mobile-project-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .mobile-project-form-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .mobile-project-upload-area {
        padding: 1.5rem;
    }
    
    .mobile-project-upload-icon {
        width: 48px;
        height: 48px;
    }
    
    .mobile-project-button-group {
        flex-direction: column;
        width: 100%;
    }
    
    .mobile-project-button {
        width: 100%;
        justify-content: center;
    }
    
    .mobile-project-textarea {
        min-height: 120px;
    }
    
    .mobile-project-select {
        font-size: 16px; 
    }
    
    .dropdown-options {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 90vw;
        max-height: 70vh;
        z-index: 1001;
    }
    
    .searchable-dropdown .dropdown-options {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 90vw;
        max-height: 70vh;
        z-index: 1001;
    }
    
    .dropdown-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        display: none;
    }
}

@media (max-width: 640px) {
    .mobile-project-header-inner {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .mobile-project-actions {
        width: 100%;
        justify-content: space-between;
    }
    
    .mobile-project-upload-text {
        font-size: 0.875rem;
    }
    
    .mobile-project-skill-tags {
        justify-content: center;
    }
}

@media (max-width: 768px) {
    .custom-dropdown-container {
        position: relative;
    }
    
    .custom-dropdown-options {
        position: fixed !important;
        top: 50% !important;
        left: 50% !important;
        transform: translate(-50%, -50%) !important;
        width: 90vw !important;
        max-height: 70vh !important;
        z-index: 1001 !important;
    }
}

@media (max-width: 768px) {
    input[type="text"],
    input[type="url"],
    input[type="date"],
    select,
    textarea {
        font-size: 16px !important;
        -webkit-appearance: none;
    }
    
    .custom-dropdown-container {
        position: relative;
    }
    
    .custom-dropdown-options {
        position: fixed !important;
        top: 50% !important;
        left: 50% !important;
        transform: translate(-50%, -50%) !important;
        width: 90vw !important;
        max-width: 400px !important;
        max-height: 70vh !important;
        overflow-y: auto !important;
        z-index: 1001 !important;
        border-radius: 16px !important;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2) !important;
    }
    
    .dropdown-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        display: none;
        backdrop-filter: blur(2px);
    }
    
    [data-option] {
        padding: 16px !important;
        font-size: 15px !important;
        min-height: 52px !important;
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
        transition: background-color 0.2s ease;
        cursor: pointer;
        -webkit-tap-highlight-color: transparent;
    }
    
    [data-option]:active {
        background-color: #E0F7FF !important;
        transform: scale(0.98);
    }
    
    [data-option].bg-\[#E0F7FF\] {
        background-color: #E0F7FF !important;
        color: #2A8FA9 !important;
        font-weight: 600;
    }
    
    .searchable-dropdown .dropdown-options {
        position: fixed !important;
        top: 50% !important;
        left: 50% !important;
        transform: translate(-50%, -50%) !important;
        width: 90vw !important;
        max-width: 400px !important;
        max-height: 70vh !important;
        z-index: 1001 !important;
        border-radius: 16px !important;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2) !important;
    }
    
    .search-input {
        font-size: 16px !important;
        padding: 14px 16px !important;
        border-bottom: 2px solid #e5e7eb;
        position: sticky;
        top: 0;
        background: white;
        z-index: 10;
    }
    
    .options-list {
        max-height: calc(70vh - 60px);
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .skill-tag {
        font-size: 13px !important;
        padding: 8px 14px !important;
        border-radius: 20px !important;
    }
    
    .skill-tag button {
        padding: 2px !important;
        margin-left: 6px !important;
    }
    
    .skill-dropdown-toggle,
    [data-toggle] {
        min-height: 52px !important;
        font-size: 15px !important;
        padding: 14px 16px !important;
        cursor: pointer;
        -webkit-tap-highlight-color: transparent;
        user-select: none;
    }
    
    .skill-dropdown-toggle:active,
    [data-toggle]:active {
        background-color: #f9fafb;
        transform: scale(0.99);
    }
    
    body.dropdown-open {
        overflow: hidden !important;
        position: fixed !important;
        width: 100% !important;
    }
}

@media (max-width: 640px) {
    .custom-dropdown-options {
        width: 95vw !important;
    }
    
    .searchable-dropdown .dropdown-options {
        width: 95vw !important;
    }
    
    [data-option] {
        padding: 14px !important;
        font-size: 14px !important;
    }
}

.custom-dropdown-options,
.dropdown-options {
    scroll-behavior: smooth;
}

.custom-dropdown-options::-webkit-scrollbar,
.options-list::-webkit-scrollbar {
    width: 6px;
}

.custom-dropdown-options::-webkit-scrollbar-track,
.options-list::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.custom-dropdown-options::-webkit-scrollbar-thumb,
.options-list::-webkit-scrollbar-thumb {
    background: #51A3B9;
    border-radius: 10px;
}

.custom-dropdown-options::-webkit-scrollbar-thumb:hover,
.options-list::-webkit-scrollbar-thumb:hover {
    background: #2A8FA9;
}

.loading-spinner {
    border: 3px solid #f3f4f6;
    border-top: 3px solid #51A3B9;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@media (hover: none) {
    button,
    [data-option],
    .skill-dropdown-toggle,
    [data-toggle] {
        -webkit-tap-highlight-color: transparent;
    }
    
    button:active,
    [data-option]:active,
    .skill-dropdown-toggle:active,
    [data-toggle]:active {
        opacity: 0.8;
    }
}

@supports (padding: max(0px)) {
    .custom-dropdown-options,
    .searchable-dropdown .dropdown-options {
        padding-left: max(16px, env(safe-area-inset-left));
        padding-right: max(16px, env(safe-area-inset-right));
    }
}
</style>

<div class="px-4 sm:px-6 lg:px-8 py-8 mobile-project-container">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4 mobile-project-header">
            <div class="w-full sm:w-auto">
                <h1 class="text-2xl sm:text-3xl font-bold text-[#2A8FA9] flex items-center gap-3 mobile-project-title">
                    <span class="iconify" data-icon="mdi:plus-box" data-width="28"></span>
                    Tambah Proyek Baru
                </h1>
                <p class="text-gray-600 mt-2 text-sm sm:text-base">Tunjukkan kemampuan nyata kamu melalui bukti kerja proyek</p>
            </div>
            <a href="projects.php" 
                class="bg-[#E0F7FF] text-[#2A8FA9] px-4 sm:px-6 py-3 rounded-xl font-semibold hover:bg-[#51A3B9] hover:text-white transition-colors duration-300 border border-[#51A3B9] border-opacity-30 flex items-center gap-2 w-full sm:w-auto justify-center mobile-project-back-button">
                <span class="iconify" data-icon="mdi:arrow-left" data-width="18"></span>
                <span class="text-sm sm:text-base">Kembali</span>
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center gap-2 text-sm sm:text-base">
            <span class="iconify" data-icon="mdi:alert-circle" data-width="20"></span>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center gap-2 text-sm sm:text-base">
            <span class="iconify" data-icon="mdi:check-circle" data-width="20"></span>
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <!-- Project Form -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8 mobile-project-card">
        <form method="POST" action="" enctype="multipart/form-data" class="space-y-6 sm:space-y-8">
            <!-- Basic Information -->
            <div class="space-y-6">
                <h2 class="text-xl sm:text-2xl font-bold text-[#2A8FA9] flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:information" data-width="20"></span>
                    Informasi Dasar Proyek
                </h2>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Judul Proyek *</label>
                    <input type="text" name="title" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] transition-colors mobile-project-select" 
                            placeholder="Contoh: Aplikasi E-Commerce dengan Laravel" required maxlength="255">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Deskripsi Proyek *</label>
                    <textarea name="description" rows="6"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] transition-colors resize-none mobile-project-textarea"
                    placeholder="Jelaskan proyek menggunakan metode STAR (Situasi, Task, Aksi, Result). Tulis dalam satu paragraf."
                    required><?php echo htmlspecialchars($project['description'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- Project Details -->                      
            <div class="space-y-6">
                <h2 class="text-xl sm:text-2xl font-bold text-[#2A8FA9] flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:clipboard-list" data-width="20"></span>
                    Detail Proyek
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 mobile-project-form-grid">
                    <!-- Project Category -->
                    <div class="relative custom-dropdown-container" id="category-dropdown">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Kategori Proyek *</label>
                        
                        <div class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] transition-colors cursor-pointer bg-white flex items-center justify-between mobile-project-select" data-toggle>
                            <div class="flex items-center gap-3">
                                <?php 
                                $current_category_value = $_POST['category'] ?? ''; 
                                $current_icon = 'mdi:tag-outline';
                                $current_category_name = 'Pilih Kategori';
                                
                                foreach ($categories as $cat) {
                                    if ($cat['value'] == $current_category_value) {
                                        $current_icon = $cat['icon'];
                                        $current_category_name = $cat['name'];
                                        break;
                                    }
                                }
                                ?>
                                <span class="iconify" data-icon="<?php echo htmlspecialchars($current_icon); ?>" data-width="18"></span>
                                <span data-selected-text><?php echo htmlspecialchars($current_category_name); ?></span>
                            </div>
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="18"></span>
                        </div>
                        
                        <input type="hidden" name="category" id="category-value" value="<?php echo htmlspecialchars($current_category_value); ?>" required>
                        
                        <div class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg hidden max-h-60 overflow-y-auto custom-dropdown-options" data-options>
                            <div class="p-2 space-y-1">
                                <?php if (!empty($categories)): ?>
                                    <?php foreach ($categories as $category): ?>
                                        <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer <?php echo $current_category_value == $category['value'] ? 'bg-[#E0F7FF] text-[#2A8FA9]' : ''; ?>" 
                                            data-option 
                                            data-value="<?php echo htmlspecialchars($category['value']); ?>" 
                                            data-icon="<?php echo htmlspecialchars($category['icon']); ?>"
                                            data-display-name="<?php echo htmlspecialchars($category['name']); ?>">
                                            <span class="iconify" data-icon="<?php echo htmlspecialchars($category['icon']); ?>" data-width="18"></span>
                                            <span><?php echo htmlspecialchars($category['name']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="p-3 text-gray-500 text-sm text-center">
                                        <span class="iconify" data-icon="mdi:alert-circle" data-width="18"></span>
                                        Tidak ada kategori tersedia
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Project Status -->
                    <div class="relative custom-dropdown-container" id="status-dropdown">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status Proyek *</label>
                        
                        <div class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] transition-colors cursor-pointer bg-white flex items-center justify-between mobile-project-select" data-toggle>
                            <div class="flex items-center gap-3">
                                <span class="iconify" data-icon="mdi:progress-clock" data-width="18" data-selected-icon></span>
                                <span data-selected-text>Pilih Status</span>
                            </div>
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="18"></span>
                        </div>
                        
                        <input type="hidden" name="status" id="status-value" required>
                        
                        <div class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg hidden custom-dropdown-options" data-options>
                            <div class="p-2 space-y-1">
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="completed" data-icon="mdi:check-circle">
                                    <span class="iconify" data-icon="mdi:check-circle" data-width="18"></span>
                                    <span>Selesai</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="in-progress" data-icon="mdi:progress-clock">
                                    <span class="iconify" data-icon="mdi:progress-clock" data-width="18"></span>
                                    <span>Dalam Pengerjaan</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="prototype" data-icon="mdi:flask">
                                    <span class="iconify" data-icon="mdi:flask" data-width="18"></span>
                                    <span>Prototype</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Project Timeline & Credibility -->
            <div class="space-y-4">
                <h2 class="text-xl sm:text-2xl font-bold text-[#2A8FA9] flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:calendar-clock" data-width="20"></span>
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

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 mobile-project-form-grid">
                    <!-- Project Year -->
                    <div class="relative custom-dropdown-container" id="year-dropdown">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tahun Proyek *</label>
                        
                        <div class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] transition-colors cursor-pointer bg-white flex items-center justify-between mobile-project-select" data-toggle>
                            <div class="flex items-center gap-3">
                                <span class="iconify" data-icon="mdi:calendar" data-width="18" data-selected-icon></span>
                                <span data-selected-text>Pilih Tahun</span>
                            </div>
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="18"></span>
                        </div>
                        
                        <input type="hidden" name="project_year" id="year-value" required>
                        
                        <div class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg hidden max-h-60 overflow-y-auto custom-dropdown-options" data-options>
                            <div class="p-2 space-y-1">
                                <?php
                                $current_year = date('Y');
                                for ($year = $current_year; $year >= $current_year - 5; $year--) {
                                    echo '<div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="'.$year.'" data-icon="mdi:calendar">
                                        <span class="iconify" data-icon="mdi:calendar" data-width="18"></span>
                                        <span>'.$year.'</span>
                                    </div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <!-- Project Duration -->
                    <div class="relative custom-dropdown-container" id="duration-dropdown">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Durasi Pengerjaan</label>
                        
                        <div class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] transition-colors cursor-pointer bg-white flex items-center justify-between mobile-project-select" data-toggle>
                            <div class="flex items-center gap-3">
                                <span class="iconify" data-icon="mdi:clock-outline" data-width="18" data-selected-icon></span>
                                <span data-selected-text>Pilih Durasi</span>
                            </div>
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="18"></span>
                        </div>
                        
                        <input type="hidden" name="project_duration" id="duration-value">
                        
                        <div class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg hidden max-h-60 overflow-y-auto custom-dropdown-options" data-options>
                            <div class="p-2 space-y-1">
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="1-2 weeks" data-icon="mdi:clock-fast">
                                    <span class="iconify" data-icon="mdi:clock-fast" data-width="18"></span>
                                    <span>1-2 Minggu</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="1 month" data-icon="mdi:clock-outline">
                                    <span class="iconify" data-icon="mdi:clock-outline" data-width="18"></span>
                                    <span>1 Bulan</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="2-3 months" data-icon="mdi:clock">
                                    <span class="iconify" data-icon="mdi:clock" data-width="18"></span>
                                    <span>2-3 Bulan</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="4-6 months" data-icon="mdi:clock">
                                    <span class="iconify" data-icon="mdi:clock" data-width="18"></span>
                                    <span>4-6 Bulan</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="7-12 months" data-icon="mdi:clock">
                                    <span class="iconify" data-icon="mdi:clock" data-width="18"></span>
                                    <span>7-12 Bulan</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="1+ years" data-icon="mdi:calendar">
                                    <span class="iconify" data-icon="mdi:calendar" data-width="18"></span>
                                    <span>1+ Tahun</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 mobile-project-form-grid">
                    <!-- Project Type -->
                    <div class="relative custom-dropdown-container" id="project-type-dropdown">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tipe Proyek *</label>
                        
                        <div class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] transition-colors cursor-pointer bg-white flex items-center justify-between mobile-project-select" data-toggle>
                            <div class="flex items-center gap-3">
                                <span class="iconify" data-icon="mdi:folder-outline" data-width="18" data-selected-icon></span>
                                <span data-selected-text>Pilih Tipe Proyek</span>
                            </div>
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="18"></span>
                        </div>
                        
                        <input type="hidden" name="project_type" id="project-type-value" required>
                        
                        <div class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg hidden max-h-60 overflow-y-auto custom-dropdown-options" data-options>
                            <div class="p-2 space-y-1">
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="academic" data-icon="mdi:school">
                                    <span class="iconify" data-icon="mdi:school" data-width="18"></span>
                                    <span>Project Akademik</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="personal" data-icon="mdi:account">
                                    <span class="iconify" data-icon="mdi:account" data-width="18"></span>
                                    <span>Project Personal</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="freelance" data-icon="mdi:briefcase">
                                    <span class="iconify" data-icon="mdi:briefcase" data-width="18"></span>
                                    <span>Project Freelance</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="internship" data-icon="mdi:office-building">
                                    <span class="iconify" data-icon="mdi:office-building" data-width="18"></span>
                                    <span>Project Internship</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="competition" data-icon="mdi:trophy">
                                    <span class="iconify" data-icon="mdi:trophy" data-width="18"></span>
                                    <span>Project Kompetisi</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Skills Section -->
            <div class="space-y-6">
                <h2 class="text-xl sm:text-2xl font-bold text-[#2A8FA9] flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:tag-multiple" data-width="20"></span>
                    Keterampilan yang Digunakan *
                </h2>
                <p class="text-gray-500 text-sm -mt-4">Klik dropdown dan ketik untuk mencari skill. Pilih dari daftar yang tersedia.</p>

                <!-- Technical Skills -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Technical Skills *</label>
                    <div class="searchable-dropdown">
                        <div class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors cursor-pointer bg-white flex items-center justify-between skill-dropdown-toggle mobile-project-select" data-category="technical">
                            <div class="flex items-center gap-3">
                                <span class="iconify" data-icon="mdi:code-braces" data-width="18"></span>
                                <span class="skill-placeholder">Pilih Technical Skills</span>
                            </div>
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="18"></span>
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
                    
                    <!-- Selected skills display -->
                    <div id="selected-technical-skills" class="flex flex-wrap gap-2 mt-3 mobile-project-skill-tags">
                        <?php
                        if (isset($_POST['skills'])) {
                            foreach ($_POST['skills'] as $skill) {
                                if (!empty(trim($skill)) && in_array($skill, $skills_by_category['technical'])) {
                                    echo '<span class="skill-tag technical-tag">';
                                    echo htmlspecialchars($skill);
                                    echo '<button type="button" onclick="searchableDropdown.removeSkill(\'' . htmlspecialchars($skill) . '\', \'technical\')">';
                                    echo '<span class="iconify" data-icon="mdi:close" data-width="14"></span>';
                                    echo '</button>';
                                    echo '</span>';
                                }
                            }
                        }
                        ?>
                    </div>
                    <p class="text-gray-500 text-xs mt-1">Pilih minimal 1 technical skill</p>
                </div>

                <!-- Soft Skills -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Soft Skills</label>
                    <div class="searchable-dropdown">
                        <div class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors cursor-pointer bg-white flex items-center justify-between skill-dropdown-toggle mobile-project-select" data-category="soft">
                            <div class="flex items-center gap-3">
                                <span class="iconify" data-icon="mdi:account-group" data-width="18"></span>
                                <span class="skill-placeholder">Pilih Soft Skills</span>
                            </div>
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="18"></span>
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
                    
                    <!-- Selected skills display -->
                    <div id="selected-soft-skills" class="flex flex-wrap gap-2 mt-3 mobile-project-skill-tags">
                        <?php
                        if (isset($_POST['skills'])) {
                            foreach ($_POST['skills'] as $skill) {
                                if (!empty(trim($skill)) && in_array($skill, $skills_by_category['soft'])) {
                                    echo '<span class="skill-tag soft-tag">';
                                    echo htmlspecialchars($skill);
                                    echo '<button type="button" onclick="searchableDropdown.removeSkill(\'' . htmlspecialchars($skill) . '\', \'soft\')">';
                                    echo '<span class="iconify" data-icon="mdi:close" data-width="14"></span>';
                                    echo '</button>';
                                    echo '</span>';
                                }
                            }
                        }
                        ?>
                    </div>
                    <p class="text-gray-500 text-xs mt-1">Soft skill yang digunakan dalam proyek</p>
                </div>

                <!-- Tools & Software -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tools & Software</label>
                    <div class="searchable-dropdown">
                        <div class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors cursor-pointer bg-white flex items-center justify-between skill-dropdown-toggle mobile-project-select" data-category="tool">
                            <div class="flex items-center gap-3">
                                <span class="iconify" data-icon="mdi:tools" data-width="18"></span>
                                <span class="skill-placeholder">Pilih Tools & Software</span>
                            </div>
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="18"></span>
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
                    
                    <!-- Selected skills display -->
                    <div id="selected-tool-skills" class="flex flex-wrap gap-2 mt-3 mobile-project-skill-tags">
                        <?php
                        if (isset($_POST['skills'])) {
                            foreach ($_POST['skills'] as $skill) {
                                if (!empty(trim($skill)) && in_array($skill, $skills_by_category['tool'])) {
                                    echo '<span class="skill-tag tool-tag">';
                                    echo htmlspecialchars($skill);
                                    echo '<button type="button" onclick="searchableDropdown.removeSkill(\'' . htmlspecialchars($skill) . '\', \'tool\')">';
                                    echo '<span class="iconify" data-icon="mdi:close" data-width="14"></span>';
                                    echo '</button>';
                                    echo '</span>';
                                }
                            }
                        }
                        ?>
                    </div>
                    <p class="text-gray-500 text-xs mt-1">Software dan tools yang digunakan</p>
                </div>

                <div id="skills-hidden-container">
                    <?php
                    if (isset($_POST['skills'])) {
                        foreach ($_POST['skills'] as $skill) {
                            echo '<input type="hidden" name="skills[]" value="' . htmlspecialchars($skill) . '">';
                        }
                    }
                    ?>
                </div>
            </div>

            <!-- Media & Links -->
            <div class="space-y-6">
                <h2 class="text-xl sm:text-2xl font-bold text-[#2A8FA9] flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:link" data-width="20"></span>
                    Media & Tautan
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 mobile-project-grid">
                    <!-- Main Project Image -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-3">Gambar Utama Proyek</label>
                        <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 sm:p-8 text-center hover:border-[#51A3B9] transition-colors duration-300 bg-gray-50/50 mobile-project-upload-area">
                            <div class="flex flex-col items-center justify-center mb-4">
                                <div class="text-gray-400 mb-3">
                                    <span class="iconify mobile-project-upload-icon" data-icon="mdi:image" data-width="48"></span>
                                </div>
                                <p class="text-base sm:text-lg font-medium text-gray-700 mb-1 mobile-project-upload-text">Gambar cover/utama proyek</p>
                                <p class="text-xs sm:text-sm text-gray-500">Drag & drop file atau klik untuk memilih</p>
                            </div>
                            
                            <label class="cursor-pointer inline-block">
                                <span class="bg-[#51A3B9] text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg font-semibold hover:bg-[#409BB2] transition-colors duration-300 inline-flex items-center gap-2 text-sm sm:text-base">
                                    <span class="iconify" data-icon="mdi:folder-open" data-width="16"></span>
                                    Pilih File
                                </span>
                                <input type="file" name="project_image" accept="image/*" 
                                    class="hidden" id="project-image-input">
                            </label>
                            
                            <p class="text-xs text-gray-500 mt-4">Max. 5MB per file (JPG, PNG, GIF, WebP)</p>
            
                            <div id="main-file-names" class="text-sm text-gray-600 mt-3 hidden"></div>
                        </div>
                    </div>

                    <!-- Gallery Images -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-3">Gallery Proyek (Multiple)</label>
                        <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 sm:p-8 text-center hover:border-[#51A3B9] transition-colors duration-300 bg-gray-50/50 mobile-project-upload-area">
                            <div class="flex flex-col items-center justify-center mb-4">
                                <div class="text-gray-400 mb-3">
                                    <span class="iconify mobile-project-upload-icon" data-icon="mdi:image-multiple" data-width="48"></span>
                                </div>
                                <p class="text-base sm:text-lg font-medium text-gray-700 mb-1 mobile-project-upload-text">Upload screenshot atau mockup</p>
                                <p class="text-xs sm:text-sm text-gray-500">Drag & drop file atau klik untuk memilih (max 6 files)</p>
                            </div>
                            
                            <label class="cursor-pointer inline-block">
                                <span class="bg-[#409BB2] text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg font-semibold hover:bg-[#2A8FA9] transition-colors duration-300 inline-flex items-center gap-2 text-sm sm:text-base">
                                    <span class="iconify" data-icon="mdi:folder-multiple-image" data-width="16"></span>
                                    Pilih File
                                </span>
                                <input type="file" name="project_gallery[]" accept="image/*" 
                                    class="hidden" id="project-gallery-input" multiple>
                            </label>
                            
                            <p class="text-xs text-gray-500 mt-4">Max. 5MB per file, maksimal 6 file (JPG, PNG, GIF, WebP)</p>
                            
                            <div id="gallery-file-names" class="text-sm text-gray-600 mt-3 hidden"></div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6 mobile-project-form-grid">
                    <!-- GitHub -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">GitHub Repository (Opsional)</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                <span class="iconify" data-icon="mdi:github" data-width="18"></span>
                            </span>
                            <input type="url" name="github_url" value="<?php echo isset($_POST['github_url']) ? htmlspecialchars($_POST['github_url']) : ''; ?>" 
                                    class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] transition-colors mobile-project-select" 
                                    placeholder="https://github.com/username/repo">
                        </div>
                    </div>

                    <!-- desain -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Link Desain / Mockup (Opsional)</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                <span class="iconify" data-icon="mdi:palette" data-width="18"></span>
                            </span>
                            <input type="url" name="figma_url" value="<?php echo isset($_POST['figma_url']) ? htmlspecialchars($_POST['figma_url']) : ''; ?>" 
                                    class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] transition-colors mobile-project-select" 
                                    placeholder="https://figma.com/file/...">
                        </div>
                    </div>

                    <!-- Demo -->
                    <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Project Link Terkait (Opsional)</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                <span class="iconify" data-icon="mdi:link" data-width="18"></span>
                            </span>
                            <input type="url" name="demo_url" value="<?php echo isset($_POST['demo_url']) ? htmlspecialchars($_POST['demo_url']) : ''; ?>" 
                                class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] transition-colors mobile-project-select" 
                                placeholder="https://your-project-link.com">
                        </div>
                    </div>
                </div>

                <!-- Video URL -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Video URL (YouTube, etc.)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <span class="iconify" data-icon="mdi:video" data-width="18"></span>
                        </span>
                        <input type="url" name="video_url" value="<?php echo isset($_POST['video_url']) ? htmlspecialchars($_POST['video_url']) : ''; ?>" 
                                class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] transition-colors mobile-project-select" 
                                placeholder="https://youtube.com/watch?v=...">
                    </div>
                </div>
            </div>

            <!-- Certificate Upload -->
            <div class="space-y-6">
                <h2 class="text-xl sm:text-2xl font-bold text-[#2A8FA9] flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:certificate" data-width="20"></span>
                    Sertifikat (Opsional)
                </h2>

                <div class="bg-[#E0F7FF] border border-[#51A3B9] border-opacity-30 rounded-lg p-4">
                    <p class="text-sm text-[#2A8FA9] flex items-center gap-2 mb-2">
                        <span class="iconify" data-icon="mdi:information" data-width="16"></span>
                        <strong>Tambahkan Sertifikat</strong>
                    </p>
                    <p class="text-xs text-[#409BB2]">
                        Upload sertifikat jika proyek ini terkait dengan kompetisi, sertifikasi, atau program tertentu.
                        Informasi kredensial akan muncul setelah upload file.
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-3">Upload Sertifikat</label>
                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 sm:p-8 text-center hover:border-[#51A3B9] transition-colors duration-300 bg-gray-50/50 mobile-project-upload-area">
                        <div class="flex flex-col items-center justify-center mb-4">
                            <div class="text-gray-400 mb-3">
                                <span class="iconify mobile-project-upload-icon" data-icon="mdi:certificate-outline" data-width="48"></span>
                            </div>
                            <p class="text-base sm:text-lg font-medium text-gray-700 mb-1 mobile-project-upload-text">Upload sertifikat proyek</p>
                            <p class="text-xs sm:text-sm text-gray-500">Drag & drop file atau klik untuk memilih</p>
                        </div>
                        
                        <label class="cursor-pointer inline-block">
                            <span class="bg-[#489EB7] text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg font-semibold hover:bg-[#409BB2] transition-colors duration-300 inline-flex items-center gap-2 text-sm sm:text-base">
                                <span class="iconify" data-icon="mdi:certificate" data-width="16"></span>
                                Pilih Sertifikat
                            </span>
                            <input type="file" name="certificate_file" accept=".pdf,.jpg,.jpeg,.png" 
                                class="hidden" id="certificate-input">
                        </label>
                        
                        <p class="text-xs text-gray-500 mt-4">Max. 5MB per file (PDF, JPG, PNG)</p>
                        <div id="certificate-file-names" class="text-sm text-gray-600 mt-3 hidden"></div>
                    </div>
                </div>

                <!-- Certificate Information Form (HIDDEN BY DEFAULT) -->
                <div id="certificate-info-form" class="hidden space-y-6 pt-6 border-t border-gray-200">
                    <h3 class="text-lg sm:text-xl font-bold text-[#2A8FA9] flex items-center gap-3">
                        <span class="iconify" data-icon="mdi:certificate-edit" data-width="18"></span>
                        Informasi Sertifikat
                    </h3>

                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <p class="text-sm text-green-800 flex items-center gap-2">
                            <span class="iconify" data-icon="mdi:check-circle" data-width="16"></span>
                            <strong>File sertifikat berhasil dipilih!</strong>
                        </p>
                        <p class="text-xs text-green-700 mt-1">
                            Lengkapi informasi di bawah ini untuk verifikasi yang lebih baik oleh industri.
                        </p>
                    </div>

                    <!-- Informasi Kredensial Sertifikat -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 mobile-project-form-grid">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">ID Kredensial Sertifikat</label>
                            <input type="text" name="certificate_credential_id" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] transition-colors mobile-project-select" 
                                placeholder="Contoh: ABC123XYZ, 123-456-789">
                            <p class="text-xs text-gray-500 mt-1">ID unik untuk verifikasi sertifikat</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Link Verifikasi Sertifikat</label>
                            <input type="url" name="certificate_credential_url" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] transition-colors mobile-project-select" 
                                placeholder="https://credential.net/verify/12345">
                            <p class="text-xs text-gray-500 mt-1">Link untuk verifikasi online sertifikat</p>
                        </div>
                    </div>

                    <!-- Tanggal Sertifikat -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 mobile-project-form-grid">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Diterbitkan</label>
                            <input type="date" name="certificate_issue_date" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] transition-colors mobile-project-select">
                            <p class="text-xs text-gray-500 mt-1">Tanggal sertifikat diterbitkan</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Kadaluarsa</label>
                            <input type="date" name="certificate_expiry_date" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] transition-colors mobile-project-select">
                            <p class="text-xs text-gray-500 mt-1">Kosongkan jika tidak ada masa berlaku</p>
                        </div>
                    </div>

                    <!-- Deskripsi Sertifikat -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Deskripsi Sertifikat</label>
                        <textarea name="certificate_description" rows="3"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] transition-colors resize-none mobile-project-textarea"
                                placeholder="Jelaskan tentang sertifikat ini, misalnya: 'Sertifikat lulus bootcamp dengan project..'"></textarea>
                        <p class="text-xs text-gray-500 mt-1">Deskripsi singkat tentang sertifikat dan pencapaiannya</p>
                    </div>

                    <!-- Tombol untuk menghapus sertifikat -->
                    <div class="flex justify-end">
                        <button type="button" 
                                onclick="removeCertificate()"
                                class="bg-red-100 text-red-700 px-4 py-2 rounded-lg hover:bg-red-200 transition-colors font-medium text-sm flex items-center gap-2">
                            <span class="iconify" data-icon="mdi:delete" data-width="16"></span>
                            Hapus Sertifikat
                        </button>
                    </div>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 pt-6 border-t border-gray-200 mobile-project-button-group">
                <button type="submit" class="bg-gradient-to-r from-[#51A3B9] to-[#2A8FA9] text-white px-6 sm:px-8 py-3 rounded-xl font-semibold hover:from-[#409BB2] hover:to-[#51A3B9] transition-all duration-300 flex items-center justify-center gap-2 shadow-md flex-1 mobile-project-button">
                    <span class="iconify" data-icon="mdi:rocket-launch" data-width="18"></span>
                    <span class="text-sm sm:text-base">Publikasikan Proyek</span>
                </button>
                <a href="index.php" class="bg-gray-100 text-gray-700 px-6 sm:px-8 py-3 rounded-xl font-semibold hover:bg-gray-200 transition-colors duration-300 border border-gray-200 flex items-center justify-center gap-2 mobile-project-button">
                    <span class="iconify" data-icon="mdi:close" data-width="18"></span>
                    <span class="text-sm sm:text-base">Batal</span>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="dropdown-overlay" id="dropdown-overlay"></div>

<script>
class CustomDropdown {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.error(`Container ${containerId} not found`);
            return;
        }
        
        this.toggle = this.container.querySelector('[data-toggle]');
        this.options = this.container.querySelector('[data-options]');
        this.hiddenInput = this.container.querySelector('input[type="hidden"]');
        this.selectedText = this.container.querySelector('[data-selected-text]');
        this.selectedIcon = this.container.querySelector('[data-selected-icon]');
        this.overlay = document.getElementById('dropdown-overlay');
        
        if (!this.toggle || !this.options || !this.hiddenInput || !this.selectedText) {
            console.error(`Missing required elements in ${containerId}`);
            return;
        }
        
        this.init();
    }
    
    init() {
        this.toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleDropdown();
        });
        
        document.addEventListener('click', () => {
            this.closeDropdown();
        });
        
        this.options.querySelectorAll('[data-option]').forEach(option => {
            option.addEventListener('click', (e) => {
                e.stopPropagation();
                
                const value = option.getAttribute('data-value');
                const text = option.textContent.trim();
                const icon = option.getAttribute('data-icon');
                const displayName = option.getAttribute('data-display-name');
                
                this.hiddenInput.value = value;
                this.selectedText.textContent = displayName || text;
                
                if (this.selectedIcon && icon) {
                    this.selectedIcon.setAttribute('data-icon', icon);
                }
                
                this.options.querySelectorAll('[data-option]').forEach(opt => {
                    opt.classList.remove('bg-[#E0F7FF]', 'text-[#2A8FA9]');
                });
                option.classList.add('bg-[#E0F7FF]', 'text-[#2A8FA9]');
                
                this.closeDropdown();
                
                this.toggle.classList.add('border-green-500', 'bg-green-50');
                setTimeout(() => {
                    this.toggle.classList.remove('border-green-500', 'bg-green-50');
                }, 500);
            });
        });
        
        this.options.addEventListener('click', (e) => {
            e.stopPropagation();
        });

        if (this.overlay) {
            this.overlay.addEventListener('click', () => {
                this.closeDropdown();
            });
        }
    }

    toggleDropdown() {
        const isOpen = !this.options.classList.contains('hidden');
        
        this.closeAllDropdowns();
        
        if (!isOpen) {
            this.options.classList.remove('hidden');
            
            if (window.innerWidth <= 768) {
                if (this.overlay) {
                    this.overlay.style.display = 'block';
                }
                document.body.style.overflow = 'hidden';
            }
        }
    }

    closeDropdown() {
        if (this.options) {
            this.options.classList.add('hidden');
        }
        if (this.overlay) {
            this.overlay.style.display = 'none';
        }
        document.body.style.overflow = '';
    }

    closeAllDropdowns() {
        document.querySelectorAll('.custom-dropdown-options').forEach(dropdown => {
            dropdown.classList.add('hidden');
        });
        if (this.overlay) {
            this.overlay.style.display = 'none';
        }
        document.body.style.overflow = '';
    }
}

class SearchableDropdown {
    constructor() {
        this.selectedSkills = new Set();
        this.overlay = document.getElementById('dropdown-overlay');
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

        if (this.overlay) {
            this.overlay.addEventListener('click', () => {
                this.closeAllDropdowns();
            });
        }
    }

    toggleDropdown(category) {
        const dropdown = document.querySelector(`.dropdown-options[data-category="${category}"]`);
        if (!dropdown) return;
        
        const isOpen = dropdown.style.display === 'block';
        
        this.closeAllDropdowns();
        
        if (!isOpen) {
            dropdown.style.display = 'block';
            const searchInput = dropdown.querySelector('.search-input');
            if (searchInput) {
                searchInput.value = '';
                searchInput.focus();
            }
            this.filterOptions(category, '');
            
            if (window.innerWidth <= 768) {
                if (this.overlay) {
                    this.overlay.style.display = 'block';
                }
                document.body.style.overflow = 'hidden';
            }
        }
    }

    closeAllDropdowns() {
        document.querySelectorAll('.dropdown-options').forEach(dropdown => {
            dropdown.style.display = 'none';
        });
        if (this.overlay) {
            this.overlay.style.display = 'none';
        }
        document.body.style.overflow = '';
    }

    filterOptions(category, query) {
        const optionsList = document.querySelector(`.options-list[data-category="${category}"]`);
        if (!optionsList) return;
        
        const options = optionsList.querySelectorAll('.option-item');
        const lowerQuery = query.toLowerCase();

        options.forEach(option => {
            const text = option.textContent.toLowerCase();
            option.style.display = text.includes(lowerQuery) ? 'flex' : 'none';
        });
    }

    selectSkill(skillName, category) {
        if (this.selectedSkills.has(skillName)) {
            this.closeAllDropdowns();
            return;
        }

        this.selectedSkills.add(skillName);
        
        const container = document.getElementById(`selected-${category}-skills`);
        if (!container) return;
        
        const tag = document.createElement('span');
        tag.className = `skill-tag ${category}-tag`;
        tag.innerHTML = `
            ${skillName}
            <button type="button" onclick="searchableDropdown.removeSkill('${skillName.replace(/'/g, "\\'")}', '${category}')">
                <span class="iconify" data-icon="mdi:close" data-width="14"></span>
            </button>
        `;
        container.appendChild(tag);

        const hiddenContainer = document.getElementById('skills-hidden-container');
        if (hiddenContainer) {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'skills[]';
            hiddenInput.value = skillName;
            hiddenInput.id = `skill-${category}-${skillName.replace(/\s+/g, '-').toLowerCase()}`;
            hiddenContainer.appendChild(hiddenInput);
        }

        this.closeAllDropdowns();

        const dropdownToggle = document.querySelector(`.skill-dropdown-toggle[data-category="${category}"]`);
        if (dropdownToggle) {
            dropdownToggle.classList.add('border-green-500', 'bg-green-50');
            setTimeout(() => {
                dropdownToggle.classList.remove('border-green-500', 'bg-green-50');
            }, 1000);
        }
    }

    removeSkill(skillName, category) {
        this.selectedSkills.delete(skillName);
        
        const container = document.getElementById(`selected-${category}-skills`);
        if (!container) return;
        
        const tags = container.querySelectorAll('.skill-tag');
        tags.forEach(tag => {
            if (tag.textContent.includes(skillName)) {
                tag.remove();
            }
        });

        const hiddenInput = document.getElementById(`skill-${category}-${skillName.replace(/\s+/g, '-').toLowerCase()}`);
        if (hiddenInput) {
            hiddenInput.remove();
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    try {
        new CustomDropdown('category-dropdown');
        new CustomDropdown('status-dropdown');
        new CustomDropdown('year-dropdown');
        new CustomDropdown('duration-dropdown');
        new CustomDropdown('project-type-dropdown');
    } catch (error) {
        console.error('Error initializing custom dropdowns:', error);
    }

    try {
        window.searchableDropdown = new SearchableDropdown();
    } catch (error) {
        console.error('Error initializing searchable dropdown:', error);
    }

    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const technicalSkillsContainer = document.getElementById('selected-technical-skills');
            if (technicalSkillsContainer && technicalSkillsContainer.children.length === 0) {
                e.preventDefault();
                alert('Pilih minimal 1 technical skill!');
                const technicalDropdown = document.querySelector('.skill-dropdown-toggle[data-category="technical"]');
                if (technicalDropdown) {
                    technicalDropdown.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    }

    setupFileInput('project-image-input', 'main-file-names', 1);
    setupFileInput('project-gallery-input', 'gallery-file-names', 6);
    setupFileInput('certificate-input', 'certificate-file-names', 1, true);

    setupDragAndDrop('project-image-input', 1);
    setupDragAndDrop('project-gallery-input', 6);
    setupDragAndDrop('certificate-input', 1);
});

function setupFileInput(inputId, displayId, maxFiles, showCertForm = false) {
    const input = document.getElementById(inputId);
    const display = document.getElementById(displayId);
    
    if (!input || !display) return;
    
    input.addEventListener('change', function(e) {
        if (this.files.length > 0) {
            if (this.files.length > maxFiles) {
                display.innerHTML = `Maksimal ${maxFiles} file!`;
                display.classList.remove('hidden');
                this.value = '';
                return;
            }
            
            let fileList = 'File terpilih:<br>';
            for (let i = 0; i < this.files.length; i++) {
                fileList += `• ${this.files[i].name}<br>`;
            }
            display.innerHTML = fileList;
            display.classList.remove('hidden');
            
            if (showCertForm) {
                const certForm = document.getElementById('certificate-info-form');
                if (certForm) {
                    certForm.classList.remove('hidden');
                    setTimeout(() => {
                        certForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }, 300);
                }
            }
        } else {
            display.classList.add('hidden');
            if (showCertForm) {
                const certForm = document.getElementById('certificate-info-form');
                if (certForm) certForm.classList.add('hidden');
            }
        }
    });
}

function setupDragAndDrop(inputId, maxFiles) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    const uploadArea = input.closest('.border-dashed');
    if (!uploadArea) return;
    
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('border-[#51A3B9]', 'bg-[#E0F7FF]');
    });

    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('border-[#51A3B9]', 'bg-[#E0F7FF]');
    });

    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('border-[#51A3B9]', 'bg-[#E0F7FF]');
        
        if (e.dataTransfer.files.length > 0) {
            const dt = new DataTransfer();
            const files = e.dataTransfer.files;
            
            for (let i = 0; i < files.length && i < maxFiles; i++) {
                dt.items.add(files[i]);
            }
            
            input.files = dt.files;
            input.dispatchEvent(new Event('change'));
        }
    });
}

function removeCertificate() {
    const certificateInput = document.getElementById('certificate-input');
    const certificateFileNamesDisplay = document.getElementById('certificate-file-names');
    const certificateInfoForm = document.getElementById('certificate-info-form');
    
    if (certificateInput) certificateInput.value = '';
    if (certificateFileNamesDisplay) certificateFileNamesDisplay.classList.add('hidden');
    if (certificateInfoForm) {
        certificateInfoForm.classList.add('hidden');
        const formFields = certificateInfoForm.querySelectorAll('input, textarea');
        formFields.forEach(field => field.value = '');
    }
}

window.addEventListener('resize', function() {
    const overlay = document.getElementById('dropdown-overlay');
    if (window.innerWidth > 768 && overlay) {
        overlay.style.display = 'none';
        document.body.style.overflow = '';
    }
});
</script>

<?php include '../../includes/footer.php'; ?>