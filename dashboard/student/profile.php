<?php
include '../../includes/config.php';

if (!isLoggedIn() || getUserRole() != 'student') {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($_POST['name']);
    $university = sanitize($_POST['university']);
    $major = sanitize($_POST['major']);
    $bio = sanitize($_POST['bio']);
    $linkedin = sanitize($_POST['linkedin']);

    $stmt = $conn->prepare("UPDATE users SET name = ?, university = ?, major = ?, bio = ?, linkedin = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $name, $university, $major, $bio, $linkedin, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['name'] = $name;
        $success = "Profil berhasil diperbarui!";
    } else {
        $error = "Terjadi kesalahan: " . $conn->error;
    }
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
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
                <p class="text-gray-600 mt-2">Lengkapi informasi profil kamu untuk meningkatkan visibilitas</p>
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
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center gap-2">
            <span class="iconify" data-icon="mdi:check-circle" data-width="20"></span>
            <?php echo $success; ?>
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

                <form method="POST" action="" class="space-y-8">
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
                                           required>
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
                                           placeholder="Nama universitas">
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
                                           placeholder="Program studi">
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
                                           placeholder="https://linkedin.com/in/username">
                                </div>
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
                                          placeholder="Ceritakan tentang diri kamu, minat, keterampilan, dan tujuan karir..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            </div>
                            <p class="text-gray-500 text-xs mt-1">Rekomendasi: 150-300 karakter</p>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-gray-200">
                        <button type="submit" class="bg-gradient-to-r from-cyan-500 to-blue-600 text-white px-8 py-3 rounded-xl font-semibold hover:from-cyan-600 hover:to-blue-700 transition-all duration-300 flex items-center justify-center gap-2 shadow-md">
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
                    <a href="/studenthub/logout.php" 
                       class="w-full bg-red-500 text-white py-3 px-4 rounded-lg font-semibold hover:bg-red-600 transition-colors duration-300 flex items-center justify-center gap-2">
                        <span class="iconify" data-icon="mdi:logout" data-width="18"></span>
                        Logout
                    </a>
                    
                    <button class="w-full bg-red-100 text-red-700 py-3 px-4 rounded-lg font-semibold hover:bg-red-200 transition-colors duration-300 flex items-center justify-center gap-2 opacity-50 cursor-not-allowed" disabled>
                        <span class="iconify" data-icon="mdi:delete-forever" data-width="18"></span>
                        Hapus Akun
                    </button>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="bg-gradient-to-br from-cyan-500 to-blue-600 rounded-2xl p-6 text-white">
                <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:chart-box" data-width="20"></span>
                    Statistik Cepat
                </h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-cyan-100">Proyek</span>
                        <span class="font-bold text-lg">0</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-cyan-100">Dilihat</span>
                        <span class="font-bold text-lg">0</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-cyan-100">Suka</span>
                        <span class="font-bold text-lg">0</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>