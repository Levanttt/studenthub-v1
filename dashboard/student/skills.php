<?php
include '../../includes/config.php';
include '../../includes/functions.php';

if (!isLoggedIn() || getUserRole() != 'student') {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Ambil nama user dari database berdasarkan user_id
$user_name = 'Unknown';
$user_query = $conn->prepare("SELECT name FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();

if ($user_result->num_rows > 0) {
    $user_data = $user_result->fetch_assoc();
    $user_name = !empty($user_data['name']) ? $user_data['name'] : 'User-' . $user_id;
} else {
    $user_name = 'User-' . $user_id;
}
$user_query->close();

$success = '';
$error = '';

// ✅ HANDLER UNTUK SEMUA REDIRECT MESSAGES
if (isset($_GET['success'])) {
    $skill_name = isset($_GET['skill']) ? urldecode($_GET['skill']) : '';
    
    switch($_GET['success']) {
        case 'added':
            $success = "Skill '{$skill_name}' berhasil ditambahkan!";
            break;
        case 'edited':
            $success = "Skill '{$skill_name}' berhasil diperbarui!";
            break;
        case 'deleted':
            $success = "Skill '{$skill_name}' berhasil dihapus!";
            break;
    }
}

// ✅ HANDLER UNTUK ERROR MESSAGES
if (isset($_GET['error'])) {
    switch($_GET['error']) {
        case 'used':
            $error = "Tidak dapat menghapus skill karena sedang digunakan dalam proyek!";
            break;
        case 'not_found':
            $error = "Skill tidak ditemukan!";
            break;
        case 'delete_failed':
            $error = "Gagal menghapus skill!";
            break;
        case 'already_exists':
            $error = "Skill sudah ada!";
            break;
    }
}

// Fungsi untuk mencatat log skill
function logSkillAction($conn, $skill_id, $skill_name, $action, $user_id, $user_name) {
    $stmt = $conn->prepare("INSERT INTO skill_logs (skill_id, skill_name, action, user_id, user_name) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issis", $skill_id, $skill_name, $action, $user_id, $user_name);
    return $stmt->execute();
}

// Fungsi untuk mengambil data skills - FIXED VERSION
function getSkillsData($conn) {
    $skills_by_category = [
        'technical' => [],
        'soft' => [],
        'tool' => []
    ];

    $skills_stmt = $conn->prepare("
        SELECT s.id, s.name, s.skill_type, COUNT(ps.project_id) as usage_count 
        FROM skills s 
        LEFT JOIN project_skills ps ON s.id = ps.skill_id 
        GROUP BY s.id 
        ORDER BY usage_count DESC, s.skill_type, s.name
    ");
    
    if ($skills_stmt) {
        $skills_stmt->execute();
        $skills_result = $skills_stmt->get_result();
        
        if ($skills_result) {
            while ($skill = $skills_result->fetch_assoc()) {
                $skill_type = $skill['skill_type'] ?? 'technical';
                
                // Validasi dan pastikan skill_type valid
                if (!in_array($skill_type, ['technical', 'soft', 'tool'])) {
                    $skill_type = 'technical';
                }
                
                // PASTIKAN KEY EXISTS sebelum assign
                if (array_key_exists($skill_type, $skills_by_category)) {
                    $skills_by_category[$skill_type][] = $skill;
                } else {
                    // Fallback ke technical
                    $skills_by_category['technical'][] = $skill;
                }
            }
            $total_skills = $skills_result->num_rows;
        } else {
            $total_skills = 0;
        }
        $skills_stmt->close();
    } else {
        $total_skills = 0;
    }
    
    // Refresh skill_usage juga
    $skill_usage = [];
    foreach ($skills_by_category as $category => $skills) {
        foreach ($skills as $skill) {
            if (isset($skill['id']) && isset($skill['usage_count'])) {
                $skill_usage[$skill['id']] = $skill['usage_count'];
            }
        }
    }
    
    return [
        'skills_by_category' => $skills_by_category,
        'total_skills' => $total_skills,
        'skill_usage' => $skill_usage
    ];
}

// Inisialisasi variabel - FIXED
$skills_by_category = [
    'technical' => [],
    'soft' => [],
    'tool' => []
];
$total_skills = 0;
$skill_usage = [];

// Ambil data skills
$skills_data = getSkillsData($conn);
$skills_by_category = $skills_data['skills_by_category'];
$total_skills = $skills_data['total_skills'];
$skill_usage = $skills_data['skill_usage'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_skill'])) {
    $skill_name = sanitize($_POST['skill_name']);
    $skill_type = sanitize($_POST['skill_type']);
    
    if (empty($skill_name)) {
        $error = "Nama skill tidak boleh kosong!";
    } else {
        $check_stmt = $conn->prepare("SELECT id FROM skills WHERE name = ?");
        $check_stmt->bind_param("s", $skill_name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Redirect dengan error
            header("Location: skills.php?error=already_exists");
            exit();
        } else {
            $insert_stmt = $conn->prepare("INSERT INTO skills (name, skill_type) VALUES (?, ?)");
            $insert_stmt->bind_param("ss", $skill_name, $skill_type);
            
            if ($insert_stmt->execute()) {
                $new_skill_id = $conn->insert_id;
                logSkillAction($conn, $new_skill_id, $skill_name, 'added', $user_id, $user_name);
                
                // ✅ REDIRECT SETELAH ADD SUCCESS
                header("Location: skills.php?success=added&skill=" . urlencode($skill_name));
                exit();
            } else {
                $error = "Gagal menambahkan skill: " . $conn->error;
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    $check_usage = $conn->prepare("SELECT ps.id FROM project_skills ps WHERE ps.skill_id = ? LIMIT 1");
    $check_usage->bind_param("i", $delete_id);
    $check_usage->execute();
    $usage_result = $check_usage->get_result();
    
    if ($usage_result->num_rows > 0) {
        // Redirect dengan error
        header("Location: skills.php?error=used");
        exit();
    } else {
        // Ambil informasi skill sebelum dihapus untuk logging
        $skill_info_stmt = $conn->prepare("SELECT name FROM skills WHERE id = ?");
        $skill_info_stmt->bind_param("i", $delete_id);
        $skill_info_stmt->execute();
        $skill_info_result = $skill_info_stmt->get_result();
        
        // ✅ PERBAIKAN: Check jika skill exists
        if ($skill_info_result->num_rows > 0) {
            $skill_info = $skill_info_result->fetch_assoc();
            $skill_name = $skill_info['name'];
            $skill_info_stmt->close();
            
            $delete_stmt = $conn->prepare("DELETE FROM skills WHERE id = ?");
            $delete_stmt->bind_param("i", $delete_id);
            
            if ($delete_stmt->execute()) {
                // Catat log untuk penghapusan skill (skill_id = NULL karena sudah dihapus)
                logSkillAction($conn, NULL, $skill_name, 'deleted', $user_id, $user_name);
                
                // ✅ REDIRECT SETELAH DELETE SUCCESS
                header("Location: skills.php?success=deleted&skill=" . urlencode($skill_name));
                exit();
            } else {
                // Redirect dengan error
                header("Location: skills.php?error=delete_failed");
                exit();
            }
        } else {
            // Redirect dengan error
            header("Location: skills.php?error=not_found");
            exit();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_skill'])) {
    $skill_id = intval($_POST['skill_id']);
    $skill_name = sanitize($_POST['edit_skill_name']);
    $skill_type = sanitize($_POST['edit_skill_type']);
    
    if (empty($skill_name)) {
        $error = "Nama skill tidak boleh kosong!";
    } else {
        $old_skill_stmt = $conn->prepare("SELECT name FROM skills WHERE id = ?");
        $old_skill_stmt->bind_param("i", $skill_id);
        $old_skill_stmt->execute();
        $old_skill_result = $old_skill_stmt->get_result();
        $old_skill = $old_skill_result->fetch_assoc();
        $old_skill_name = $old_skill['name'];
        $old_skill_stmt->close();
        
        $check_stmt = $conn->prepare("SELECT id FROM skills WHERE name = ? AND id != ?");
        $check_stmt->bind_param("si", $skill_name, $skill_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Redirect dengan error
            header("Location: skills.php?error=already_exists");
            exit();
        } else {
            $update_stmt = $conn->prepare("UPDATE skills SET name = ?, skill_type = ? WHERE id = ?");
            $update_stmt->bind_param("ssi", $skill_name, $skill_type, $skill_id);
            
            if ($update_stmt->execute()) {
                logSkillAction($conn, $skill_id, $skill_name, 'edited', $user_id, $user_name);
                
                // ✅ REDIRECT SETELAH EDIT SUCCESS
                header("Location: skills.php?success=edited&skill=" . urlencode($skill_name));
                exit();
            } else {
                $error = "Gagal memperbarui skill: " . $conn->error;
            }
            $update_stmt->close();
        }
        $check_stmt->close();
    }
}
?>

<?php include '../../includes/header.php'; ?>

<style>
header .hidden.sm\:inline {
    display: inline-block !important;
}

/* Pastikan tidak ada konflik dengan class hidden */
nav .hidden.sm\:inline {
    display: inline-block !important;
}

.skill-card {
    transition: all 0.3s ease;
}

.skill-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.technical-badge { background-color: #dbeafe; color: #1e40af; }
.soft-badge { background-color: #dcfce7; color: #166534; }
.tool-badge { background-color: #f3e8ff; color: #7e22ce; }

.tab-button.active {
    border-bottom-color: #3b82f6;
    color: #1e40af;
    background-color: #eff6ff;
}

.tab-button.active[data-tab="technical"] {
    border-bottom-color: #3b82f6;
    color: #1e40af;
}

.tab-button.active[data-tab="soft"] {
    border-bottom-color: #10b981;
    color: #166534;
}

.tab-button.active[data-tab="tool"] {
    border-bottom-color: #8b5cf6;
    color: #7e22ce;
}

/* Modal Styles untuk Edit */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 1rem;
}

.modal-content {
    background: white;
    border-radius: 1rem;
    padding: 2rem;
    width: 100%;
    max-width: 500px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    animation: modalSlideIn 0.2s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-10px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.modal-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1a1a1a;
    line-height: 1.2;
}

.modal-footer {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    margin-top: 2rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 0.75rem;
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    outline: none;
}

.btn:focus {
    outline: 2px solid #2A8FA9;
    outline-offset: 2px;
}

.btn-secondary {
    background: #f8f9fa;
    color: #475467;
    border: 1px solid #d0d5dd;
}

.btn-secondary:hover {
    background: #f1f3f5;
}

.btn-primary {
    background: #2A8FA9;
    color: white;
}

.btn-primary:hover {
    background: #409BB2;
}

.hidden {
    display: none !important;
}

.flex {
    display: flex;
}
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
            <div>
                <h1 class="text-3xl font-bold text-[#2A8FA9] flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:tag-multiple" data-width="32"></span>
                    Kelola Skill & Tag
                </h1>
                <p class="text-gray-600 mt-2">Kelola keterampilan dan tools untuk portfolio proyek Anda</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3">
                <a href="index.php" class="bg-[#E0F7FF] text-[#2A8FA9] px-6 py-3 rounded-xl font-semibold hover:bg-[#51A3B9] hover:text-white transition-colors duration-300 border border-[#51A3B9] border-opacity-30 flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:arrow-left" data-width="18"></span>
                    Kembali ke Dashboard
                </a>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-gradient-to-r from-[#2A8FA9]/5 to-[#409BB2]/10 rounded-2xl p-6 border border-[#2A8FA9]/30">
                <div class="flex items-center gap-4">
                    <div class="bg-[#2A8FA9] p-3 rounded-xl">
                        <span class="iconify text-white" data-icon="mdi:tag" data-width="24"></span>
                    </div>
                    <div>
                        <h3 class="text-[#2A8FA9] font-bold text-2xl"><?php echo $total_skills; ?></h3>
                        <p class="text-[#2A8FA9] text-sm">Total Skill</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-gradient-to-r from-blue-50 to-blue-100/50 rounded-2xl p-6 border border-blue-200">
                <div class="flex items-center gap-4">
                    <div class="bg-blue-600 p-3 rounded-xl">
                        <span class="iconify text-white" data-icon="mdi:code-braces" data-width="24"></span>
                    </div>
                    <div>
                        <h3 class="text-blue-900 font-bold text-2xl"><?php echo isset($skills_by_category['technical']) ? count($skills_by_category['technical']) : 0; ?></h3>
                        <p class="text-blue-700 text-sm">Technical Skills</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-gradient-to-r from-green-50 to-green-100/50 rounded-2xl p-6 border border-green-200">
                <div class="flex items-center gap-4">
                    <div class="bg-green-600 p-3 rounded-xl">
                        <span class="iconify text-white" data-icon="mdi:account-group" data-width="24"></span>
                    </div>
                    <div>
                        <h3 class="text-green-900 font-bold text-2xl"><?php echo isset($skills_by_category['soft']) ? count($skills_by_category['soft']) : 0; ?></h3>
                        <p class="text-green-700 text-sm">Soft Skills</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-gradient-to-r from-purple-50 to-purple-100/50 rounded-2xl p-6 border border-purple-200">
                <div class="flex items-center gap-4">
                    <div class="bg-purple-600 p-3 rounded-xl">
                        <span class="iconify text-white" data-icon="mdi:tools" data-width="24"></span>
                    </div>
                    <div>
                        <h3 class="text-purple-900 font-bold text-2xl"><?php echo isset($skills_by_category['tool']) ? count($skills_by_category['tool']) : 0; ?></h3>
                        <p class="text-purple-700 text-sm">Tools & Software</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Global Warning -->
        <div class="mb-6 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-700 p-4 rounded-lg" role="alert">
            <p class="font-bold flex items-center gap-2">
                <span class="iconify" data-icon="mdi:alert" data-width="20"></span>
                Penting: Pengelolaan Skill Bersifat Global
            </p>
            <p class="text-sm mt-1">
                Penambahan, pengeditan, atau penghapusan skill di halaman ini akan <strong>berdampak pada semua pengguna</strong> platform Cakrawala Connect. Pastikan skill yang Anda tambahkan relevan dan belum ada. Skill yang sedang digunakan dalam proyek tidak dapat dihapus.
            </p>
        </div>
    </div>

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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Add Skill Form -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sticky top-6">
                <h2 class="text-xl font-bold text-[#2A8FA9] mb-4 flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:plus-circle" data-width="24"></span>
                    Tambah Skill Baru
                </h2>
                
                <form method="POST" action="" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nama Skill *</label>
                        <input type="text" name="skill_name" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors" 
                            placeholder="Contoh: React, Leadership, Figma" required>
                    </div>
        
                    <div class="relative" id="skill-type-dropdown">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Kategori Skill *</label>
                        
                        <div class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors cursor-pointer bg-white flex items-center justify-between" data-toggle>
                            <div class="flex items-center gap-3">
                                <span class="iconify" data-icon="mdi:tag-outline" data-width="20" data-selected-icon></span>
                                <span data-selected-text>Pilih Kategori</span>
                            </div>
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="20"></span>
                        </div>
                        
                        <input type="hidden" name="skill_type" id="skill-type-value" required>
                        
                        <div class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg hidden" data-options>
                            <div class="p-2 space-y-1">
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="technical" data-icon="mdi:code-braces">
                                    <span class="iconify" data-icon="mdi:code-braces" data-width="20"></span>
                                    <span>Technical Skill</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="soft" data-icon="mdi:account-group">
                                    <span class="iconify" data-icon="mdi:account-group" data-width="20"></span>
                                    <span>Soft Skill</span>
                                </div>
                                <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="tool" data-icon="mdi:tools">
                                    <span class="iconify" data-icon="mdi:tools" data-width="20"></span>
                                    <span>Tool & Software</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_skill" 
                            class="w-full bg-gradient-to-r from-[#2A8FA9] to-[#409BB2] text-white py-3 px-4 rounded-lg font-semibold hover:from-[#409BB2] hover:to-[#489EB7] transition-all duration-300 flex items-center justify-center gap-2 shadow-md">
                        <span class="iconify" data-icon="mdi:plus" data-width="20"></span>
                        Tambah Skill
                    </button>
                </form>
                
                <div class="mt-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
                        <span class="iconify" data-icon="mdi:information" data-width="16"></span>
                        Keterangan Kategori:
                    </h3>
                    <ul class="text-xs text-gray-600 space-y-1">
                        <li class="flex items-center gap-2">
                            <span class="w-2 h-2 bg-blue-500 rounded-full"></span>
                            <span><strong>Technical:</strong> Bahasa pemrograman, framework, database</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                            <span><strong>Soft Skill:</strong> Kemampuan interpersonal, leadership</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="w-2 h-2 bg-purple-500 rounded-full"></span>
                            <span><strong>Tools:</strong> Software, platform, development tools</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Skills List -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <!-- Tabs Navigation -->
                <div class="flex border-b border-gray-200 mb-6">
                    <button type="button" 
                            class="tab-button flex-1 py-3 px-4 text-center font-semibold border-b-2 border-transparent hover:bg-gray-50 transition-colors flex items-center justify-center gap-2 active"
                            data-tab="technical"
                            onclick="switchTab('technical')">
                        <span class="iconify" data-icon="mdi:code-braces" data-width="20"></span>
                        Technical
                        <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full"><?php echo isset($skills_by_category['technical']) ? count($skills_by_category['technical']) : 0; ?></span>
                    </button>
                    
                    <button type="button" 
                            class="tab-button flex-1 py-3 px-4 text-center font-semibold border-b-2 border-transparent hover:bg-gray-50 transition-colors flex items-center justify-center gap-2"
                            data-tab="soft"
                            onclick="switchTab('soft')">
                        <span class="iconify" data-icon="mdi:account-group" data-width="20"></span>
                        Soft Skills
                        <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full"><?php echo isset($skills_by_category['soft']) ? count($skills_by_category['soft']) : 0; ?></span>
                    </button>
                    
                    <button type="button" 
                            class="tab-button flex-1 py-3 px-4 text-center font-semibold border-b-2 border-transparent hover:bg-gray-50 transition-colors flex items-center justify-center gap-2"
                            data-tab="tool"
                            onclick="switchTab('tool')">
                        <span class="iconify" data-icon="mdi:tools" data-width="20"></span>
                        Tools
                        <span class="bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded-full"><?php echo isset($skills_by_category['tool']) ? count($skills_by_category['tool']) : 0; ?></span>
                    </button>
                </div>

                <!-- Tab Contents -->
                <div id="technical-tab" class="tab-content">
                    <h2 class="text-xl font-bold text-blue-900 mb-4 flex items-center gap-2">
                        <span class="iconify" data-icon="mdi:code-braces" data-width="24"></span>
                        Technical Skills
                    </h2>
                    
                    <?php if (!empty($skills_by_category['technical'])): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach ($skills_by_category['technical'] as $skill): ?>
                                <div class="skill-card bg-white border border-gray-200 rounded-xl p-4 flex justify-between items-center">
                                    <div class="flex items-center gap-3">
                                        <span class="technical-badge px-3 py-1 rounded-lg text-sm font-medium">
                                            <?php echo htmlspecialchars($skill['name']); ?>
                                        </span>
                                        <?php if (isset($skill_usage[$skill['id']]) && $skill_usage[$skill['id']] > 0): ?>
                                            <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                                                <?php echo $skill_usage[$skill['id']]; ?> proyek
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex gap-2">
                                        <button onclick="openEditModal(<?php echo $skill['id']; ?>, '<?php echo htmlspecialchars($skill['name']); ?>', 'technical')" 
                                                class="text-gray-400 hover:text-[#2A8FA9] transition-colors p-1"
                                                title="Edit Skill">
                                            <span class="iconify" data-icon="mdi:pencil" data-width="16"></span>
                                        </button>
                                        <button onclick="confirmDeleteSkill(<?php echo $skill['id']; ?>, '<?php echo htmlspecialchars($skill['name']); ?>')" 
                                                class="text-gray-400 hover:text-red-600 transition-colors p-1"
                                                title="Hapus Skill">
                                            <span class="iconify" data-icon="mdi:delete" data-width="16"></span>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <span class="iconify inline-block mb-2" data-icon="mdi:code-braces-off" data-width="48"></span>
                            <p class="text-lg font-semibold">Belum ada Technical Skills</p>
                            <p class="text-sm mt-2">Tambahkan bahasa pemrograman atau framework yang Anda kuasai</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div id="soft-tab" class="tab-content hidden">
                    <h2 class="text-xl font-bold text-green-900 mb-4 flex items-center gap-2">
                        <span class="iconify" data-icon="mdi:account-group" data-width="24"></span>
                        Soft Skills
                    </h2>
                    
                    <?php if (!empty($skills_by_category['soft'])): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach ($skills_by_category['soft'] as $skill): ?>
                                <div class="skill-card bg-white border border-gray-200 rounded-xl p-4 flex justify-between items-center">
                                    <div class="flex items-center gap-3">
                                        <span class="soft-badge px-3 py-1 rounded-lg text-sm font-medium">
                                            <?php echo htmlspecialchars($skill['name']); ?>
                                        </span>
                                        <?php if (isset($skill_usage[$skill['id']]) && $skill_usage[$skill['id']] > 0): ?>
                                            <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                                                <?php echo $skill_usage[$skill['id']]; ?> proyek
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex gap-2">
                                        <button onclick="openEditModal(<?php echo $skill['id']; ?>, '<?php echo htmlspecialchars($skill['name']); ?>', 'soft')" 
                                                class="text-gray-400 hover:text-[#2A8FA9] transition-colors p-1"
                                                title="Edit Skill">
                                            <span class="iconify" data-icon="mdi:pencil" data-width="16"></span>
                                        </button>
                                        <button onclick="confirmDeleteSkill(<?php echo $skill['id']; ?>, '<?php echo htmlspecialchars($skill['name']); ?>')" 
                                                class="text-gray-400 hover:text-red-600 transition-colors p-1"
                                                title="Hapus Skill">
                                            <span class="iconify" data-icon="mdi:delete" data-width="16"></span>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <span class="iconify inline-block mb-2" data-icon="mdi:account-off" data-width="48"></span>
                            <p class="text-lg font-semibold">Belum ada Soft Skills</p>
                            <p class="text-sm mt-2">Tambahkan kemampuan interpersonal dan leadership Anda</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div id="tool-tab" class="tab-content hidden">
                    <h2 class="text-xl font-bold text-purple-900 mb-4 flex items-center gap-2">
                        <span class="iconify" data-icon="mdi:tools" data-width="24"></span>
                        Tools & Software
                    </h2>
                    
                    <?php if (!empty($skills_by_category['tool'])): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach ($skills_by_category['tool'] as $skill): ?>
                                <div class="skill-card bg-white border border-gray-200 rounded-xl p-4 flex justify-between items-center">
                                    <div class="flex items-center gap-3">
                                        <span class="tool-badge px-3 py-1 rounded-lg text-sm font-medium">
                                            <?php echo htmlspecialchars($skill['name']); ?>
                                        </span>
                                        <?php if (isset($skill_usage[$skill['id']]) && $skill_usage[$skill['id']] > 0): ?>
                                            <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                                                <?php echo $skill_usage[$skill['id']]; ?> proyek
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex gap-2">
                                        <button onclick="openEditModal(<?php echo $skill['id']; ?>, '<?php echo htmlspecialchars($skill['name']); ?>', 'tool')" 
                                                class="text-gray-400 hover:text-[#2A8FA9] transition-colors p-1"
                                                title="Edit Skill">
                                            <span class="iconify" data-icon="mdi:pencil" data-width="16"></span>
                                        </button>
                                        <button onclick="confirmDeleteSkill(<?php echo $skill['id']; ?>, '<?php echo htmlspecialchars($skill['name']); ?>')" 
                                                class="text-gray-400 hover:text-red-600 transition-colors p-1"
                                                title="Hapus Skill">
                                            <span class="iconify" data-icon="mdi:delete" data-width="16"></span>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <span class="iconify inline-block mb-2" data-icon="mdi:tools-off" data-width="48"></span>
                            <p class="text-lg font-semibold">Belum ada Tools & Software</p>
                            <p class="text-sm mt-2">Tambahkan software dan tools yang Anda gunakan</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Skill Modal -->
<div id="editModal" class="modal-overlay hidden">
    <div class="modal-content">
        <div class="modal-header">
            <div class="bg-[#E0F7FF] p-3 rounded-xl">
                <span class="iconify text-[#2A8FA9]" data-icon="mdi:pencil" data-width="24"></span>
            </div>
            <h3 class="modal-title">Edit Skill</h3>
        </div>
        
        <form method="POST" action="" id="editForm">
            <input type="hidden" name="skill_id" id="edit_skill_id">
            <input type="hidden" name="edit_skill" value="1">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nama Skill</label>
                    <input type="text" name="edit_skill_name" id="edit_skill_name" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors" 
                           required>
                </div>
                
                <!-- Custom Dropdown untuk Edit Kategori Skill -->
                <div class="relative" id="edit-skill-type-dropdown">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Kategori Skill</label>
                    
                    <div class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors cursor-pointer bg-white flex items-center justify-between" data-toggle>
                        <div class="flex items-center gap-3">
                            <span class="iconify" data-icon="mdi:tag-outline" data-width="20" data-selected-icon></span>
                            <span data-selected-text>Pilih Kategori</span>
                        </div>
                        <span class="iconify" data-icon="mdi:chevron-down" data-width="20"></span>
                    </div>
                    
                    <input type="hidden" name="edit_skill_type" id="edit-skill-type-value" required>
                    
                    <div class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg hidden" data-options>
                        <div class="p-2 space-y-1">
                            <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="technical" data-icon="mdi:code-braces">
                                <span class="iconify" data-icon="mdi:code-braces" data-width="20"></span>
                                <span>Technical Skill</span>
                            </div>
                            <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="soft" data-icon="mdi:account-group">
                                <span class="iconify" data-icon="mdi:account-group" data-width="20"></span>
                                <span>Soft Skill</span>
                            </div>
                            <div class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer" data-option data-value="tool" data-icon="mdi:tools">
                                <span class="iconify" data-icon="mdi:tools" data-width="20"></span>
                                <span>Tool & Software</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="closeEditModal()" class="btn btn-secondary">
                    Batal
                </button>
                <button type="submit" class="btn btn-primary">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Tab System
function switchTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.add('hidden');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active');
    });
    
    // Show selected tab content
    document.getElementById(`${tabName}-tab`).classList.remove('hidden');
    
    // Add active class to clicked button
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
}

// Custom Dropdown System
class CustomDropdown {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.error('Container not found:', containerId);
            return;
        }
        
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
                
                this.options.classList.add('hidden');
                
                // Trigger change event
                this.hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    }
    
    setValue(value, text, icon) {
        if (this.hiddenInput) this.hiddenInput.value = value;
        if (this.selectedText) this.selectedText.textContent = text;
        if (this.selectedIcon && icon) this.selectedIcon.setAttribute('data-icon', icon);
    }
}

// Edit Modal Functions
function openEditModal(skillId, skillName, skillType) {
    document.getElementById('edit_skill_id').value = skillId;
    document.getElementById('edit_skill_name').value = skillName;
    
    // Set dropdown value based on skill type
    const editDropdown = new CustomDropdown('edit-skill-type-dropdown');
    
    let typeText = '';
    let typeIcon = '';
    
    switch(skillType) {
        case 'technical':
            typeText = 'Technical Skill';
            typeIcon = 'mdi:code-braces';
            break;
        case 'soft':
            typeText = 'Soft Skill';
            typeIcon = 'mdi:account-group';
            break;
        case 'tool':
            typeText = 'Tool & Software';
            typeIcon = 'mdi:tools';
            break;
    }
    
    editDropdown.setValue(skillType, typeText, typeIcon);
    
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

// Delete Confirmation dengan SweetAlert
function confirmDeleteSkill(skillId, skillName) {
    Swal.fire({
        title: 'Hapus Skill?',
        html: `<div class="text-center">
                <p class="text-gray-600 mt-2">Skill <strong>"${skillName}"</strong> akan dihapus permanent dari sistem.</p>
                <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg text-left">
                    <p class="text-sm text-red-700 font-semibold flex items-center gap-2">
                        <span class="iconify" data-icon="mdi:alert" data-width="16"></span>
                        PERINGATAN: Tindakan ini akan berdampak pada semua pengguna!
                    </p>
                </div>
                </div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        background: '#ffffff',
        customClass: {
            popup: 'rounded-2xl',
            title: 'text-xl font-bold text-gray-900',
            htmlContainer: 'text-left'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `skills.php?delete_id=${skillId}`;
        }
    });
}

// Close modal when clicking outside
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditModal();
    }
});

// Initialize dropdowns when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize main dropdown
    new CustomDropdown('skill-type-dropdown');
    
    // Initialize edit dropdown
    new CustomDropdown('edit-skill-type-dropdown');
});

document.addEventListener('DOMContentLoaded', function() {
    // Debug: Cek apakah elemen header text ada dan kenapa hidden
    const headerText = document.querySelector('nav span.text-lg');
    if (headerText) {
        console.log('Header text found:', headerText);
        console.log('Computed display:', window.getComputedStyle(headerText).display);
        console.log('Classes:', headerText.className);
    }
});

// Clear notification parameters from URL
function clearNotificationParams() {
    const urlParams = new URLSearchParams(window.location.search);
    
    if (urlParams.has('success') || urlParams.has('error')) {
        const newUrl = window.location.pathname;
        window.history.replaceState({}, '', newUrl);
    }
}

// Execute when page loads
document.addEventListener('DOMContentLoaded', clearNotificationParams);
</script>

<?php include '../../includes/footer.php'; ?>