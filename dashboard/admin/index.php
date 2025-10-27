<?php
include '../../includes/config.php';
include '../../includes/functions.php';

if (!isLoggedIn() || getUserRole() != 'admin') {
    header("Location: ../../login.php");
    exit();
}

$stats = [];

$students_query = "SELECT COUNT(*) as total FROM users WHERE role = 'student'";
$students_result = $conn->query($students_query);
if ($students_result) {
    $stats['total_students'] = $students_result->fetch_assoc()['total'];
} else {
    $stats['total_students'] = 0;
}

$eligible_query = "SELECT COUNT(*) as total FROM users WHERE role = 'student' AND eligibility_status = 'eligible'";
$eligible_result = $conn->query($eligible_query);
if ($eligible_result) {
    $stats['eligible_students'] = $eligible_result->fetch_assoc()['total'];
} else {
    $stats['eligible_students'] = 0;
}

$mitra_query = "SELECT COUNT(*) as total FROM users WHERE role = 'mitra_industri'";
$mitra_result = $conn->query($mitra_query);
if ($mitra_result) {
    $stats['total_mitra'] = $mitra_result->fetch_assoc()['total'];
} else {
    $stats['total_mitra'] = 0;
}

$verified_mitra_query = "SELECT COUNT(*) as total FROM users WHERE role = 'mitra_industri' AND verification_status = 'verified'";
$verified_mitra_result = $conn->query($verified_mitra_query);
if ($verified_mitra_result) {
    $stats['verified_mitra'] = $verified_mitra_result->fetch_assoc()['total'];
} else {
    $stats['verified_mitra'] = 0;
}

$projects_query = "SELECT COUNT(*) as total FROM projects";
$projects_result = $conn->query($projects_query);
if ($projects_result) {
    $stats['total_projects'] = $projects_result->fetch_assoc()['total'];
} else {
    $stats['total_projects'] = 0;
}

$recent_activities = [];

$students_act = $conn->query("SELECT 'student' as type, name, created_at FROM users WHERE role = 'student' ORDER BY created_at DESC LIMIT 3");
if ($students_act) {
    while ($row = $students_act->fetch_assoc()) {
        $recent_activities[] = $row;
    }
}

$mitra_act = $conn->query("SELECT 'mitra' as type, name, created_at FROM users WHERE role = 'mitra_industri' ORDER BY created_at DESC LIMIT 3");
if ($mitra_act) {
    while ($row = $mitra_act->fetch_assoc()) {
        $recent_activities[] = $row;
    }
}

usort($recent_activities, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

$recent_activities = array_slice($recent_activities, 0, 5);
?>

<?php include '../../includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Hero Section -->
    <div class="bg-gradient-to-r from-[#2A8FA9] to-[#51A3B9] text-white rounded-2xl p-8 mb-8 shadow-lg">
        <div class="flex items-center gap-4">
            <div class="flex-1">
                <h1 class="text-3xl lg:text-4xl font-bold mb-4 flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:shield-account" data-width="40"></span>
                    Admin Dashboard
                </h1>
                <p class="text-[#E0F7FF] text-lg opacity-90">Kelola sistem portfolio mahasiswa dan mitra industri.</p>
            </div>
        </div>
    </div>

    <!-- Main Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Mahasiswa -->
        <div class="bg-white rounded-2xl p-6 text-center shadow-sm border border-gray-100 hover:shadow-md transition-all duration-300">
            <div class="text-[#51A3B9] mb-3 flex justify-center">
                <span class="iconify" data-icon="mdi:account-multiple" data-width="48"></span>
            </div>
            <h3 class="text-[#2A8FA9] font-bold text-2xl mb-2"><?php echo $stats['total_students'] ?? 0; ?></h3>
            <p class="text-gray-600 font-medium">Total Mahasiswa</p>
        </div>
        
        <!-- Mahasiswa Eligible -->
        <div class="bg-white rounded-2xl p-6 text-center shadow-sm border border-gray-100 hover:shadow-md transition-all duration-300">
            <div class="text-green-500 mb-3 flex justify-center">
                <span class="iconify" data-icon="mdi:check-circle" data-width="48"></span>
            </div>
            <h3 class="text-green-600 font-bold text-2xl mb-2"><?php echo $stats['eligible_students'] ?? 0; ?></h3>
            <p class="text-gray-600 font-medium">Mahasiswa Eligible</p>
        </div>
        
        <!-- Total Mitra -->
        <div class="bg-white rounded-2xl p-6 text-center shadow-sm border border-gray-100 hover:shadow-md transition-all duration-300">
            <div class="text-blue-500 mb-3 flex justify-center">
                <span class="iconify" data-icon="mdi:office-building" data-width="48"></span>
            </div>
            <h3 class="text-blue-600 font-bold text-2xl mb-2"><?php echo $stats['total_mitra'] ?? 0; ?></h3>
            <p class="text-gray-600 font-medium">Total Mitra</p>
        </div>
        
        <!-- Mitra Terverifikasi -->
        <div class="bg-white rounded-2xl p-6 text-center shadow-sm border border-gray-100 hover:shadow-md transition-all duration-300">
            <div class="text-purple-500 mb-3 flex justify-center">
                <span class="iconify" data-icon="mdi:shield-check" data-width="48"></span>
            </div>
            <h3 class="text-purple-600 font-bold text-2xl mb-2"><?php echo $stats['verified_mitra'] ?? 0; ?></h3>
            <p class="text-gray-600 font-medium">Mitra Terverifikasi</p>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                    <span class="iconify text-[#2A8FA9]" data-icon="mdi:lightning-bolt" data-width="24"></span>
                    Quick Actions
                </h2>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Kelola Mahasiswa -->
                    <a href="students.php" class="group bg-white border-2 border-gray-200 hover:border-[#2A8FA9] rounded-xl p-5 transition-all duration-200 hover:shadow-md">
                        <div class="flex items-start gap-4">
                            <div class="bg-blue-50 group-hover:bg-blue-100 p-3 rounded-lg transition-colors">
                                <span class="iconify text-[#2A8FA9]" data-icon="mdi:account-check" data-width="24"></span>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 mb-1 group-hover:text-[#2A8FA9] transition-colors">Kelola Mahasiswa</h3>
                                <p class="text-sm text-gray-600">Atur eligibility & verifikasi data</p>
                            </div>
                        </div>
                    </a>

                    <!-- Kelola Mitra -->
                    <a href="manage-mitra.php" class="group bg-white border-2 border-gray-200 hover:border-[#F9A825] rounded-xl p-5 transition-all duration-200 hover:shadow-md">
                        <div class="flex items-start gap-4">
                            <div class="bg-orange-50 group-hover:bg-orange-100 p-3 rounded-lg transition-colors">
                                <span class="iconify text-[#F9A825]" data-icon="mdi:office-building-cog" data-width="24"></span>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 mb-1 group-hover:text-[#F9A825] transition-colors">Kelola Mitra</h3>
                                <p class="text-sm text-gray-600">Verifikasi akun mitra industri</p>
                            </div>
                        </div>
                    </a>

                    <!-- Kelola spesialisasi -->
                    <a href="manage-data.php" class="group bg-white border-2 border-gray-200 hover:border-indigo-500 rounded-xl p-5 transition-all duration-200 hover:shadow-md">
                        <div class="flex items-start gap-4">
                            <div class="bg-indigo-50 group-hover:bg-indigo-100 p-3 rounded-lg transition-colors">
                                <span class="iconify text-indigo-600" data-icon="mdi:certificate" data-width="24"></span>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 mb-1 group-hover:text-indigo-600 transition-colors">Kelola Spesialisasi</h3>
                                <p class="text-sm text-gray-600">Tambah & edit spesialisasi</p>
                            </div>
                        </div>
                    </a>

                    <!-- Kelola Skills -->
                    <a href="manage-skills.php" class="group bg-white border-2 border-gray-200 hover:border-green-500 rounded-xl p-5 transition-all duration-200 hover:shadow-md">
                        <div class="flex items-start gap-4">
                            <div class="bg-green-50 group-hover:bg-green-100 p-3 rounded-lg transition-colors">
                                <span class="iconify text-green-600" data-icon="mdi:tag-multiple" data-width="24"></span>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 mb-1 group-hover:text-green-600 transition-colors">Kelola Skills</h3>
                                <p class="text-sm text-gray-600">Tambah & edit skills</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                    <span class="iconify text-[#2A8FA9]" data-icon="mdi:clock-outline" data-width="24"></span>
                    Aktivitas Terbaru
                </h2>
                
                <div class="space-y-3">
                    <?php if (!empty($recent_activities)): ?>
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                <?php if ($activity['type'] == 'student'): ?>
                                    <div class="bg-blue-500 p-2 rounded-lg flex-shrink-0">
                                        <span class="iconify text-white" data-icon="mdi:account-plus" data-width="16"></span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 truncate"><?php echo htmlspecialchars($activity['name']); ?></p>
                                        <p class="text-xs text-gray-500">Mahasiswa baru mendaftar</p>
                                        <p class="text-xs text-gray-400 mt-1"><?php echo date('d M Y H:i', strtotime($activity['created_at'])); ?></p>
                                    </div>
                                <?php elseif ($activity['type'] == 'mitra'): ?>
                                    <div class="bg-orange-500 p-2 rounded-lg flex-shrink-0">
                                        <span class="iconify text-white" data-icon="mdi:office-building-plus" data-width="16"></span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 truncate"><?php echo htmlspecialchars($activity['name']); ?></p>
                                        <p class="text-xs text-gray-500">Mitra baru mendaftar</p>
                                        <p class="text-xs text-gray-400 mt-1"><?php echo date('d M Y H:i', strtotime($activity['created_at'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-400">
                            <span class="iconify inline-block mb-2" data-icon="mdi:inbox" data-width="32"></span>
                            <p class="text-sm">Belum ada aktivitas</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>