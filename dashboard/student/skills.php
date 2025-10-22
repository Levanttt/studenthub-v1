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

if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    $check_usage = $conn->prepare("SELECT ps.id FROM project_skills ps WHERE ps.skill_id = ? LIMIT 1");
    $check_usage->bind_param("i", $delete_id);
    $check_usage->execute();
    $usage_result = $check_usage->get_result();
    
    if ($usage_result->num_rows > 0) {
        $error = "Tidak dapat menghapus skill karena sedang digunakan dalam proyek!";
    } else {
        $delete_stmt = $conn->prepare("DELETE FROM skills WHERE id = ?");
        $delete_stmt->bind_param("i", $delete_id);
        
        if ($delete_stmt->execute()) {
            $success = "Skill berhasil dihapus!";
        } else {
            $error = "Gagal menghapus skill: " . $conn->error;
        }
        $delete_stmt->close();
    }
    $check_usage->close();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_skill'])) {
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
$skills_stmt->execute();
$skills_result = $skills_stmt->get_result();

while ($skill = $skills_result->fetch_assoc()) {
    $skills_by_category[$skill['skill_type']][] = $skill;
}

$total_skills = $skills_result->num_rows;
$skills_stmt->close();

$skill_usage = [];
foreach ($skills_by_category as $category => $skills) {
    foreach ($skills as $skill) {
        $skill_usage[$skill['id']] = $skill['usage_count'];
    }
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
                        <h3 class="text-blue-900 font-bold text-2xl"><?php echo count($skills_by_category['technical']); ?></h3>
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
                        <h3 class="text-green-900 font-bold text-2xl"><?php echo count($skills_by_category['soft']); ?></h3>
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
                        <h3 class="text-purple-900 font-bold text-2xl"><?php echo count($skills_by_category['tool']); ?></h3>
                        <p class="text-purple-700 text-sm">Tools & Software</p>
                    </div>
                </div>
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
                            class="tab-button flex-1 py-3 px-4 text-center font-semibold border-b-2 border-transparent hover:bg-gray-50 transition-colors flex items-center justify-center gap-2"
                            data-tab="technical"
                            onclick="switchTab('technical')">
                        <span class="iconify" data-icon="mdi:code-braces" data-width="20"></span>
                        Technical
                        <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full"><?php echo count($skills_by_category['technical']); ?></span>
                    </button>
                    
                    <button type="button" 
                            class="tab-button flex-1 py-3 px-4 text-center font-semibold border-b-2 border-transparent hover:bg-gray-50 transition-colors flex items-center justify-center gap-2"
                            data-tab="soft"
                            onclick="switchTab('soft')">
                        <span class="iconify" data-icon="mdi:account-group" data-width="20"></span>
                        Soft Skills
                        <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full"><?php echo count($skills_by_category['soft']); ?></span>
                    </button>
                    
                    <button type="button" 
                            class="tab-button flex-1 py-3 px-4 text-center font-semibold border-b-2 border-transparent hover:bg-gray-50 transition-colors flex items-center justify-center gap-2"
                            data-tab="tool"
                            onclick="switchTab('tool')">
                        <span class="iconify" data-icon="mdi:tools" data-width="20"></span>
                        Tools
                        <span class="bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded-full"><?php echo count($skills_by_category['tool']); ?></span>
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
                                        <button onclick="confirmDelete(<?php echo $skill['id']; ?>, '<?php echo htmlspecialchars($skill['name']); ?>')" 
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
                                        <button onclick="confirmDelete(<?php echo $skill['id']; ?>, '<?php echo htmlspecialchars($skill['name']); ?>')" 
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
                                        <button onclick="confirmDelete(<?php echo $skill['id']; ?>, '<?php echo htmlspecialchars($skill['name']); ?>')" 
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
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4">
        <h3 class="text-xl font-bold text-[#2A8FA9] mb-4 flex items-center gap-2">
            <span class="iconify" data-icon="mdi:pencil" data-width="24"></span>
            Edit Skill
        </h3>
        
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
            
            <div class="flex gap-3 mt-6">
                <button type="button" onclick="closeEditModal()" 
                        class="flex-1 bg-gray-100 text-gray-700 py-3 px-4 rounded-lg font-semibold hover:bg-gray-200 transition-colors duration-300">
                    Batal
                </button>
                <button type="submit" 
                        class="flex-1 bg-gradient-to-r from-[#2A8FA9] to-[#409BB2] text-white py-3 px-4 rounded-lg font-semibold hover:from-[#409BB2] hover:to-[#489EB7] transition-all duration-300">
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
        
        // Debug logging
        console.log('Initializing dropdown:', containerId, {
            toggle: !!this.toggle,
            options: !!this.options,
            hiddenInput: !!this.hiddenInput,
            selectedText: !!this.selectedText,
            selectedIcon: !!this.selectedIcon
        });
        
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
                
                console.log('Option selected:', value, text, icon);
                
                this.hiddenInput.value = value;
                this.selectedText.textContent = text;
                if (this.selectedIcon) {
                    this.selectedIcon.setAttribute('data-icon', icon);
                }
                
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

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing components...');
    
    // Set technical as default active tab
    switchTab('technical');
    
    // Initialize custom dropdowns
    const skillTypeDropdown = new CustomDropdown('skill-type-dropdown');
    const editSkillTypeDropdown = new CustomDropdown('edit-skill-type-dropdown');
    
    // Debug: Check if dropdowns were initialized
    console.log('Dropdowns initialized:', {
        skillType: skillTypeDropdown,
        editSkillType: editSkillTypeDropdown
    });
});

function openEditModal(skillId, skillName, skillType) {
    document.getElementById('edit_skill_id').value = skillId;
    document.getElementById('edit_skill_name').value = skillName;
    
    // Set values for edit modal dropdown
    const editDropdown = document.getElementById('edit-skill-type-dropdown');
    if (!editDropdown) {
        console.error('Edit dropdown container not found');
        return;
    }
    
    const hiddenInput = editDropdown.querySelector('input[type="hidden"]');
    const selectedText = editDropdown.querySelector('[data-selected-text]');
    const selectedIcon = editDropdown.querySelector('[data-selected-icon]');
    
    // Map skill type to display text and icon
    const typeMap = {
        'technical': { text: 'Technical Skill', icon: 'mdi:code-braces' },
        'soft': { text: 'Soft Skill', icon: 'mdi:account-group' },
        'tool': { text: 'Tool & Software', icon: 'mdi:tools' }
    };
    
    if (typeMap[skillType]) {
        hiddenInput.value = skillType;
        selectedText.textContent = typeMap[skillType].text;
        if (selectedIcon) {
            selectedIcon.setAttribute('data-icon', typeMap[skillType].icon);
        }
        
        // Update selected state in options
        editDropdown.querySelectorAll('[data-option]').forEach(option => {
            option.classList.remove('bg-blue-50', 'text-blue-700');
            if (option.getAttribute('data-value') === skillType) {
                option.classList.add('bg-blue-50', 'text-blue-700');
            }
        });
    }
    
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

function confirmDelete(skillId, skillName) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Hapus Skill?',
            html: `<div class="text-left">
                    <p class="text-red-600 font-semibold">Skill: <span class="text-gray-800">"${skillName}"</span></p>
                    <p class="text-gray-600 mt-2">Skill akan dihapus permanent dari sistem.</p>
                    <p class="text-amber-600 text-sm mt-2"><strong>Note:</strong> Skill yang sedang digunakan dalam proyek tidak dapat dihapus.</p>
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
                window.location.href = `skills.php?delete_id=${skillId}`;
            }
        });
    } else {
        // Fallback jika SweetAlert tidak tersedia
        if (confirm(`Hapus skill "${skillName}"?`)) {
            window.location.href = `skills.php?delete_id=${skillId}`;
        }
    }
}

// Close modal when clicking outside
document.getElementById('editModal')?.addEventListener('click', function(e) {
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
</script>

<?php include '../../includes/footer.php'; ?>