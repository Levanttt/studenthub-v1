<?php
include '../../includes/config.php';
include '../../includes/functions.php';

if (!isLoggedIn() || getUserRole() != 'mitra_industri') {
    header("Location: ../../login.php");
    exit();
}

$mitra_id = $_SESSION['user_id'];

// Get all interests from this mitra
$interests = [];
try {
    $query = "
        SELECT mi.*, 
               s.id as student_id,
               s.name as student_name,
               s.profile_picture as student_photo,
               s.major as student_major,
               s.university as student_university,
               s.semester as student_semester,
               s.specializations as student_specializations,
               s.availability_status as student_availability,
               mi.created_at as interest_date,
               mi.updated_at as status_updated
        FROM mitra_interest mi
        JOIN users s ON mi.student_id = s.id
        WHERE mi.mitra_id = ?
        ORDER BY mi.created_at DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $mitra_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $interests = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching interests: " . $e->getMessage());
}

// Get counts for stats
$total_interests = count($interests);
$pending_count = 0;
$contacted_count = 0;
$other_process_count = 0;

foreach ($interests as $interest) {
    if ($interest['status'] === 'contacted') {
        $contacted_count++;
    } elseif ($interest['status'] === 'pending') {
        if ($interest['student_availability'] === 'available') {
            $pending_count++;
        } else {
            $other_process_count++;
        }
    }
}

function getDisplayStatus($interest_status, $student_availability) {
    if ($interest_status === 'contacted') {
        return [
            'text' => 'Telah Ditindaklanjuti',
            'class' => 'bg-green-100 text-green-800 border border-green-200',
            'icon' => 'mdi:phone-check'
        ];
    } elseif ($interest_status === 'pending') {
        if ($student_availability === 'available') {
            return [
                'text' => 'Menunggu Review CDC',
                'class' => 'bg-yellow-100 text-yellow-800 border border-yellow-200',
                'icon' => 'mdi:clock-outline'
            ];
        } else {
            return [
                'text' => 'Kandidat Dalam Proses Lain',
                'class' => 'bg-gray-100 text-gray-800 border border-gray-200',
                'icon' => 'mdi:account-clock'
            ];
        }
    }
    
    // Default fallback
    return [
        'text' => 'Unknown',
        'class' => 'bg-gray-100 text-gray-800 border border-gray-200',
        'icon' => 'mdi:help-circle'
    ];
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
                    Kandidat Yang Saya Minati
                </h1>
                <p class="text-gray-600 mt-2">Lihat semua kandidat yang telah Anda ajukan ketertarikan</p>
            </div>
            <a href="index.php" class="bg-[#E0F7FF] text-[#2A8FA9] px-6 py-3 rounded-xl font-semibold hover:bg-[#51A3B9] hover:text-white transition-colors duration-300 border border-[#51A3B9] border-opacity-30 flex items-center gap-2">
                <span class="iconify" data-icon="mdi:arrow-left" data-width="16"></span>
                Kembali ke Pencarian
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <!-- Total Kandidat -->
        <div class="bg-white rounded-2xl p-6 text-center shadow-sm border border-gray-200">
            <div class="text-[#2A8FA9] mb-3 flex justify-center">
                <span class="iconify" data-icon="mdi:account-multiple" data-width="48"></span>
            </div>
            <h3 class="text-[#2A8FA9] font-bold text-2xl mb-2"><?php echo $total_interests; ?></h3>
            <p class="text-gray-600 font-medium">Total Kandidat</p>
        </div>

        <!-- Menunggu Review -->
        <div class="bg-white rounded-2xl p-6 text-center shadow-sm border border-gray-200">
            <div class="text-yellow-500 mb-3 flex justify-center">
                <span class="iconify" data-icon="mdi:clock" data-width="48"></span>
            </div>
            <h3 class="text-yellow-600 font-bold text-2xl mb-2"><?php echo $pending_count; ?></h3>
            <p class="text-gray-600 font-medium">Menunggu Review CDC</p>
        </div>

        <!-- Sedang Diproses -->
        <div class="bg-white rounded-2xl p-6 text-center shadow-sm border border-gray-200">
            <div class="text-green-500 mb-3 flex justify-center">
                <span class="iconify" data-icon="mdi:phone-check" data-width="48"></span>
            </div>
            <h3 class="text-green-600 font-bold text-2xl mb-2"><?php echo $contacted_count; ?></h3>
            <p class="text-gray-600 font-medium">Telah Ditindaklanjuti</p>
        </div>

        <!-- Dalam Proses Lain -->
        <div class="bg-white rounded-2xl p-6 text-center shadow-sm border border-gray-200">
            <div class="text-gray-500 mb-3 flex justify-center">
                <span class="iconify" data-icon="mdi:account-clock" data-width="48"></span>
            </div>
            <h3 class="text-gray-600 font-bold text-2xl mb-2"><?php echo $other_process_count; ?></h3>
            <p class="text-gray-600 font-medium">Dalam Proses Lain</p>
        </div>
    </div>

    <!-- Interests List -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <?php if (!empty($interests)): ?>
            <div class="divide-y divide-gray-200">
                <?php foreach ($interests as $interest): 
                    $display_status = getDisplayStatus($interest['status'], $interest['student_availability']);
                ?>
                    <div class="p-6 hover:bg-gray-50 transition-colors">
                        <div class="flex flex-col lg:flex-row gap-6">
                            <!-- Student Info -->
                            <div class="flex items-start gap-4 flex-1">
                                <!-- Student Photo -->
                                <?php if (!empty($interest['student_photo'])): ?>
                                    <img class="h-16 w-16 rounded-full object-cover border-2 border-[#E0F7FF] flex-shrink-0" 
                                         src="<?php echo htmlspecialchars($interest['student_photo']); ?>" 
                                         alt="<?php echo htmlspecialchars($interest['student_name']); ?>">
                                <?php else: ?>
                                    <div class="h-16 w-16 rounded-full bg-gradient-to-br from-[#51A3B9] to-[#2A8FA9] flex items-center justify-center border-2 border-[#E0F7FF] flex-shrink-0">
                                        <span class="iconify text-white" data-icon="mdi:account" data-width="32"></span>
                                    </div>
                                <?php endif; ?>

                                <!-- Student Details -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2 mb-3">
                                        <div>
                                            <h3 class="text-xl font-bold text-[#2A8FA9] mb-1">
                                                <?php echo htmlspecialchars($interest['student_name']); ?>
                                            </h3>
                                            <div class="flex flex-wrap items-center gap-2 text-sm text-gray-600 mb-2">
                                                <?php if (!empty($interest['student_major'])): ?>
                                                    <span class="flex items-center gap-1">
                                                        <span class="iconify" data-icon="mdi:book-education" data-width="14"></span>
                                                        <?php echo htmlspecialchars($interest['student_major']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($interest['student_university'])): ?>
                                                    <span class="flex items-center gap-1">
                                                        <span class="iconify" data-icon="mdi:school" data-width="14"></span>
                                                        <?php echo htmlspecialchars($interest['student_university']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($interest['student_semester'])): ?>
                                                    <span class="flex items-center gap-1">
                                                        <span class="iconify" data-icon="mdi:calendar" data-width="14"></span>
                                                        Semester <?php echo htmlspecialchars($interest['student_semester']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Status Badge -->
                                        <div class="flex sm:flex-col items-start sm:items-end gap-2">
                                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium <?php echo $display_status['class']; ?>">
                                                <span class="iconify" data-icon="<?php echo $display_status['icon']; ?>" data-width="14"></span>
                                                <?php echo $display_status['text']; ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Specializations -->
                                    <?php if (!empty($interest['student_specializations'])): ?>
                                        <div class="mb-3">
                                            <p class="text-sm text-gray-600 mb-2 font-medium">Spesialisasi:</p>
                                            <div class="flex flex-wrap gap-1">
                                                <?php 
                                                $specs = explode(',', $interest['student_specializations']);
                                                foreach ($specs as $spec):
                                                    $spec = trim($spec);
                                                    if (!empty($spec)):
                                                ?>
                                                    <span class="bg-[#E0F7FF] text-[#2A8FA9] px-2 py-1 rounded-full text-xs font-medium">
                                                        <?php echo htmlspecialchars($spec); ?>
                                                    </span>
                                                <?php 
                                                    endif;
                                                endforeach; 
                                                ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Message from Mitra -->
                                    <?php if (!empty($interest['message'])): ?>
                                        <div class="bg-blue-50 rounded-lg p-3 border border-blue-200">
                                            <h4 class="font-semibold text-blue-900 mb-2 flex items-center gap-2 text-sm">
                                                <span class="iconify" data-icon="mdi:message-text" data-width="14"></span>
                                                Pesan yang Anda Kirim:
                                            </h4>
                                            <p class="text-blue-800 text-sm italic">"<?php echo htmlspecialchars($interest['message']); ?>"</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons & Timestamps -->
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mt-4 pt-4 border-t border-gray-200">
                            <div class="flex flex-wrap gap-4 text-xs text-gray-500">
                                <span class="flex items-center gap-1">
                                    <span class="iconify" data-icon="mdi:calendar-plus" data-width="12"></span>
                                    Diajukan: <?php echo date('d M Y H:i', strtotime($interest['interest_date'])); ?>
                                </span>
                                
                                <?php if ($interest['status_updated'] !== $interest['interest_date']): ?>
                                    <span class="flex items-center gap-1">
                                        <span class="iconify" data-icon="mdi:update" data-width="12"></span>
                                        Diupdate: <?php echo date('d M Y H:i', strtotime($interest['status_updated'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="flex gap-2">
                                <a href="student-profile.php?id=<?php echo $interest['student_id']; ?>" 
                                    class="bg-[#2A8FA9] text-white px-4 py-2 rounded-lg font-semibold hover:bg-[#409BB2] transition-colors flex items-center gap-2 text-sm">
                                    <span class="iconify" data-icon="mdi:eye" data-width="14"></span>
                                    Lihat Profil
                                </a>
                                
                                <?php if ($display_status['text'] === 'Menunggu Review CDC'): ?>
                                    <button class="bg-gray-100 text-gray-600 px-4 py-2 rounded-lg font-semibold flex items-center gap-2 text-sm cursor-not-allowed" disabled>
                                        <span class="iconify" data-icon="mdi:clock" data-width="14"></span>
                                        Menunggu CDC
                                    </button>
                                <?php elseif ($display_status['text'] === 'Telah Ditindaklanjuti'): ?>
                                    <button class="bg-green-100 text-green-700 px-4 py-2 rounded-lg font-semibold flex items-center gap-2 text-sm cursor-not-allowed" disabled>
                                        <span class="iconify" data-icon="mdi:phone" data-width="14"></span>
                                        CDC Telah Menghubungi
                                    </button>
                                <?php else: ?>
                                    <button class="bg-gray-100 text-gray-600 px-4 py-2 rounded-lg font-semibold flex items-center gap-2 text-sm cursor-not-allowed" disabled>
                                        <span class="iconify" data-icon="mdi:account-clock" data-width="14"></span>
                                        Kandidat Sedang Diproses
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="text-center py-12">
                <span class="iconify text-gray-400 mx-auto mb-4" data-icon="mdi:heart-off" data-width="64"></span>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Belum Ada Kandidat</h3>
                <p class="text-gray-600 mb-6 max-w-md mx-auto">
                    Anda belum mengajukan ketertarikan pada kandidat manapun. Mulai jelajahi talenta-talenta terbaik untuk kebutuhan perusahaan Anda.
                </p>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <a href="index.php" 
                       class="bg-[#2A8FA9] text-white px-6 py-3 rounded-lg font-semibold hover:bg-[#409BB2] transition-colors inline-flex items-center gap-2">
                        <span class="iconify" data-icon="mdi:account-search" data-width="16"></span>
                        Jelajahi Talenta
                    </a>
                    <a href="index.php?show_all=1" 
                       class="bg-[#51A3B9] text-white px-6 py-3 rounded-lg font-semibold hover:bg-[#409BB2] transition-colors inline-flex items-center gap-2">
                        <span class="iconify" data-icon="mdi:account-multiple" data-width="16"></span>
                        Lihat Semua Mahasiswa
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>