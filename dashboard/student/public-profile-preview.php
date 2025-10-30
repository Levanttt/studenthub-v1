<?php
include '../../includes/config.php';
include '../../includes/functions.php';

if (!isLoggedIn() || getUserRole() != 'student') {
    header("Location: ../../login.php");
    exit();
}

$student_id = intval($_SESSION['user_id']);
$student = [];
try {
    $student_query = "
        SELECT id, name, email, profile_picture, phone, major, bio, specializations, 
                cv_file_path, linkedin, created_at, semester
        FROM users 
        WHERE id = ? AND role = 'student'
    ";
    $stmt = $conn->prepare($student_query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
    
    if (!$student) {
        die("Student not found");
    }
} catch (Exception $e) {
    die("Database error");
}

$work_preferences = [];

try {
    $work_pref_query = "
        SELECT wl.name 
        FROM user_work_locations uwl 
        JOIN work_locations wl ON uwl.work_location_id = wl.id 
        WHERE uwl.user_id = ? 
        ORDER BY wl.name
    ";
    
    $work_pref_stmt = $conn->prepare($work_pref_query);
    $work_pref_stmt->bind_param("i", $student_id);
    $work_pref_stmt->execute();
    $work_pref_result = $work_pref_stmt->get_result();
    
    while ($pref = $work_pref_result->fetch_assoc()) {
        $work_preferences[] = $pref['name'];
    }
    $work_pref_stmt->close();
    
} catch (Exception $e) {
    error_log("Work preferences query error: " . $e->getMessage());
}

$all_skills = [
    'technical' => [],
    'soft' => [],
    'tool' => []
];

try {
    $skills_query = "
        SELECT DISTINCT s.name, s.skill_type, COUNT(DISTINCT p.id) as project_count 
        FROM project_skills ps 
        JOIN projects p ON ps.project_id = p.id
        JOIN skills s ON ps.skill_id = s.id 
        WHERE p.student_id = ?
        GROUP BY s.id
        ORDER BY project_count DESC, s.skill_type, s.name
    ";
    $skills_stmt = $conn->prepare($skills_query);
    $skills_stmt->bind_param("i", $student_id);
    $skills_stmt->execute();
    $skills_result = $skills_stmt->get_result();
    
    while ($skill = $skills_result->fetch_assoc()) {
        $skill_type = $skill['skill_type'];
        if (isset($all_skills[$skill_type])) {
            $all_skills[$skill_type][] = $skill;
        }
    }
    $skills_stmt->close();
} catch (Exception $e) {}

$projects = [];
try {
    $projects_query = "
        SELECT p.*, 
                GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') as skill_names,
                GROUP_CONCAT(DISTINCT s.skill_type ORDER BY s.skill_type SEPARATOR ', ') as skill_types,
                GROUP_CONCAT(DISTINCT pi.image_path ORDER BY pi.id SEPARATOR '|||') as gallery_images
        FROM projects p
        LEFT JOIN project_skills ps ON p.id = ps.project_id
        LEFT JOIN skills s ON ps.skill_id = s.id
        LEFT JOIN project_images pi ON p.id = pi.project_id
        WHERE p.student_id = ?
        GROUP BY p.id
        ORDER BY p.project_year DESC, p.created_at DESC
        LIMIT 2
    ";
    $projects_stmt = $conn->prepare($projects_query);
    $projects_stmt->bind_param("i", $student_id);
    $projects_stmt->execute();
    $projects_result = $projects_stmt->get_result();
    $projects = $projects_result->fetch_all(MYSQLI_ASSOC);
    $projects_stmt->close();
} catch (Exception $e) {}

foreach ($projects as &$project) {
    $project_skills = [];
    try {
        $project_skills_query = "
            SELECT s.name, s.skill_type 
            FROM project_skills ps 
            JOIN skills s ON ps.skill_id = s.id 
            WHERE ps.project_id = ?
            ORDER BY s.skill_type, s.name
        ";
        $project_skills_stmt = $conn->prepare($project_skills_query);
        $project_skills_stmt->bind_param("i", $project['id']);
        $project_skills_stmt->execute();
        $project_skills_result = $project_skills_stmt->get_result();
        
        while ($skill = $project_skills_result->fetch_assoc()) {
            $project_skills[] = $skill;
        }
        $project_skills_stmt->close();
    } catch (Exception $e) {}
    
    $project['skills_detail'] = $project_skills;
}
unset($project);

$total_projects = count($projects);
$certificates = [];

try {
    $standalone_certs_query = "
        SELECT 
            c.id,
            c.title as certificate_name,
            c.organization as issuing_organization,
            c.issue_date,
            c.expiry_date,
            c.credential_id,
            c.credential_url,
            c.file_path as image_path,
            c.description,
            c.project_id,
            'standalone' as source_type
        FROM certificates c
        WHERE c.student_id = ?
        ORDER BY c.issue_date DESC
        LIMIT 4
    ";
    
    $standalone_stmt = $conn->prepare($standalone_certs_query);
    if ($standalone_stmt) {
        $standalone_stmt->bind_param("i", $student_id);
        $standalone_stmt->execute();
        $standalone_result = $standalone_stmt->get_result();
        while ($cert = $standalone_result->fetch_assoc()) {
            $certificates[] = $cert;
        }
        $standalone_stmt->close();
    }
} catch (Exception $e) {}

try {
    $project_certs_query = "
        SELECT 
            p.id as id,
            p.title as certificate_name,
            'Project Completion' as issuing_organization,
            p.certificate_issue_date as issue_date,
            p.certificate_expiry_date as expiry_date,
            p.certificate_credential_id as credential_id,
            p.certificate_credential_url as credential_url,
            p.certificate_path as image_path,
            p.certificate_description as description,
            p.id as project_id,
            'project' as source_type
        FROM projects p
        WHERE p.student_id = ? 
        AND p.certificate_path IS NOT NULL 
        AND p.certificate_path != ''
        ORDER BY p.created_at DESC
        LIMIT 4
    ";
    
    $project_certs_stmt = $conn->prepare($project_certs_query);
    if ($project_certs_stmt) {
        $project_certs_stmt->bind_param("i", $student_id);
        $project_certs_stmt->execute();
        $project_certs_result = $project_certs_stmt->get_result();
        while ($cert = $project_certs_result->fetch_assoc()) {
            $certificates[] = $cert;
        }
        $project_certs_stmt->close();
    }
} catch (Exception $e) {}

usort($certificates, function($a, $b) {
    return strtotime($b['issue_date']) - strtotime($a['issue_date']);
});
?>

<?php include '../../includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-8">
    <!-- Header -->
    <div class="mb-6 sm:mb-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4 sm:mb-6">
            <div class="w-full sm:w-auto">
                <h1 class="text-2xl sm:text-3xl font-bold text-[#2A8FA9] flex items-center gap-2 sm:gap-3">
                    <span class="iconify" data-icon="mdi:account-eye" data-width="24" data-height="24" class="sm:w-8 sm:h-8"></span>
                    <span class="break-words">Preview Profil Publik</span>
                </h1>
                <p class="text-sm sm:text-base text-gray-600 mt-2">Ini adalah tampilan profil kamu yang dilihat oleh mitra industri</p>
            </div>
            <div class="flex gap-2 sm:gap-3 w-full sm:w-auto">
                <a href="index.php" class="flex-1 sm:flex-none bg-[#E0F7FF] text-[#2A8FA9] px-4 sm:px-6 py-2 sm:py-3 rounded-xl text-sm sm:text-base font-semibold hover:bg-[#51A3B9] hover:text-white transition-colors duration-300 border border-[#51A3B9] border-opacity-30 flex items-center justify-center gap-2">
                    <span class="iconify" data-icon="mdi:arrow-left" data-width="18"></span>
                    <span class="hidden sm:inline">Kembali ke Dashboard</span>
                    <span class="sm:hidden">Kembali</span>
                </a>
            </div>
        </div>
        
        <!-- Info Box -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 sm:p-4 mb-4 sm:mb-6">
            <div class="flex items-start gap-2 sm:gap-3">
                <span class="iconify text-blue-600 mt-0.5 flex-shrink-0" data-icon="mdi:information" data-width="20"></span>
                <div>
                    <h3 class="font-semibold text-blue-900 text-sm sm:text-base">Tips Profil Menarik</h3>
                    <p class="text-blue-700 text-xs sm:text-sm mt-1">
                        Pastikan foto profil profesional, deskripsi diri lengkap, dan project terbaru sudah diupload 
                        untuk meningkatkan peluang dilirik oleh mitra industri.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 sm:gap-8">
        <!-- Sidebar - Profile Info -->
        <div class="lg:col-span-1 order-1 lg:order-1">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 sm:p-6 lg:sticky lg:top-8">
                <!-- Profile Photo & Basic Info -->
                <div class="text-center mb-4 sm:mb-6">
                    <?php if (!empty($student['profile_picture'])): ?>
                        <img class="h-24 w-24 sm:h-32 sm:w-32 rounded-full object-cover border-4 border-[#E0F7FF] mx-auto mb-3 sm:mb-4 shadow-lg" 
                            src="<?php echo htmlspecialchars($student['profile_picture']); ?>" 
                            alt="<?php echo htmlspecialchars($student['name']); ?>">
                    <?php else: ?>
                        <div class="h-24 w-24 sm:h-32 sm:w-32 rounded-full bg-gradient-to-br from-[#409BB2] to-[#2A8FA9] flex items-center justify-center border-4 border-[#E0F7FF] mx-auto mb-3 sm:mb-4 shadow-lg">
                            <span class="iconify text-white" data-icon="mdi:account" data-width="36" data-height="36" class="sm:w-12 sm:h-12"></span>
                        </div>
                    <?php endif; ?>
                    
                    <h1 class="text-xl sm:text-2xl font-bold text-[#2A8FA9] mb-2 break-words"><?php echo htmlspecialchars($student['name']); ?></h1>
                    
                    <?php if (!empty($student['major'])): ?>
                        <p class="text-sm sm:text-base text-gray-600 font-medium mb-1 break-words"><?php echo htmlspecialchars($student['major']); ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($student['semester'])): ?>
                        <p class="text-gray-500 text-xs sm:text-sm mb-3 sm:mb-4">Semester <?php echo htmlspecialchars($student['semester']); ?></p>
                    <?php endif; ?>
                    
                    <!-- Specializations -->
                    <?php if (!empty($student['specializations'])): ?>
                        <div class="flex flex-wrap gap-1.5 sm:gap-2 justify-center mb-4 sm:mb-6 mt-2">
                            <?php 
                            $specs = explode(',', $student['specializations']);
                            foreach ($specs as $spec):
                                $spec = trim($spec);
                                if (!empty($spec)):
                            ?>
                                <span class="bg-[#E0F7FF] text-[#2A8FA9] px-2 sm:px-3 py-1 sm:py-1.5 rounded-full text-xs font-medium my-0.5">
                                    <?php echo htmlspecialchars($spec); ?>
                                </span>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Action Buttons -->
                <div class="space-y-2 sm:space-y-3 mb-4 sm:mb-6">
                    <?php if (!empty($student['cv_file_path'])): ?>
                        <a href="<?php echo htmlspecialchars($student['cv_file_path']); ?>" 
                        target="_blank"
                        class="w-full bg-[#2A8FA9] text-white py-2.5 sm:py-3 px-4 rounded-xl text-sm sm:text-base font-bold hover:bg-[#409BB2] transition-colors duration-300 flex items-center justify-center gap-2 shadow-sm">
                            <span class="iconify" data-icon="mdi:file-eye" data-width="18"></span>
                            Lihat CV
                        </a>
                    <?php else: ?>
                        <div class="w-full bg-gray-100 text-gray-500 py-2.5 sm:py-3 px-4 rounded-xl text-sm sm:text-base font-bold flex items-center justify-center gap-2 shadow-sm cursor-not-allowed">
                            <span class="iconify" data-icon="mdi:file-remove" data-width="18"></span>
                            CV Belum Tersedia
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($student['linkedin'])): ?>
                        <a href="<?php echo htmlspecialchars($student['linkedin']); ?>" 
                            target="_blank"
                            class="w-full bg-blue-600 text-white py-2.5 sm:py-3 px-4 rounded-xl text-sm sm:text-base font-bold hover:bg-blue-700 transition-colors duration-300 flex items-center justify-center gap-2 shadow-sm">
                            <span class="iconify" data-icon="mdi:linkedin" data-width="18"></span>
                            LinkedIn Profile
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Contact Info -->
                <div class="border-t border-gray-200 pt-4 sm:pt-6">
                    <h3 class="text-base sm:text-lg font-semibold text-[#2A8FA9] mb-3 sm:mb-4 flex items-center gap-2">
                        <span class="iconify" data-icon="mdi:contact-mail" data-width="20"></span>
                        Kontak
                    </h3>
                    
                    <div class="space-y-2 sm:space-y-3">
                        <!-- Email -->
                        <div class="flex items-center gap-2 sm:gap-3 p-2.5 sm:p-3 bg-gray-50 rounded-lg">
                            <div class="w-8 h-8 sm:w-10 sm:h-10 bg-[#E0F7FF] rounded-full flex items-center justify-center flex-shrink-0">
                                <span class="iconify text-[#2A8FA9]" data-icon="mdi:email" data-width="16" data-height="16" class="sm:w-[18px] sm:h-[18px]"></span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs sm:text-sm text-gray-600">Email</p>
                                <a href="mailto:<?php echo htmlspecialchars($student['email']); ?>" 
                                    class="text-[#2A8FA9] hover:text-[#409BB2] font-medium text-xs sm:text-sm truncate block">
                                    <?php echo htmlspecialchars($student['email']); ?>
                                </a>
                            </div>
                        </div>
                        
                        <!-- Phone -->
                        <?php if (!empty($student['phone'])): ?>
                        <div class="flex items-center gap-2 sm:gap-3 p-2.5 sm:p-3 bg-gray-50 rounded-lg">
                            <div class="w-8 h-8 sm:w-10 sm:h-10 bg-[#E0F7FF] rounded-full flex items-center justify-center flex-shrink-0">
                                <span class="iconify text-[#2A8FA9]" data-icon="mdi:phone" data-width="16" data-height="16" class="sm:w-[18px] sm:h-[18px]"></span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs sm:text-sm text-gray-600">Nomor Telepon</p>
                                <p class="text-gray-900 font-medium text-xs sm:text-sm break-all"><?php echo htmlspecialchars($student['phone']); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Work Preferences -->
                <?php if (!empty($work_preferences)): ?>
                <div class="border-t border-gray-200 pt-4 sm:pt-6 mt-4">
                    <h3 class="text-base sm:text-lg font-semibold text-[#2A8FA9] mb-3 sm:mb-4 flex items-center gap-2">
                        <span class="iconify" data-icon="mdi:briefcase-check" data-width="20"></span>
                        Preferensi Kerja
                    </h3>
                    
                    <div class="flex flex-wrap gap-1.5 sm:gap-2">
                        <?php foreach ($work_preferences as $pref): ?>
                            <span class="bg-green-100 text-green-800 px-2 sm:px-3 py-1 sm:py-1.5 rounded-full text-xs font-medium border border-green-200">
                                <?php echo htmlspecialchars($pref); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Skills Summary -->
                <div class="border-t border-gray-200 pt-4 sm:pt-6 mt-4">
                    <h3 class="text-base sm:text-lg font-semibold text-[#2A8FA9] mb-3 sm:mb-4 flex items-center gap-2">
                        <span class="iconify" data-icon="mdi:code-braces" data-width="20"></span>
                        Ringkasan Skills
                    </h3>
                    
                    <div class="space-y-3 sm:space-y-4">
                        <!-- Technical Skills -->
                        <?php if (!empty($all_skills['technical'])): ?>
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-2 flex items-center gap-2 text-xs sm:text-sm">
                                <span class="iconify text-blue-600" data-icon="mdi:cog" data-width="14" data-height="14" class="sm:w-4 sm:h-4"></span>
                                Technical Skills
                                <span class="bg-blue-100 text-blue-800 text-xs px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full">
                                    <?php echo count($all_skills['technical']); ?>
                                </span>
                            </h4>
                            <div class="flex flex-wrap gap-1">
                                <?php foreach ($all_skills['technical'] as $skill): ?>
                                    <span class="bg-blue-100 text-blue-800 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded text-xs" 
                                        title="Digunakan di <?php echo $skill['project_count']; ?> project">
                                        <?php echo htmlspecialchars($skill['name']); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Soft Skills -->
                        <?php if (!empty($all_skills['soft'])): ?>
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-2 flex items-center gap-2 text-xs sm:text-sm">
                                <span class="iconify text-green-600" data-icon="mdi:account-group" data-width="14" data-height="14" class="sm:w-4 sm:h-4"></span>
                                Soft Skills
                                <span class="bg-green-100 text-green-800 text-xs px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full">
                                    <?php echo count($all_skills['soft']); ?>
                                </span>
                            </h4>
                            <div class="flex flex-wrap gap-1">
                                <?php foreach ($all_skills['soft'] as $skill): ?>
                                    <span class="bg-green-100 text-green-800 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded text-xs"
                                        title="Digunakan di <?php echo $skill['project_count']; ?> project">
                                        <?php echo htmlspecialchars($skill['name']); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Tools -->
                        <?php if (!empty($all_skills['tool'])): ?>
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-2 flex items-center gap-2 text-xs sm:text-sm">
                                <span class="iconify text-purple-600" data-icon="mdi:tools" data-width="14" data-height="14" class="sm:w-4 sm:h-4"></span>
                                Tools
                                <span class="bg-purple-100 text-purple-800 text-xs px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-full">
                                    <?php echo count($all_skills['tool']); ?>
                                </span>
                            </h4>
                            <div class="flex flex-wrap gap-1">
                                <?php foreach ($all_skills['tool'] as $skill): ?>
                                    <span class="bg-purple-100 text-purple-800 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded text-xs"
                                        title="Digunakan di <?php echo $skill['project_count']; ?> project">
                                        <?php echo htmlspecialchars($skill['name']); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-3 order-2 lg:order-2">
            <!-- Bio Section -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 sm:p-6 mb-6 sm:mb-8">
                <h2 class="text-xl sm:text-2xl font-bold text-[#2A8FA9] mb-3 sm:mb-4 flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:account-circle" data-width="22" data-height="22" class="sm:w-6 sm:h-6"></span>
                    Tentang Saya
                </h2>
                
                <?php if (!empty($student['bio'])): ?>
                    <div class="prose max-w-none text-gray-700 leading-relaxed text-sm sm:text-base">
                        <?php echo nl2br(htmlspecialchars($student['bio'])); ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-6 sm:py-8 bg-gray-50 rounded-xl">
                        <span class="iconify text-gray-400 mx-auto mb-2 sm:mb-3" data-icon="mdi:text-box-remove" data-width="40" data-height="40" class="sm:w-12 sm:h-12"></span>
                        <h3 class="text-base sm:text-lg font-bold text-gray-600 mb-2">Deskripsi Belum Tersedia</h3>
                        <p class="text-gray-500 text-xs sm:text-sm px-4">Tambahkan deskripsi diri untuk membuat profil lebih menarik</p>
                        <a href="profile.php" class="inline-block mt-3 bg-[#51A3B9] text-white px-4 sm:px-6 py-2 rounded-lg font-semibold hover:bg-[#409BB2] transition-colors text-xs sm:text-sm">
                            Edit Profil
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Projects Portfolio -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 sm:gap-0 mb-4 sm:mb-6">
                    <h2 class="text-xl sm:text-2xl font-bold text-[#2A8FA9] flex items-center gap-2">
                        <span class="iconify" data-icon="mdi:folder-multiple" data-width="22" data-height="22" class="sm:w-6 sm:h-6"></span>
                        Portofolio Project
                    </h2>
                    <span class="bg-[#E0F7FF] text-[#2A8FA9] px-2.5 sm:px-3 py-1 rounded-full text-xs sm:text-sm font-semibold">
                        <?php echo $total_projects; ?> Project Ditampilkan
                    </span>
                </div>

                <?php if ($total_projects > 0): ?>
                    <div class="space-y-4 sm:space-y-6">
                        <?php foreach ($projects as $project): ?>
                            <div class="border border-gray-200 rounded-xl overflow-hidden hover:shadow-lg transition-all duration-300 project-card">
                                <!-- Project Header -->
                                <div class="bg-gradient-to-r from-gray-50 to-gray-100 p-4 sm:p-6 border-b border-gray-200">
                                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3 sm:gap-4">
                                        <div class="flex-1">
                                            <h3 class="text-lg sm:text-xl font-bold text-[#2A8FA9] mb-2 break-words"><?php echo htmlspecialchars($project['title']); ?></h3>
                                            
                                            <div class="flex flex-wrap gap-2 sm:gap-4 text-xs sm:text-sm text-gray-600 mb-3">
                                                <?php if (!empty($project['project_year'])): ?>
                                                    <span class="flex items-center gap-1">
                                                        <span class="iconify text-[#51A3B9]" data-icon="mdi:calendar" data-width="12" data-height="12" class="sm:w-[14px] sm:h-[14px]"></span>
                                                        <?php echo htmlspecialchars($project['project_year']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($project['project_type'])): ?>
                                                    <span class="flex items-center gap-1">
                                                        <span class="iconify text-[#409BB2]" data-icon="mdi:tag" data-width="12" data-height="12" class="sm:w-[14px] sm:h-[14px]"></span>
                                                        <?php echo htmlspecialchars($project['project_type']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($project['category'])): ?>
                                                    <span class="flex items-center gap-1">
                                                        <span class="iconify text-[#489EB7]" data-icon="mdi:folder" data-width="12" data-height="12" class="sm:w-[14px] sm:h-[14px]"></span>
                                                        <?php echo htmlspecialchars($project['category']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Project Links -->
                                        <?php if (!empty($project['github_url']) || !empty($project['demo_url']) || !empty($project['figma_url'])): ?>
                                        <div class="flex flex-wrap gap-2">
                                            <?php if (!empty($project['github_url'])): ?>
                                                <a href="<?php echo htmlspecialchars($project['github_url']); ?>" 
                                                    target="_blank"
                                                    class="bg-gray-800 text-white px-2.5 sm:px-3 py-1.5 sm:py-2 rounded-lg text-xs sm:text-sm font-semibold hover:bg-gray-900 transition-colors flex items-center gap-1">
                                                    <span class="iconify" data-icon="mdi:github" data-width="12" data-height="12" class="sm:w-[14px] sm:h-[14px]"></span>
                                                    Code
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($project['demo_url'])): ?>
                                                <a href="<?php echo htmlspecialchars($project['demo_url']); ?>" 
                                                    target="_blank"
                                                    class="bg-cyan-500 text-white px-2.5 sm:px-3 py-1.5 sm:py-2 rounded-lg text-xs sm:text-sm font-semibold hover:bg-cyan-600 transition-colors flex items-center gap-1">
                                                    <span class="iconify" data-icon="mdi:play" data-width="12" data-height="12" class="sm:w-[14px] sm:h-[14px]"></span>
                                                    Link Project
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($project['figma_url'])): ?>
                                                <a href="<?php echo htmlspecialchars($project['figma_url']); ?>" 
                                                    target="_blank"
                                                    class="bg-purple-500 text-white px-2.5 sm:px-3 py-1.5 sm:py-2 rounded-lg text-xs sm:text-sm font-semibold hover:bg-purple-600 transition-colors flex items-center gap-1">
                                                    <span class="iconify" data-icon="mdi:palette" data-width="12" data-height="12" class="sm:w-[14px] sm:h-[14px]"></span>
                                                    Design
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Project Content -->
                                <div class="p-4 sm:p-6">
                                    <!-- Project Description -->
                                    <?php if (!empty($project['description'])): ?>
                                        <div class="mb-4">
                                            <h4 class="font-semibold text-gray-800 mb-2 text-sm sm:text-base">Deskripsi Project</h4>
                                            <p class="text-gray-700 leading-relaxed text-sm sm:text-base"><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Project Skills -->
                                    <?php if (!empty($project['skills_detail'])): ?>
                                        <div class="mb-4">
                                            <h4 class="font-semibold text-gray-800 mb-2 text-sm sm:text-base">Teknologi & Tools</h4>
                                            <div class="flex flex-wrap gap-1.5 sm:gap-2">
                                                <?php foreach ($project['skills_detail'] as $skill): 
                                                    $color_class = [
                                                        'technical' => 'bg-blue-100 text-blue-800 border border-blue-200',
                                                        'soft' => 'bg-green-100 text-green-800 border border-green-200',
                                                        'tool' => 'bg-purple-100 text-purple-800 border border-purple-200'
                                                    ][$skill['skill_type']] ?? 'bg-gray-100 text-gray-800 border border-gray-200';
                                                ?>
                                                    <span class="inline-block <?php echo $color_class; ?> px-2 sm:px-3 py-0.5 sm:py-1 rounded-full text-xs font-medium">
                                                        <?php echo htmlspecialchars($skill['name']); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Project Images Gallery -->
                                    <?php 
                                    $all_project_images = [];
                                    if (!empty($project['image_path'])) {
                                        $all_project_images[] = $project['image_path'];
                                    }
                                    if (!empty($project['gallery_images'])) {
                                        $gallery_images = explode('|||', $project['gallery_images']);
                                        $all_project_images = array_merge($all_project_images, $gallery_images);
                                    }
                                    $all_project_images = array_unique(array_filter($all_project_images)); 
                                    ?>

                                    <?php if (!empty($all_project_images)): ?>
                                        <div class="mb-4">
                                            <h4 class="font-semibold text-gray-800 mb-2 sm:mb-3 text-sm sm:text-base">Galeri Project</h4>
                                            <div class="relative">
                                                <!-- Gallery Container -->
                                                <div class="bg-gray-50 rounded-lg p-3 sm:p-4 border border-gray-200">
                                                    <!-- Main Image Display -->
                                                    <div class="mb-3 sm:mb-4 flex justify-center">
                                                        <img id="mainImage-<?php echo $project['id']; ?>" 
                                                            src="<?php echo htmlspecialchars($all_project_images[0]); ?>" 
                                                            alt="Project Image" 
                                                            class="max-w-full max-h-60 sm:max-h-80 object-contain rounded-lg border border-gray-300 cursor-pointer"
                                                            onclick="openImageModal('<?php echo htmlspecialchars($all_project_images[0]); ?>')">
                                                    </div>
                                                    
                                                    <!-- Navigation & Thumbnails -->
                                                    <?php if (count($all_project_images) > 1): ?>
                                                        <div class="flex items-center justify-center gap-2 sm:gap-4">
                                                            <!-- Left Arrow -->
                                                            <button onclick="prevImage(<?php echo $project['id']; ?>, <?php echo count($all_project_images); ?>)"
                                                                    class="bg-white border border-gray-300 rounded-full p-1.5 sm:p-2 hover:bg-gray-100 transition-colors shadow-sm flex-shrink-0">
                                                                <span class="iconify text-gray-600" data-icon="mdi:chevron-left" data-width="16" data-height="16" class="sm:w-5 sm:h-5"></span>
                                                            </button>
                                                            
                                                            <!-- Thumbnails -->
                                                            <div class="flex gap-1.5 sm:gap-2 overflow-x-auto py-2 px-2 sm:px-4 justify-start sm:justify-center flex-1 max-w-full sm:max-w-2xl scrollbar-thin">
                                                                <?php foreach ($all_project_images as $index => $image): ?>
                                                                    <img src="<?php echo htmlspecialchars($image); ?>" 
                                                                        alt="Thumbnail <?php echo $index + 1; ?>" 
                                                                        class="w-12 h-12 sm:w-16 sm:h-16 object-cover rounded border-2 cursor-pointer transition-all flex-shrink-0 <?php echo $index === 0 ? 'border-[#2A8FA9]' : 'border-gray-300'; ?>"
                                                                        onclick="changeMainImage(<?php echo $project['id']; ?>, '<?php echo htmlspecialchars($image); ?>', <?php echo $index; ?>)"
                                                                        data-project-id="<?php echo $project['id']; ?>"
                                                                        data-image-index="<?php echo $index; ?>">
                                                                <?php endforeach; ?>
                                                            </div>
                                                            
                                                            <!-- Right Arrow -->
                                                            <button onclick="nextImage(<?php echo $project['id']; ?>, <?php echo count($all_project_images); ?>)"
                                                                    class="bg-white border border-gray-300 rounded-full p-1.5 sm:p-2 hover:bg-gray-100 transition-colors shadow-sm flex-shrink-0">
                                                                <span class="iconify text-gray-600" data-icon="mdi:chevron-right" data-width="16" data-height="16" class="sm:w-5 sm:h-5"></span>
                                                            </button>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Project Video -->
                                    <?php if (!empty($project['video_url'])): ?>
                                        <div class="mb-4">
                                            <h4 class="font-semibold text-gray-800 mb-2 text-sm sm:text-base">Video Demo</h4>
                                            <div class="border border-gray-200 rounded-lg overflow-hidden">
                                                <video controls class="w-full max-w-full sm:max-w-2xl mx-auto" poster="<?php echo !empty($project['image_path']) ? htmlspecialchars(explode(',', $project['image_path'])[0]) : ''; ?>">
                                                    <source src="<?php echo htmlspecialchars($project['video_url']); ?>" type="video/mp4">
                                                    Browser Anda tidak mendukung tag video.
                                                </video>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Project Details -->
                                    <div class="flex flex-wrap justify-between items-center gap-3 sm:gap-4 text-xs sm:text-sm">
                                        <?php if (!empty($project['project_duration'])): ?>
                                            <div class="flex items-center gap-1.5 sm:gap-2">
                                                <span class="iconify text-gray-400" data-icon="mdi:clock-outline" data-width="14" data-height="14" class="sm:w-4 sm:h-4"></span>
                                                <span class="text-gray-600"><?php echo htmlspecialchars($project['project_duration']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($project['status'])): ?>
                                        <div> <span class="inline-flex items-center gap-1 bg-green-100 text-green-800 px-2 sm:px-3 py-1 rounded-full text-xs font-medium">
                                                <span class="iconify" data-icon="mdi:check-circle" data-width="10" data-height="10" class="sm:w-3 sm:h-3"></span>
                                                Status: <?php echo htmlspecialchars($project['status']); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Tombol Lihat Semua Project -->
                    <div class="text-center mt-6 sm:mt-8 pt-4 sm:pt-6 border-t border-gray-200">
                        <a href="projects.php" 
                            class="bg-[#E0F7FF] text-[#2A8FA9] px-6 sm:px-8 py-2.5 sm:py-3 rounded-xl text-sm sm:text-base font-bold hover:bg-[#51A3B9] hover:text-white transition-colors duration-300 border border-[#51A3B9] border-opacity-30 inline-flex items-center gap-2">
                            <span class="iconify" data-icon="mdi:folder-open" data-width="18" data-height="18" class="sm:w-5 sm:h-5"></span>
                            Kelola Semua Project
                        </a>
                    </div>

                <?php else: ?>
                    <div class="text-center py-8 sm:py-12 bg-gray-50 rounded-xl px-4">
                        <span class="iconify text-gray-400 mx-auto mb-3 sm:mb-4" data-icon="mdi:folder-open" data-width="48" data-height="48" class="sm:w-16 sm:h-16"></span>
                        <h3 class="text-lg sm:text-xl font-bold text-blue-900 mb-2">Belum Ada Project</h3>
                        <p class="text-gray-600 max-w-md mx-auto text-sm sm:text-base">Tambahkan project untuk membangun portofolio yang menarik bagi mitra industri.</p>
                        <a href="add-project.php" class="inline-block mt-4 bg-[#51A3B9] text-white px-5 sm:px-6 py-2.5 sm:py-3 rounded-xl text-sm sm:text-base font-bold hover:bg-[#409BB2] transition-colors duration-300 inline-flex items-center gap-2">
                            <span class="iconify" data-icon="mdi:rocket-launch" data-width="18" data-height="18" class="sm:w-5 sm:h-5"></span>
                            Tambah Project Pertama
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Certificates Section -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 sm:p-6 mt-6 sm:mt-8">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 sm:gap-0 mb-4 sm:mb-6">
                    <h2 class="text-xl sm:text-2xl font-bold text-[#2A8FA9] flex items-center gap-2">
                        <span class="iconify" data-icon="mdi:certificate" data-width="22" data-height="22" class="sm:w-6 sm:h-6"></span>
                        Sertifikat & Sertifikasi
                    </h2>
                    <span class="bg-[#E0F7FF] text-[#2A8FA9] px-2.5 sm:px-3 py-1 rounded-full text-xs sm:text-sm font-semibold">
                        <?php echo count($certificates); ?> Sertifikat
                    </span>
                </div>

                <?php if (!empty($certificates)): ?>
                    <div class="relative">
                        <!-- Certificates Horizontal Scroll -->
                        <div class="flex gap-3 sm:gap-4 overflow-x-auto pb-4 scrollbar-hide" id="certificates-scroll">
                            <?php foreach ($certificates as $cert): ?>
                                <div class="flex-shrink-0 w-72 sm:w-80 bg-gradient-to-br from-amber-50 to-amber-100 border border-amber-200 rounded-xl p-3 sm:p-4 hover:shadow-lg transition-all duration-300 group relative certificate-card flex flex-col">
                                    
                                    <!-- Compact Badge -->
                                    <div class="absolute top-2 sm:top-3 right-2 sm:right-3">
                                        <?php if ($cert['source_type'] == 'project'): ?>
                                            <div class="relative group/badge">
                                                <span class="inline-flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 bg-green-100 text-green-800 rounded-full text-xs font-medium transition-colors group-hover/badge:bg-green-200">
                                                    <span class="iconify" data-icon="mdi:folder" data-width="12" data-height="12" class="sm:w-[14px] sm:h-[14px]"></span>
                                                </span>
                                                <div class="absolute top-full mt-2 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs py-1 px-2 rounded opacity-0 group-hover/badge:opacity-100 transition-opacity duration-200 whitespace-nowrap pointer-events-none z-10">
                                                    Dari Project
                                                    <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-b-gray-800"></div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="relative group/badge">
                                                <span class="inline-flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 bg-blue-100 text-blue-800 rounded-full text-xs font-medium transition-colors group-hover/badge:bg-blue-200">
                                                    <span class="iconify" data-icon="mdi:star" data-width="12" data-height="12" class="sm:w-[14px] sm:h-[14px]"></span>
                                                </span>
                                                <div class="absolute top-full mt-2 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs py-1 px-2 rounded opacity-0 group-hover/badge:opacity-100 transition-opacity duration-200 whitespace-nowrap pointer-events-none z-10">
                                                    Standalone
                                                    <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-b-gray-800"></div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Certificate Header -->
                                    <div class="flex items-start gap-2 sm:gap-3 mb-2 sm:mb-3">
                                        <div class="w-10 h-10 sm:w-12 sm:h-12 bg-amber-500 rounded-xl flex items-center justify-center flex-shrink-0">
                                            <span class="iconify text-white" data-icon="mdi:certificate" data-width="20" data-height="20" class="sm:w-6 sm:h-6"></span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <h3 class="font-bold text-gray-800 group-hover:text-amber-600 transition-colors line-clamp-2 text-xs sm:text-sm leading-tight">
                                                <?php echo htmlspecialchars($cert['certificate_name']); ?>
                                            </h3>
                                            <p class="text-xs text-gray-600 mt-1 flex items-center gap-1">
                                                <span class="iconify" data-icon="mdi:office-building" data-width="10" data-height="10" class="sm:w-3 sm:h-3"></span>
                                                <span class="truncate"><?php echo htmlspecialchars($cert['issuing_organization']); ?></span>
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Certificate Details -->
                                    <div class="space-y-1.5 sm:space-y-2 text-xs text-gray-600 mb-3 sm:mb-4">
                                        <div class="flex items-center gap-1.5 sm:gap-2">
                                            <span class="iconify flex-shrink-0" data-icon="mdi:calendar" data-width="10" data-height="10" class="sm:w-3 sm:h-3"></span>
                                            <span class="truncate">Diterbitkan: <?php echo !empty($cert['issue_date']) ? date('M Y', strtotime($cert['issue_date'])) : '-'; ?></span>
                                        </div>
                                        
                                        <div class="flex items-center gap-1.5 sm:gap-2 <?php echo (!empty($cert['expiry_date']) && strtotime($cert['expiry_date']) < time()) ? 'text-red-600' : ''; ?>">
                                            <span class="iconify flex-shrink-0" data-icon="mdi:clock" data-width="10" data-height="10" class="sm:w-3 sm:h-3"></span>
                                            <span class="truncate">Berlaku hingga: <?php echo !empty($cert['expiry_date']) ? date('M Y', strtotime($cert['expiry_date'])) : '-'; ?></span>
                                        </div>
                                        
                                        <div class="flex items-center gap-1.5 sm:gap-2">
                                            <span class="iconify flex-shrink-0" data-icon="mdi:identifier" data-width="10" data-height="10" class="sm:w-3 sm:h-3"></span>
                                            <span class="truncate">ID: <?php echo !empty($cert['credential_id']) ? htmlspecialchars($cert['credential_id']) : '-'; ?></span>
                                        </div>
                                    </div>

                                    <!-- Certificate Description -->
                                    <?php if (!empty($cert['description'])): ?>
                                        <div class="mb-3 sm:mb-4">
                                            <h4 class="font-semibold text-gray-700 text-xs mb-1">Deskripsi:</h4>
                                            <p class="text-xs text-gray-600 line-clamp-3">
                                                <?php echo htmlspecialchars($cert['description']); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Action Buttons -->
                                    <div class="mt-auto pt-3 sm:pt-4">
                                        <div class="flex gap-2">
                                            <?php if (!empty($cert['image_path'])): ?>
                                                <?php
                                                $file_extension = strtolower(pathinfo($cert['image_path'], PATHINFO_EXTENSION));
                                                $is_pdf = $file_extension === 'pdf';
                                                ?>
                                                
                                                <?php if ($is_pdf): ?>
                                                    <!-- Untuk PDF -->
                                                    <a href="<?php echo htmlspecialchars($cert['image_path']); ?>" 
                                                    target="_blank"
                                                    class="flex-1 bg-amber-500 text-white py-1.5 sm:py-2 px-2.5 sm:px-3 rounded-lg text-xs font-semibold hover:bg-amber-600 transition-colors flex items-center justify-center gap-1">
                                                        <span class="iconify" data-icon="mdi:eye" data-width="12" data-height="12" class="sm:w-[14px] sm:h-[14px]"></span>
                                                        Lihat
                                                    </a>
                                                <?php else: ?>
                                                    <!-- Untuk images -->
                                                    <button onclick="openImageModal('<?php echo htmlspecialchars($cert['image_path']); ?>')"
                                                            class="flex-1 bg-amber-500 text-white py-1.5 sm:py-2 px-2.5 sm:px-3 rounded-lg text-xs font-semibold hover:bg-amber-600 transition-colors flex items-center justify-center gap-1">
                                                        <span class="iconify" data-icon="mdi:eye" data-width="12" data-height="12" class="sm:w-[14px] sm:h-[14px]"></span>
                                                        Lihat
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($cert['credential_url'])): ?>
                                                <a href="<?php echo htmlspecialchars($cert['credential_url']); ?>" 
                                                target="_blank"
                                                class="flex-1 bg-white text-amber-700 py-1.5 sm:py-2 px-2.5 sm:px-3 rounded-lg text-xs font-semibold hover:bg-amber-50 transition-colors flex items-center justify-center gap-1 border border-amber-300">
                                                    <span class="iconify" data-icon="mdi:shield-check" data-width="10" data-height="10" class="sm:w-3 sm:h-3"></span>
                                                    Verify
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (count($certificates) > 2): ?>
                        <button onclick="scrollCertificates('left')" 
                                class="absolute left-0 top-1/2 transform -translate-y-1/2 -translate-x-4 bg-white/70 border border-gray-300/50 rounded-full p-2 shadow-lg hover:bg-white hover:border-gray-300 transition-all duration-300 z-10 hidden md:flex items-center justify-center backdrop-blur-sm">
                            <span class="iconify text-gray-600/70 hover:text-gray-600 transition-colors" data-icon="mdi:chevron-left" data-width="20"></span>
                        </button>
                        <button onclick="scrollCertificates('right')" 
                                class="absolute right-0 top-1/2 transform -translate-y-1/2 translate-x-4 bg-white/70 border border-gray-300/50 rounded-full p-2 shadow-lg hover:bg-white hover:border-gray-300 transition-all duration-300 z-10 hidden md:flex items-center justify-center backdrop-blur-sm">
                            <span class="iconify text-gray-600/70 hover:text-gray-600 transition-colors" data-icon="mdi:chevron-right" data-width="20"></span>
                        </button>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (count($certificates) > 2): ?>
                    <div class="flex justify-center mt-4 md:hidden">
                        <div class="flex gap-1">
                            <div class="w-2 h-2 bg-amber-400 rounded-full"></div>
                            <div class="w-2 h-2 bg-amber-200 rounded-full"></div>
                            <div class="w-2 h-2 bg-amber-200 rounded-full"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="text-center py-6 sm:py-8 bg-gray-50 rounded-xl px-4">
                        <span class="iconify text-gray-400 mx-auto mb-2 sm:mb-3" data-icon="mdi:certificate-outline" data-width="40" data-height="40" class="sm:w-12 sm:h-12"></span>
                        <h3 class="text-base sm:text-lg font-bold text-blue-900 mb-2">Belum Ada Sertifikat</h3>
                        <p class="text-gray-600 text-xs sm:text-sm">Tambahkan sertifikat untuk meningkatkan kredibilitas profil kamu di mata mitra industri.</p>
                        <a href="certificates.php" class="inline-block mt-3 sm:mt-4 bg-[#51A3B9] text-white px-5 sm:px-6 py-2 rounded-lg font-semibold hover:bg-[#409BB2] transition-colors text-xs sm:text-sm">
                            Tambah Sertifikat
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div id="imageModal" class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-50 hidden p-4">
    <div class="relative max-w-4xl max-h-full w-full">
        <button onclick="closeImageModal()" 
                class="absolute -top-10 sm:-top-12 right-0 text-white hover:text-gray-300 transition-colors">
            <span class="iconify" data-icon="mdi:close" data-width="24" data-height="24" class="sm:w-8 sm:h-8"></span>
        </button>
        <img id="modalImage" src="" alt="Full size image" class="max-w-full max-h-[calc(100vh-100px)] object-contain rounded-lg mx-auto">
    </div>
</div>

<script>
let currentImageIndex = {};

function changeMainImage(projectId, imageSrc, index) {
    document.getElementById('mainImage-' + projectId).src = imageSrc;
    currentImageIndex[projectId] = index;
    updateThumbnailBorders(projectId, index);
}

function prevImage(projectId, totalImages) {
    let currentIndex = currentImageIndex[projectId] || 0;
    let newIndex = (currentIndex - 1 + totalImages) % totalImages;
    const thumbnails = document.querySelectorAll(`img[data-project-id="${projectId}"]`);
    if (thumbnails[newIndex]) {
        changeMainImage(projectId, thumbnails[newIndex].src, newIndex);
    }
}

function nextImage(projectId, totalImages) {
    let currentIndex = currentImageIndex[projectId] || 0;
    let newIndex = (currentIndex + 1) % totalImages;
    const thumbnails = document.querySelectorAll(`img[data-project-id="${projectId}"]`);
    if (thumbnails[newIndex]) {
        changeMainImage(projectId, thumbnails[newIndex].src, newIndex);
    }
}

function updateThumbnailBorders(projectId, activeIndex) {
    const thumbnails = document.querySelectorAll(`img[data-project-id="${projectId}"]`);
    thumbnails.forEach((thumb, index) => {
        if (index === activeIndex) {
            thumb.classList.remove('border-gray-300');
            thumb.classList.add('border-[#2A8FA9]');
        } else {
            thumb.classList.remove('border-[#2A8FA9]');
            thumb.classList.add('border-gray-300');
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const projects = document.querySelectorAll('[id^="mainImage-"]');
    projects.forEach(mainImage => {
        const projectId = mainImage.id.split('-')[1];
        currentImageIndex[projectId] = 0;
        updateThumbnailBorders(projectId, 0);
    });
});

function openImageModal(imageSrc) {
    document.getElementById('modalImage').src = imageSrc;
    document.getElementById('imageModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeImageModal() {
    document.getElementById('imageModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeImageModal();
    }
});

document.getElementById('imageModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeImageModal();
    }
});

function scrollCertificates(direction) {
    const container = document.getElementById('certificates-scroll');
    if (!container) return;
    const scrollAmount = 320;
    const currentScroll = container.scrollLeft;
    
    if (direction === 'left') {
        container.scrollTo({
            left: currentScroll - scrollAmount,
            behavior: 'smooth'
        });
    } else if (direction === 'right') {
        container.scrollTo({
            left: currentScroll + scrollAmount,
            behavior: 'smooth'
        });
    }
}
</script>
<?php include '../../includes/footer.php'; ?>