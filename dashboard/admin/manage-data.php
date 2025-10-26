<?php
include '../../includes/config.php';
include '../../includes/functions.php';

if (!isLoggedIn() || getUserRole() != 'admin') {
    header("Location: ../../login.php");
    exit();
}

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add_specialization') {
        $name = sanitize($_POST['name']);
        
        if (!empty($name)) {
            $check_stmt = $conn->prepare("SELECT id FROM specializations WHERE name = ?");
            $check_stmt->bind_param("s", $name);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                $error = "Spesialisasi '$name' sudah ada!";
            } else {
                $insert_stmt = $conn->prepare("INSERT INTO specializations (name) VALUES (?)");
                $insert_stmt->bind_param("s", $name);
                
                if ($insert_stmt->execute()) {
                    $success = "Spesialisasi '$name' berhasil ditambahkan!";
                } else {
                    $error = "Gagal menambahkan spesialisasi!";
                }
            }
        }
    }
    
    // Handle edit specialization
    elseif ($action == 'edit_specialization') {
        $id = intval($_POST['id']);
        $name = sanitize($_POST['name']);
        
        if (!empty($name) && $id > 0) {
            // Check if name already exists (excluding current record)
            $check_stmt = $conn->prepare("SELECT id FROM specializations WHERE name = ? AND id != ?");
            $check_stmt->bind_param("si", $name, $id);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                $error = "Spesialisasi '$name' sudah ada!";
            } else {
                $update_stmt = $conn->prepare("UPDATE specializations SET name = ? WHERE id = ?");
                $update_stmt->bind_param("si", $name, $id);
                
                if ($update_stmt->execute()) {
                    $success = "Spesialisasi berhasil diperbarui!";
                } else {
                    $error = "Gagal memperbarui spesialisasi!";
                }
            }
        }
    }
    
    // Handle delete specialization
    elseif ($action == 'delete_specialization') {
        $id = intval($_POST['id']);
        $name = sanitize($_POST['name']);
        
        if ($id > 0) {
            // Check if specialization is being used in any projects or users
            // Perbaikan query untuk pengecekan usage
            $user_count = 0;
            $project_count = 0;
            
            // Cek di user_specializations
            $check_user = $conn->prepare("SELECT COUNT(*) as count FROM user_specializations WHERE specialization_id = ?");
            if ($check_user) {
                $check_user->bind_param("i", $id);
                $check_user->execute();
                $user_result = $check_user->get_result();
                if ($user_row = $user_result->fetch_assoc()) {
                    $user_count = $user_row['count'];
                }
                $check_user->close();
            }
            
            // Cek di project_specializations  
            $check_project = $conn->prepare("SELECT COUNT(*) as count FROM project_specializations WHERE specialization_id = ?");
            if ($check_project) {
                $check_project->bind_param("i", $id);
                $check_project->execute();
                $project_result = $check_project->get_result();
                if ($project_row = $project_result->fetch_assoc()) {
                    $project_count = $project_row['count'];
                }
                $check_project->close();
            }
            
            if ($user_count > 0 || $project_count > 0) {
                $error = "Tidak dapat menghapus spesialisasi '$name' karena sedang digunakan ($user_count user, $project_count proyek)!";
            } else {
                $delete_stmt = $conn->prepare("DELETE FROM specializations WHERE id = ?");
                $delete_stmt->bind_param("i", $id);
                
                if ($delete_stmt->execute()) {
                    $success = "Spesialisasi '$name' berhasil dihapus!";
                } else {
                    $error = "Gagal menghapus spesialisasi!";
                }
            }
        }
    }
}

// Fetch specializations only
$specializations = [];
$specializations_result = $conn->query("SELECT * FROM specializations ORDER BY name");
while ($specialization = $specializations_result->fetch_assoc()) {
    $specializations[] = $specialization;
}
?>

<?php include '../../includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
            <div>
                <h1 class="text-3xl font-bold text-[#2A8FA9] flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:database-cog" data-width="32"></span>
                    Kelola Spesialisasi
                </h1>
                <p class="text-gray-600 mt-2">Kelola data spesialisasi untuk user dan proyek</p>
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

    <!-- Main Content -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 mb-6">
        <div class="p-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Add Specialization Form -->
                <div class="lg:col-span-1">
                    <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
                        <h3 class="text-lg font-semibold text-[#2A8FA9] mb-4 flex items-center gap-2">
                            <span class="iconify" data-icon="mdi:plus-circle" data-width="20"></span>
                            Tambah Spesialisasi Baru
                        </h3>
                        
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="add_specialization">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nama Spesialisasi</label>
                                <input type="text" name="name" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors text-sm" 
                                    placeholder="Contoh: Frontend Development" required>
                            </div>
                            
                            <button type="submit" 
                                    class="w-full bg-[#2A8FA9] text-white py-2 px-4 rounded-lg font-semibold hover:bg-[#409BB2] transition-colors duration-300 flex items-center justify-center gap-2 text-sm">
                                <span class="iconify" data-icon="mdi:plus" data-width="16"></span>
                                Tambah Spesialisasi
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Specializations List -->
                <div class="lg:col-span-2">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Daftar Spesialisasi</h3>
                        <span class="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded-full">
                            <?php echo count($specializations); ?> spesialisasi
                        </span>
                    </div>
                    
                    <?php if (!empty($specializations)): ?>
                        <div class="space-y-4">
                            <?php foreach ($specializations as $spec): ?>
                                <div class="bg-white border border-gray-200 rounded-xl p-4 flex justify-between items-center">
                                    <div>
                                        <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($spec['name']); ?></h4>
                                        <p class="text-sm text-gray-500 mt-1">
                                            Dibuat: <?php echo date('d M Y', strtotime($spec['created_at'])); ?>
                                        </p>
                                    </div>
                                    <div class="flex gap-2">
                                        <button onclick="openEditModal(<?php echo $spec['id']; ?>, '<?php echo htmlspecialchars($spec['name']); ?>')" 
                                                class="text-gray-400 hover:text-[#2A8FA9] transition-colors p-1"
                                                title="Edit Spesialisasi">
                                            <span class="iconify" data-icon="mdi:pencil" data-width="16"></span>
                                        </button>
                                        <button onclick="confirmDeleteSpecialization(<?php echo $spec['id']; ?>, '<?php echo htmlspecialchars($spec['name']); ?>')" 
                                                class="text-gray-400 hover:text-red-600 transition-colors p-1"
                                                title="Hapus Spesialisasi">
                                            <span class="iconify" data-icon="mdi:delete" data-width="16"></span>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <span class="iconify inline-block mb-2" data-icon="mdi:certificate-off" data-width="32"></span>
                            <p>Belum ada spesialisasi</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-[#2A8FA9]">Edit Spesialisasi</h3>
            <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                <span class="iconify" data-icon="mdi:close" data-width="24"></span>
            </button>
        </div>
        
        <form id="editForm" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="edit_specialization">
            <input type="hidden" name="id" id="editId">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Nama Spesialisasi</label>
                <input type="text" name="name" id="editName"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9] transition-colors text-sm" 
                    required>
            </div>
            
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeEditModal()" 
                        class="flex-1 bg-gray-100 text-gray-700 py-2 px-4 rounded-lg font-semibold hover:bg-gray-200 transition-colors duration-300">
                    Batal
                </button>
                <button type="submit" 
                        class="flex-1 bg-[#2A8FA9] text-white py-2 px-4 rounded-lg font-semibold hover:bg-[#409BB2] transition-colors duration-300">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden form untuk delete -->
<form id="deleteSpecializationForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_specialization">
    <input type="hidden" name="id" id="deleteSpecializationId">
    <input type="hidden" name="name" id="deleteSpecializationName">
</form>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Edit Modal Functions
function openEditModal(id, name) {
    document.getElementById('editId').value = id;
    document.getElementById('editName').value = name;
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

// SweetAlert Delete Confirmation
function confirmDeleteSpecialization(specializationId, specializationName) {
    Swal.fire({
        title: 'Hapus Spesialisasi?',
        html: `<div class="text-center">
                <p class="text-gray-600 mt-2">Spesialisasi <strong>"${specializationName}"</strong> akan dihapus permanent dari sistem.</p>
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
            // Submit form untuk delete
            document.getElementById('deleteSpecializationId').value = specializationId;
            document.getElementById('deleteSpecializationName').value = specializationName;
            document.getElementById('deleteSpecializationForm').submit();
        }
    });
}

// Close modals when clicking outside
document.addEventListener('click', function(event) {
    const editModal = document.getElementById('editModal');
    
    if (event.target === editModal) {
        closeEditModal();
    }
});

// Close modals with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeEditModal();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>