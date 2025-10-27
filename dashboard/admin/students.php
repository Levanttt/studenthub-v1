<?php
include '../../includes/config.php';
include '../../includes/functions.php';

if (!isLoggedIn() || getUserRole() != 'admin') {
    header("Location: ../../login.php");
    exit();
}

// Variabel untuk search dan filter
$search = $_GET['search'] ?? '';
$major = $_GET['major'] ?? '';
$semester = $_GET['semester'] ?? '';
$eligibility_filter = $_GET['eligibility_status'] ?? '';

// Pagination setup
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$students_per_page = 10;
$offset = ($current_page - 1) * $students_per_page;

// Inisialisasi variabel
$total_students = 0;
$total_pages = 0;
$students = [];
$daftar_major = [];

// Ambil daftar major unik untuk dropdown filter
$major_query = "SELECT DISTINCT major FROM users WHERE major IS NOT NULL AND major != '' ORDER BY major";
$major_result = $conn->query($major_query);
if ($major_result) {
    $daftar_major = $major_result->fetch_all(MYSQLI_ASSOC);
}

// Query dasar untuk mengambil data mahasiswa
$query = "SELECT id, nim, name, email, major, semester, eligibility_status, created_at FROM users WHERE role = 'student'";
$count_query = "SELECT COUNT(*) as total FROM users WHERE role = 'student'";

$major_query = "SELECT DISTINCT major FROM users WHERE major IS NOT NULL AND major != '' ORDER BY major";
$major_result = $conn->query($major_query);
if ($major_result) {
    $daftar_major = $major_result->fetch_all(MYSQLI_ASSOC);
}

$params = [];
$types = "";
$where_conditions = [];

// Filter search (nama, nim, email)
if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR nim LIKE ? OR email LIKE ?)";
    $search_term = "%" . $search . "%";
    array_push($params, $search_term, $search_term, $search_term);
    $types .= "sss";
}

// Filter major
if (!empty($major)) {
    $where_conditions[] = "major = ?";
    $params[] = $major;
    $types .= "s";
}

// Filter semester
if (!empty($semester)) {
    $where_conditions[] = "semester = ?";
    $params[] = $semester;
    $types .= "s";
}

// Filter eligibility status
if (!empty($eligibility_filter)) {
    $where_conditions[] = "eligibility_status = ?";
    $params[] = $eligibility_filter;
    $types .= "s";
}

// Gabungkan kondisi WHERE
if (!empty($where_conditions)) {
    $where_clause = " AND " . implode(" AND ", $where_conditions);
    $query .= $where_clause;
    $count_query .= $where_clause;
}

// Hitung total students
$count_stmt = $conn->prepare($count_query);
if ($count_stmt) {
    if (!empty($params)) {
        $count_stmt->bind_param($types, ...$params);
    }
    if ($count_stmt->execute()) {
        $count_result = $count_stmt->get_result();
        $total_row = $count_result->fetch_assoc();
        $total_students = $total_row['total'];
        $total_pages = ceil($total_students / $students_per_page);
    }
    $count_stmt->close();
}

// Query untuk data students dengan pagination
$query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$pagination_params = $params;
$pagination_params[] = $students_per_page;
$pagination_params[] = $offset;
$pagination_types = $types . "ii";

// Ambil data students
$stmt = $conn->prepare($query);
if ($stmt) {
    if (!empty($pagination_params)) {
        $stmt->bind_param($pagination_types, ...$pagination_params);
    }
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $students = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
}

// AJAX update eligibility status 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_eligibility') {
    header('Content-Type: application/json');
    
    try {
        if (!isset($_POST['user_id']) || !isset($_POST['eligibility_status'])) {
            throw new Exception('Data tidak lengkap');
        }
        
        $user_id = intval($_POST['user_id']);
        $eligibility_status = $_POST['eligibility_status'];
        
        $allowed_statuses = ['pending', 'eligible', 'not_eligible'];
        if (!in_array($eligibility_status, $allowed_statuses)) {
            throw new Exception('Status tidak valid');
        }
        
        $update_stmt = $conn->prepare("UPDATE users SET eligibility_status = ? WHERE id = ?");
        if (!$update_stmt) {
            throw new Exception('Prepare statement failed: ' . $conn->error);
        }
        
        $update_stmt->bind_param('si', $eligibility_status, $user_id);
        
        if ($update_stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Status berhasil diupdate',
                'user_id' => $user_id,
                'new_status' => $eligibility_status
            ]);
        } else {
            throw new Exception('Execute failed: ' . $update_stmt->error);
        }
        
        $update_stmt->close();
        
    } catch (Exception $e) {
        error_log("Update eligibility error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Gagal mengupdate status: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Stats untuk dashboard
$stats = [];
$stats_query = "
    SELECT 
        COUNT(*) as total_students,
        SUM(eligibility_status = 'eligible') as eligible_students,
        SUM(eligibility_status = 'not_eligible') as not_eligible_students,
        SUM(eligibility_status = 'pending' OR eligibility_status IS NULL OR eligibility_status = '') as pending_students
    FROM users 
    WHERE role = 'student'
";
$stats_result = $conn->query($stats_query);
if ($stats_result) {
    $stats = $stats_result->fetch_assoc();
}
?>

<?php include '../../includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Hero Section -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
        <div>
            <h1 class="text-3xl font-bold text-[#2A8FA9] flex items-center gap-3">
                <span class="iconify" data-icon="mdi:shield-account" data-width="32"></span>
                Kelola Status Mahasiswa
            </h1>
            <p class="text-gray-600 mt-2">Atur status eligibility dan pantau data mahasiswa</p>
        </div>
        <a href="index.php" class="bg-[#E0F7FF] text-[#2A8FA9] px-6 py-3 rounded-xl font-semibold hover:bg-[#51A3B9] hover:text-white transition-colors duration-300 border border-[#51A3B9] border-opacity-30 flex items-center gap-2">
            <span class="iconify" data-icon="mdi:arrow-left" data-width="18"></span>
            Kembali ke Dashboard
        </a>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8 mt-8">
        <div class="bg-white rounded-2xl p-6 text-center shadow-sm border border-gray-100 hover:shadow-md transition-all duration-300">
            <div class="text-[#51A3B9] mb-3 flex justify-center">
                <span class="iconify" data-icon="mdi:account-multiple" data-width="48"></span>
            </div>
            <h3 class="text-[#2A8FA9] font-bold text-2xl mb-2"><?php echo $stats['total_students'] ?? 0; ?></h3>
            <p class="text-gray-600 font-medium">Total Mahasiswa</p>
        </div>
        
        <div class="bg-white rounded-2xl p-6 text-center shadow-sm border border-gray-100 hover:shadow-md transition-all duration-300">
            <div class="text-green-500 mb-3 flex justify-center">
                <span class="iconify" data-icon="mdi:check-circle" data-width="48"></span>
            </div>
            <h3 class="text-green-600 font-bold text-2xl mb-2"><?php echo $stats['eligible_students'] ?? 0; ?></h3>
            <p class="text-gray-600 font-medium">Eligible</p>
        </div>
        
        <div class="bg-white rounded-2xl p-6 text-center shadow-sm border border-gray-100 hover:shadow-md transition-all duration-300">
            <div class="text-red-500 mb-3 flex justify-center">
                <span class="iconify" data-icon="mdi:close-circle" data-width="48"></span>
            </div>
            <h3 class="text-red-600 font-bold text-2xl mb-2"><?php echo $stats['not_eligible_students'] ?? 0; ?></h3>
            <p class="text-gray-600 font-medium">Not Eligible</p>
        </div>
        
        <div class="bg-white rounded-2xl p-6 text-center shadow-sm border border-gray-100 hover:shadow-md transition-all duration-300">
            <div class="text-yellow-500 mb-3 flex justify-center">
                <span class="iconify" data-icon="mdi:clock" data-width="48"></span>
            </div>
            <h3 class="text-yellow-600 font-bold text-2xl mb-2"><?php echo $stats['pending_students'] ?? 0; ?></h3>
            <p class="text-gray-600 font-medium">Pending</p>
        </div>
    </div>

    <!-- Search & Filter Section -->
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 mb-8">
        <h2 class="text-2xl font-bold text-[#2A8FA9] mb-6 flex items-center gap-2">
            <span class="iconify" data-icon="mdi:filter" data-width="28"></span>
            Filter Mahasiswa
        </h2>
        
        <form method="GET" action="" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Search Input -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Cari Mahasiswa</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <span class="iconify" data-icon="mdi:magnify" data-width="20"></span>
                        </span>
                        <input type="text" 
                            name="search" 
                            value="<?php echo htmlspecialchars($search); ?>" 
                            class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] transition-colors text-sm" 
                            placeholder="Nama, NIM, atau Email...">
                    </div>
                </div>

                <!-- Major Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Program Studi</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <span class="iconify" data-icon="mdi:book-education" data-width="18"></span>
                        </span>
                        <select name="major" class="w-full pl-10 pr-10 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] text-sm appearance-none bg-white">
                            <option value="">Semua Program Studi</option>
                            <?php foreach ($daftar_major as $m): ?>
                                <option value="<?php echo htmlspecialchars($m['major']); ?>" 
                                        <?php echo $major == $m['major'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($m['major']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 pointer-events-none">
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="16"></span>
                        </span>
                    </div>
                </div>

                <!-- Semester Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Semester</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <span class="iconify" data-icon="mdi:numeric" data-width="18"></span>
                        </span>
                        <select name="semester" class="w-full pl-10 pr-10 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] text-sm appearance-none bg-white">
                            <option value="">Semua Semester</option>
                            <?php for ($i = 1; $i <= 8; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $semester == $i ? 'selected' : ''; ?>>
                                    Semester <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 pointer-events-none">
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="16"></span>
                        </span>
                    </div>
                </div>

                <!-- Eligibility Status Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status Eligibility</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <span class="iconify" data-icon="mdi:shield-check" data-width="18"></span>
                        </span>
                        <select name="eligibility_status" class="w-full pl-10 pr-10 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] text-sm appearance-none bg-white">
                            <option value="">Semua Status</option>
                            <option value="pending" <?php echo $eligibility_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="eligible" <?php echo $eligibility_filter == 'eligible' ? 'selected' : ''; ?>>Eligible</option>
                            <option value="not_eligible" <?php echo $eligibility_filter == 'not_eligible' ? 'selected' : ''; ?>>Not Eligible</option>
                        </select>
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 pointer-events-none">
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="16"></span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-end space-x-4 pt-4">
                <button type="submit" class="bg-[#51A3B9] text-white px-8 py-3 rounded-xl font-bold hover:bg-[#409BB2] transition-colors duration-300 shadow-sm flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:magnify" data-width="20"></span>
                    Terapkan Filter
                </button>
                <a href="students.php" class="bg-gray-200 text-gray-700 px-8 py-3 rounded-xl font-bold hover:bg-gray-300 transition-colors duration-300 flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:refresh" data-width="20"></span>
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Results Section -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
        <div class="p-6 border-b border-gray-200">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <h2 class="text-2xl font-bold text-[#2A8FA9] flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:account-multiple" data-width="28"></span>
                    Data Mahasiswa
                </h2>
                <p class="text-gray-600 text-sm">
                    Menampilkan <span class="font-bold text-[#2A8FA9]"><?php echo count($students); ?></span> dari 
                    <span class="font-bold text-[#2A8FA9]"><?php echo $total_students; ?></span> mahasiswa
                </p>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-20">No</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">NIM</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nama</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Program Studi</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-28">Semester</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-32">Profil</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-44">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (count($students) > 0): ?>
                        <?php foreach ($students as $index => $student): ?>
                            <tr class="hover:bg-gray-50 transition-colors duration-200" data-student-id="<?php echo $student['id']; ?>">
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 text-left pl-5">
                                    <?php echo $offset + $index + 1; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($student['nim'] ?? '-'); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($student['name']); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                    <?php echo htmlspecialchars($student['email']); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                    <?php echo htmlspecialchars($student['major'] ?? '-'); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 text-center">
                                    <?php echo htmlspecialchars($student['semester'] ?? '-'); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <a href="student-profile.php?id=<?php echo $student['id']; ?>" 
                                            class="bg-[#E0F7FF] text-[#2A8FA9] px-4 py-2 rounded-lg text-xs font-semibold hover:bg-[#51A3B9] hover:text-white transition-colors duration-300 border border-[#51A3B9] border-opacity-30 flex items-center gap-2 w-full justify-center">
                                        <span class="iconify" data-icon="mdi:eye" data-width="14"></span>
                                        Lihat Profil
                                    </a>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <?php
                                    $current_status = $student['eligibility_status'] ?? 'pending';
                                    $statusConfig = getEligibilityBadge($current_status);
                                    ?>
                                    
                                    <div class="relative" id="status-container-<?php echo $student['id']; ?>">
                                        <!-- Icon indicator -->
                                        <span class="absolute left-2 top-1/2 -translate-y-1/2 pointer-events-none z-10 status-icon">
                                            <span class="iconify <?php echo $statusConfig['iconColor']; ?>" 
                                                data-icon="<?php echo $statusConfig['icon']; ?>" 
                                                data-width="14"></span>
                                        </span>
                                        
                                        <!-- Native Select dengan styling -->
                                        <select onchange="updateEligibilityStatus(<?php echo $student['id']; ?>, this.value, this)" 
                                                class="status-select bg-[#E0F7FF] text-[#2A8FA9] border border-[#51A3B9] border-opacity-30 pl-8 pr-8 py-2 rounded-lg text-xs font-semibold w-full appearance-none cursor-pointer transition-colors duration-300 flex items-center gap-2"
                                                data-current-status="<?php echo $current_status; ?>">
                                            <option value="pending" <?php echo $current_status == 'pending' ? 'selected' : ''; ?>>PENDING</option>
                                            <option value="eligible" <?php echo $current_status == 'eligible' ? 'selected' : ''; ?>>ELIGIBLE</option>
                                            <option value="not_eligible" <?php echo $current_status == 'not_eligible' ? 'selected' : ''; ?>>NOT ELIGIBLE</option>
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
                            <td colspan="8" class="px-6 py-12 text-center">
                                <div class="text-gray-500 flex flex-col items-center">
                                    <span class="iconify text-gray-400 mb-3" data-icon="mdi:account-search" data-width="48"></span>
                                    <p class="text-lg font-medium text-gray-900 mb-2">Tidak ada data mahasiswa</p>
                                    <p class="text-gray-600">Coba ubah filter pencarian Anda.</p>
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
                        <a href="?page=1&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" 
                           class="flex items-center justify-center w-8 h-8 bg-white border border-gray-300 rounded font-medium text-gray-700 hover:bg-gray-50 transition-colors duration-300 text-sm">
                            <span class="iconify" data-icon="mdi:chevron-double-left" data-width="14"></span>
                        </a>
                    <?php endif; ?>

                    <!-- Previous Page -->
                    <?php if ($current_page > 1): ?>
                        <a href="?page=<?php echo $current_page - 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" 
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
                        <a href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" 
                           class="flex items-center justify-center w-8 h-8 rounded font-medium transition-colors duration-300 text-sm <?php echo $i == $current_page ? 'bg-[#51A3B9] text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <!-- Next Page -->
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?php echo $current_page + 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" 
                           class="flex items-center justify-center w-8 h-8 bg-white border border-gray-300 rounded font-medium text-gray-700 hover:bg-gray-50 transition-colors duration-300 text-sm">
                            <span class="iconify" data-icon="mdi:chevron-right" data-width="14"></span>
                        </a>
                    <?php endif; ?>

                    <!-- Last Page -->
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?php echo $total_pages; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" 
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

table {
    table-layout: auto;
}

th:nth-child(1), td:nth-child(1) { width: 60px; }
th:nth-child(6), td:nth-child(6) { width: 100px; padding-right: 6rem; }
th:nth-child(7), td:nth-child(7) { width: 176px; }

.status-select option {
    background: white;
    color: #1f2937;
    padding: 8px;
}

.student-profile-modal {
    background: white;
    border-radius: 1rem;
    max-width: 900px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    background: linear-gradient(135deg, #E0F7FF 0%, #B8E6F5 100%);
    padding: 1.5rem;
    border-bottom: 1px solid #51A3B9;
}

.modal-body {
    padding: 1.5rem;
}

.skill-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 500;
    margin: 0.125rem;
}

.skill-technical {
    background-color: #DBEAFE;
    color: #1E40AF;
    border: 1px solid #BFDBFE;
}

.skill-soft {
    background-color: #D1FAE5;
    color: #065F46;
    border: 1px solid #A7F3D0;
}

.skill-tool {
    background-color: #EDE9FE;
    color: #5B21B6;
    border: 1px solid #DDD6FE;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin: 1rem 0;
}

.stat-card {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 0.75rem;
    padding: 1rem;
    text-align: center;
}

.stat-number {
    font-size: 1.5rem;
    font-weight: bold;
    color: #2A8FA9;
    display: block;
}

.stat-label {
    font-size: 0.875rem;
    color: #64748B;
    margin-top: 0.25rem;
}
</style>

<script>
const STATUS_CONFIG = {
    'pending': {
        icon: 'mdi:clock',
        iconColor: 'text-[#2A8FA9]'
    },
    'eligible': {
        icon: 'mdi:check-circle',
        iconColor: 'text-[#2A8FA9]'
    },
    'not_eligible': {
        icon: 'mdi:close-circle',
        iconColor: 'text-[#2A8FA9]'
    }
};

function updateEligibilityStatus(userId, newStatus, selectElement) {
    console.log('🔄 Updating user:', userId, 'to:', newStatus);
    
    // Simpan element references
    const parentDiv = selectElement.parentElement;
    const iconElement = parentDiv.querySelector('.status-icon .iconify');
    
    // Disable sementara
    selectElement.disabled = true;
    selectElement.style.opacity = '0.6';
    
    // Prepare form data
    const formData = new URLSearchParams();
    formData.append('action', 'update_eligibility');
    formData.append('user_id', userId);
    formData.append('eligibility_status', newStatus);
    
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
            console.log('✅ Status updated successfully');
            
            // 1. Update selected option
            Array.from(selectElement.options).forEach(option => {
                option.selected = (option.value === newStatus);
            });
            
            // 2. Update data attribute
            selectElement.setAttribute('data-current-status', newStatus);
            
            // 3. Update icon secara realtime
            if (iconElement) {
                const config = STATUS_CONFIG[newStatus];
                if (config) {
                    // Update icon dan color
                    iconElement.setAttribute('data-icon', config.icon);
                    iconElement.className = `iconify ${config.iconColor}`;
                    
                    // Force iconify to reload the icon
                    if (window.Iconify && window.Iconify.replace) {
                        window.Iconify.replace(iconElement);
                    } else if (window.iconify && window.iconify.replace) {
                        window.iconify.replace(iconElement);
                    }
                    
                    console.log('🎨 Icon updated to:', config.icon);
                }
            }
            
            // Success animation
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
        alert('Error saat update: ' + error.message);
    })
    .finally(() => {
        // Enable kembali
        selectElement.disabled = false;
        selectElement.style.opacity = '1';
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Admin dashboard loaded');
    console.log('📋 Status config:', STATUS_CONFIG);
});

// Function to open student profile modal
function openStudentProfileModal(studentId) {
    // Show loading
    document.getElementById('studentModalContent').innerHTML = `
        <div class="flex items-center justify-center p-12">
            <div class="text-center">
                <span class="iconify text-[#2A8FA9] animate-spin" data-icon="mdi:loading" data-width="32"></span>
                <p class="text-gray-600 mt-2">Memuat profil mahasiswa...</p>
            </div>
        </div>
    `;
    
    document.getElementById('studentProfileModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Load student profile via AJAX
    fetch(`student-profile-modal.php?id=${studentId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(html => {
            document.getElementById('studentModalContent').innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading student profile:', error);
            document.getElementById('studentModalContent').innerHTML = `
                <div class="flex items-center justify-center p-12">
                    <div class="text-center">
                        <span class="iconify text-red-400" data-icon="mdi:alert-circle" data-width="48"></span>
                        <p class="text-gray-600 mt-2">Gagal memuat profil mahasiswa.</p>
                        <button onclick="closeStudentProfileModal()" 
                                class="mt-4 bg-[#2A8FA9] text-white px-4 py-2 rounded-lg hover:bg-[#51A3B9] transition-colors">
                            Tutup
                        </button>
                    </div>
                </div>
            `;
        });
}

// Function to close student profile modal
function closeStudentProfileModal() {
    document.getElementById('studentProfileModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeStudentProfileModal();
    }
});

// Close modal when clicking outside
document.getElementById('studentProfileModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeStudentProfileModal();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>