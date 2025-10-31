<?php
include '../../includes/config.php';
include '../../includes/functions.php';

if (!isLoggedIn() || getUserRole() != 'admin') {
    header("Location: ../../login.php");
    exit();
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $interest_id = intval($_POST['interest_id']);
    $status = $_POST['status'];
    
    try {
        $stmt = $conn->prepare("UPDATE mitra_interest SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $interest_id);
        
        if ($stmt->execute()) {
            $success_message = "Status berhasil diupdate!";
        } else {
            $error_message = "Gagal update status.";
        }
        $stmt->close();
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Handle filters
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$query = "
    SELECT mi.*, 
           m.company_name as mitra_company, 
           m.email as mitra_email,
           m.phone as mitra_phone,
           s.name as student_name,
           s.email as student_email,
           s.major as student_major,
           s.profile_picture as student_photo
    FROM mitra_interest mi
    JOIN users m ON mi.mitra_id = m.id
    JOIN users s ON mi.student_id = s.id
    WHERE 1=1
";

$params = [];
$types = "";

if ($status_filter !== 'all') {
    $query .= " AND mi.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($search)) {
    $query .= " AND (m.company_name LIKE ? OR s.name LIKE ? OR s.major LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

$query .= " ORDER BY mi.created_at DESC";

// Execute query
$interests = [];
try {
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $interests = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $error_message = "Error fetching data: " . $e->getMessage();
}

// Get counts for stats
$stats = [
    'all' => getCount("SELECT COUNT(*) as total FROM mitra_interest"),
    'pending' => getCount("SELECT COUNT(*) as total FROM mitra_interest WHERE status = 'pending'"),
    'contacted' => getCount("SELECT COUNT(*) as total FROM mitra_interest WHERE status = 'contacted'")
];

function getCount($query) {
    global $conn;
    $result = $conn->query($query);
    return $result ? $result->fetch_assoc()['total'] : 0;
}

function getStatusBadgeClass($status) {
    return [
        'pending' => 'bg-yellow-100 text-yellow-800 border border-yellow-200',
        'contacted' => 'bg-green-100 text-green-800 border border-green-200'
    ][$status] ?? 'bg-gray-100 text-gray-800 border border-gray-200';
}

function getStatusText($status) {
    return [
        'pending' => 'Menunggu',
        'contacted' => 'Dihubungi'
    ][$status] ?? 'Unknown';
}
?>

<?php include '../../includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-[#2A8FA9] flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:heart-multiple" data-width="28"></span>
                    Kelola Ketertarikan Mitra
                </h1>
                <p class="text-gray-600 mt-2">Kelola semua permintaan ketertarikan dari mitra industri</p>
            </div>
            <a href="index.php" class="bg-[#E0F7FF] text-[#2A8FA9] px-6 py-3 rounded-xl font-semibold hover:bg-[#51A3B9] hover:text-white transition-colors duration-300 border border-[#51A3B9] border-opacity-30 flex items-center gap-2">
                <span class="iconify" data-icon="mdi:arrow-left" data-width="16"></span>
                Kembali ke Dashboard
            </a>
        </div>
    </div>

    <!-- Notification -->
    <?php if (isset($success_message)): ?>
        <div class="mb-6 p-4 bg-green-100 text-green-800 border border-green-200 rounded-lg">
            <div class="flex items-center gap-2">
                <span class="iconify" data-icon="mdi:check-circle" data-width="20"></span>
                <span class="font-medium"><?php echo $success_message; ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="mb-6 p-4 bg-red-100 text-red-800 border border-red-200 rounded-lg">
            <div class="flex items-center gap-2">
                <span class="iconify" data-icon="mdi:alert-circle" data-width="20"></span>
                <span class="font-medium"><?php echo $error_message; ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Total Ketertarikan -->
        <div class="bg-white rounded-2xl p-6 text-center shadow-sm border border-gray-200">
            <div class="text-[#2A8FA9] mb-3 flex justify-center">
                <span class="iconify" data-icon="mdi:heart-multiple" data-width="48"></span>
            </div>
            <h3 class="text-[#2A8FA9] font-bold text-2xl mb-2"><?php echo $stats['all']; ?></h3>
            <p class="text-gray-600 font-medium">Total Ketertarikan</p>
        </div>

        <!-- Menunggu -->
        <div class="bg-white rounded-2xl p-6 text-center shadow-sm border border-gray-200">
            <div class="text-yellow-500 mb-3 flex justify-center">
                <span class="iconify" data-icon="mdi:clock" data-width="48"></span>
            </div>
            <h3 class="text-yellow-600 font-bold text-2xl mb-2"><?php echo $stats['pending']; ?></h3>
            <p class="text-gray-600 font-medium">Menunggu Dihubungi</p>
        </div>

        <!-- Sudah Dihubungi -->
        <div class="bg-white rounded-2xl p-6 text-center shadow-sm border border-gray-200">
            <div class="text-green-500 mb-3 flex justify-center">
                <span class="iconify" data-icon="mdi:phone-check" data-width="48"></span>
            </div>
            <h3 class="text-green-600 font-bold text-2xl mb-2"><?php echo $stats['contacted']; ?></h3>
            <p class="text-gray-600 font-medium">Sudah Dihubungi</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-center justify-between">
            <!-- Status Filter -->
            <div class="flex flex-wrap gap-2">
                <a href="?status=all&search=<?php echo urlencode($search); ?>" 
                   class="px-4 py-2 rounded-lg font-medium <?php echo $status_filter === 'all' ? 'bg-[#2A8FA9] text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                    Semua (<?php echo $stats['all']; ?>)
                </a>
                <a href="?status=pending&search=<?php echo urlencode($search); ?>" 
                   class="px-4 py-2 rounded-lg font-medium <?php echo $status_filter === 'pending' ? 'bg-yellow-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                    Menunggu (<?php echo $stats['pending']; ?>)
                </a>
                <a href="?status=contacted&search=<?php echo urlencode($search); ?>" 
                   class="px-4 py-2 rounded-lg font-medium <?php echo $status_filter === 'contacted' ? 'bg-green-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                    Dihubungi (<?php echo $stats['contacted']; ?>)
                </a>
            </div>

            <!-- Search -->
            <form method="GET" class="w-full lg:w-auto">
                <div class="relative">
                    <input type="text" 
                           name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Cari mitra atau mahasiswa..."
                           class="w-full lg:w-80 pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2A8FA9] focus:border-transparent">
                    <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                        <span class="iconify" data-icon="mdi:magnify" data-width="20"></span>
                    </span>
                    <?php if ($status_filter !== 'all'): ?>
                        <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Interests List -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <?php if (!empty($interests)): ?>
            <div class="divide-y divide-gray-200">
                <?php foreach ($interests as $interest): ?>
                    <div class="p-6 hover:bg-gray-50 transition-colors">
                        <div class="flex flex-col lg:flex-row lg:items-start gap-6">
                            <!-- Main Content -->
                            <div class="flex-1">
                                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-4">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <h3 class="text-lg font-bold text-gray-900">
                                                <?php echo htmlspecialchars($interest['mitra_company']); ?>
                                            </h3>
                                            <span class="text-sm px-3 py-1 rounded-full <?php echo getStatusBadgeClass($interest['status']); ?>">
                                                <?php echo getStatusText($interest['status']); ?>
                                            </span>
                                        </div>
                                        
                                        <p class="text-gray-600 mb-3">
                                            Tertarik dengan 
                                            <span class="font-semibold text-[#2A8FA9]"><?php echo htmlspecialchars($interest['student_name']); ?></span>
                                            • <?php echo htmlspecialchars($interest['student_major']); ?>
                                        </p>

                                        <?php if (!empty($interest['message'])): ?>
                                            <div class="bg-gray-50 rounded-lg p-4 mb-3 border border-gray-200">
                                                <h4 class="font-semibold text-gray-800 mb-2 flex items-center gap-2">
                                                    <span class="iconify" data-icon="mdi:message-text" data-width="16"></span>
                                                    Pesan dari Mitra:
                                                </h4>
                                                <p class="text-gray-700 italic">"<?php echo htmlspecialchars($interest['message']); ?>"</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="flex sm:flex-col gap-2 sm:gap-3">
                                        <?php if ($interest['status'] === 'pending'): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="interest_id" value="<?php echo $interest['id']; ?>">
                                                <input type="hidden" name="status" value="contacted">
                                                <button type="submit" name="update_status" 
                                                        class="w-full bg-green-500 text-white px-4 py-2 rounded-lg font-semibold hover:bg-green-600 transition-colors flex items-center justify-center gap-2">
                                                    <span class="iconify" data-icon="mdi:phone" data-width="16"></span>
                                                    Tandai Sudah Dihubungi
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button class="w-full bg-gray-300 text-gray-600 px-4 py-2 rounded-lg font-semibold cursor-not-allowed flex items-center justify-center gap-2" disabled>
                                                <span class="iconify" data-icon="mdi:phone-check" data-width="16"></span>
                                                Sudah Dihubungi
                                            </button>
                                        <?php endif; ?>
                                        
                                        <a href="student-profile.php?id=<?php echo $interest['student_id']; ?>" 
                                           target="_blank"
                                           class="w-full bg-[#2A8FA9] text-white px-4 py-2 rounded-lg font-semibold hover:bg-[#409BB2] transition-colors flex items-center justify-center gap-2">
                                            <span class="iconify" data-icon="mdi:eye" data-width="16"></span>
                                            Lihat Profil
                                        </a>
                                    </div>
                                </div>

                                <!-- Contact Info -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                    <!-- Mitra Info -->
                                    <div class="bg-blue-50 rounded-lg p-3 border border-blue-200">
                                        <h4 class="font-semibold text-blue-900 mb-2 flex items-center gap-2">
                                            <span class="iconify" data-icon="mdi:office-building" data-width="16"></span>
                                            Info Mitra
                                        </h4>
                                        <div class="space-y-1">
                                            <p class="text-blue-800">
                                                <span class="font-medium">Email:</span> 
                                                <?php echo htmlspecialchars($interest['mitra_email']); ?>
                                            </p>
                                            <?php if (!empty($interest['mitra_phone'])): ?>
                                                <p class="text-blue-800">
                                                    <span class="font-medium">Telepon:</span> 
                                                    <?php echo htmlspecialchars($interest['mitra_phone']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Student Info -->
                                    <div class="bg-green-50 rounded-lg p-3 border border-green-200">
                                        <h4 class="font-semibold text-green-900 mb-2 flex items-center gap-2">
                                            <span class="iconify" data-icon="mdi:account-school" data-width="16"></span>
                                            Info Mahasiswa
                                        </h4>
                                        <div class="space-y-1">
                                            <p class="text-green-800">
                                                <span class="font-medium">Email:</span> 
                                                <?php echo htmlspecialchars($interest['student_email']); ?>
                                            </p>
                                            <p class="text-green-800">
                                                <span class="font-medium">Jurusan:</span> 
                                                <?php echo htmlspecialchars($interest['student_major']); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Timestamp -->
                        <div class="flex justify-between items-center mt-4 pt-4 border-t border-gray-200 text-xs text-gray-500">
                            <span class="flex items-center gap-1">
                                <span class="iconify" data-icon="mdi:calendar" data-width="12"></span>
                                Diajukan: <?php echo date('d M Y H:i', strtotime($interest['created_at'])); ?>
                            </span>
                            <?php if ($interest['updated_at'] !== $interest['created_at']): ?>
                                <span class="flex items-center gap-1">
                                    <span class="iconify" data-icon="mdi:update" data-width="12"></span>
                                    Diupdate: <?php echo date('d M Y H:i', strtotime($interest['updated_at'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="text-center py-12">
                <span class="iconify text-gray-400 mx-auto mb-4" data-icon="mdi:heart-off" data-width="64"></span>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Tidak Ada Ketertarikan</h3>
                <p class="text-gray-600 mb-6">
                    <?php if ($status_filter !== 'all' || !empty($search)): ?>
                        Tidak ditemukan ketertarikan dengan filter yang dipilih.
                    <?php else: ?>
                        Belum ada mitra yang menyatakan ketertarikan pada mahasiswa.
                    <?php endif; ?>
                </p>
                <?php if ($status_filter !== 'all' || !empty($search)): ?>
                    <a href="manage-interest.php" 
                       class="bg-[#2A8FA9] text-white px-6 py-2 rounded-lg font-semibold hover:bg-[#409BB2] transition-colors inline-flex items-center gap-2">
                        <span class="iconify" data-icon="mdi:filter-remove" data-width="16"></span>
                        Reset Filter
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>