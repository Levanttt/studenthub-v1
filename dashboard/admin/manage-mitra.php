<?php
include '../../includes/config.php';
include '../../includes/functions.php';

if (!isLoggedIn() || getUserRole() != 'admin') {
    header("Location: ../../login.php");
    exit();
}

$success = '';
$error = '';

// Handle verification status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_verification'])) {
    $user_id = intval($_POST['user_id']);
    $verification_status = sanitize($_POST['verification_status']);
    
    $update_stmt = $conn->prepare("UPDATE users SET verification_status = ? WHERE id = ? AND role = 'mitra_industri'");
    $update_stmt->bind_param("si", $verification_status, $user_id);
    
    if ($update_stmt->execute()) {
        $success = "Status verifikasi berhasil diupdate!";
    } else {
        $error = "Gagal mengupdate status verifikasi!";
    }
    $update_stmt->close();
}

// Fetch all mitra industri
$mitra = [];
$mitra_query = "
    SELECT id, name, email, company_name, position, verification_status, created_at 
    FROM users 
    WHERE role = 'mitra_industri' 
    ORDER BY created_at DESC
";
$mitra_result = $conn->query($mitra_query);
while ($m = $mitra_result->fetch_assoc()) {
    $mitra[] = $m;
}

// Stats
$total_mitra = count($mitra);
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
            <h2 class="text-xl font-bold text-[#2A8FA9] flex items-center gap-2">
                <span class="iconify" data-icon="mdi:account-tie" data-width="24"></span>
                Daftar Mitra Industri
            </h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nama</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Perusahaan</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Posisi</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($mitra)): ?>
                        <?php foreach ($mitra as $m): ?>
                            <tr class="hover:bg-gray-50 transition-colors duration-200">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($m['name']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-600"><?php echo htmlspecialchars($m['email']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-600"><?php echo htmlspecialchars($m['company_name'] ?? '-'); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-600"><?php echo htmlspecialchars($m['position'] ?? '-'); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $status_config = [
                                        'pending' => ['color' => 'bg-yellow-100 text-yellow-800', 'icon' => 'mdi:clock'],
                                        'verified' => ['color' => 'bg-green-100 text-green-800', 'icon' => 'mdi:check-circle'],
                                        'rejected' => ['color' => 'bg-red-100 text-red-800', 'icon' => 'mdi:close-circle']
                                    ];
                                    $config = $status_config[$m['verification_status']] ?? $status_config['pending'];
                                    ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $config['color']; ?>">
                                        <span class="iconify mr-1" data-icon="<?php echo $config['icon']; ?>" data-width="12"></span>
                                        <?php echo ucfirst($m['verification_status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <form method="POST" class="inline-flex items-center gap-2">
                                        <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
                                        <select name="verification_status" 
                                                onchange="this.form.submit()" 
                                                class="text-xs border border-gray-300 rounded-lg px-2 py-1 focus:ring-2 focus:ring-[#2A8FA9] focus:border-[#2A8FA9]">
                                            <option value="pending" <?php echo $m['verification_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="verified" <?php echo $m['verification_status'] == 'verified' ? 'selected' : ''; ?>>Verified</option>
                                            <option value="rejected" <?php echo $m['verification_status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        </select>
                                        <input type="hidden" name="update_verification" value="1">
                                    </form>
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
    </div>
</div>

<?php include '../../includes/footer.php'; ?>