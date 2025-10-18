<?php
include '../../includes/config.php';
include '../../includes/functions.php';

if (!isLoggedIn() || getUserRole() != 'student') {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $certificate_title = sanitize($_POST['certificate_title']);
    $organization = sanitize($_POST['organization']);
    $issue_date = sanitize($_POST['issue_date']);
    $expiry_date = !empty($_POST['expiry_date']) ? sanitize($_POST['expiry_date']) : null;
    $credential_id = !empty($_POST['credential_id']) ? sanitize($_POST['credential_id']) : null;
    $credential_url = !empty($_POST['credential_url']) ? sanitize($_POST['credential_url']) : null;
    $description = sanitize($_POST['description']);

    // Validation
    if (empty($certificate_title) || empty($organization) || empty($issue_date)) {
        $error = "Nama sertifikat, organisasi, dan tanggal terbit wajib diisi!";
    } else {
        $file_path = null;
        
        // Handle file upload
        if (isset($_FILES['certificate_file']) && $_FILES['certificate_file']['error'] === UPLOAD_ERR_OK) {
            $upload_result = handleCertificateUpload($_FILES['certificate_file'], $user_id);
            if ($upload_result['success']) {
                $file_path = $upload_result['file_path'];
            } else {
                $error = $upload_result['error'];
            }
        }

        if (!$error) {
            $stmt = $conn->prepare("
                INSERT INTO certificates 
                (student_id, title, organization, issue_date, expiry_date, credential_id, credential_url, file_path, description) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt) {
                $stmt->bind_param("issssssss", $user_id, $certificate_title, $organization, $issue_date, $expiry_date, $credential_id, $credential_url, $file_path, $description);
                
                if ($stmt->execute()) {
                    $success = "Sertifikat berhasil ditambahkan!";
                    // Clear form
                    $certificate_title = $organization = $issue_date = $expiry_date = $credential_id = $credential_url = $description = '';
                } else {
                    $error = "Gagal menambahkan sertifikat: " . $conn->error;
                }
                $stmt->close();
            } else {
                $error = "Error preparing statement: " . $conn->error;
            }
        }
    }
}

function handleCertificateUpload($file, $user_id) {
    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'Ukuran file maksimal 5MB'];
    }
    
    $file_info = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file['tmp_name']);
    if (!in_array($file_info, $allowed_types)) {
        return ['success' => false, 'error' => 'Hanya file PDF, JPG, dan PNG yang diizinkan'];
    }
    
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/studenthub/uploads/certificates/' . $user_id . '/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'certificate_' . time() . '_' . uniqid() . '.' . $file_ext;
    $file_path = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        return ['success' => true, 'file_path' => '/studenthub/uploads/certificates/' . $user_id . '/' . $filename];
    } else {
        return ['success' => false, 'error' => 'Gagal mengupload file'];
    }
}
?>

<?php include '../../includes/header.php'; ?>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="flex justify-between items-center w-full mb-8">
        <div class="flex-1">
            <h1 class="text-3xl font-bold text-blue-900 flex items-center gap-3">
                <span class="iconify" data-icon="mdi:certificate-plus" data-width="32"></span>
                Tambah Sertifikat Baru
            </h1>
            <p class="text-gray-600 mt-2">Tambahkan sertifikat standalone untuk melengkapi portofolio Anda</p>
        </div>
        <a href="certificates.php" 
           class="bg-blue-500/10 text-blue-700 px-6 py-3 rounded-xl font-semibold hover:bg-blue-500/20 transition-colors duration-300 border border-blue-200 flex items-center gap-2">
            <span class="iconify" data-icon="mdi:arrow-left" data-width="18"></span>
            Kembali
        </a>
    </div>

    <!-- Alerts -->
    <?php if ($error): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center gap-2">
            <span class="iconify" data-icon="mdi:alert-circle" data-width="20"></span>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center gap-2">
            <span class="iconify" data-icon="mdi:check-circle" data-width="20"></span>
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <!-- Certificate Form -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
        <form method="POST" action="" enctype="multipart/form-data" class="space-y-8">
            <!-- Certificate Information -->
            <div class="space-y-8">
                <h2 class="text-2xl font-bold text-blue-900 flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:information" data-width="24"></span>
                    Informasi Sertifikat
                </h2>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Kolom Kiri -->
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nama Sertifikat *</label>
                            <input type="text" name="certificate_title" value="<?php echo htmlspecialchars($certificate_title ?? ''); ?>" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                                placeholder="Contoh: Google Cloud Professional" required maxlength="255">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Organisasi Pemberi *</label>
                            <input type="text" name="organization" value="<?php echo htmlspecialchars($organization ?? ''); ?>" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                                placeholder="Contoh: Google, Microsoft, AWS" required maxlength="255">
                        </div>
                    </div>

                    <!-- Kolom Kanan -->
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Terbit *</label>
                            <input type="date" name="issue_date" value="<?php echo htmlspecialchars($issue_date ?? ''); ?>" 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                                    required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Kadaluarsa</label>
                            <input type="date" name="expiry_date" value="<?php echo htmlspecialchars($expiry_date ?? ''); ?>" 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                            <p class="text-xs text-gray-500 mt-1">Kosongkan jika sertifikat tidak memiliki masa berlaku</p>
                        </div>
                    </div>
                </div>

                <!-- Informasi Kredensial - Hanya SATU bagian ini yang dipakai -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">ID Kredensial</label>
                        <input type="text" name="credential_id" value="<?php echo htmlspecialchars($credential_id ?? ''); ?>" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                            placeholder="Contoh: ABC123XYZ, 123-456-789">
                        <p class="text-xs text-gray-500 mt-1">ID unik untuk verifikasi sertifikat</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Link Kredensial</label>
                        <input type="url" name="credential_url" value="<?php echo htmlspecialchars($credential_url ?? ''); ?>" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                            placeholder="https://credential.net/verify/12345">
                        <p class="text-xs text-gray-500 mt-1">Link untuk verifikasi online</p>
                    </div>
                </div>

                <div class="mt-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Deskripsi</label>
                    <textarea name="description" rows="4" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none" 
                            placeholder="Deskripsi singkat tentang sertifikat ini..."><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                </div>
            </div>

            <!-- File Upload -->
            <div class="space-y-6 pt-6 border-t border-gray-200">
                <h2 class="text-2xl font-bold text-blue-900 flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:file-upload" data-width="24"></span>
                    Upload File Sertifikat
                </h2>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                    <div class="flex items-start gap-3">
                        <span class="iconify text-blue-600 mt-0.5" data-icon="mdi:information" data-width="20"></span>
                        <div>
                            <p class="text-sm text-blue-800 font-medium mb-1">Rekomendasi</p>
                            <p class="text-sm text-blue-700">
                                Upload file sertifikat untuk bukti fisik. Format PDF, JPG, atau PNG dengan ukuran maksimal 5MB.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="max-w-2xl">
                    <label class="block text-sm font-medium text-gray-700 mb-2">File Sertifikat</label>
                    <input type="file" name="certificate_file" accept=".pdf,.jpg,.jpeg,.png" 
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="text-xs text-gray-500 mt-2">Format: PDF, JPG, PNG. Maksimal 5MB</p>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end gap-4 pt-8 border-t border-gray-200">
                <a href="certificates.php" 
                class="px-8 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                    Batal
                </a>
                <button type="submit" 
                        class="px-8 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all duration-300 font-medium shadow-sm hover:shadow-md flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:certificate-plus" data-width="18"></span>
                    Tambah Sertifikat
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set default date to today for issue date
    const issueDateInput = document.querySelector('input[name="issue_date"]');
    if (issueDateInput && !issueDateInput.value) {
        const today = new Date().toISOString().split('T')[0];
        issueDateInput.value = today;
    }

    // Validate expiry date is after issue date
    const expiryDateInput = document.querySelector('input[name="expiry_date"]');
    if (expiryDateInput) {
        expiryDateInput.addEventListener('change', function() {
            const issueDate = new Date(issueDateInput.value);
            const expiryDate = new Date(this.value);
            
            if (expiryDate <= issueDate) {
                alert('Tanggal kadaluarsa harus setelah tanggal terbit!');
                this.value = '';
            }
        });
    }
});
</script>

<?php include '../../includes/footer.php'; ?>