<?php
include '../../includes/config.php';
include '../../includes/functions.php';

if (!isLoggedIn() || getUserRole() != 'student') {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle delete certificate
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_certificate') {
    if (isset($_POST['certificate_id']) && !empty($_POST['certificate_id'])) {
        $certificate_id = intval($_POST['certificate_id']);
        
        // Cek apakah sertifikat milik user yang login
        $check_stmt = $conn->prepare("SELECT id FROM certificates WHERE id = ? AND student_id = ?");
        $check_stmt->bind_param("ii", $certificate_id, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Hapus sertifikat
            $delete_stmt = $conn->prepare("DELETE FROM certificates WHERE id = ? AND student_id = ?");
            $delete_stmt->bind_param("ii", $certificate_id, $user_id);
            
            if ($delete_stmt->execute()) {
                $_SESSION['success'] = "Sertifikat berhasil dihapus";
            } else {
                $_SESSION['error'] = "Gagal menghapus sertifikat: " . $conn->error;
            }
        } else {
            $_SESSION['error'] = "Sertifikat tidak ditemukan atau tidak memiliki akses";
        }
        
        // Redirect ke halaman yang sama untuk menghindari resubmit
        header("Location: certificates.php");
        exit();
    }
}

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
    if (!empty($cert['certificate_title']) && $cert['certificate_title'] != '0' && !empty($cert['file_path'])) {
        $project_certificates[] = $cert;
    }
}

$standalone_certificates = [];
$standalone_certs_stmt = null; 

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

if ($standalone_certs_stmt) {
    $standalone_certs_stmt->bind_param("i", $user_id);
    $standalone_certs_stmt->execute();
    $standalone_certs_result = $standalone_certs_stmt->get_result();

    while ($cert = $standalone_certs_result->fetch_assoc()) {
        $standalone_certificates[] = $cert;
    }
} else {
    error_log("Certificates table doesn't exist or query error: " . $conn->error);
}

$all_certificates = array_merge($project_certificates, $standalone_certificates);

usort($all_certificates, function($a, $b) {
    return strtotime($b['issue_date']) - strtotime($a['issue_date']);
});

$total_certificates = count($all_certificates);
$from_projects = count($project_certificates);
$standalone = count($standalone_certificates);
?>

<?php include '../../includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center w-full mb-8">
        <div class="flex-1">
            <h1 class="text-3xl font-bold text-[#2A8FA9] flex items-center gap-3">
                <span class="iconify" data-icon="mdi:certificate" data-width="32"></span>
                Semua Sertifikat
            </h1>
            <p class="text-gray-600 mt-2">Kelola semua sertifikat dari proyek dan pencapaian lainnya</p>
        </div>
        <div class="flex items-center gap-4">
            <a href="add-certificate.php" class="bg-[#2A8FA9] text-white px-6 py-3 rounded-xl font-bold hover:bg-[#409BB2] transition-colors duration-300 flex items-center gap-2">
                <span class="iconify" data-icon="mdi:certificate-plus" data-width="20"></span>
                Tambah Sertifikat
            </a>
            <a href="index.php" 
                class="bg-[#E0F7FF] text-[#2A8FA9] px-6 py-3 rounded-xl font-semibold hover:bg-[#51A3B9] hover:text-white transition-colors duration-300 border border-[#51A3B9] border-opacity-30 flex items-center gap-2">
                <span class="iconify" data-icon="mdi:arrow-left" data-width="18"></span>
                Kembali
            </a>
        </div>
    </div>

    <!-- Notifikasi -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="bg-gradient-to-r from-[#2A8FA9]/10 to-[#409BB2]/10 rounded-xl p-4 border border-[#2A8FA9]/30">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-[#2A8FA9] rounded-xl flex items-center justify-center">
                    <span class="iconify text-white" data-icon="mdi:certificate" data-width="24"></span>
                </div>
                <div>
                    <div class="text-2xl font-bold text-[#2A8FA9]"><?php echo $total_certificates; ?></div>
                    <div class="text-sm text-[#2A8FA9]">Total Sertifikat</div>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-r from-[#2A8FA9]/10 to-[#489EB7]/10 rounded-xl p-4 border border-[#2A8FA9]/30">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-[#489EB7] rounded-xl flex items-center justify-center">
                    <span class="iconify text-white" data-icon="mdi:folder" data-width="24"></span>
                </div>
                <div>
                    <div class="text-2xl font-bold text-[#489EB7]"><?php echo $from_projects; ?></div>
                    <div class="text-sm text-[#489EB7]">Dari Proyek</div>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-r from-[#409BB2]/10 to-[#51A3B9]/10 rounded-xl p-4 border border-[#409BB2]/30">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-[#51A3B9] rounded-xl flex items-center justify-center">
                    <span class="iconify text-white" data-icon="mdi:star" data-width="24"></span>
                </div>
                <div>
                    <div class="text-2xl font-bold text-[#51A3B9]"><?php echo $standalone; ?></div>
                    <div class="text-sm text-[#51A3B9]">Standalone</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Certificates Grid -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <?php if (empty($all_certificates)): ?>
            <div class="text-center py-12">
                <div class="text-[#2A8FA9] mb-4 flex justify-center">
                    <span class="iconify" data-icon="mdi:certificate-off" data-width="80"></span>
                </div>
                <h3 class="text-[#2A8FA9] font-bold text-xl mb-2">Belum Ada Sertifikat</h3>
                <p class="text-gray-600 mb-6">Tambahkan sertifikat pertama Anda untuk melengkapi portofolio</p>
                <div class="flex gap-4 justify-center">
                    <a href="add-certificate.php" class="bg-[#2A8FA9] text-white px-6 py-3 rounded-xl font-bold hover:bg-[#409BB2] transition-colors duration-300 inline-flex items-center gap-2">
                        <span class="iconify" data-icon="mdi:certificate-plus" data-width="20"></span>
                        Tambah Sertifikat
                    </a>
                    <a href="projects.php" class="bg-[#489EB7] text-white px-6 py-3 rounded-xl font-bold hover:bg-[#51A3B9] transition-colors duration-300 inline-flex items-center gap-2">
                        <span class="iconify" data-icon="mdi:folder-open" data-width="20"></span>
                        Lihat Proyek
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 gap-6">
                <?php foreach ($all_certificates as $certificate): ?>
                <!-- Certificate Card -->
                <div class="bg-gradient-to-r from-[#2A8FA9]/5 to-[#409BB2]/5 rounded-xl border-2 border-[#2A8FA9]/20 p-6 hover:shadow-lg transition-all duration-300 group hover:border-[#2A8FA9]/40">
                    <div class="flex flex-col md:flex-row gap-6">
                        <!-- Certificate Thumbnail/Icon -->
                        <div class="flex-shrink-0">
                            <div class="w-20 h-20 bg-[#2A8FA9] rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                                <span class="iconify text-white" data-icon="mdi:certificate" data-width="40"></span>
                            </div>
                        </div>
                        
                        <!-- Certificate Content -->
                        <div class="flex-1">
                            <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-3 mb-3">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <h3 class="font-bold text-[#2A8FA9] text-xl group-hover:text-[#409BB2] transition-colors line-clamp-1">
                                            <?php echo htmlspecialchars($certificate['certificate_title']); ?>
                                        </h3>
                                        <!-- Source Badge -->
                                        <?php if ($certificate['source_type'] == 'project'): ?>
                                        <span class="bg-blue-50 text-[#489EB7] px-3 py-1 rounded-full text-xs font-semibold border border-[#489EB7]/30 flex items-center gap-1">
                                            <span class="iconify" data-icon="mdi:folder" data-width="12"></span>
                                            Dari Proyek
                                        </span>
                                        <?php else: ?>
                                        <span class="bg-cyan-50 text-[#51A3B9] px-3 py-1 rounded-full text-xs font-semibold border border-[#51A3B9]/30 flex items-center gap-1">
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
                                    <span class="text-gray-500 text-sm bg-white px-4 py-2 rounded-lg border border-[#2A8FA9]/20 whitespace-nowrap min-w-24 text-center">
                                        <span class="iconify inline-block mr-1" data-icon="mdi:calendar" data-width="14"></span>
                                        <?php echo date('M Y', strtotime($certificate['issue_date'])); ?>
                                    </span>
                                </div>
                            </div>

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
                                   class="bg-[#2A8FA9] text-white px-4 py-2 rounded-lg hover:bg-[#409BB2] transition-colors font-medium text-sm flex items-center gap-2">
                                    <span class="iconify" data-icon="mdi:eye" data-width="16"></span>
                                    Lihat Sertifikat
                                </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($certificate['certificate_url'])): ?>
                                <a href="<?php echo htmlspecialchars($certificate['certificate_url']); ?>" 
                                   target="_blank"
                                   class="bg-white text-[#2A8FA9] px-4 py-2 rounded-lg hover:bg-[#2A8FA9]/10 transition-colors font-medium text-sm flex items-center gap-2 border border-[#2A8FA9]/30">
                                    <span class="iconify" data-icon="mdi:link" data-width="16"></span>
                                    Lihat Online
                                </a>
                                <?php endif; ?>
                                
                                <!-- Edit Action -->
                                <?php if ($certificate['source_type'] == 'project'): ?>
                                <a href="edit-project.php?id=<?php echo $certificate['project_id']; ?>" 
                                   class="bg-[#489EB7]/10 text-[#489EB7] px-4 py-2 rounded-lg hover:bg-[#489EB7]/20 transition-colors font-medium text-sm flex items-center gap-2">
                                    <span class="iconify" data-icon="mdi:pencil" data-width="16"></span>
                                    Edit di Proyek
                                </a>
                                <?php else: ?>
                                <a href="edit-certificate.php?id=<?php echo $certificate['id']; ?>" 
                                   class="bg-[#51A3B9]/10 text-[#51A3B9] px-4 py-2 rounded-lg hover:bg-[#51A3B9]/20 transition-colors font-medium text-sm flex items-center gap-2">
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

<!-- Hidden form untuk delete -->
<form id="deleteCertificateForm" method="post" style="display: none;">
    <input type="hidden" name="certificate_id" id="deleteCertificateId">
    <input type="hidden" name="action" value="delete_certificate">
</form>

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
            // Submit form untuk delete
            document.getElementById('deleteCertificateId').value = certificateId;
            document.getElementById('deleteCertificateForm').submit();
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