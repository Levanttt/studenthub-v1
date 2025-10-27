<?php
include '../../includes/config.php';
include '../../includes/functions.php';

if (!isLoggedIn() || getUserRole() != 'admin') {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Admin';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add_skill') {
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
                $error = "Skill '$skill_name' sudah ada!";
            } else {
                $insert_stmt = $conn->prepare("INSERT INTO skills (name, skill_type) VALUES (?, ?)");
                $insert_stmt->bind_param("ss", $skill_name, $skill_type);
                
                if ($insert_stmt->execute()) {
                    $success = "Skill '$skill_name' berhasil ditambahkan!";
                } else {
                    $error = "Gagal menambahkan skill: " . $conn->error;
                }
                $insert_stmt->close();
            }
            $check_stmt->close();
        }
    }
    
    elseif ($action == 'edit_skill') {
        $skill_id = intval($_POST['skill_id']);
        $skill_name = sanitize($_POST['edit_skill_name']);
        $skill_type = sanitize($_POST['edit_skill_type']);
        
        if (empty($skill_name)) {
            $error = "Nama skill tidak boleh kosong!";
        } else {
            $check_stmt = $conn->prepare("SELECT id FROM skills WHERE name = ? AND id != ?");
            $check_stmt->bind_param("si", $skill_name, $skill_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error = "Skill '$skill_name' sudah ada!";
            } else {
                $update_stmt = $conn->prepare("UPDATE skills SET name = ?, skill_type = ? WHERE id = ?");
                $update_stmt->bind_param("ssi", $skill_name, $skill_type, $skill_id);
                
                if ($update_stmt->execute()) {
                    $success = "Skill berhasil diperbarui!";
                } else {
                    $error = "Gagal memperbarui skill: " . $conn->error;
                }
                $update_stmt->close();
            }
            $check_stmt->close();
        }
    }
}

if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    $check_usage = $conn->prepare("SELECT ps.id FROM project_skills ps WHERE ps.skill_id = ? LIMIT 1");
    $check_usage->bind_param("i", $delete_id);
    $check_usage->execute();
    $usage_result = $check_usage->get_result();
    
    if ($usage_result->num_rows > 0) {
        $error = "Tidak dapat menghapus skill karena sedang digunakan dalam proyek!";
    } else {
        $skill_info_stmt = $conn->prepare("SELECT name FROM skills WHERE id = ?");
        $skill_info_stmt->bind_param("i", $delete_id);
        $skill_info_stmt->execute();
        $skill_info_result = $skill_info_stmt->get_result();
        
        if ($skill_info_result->num_rows > 0) {
            $skill_info = $skill_info_result->fetch_assoc();
            $skill_name = $skill_info['name'];
            
            $delete_stmt = $conn->prepare("DELETE FROM skills WHERE id = ?");
            $delete_stmt->bind_param("i", $delete_id);
            
            if ($delete_stmt->execute()) {
                $success = "Skill '$skill_name' berhasil dihapus!";
            } else {
                $error = "Gagal menghapus skill!";
            }
            $delete_stmt->close();
        } else {
            $error = "Skill tidak ditemukan!";
        }
        $skill_info_stmt->close();
    }
    $check_usage->close();
}

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
            
            if (!in_array($skill_type, ['technical', 'soft', 'tool'])) {
                $skill_type = 'technical';
            }
            
            if (array_key_exists($skill_type, $skills_by_category)) {
                $skills_by_category[$skill_type][] = $skill;
            } else {
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
    $error = "Error mengambil data skills: " . $conn->error;
}
?>

<?php include '../../includes/header.php'; ?>

<style>
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

.tab-button {
    flex: 1;
    padding: 12px 16px;
    text-align: center;
    font-weight: 600;
    border-bottom: 2px solid transparent;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
}

.tab-button {
    border-bottom-color: transparent !important;
    color: #6b7280 !important;
    background-color: transparent !important;
}

.tab-button.active.technical {
    border-bottom-color: #3b82f6 !important;
    color: #1e40af !important; 
    background-color: #eff6ff !important; 
}

.tab-button.active.soft {
    border-bottom-color: #10b981 !important;
    color: #166534 !important; 
    background-color: #f0fdf4 !important;
}

.tab-button.active.tool {
    border-bottom-color: #8b5cf6 !important;
    color: #7e22ce !important;
    background-color: #faf5ff !important; 
}

.tab-button .iconify.text-blue-600 { color: #2563eb !important; }
.tab-button .iconify.text-green-600 { color: #059669 !important; }
.tab-button .iconify.text-purple-600 { color: #7c3aed !important; }
.tab-button .iconify.text-gray-500 { color: #6b7280 !important; }

.tab-button .tab-count.bg-blue-200 { background-color: #bfdbfe !important; }
.tab-button .tab-count.text-blue-800 { color: #1e40af !important; }
.tab-button .tab-count.bg-green-200 { background-color: #bbf7d0 !important; }
.tab-button .tab-count.text-green-800 { color: #166534 !important; }
.tab-button .tab-count.bg-purple-200 { background-color: #e9d5ff !important; }
.tab-button .tab-count.text-purple-800 { color: #6b21a8 !important; }
.tab-button .tab-count.bg-gray-200 { background-color: #e5e7eb !important; }
.tab-button .tab-count.text-gray-800 { color: #1f2937 !important; }

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
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
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

.modal-overlay.hidden {
    display: none !important;
}

.tab-content.hidden {
    display: none !important;
}

.custom-dropdown {
    position: relative;
}

.dropdown-toggle {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    background: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: all 0.2s ease;
}

.dropdown-toggle:hover {
    border-color: #2A8FA9;
}

.dropdown-toggle:focus {
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
    border-radius: 0.5rem;
    margin-top: 4px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    z-index: 10;
    max-height: 200px;
    overflow-y: auto;
}

.dropdown-option {
    padding: 12px 16px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: background-color 0.2s ease;
}

.dropdown-option:hover {
    background-color: #f3f4f6;
}

.dropdown-option:first-child {
    border-radius: 0.5rem 0.5rem 0 0;
}

.dropdown-option:last-child {
    border-radius: 0 0 0.5rem 0.5rem;
}

.hidden {
    display: none !important;
}
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
            <div>
                <h1 class="text-3xl font-bold text-[#2A8FA9] flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:tag-multiple" data-width="32"></span>
                    Kelola Skills
                </h1>
                <p class="text-gray-600 mt-2">Kelola keterampilan dan tools untuk portfolio mahasiswa</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3">
                <a href="index.php" class="bg-[#E0F7FF] text-[#2A8FA9] px-6 py-3 rounded-xl font-semibold hover:bg-[#51A3B9] hover:text-white transition-colors duration-300 border border-[#51A3B9] border-opacity-30 flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:arrow-left" data-width="18"></span>
                    Kembali ke Dashboard
                </a>
            </div>
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
                    <input type="hidden" name="action" value="add_skill">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nama Skill *</label>
                        <input type="text" name="skill_name" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors" 
                            placeholder="Contoh: React, Leadership, Figma" required>
                    </div>
    
                    <!-- Dropdown Kategori Skill -->
                    <div class="custom-dropdown" id="skill-type-dropdown">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Kategori Skill *</label>
                        
                        <div class="dropdown-toggle" data-toggle>
                            <div class="flex items-center gap-3 flex-1">
                                <span class="iconify" data-icon="mdi:tag-outline" data-width="20" data-selected-icon></span>
                                <span data-selected-text>Pilih Kategori</span>
                            </div>
                            <span class="iconify transform transition-transform" data-icon="mdi:chevron-down" data-width="20" data-arrow></span>
                        </div>
                        
                        <input type="hidden" name="skill_type" id="skill-type-value" required>
                        
                        <div class="dropdown-options hidden" data-options>
                            <div class="dropdown-option" data-value="technical" data-icon="mdi:code-braces">
                                <span class="iconify" data-icon="mdi:code-braces" data-width="20"></span>
                                <span>Technical Skill</span>
                            </div>
                            <div class="dropdown-option" data-value="soft" data-icon="mdi:account-group">
                                <span class="iconify" data-icon="mdi:account-group" data-width="20"></span>
                                <span>Soft Skill</span>
                            </div>
                            <div class="dropdown-option" data-value="tool" data-icon="mdi:tools">
                                <span class="iconify" data-icon="mdi:tools" data-width="20"></span>
                                <span>Tool & Software</span>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" 
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
                            class="tab-button flex-1 py-3 px-4 text-center font-semibold border-b-2 transition-colors flex items-center justify-center gap-2 border-blue-500 text-blue-700 bg-blue-50"
                            data-tab="technical"
                            onclick="switchTab('technical')">
                        <span class="iconify text-blue-600" data-icon="mdi:code-braces" data-width="20"></span>
                        Technical
                        <span class="bg-blue-200 text-blue-800 text-xs px-2 py-1 rounded-full tab-count"><?php echo count($skills_by_category['technical']); ?></span>
                    </button>
                    
                    <button type="button" 
                            class="tab-button flex-1 py-3 px-4 text-center font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition-colors flex items-center justify-center gap-2"
                            data-tab="soft"
                            onclick="switchTab('soft')">
                        <span class="iconify text-gray-500" data-icon="mdi:account-group" data-width="20"></span>
                        Soft Skills
                        <span class="bg-gray-200 text-gray-800 text-xs px-2 py-1 rounded-full tab-count"><?php echo count($skills_by_category['soft']); ?></span>
                    </button>
                    
                    <button type="button" 
                            class="tab-button flex-1 py-3 px-4 text-center font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition-colors flex items-center justify-center gap-2"
                            data-tab="tool"
                            onclick="switchTab('tool')">
                        <span class="iconify text-gray-500" data-icon="mdi:tools" data-width="20"></span>
                        Tools
                        <span class="bg-gray-200 text-gray-800 text-xs px-2 py-1 rounded-full tab-count"><?php echo count($skills_by_category['tool']); ?></span>
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
                                        <?php if (isset($skill['usage_count']) && $skill['usage_count'] > 0): ?>
                                            <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                                                <?php echo $skill['usage_count']; ?> proyek
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex gap-2">
                                        <button onclick="openEditModal(<?php echo $skill['id']; ?>, '<?php echo htmlspecialchars($skill['name']); ?>', '<?php echo $skill['skill_type']; ?>')" 
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
                                        <?php if (isset($skill['usage_count']) && $skill['usage_count'] > 0): ?>
                                            <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                                                <?php echo $skill['usage_count']; ?> proyek
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex gap-2">
                                        <button onclick="openEditModal(<?php echo $skill['id']; ?>, '<?php echo htmlspecialchars($skill['name']); ?>', '<?php echo $skill['skill_type']; ?>')" 
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
                                        <?php if (isset($skill['usage_count']) && $skill['usage_count'] > 0): ?>
                                            <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                                                <?php echo $skill['usage_count']; ?> proyek
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex gap-2">
                                        <button onclick="openEditModal(<?php echo $skill['id']; ?>, '<?php echo htmlspecialchars($skill['name']); ?>', '<?php echo $skill['skill_type']; ?>')" 
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
        <div class="flex items-center gap-3 mb-4">
            <div class="bg-[#E0F7FF] p-2 rounded-lg">
                <span class="iconify text-[#2A8FA9]" data-icon="mdi:pencil" data-width="20"></span>
            </div>
            <h3 class="text-lg font-semibold text-gray-900">Edit Skill</h3>
        </div>
        
        <form method="POST" action="" id="editForm">
            <input type="hidden" name="action" value="edit_skill">
            <input type="hidden" name="skill_id" id="edit_skill_id">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nama Skill</label>
                    <input type="text" name="edit_skill_name" id="edit_skill_name" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors" 
                            required>
                </div>
                
                <!-- Edit Kategori Skill -->
                <div class="custom-dropdown" id="edit-skill-type-dropdown">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Kategori Skill</label>
                    
                    <div class="dropdown-toggle" data-toggle>
                        <div class="flex items-center gap-3 flex-1">
                            <span class="iconify" data-icon="mdi:tag-outline" data-width="20" data-selected-icon></span>
                            <span data-selected-text>Pilih Kategori</span>
                        </div>
                        <span class="iconify transform transition-transform" data-icon="mdi:chevron-down" data-width="20" data-arrow></span>
                    </div>
                    
                    <input type="hidden" name="edit_skill_type" id="edit-skill-type-value" required>
                    
                    <div class="dropdown-options hidden" data-options>
                        <div class="dropdown-option" data-value="technical" data-icon="mdi:code-braces">
                            <span class="iconify" data-icon="mdi:code-braces" data-width="20"></span>
                            <span>Technical Skill</span>
                        </div>
                        <div class="dropdown-option" data-value="soft" data-icon="mdi:account-group">
                            <span class="iconify" data-icon="mdi:account-group" data-width="20"></span>
                            <span>Soft Skill</span>
                        </div>
                        <div class="dropdown-option" data-value="tool" data-icon="mdi:tools">
                            <span class="iconify" data-icon="mdi:tools" data-width="20"></span>
                            <span>Tool & Software</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex gap-3 justify-end mt-6">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium">
                    Batal
                </button>
                <button type="submit" class="bg-[#2A8FA9] text-white px-4 py-2 rounded-lg font-semibold hover:bg-[#409BB2] transition-colors">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<form id="deleteSkillForm" method="GET" style="display: none;">
    <input type="hidden" name="delete_id" id="deleteSkillId">
</form>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
class NotificationManager {
    constructor() {
        this.notificationTimeout = 5000; 
        this.fadeOutDuration = 500; 
        this.maxNotifications = 1;
        this.init();
    }
    
    init() {
        console.log('NotificationManager initialized');
        this.ensureSingleNotification();
        this.setupAutoHide();
        this.addCloseButtons();
        this.clearURLParams();
        this.setupMutationObserver();
    }
    
    ensureSingleNotification() {
        const notifications = this.getAllNotifications();
        
        if (notifications.length > this.maxNotifications) {
            for (let i = this.maxNotifications; i < notifications.length; i++) {
                notifications[i].remove();
            }
            console.log(`Removed ${notifications.length - this.maxNotifications} excess notifications`);
        }
    }
    
    setupAutoHide() {
        const notifications = this.getAllNotifications();
        
        notifications.forEach(notification => {
            const timeoutId = setTimeout(() => {
                this.hideNotification(notification);
            }, this.notificationTimeout);
            
            notification.dataset.timeoutId = timeoutId;
        });
    }
    
    addCloseButtons() {
        const notifications = this.getAllNotifications();
        
        notifications.forEach(notification => {
            if (notification.querySelector('.notification-close')) return;
            
            const closeButton = document.createElement('button');
            closeButton.innerHTML = '<span class="iconify" data-icon="mdi:close" data-width="16"></span>';
            closeButton.className = 'notification-close ml-auto text-gray-400 hover:text-gray-600 transition-colors flex-shrink-0';
            closeButton.setAttribute('type', 'button');
            closeButton.setAttribute('aria-label', 'Tutup notifikasi');
            
            closeButton.addEventListener('click', () => {
                if (notification.dataset.timeoutId) {
                    clearTimeout(parseInt(notification.dataset.timeoutId));
                }
                this.hideNotification(notification);
            });
            
            notification.style.display = 'flex';
            notification.style.alignItems = 'center';
            notification.style.justifyContent = 'space-between';
            notification.appendChild(closeButton);
        });
    }
    
    hideNotification(notification) {
        if (!notification || !notification.parentNode) return;
        
        notification.style.opacity = '0';
        notification.style.transition = `opacity ${this.fadeOutDuration}ms ease`;
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
                console.log('Notification removed');
            }
        }, this.fadeOutDuration);
    }
    
    clearURLParams() {
        const url = new URL(window.location);
        const params = new URLSearchParams(url.search);
        
        const hadNotification = params.has('success') || params.has('error');
        
        if (hadNotification) {
            params.delete('success');
            params.delete('error');
            params.delete('skill');
            
            const newUrl = `${url.pathname}${params.toString() ? '?' + params.toString() : ''}`;
            window.history.replaceState({}, '', newUrl);
            
            console.log('Cleared notification parameters from URL');
        }
    }
    
    setupMutationObserver() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1 && 
                        (node.classList.contains('bg-green-50') || 
                        node.classList.contains('bg-red-50') ||
                        node.querySelector('.bg-green-50') || 
                        node.querySelector('.bg-red-50'))) {
                        console.log('New notification detected');
                        setTimeout(() => {
                            this.init();
                        }, 100);
                    }
                });
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
    
    getAllNotifications() {
        return Array.from(document.querySelectorAll('.bg-green-50, .bg-red-50'))
                    .filter(el => el.closest('body')); 
    }
    
    showNotification(message, type = 'success') {
        this.getAllNotifications().forEach(notification => {
            this.hideNotification(notification);
        });
        
        const notification = document.createElement('div');
        notification.className = `mb-6 ${type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'} px-4 py-3 rounded-lg flex items-center gap-2`;
        notification.innerHTML = `
            <span class="iconify" data-icon="mdi:${type === 'success' ? 'check-circle' : 'alert-circle'}" data-width="20"></span>
            ${message}
        `;
        
        const container = document.querySelector('.max-w-7xl.mx-auto .px-4') || document.body;
        container.insertBefore(notification, container.firstChild);
        
        setTimeout(() => {
            this.init();
        }, 100);
    }
}

function switchTab(tabName) {
    console.log('Switching to tab:', tabName);
    
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.add('hidden');
    });
    document.getElementById(`${tabName}-tab`)?.classList.remove('hidden');
    
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active', 'technical', 'soft', 'tool');
        
        button.classList.add('border-transparent', 'text-gray-500');
        
        const icon = button.querySelector('.iconify');
        if (icon) {
            icon.classList.remove('text-blue-600', 'text-green-600', 'text-purple-600');
            icon.classList.add('text-gray-500');
        }
        
        const count = button.querySelector('.tab-count');
        if (count) {
            count.classList.remove('bg-blue-200', 'text-blue-800', 'bg-green-200', 'text-green-800', 'bg-purple-200', 'text-purple-800');
            count.classList.add('bg-gray-200', 'text-gray-800');
        }
    });
    
    const activeButton = document.querySelector(`[data-tab="${tabName}"]`);
    if (activeButton) {
        activeButton.classList.add('active', tabName);
        activeButton.classList.remove('border-transparent', 'text-gray-500');

        switch(tabName) {
            case 'technical':
                activeButton.querySelector('.iconify')?.classList.replace('text-gray-500', 'text-blue-600');
                activeButton.querySelector('.tab-count')?.classList.replace('bg-gray-200', 'bg-blue-200');
                activeButton.querySelector('.tab-count')?.classList.replace('text-gray-800', 'text-blue-800');
                break;
            case 'soft':
                activeButton.querySelector('.iconify')?.classList.replace('text-gray-500', 'text-green-600');
                activeButton.querySelector('.tab-count')?.classList.replace('bg-gray-200', 'bg-green-200');
                activeButton.querySelector('.tab-count')?.classList.replace('text-gray-800', 'text-green-800');
                break;
            case 'tool':
                activeButton.querySelector('.iconify')?.classList.replace('text-gray-500', 'text-purple-600');
                activeButton.querySelector('.tab-count')?.classList.replace('bg-gray-200', 'bg-purple-200');
                activeButton.querySelector('.tab-count')?.classList.replace('text-gray-800', 'text-purple-800');
                break;
        }
    }
}

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
        this.arrow = this.container.querySelector('[data-arrow]');
        
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
        
        this.options.querySelectorAll('.dropdown-option').forEach(option => {
            option.addEventListener('click', () => {
                const value = option.getAttribute('data-value');
                const text = option.textContent.trim();
                const icon = option.getAttribute('data-icon');
                
                this.setValue(value, text, icon);
                this.closeDropdown();
            });
        });
    }
    
    toggleDropdown() {
        this.options.classList.toggle('hidden');
        this.arrow.style.transform = this.options.classList.contains('hidden') 
            ? 'rotate(0deg)' 
            : 'rotate(180deg)';
    }
    
    closeDropdown() {
        this.options.classList.add('hidden');
        this.arrow.style.transform = 'rotate(0deg)';
    }
    
    setValue(value, text, icon) {
        if (this.hiddenInput) this.hiddenInput.value = value;
        if (this.selectedText) this.selectedText.textContent = text;
        if (this.selectedIcon && icon) this.selectedIcon.setAttribute('data-icon', icon);
    }
    
    getValue() {
        return this.hiddenInput ? this.hiddenInput.value : '';
    }
}

function openEditModal(skillId, skillName, skillType) {
    document.getElementById('edit_skill_id').value = skillId;
    document.getElementById('edit_skill_name').value = skillName;
    
    const editDropdown = window.editDropdown;
    
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

function confirmDeleteSkill(skillId, skillName) {
    Swal.fire({
        title: 'Hapus Skill?',
        html: `<div class="text-center">
                <p class="text-gray-600 mt-2">Skill <strong>"${skillName}"</strong> akan dihapus permanent dari sistem.</p>
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
            title: 'text-lg font-semibold',
            confirmButton: 'px-4 py-2 rounded-lg font-semibold',
            cancelButton: 'px-4 py-2 rounded-lg font-semibold'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('deleteSkillId').value = skillId;
            document.getElementById('deleteSkillForm').submit();
        }
    });
}

document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditModal();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing components...');
    
    window.notificationManager = new NotificationManager();
    
    new CustomDropdown('skill-type-dropdown');
    window.editDropdown = new CustomDropdown('edit-skill-type-dropdown');
    
    switchTab('technical');
    
    if (window.iconify) {
        window.iconify.scan();
    }
    
    console.log('All components initialized');
});

window.addEventListener('load', function() {
    console.log('Window fully loaded');
    setTimeout(() => {
        if (window.notificationManager) {
            window.notificationManager.init();
        }
    }, 500);
});
</script>

<?php include '../../includes/footer.php'; ?>