<?php
include '../../includes/config.php';
include '../../includes/functions.php';

if (!isLoggedIn() || getUserRole() != 'admin') {
    header("Location: ../../login.php");
    exit();
}

// Fetch statistics
$stats = [
    'total_students' => getCount("SELECT COUNT(*) as total FROM users WHERE role = 'student'"),
    'eligible_students' => getCount("SELECT COUNT(*) as total FROM users WHERE role = 'student' AND eligibility_status = 'eligible'"),
    'total_mitra' => getCount("SELECT COUNT(*) as total FROM users WHERE role = 'mitra_industri'"),
    'verified_mitra' => getCount("SELECT COUNT(*) as total FROM users WHERE role = 'mitra_industri' AND verification_status = 'verified'"),
    'total_projects' => getCount("SELECT COUNT(*) as total FROM projects"),
    'pending_interests' => getCount("SELECT COUNT(*) as total FROM mitra_interest WHERE status = 'pending'"),
    'contacted_interests' => getCount("SELECT COUNT(*) as total FROM mitra_interest WHERE status = 'contacted'")
];

// Fetch recent activities
$recent_activities = getRecentActivities();

// Fetch recent interests
$recent_interests = getRecentInterests();

// Helper functions
function getCount($query) {
    global $conn;
    $result = $conn->query($query);
    return $result ? $result->fetch_assoc()['total'] : 0;
}

function getRecentActivities() {
    global $conn;
    $activities = [];
    
    $students = $conn->query("SELECT 'student' as type, name, created_at FROM users WHERE role = 'student' ORDER BY created_at DESC LIMIT 3");
    if ($students) {
        while ($row = $students->fetch_assoc()) {
            $activities[] = $row;
        }
    }
    
    $mitra = $conn->query("SELECT 'mitra' as type, name, created_at FROM users WHERE role = 'mitra_industri' ORDER BY created_at DESC LIMIT 3");
    if ($mitra) {
        while ($row = $mitra->fetch_assoc()) {
            $activities[] = $row;
        }
    }
    
    usort($activities, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    return array_slice($activities, 0, 5);
}

function getRecentInterests() {
    global $conn;
    $interests = [];
    
    $query = "
        SELECT mi.*, 
               m.company_name as mitra_company, 
               m.email as mitra_email,
               s.name as student_name,
               s.major as student_major
        FROM mitra_interest mi
        JOIN users m ON mi.mitra_id = m.id
        JOIN users s ON mi.student_id = s.id
        ORDER BY mi.created_at DESC 
        LIMIT 5
    ";
    
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $interests[] = $row;
        }
    }
    
    return $interests;
}
?>

<?php include '../../includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header Section -->
    <div class="bg-gradient-to-r from-[#2A8FA9] to-[#51A3B9] text-white rounded-2xl p-8 mb-8 shadow-lg">
        <div class="flex items-center gap-4">
            <div class="flex-1">
                <h1 class="text-3xl lg:text-4xl font-bold mb-4 flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:shield-account" data-width="40"></span>
                    Admin Dashboard
                </h1>
                <p class="text-[#E0F7FF] text-lg opacity-90">Kelola sistem portfolio mahasiswa dan mitra industri</p>
            </div>
        </div>
    </div>

    <!-- Statistics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <?php renderStatCard(
            'mdi:account-multiple', 
            $stats['total_students'], 
            'Total Mahasiswa', 
            '#2A8FA9'
        ); ?>
        
        <?php renderStatCard(
            'mdi:check-circle', 
            $stats['eligible_students'], 
            'Mahasiswa Eligible', 
            'green'
        ); ?>
        
        <?php renderStatCard(
            'mdi:office-building', 
            $stats['total_mitra'], 
            'Total Mitra', 
            'blue'
        ); ?>
        
        <?php renderStatCard(
            'mdi:shield-check', 
            $stats['verified_mitra'], 
            'Mitra Terverifikasi', 
            'purple'
        ); ?>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Quick Actions -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                    <span class="iconify text-[#2A8FA9]" data-icon="mdi:lightning-bolt" data-width="24"></span>
                    Quick Actions
                </h2>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php renderActionCard(
                        'students.php',
                        'mdi:account-check',
                        'Kelola Mahasiswa',
                        'Atur eligibility & verifikasi data',
                        '#2A8FA9',
                        'blue'
                    ); ?>
                    
                    <?php renderActionCard(
                        'manage-mitra.php',
                        'mdi:office-building-cog',
                        'Kelola Mitra',
                        'Verifikasi akun mitra industri',
                        '#F9A825',
                        'orange'
                    ); ?>
                    
                    <?php renderActionCard(
                        'manage-data.php',
                        'mdi:certificate',
                        'Kelola Spesialisasi',
                        'Tambah & edit spesialisasi',
                        'indigo',
                        'indigo'
                    ); ?>
                    
                    <?php renderActionCard(
                        'manage-skills.php',
                        'mdi:tag-multiple',
                        'Kelola Skills',
                        'Tambah & edit skills',
                        'green',
                        'green'
                    ); ?>
                </div>
            </div>

            <!-- Ketertarikan Mitra -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                        <span class="iconify text-[#2A8FA9]" data-icon="mdi:heart" data-width="24"></span>
                        Ketertarikan Mitra
                    </h2>
                    <a href="manage-interest.php" class="text-sm text-[#2A8FA9] hover:text-[#409BB2] font-medium flex items-center gap-1">
                        Lihat Semua
                        <span class="iconify" data-icon="mdi:chevron-right" data-width="16"></span>
                    </a>
                </div>
                
                <?php 
                $display_interests = array_slice($recent_interests, 0, 2);
                ?>
                
                <?php if (!empty($display_interests)): ?>
                    <div class="space-y-4">
                        <?php foreach ($display_interests as $interest): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:border-[#2A8FA9] hover:bg-[#E0F7FF] transition-all duration-200">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <h3 class="font-semibold text-gray-900"><?php echo htmlspecialchars($interest['mitra_company']); ?></h3>
                                            <span class="text-xs px-2 py-1 rounded-full <?php echo getStatusBadgeClass($interest['status']); ?>">
                                                <?php echo getStatusText($interest['status']); ?>
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-600 mb-2">
                                            Tertarik dengan 
                                            <span class="font-medium text-[#2A8FA9]"><?php echo htmlspecialchars($interest['student_name']); ?></span>
                                            - <?php echo htmlspecialchars($interest['student_major']); ?>
                                        </p>
                                        
                                        <?php if (!empty($interest['message'])): ?>
                                            <div class="bg-gray-50 rounded p-3 mb-2">
                                                <p class="text-sm text-gray-700 italic">"<?php echo htmlspecialchars($interest['message']); ?>"</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="flex justify-between items-center text-xs text-gray-500">
                                    <div class="flex items-center gap-4">
                                        <span class="flex items-center gap-1">
                                            <span class="iconify" data-icon="mdi:calendar" data-width="12"></span>
                                            <?php echo date('d M Y', strtotime($interest['created_at'])); ?>
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <span class="iconify" data-icon="mdi:email" data-width="12"></span>
                                            <?php echo htmlspecialchars($interest['mitra_email']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="flex gap-2">
                                        <?php renderInterestActionButtons($interest); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-400">
                        <span class="iconify inline-block mb-2" data-icon="mdi:heart-off" data-width="32"></span>
                        <p class="text-sm">Belum ada ketertarikan dari mitra</p>
                    </div>
                <?php endif; ?>
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
                                <?php else: ?>
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

    <!-- Modal Konfirmasi Update Status -->
    <div id="statusModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden p-4">
        <div class="bg-white rounded-2xl max-w-xl w-full p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <span class="iconify text-blue-600" data-icon="mdi:information" data-width="20"></span>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900" id="modalTitle">Update Status</h3>
                    <p class="text-sm text-gray-600" id="modalMessage">Apakah Anda yakin ingin mengupdate status?</p>
                </div>
            </div>
            
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                <div class="flex items-start gap-2">
                    <span class="iconify text-blue-600 mt-0.5" data-icon="mdi:clock" data-width="14"></span>
                    <p class="text-blue-700 text-xs">
                        Status akan diupdate dan mitra akan masuk ke tahap selanjutnya.
                    </p>
                </div>
            </div>
            
            <div class="flex gap-3">
                <button type="button" onclick="closeStatusModal()" 
                        class="flex-1 bg-gray-300 text-gray-700 py-2 px-4 rounded-lg font-semibold hover:bg-gray-400 transition-colors">
                    Batal
                </button>
                <button type="button" id="confirmButton"
                        class="flex-1 bg-[#2A8FA9] text-white py-2 px-4 rounded-lg font-semibold hover:bg-[#227a94] transition-colors flex items-center justify-center gap-2">
                    <span class="iconify" data-icon="mdi:check" data-width="16"></span>
                    Ya, Update
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<?php
function renderStatCard($icon, $count, $label, $color) {
    $colorClasses = [
        'blue' => 'text-blue-500',
        'green' => 'text-green-500', 
        'purple' => 'text-purple-500',
        '#2A8FA9' => 'text-[#2A8FA9]'
    ];
    
    $textClasses = [
        'blue' => 'text-blue-600',
        'green' => 'text-green-600',
        'purple' => 'text-purple-600', 
        '#2A8FA9' => 'text-[#2A8FA9]'
    ];
    
    $iconColor = $colorClasses[$color] ?? $colorClasses['blue'];
    $textColor = $textClasses[$color] ?? $textClasses['blue'];
    ?>
    <div class="bg-white rounded-2xl p-6 text-center shadow-sm border border-gray-100 hover:shadow-md transition-all duration-300">
        <div class="<?php echo $iconColor; ?> mb-3 flex justify-center">
            <span class="iconify" data-icon="<?php echo $icon; ?>" data-width="48"></span>
        </div>
        <h3 class="<?php echo $textColor; ?> font-bold text-2xl mb-2"><?php echo $count; ?></h3>
        <p class="text-gray-600 font-medium"><?php echo $label; ?></p>
    </div>
    <?php
}

function renderActionCard($link, $icon, $title, $description, $color, $bgColor) {
    $colorClasses = [
        'blue' => 'text-blue-600 border-blue-200 hover:border-blue-500 bg-blue-50 hover:bg-blue-100',
        'orange' => 'text-orange-600 border-orange-200 hover:border-orange-500 bg-orange-50 hover:bg-orange-100',
        'indigo' => 'text-indigo-600 border-indigo-200 hover:border-indigo-500 bg-indigo-50 hover:bg-indigo-100',
        'green' => 'text-green-600 border-green-200 hover:border-green-500 bg-green-50 hover:bg-green-100'
    ];
    
    $class = $colorClasses[$bgColor] ?? $colorClasses['blue'];
    ?>
    <a href="<?php echo $link; ?>" class="group bg-white border-2 border-gray-200 hover:border-<?php echo $color; ?> rounded-xl p-5 transition-all duration-200 hover:shadow-md">
        <div class="flex items-start gap-4">
            <div class="<?php echo $class; ?> p-3 rounded-lg transition-colors">
                <span class="iconify" data-icon="<?php echo $icon; ?>" data-width="24"></span>
            </div>
            <div>
                <h3 class="font-bold text-gray-900 mb-1 group-hover:text-<?php echo $color; ?> transition-colors"><?php echo $title; ?></h3>
                <p class="text-sm text-gray-600"><?php echo $description; ?></p>
            </div>
        </div>
    </a>
    <?php
}

function renderInterestStatCard($icon, $count, $label, $color) {
    $colorClasses = [
        'yellow' => 'bg-yellow-50 border-yellow-200 text-yellow-600',
        'blue' => 'bg-blue-50 border-blue-200 text-blue-600',
        'green' => 'bg-green-50 border-green-200 text-green-600'
    ];
    
    $class = $colorClasses[$color] ?? $colorClasses['yellow'];
    ?>
    <div class="<?php echo $class; ?> border rounded-xl p-4 text-center">
        <div class="mb-2 flex justify-center">
            <span class="iconify" data-icon="<?php echo $icon; ?>" data-width="24"></span>
        </div>
        <h3 class="font-bold text-xl mb-1"><?php echo $count; ?></h3>
        <p class="text-sm font-medium"><?php echo $label; ?></p>
    </div>
    <?php
}

function getStatusBadgeClass($status) {
    return [
        'pending' => 'bg-yellow-100 text-yellow-800',
        'reviewed' => 'bg-blue-100 text-blue-800', 
        'contacted' => 'bg-green-100 text-green-800'
    ][$status] ?? 'bg-gray-100 text-gray-800';
}

function getStatusText($status) {
    return [
        'pending' => 'Menunggu',
        'reviewed' => 'Ditinjau',
        'contacted' => 'Selesai'
    ][$status] ?? 'Unknown';
}

function renderInterestActionButtons($interest) {
    $status = $interest['status'];
    $id = $interest['id'];
    
    if ($status === 'pending') {
        ?>
        <button onclick="showStatusModal(<?php echo $id; ?>, 'contacted', 'Hubungi Mahasiswa', '<?php echo htmlspecialchars($interest['mitra_company']); ?>', '<?php echo htmlspecialchars($interest['student_name']); ?>')" 
                class="text-green-600 hover:text-green-800 flex items-center gap-2 cursor-pointer group px-3 py-2 bg-green-50 rounded-lg hover:bg-green-100 transition-all duration-200">
            <div class="w-6 h-6 bg-green-100 rounded-full flex items-center justify-center group-hover:bg-green-200 transition-colors">
                <span class="iconify text-green-600" data-icon="mdi:account-arrow-right" data-width="14"></span>
            </div>
            <span class="font-medium">Hubungi Mahasiswa</span>
        </button>
        <?php
    } else { 
        ?>
        <button class="text-gray-500 flex items-center gap-2 cursor-not-allowed group px-3 py-2 bg-gray-100 rounded-lg" disabled>
            <div class="w-6 h-6 bg-gray-200 rounded-full flex items-center justify-center">
                <span class="iconify text-gray-400" data-icon="mdi:check-circle" data-width="14"></span>
            </div>
            <span class="font-medium">Sudah Dihubungi</span>
        </button>
        <?php
    }
}
?>

<script>
let currentInterestId = null;
let currentNewStatus = null;

function showStatusModal(interestId, newStatus, action, mitraName, studentName) {
    currentInterestId = interestId;
    currentNewStatus = newStatus;
    
    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');
    const confirmButton = document.getElementById('confirmButton');
    
    modalTitle.textContent = `Tindak Lanjuti Permintaan`;
    
    modalMessage.innerHTML = `
        <div class="mb-3">
            Konfirmasi untuk menindaklanjuti ketertarikan dari <strong>${mitraName}</strong> 
            kepada <strong>${studentName}</strong>.
        </div>
        <div class="text-sm text-gray-600 bg-green-50 p-3 rounded border border-green-200">
            <div class="flex items-start gap-2">
                <span class="iconify text-green-600 mt-0.5" data-icon="mdi:information" data-width="14"></span>
                <div>
                    <p class="font-medium text-green-800">Next Steps (Tugas Anda):</p>
                    <ul class="list-disc list-inside mt-1 space-y-1">
                        <li>Hubungi <strong>${studentName}</strong> (mahasiswa) via WA/Email.</li>
                        <li>Sampaikan pesan ketertarikan dari <strong>${mitraName}</strong>.</li>
                        <li>Bantu koordinasi proses selanjutnya.</li>
                        <li>Klik "Konfirmasi" di bawah setelah mahasiswa dihubungi.</li>
                    </ul>
                </div>
            </div>
        </div>
    `;
    
    confirmButton.innerHTML = `
        <span class="iconify" data-icon="mdi:check-circle" data-width="16"></span>
        Ya, Mahasiswa Dihubungi
    `;
    confirmButton.className = 'flex-1 bg-green-500 text-white py-2 px-4 rounded-lg font-semibold hover:bg-green-600 transition-colors flex items-center justify-center gap-2';
    
    document.getElementById('statusModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeStatusModal() {
    document.getElementById('statusModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
    currentInterestId = null;
    currentNewStatus = null;
}

function confirmStatusUpdate() {
    if (!currentInterestId || !currentNewStatus) return;
    
    const confirmButton = document.getElementById('confirmButton');
    const originalText = confirmButton.innerHTML;
    confirmButton.innerHTML = `
        <span class="iconify animate-spin" data-icon="mdi:loading" data-width="16"></span>
        Memproses...
    `;
    confirmButton.disabled = true;
    
    fetch('update-interest-status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            interest_id: currentInterestId,
            status: currentNewStatus
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Status berhasil diupdate!', 'success');
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showToast('Error: ' + data.message, 'error');
            confirmButton.innerHTML = originalText;
            confirmButton.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Terjadi kesalahan saat update status', 'error');
        confirmButton.innerHTML = originalText;
        confirmButton.disabled = false;
    });
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg transform transition-all duration-300 ${
        type === 'success' ? 'bg-green-500 text-white' : 
        type === 'error' ? 'bg-red-500 text-white' : 
        'bg-blue-500 text-white'
    }`;
    toast.innerHTML = `
        <div class="flex items-center gap-2">
            <span class="iconify" data-icon="${
                type === 'success' ? 'mdi:check-circle' : 
                type === 'error' ? 'mdi:alert-circle' : 
                'mdi:information'
            }" data-width="20"></span>
            <span class="font-medium">${message}</span>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('translate-x-0', 'opacity-100');
    }, 10);
    
    setTimeout(() => {
        toast.classList.remove('translate-x-0', 'opacity-100');
        toast.classList.add('translate-x-full', 'opacity-0');
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 3000);
}

document.getElementById('confirmButton').addEventListener('click', confirmStatusUpdate);

document.getElementById('statusModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeStatusModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeStatusModal();
    }
});
</script>