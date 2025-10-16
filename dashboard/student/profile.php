<?php
include '../../includes/config.php';

if (!isLoggedIn() || getUserRole() != 'student') {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Generate CSRF token jika belum ada
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle file upload untuk profile picture
function handleProfilePictureUpload($file, $user_id) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    // Check file size
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'Ukuran file maksimal 2MB'];
    }
    
    // Check MIME type
    $file_info = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file['tmp_name']);
    if (!in_array($file_info, $allowed_types)) {
        return ['success' => false, 'error' => 'Hanya file gambar (JPG, PNG, GIF, WebP) yang diizinkan'];
    }
    
    // Create upload directory
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/studenthub/uploads/profiles/' . $user_id . '/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . time() . '_' . uniqid() . '.' . $file_ext;
    $file_path = $upload_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        return ['success' => true, 'file_path' => '/studenthub/uploads/profiles/' . $user_id . '/' . $filename];
    } else {
        return ['success' => false, 'error' => 'Gagal mengupload file'];
    }
}

// Ambil data user terlebih dahulu
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validasi CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Token keamanan tidak valid";
    } else {
        // Handle delete account
        if (isset($_POST['delete_account'])) {
            // Hapus profile picture jika ada
            if (!empty($user['profile_picture'])) {
                $old_picture_path = $_SERVER['DOCUMENT_ROOT'] . $user['profile_picture'];
                if (file_exists($old_picture_path)) {
                    unlink($old_picture_path);
                }
            }
            
            // Hapus akun
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                session_destroy();
                header("Location: ../../login.php?message=account_deleted");
                exit();
            } else {
                $error = "Gagal menghapus akun: " . $conn->error;
            }
            $stmt->close();
        } else {
            // Handle update profile
            $name = sanitize($_POST['name']);
            $university = sanitize($_POST['university'] ?? '');
            $major = sanitize($_POST['major'] ?? '');
            $bio = sanitize($_POST['bio'] ?? '');
            $linkedin = sanitize($_POST['linkedin'] ?? '');

            // Validasi tambahan
            if (empty($name)) {
                $error = "Nama lengkap wajib diisi";
            } elseif (strlen($name) > 100) {
                $error = "Nama terlalu panjang (maksimal 100 karakter)";
            }

            if (empty($error)) {
                // Validasi URL LinkedIn yang lebih ketat
                if (!empty($linkedin)) {
                    if (!filter_var($linkedin, FILTER_VALIDATE_URL)) {
                        $error = "URL LinkedIn tidak valid";
                    } elseif (!preg_match('/^(https?:\/\/)?(www\.)?linkedin\.com\/in\/[a-zA-Z0-9\-_]+\/?$/', $linkedin)) {
                        $error = "URL harus berupa profil LinkedIn yang valid (contoh: https://linkedin.com/in/username)";
                    } elseif (strlen($linkedin) > 200) {
                        $error = "URL LinkedIn terlalu panjang";
                    }
                }

                // Validasi panjang bio
                if (strlen($bio) > 500) {
                    $error = "Bio terlalu panjang (maksimal 500 karakter)";
                }
            }

            if (empty($error)) {
                // Handle profile picture upload
                $profile_picture_path = $user['profile_picture'] ?? ''; // Keep existing if no new upload
                
                if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = handleProfilePictureUpload($_FILES['profile_picture'], $user_id);
                    if ($upload_result['success']) {
                        // Delete old profile picture if exists
                        if (!empty($user['profile_picture'])) {
                            $old_picture_path = $_SERVER['DOCUMENT_ROOT'] . $user['profile_picture'];
                            if (file_exists($old_picture_path)) {
                                unlink($old_picture_path);
                            }
                        }
                        $profile_picture_path = $upload_result['file_path'];
                    } else {
                        $error = $upload_result['error'];
                    }
                }

                // Handle remove profile picture
                if (isset($_POST['remove_profile_picture'])) {
                    if (!empty($user['profile_picture'])) {
                        $old_picture_path = $_SERVER['DOCUMENT_ROOT'] . $user['profile_picture'];
                        if (file_exists($old_picture_path)) {
                            unlink($old_picture_path);
                        }
                    }
                    $profile_picture_path = '';
                }

                if (empty($error)) {
                    // Cek struktur tabel users dan sesuaikan query
                    $stmt = $conn->prepare("UPDATE users SET name = ?, university = ?, major = ?, bio = ?, linkedin = ?, profile_picture = ? WHERE id = ?");
                    
                    if ($stmt) {
                        $stmt->bind_param("ssssssi", $name, $university, $major, $bio, $linkedin, $profile_picture_path, $user_id);
                        
                        if ($stmt->execute()) {
                            $_SESSION['name'] = $name;
                            $_SESSION['profile_picture'] = $profile_picture_path;
                            $success = "Profil berhasil diperbarui!";
                            // Generate new CSRF token setelah success
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                            
                            // Refresh user data
                            $refresh_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                            $refresh_stmt->bind_param("i", $user_id);
                            $refresh_stmt->execute();
                            $refresh_result = $refresh_stmt->get_result();
                            $user = $refresh_result->fetch_assoc();
                            $refresh_stmt->close();
                        } else {
                            error_log("Database error: " . $conn->error);
                            $error = "Terjadi kesalahan saat memperbarui profil: " . $conn->error;
                        }
                        $stmt->close();
                    } else {
                        $error = "Gagal mempersiapkan query: " . $conn->error;
                    }
                }
            }
        }
    }
}
?>

<?php include '../../includes/header.php'; ?>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
            <div>
                <h1 class="text-3xl font-bold text-blue-900 flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:account-cog" data-width="32"></span>
                    Kelola Profil
                </h1>
                <p class="text-gray-600 mt-2">Lengkapi informasi profil kamu untuk meningkatkan visibilitas di StudentHub</p>
            </div>
            <a href="index.php" class="bg-blue-500/10 text-blue-700 px-6 py-3 rounded-xl font-semibold hover:bg-blue-500/20 transition-colors duration-300 border border-blue-200 flex items-center gap-2">
                <span class="iconify" data-icon="mdi:arrow-left" data-width="18"></span>
                Kembali ke Dashboard
            </a>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($error): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center gap-2">
            <span class="iconify" data-icon="mdi:alert-circle" data-width="20"></span>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center gap-2">
            <span class="iconify" data-icon="mdi:check-circle" data-width="20"></span>
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Profile Form -->
        <div class="lg:col-span-3">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                <h2 class="text-2xl font-bold text-blue-900 mb-8 flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:account-edit" data-width="28"></span>
                    Informasi Pribadi
                </h2>

                <form method="POST" action="" class="space-y-8" id="profileForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <!-- Profile Picture Upload -->
                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-200 pb-2">Foto Profil</h3>
                        
                        <div class="flex flex-col lg:flex-row items-start gap-8">
                            <!-- Current Profile Picture -->
                            <div class="flex-shrink-0">
                                <div class="relative group">
                                    <?php if (!empty($user['profile_picture'])): ?>
                                        <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                                            alt="Profile Picture" 
                                            class="w-36 h-36 rounded-full object-cover border-4 border-cyan-100 shadow-lg">
                                    <?php else: ?>
                                        <div class="w-36 h-36 rounded-full bg-gradient-to-br from-cyan-400 to-blue-500 flex items-center justify-center border-4 border-cyan-100 shadow-lg">
                                            <span class="iconify text-white" data-icon="mdi:account" data-width="48"></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="absolute -bottom-2 -right-2 bg-cyan-500 rounded-full p-2 shadow-lg">
                                        <span class="iconify text-white" data-icon="mdi:camera" data-width="18"></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Upload Controls -->
                            <div class="flex-1 space-y-4">
                                <!-- Upload Button -->
                                <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-cyan-400 transition-colors duration-300 bg-gray-50/50">
                                    <div class="flex flex-col items-center justify-center mb-3">
                                        <div class="text-gray-400 mb-2">
                                            <span class="iconify" data-icon="mdi:cloud-upload" data-width="48"></span>
                                        </div>
                                        <p class="text-lg font-medium text-gray-700 mb-1">Upload Foto Profil</p>
                                        <p class="text-sm text-gray-500">Drag & drop file atau klik untuk memilih</p>
                                    </div>
                                    
                                    <label class="cursor-pointer inline-block">
                                        <span class="bg-cyan-500 text-white px-6 py-3 rounded-lg font-semibold hover:bg-cyan-600 transition-colors duration-300 inline-flex items-center gap-2">
                                            <span class="iconify" data-icon="mdi:folder-open" data-width="20"></span>
                                            Pilih File
                                        </span>
                                        <input type="file" name="profile_picture" accept="image/*" 
                                            class="hidden" id="profile-picture-input">
                                    </label>
                                    
                                    <p class="text-xs text-gray-500 mt-3">Max. 2MB (JPG, PNG, GIF, WebP)</p>
                                    
                                    <!-- File name display -->
                                    <div id="profile-picture-name" class="text-sm text-cyan-600 mt-2 hidden"></div>
                                </div>
                                
                                <?php if (!empty($user['profile_picture'])): ?>
                                <button type="button" 
                                        onclick="removeProfilePicture()"
                                        class="text-red-600 hover:text-red-700 text-sm font-medium flex items-center gap-1 transition-colors">
                                    <span class="iconify" data-icon="mdi:delete" data-width="16"></span>
                                    Hapus Foto Profil
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Personal Information -->
                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-200 pb-2">Informasi Dasar</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nama Lengkap *</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                        <span class="iconify" data-icon="mdi:account" data-width="20"></span>
                                    </span>
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" 
                                           class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors" 
                                           required maxlength="100">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Universitas</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                        <span class="iconify" data-icon="mdi:school" data-width="20"></span>
                                    </span>
                                    <input type="text" name="university" value="<?php echo htmlspecialchars($user['university'] ?? ''); ?>" 
                                           class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors" 
                                           placeholder="Nama universitas" maxlength="100">
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Jurusan</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                        <span class="iconify" data-icon="mdi:book-education" data-width="20"></span>
                                    </span>
                                    <input type="text" name="major" value="<?php echo htmlspecialchars($user['major'] ?? ''); ?>" 
                                           class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors" 
                                           placeholder="Program studi" maxlength="100">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">LinkedIn</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                        <span class="iconify" data-icon="mdi:linkedin" data-width="20"></span>
                                    </span>
                                    <input type="url" name="linkedin" value="<?php echo htmlspecialchars($user['linkedin'] ?? ''); ?>" 
                                           class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors" 
                                           placeholder="https://linkedin.com/in/username" 
                                           pattern="^(https?:\/\/)?(www\.)?linkedin\.com\/in\/[a-zA-Z0-9\-_]+\/?$"
                                           title="Masukkan URL LinkedIn yang valid (contoh: https://linkedin.com/in/username)"
                                           maxlength="200">
                                </div>
                                <p class="text-gray-500 text-xs mt-1">Format: linkedin.com/in/username</p>
                            </div>
                        </div>
                    </div>

                    <!-- Bio Section -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-200 pb-2">Tentang Saya</h3>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Bio / Deskripsi Diri</label>
                            <div class="relative">
                                <span class="absolute top-3 left-3 text-gray-400">
                                    <span class="iconify" data-icon="mdi:text" data-width="20"></span>
                                </span>
                                <textarea name="bio" rows="5" 
                                          class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors resize-none" 
                                          placeholder="Ceritakan tentang diri kamu, minat, keterampilan, dan tujuan karir..." 
                                          maxlength="500"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            </div>
                            <div class="flex justify-between items-center mt-1">
                                <p class="text-gray-500 text-xs">Rekomendasi: 150-300 karakter</p>
                                <p class="text-gray-500 text-xs" id="bioCounter"><?php echo strlen($user['bio'] ?? ''); ?>/500</p>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-gray-200">
                        <button type="submit" class="bg-gradient-to-r from-cyan-500 to-blue-600 text-white px-8 py-3 rounded-xl font-semibold hover:from-cyan-600 hover:to-blue-700 transition-all duration-300 flex items-center justify-center gap-2 shadow-md" id="submitBtn">
                            <span class="iconify" data-icon="mdi:content-save" data-width="20"></span>
                            Simpan Perubahan
                        </button>
                        <a href="index.php" class="bg-gray-100 text-gray-700 px-8 py-3 rounded-xl font-semibold hover:bg-gray-200 transition-colors duration-300 border border-gray-200 flex items-center justify-center gap-2">
                            <span class="iconify" data-icon="mdi:close" data-width="20"></span>
                            Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sidebar - Account Info -->
        <div class="space-y-6">
            <!-- Profile Summary -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-bold text-blue-900 mb-4 flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:account-box" data-width="20"></span>
                    Ringkasan Profil
                </h3>
                <div class="space-y-4">
                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                        <div class="w-10 h-10 bg-cyan-100 rounded-full flex items-center justify-center">
                            <span class="iconify text-cyan-600" data-icon="mdi:calendar" data-width="18"></span>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Bergabung</p>
                            <p class="font-semibold text-gray-900"><?php echo date('d M Y', strtotime($user['created_at'])); ?></p>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                            <span class="iconify text-blue-600" data-icon="mdi:account-tie" data-width="18"></span>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Role</p>
                            <p class="font-semibold text-gray-900 capitalize"><?php echo $user['role']; ?></p>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                            <span class="iconify text-green-600" data-icon="mdi:email" data-width="18"></span>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Email</p>
                            <p class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="bg-white rounded-2xl shadow-sm border border-red-100 p-6">
                <h3 class="text-lg font-bold text-red-800 mb-4 flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:alert-circle" data-width="20"></span>
                    Zona Berbahaya
                </h3>
                <p class="text-red-600 text-sm mb-4">Tindakan ini tidak dapat dibatalkan</p>
                
                <div class="space-y-3">
                    <!-- Logout Button -->
                    <button type="button" 
                            onclick="confirmLogout()"
                            class="w-full bg-red-500 text-white py-3 px-4 rounded-lg font-semibold hover:bg-red-600 transition-colors duration-300 flex items-center justify-center gap-2 cursor-pointer">
                        <span class="iconify" data-icon="mdi:logout" data-width="18"></span>
                        Logout
                    </button>
                    
                    <!-- Delete Account Button -->
                    <button type="button" 
                            onclick="confirmDelete()"
                            class="w-full bg-red-100 text-red-700 py-3 px-4 rounded-lg font-semibold hover:bg-red-200 transition-colors duration-300 flex items-center justify-center gap-2 cursor-pointer">
                        <span class="iconify" data-icon="mdi:delete-forever" data-width="18"></span>
                        Hapus Akun
                    </button>
                    
                    <!-- Delete Account Form (Hidden) -->
                    <form method="POST" action="" id="deleteAccountForm" class="hidden">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="delete_account" value="1">
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Real-time bio character counter
document.addEventListener('DOMContentLoaded', function() {
    const bioTextarea = document.querySelector('textarea[name="bio"]');
    const bioCounter = document.getElementById('bioCounter');
    
    if (bioTextarea && bioCounter) {
        bioTextarea.addEventListener('input', function() {
            bioCounter.textContent = this.value.length + '/500';
        });
    }
    
    // Loading state untuk form submission
    const form = document.getElementById('profileForm');
    const submitBtn = document.getElementById('submitBtn');
    
    if (form && submitBtn) {
        form.addEventListener('submit', function() {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="iconify" data-icon="mdi:loading" data-width="20"></span> Menyimpan...';
        });
    }

    // Profile picture upload preview - FIXED
    const profilePictureInput = document.getElementById('profile-picture-input');
    const profilePictureName = document.getElementById('profile-picture-name');
    
    if (profilePictureInput && profilePictureName) {
        profilePictureInput.addEventListener('change', function(e) {
            if (this.files.length > 0) {
                handleFileSelection(this.files[0]);
            }
        });
    }

    // Drag and drop functionality - FIXED
    const dropZone = document.querySelector('.border-dashed');
    
    if (dropZone) {
        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });
        
        // Highlight drop zone when item is dragged over it
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });
        
        // Handle dropped files
        dropZone.addEventListener('drop', handleDrop, false);

        // Click to select file
        dropZone.addEventListener('click', function() {
            profilePictureInput.click();
        });
    }
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    function highlight() {
        dropZone.classList.add('border-cyan-400', 'bg-cyan-50');
    }
    
    function unhighlight() {
        dropZone.classList.remove('border-cyan-400', 'bg-cyan-50');
    }
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (files.length > 0) {
            handleFileSelection(files[0]);
        }
    }
    
    function handleFileSelection(file) {
        // Validate file type and size
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        const maxSize = 2 * 1024 * 1024; // 2MB
        
        if (!allowedTypes.includes(file.type)) {
            showError('Hanya file gambar (JPG, PNG, GIF, WebP) yang diizinkan');
            return;
        }
        
        if (file.size > maxSize) {
            showError('Ukuran file maksimal 2MB');
            return;
        }
        
        // Show file name
        if (profilePictureName) {
            profilePictureName.textContent = 'File terpilih: ' + file.name;
            profilePictureName.classList.remove('hidden');
        }
        
        // Set file to input
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        profilePictureInput.files = dataTransfer.files;
        
        // Preview image
        const reader = new FileReader();
        reader.onload = function(e) {
            // Target the profile picture container in the form, not in header
            const profileContainer = document.querySelector('.flex-shrink-0 .relative.group');
            if (profileContainer) {
                let profilePic = profileContainer.querySelector('img');
                
                if (!profilePic) {
                    // If no img exists (using default icon), replace the icon with image
                    const defaultIcon = profileContainer.querySelector('.bg-gradient-to-br');
                    if (defaultIcon) {
                        profilePic = document.createElement('img');
                        profilePic.src = e.target.result;
                        profilePic.alt = 'Profile Picture';
                        profilePic.className = 'w-32 h-32 rounded-full object-cover border-4 border-cyan-100 shadow-lg';
                        defaultIcon.parentNode.replaceChild(profilePic, defaultIcon);
                    }
                } else {
                    // If img exists, just update the src
                    profilePic.src = e.target.result;
                }
            }
        };
        reader.readAsDataURL(file);
    }
    
    function showError(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Upload Gagal',
                text: message,
                icon: 'error',
                confirmButtonColor: '#3085d6',
                background: '#ffffff'
            });
        } else {
            alert('Error: ' + message);
        }
    }
});

// Konfirmasi Logout
function confirmLogout() {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Yakin ingin logout?',
            text: "Anda akan keluar dari sesi saat ini",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, Logout!',
            cancelButtonText: 'Batal',
            background: '#ffffff'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '/studenthub/logout.php';
            }
        });
    } else {
        if (confirm('Yakin ingin logout?')) {
            window.location.href = '/studenthub/logout.php';
        }
    }
}

// Konfirmasi Hapus Akun
function confirmDelete() {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Hapus Akun Permanent?',
            html: `<div class="text-left">
                    <p class="text-red-600 font-semibold">PERINGATAN: Tindakan ini tidak dapat dibatalkan!</p>
                   </div>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus Permanent!',
            cancelButtonText: 'Batal',
            background: '#ffffff'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deleteAccountForm').submit();
            }
        });
    } else {
        if (confirm('PERINGATAN: Hapus akun permanent?\n\nYakin ingin menghapus?')) {
            document.getElementById('deleteAccountForm').submit();
        }
    }
}

// Remove profile picture
function removeProfilePicture() {
    if (confirm('Hapus foto profil?')) {
        // Create a hidden input to indicate removal
        const removeInput = document.createElement('input');
        removeInput.type = 'hidden';
        removeInput.name = 'remove_profile_picture';
        removeInput.value = '1';
        document.getElementById('profileForm').appendChild(removeInput);
        
        // Submit form
        document.getElementById('profileForm').submit();
    }
}
</script>

<?php include '../../includes/footer.php'; ?>