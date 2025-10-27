<?php
include '../../includes/config.php';
include '../../includes/functions.php';

if (!isLoggedIn() || getUserRole() != 'admin') {
    header("Location: ../../login.php");
    exit();
}

$success = '';
$error = '';

// Pagination setup
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$mitra_per_page = 10;
$offset = ($current_page - 1) * $mitra_per_page;

// Handle verification status update via AJAX
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_verification') {
    header('Content-Type: application/json');
    
    try {
        if (!isset($_POST['user_id']) || !isset($_POST['verification_status'])) {
            throw new Exception('Data tidak lengkap');
        }
        
        $user_id = intval($_POST['user_id']);
        $verification_status = $_POST['verification_status'];
        
        $allowed_statuses = ['pending', 'verified', 'rejected'];
        if (!in_array($verification_status, $allowed_statuses)) {
            throw new Exception('Status tidak valid');
        }
        
        $update_stmt = $conn->prepare("UPDATE users SET verification_status = ? WHERE id = ? AND role = 'mitra_industri'");
        if (!$update_stmt) {
            throw new Exception('Prepare statement failed: ' . $conn->error);
        }
        
        $update_stmt->bind_param('si', $verification_status, $user_id);
        
        if ($update_stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Status verifikasi berhasil diupdate!',
                'user_id' => $user_id,
                'new_status' => $verification_status
            ]);
        } else {
            throw new Exception('Execute failed: ' . $update_stmt->error);
        }
        
        $update_stmt->close();
        
    } catch (Exception $e) {
        error_log("Update verification error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Gagal mengupdate status verifikasi: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Fetch total mitra count for pagination
$total_mitra = 0;
$count_query = "SELECT COUNT(*) as total FROM users WHERE role = 'mitra_industri'";
$count_result = $conn->query($count_query);
if ($count_result) {
    $total_mitra = $count_result->fetch_assoc()['total'];
}

// Calculate total pages
$total_pages = ceil($total_mitra / $mitra_per_page);

// Fetch mitra with pagination
$mitra = [];
$mitra_query = "
    SELECT id, name, email, company_name, position, verification_status, created_at 
    FROM users 
    WHERE role = 'mitra_industri' 
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
";
$mitra_stmt = $conn->prepare($mitra_query);
$mitra_stmt->bind_param("ii", $mitra_per_page, $offset);
$mitra_stmt->execute();
$mitra_result = $mitra_stmt->get_result();
while ($m = $mitra_result->fetch_assoc()) {
    $mitra[] = $m;
}
$mitra_stmt->close();

// Stats
$verified_mitra = array_filter($mitra, function($m) {
    return $m['verification_status'] == 'verified';
});
$pending_mitra = array_filter($mitra, function($m) {
    return $m['verification_status'] == 'pending';
});
$rejected_mitra = array_filter($mitra, function($m) {
    return $m['verification_status'] == 'rejected';
});
?>

<?php include '../../includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
            <div>
                <h1 class="text-3xl font-bold text-[#2A8FA9] flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:office-building-cog" data-width="32"></span>
                    Kelola Mitra Industri
                </h1>
                <p class="text-gray-600 mt-2">Verifikasi dan kelola akun mitra industri</p>
            </div>
            <a href="index.php" class="bg-[#E0F7FF] text-[#2A8FA9] px-6 py-3 rounded-xl font-semibold hover:bg-[#51A3B9] hover:text-white transition-colors duration-300 border border-[#51A3B9] border-opacity-30 flex items-center gap-2">
                <span class="iconify" data-icon="mdi:arrow-left" data-width="18"></span>
                Kembali ke Dashboard
            </a>
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

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-gradient-to-r from-blue-50 to-blue-100/50 rounded-2xl p-6 border border-blue-200">
            <div class="flex items-center gap-4">
                <div class="bg-blue-600 p-3 rounded-xl">
                    <span class="iconify text-white" data-icon="mdi:office-building" data-width="24"></span>
                </div>
                <div>
                    <h3 class="text-blue-900 font-bold text-2xl"><?php echo $total_mitra; ?></h3>
                    <p class="text-blue-700 text-sm">Total Mitra</p>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-r from-green-50 to-green-100/50 rounded-2xl p-6 border border-green-200">
            <div class="flex items-center gap-4">
                <div class="bg-green-600 p-3 rounded-xl">
                    <span class="iconify text-white" data-icon="mdi:shield-check" data-width="24"></span>
                </div>
                <div>
                    <h3 class="text-green-900 font-bold text-2xl"><?php echo count($verified_mitra); ?></h3>
                    <p class="text-green-700 text-sm">Terverifikasi</p>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-r from-yellow-50 to-yellow-100/50 rounded-2xl p-6 border border-yellow-200">
            <div class="flex items-center gap-4">
                <div class="bg-yellow-600 p-3 rounded-xl">
                    <span class="iconify text-white" data-icon="mdi:clock-alert" data-width="24"></span>
                </div>
                <div>
                    <h3 class="text-yellow-900 font-bold text-2xl"><?php echo count($pending_mitra); ?></h3>
                    <p class="text-yellow-700 text-sm">Menunggu</p>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-r from-red-50 to-red-100/50 rounded-2xl p-6 border border-red-200">
            <div class="flex items-center gap-4">
                <div class="bg-red-600 p-3 rounded-xl">
                    <span class="iconify text-white" data-icon="mdi:close-circle" data-width="24"></span>
                </div>
                <div>
                    <h3 class="text-red-900 font-bold text-2xl"><?php echo count($rejected_mitra); ?></h3>
                    <p class="text-red-700 text-sm">Ditolak</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Mitra Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
        <div class="p-6 border-b border-gray-200">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <h2 class="text-xl font-bold text-[#2A8FA9] flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:account-tie" data-width="24"></span>
                    Daftar Mitra Industri
                </h2>
                <p class="text-gray-600 text-sm">
                    Menampilkan <span class="font-bold text-[#2A8FA9]"><?php echo count($mitra); ?></span> dari 
                    <span class="font-bold text-[#2A8FA9]"><?php echo $total_mitra; ?></span> mitra
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-16">No</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nama</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Perusahaan</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Posisi</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-44">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($mitra)): ?>
                        <?php foreach ($mitra as $index => $m): ?>
                            <tr class="hover:bg-gray-50 transition-colors duration-200" data-mitra-id="<?php echo $m['id']; ?>">
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 text-left pl-5">
                                    <?php echo $offset + $index + 1; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($m['name']); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                    <?php echo htmlspecialchars($m['email']); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                    <?php echo htmlspecialchars($m['company_name'] ?? '-'); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                    <?php echo htmlspecialchars($m['position'] ?? '-'); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <?php
                                    $current_status = $m['verification_status'] ?? 'pending';
                                    $statusConfig = [
                                        'pending' => ['icon' => 'mdi:clock', 'iconColor' => 'text-[#2A8FA9]'],
                                        'verified' => ['icon' => 'mdi:check-circle', 'iconColor' => 'text-[#2A8FA9]'],
                                        'rejected' => ['icon' => 'mdi:close-circle', 'iconColor' => 'text-[#2A8FA9]']
                                    ];
                                    $config = $statusConfig[$current_status] ?? $statusConfig['pending'];
                                    ?>
                                    
                                    <div class="relative" id="status-container-<?php echo $m['id']; ?>">
                                        <!-- Icon indicator -->
                                        <span class="absolute left-2 top-1/2 -translate-y-1/2 pointer-events-none z-10 status-icon">
                                            <span class="iconify <?php echo $config['iconColor']; ?>" 
                                                data-icon="<?php echo $config['icon']; ?>" 
                                                data-width="14"></span>
                                        </span>
                                        
                                        <!-- Native Select dengan styling -->
                                        <select onchange="updateVerificationStatus(<?php echo $m['id']; ?>, this.value, this)" 
                                                class="status-select bg-[#E0F7FF] text-[#2A8FA9] border border-[#51A3B9] border-opacity-30 pl-8 pr-8 py-2 rounded-lg text-xs font-semibold w-full appearance-none cursor-pointer transition-colors duration-300 flex items-center gap-2"
                                                data-current-status="<?php echo $current_status; ?>">
                                            <option value="pending" <?php echo $current_status == 'pending' ? 'selected' : ''; ?>>PENDING</option>
                                            <option value="verified" <?php echo $current_status == 'verified' ? 'selected' : ''; ?>>VERIFIED</option>
                                            <option value="rejected" <?php echo $current_status == 'rejected' ? 'selected' : ''; ?>>REJECTED</option>
                                        </select>
                                        
                                        <!-- Chevron icon -->
                                        <span class="absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none">
                                            <span class="iconify text-[#2A8FA9]" data-icon="mdi:chevron-down" data-width="14"></span>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="text-gray-500 flex flex-col items-center">
                                    <span class="iconify text-gray-400 mb-3" data-icon="mdi:office-building-off" data-width="48"></span>
                                    <p class="text-lg font-medium text-gray-900 mb-2">Belum ada mitra industri</p>
                                    <p class="text-gray-600">Mitra industri yang mendaftar akan muncul di sini.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
            <div class="flex justify-between items-center">
                <p class="text-sm text-gray-700">
                    Halaman <span class="font-medium"><?php echo $current_page; ?></span> dari 
                    <span class="font-medium"><?php echo $total_pages; ?></span>
                </p>
                <div class="flex items-center space-x-1">
                    <!-- First Page -->
                    <?php if ($current_page > 1): ?>
                        <a href="?page=1" 
                           class="flex items-center justify-center w-8 h-8 bg-white border border-gray-300 rounded font-medium text-gray-700 hover:bg-gray-50 transition-colors duration-300 text-sm">
                            <span class="iconify" data-icon="mdi:chevron-double-left" data-width="14"></span>
                        </a>
                    <?php endif; ?>

                    <!-- Previous Page -->
                    <?php if ($current_page > 1): ?>
                        <a href="?page=<?php echo $current_page - 1; ?>" 
                           class="flex items-center justify-center w-8 h-8 bg-white border border-gray-300 rounded font-medium text-gray-700 hover:bg-gray-50 transition-colors duration-300 text-sm">
                            <span class="iconify" data-icon="mdi:chevron-left" data-width="14"></span>
                        </a>
                    <?php endif; ?>

                    <!-- Page Numbers -->
                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?page=<?php echo $i; ?>" 
                           class="flex items-center justify-center w-8 h-8 rounded font-medium transition-colors duration-300 text-sm <?php echo $i == $current_page ? 'bg-[#51A3B9] text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <!-- Next Page -->
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?php echo $current_page + 1; ?>" 
                           class="flex items-center justify-center w-8 h-8 bg-white border border-gray-300 rounded font-medium text-gray-700 hover:bg-gray-50 transition-colors duration-300 text-sm">
                            <span class="iconify" data-icon="mdi:chevron-right" data-width="14"></span>
                        </a>
                    <?php endif; ?>

                    <!-- Last Page -->
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?php echo $total_pages; ?>" 
                           class="flex items-center justify-center w-8 h-8 bg-white border border-gray-300 rounded font-medium text-gray-700 hover:bg-gray-50 transition-colors duration-300 text-sm">
                            <span class="iconify" data-icon="mdi:chevron-double-right" data-width="14"></span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.status-select {
    background-color: #e8e8e8ff;
    color: #2A8FA9;
    border: 1px solid #51a2b994;
    border-opacity: 0.3;
    transition: all 0.3s ease;
    min-width: 130px;
    transform: scale(1);
    padding-left: 2rem;
    padding-right: 2rem;
    padding-top: 0.5rem;
    padding-bottom: 0.5rem;
    border-radius: 0.75rem;
    font-size: 0.75rem;
    font-weight: 600;
    width: 100%;
    appearance: none;
    cursor: pointer;
}

.status-select:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.ring-2 { 
    box-shadow: 0 0 0 1px rgba(81, 163, 185, 0.5); 
}

.status-select::-ms-expand {
    display: none;
}

.status-select option {
    background: white;
    color: #1f2937;
    padding: 8px;
}

table {
    table-layout: auto;
}

th:nth-child(1), td:nth-child(1) { width: 60px; }
th:nth-child(6), td:nth-child(6) { width: 176px; }
</style>

<script>
const VERIFICATION_STATUS_CONFIG = {
    'pending': {
        icon: 'mdi:clock',
        iconColor: 'text-[#2A8FA9]'
    },
    'verified': {
        icon: 'mdi:check-circle',
        iconColor: 'text-[#2A8FA9]'
    },
    'rejected': {
        icon: 'mdi:close-circle',
        iconColor: 'text-[#2A8FA9]'
    }
};

function updateVerificationStatus(userId, newStatus, selectElement) {
    console.log('🔄 Updating mitra verification:', userId, 'to:', newStatus);
    
    const parentDiv = selectElement.parentElement;
    const iconElement = parentDiv.querySelector('.status-icon .iconify');
    
    selectElement.disabled = true;
    selectElement.style.opacity = '0.6';
    
    const formData = new URLSearchParams();
    formData.append('action', 'update_verification');
    formData.append('user_id', userId);
    formData.append('verification_status', newStatus);
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            console.log('✅ Verification status updated successfully');
            
            Array.from(selectElement.options).forEach(option => {
                option.selected = (option.value === newStatus);
            });
            
            selectElement.setAttribute('data-current-status', newStatus);
            
            if (iconElement) {
                const config = VERIFICATION_STATUS_CONFIG[newStatus];
                if (config) {
                    iconElement.setAttribute('data-icon', config.icon);
                    iconElement.className = `iconify ${config.iconColor}`;
                    
                    if (window.Iconify && window.Iconify.replace) {
                        window.Iconify.replace(iconElement);
                    } else if (window.iconify && window.iconify.replace) {
                        window.iconify.replace(iconElement);
                    }
                    
                    console.log('🎨 Icon updated to:', config.icon);
                }
            }
            
            selectElement.classList.add('ring-2', 'ring-[#51A3B9]', 'scale-105');
            setTimeout(() => {
                selectElement.classList.remove('ring-2', 'ring-[#51A3B9]', 'scale-105');
            }, 1000);
            
            console.log('✨ UI fully updated to:', newStatus);
            
        } else {
            throw new Error(data.message || 'Unknown server error');
        }
    })
    .catch(error => {
        console.error('💥 Error:', error);
        alert('Error saat update status verifikasi: ' + error.message);
    })
    .finally(() => {
        selectElement.disabled = false;
        selectElement.style.opacity = '1';
    });
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Manage Mitra dashboard loaded');
    console.log('📋 Verification status config:', VERIFICATION_STATUS_CONFIG);
});
</script>

<?php include '../../includes/footer.php'; ?>