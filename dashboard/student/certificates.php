<?php
include '../../includes/config.php';
include '../../includes/functions.php';

if (!isLoggedIn() || getUserRole() != 'student') {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get certificates from projects
$project_certs_stmt = $conn->prepare("
    SELECT 
        p.id as project_id,
        p.title as project_title,
        p.certificate_path as file_path,
        p.title as certificate_title,
        'Project Certificate' as organization,
        p.created_at as issue_date,
        NULL as expiry_date,
        NULL as certificate_url,
        p.description,
        p.created_at,
        'project' as source_type
    FROM projects p 
    WHERE p.student_id = ? AND p.certificate_path IS NOT NULL AND p.certificate_path != ''
");

if (!$project_certs_stmt) {
    die("Error preparing project certificates query: " . $conn->error);
}

$project_certs_stmt->bind_param("i", $user_id);
$project_certs_stmt->execute();
$project_certs_result = $project_certs_stmt->get_result();

$project_certificates = [];
while ($cert = $project_certs_result->fetch_assoc()) {
    // Pastikan data valid
    if (!empty($cert['certificate_title']) && $cert['certificate_title'] != '0' && !empty($cert['file_path'])) {
        $project_certificates[] = $cert;
    }
}

// Get standalone certificates - dengan error handling
$standalone_certs_stmt = $conn->prepare("
    SELECT 
        id,
        NULL as project_id,
        title as certificate_title,
        organization,
        issue_date,
        expiry_date,
        certificate_url,
        file_path,
        description,
        created_at,
        'standalone' as source_type
    FROM certificates 
    WHERE student_id = ?
");

if (!$standalone_certs_stmt) {
    $standalone_certificates = [];
} else {
    $standalone_certs_stmt->bind_param("i", $user_id);
    $standalone_certs_stmt->execute();
    $standalone_certs_result = $standalone_certs_stmt->get_result();

    $standalone_certificates = [];
    while ($cert = $standalone_certs_result->fetch_assoc()) {
        $standalone_certificates[] = $cert;
    }
}

// Merge all certificates
$all_certificates = array_merge($project_certificates, $standalone_certificates);

// Sort by issue date (newest first)
usort($all_certificates, function($a, $b) {
    return strtotime($b['issue_date']) - strtotime($a['issue_date']);
});

$total_certificates = count($all_certificates);
$from_projects = count($project_certificates);
$standalone = count($standalone_certificates);
?>

<?php include '../../includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header dengan tombol kembali -->
    <div class="flex justify-between items-center w-full mb-8">
        <div class="flex-1">
            <h1 class="text-3xl font-bold text-blue-900 flex items-center gap-3">
                <span class="iconify" data-icon="mdi:certificate" data-width="32"></span>
                Semua Sertifikat
            </h1>
            <p class="text-gray-600 mt-2">Kelola semua sertifikat dari proyek dan pencapaian lainnya</p>
        </div>
        <div class="flex items-center gap-4">
            <a href="index.php" 
                class="bg-blue-500/10 text-blue-700 px-6 py-3 rounded-xl font-semibold hover:bg-blue-500/20 transition-colors duration-300 border border-blue-200 flex items-center gap-2">
                <span class="iconify" data-icon="mdi:arrow-left" data-width="18"></span>
                Kembali
            </a>
            <a href="add-certificate.php" class="bg-blue-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-blue-700 transition-colors duration-300 flex items-center gap-2">
                <span class="iconify" data-icon="mdi:certificate-plus" data-width="20"></span>
                Tambah Sertifikat
            </a>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="bg-gradient-to-r from-blue-50 to-cyan-50 rounded-xl p-4 border border-blue-200">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center">
                    <span class="iconify text-white" data-icon="mdi:certificate" data-width="24"></span>
                </div>
                <div>
                    <div class="text-2xl font-bold text-blue-900"><?php echo $total_certificates; ?></div>
                    <div class="text-sm text-blue-700">Total Sertifikat</div>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-4 border border-blue-200">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center">
                    <span class="iconify text-white" data-icon="mdi:folder" data-width="24"></span>
                </div>
                <div>
                    <div class="text-2xl font-bold text-blue-900"><?php echo $from_projects; ?></div>
                    <div class="text-sm text-blue-700">Dari Proyek</div>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-r from-cyan-50 to-blue-50 rounded-xl p-4 border border-cyan-200">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-cyan-600 rounded-xl flex items-center justify-center">
                    <span class="iconify text-white" data-icon="mdi:star" data-width="24"></span>
                </div>
                <div>
                    <div class="text-2xl font-bold text-cyan-900"><?php echo $standalone; ?></div>
                    <div class="text-sm text-cyan-700">Standalone</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Certificates Grid -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <?php if (empty($all_certificates)): ?>
            <!-- Empty State -->
            <div class="text-center py-12">
                <div class="text-blue-500 mb-4 flex justify-center">
                    <span class="iconify" data-icon="mdi:certificate-off" data-width="80"></span>
                </div>
                <h3 class="text-blue-900 font-bold text-xl mb-2">Belum Ada Sertifikat</h3>
                <p class="text-gray-600 mb-6">Tambahkan sertifikat pertama Anda untuk melengkapi portofolio</p>
                <div class="flex gap-4 justify-center">
                    <a href="add-certificate.php" class="bg-blue-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-blue-700 transition-colors duration-300 inline-flex items-center gap-2">
                        <span class="iconify" data-icon="mdi:certificate-plus" data-width="20"></span>
                        Tambah Sertifikat
                    </a>
                    <a href="projects.php" class="bg-blue-500 text-white px-6 py-3 rounded-xl font-bold hover:bg-blue-600 transition-colors duration-300 inline-flex items-center gap-2">
                        <span class="iconify" data-icon="mdi:folder-open" data-width="20"></span>
                        Lihat Proyek
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 gap-6">
                <?php foreach ($all_certificates as $certificate): ?>
                <!-- Certificate Card - Horizontal dengan Blue Theme -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl border-2 border-blue-200 p-6 hover:shadow-lg transition-all duration-300 group hover:border-blue-300">
                    <div class="flex flex-col md:flex-row gap-6">
                        <!-- Certificate Thumbnail/Icon -->
                        <div class="flex-shrink-0">
                            <div class="w-20 h-20 bg-blue-600 rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                                <span class="iconify text-white" data-icon="mdi:certificate" data-width="40"></span>
                            </div>
                        </div>
                        
                        <!-- Certificate Content -->
                        <div class="flex-1">
                            <!-- Header dengan Source Badge -->
                            <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-3 mb-3">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <h3 class="font-bold text-blue-900 text-xl group-hover:text-blue-600 transition-colors line-clamp-1">
                                            <?php echo htmlspecialchars($certificate['certificate_title']); ?>
                                        </h3>
                                        <!-- Source Badge -->
                                        <?php if ($certificate['source_type'] == 'project'): ?>
                                        <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-semibold border border-green-200 flex items-center gap-1">
                                            <span class="iconify" data-icon="mdi:folder" data-width="12"></span>
                                            Dari Proyek
                                        </span>
                                        <?php else: ?>
                                        <span class="bg-cyan-100 text-cyan-800 px-3 py-1 rounded-full text-xs font-semibold border border-cyan-200 flex items-center gap-1">
                                            <span class="iconify" data-icon="mdi:star" data-width="12"></span>
                                            Standalone
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-gray-600 font-medium">
                                        <?php echo htmlspecialchars($certificate['organization']); ?>
                                    </p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <!-- Issue Date -->
                                    <span class="text-gray-500 text-sm bg-white px-4 py-2 rounded-lg border border-blue-200 whitespace-nowrap min-w-24 text-center">
                                        <span class="iconify inline-block mr-1" data-icon="mdi:calendar" data-width="14"></span>
                                        <?php echo date('M Y', strtotime($certificate['issue_date'])); ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Description -->
                            <?php if (!empty($certificate['description'])): ?>
                            <p class="text-gray-600 mb-4 line-clamp-2">
                                <?php echo htmlspecialchars($certificate['description']); ?>
                            </p>
                            <?php endif; ?>

                            <!-- Validity Period -->
                            <div class="flex items-center gap-4 text-sm text-gray-500 mb-4">
                                <span class="flex items-center gap-1">
                                    <span class="iconify" data-icon="mdi:calendar-start" data-width="14"></span>
                                    Diterbitkan: <?php echo date('d M Y', strtotime($certificate['issue_date'])); ?>
                                </span>
                                <?php if (!empty($certificate['expiry_date'])): ?>
                                <span class="flex items-center gap-1">
                                    <span class="iconify" data-icon="mdi:calendar-end" data-width="14"></span>
                                    Berlaku hingga: <?php echo date('d M Y', strtotime($certificate['expiry_date'])); ?>
                                </span>
                                <?php endif; ?>
                            </div>

                            <!-- Actions -->
                            <div class="flex gap-3 flex-wrap">
                                <!-- View/Download -->
                                <?php if (!empty($certificate['file_path'])): ?>
                                <a href="<?php echo htmlspecialchars($certificate['file_path']); ?>" 
                                   target="_blank"
                                   class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium text-sm flex items-center gap-2">
                                    <span class="iconify" data-icon="mdi:eye" data-width="16"></span>
                                    Lihat Sertifikat
                                </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($certificate['certificate_url'])): ?>
                                <a href="<?php echo htmlspecialchars($certificate['certificate_url']); ?>" 
                                   target="_blank"
                                   class="bg-white text-blue-700 px-4 py-2 rounded-lg hover:bg-blue-50 transition-colors font-medium text-sm flex items-center gap-2 border border-blue-300">
                                    <span class="iconify" data-icon="mdi:link" data-width="16"></span>
                                    Lihat Online
                                </a>
                                <?php endif; ?>
                                
                                <!-- Edit Action -->
                                <?php if ($certificate['source_type'] == 'project'): ?>
                                <a href="edit-project.php?id=<?php echo $certificate['project_id']; ?>" 
                                   class="bg-green-100 text-green-700 px-4 py-2 rounded-lg hover:bg-green-200 transition-colors font-medium text-sm flex items-center gap-2">
                                    <span class="iconify" data-icon="mdi:pencil" data-width="16"></span>
                                    Edit di Proyek
                                </a>
                                <?php else: ?>
                                <a href="edit-certificate.php?id=<?php echo $certificate['id']; ?>" 
                                   class="bg-cyan-100 text-cyan-700 px-4 py-2 rounded-lg hover:bg-cyan-200 transition-colors font-medium text-sm flex items-center gap-2">
                                    <span class="iconify" data-icon="mdi:pencil" data-width="16"></span>
                                    Edit
                                </a>
                                <?php endif; ?>
                                
                                <!-- Delete Action -->
                                <?php if ($certificate['source_type'] == 'project'): ?>
                                <a href="edit-project.php?id=<?php echo $certificate['project_id']; ?>#certificate-section" 
                                   class="bg-red-100 text-red-700 px-4 py-2 rounded-lg hover:bg-red-200 transition-colors font-medium text-sm flex items-center gap-2">
                                    <span class="iconify" data-icon="mdi:delete" data-width="16"></span>
                                    Hapus dari Proyek
                                </a>
                                <?php else: ?>
                                <button onclick="confirmDeleteCertificate(<?php echo $certificate['id']; ?>)" 
                                        class="bg-red-100 text-red-700 px-4 py-2 rounded-lg hover:bg-red-200 transition-colors font-medium text-sm flex items-center gap-2">
                                    <span class="iconify" data-icon="mdi:delete" data-width="16"></span>
                                    Hapus
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function confirmDeleteCertificate(certificateId) {
    Swal.fire({
        title: 'Hapus Sertifikat?',
        html: `<div class="text-center">
                <p class="text-gray-600 mt-2">Sertifikat akan dihapus permanent dari portofoliomu.</p>
               </div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        background: '#ffffff'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `delete-certificate.php?id=${certificateId}`;
        }
    });
}
</script>

<style>
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>

<?php include '../../includes/footer.php'; ?>