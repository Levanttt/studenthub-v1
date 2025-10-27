<?php
include '../../includes/config.php';
include '../../includes/functions.php';

if (!isLoggedIn() || getUserRole() != 'mitra_industri') {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
} else {
    $error = "Error preparing user query: " . $conn->error;
}

function handleProfilePictureUpload($file, $user_id) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 2 * 1024 * 1024; 
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'Ukuran file maksimal 2MB'];
    }
    
    $file_info = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file['tmp_name']);
    if (!in_array($file_info, $allowed_types)) {
        return ['success' => false, 'error' => 'Hanya file gambar (JPG, PNG, GIF, WebP) yang diizinkan'];
    }
    
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/cakrawala-connect/uploads/profiles/' . $user_id . '/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . time() . '_' . uniqid() . '.' . $file_ext;
    $file_path = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        return ['success' => true, 'file_path' => '/cakrawala-connect/uploads/profiles/' . $user_id . '/' . $filename];
    } else {
        return ['success' => false, 'error' => 'Gagal mengupload file'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Token keamanan tidak valid";
    } else {
        if (isset($_POST['delete_account'])) {
            if (!empty($user['profile_picture'])) {
                $old_picture_path = $_SERVER['DOCUMENT_ROOT'] . $user['profile_picture'];
                if (file_exists($old_picture_path)) {
                    unlink($old_picture_path);
                }
            }
            
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt) {
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
                $error = "Error preparing delete query: " . $conn->error;
            }
        } else {
            $name = sanitize($_POST['name']);
            $company = sanitize($_POST['company'] ?? '');
            $position = sanitize($_POST['position'] ?? '');
            $bio = sanitize($_POST['bio'] ?? '');
            $linkedin = sanitize($_POST['linkedin'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');

            if (empty($name)) {
                $error = "Nama lengkap wajib diisi";
            } elseif (strlen($name) > 100) {
                $error = "Nama terlalu panjang (maksimal 100 karakter)";
            }

            if (empty($error)) {
                if (!empty($linkedin)) {
                    if (!filter_var($linkedin, FILTER_VALIDATE_URL)) {
                        $error = "URL LinkedIn tidak valid";
                    } elseif (!preg_match('/^(https?:\/\/)?(www\.)?linkedin\.com\/.+/', $linkedin)) {
                        $error = "URL harus berupa profil LinkedIn yang valid";
                    } elseif (strlen($linkedin) > 200) {
                        $error = "URL LinkedIn terlalu panjang";
                    }
                }

                if (strlen($bio) > 500) {
                    $error = "Bio terlalu panjang (maksimal 500 karakter)";
                }

                if (!empty($phone) && !preg_match('/^[0-9+\-\s()]{10,20}$/', $phone)) {
                    $error = "Format nomor telepon tidak valid";
                }
            }

            if (empty($error)) {
                $profile_picture_path = $user['profile_picture'] ?? '';
                
                if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = handleProfilePictureUpload($_FILES['profile_picture'], $user_id);
                    if ($upload_result['success']) {
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
                    $stmt = $conn->prepare("UPDATE users SET name = ?, company_name = ?, position = ?, bio = ?, linkedin = ?, phone = ?, profile_picture = ? WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("sssssssi", $name, $company, $position, $bio, $linkedin, $phone, $profile_picture_path, $user_id);
                        
                        if ($stmt->execute()) {
                            $_SESSION['name'] = $name;
                            $_SESSION['profile_picture'] = $profile_picture_path;
                            $success = "Profil berhasil diperbarui!";
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                            
                            $refresh_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                            if ($refresh_stmt) {
                                $refresh_stmt->bind_param("i", $user_id);
                                $refresh_stmt->execute();
                                $refresh_result = $refresh_stmt->get_result();
                                $user = $refresh_result->fetch_assoc();
                                $refresh_stmt->close();
                            }
                        } else {
                            error_log("Database error: " . $conn->error);
                            $error = "Terjadi kesalahan saat memperbarui profil: " . $conn->error;
                        }
                        $stmt->close();
                    } else {
                        $error = "Gagal mempersiapkan query update: " . $conn->error;
                    }
                }
            }
        }
    }
}
?>

<?php include '../../includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
            <div>
                <h1 class="text-3xl font-bold text-[#2A8FA9] flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:account-tie" data-width="32"></span>
                    Kelola Profil Mitra Industri
                </h1>
                <p class="text-gray-600 mt-2">Lengkapi informasi profil perusahaan Anda untuk meningkatkan kredibilitas</p>
            </div>
            <a href="index.php" class="bg-[#E0F7FF] text-[#2A8FA9] px-6 py-3 rounded-xl font-semibold hover:bg-[#51A3B9] hover:text-white transition-colors duration-300 border border-[#51A3B9] border-opacity-30 flex items-center gap-2">
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
                <h2 class="text-2xl font-bold text-[#2A8FA9] mb-8 flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:account-edit" data-width="28"></span>
                    Informasi Profil
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
                                            class="w-36 h-36 rounded-full object-cover border-4 border-[#E0F7FF] shadow-lg">
                                    <?php else: ?>
                                        <div class="w-36 h-36 rounded-full bg-gradient-to-br from-[#51A3B9] to-[#2A8FA9] flex items-center justify-center border-4 border-[#E0F7FF] shadow-lg">
                                            <span class="iconify text-white" data-icon="mdi:account-tie" data-width="48"></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="absolute -bottom-2 -right-2 bg-[#51A3B9] rounded-full p-2 shadow-lg">
                                        <span class="iconify text-white" data-icon="mdi:camera" data-width="18"></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Upload Controls -->
                            <div class="flex-1 space-y-4">
                                <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-[#51A3B9] transition-colors duration-300 bg-gray-50/50">
                                    <div class="flex flex-col items-center justify-center mb-3">
                                        <div class="text-gray-400 mb-2">
                                            <span class="iconify" data-icon="mdi:cloud-upload" data-width="48"></span>
                                        </div>
                                        <p class="text-lg font-medium text-gray-700 mb-1">Upload Foto Profil</p>
                                        <p class="text-sm text-gray-500">Drag & drop file atau klik untuk memilih</p>
                                    </div>
                                    
                                    <label class="cursor-pointer inline-block">
                                        <span class="bg-[#51A3B9] text-white px-6 py-3 rounded-lg font-semibold hover:bg-[#409BB2] transition-colors duration-300 inline-flex items-center gap-2">
                                            <span class="iconify" data-icon="mdi:folder-open" data-width="20"></span>
                                            Pilih File
                                        </span>
                                        <input type="file" name="profile_picture" accept="image/*" 
                                            class="hidden" id="profile-picture-input">
                                    </label>
                                    
                                    <p class="text-xs text-gray-500 mt-3">Max. 2MB (JPG, PNG, GIF, WebP)</p>
                                    
                                    <div id="profile-picture-name" class="text-sm text-[#2A8FA9] mt-2 hidden"></div>
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
                        <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-200 pb-2">Informasi Pribadi</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nama Lengkap *</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                        <span class="iconify" data-icon="mdi:account" data-width="20"></span>
                                    </span>
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" 
                                        class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] transition-colors" 
                                        required maxlength="100">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Posisi / Jabatan</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                        <span class="iconify" data-icon="mdi:briefcase" data-width="20"></span>
                                    </span>
                                    <input type="text" name="position" value="<?php echo htmlspecialchars($user['position'] ?? ''); ?>" 
                                        class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] transition-colors" 
                                        placeholder="HR Manager, Recruiter, dll" maxlength="100">
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Perusahaan</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                        <span class="iconify" data-icon="mdi:office-building" data-width="20"></span>
                                    </span>
                                    <input type="text" name="company" value="<?php echo htmlspecialchars($user['company_name'] ?? ''); ?>" 
                                        class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] transition-colors" 
                                        placeholder="Nama perusahaan" maxlength="100">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nomor Telepon</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                        <span class="iconify" data-icon="mdi:phone" data-width="20"></span>
                                    </span>
                                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                        class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] transition-colors" 
                                        placeholder="Contoh: 081234567890" 
                                        pattern="^[0-9+\-\s()]{10,20}$"
                                        title="Format: +62xxx atau 08xxx"
                                        maxlength="20">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">LinkedIn</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                    <span class="iconify" data-icon="mdi:linkedin" data-width="20"></span>
                                </span>
                                <input type="url" name="linkedin" value="<?php echo htmlspecialchars($user['linkedin'] ?? ''); ?>" 
                                    class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] transition-colors" 
                                    placeholder="https://linkedin.com/in/username" 
                                    pattern="^(https?:\/\/)?(www\.)?linkedin\.com\/.+\/?$"
                                    title="Masukkan URL LinkedIn yang valid"
                                    maxlength="200">
                            </div>
                            <p class="text-gray-500 text-xs mt-1">URL profil LinkedIn Anda</p>
                        </div>
                    </div>

                    <!-- Bio Section -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-200 pb-2">Tentang Saya / Perusahaan</h3>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Bio / Deskripsi</label>
                            <div class="relative">
                                <span class="absolute top-3 left-3 text-gray-400">
                                    <span class="iconify" data-icon="mdi:text" data-width="20"></span>
                                </span>
                                <textarea name="bio" rows="5" 
                                        class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] transition-colors resize-none" 
                                        placeholder="Ceritakan tentang diri Anda, perusahaan, atau bidang yang Anda tekuni..." 
                                        maxlength="500"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            </div>
                            <div class="flex justify-between items-center mt-1">
                                <p class="text-gray-500 text-xs">Deskripsi singkat tentang profil profesional</p>
                                <p class="text-gray-500 text-xs" id="bioCounter"><?php echo strlen($user['bio'] ?? ''); ?>/500</p>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-gray-200">
                        <button type="submit" class="bg-gradient-to-r from-[#51A3B9] to-[#2A8FA9] text-white px-8 py-3 rounded-xl font-semibold hover:from-[#409BB2] hover:to-[#2A8FA9] transition-all duration-300 flex items-center justify-center gap-2 shadow-md" id="submitBtn">
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
                <h3 class="text-lg font-bold text-[#2A8FA9] mb-4 flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:account-box" data-width="20"></span>
                    Ringkasan Profil
                </h3>
                <div class="space-y-4">
                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                            <span class="iconify text-blue-600" data-icon="mdi:calendar" data-width="18"></span>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Bergabung</p>
                            <p class="font-semibold text-gray-900 text-sm"><?php echo date('d M Y', strtotime($user['created_at'])); ?></p>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                        <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center">
                            <span class="iconify text-indigo-600" data-icon="mdi:account-tie" data-width="18"></span>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Role</p>
                            <p class="font-semibold text-gray-900 capitalize text-sm">
                                <?php 
                                $display_role = str_replace('_', ' ', $user['role']);
                                echo htmlspecialchars(ucwords($display_role));
                                ?>
                            </p>
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

                    <!-- Company Info -->
                    <?php if (!empty($user['company_name'])): ?>
                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                        <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                            <span class="iconify text-purple-600" data-icon="mdi:office-building" data-width="18"></span>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Perusahaan</p>
                            <p class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($user['company_name']); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Position Info -->
                    <?php if (!empty($user['position'])): ?>
                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                        <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center">
                            <span class="iconify text-orange-600" data-icon="mdi:briefcase" data-width="18"></span>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Posisi</p>
                            <p class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($user['position']); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
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
                    
                    <!-- Delete Account Form  -->
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
function initializeProfilePictureDragDrop() {
    const profilePictureInput = document.getElementById('profile-picture-input');
    const profilePictureName = document.getElementById('profile-picture-name');
    const dropZone = document.querySelector('.border-dashed');
    
    if (dropZone) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });
        
        dropZone.addEventListener('drop', handleDrop, false);
    }
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    function highlight() {
        dropZone.classList.add('border-[#51A3B9]', 'bg-[#E0F7FF]');
    }
    
    function unhighlight() {
        dropZone.classList.remove('border-[#51A3B9]', 'bg-[#E0F7FF]');
    }
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (files.length > 0) {
            handleFileSelection(files[0]);
        }
    }
    
    function handleFileSelection(file) {
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        const maxSize = 2 * 1024 * 1024;
        
        if (!allowedTypes.includes(file.type)) {
            showError('Hanya file gambar (JPG, PNG, GIF, WebP) yang diizinkan');
            return;
        }
        
        if (file.size > maxSize) {
            showError('Ukuran file maksimal 2MB');
            return;
        }
        
        if (profilePictureName) {
            profilePictureName.textContent = 'File terpilih: ' + file.name;
            profilePictureName.classList.remove('hidden');
        }
        
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        profilePictureInput.files = dataTransfer.files;
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const profileContainer = document.querySelector('.flex-shrink-0 .relative.group');
            if (profileContainer) {
                let profilePic = profileContainer.querySelector('img');
                
                if (!profilePic) {
                    const defaultIcon = profileContainer.querySelector('.bg-gradient-to-br');
                    if (defaultIcon) {
                        profilePic = document.createElement('img');
                        profilePic.src = e.target.result;
                        profilePic.alt = 'Profile Picture';
                        profilePic.className = 'w-32 h-32 rounded-full object-cover border-4 border-[#E0F7FF] shadow-lg';
                        defaultIcon.parentNode.replaceChild(profilePic, defaultIcon);
                    }
                } else {
                    profilePic.src = e.target.result;
                }
            }
        };
        reader.readAsDataURL(file);
    }
    
    if (profilePictureInput && profilePictureName) {
        profilePictureInput.addEventListener('change', function(e) {
            if (this.files.length > 0) {
                handleFileSelection(this.files[0]);
            }
        });
    }
}

function showError(message) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Error',
            text: message,
            icon: 'error',
            confirmButtonColor: '#3085d6',
            background: '#ffffff'
        });
    } else {
        alert('Error: ' + message);
    }
}

function removeProfilePicture() {
    if (confirm('Hapus foto profil?')) {
        const removeInput = document.createElement('input');
        removeInput.type = 'hidden';
        removeInput.name = 'remove_profile_picture';
        removeInput.value = '1';
        document.getElementById('profileForm').appendChild(removeInput);
        
        document.getElementById('profileForm').submit();
    }
}

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
                window.location.href = '/cakrawala-connect/logout.php';
            }
        });
    } else {
        if (confirm('Yakin ingin logout?')) {
            window.location.href = '/cakrawala-connect/logout.php';
        }
    }
}

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

document.addEventListener('DOMContentLoaded', function() {
    const bioTextarea = document.querySelector('textarea[name="bio"]');
    const bioCounter = document.getElementById('bioCounter');
    
    if (bioTextarea && bioCounter) {
        bioTextarea.addEventListener('input', function() {
            bioCounter.textContent = this.value.length + '/500';
        });
    }
    
    const form = document.getElementById('profileForm');
    const submitBtn = document.getElementById('submitBtn');
    
    if (form && submitBtn) {
        form.addEventListener('submit', function() {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="iconify" data-icon="mdi:loading" data-width="20"></span> Menyimpan...';
        });
    }

    initializeProfilePictureDragDrop();
});
</script>

<?php include '../../includes/footer.php'; ?>