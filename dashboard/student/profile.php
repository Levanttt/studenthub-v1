<?php
include '../../includes/config.php';

if (!isLoggedIn() || getUserRole() != 'student') {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$availableSpecializations = [];
try {
    $spec_query = "SELECT name FROM specializations ORDER BY name ASC";
    $spec_result = $conn->query($spec_query);
    if ($spec_result) {
        while ($row = $spec_result->fetch_assoc()) {
            $availableSpecializations[] = $row['name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching specializations: " . $e->getMessage());
}

// Semester options
$semesterOptions = [
    '1' => 'Semester 1',
    '2' => 'Semester 2', 
    '3' => 'Semester 3',
    '4' => 'Semester 4',
    '5' => 'Semester 5',
    '6' => 'Semester 6',
    '7' => 'Semester 7',
    '8' => 'Semester 8',
    'fresh_graduate' => 'Fresh Graduate',
    'graduated' => 'Sudah Lulus'
];

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
    
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/studenthub/uploads/profiles/' . $user_id . '/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . time() . '_' . uniqid() . '.' . $file_ext;
    $file_path = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        return ['success' => true, 'file_path' => '/studenthub/uploads/profiles/' . $user_id . '/' . $filename];
    } else {
        return ['success' => false, 'error' => 'Gagal mengupload file'];
    }
}

function handleCVUpload($file, $user_id) {
    $allowed_types = ['application/pdf'];
    $max_size = 2 * 1024 * 1024; 
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'Ukuran file CV maksimal 2MB'];
    }
    
    $file_info = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file['tmp_name']);
    if (!in_array($file_info, $allowed_types)) {
        return ['success' => false, 'error' => 'Hanya file PDF yang diizinkan untuk CV'];
    }
    
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/studenthub/uploads/cvs/' . $user_id . '/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'cv_' . time() . '_' . uniqid() . '.' . $file_ext;
    $file_path = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        return ['success' => true, 'file_path' => '/studenthub/uploads/cvs/' . $user_id . '/' . $filename];
    } else {
        return ['success' => false, 'error' => 'Gagal mengupload file CV'];
    }
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

// Ambil skills dari project user
$userSkills = [
    'technical' => [],
    'soft' => [],
    'tool' => []
];

// Query untuk mengambil skills dari project user
$skills_stmt = $conn->prepare("
    SELECT DISTINCT s.name, s.skill_type 
    FROM project_skills ps 
    JOIN skills s ON ps.skill_id = s.id 
    JOIN projects p ON ps.project_id = p.id 
    WHERE p.student_id = ? 
    ORDER BY s.skill_type, s.name
");

if ($skills_stmt) {
    $skills_stmt->bind_param("i", $user_id);
    if ($skills_stmt->execute()) {
        $skills_result = $skills_stmt->get_result();
        
        $total_skills = 0;
        
        while ($skill = $skills_result->fetch_assoc()) {
            $skill_type = $skill['skill_type'];
            $skill_name = $skill['name'];
            
            if (isset($userSkills[$skill_type])) {
                if (!in_array($skill_name, $userSkills[$skill_type])) {
                    $userSkills[$skill_type][] = $skill_name;
                    $total_skills++;
                }
            }
        }
        
        // Debug log
        error_log("User {$user_id} - Total skills found: {$total_skills}");
        error_log("Technical: " . count($userSkills['technical']));
        error_log("Soft: " . count($userSkills['soft'])); 
        error_log("Tool: " . count($userSkills['tool']));
        
    } else {
        error_log("Error executing skills query: " . $skills_stmt->error);
    }
    $skills_stmt->close();
} else {
    error_log("Error preparing skills query: " . $conn->error);
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
            
            if (!empty($user['cv_file_path'])) {
                $old_cv_path = $_SERVER['DOCUMENT_ROOT'] . $user['cv_file_path'];
                if (file_exists($old_cv_path)) {
                    unlink($old_cv_path);
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
            $semester = sanitize($_POST['semester'] ?? '');
            $major = sanitize($_POST['major'] ?? '');
            $bio = sanitize($_POST['bio'] ?? '');
            $linkedin = sanitize($_POST['linkedin'] ?? '');
            $specializations = sanitize($_POST['specializations'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');

            if (empty($name)) {
                $error = "Nama lengkap wajib diisi";
            } elseif (empty($semester)) {
                $error = "Semester/status wajib diisi";
            } elseif (empty($major)) {
                $error = "Jurusan wajib diisi";
            } elseif (empty($linkedin)) {
                $error = "LinkedIn wajib diisi";
            } elseif (strlen($name) > 100) {
                $error = "Nama terlalu panjang (maksimal 100 karakter)";
            } elseif (empty($phone)) {
                $error = "Nomor telepon wajib diisi";
            }

            if (empty($error)) {
                if (!empty($linkedin)) {
                    if (!filter_var($linkedin, FILTER_VALIDATE_URL)) {
                        $error = "URL LinkedIn tidak valid";
                    } elseif (!preg_match('/^(https?:\/\/)?(www\.)?linkedin\.com\/in\/[a-zA-Z0-9\-_]+\/?$/', $linkedin)) {
                        $error = "URL harus berupa profil LinkedIn yang valid (contoh: https://linkedin.com/in/username)";
                    } elseif (strlen($linkedin) > 200) {
                        $error = "URL LinkedIn terlalu panjang";
                    }
                }

                if (strlen($bio) > 500) {
                    $error = "Bio terlalu panjang (maksimal 500 karakter)";
                }

                if (!empty($specializations)) {
                    $specs = explode(',', $specializations);
                    if (count($specs) > 3) {
                        $error = "Maksimal 3 spesialisasi yang dapat dipilih";
                    }
                    
                    foreach ($specs as $spec) {
                        $spec = trim($spec);
                        if (!empty($spec) && !in_array($spec, $availableSpecializations)) {
                            $error = "Spesialisasi tidak valid: " . htmlspecialchars($spec);
                            break;
                        }
                    }
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

                $cv_file_path = $user['cv_file_path'] ?? '';
                
                if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = handleCVUpload($_FILES['cv_file'], $user_id);
                    if ($upload_result['success']) {
                        if (!empty($user['cv_file_path'])) {
                            $old_cv_path = $_SERVER['DOCUMENT_ROOT'] . $user['cv_file_path'];
                            if (file_exists($old_cv_path)) {
                                unlink($old_cv_path);
                            }
                        }
                        $cv_file_path = $upload_result['file_path'];
                    } else {
                        $error = $upload_result['error'];
                    }
                }

                // Handle remove CV
                if (isset($_POST['remove_cv'])) {
                    if (!empty($user['cv_file_path'])) {
                        $old_cv_path = $_SERVER['DOCUMENT_ROOT'] . $user['cv_file_path'];
                        if (file_exists($old_cv_path)) {
                            unlink($old_cv_path);
                        }
                    }
                    $cv_file_path = '';
                }

                if (empty($error)) {
                    $stmt = $conn->prepare("UPDATE users SET name = ?, semester = ?, major = ?, bio = ?, linkedin = ?, phone = ?, profile_picture = ?, specializations = ?, cv_file_path = ? WHERE id = ?");
                    
                    if ($stmt) {
                        $stmt->bind_param("sssssssssi", $name, $semester, $major, $bio, $linkedin, $phone, $profile_picture_path, $specializations, $cv_file_path, $user_id);
                        
                        if ($stmt->execute()) {
                            $_SESSION['name'] = $name;
                            $_SESSION['profile_picture'] = $profile_picture_path;
                            $success = "Profil berhasil diperbarui!";
                            // Generate new CSRF token setelah success
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                            
                            // Refresh user data
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
                    <span class="iconify" data-icon="mdi:account-cog" data-width="32"></span>
                    Kelola Profil
                </h1>
                <p class="text-gray-600 mt-2">Lengkapi informasi profil kamu untuk meningkatkan visibilitas bagi industri</p>
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

    <!-- Database Structure Warning -->
    <?php 
    $check_columns = $conn->query("SHOW COLUMNS FROM users LIKE 'specializations'");
    $has_specializations = $check_columns->num_rows > 0;
    
    $check_columns = $conn->query("SHOW COLUMNS FROM users LIKE 'cv_file_path'");
    $has_cv_file_path = $check_columns->num_rows > 0;
    
    $check_columns = $conn->query("SHOW COLUMNS FROM users LIKE 'semester'");
    $has_semester = $check_columns->num_rows > 0;
    
    if (!$has_specializations || !$has_cv_file_path || !$has_semester): 
    ?>
        <div class="mb-6 bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded-lg flex items-center gap-2">
            <span class="iconify" data-icon="mdi:alert" data-width="20"></span>
            <div>
                <p class="font-semibold">Perhatian: Struktur database perlu update</p>
                <p class="text-sm">Beberapa fitur mungkin tidak berfungsi dengan baik. Pastikan kolom berikut ada di tabel users:</p>
                <ul class="text-sm list-disc list-inside mt-1">
                    <?php if (!$has_specializations): ?><li><code>specializations</code> (TEXT)</li><?php endif; ?>
                    <?php if (!$has_cv_file_path): ?><li><code>cv_file_path</code> (VARCHAR(255))</li><?php endif; ?>
                    <?php if (!$has_semester): ?><li><code>semester</code> (VARCHAR(50))</li><?php endif; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Profile Form -->
        <div class="lg:col-span-3">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                <h2 class="text-2xl font-bold text-[#2A8FA9] mb-8 flex items-center gap-3">
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
                                            class="w-36 h-36 rounded-full object-cover border-4 border-[#E0F7FF] shadow-lg">
                                    <?php else: ?>
                                        <div class="w-36 h-36 rounded-full bg-gradient-to-br from-[#51A3B9] to-[#2A8FA9] flex items-center justify-center border-4 border-[#E0F7FF] shadow-lg">
                                            <span class="iconify text-white" data-icon="mdi:account" data-width="48"></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="absolute -bottom-2 -right-2 bg-[#51A3B9] rounded-full p-2 shadow-lg">
                                        <span class="iconify text-white" data-icon="mdi:camera" data-width="18"></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Upload Controls -->
                            <div class="flex-1 space-y-4">
                                <!-- Upload Button -->
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
                                    
                                    <!-- File name display -->
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

                    <!-- CV Upload Section -->
                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-200 pb-2">Curriculum Vitae (CV)</h3>
                        
                        <div class="flex flex-col lg:flex-row items-start gap-8">
                            <!-- Current CV Info -->
                            <div class="flex-shrink-0">
                                <div class="w-36 h-36 rounded-xl bg-gradient-to-br from-[#51A3B9] to-[#2A8FA9] flex flex-col items-center justify-center border-4 border-[#E0F7FF] shadow-lg p-4">
                                    <span class="iconify text-white mb-2" data-icon="mdi:file-document" data-width="48"></span>
                                    <span class="text-white text-sm font-semibold text-center">CV Document</span>
                                    <?php if (!empty($user['cv_file_path'])): ?>
                                        <span class="text-white text-xs mt-1 text-center">Uploaded</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Upload Controls -->
                            <div class="flex-1 space-y-4">
                                <!-- Upload Button with Drag & Drop -->
                                <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-[#51A3B9] transition-colors duration-300 bg-gray-50/50" id="cv-drop-zone">
                                    <div class="flex flex-col items-center justify-center mb-3">
                                        <div class="text-gray-400 mb-2">
                                            <span class="iconify" data-icon="mdi:file-upload" data-width="48"></span>
                                        </div>
                                        <p class="text-lg font-medium text-gray-700 mb-1">Upload CV</p>
                                        <p class="text-sm text-gray-500">Drag & drop file PDF atau klik untuk memilih</p>
                                    </div>
                                    
                                    <label class="cursor-pointer inline-block">
                                        <span class="bg-[#51A3B9] text-white px-6 py-3 rounded-lg font-semibold hover:bg-[#409BB2] transition-colors duration-300 inline-flex items-center gap-2">
                                            <span class="iconify" data-icon="mdi:file-pdf-box" data-width="20"></span>
                                            Pilih File PDF
                                        </span>
                                        <input type="file" name="cv_file" accept=".pdf,application/pdf" 
                                            class="hidden" id="cv-file-input">
                                    </label>
                                    
                                    <p class="text-xs text-gray-500 mt-3">Max. 2MB (PDF only)</p>
                                    
                                    <!-- File name display -->
                                    <div id="cv-file-name" class="text-sm text-[#2A8FA9] mt-2">
                                        <?php if (!empty($user['cv_file_path'])): ?>
                                            <?php 
                                                $cv_filename = basename($user['cv_file_path']);
                                                echo htmlspecialchars($cv_filename);
                                            ?>
                                        <?php else: ?>
                                            <span class="text-gray-500">Belum ada CV yang diupload</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="flex gap-4">
                                    <?php if (!empty($user['cv_file_path'])): ?>
                                    <a href="<?php echo htmlspecialchars($user['cv_file_path']); ?>" 
                                    target="_blank"
                                    class="bg-green-500 text-white px-4 py-2 rounded-lg font-semibold hover:bg-green-600 transition-colors duration-300 flex items-center gap-2 text-sm">
                                        <span class="iconify" data-icon="mdi:eye" data-width="16"></span>
                                        Lihat CV
                                    </a>
                                    <button type="button" 
                                            onclick="removeCV()"
                                            class="bg-red-500 text-white px-4 py-2 rounded-lg font-semibold hover:bg-red-600 transition-colors duration-300 flex items-center gap-2 text-sm">
                                        <span class="iconify" data-icon="mdi:delete" data-width="16"></span>
                                        Hapus CV
                                    </button>
                                    <?php endif; ?>
                                </div>
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
                                        class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] transition-colors" 
                                        required maxlength="100">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Semester / Status *</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                                        <span class="iconify" data-icon="mdi:school" data-width="20"></span>
                                    </span>
                                    <select name="semester" class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] transition-colors appearance-none bg-white" required>
                                        <option value="">Pilih semester atau status...</option>
                                        <?php foreach ($semesterOptions as $value => $label): ?>
                                            <option value="<?php echo $value; ?>" <?php echo ($user['semester'] ?? '') == $value ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 pointer-events-none">
                                        <span class="iconify" data-icon="mdi:chevron-down" data-width="16"></span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Jurusan *</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 pointer-events-none"> 
                                        <span class="iconify" data-icon="mdi:book-education" data-width="20"></span>
                                    </span>
                                    <select name="major"
                                            class="w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] transition-colors appearance-none bg-white text-sm"
                                            required>
                                        <option value="">Pilih jurusan...</option>

                                        <optgroup label="Fakultas Ekonomi dan Bisnis">
                                            <option value="Bisnis Digital" <?php echo ($user['major'] ?? '') == 'Bisnis Digital' ? 'selected' : ''; ?>>Bisnis Digital</option>
                                            <option value="Keuangan dan Investasi (S1)" <?php echo ($user['major'] ?? '') == 'Keuangan dan Investasi' ? 'selected' : ''; ?>>Keuangan dan Investasi</option>
                                            <option value="Manajemen" <?php echo ($user['major'] ?? '') == 'Manajemen' ? 'selected' : ''; ?>>Manajemen</option>
                                            <option value="Akuntansi" <?php echo ($user['major'] ?? '') == 'Akuntansi' ? 'selected' : ''; ?>>Akuntansi</option>
                                        </optgroup>

                                        <optgroup label="Fakultas Teknik dan Ilmu Komputer">
                                            <option value="AI dan Ilmu Komputer" <?php echo ($user['major'] ?? '') == 'AI dan Ilmu Komputer' ? 'selected' : ''; ?>>AI dan Ilmu Komputer</option>
                                            <option value="Sains Data" <?php echo ($user['major'] ?? '') == 'Sains Data' ? 'selected' : ''; ?>>Sains Data</option>
                                            <option value="Sistem Informasi dan Teknologi" <?php echo ($user['major'] ?? '') == 'Sistem Informasi dan Teknologi' ? 'selected' : ''; ?>>Sistem Informasi dan Teknologi</option>
                                            <option value="Teknik Elektro" <?php echo ($user['major'] ?? '') == 'Teknik Elektro' ? 'selected' : ''; ?>>Teknik Elektro</option>
                                            <option value="Teknik Industri" <?php echo ($user['major'] ?? '') == 'Teknik Industri' ? 'selected' : ''; ?>>Teknik Industri</option>
                                        </optgroup>

                                        <optgroup label="Fakultas Komunikasi dan Desain">
                                            <option value="Ilmu Komunikasi" <?php echo ($user['major'] ?? '') == 'Ilmu Komunikasi' ? 'selected' : ''; ?>>Ilmu Komunikasi</option>
                                            <option value="Desain Komunikasi Visual" <?php echo ($user['major'] ?? '') == 'Desain Komunikasi Visual' ? 'selected' : ''; ?>>Desain Komunikasi Visual</option>
                                        </optgroup>

                                        <optgroup label="Fakultas Psikologi dan Pendidikan">
                                            <option value="Psikologi" <?php echo ($user['major'] ?? '') == 'Psikologi' ? 'selected' : ''; ?>>Psikologi</option>
                                            <option value="PGSD" <?php echo ($user['major'] ?? '') == 'PGSD' ? 'selected' : ''; ?>>PGSD</option>
                                        </optgroup>

                                        <optgroup label="Fakultas Hukum">
                                            <option value="Ilmu Hukum" <?php echo ($user['major'] ?? '') == 'Ilmu Hukum' ? 'selected' : ''; ?>>Ilmu Hukum</option>
                                        </optgroup>

                                    </select>
                                    <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 pointer-events-none">
                                        <span class="iconify" data-icon="mdi:chevron-down" data-width="16"></span>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Nomor Telepon -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nomor Telepon *</label>
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
                            <label class="block text-sm font-medium text-gray-700 mb-2">LinkedIn *</label>
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

                    <!-- Specializations Section dengan Feedback Visual -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-200 pb-2">Spesialisasi / Bidang Fokus</h3>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Pilih hingga 3 bidang spesialisasi</label>
                            
                            <!-- Dropdown dengan feedback visual -->
                            <div class="relative mb-3">
                                <select id="specialization-select" 
                                        class="w-full pl-3 pr-10 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors duration-300 appearance-none bg-white hover:border-green-400">
                                    <option value="">Pilih spesialisasi...</option>
                                    <?php foreach ($availableSpecializations as $spec): ?>
                                        <option value="<?php echo htmlspecialchars($spec); ?>"><?php echo htmlspecialchars($spec); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                    <span class="iconify" data-icon="mdi:chevron-down" data-width="20"></span>
                                </div>
                            </div>
                            
                            <p class="text-gray-500 text-xs mb-3">Pilih dari daftar yang tersedia</p>
                            
                            <!-- Selected Specializations Display dengan feedback visual -->
                            <div id="specializations-container" class="flex flex-wrap gap-2 mt-3 min-h-12 p-3 bg-green-50 border border-green-200 rounded-lg transition-colors duration-300">
                                <?php
                                if (!empty($user['specializations'])) {
                                    $specs = explode(',', $user['specializations']);
                                    foreach ($specs as $spec) {
                                        $spec = trim($spec);
                                        if (!empty($spec)) {
                                            echo '<span class="specialization-tag bg-green-500 text-white px-3 py-2 rounded-full text-sm flex items-center gap-2 shadow-sm hover:shadow-md transition-all duration-200">';
                                            echo '<span class="iconify" data-icon="mdi:check-circle" data-width="16"></span>';
                                            echo htmlspecialchars($spec);
                                            echo '<button type="button" onclick="removeSpecialization(this)" class="text-white hover:text-green-200 transition-colors ml-1">';
                                            echo '<span class="iconify" data-icon="mdi:close" data-width="14"></span>';
                                            echo '</button>';
                                            echo '</span>';
                                        }
                                    }
                                } else {
                                    echo '<p class="text-gray-500 text-sm flex items-center gap-2">';
                                    echo '<span class="iconify" data-icon="mdi:information" data-width="16"></span>';
                                    echo 'Belum ada spesialisasi yang dipilih';
                                    echo '</p>';
                                }
                                ?>
                            </div>
                            
                            <!-- Hidden input untuk menyimpan data -->
                            <input type="hidden" name="specializations" id="specializations-hidden" 
                                value="<?php echo htmlspecialchars($user['specializations'] ?? ''); ?>">
                            
                            <!-- Counter dengan progress bar visual -->
                            <div class="mt-4 space-y-2">
                                <div class="flex justify-between items-center">
                                    <p class="text-gray-600 text-sm font-medium">Progress Pemilihan</p>
                                    <p class="text-gray-600 text-sm font-semibold" id="specCounter"><?php echo !empty($user['specializations']) ? count(explode(',', $user['specializations'])) : 0; ?>/3</p>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div id="specProgressBar" class="bg-green-500 h-2 rounded-full transition-all duration-500 ease-out" 
                                        style="width: <?php echo !empty($user['specializations']) ? (count(explode(',', $user['specializations'])) / 3 * 100) : 0; ?>%"></div>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500">
                                    <span>Mulai</span>
                                    <span>Optimal</span>
                                    <span>Maksimal</span>
                                </div>
                            </div>
                            
                            <!-- Success message ketika sudah memilih -->
                            <div id="specSuccessMessage" class="hidden mt-3 p-3 bg-green-100 border border-green-300 rounded-lg">
                                <p class="text-green-700 text-sm flex items-center gap-2">
                                    <span class="iconify" data-icon="mdi:check-circle" data-width="16"></span>
                                    <span class="font-semibold">Bagus!</span> Spesialisasi telah dipilih. Ini akan membantu perusahaan menemukan profil Anda.
                                </p>
                            </div>
                        </div>
                    </div> 

                    <!-- Skills from Projects Section -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800 border-b border-gray-200 pb-2">Skills dari Project</h3>
                        <div>
                            <p class="text-sm text-gray-600 mb-4">Skills berikut otomatis diambil dari project yang telah Anda buat:</p>
                            
                            <?php 
                            $hasSkills = false;
                            foreach ($userSkills as $category => $skills): 
                                if (!empty($skills)): 
                                    $hasSkills = true;
                            ?>
                                <div class="mb-6 last:mb-0">
                                    <h4 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                                        <?php 
                                        $icon = '';
                                        $color = '';
                                        switch($category) {
                                            case 'technical':
                                                $icon = 'mdi:code-braces';
                                                $color = 'text-green-600';
                                                $categoryName = 'Technical Skills';
                                                break;
                                            case 'soft':
                                                $icon = 'mdi:account-group';
                                                $color = 'text-purple-600';
                                                $categoryName = 'Soft Skills';
                                                break;
                                            case 'tool':
                                                $icon = 'mdi:tools';
                                                $color = 'text-orange-600';
                                                $categoryName = 'Tools';
                                                break;
                                            default:
                                                $icon = 'mdi:tag';
                                                $color = 'text-gray-600';
                                                $categoryName = ucfirst($category);
                                        }
                                        ?>
                                        <span class="iconify <?php echo $color; ?>" data-icon="<?php echo $icon; ?>" data-width="20"></span>
                                        <?php echo $categoryName; ?>
                                        <span class="bg-gray-200 text-gray-700 text-xs px-2 py-1 rounded-full"><?php echo count($skills); ?></span>
                                    </h4>
                                    <div class="flex flex-wrap gap-2">
                                        <?php foreach ($skills as $skill): ?>
                                            <span class="skill-tag 
                                                <?php 
                                                switch($category) {
                                                    case 'technical': echo 'bg-green-100 text-green-800 border-green-200'; break;
                                                    case 'soft': echo 'bg-purple-100 text-purple-800 border-purple-200'; break;
                                                    case 'tool': echo 'bg-orange-100 text-orange-800 border-orange-200'; break;
                                                    default: echo 'bg-gray-100 text-gray-800 border-gray-200';
                                                }
                                                ?> 
                                                px-3 py-2 rounded-lg text-sm border">
                                                <?php echo htmlspecialchars($skill); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php 
                                endif;
                            endforeach; 
                            
                            if (!$hasSkills): 
                            ?>
                                <div class="text-center py-8 bg-gray-50 rounded-xl">
                                    <span class="iconify text-gray-400 mx-auto mb-3" data-icon="mdi:folder-open" data-width="48"></span>
                                    <p class="text-gray-500">Belum ada skills dari project</p>
                                    <p class="text-gray-400 text-sm mt-1">Skills akan otomatis muncul ketika Anda menambahkan project</p>
                                    <a href="add-project.php" class="inline-block mt-4 bg-[#51A3B9] text-white px-4 py-2 rounded-lg hover:bg-[#409BB2] transition-colors">
                                        Tambah Project Pertama
                                    </a>
                                </div>
                            <?php endif; ?>
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
                                          class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] transition-colors resize-none" 
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
                        <div class="w-10 h-10 bg-cyan-100 rounded-full flex items-center justify-center">
                            <span class="iconify text-cyan-600" data-icon="mdi:calendar" data-width="18"></span>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Bergabung</p>
                            <p class="font-semibold text-gray-900 text-sm"><?php echo date('d M Y', strtotime($user['created_at'])); ?></p>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                            <span class="iconify text-blue-600" data-icon="mdi:account-tie" data-width="18"></span>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Role</p>
                            <p class="font-semibold text-gray-900 capitalize text-sm"><?php echo $user['role']; ?></p>
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

                    <!-- CV Status -->
                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                        <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                            <span class="iconify text-purple-600" data-icon="mdi:file-document" data-width="18"></span>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">CV</p>
                            <p class="font-semibold <?php echo !empty($user['cv_file_path']) ? 'text-green-600 text-sm' : 'text-gray-500 text-sm'; ?>">
                                <?php echo !empty($user['cv_file_path']) ? 'Tersedia' : 'Belum diupload'; ?>
                            </p>
                        </div>
                    </div>

                    <!-- Semester Status -->
                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                        <div class="w-10 h-10 bg-[#E0F7FF] rounded-full flex items-center justify-center">
                            <span class="iconify text-[#2A8FA9]" data-icon="mdi:school" data-width="18"></span>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Semester</p>
                            <p class="font-semibold text-gray-900 text-sm">
                                <?php 
                                if (!empty($user['semester'])) {
                                    echo $semesterOptions[$user['semester']] ?? $user['semester'];
                                } else {
                                    echo 'Belum diisi';
                                }
                                ?>
                            </p>
                        </div>
                    </div>

                    <!-- Specializations Summary -->
                    <?php if (!empty($user['specializations'])): ?>
                    <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                        <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <span class="iconify text-indigo-600" data-icon="mdi:tag-multiple" data-width="18"></span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-gray-600 mb-2">Spesialisasi</p>
                            <div class="flex flex-wrap gap-1">
                                <?php
                                $specs = explode(',', $user['specializations']);
                                foreach ($specs as $spec):
                                    $spec = trim($spec);
                                    if (!empty($spec)):
                                ?>
                                    <span class="bg-[#E0F7FF] text-[#2A8FA9] px-3 py-1.5 rounded-full text-xs font-medium my-0.5">
                                        <?php echo htmlspecialchars($spec); ?>
                                    </span>
                                <?php
                                    endif;
                                endforeach;
                                ?>
                            </div>
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
// Specializations Management dengan Feedback Visual
let specializationCount = <?php echo !empty($user['specializations']) ? count(explode(',', $user['specializations'])) : 0; ?>;

function addSpecialization() {
    const select = document.getElementById('specialization-select');
    const specText = select.value;
    
    if (!specText) return;
    
    if (specializationCount >= 3) {
        showError('Maksimal 3 spesialisasi yang dapat dipilih');
        // Feedback visual untuk max limit
        select.classList.add('border-red-500', 'bg-red-50');
        setTimeout(() => {
            select.classList.remove('border-red-500', 'bg-red-50');
        }, 2000);
        return;
    }
    
    // Check if already exists
    const existingSpecs = Array.from(document.querySelectorAll('.specialization-tag'))
        .map(tag => tag.textContent.replace('×', '').replace('check-circle', '').trim());
    
    if (existingSpecs.includes(specText)) {
        showError('Spesialisasi sudah dipilih');
        // Feedback visual untuk duplikat
        select.classList.add('border-yellow-500', 'bg-yellow-50');
        setTimeout(() => {
            select.classList.remove('border-yellow-500', 'bg-yellow-50');
        }, 2000);
        return;
    }
    
    // Create tag dengan animasi
    const container = document.getElementById('specializations-container');
    const tag = document.createElement('span');
    tag.className = 'specialization-tag bg-green-500 text-white px-3 py-2 rounded-full text-sm flex items-center gap-2 shadow-sm hover:shadow-md transition-all duration-200 animate-pulse';
    tag.innerHTML = `
        <span class="iconify" data-icon="mdi:check-circle" data-width="16"></span>
        ${specText}
        <button type="button" onclick="removeSpecialization(this)" class="text-white hover:text-green-200 transition-colors ml-1">
            <span class="iconify" data-icon="mdi:close" data-width="14"></span>
        </button>
    `;
    
    container.appendChild(tag);
    
    // Hapus placeholder text jika ada
    const placeholder = container.querySelector('p.text-gray-500');
    if (placeholder) {
        placeholder.remove();
    }
    
    updateSpecializationsHidden();
    
    // Reset select dengan feedback
    select.value = '';
    select.classList.add('border-green-500', 'bg-green-50');
    setTimeout(() => {
        select.classList.remove('border-green-500', 'bg-green-50');
    }, 1000);
    
    specializationCount++;
    updateSpecCounter();
    
    // Show success message jika sudah memilih minimal 1
    if (specializationCount >= 1) {
        document.getElementById('specSuccessMessage').classList.remove('hidden');
    }
}

function removeSpecialization(button) {
    const tag = button.parentElement;
    
    // Animasi sebelum remove
    tag.classList.add('opacity-0', 'scale-95');
    setTimeout(() => {
        tag.remove();
        specializationCount--;
        updateSpecializationsHidden();
        updateSpecCounter();
        
        // Hide success message jika tidak ada spesialisasi
        if (specializationCount === 0) {
            document.getElementById('specSuccessMessage').classList.add('hidden');
            // Tambahkan placeholder kembali
            const container = document.getElementById('specializations-container');
            const placeholder = document.createElement('p');
            placeholder.className = 'text-gray-500 text-sm flex items-center gap-2';
            placeholder.innerHTML = '<span class="iconify" data-icon="mdi:information" data-width="16"></span>Belum ada spesialisasi yang dipilih';
            container.appendChild(placeholder);
        }
    }, 300);
}

function updateSpecializationsHidden() {
    const tags = Array.from(document.querySelectorAll('.specialization-tag'))
        .map(tag => tag.textContent.replace('×', '').replace('check-circle', '').trim())
        .filter(text => text !== '');
    
    document.getElementById('specializations-hidden').value = tags.join(',');
}

function updateSpecCounter() {
    const counter = document.getElementById('specCounter');
    const progressBar = document.getElementById('specProgressBar');
    
    counter.textContent = `${specializationCount}/3`;
    
    // Update progress bar
    const progressPercentage = (specializationCount / 3) * 100;
    progressBar.style.width = `${progressPercentage}%`;
    
    // Update progress bar color based on count
    if (specializationCount === 0) {
        progressBar.className = 'bg-gray-400 h-2 rounded-full transition-all duration-500 ease-out';
    } else if (specializationCount === 1) {
        progressBar.className = 'bg-yellow-500 h-2 rounded-full transition-all duration-500 ease-out';
    } else if (specializationCount === 2) {
        progressBar.className = 'bg-green-500 h-2 rounded-full transition-all duration-500 ease-out';
    } else {
        progressBar.className = 'bg-green-600 h-2 rounded-full transition-all duration-500 ease-out';
    }
}

// CV Drag & Drop functionality
function initializeCVDragDrop() {
    const cvFileInput = document.getElementById('cv-file-input');
    const cvFileName = document.getElementById('cv-file-name');
    const cvDropZone = document.getElementById('cv-drop-zone');
    
    if (cvDropZone) {
        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            cvDropZone.addEventListener(eventName, preventDefaults, false);
        });
        
        // Highlight drop zone when item is dragged over it
        ['dragenter', 'dragover'].forEach(eventName => {
            cvDropZone.addEventListener(eventName, highlightCV, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            cvDropZone.addEventListener(eventName, unhighlightCV, false);
        });
        
        // Handle dropped files
        cvDropZone.addEventListener('drop', handleCVDrop, false);
        
        // Click to select file
        cvDropZone.addEventListener('click', function() {
            cvFileInput.click();
        });
    }
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    function highlightCV() {
        cvDropZone.classList.add('border-[#51A3B9]', 'bg-[#E0F7FF]');
    }
    
    function unhighlightCV() {
        cvDropZone.classList.remove('border-[#51A3B9]', 'bg-[#E0F7FF]');
    }
    
    function handleCVDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (files.length > 0) {
            handleCVFileSelection(files[0]);
        }
    }
    
    function handleCVFileSelection(file) {
        // Validate file type
        if (file.type !== 'application/pdf') {
            showError('Hanya file PDF yang diizinkan untuk CV');
            return;
        }
        
        // Validate file size (2MB)
        if (file.size > 2 * 1024 * 1024) {
            showError('Ukuran file CV maksimal 2MB');
            return;
        }
        
        // Update file name display
        if (cvFileName) {
            cvFileName.textContent = file.name;
            cvFileName.classList.remove('text-gray-500');
            cvFileName.classList.add('text-[#2A8FA9]');
        }
        
        // Set file to input
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        cvFileInput.files = dataTransfer.files;
    }
    
    // CV file input change event
    if (cvFileInput && cvFileName) {
        cvFileInput.addEventListener('change', function(e) {
            if (this.files.length > 0) {
                handleCVFileSelection(this.files[0]);
            }
        });
    }
}

// Profile Picture Drag & Drop functionality
function initializeProfilePictureDragDrop() {
    const profilePictureInput = document.getElementById('profile-picture-input');
    const profilePictureName = document.getElementById('profile-picture-name');
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
                        profilePic.className = 'w-32 h-32 rounded-full object-cover border-4 border-[#E0F7FF] shadow-lg';
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
    
    if (profilePictureInput && profilePictureName) {
        profilePictureInput.addEventListener('change', function(e) {
            if (this.files.length > 0) {
                handleFileSelection(this.files[0]);
            }
        });
    }
}

// Utility Functions
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

// Remove CV function
function removeCV() {
    if (confirm('Hapus CV? File akan dihapus permanent.')) {
        // Create a hidden input to indicate removal
        const removeInput = document.createElement('input');
        removeInput.type = 'hidden';
        removeInput.name = 'remove_cv';
        removeInput.value = '1';
        document.getElementById('profileForm').appendChild(removeInput);
        
        // Submit form
        document.getElementById('profileForm').submit();
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

// Initialize all functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize counters
    updateSpecCounter();
    
    // Specialization select event
    const specSelect = document.getElementById('specialization-select');
    if (specSelect) {
        specSelect.addEventListener('change', function() {
            if (this.value) {
                addSpecialization();
            }
        });
    }

    // Real-time bio character counter
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

    initializeCVDragDrop();
    initializeProfilePictureDragDrop();
});
</script>

<?php include '../../includes/footer.php'; ?>