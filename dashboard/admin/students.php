<?php
include '../../includes/config.php';
include '../../includes/functions.php';

if (!isLoggedIn() || getUserRole() != 'admin') {
    header("Location: ../../login.php");
    exit();
}

$search = $_GET['search'] ?? '';
$major = $_GET['major'] ?? '';
$semester = $_GET['semester'] ?? '';
$eligibility_filter = $_GET['eligibility_status'] ?? '';
$availability_filter = $_GET['availability_status'] ?? '';

$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$students_per_page = 10;
$offset = ($current_page - 1) * $students_per_page;

$total_students = 0;
$total_pages = 0;
$students = [];
$daftar_major = [];

$major_query = "SELECT DISTINCT major FROM users WHERE major IS NOT NULL AND major != '' ORDER BY major";
$major_result = $conn->query($major_query);
if ($major_result) {
    $daftar_major = $major_result->fetch_all(MYSQLI_ASSOC);
}

$query = "SELECT id, nim, name, email, major, semester, eligibility_status, availability_status, created_at FROM users WHERE role = 'student'";
$count_query = "SELECT COUNT(*) as total FROM users WHERE role = 'student'";

$params = [];
$types = "";
$where_conditions = [];

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR nim LIKE ? OR email LIKE ?)";
    $search_term = "%" . $search . "%";
    array_push($params, $search_term, $search_term, $search_term);
    $types .= "sss";
}

if (!empty($major)) {
    $where_conditions[] = "major = ?";
    $params[] = $major;
    $types .= "s";
}

if (!empty($semester)) {
    $where_conditions[] = "semester = ?";
    $params[] = $semester;
    $types .= "s";
}

if (!empty($eligibility_filter)) {
    $where_conditions[] = "eligibility_status = ?";
    $params[] = $eligibility_filter;
    $types .= "s";
}

if (!empty($availability_filter)) {
    $where_conditions[] = "availability_status = ?";
    $params[] = $availability_filter;
    $types .= "s";
}

if (!empty($where_conditions)) {
    $where_clause = " AND " . implode(" AND ", $where_conditions);
    $query .= $where_clause;
    $count_query .= $where_clause;
}

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

$query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$pagination_params = $params;
$pagination_params[] = $students_per_page;
$pagination_params[] = $offset;
$pagination_types = $types . "ii";

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        if (!isset($_POST['action'])) {
            throw new Exception('Aksi tidak valid');
        }
        
        if ($_POST['action'] === 'update_eligibility') {
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
        }
        elseif ($_POST['action'] === 'update_availability') {
            if (!isset($_POST['user_id']) || !isset($_POST['availability_status'])) {
                throw new Exception('Data tidak lengkap');
            }
            
            $user_id = intval($_POST['user_id']);
            $availability_status = $_POST['availability_status'];
            
            $allowed_statuses = ['available', 'interview', 'accepted', 'inactive'];
            if (!in_array($availability_status, $allowed_statuses)) {
                throw new Exception('Status tidak valid');
            }
            
            $update_stmt = $conn->prepare("UPDATE users SET availability_status = ? WHERE id = ?");
            if (!$update_stmt) {
                throw new Exception('Prepare statement failed: ' . $conn->error);
            }
            
            $update_stmt->bind_param('si', $availability_status, $user_id);
            
            if ($update_stmt->execute()) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Status ketersediaan berhasil diupdate',
                    'user_id' => $user_id,
                    'new_status' => $availability_status
                ]);
            } else {
                throw new Exception('Execute failed: ' . $update_stmt->error);
            }
            
            $update_stmt->close();
        }
        
    } catch (Exception $e) {
        error_log("Update status error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Gagal mengupdate status: ' . $e->getMessage()
        ]);
    }
    exit;
}

$stats = [];
$stats_query = "
    SELECT 
        COUNT(*) as total_students,
        SUM(eligibility_status = 'eligible') as eligible_students,
        SUM(eligibility_status = 'not_eligible') as not_eligible_students,
        SUM(eligibility_status = 'pending' OR eligibility_status IS NULL OR eligibility_status = '') as pending_students,
        SUM(availability_status = 'available') as available_students,
        SUM(availability_status = 'interview') as interview_students,
        SUM(availability_status = 'accepted') as accepted_students,
        SUM(availability_status = 'inactive' OR availability_status IS NULL OR availability_status = '') as inactive_students
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
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
        <div>
            <h1 class="text-3xl font-bold text-[#2A8FA9] flex items-center gap-3">
                <span class="iconify" data-icon="mdi:shield-account" data-width="32"></span>
                Kelola Status Mahasiswa
            </h1>
            <p class="text-gray-600 mt-2">Atur status eligibility dan ketersediaan mahasiswa</p>
        </div>
        <a href="index.php" class="bg-[#E0F7FF] text-[#2A8FA9] px-6 py-3 rounded-xl font-semibold hover:bg-[#51A3B9] hover:text-white transition-colors duration-300 border border-[#51A3B9] border-opacity-30 flex items-center gap-2">
            <span class="iconify" data-icon="mdi:arrow-left" data-width="18"></span>
            Kembali ke Dashboard
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-6 gap-6 mb-8 mt-8">
        <div class="bg-white rounded-2xl p-6 text-center shadow-sm border border-gray-100 hover:shadow-md transition-all duration-300">
            <div class="text-[#51A3B9] mb-3 flex justify-center">
                <span class="iconify" data-icon="mdi:account-multiple" data-width="48"></span>
            </div>
            <h3 class="text-[#2A8FA9] font-bold text-2xl mb-2"><?php echo $stats['total_students'] ?? 0; ?></h3>
            <p class="text-gray-600 font-medium">Total</p>
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
            <div class="text-green-500 mb-3 flex justify-center">
                <span class="iconify" data-icon="mdi:account-check" data-width="48"></span>
            </div>
            <h3 class="text-green-600 font-bold text-2xl mb-2"><?php echo $stats['available_students'] ?? 0; ?></h3>
            <p class="text-gray-600 font-medium">Tersedia</p>
        </div>
        
        <div class="bg-white rounded-2xl p-6 text-center shadow-sm border border-gray-100 hover:shadow-md transition-all duration-300">
            <div class="text-yellow-500 mb-3 flex justify-center">
                <span class="iconify" data-icon="mdi:clock" data-width="48"></span>
            </div>
            <h3 class="text-yellow-600 font-bold text-2xl mb-2"><?php echo $stats['pending_students'] ?? 0; ?></h3>
            <p class="text-gray-600 font-medium">Pending</p>
        </div>
        
        <div class="bg-white rounded-2xl p-6 text-center shadow-sm border border-gray-100 hover:shadow-md transition-all duration-300">
            <div class="text-blue-500 mb-3 flex justify-center">
                <span class="iconify" data-icon="mdi:account-clock" data-width="48"></span>
            </div>
            <h3 class="text-blue-600 font-bold text-2xl mb-2"><?php echo $stats['interview_students'] ?? 0; ?></h3>
            <p class="text-gray-600 font-medium">Interview</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 mb-8">
        <h2 class="text-2xl font-bold text-[#2A8FA9] mb-6 flex items-center gap-2">
            <span class="iconify" data-icon="mdi:filter" data-width="28"></span>
            Filter Mahasiswa
        </h2>
        
        <form method="GET" action="" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
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

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status Ketersediaan</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <span class="iconify" data-icon="mdi:account-search" data-width="18"></span>
                        </span>
                        <select name="availability_status" class="w-full pl-10 pr-10 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] text-sm appearance-none bg-white">
                            <option value="">Semua Status</option>
                            <option value="available" <?php echo $availability_filter == 'available' ? 'selected' : ''; ?>>Available</option>
                            <option value="interview" <?php echo $availability_filter == 'interview' ? 'selected' : ''; ?>>Interview</option>
                            <option value="accepted" <?php echo $availability_filter == 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                            <option value="inactive" <?php echo $availability_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 pointer-events-none">
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="16"></span>
                        </span>
                    </div>
                </div>
            </div>

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

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">No</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">NIM</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nama</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Program Studi</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Semester</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Lihat Profil</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Eligibility</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Availability</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (count($students) > 0): ?>
                        <?php foreach ($students as $index => $student): ?>
                            <tr class="hover:bg-gray-50 transition-colors duration-200" data-student-id="<?php echo $student['id']; ?>">
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $offset + $index + 1; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($student['nim'] ?? '-'); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($student['name']); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                    <?php echo htmlspecialchars($student['major'] ?? '-'); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 text-center">
                                    <?php echo htmlspecialchars($student['semester'] ?? '-'); ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <a href="student-profile.php?id=<?php echo $student['id']; ?>" 
                                            class="inline-flex items-center justify-center bg-[#E0F7FF] text-[#2A8FA9] p-2 rounded-lg text-xs font-semibold hover:bg-[#51A3B9] hover:text-white transition-colors duration-300 border border-[#51A3B9] border-opacity-30">
                                        <span class="iconify" data-icon="mdi:eye" data-width="16"></span>
                                    </a>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <?php
                                    $current_status = $student['eligibility_status'] ?? 'pending';
                                    $statusConfig = getEligibilityBadge($current_status);
                                    ?>
                                    
                                    <div class="relative" id="status-container-<?php echo $student['id']; ?>">
                                        <span class="absolute left-2 top-1/2 -translate-y-1/2 pointer-events-none z-10 status-icon">
                                            <span class="iconify <?php echo $statusConfig['iconColor']; ?>" 
                                                data-icon="<?php echo $statusConfig['icon']; ?>" 
                                                data-width="14"></span>
                                        </span>
                                        
                                        <select onchange="updateEligibilityStatus(<?php echo $student['id']; ?>, this.value, this)" 
                                                class="status-select bg-[#E0F7FF] text-[#2A8FA9] border border-[#51A3B9] border-opacity-30 pl-8 pr-8 py-2 rounded-lg text-xs font-semibold w-full appearance-none cursor-pointer transition-colors duration-300"
                                                data-current-status="<?php echo $current_status; ?>">
                                            <option value="pending" <?php echo $current_status == 'pending' ? 'selected' : ''; ?>>PENDING</option>
                                            <option value="eligible" <?php echo $current_status == 'eligible' ? 'selected' : ''; ?>>ELIGIBLE</option>
                                            <option value="not_eligible" <?php echo $current_status == 'not_eligible' ? 'selected' : ''; ?>>NOT ELIGIBLE</option>
                                        </select>
                                        
                                        <span class="absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none">
                                            <span class="iconify text-[#2A8FA9]" data-icon="mdi:chevron-down" data-width="14"></span>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <?php
                                    $current_availability = $student['availability_status'] ?? 'inactive';
                                    $availabilityConfig = getAvailabilityBadge($current_availability);
                                    ?>
                                    
                                    <div class="relative" id="availability-container-<?php echo $student['id']; ?>">
                                        <span class="absolute left-2 top-1/2 -translate-y-1/2 pointer-events-none z-10 availability-icon">
                                            <span class="iconify <?php echo $availabilityConfig['iconColor']; ?>" 
                                                data-icon="<?php echo $availabilityConfig['icon']; ?>" 
                                                data-width="14"></span>
                                        </span>
                                        
                                        <select onchange="updateAvailabilityStatus(<?php echo $student['id']; ?>, this.value, this)" 
                                                class="availability-select bg-[#E0F7FF] text-[#2A8FA9] border border-[#51A3B9] border-opacity-30 pl-8 pr-8 py-2 rounded-lg text-xs font-semibold w-full appearance-none cursor-pointer transition-colors duration-300"
                                                data-current-status="<?php echo $current_availability; ?>">
                                            <option value="available" <?php echo $current_availability == 'available' ? 'selected' : ''; ?>>AVAILABLE</option>
                                            <option value="interview" <?php echo $current_availability == 'interview' ? 'selected' : ''; ?>>INTERVIEW</option>
                                            <option value="accepted" <?php echo $current_availability == 'accepted' ? 'selected' : ''; ?>>ACCEPTED</option>
                                            <option value="inactive" <?php echo $current_availability == 'inactive' ? 'selected' : ''; ?>>INACTIVE</option>
                                        </select>
                                        
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

        <?php if ($total_pages > 1): ?>
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
            <div class="flex justify-between items-center">
                <p class="text-sm text-gray-700">
                    Halaman <span class="font-medium"><?php echo $current_page; ?></span> dari 
                    <span class="font-medium"><?php echo $total_pages; ?></span>
                </p>
                <div class="flex items-center space-x-1">
                    <?php if ($current_page > 1): ?>
                        <a href="?page=1&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" 
                            class="flex items-center justify-center w-8 h-8 bg-white border border-gray-300 rounded font-medium text-gray-700 hover:bg-gray-50 transition-colors duration-300 text-sm">
                            <span class="iconify" data-icon="mdi:chevron-double-left" data-width="14"></span>
                        </a>
                    <?php endif; ?>

                    <?php if ($current_page > 1): ?>
                        <a href="?page=<?php echo $current_page - 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" 
                            class="flex items-center justify-center w-8 h-8 bg-white border border-gray-300 rounded font-medium text-gray-700 hover:bg-gray-50 transition-colors duration-300 text-sm">
                            <span class="iconify" data-icon="mdi:chevron-left" data-width="14"></span>
                        </a>
                    <?php endif; ?>

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

                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?php echo $current_page + 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" 
                            class="flex items-center justify-center w-8 h-8 bg-white border border-gray-300 rounded font-medium text-gray-700 hover:bg-gray-50 transition-colors duration-300 text-sm">
                            <span class="iconify" data-icon="mdi:chevron-right" data-width="14"></span>
                        </a>
                    <?php endif; ?>

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
.status-select, .availability-select {
    background-color: #E0F7FF;
    color: #2A8FA9;
    border: 1px solid rgba(81, 163, 185, 0.3);
    transition: all 0.3s ease;
    min-width: 140px;
}

.status-select:disabled, .availability-select:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.ring-2 { 
    box-shadow: 0 0 0 2px rgba(81, 163, 185, 0.5); 
}

.status-select::-ms-expand, .availability-select::-ms-expand {
    display: none;
}

.status-select option, .availability-select option {
    background: white;
    color: #1f2937;
    padding: 8px;
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

const AVAILABILITY_CONFIG = {
    'available': {
        icon: 'mdi:check-circle',
        iconColor: 'text-green-500'
    },
    'interview': {
        icon: 'mdi:clock',
        iconColor: 'text-yellow-500'
    },
    'accepted': {
        icon: 'mdi:account-check',
        iconColor: 'text-red-500'
    },
    'inactive': {
        icon: 'mdi:account-off',
        iconColor: 'text-gray-500'
    }
};

function updateEligibilityStatus(userId, newStatus, selectElement) {
    const parentDiv = selectElement.parentElement;
    const iconElement = parentDiv.querySelector('.status-icon .iconify');
    
    selectElement.disabled = true;
    selectElement.style.opacity = '0.6';
    
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
            console.log('✅ Status eligibility updated successfully');
            
            Array.from(selectElement.options).forEach(option => {
                option.selected = (option.value === newStatus);
            });
            
            selectElement.setAttribute('data-current-status', newStatus);
            
            if (iconElement) {
                const config = STATUS_CONFIG[newStatus];
                if (config) {
                    iconElement.setAttribute('data-icon', config.icon);
                    iconElement.className = `iconify ${config.iconColor}`;
                    
                    if (window.Iconify && window.Iconify.replace) {
                        window.Iconify.replace(iconElement);
                    } else if (window.iconify && window.iconify.replace) {
                        window.iconify.replace(iconElement);
                    }
                }
            }
            
            selectElement.classList.add('ring-2', 'scale-105');
            setTimeout(() => {
                selectElement.classList.remove('ring-2', 'scale-105');
            }, 1000);
            
        } else {
            throw new Error(data.message || 'Unknown server error');
        }
    })
    .catch(error => {
        console.error('💥 Error:', error);
        alert('Error saat update eligibility: ' + error.message);
    })
    .finally(() => {
        selectElement.disabled = false;
        selectElement.style.opacity = '1';
    });
}

function updateAvailabilityStatus(userId, newStatus, selectElement) {
    const parentDiv = selectElement.parentElement;
    const iconElement = parentDiv.querySelector('.availability-icon .iconify');
    
    selectElement.disabled = true;
    selectElement.style.opacity = '0.6';
    
    const formData = new URLSearchParams();
    formData.append('action', 'update_availability');
    formData.append('user_id', userId);
    formData.append('availability_status', newStatus);
    
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
            console.log('✅ Status availability updated successfully');
            
            Array.from(selectElement.options).forEach(option => {
                option.selected = (option.value === newStatus);
            });
            
            selectElement.setAttribute('data-current-status', newStatus);
            
            if (iconElement) {
                const config = AVAILABILITY_CONFIG[newStatus];
                if (config) {
                    iconElement.setAttribute('data-icon', config.icon);
                    iconElement.className = `iconify ${config.iconColor}`;
                    
                    if (window.Iconify && window.Iconify.replace) {
                        window.Iconify.replace(iconElement);
                    } else if (window.iconify && window.iconify.replace) {
                        window.iconify.replace(iconElement);
                    }
                }
            }
            
            selectElement.classList.add('ring-2', 'scale-105');
            setTimeout(() => {
                selectElement.classList.remove('ring-2', 'scale-105');
            }, 1000);
            
        } else {
            throw new Error(data.message || 'Unknown server error');
        }
    })
    .catch(error => {
        console.error('💥 Error:', error);
        alert('Error saat update availability: ' + error.message);
    })
    .finally(() => {
        selectElement.disabled = false;
        selectElement.style.opacity = '1';
    });
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Admin dashboard loaded');
});
</script>

<?php include '../../includes/footer.php'; ?>