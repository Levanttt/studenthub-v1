<?php
include '../../includes/config.php';
include '../../includes/functions.php';

if (!isLoggedIn() || getUserRole() != 'mitra_industri') {
    header("Location: ../../login.php");
    exit();
}

$student_id = $_GET['id'] ?? 0;
$viewer_id = $_SESSION['user_id'];

recordProfileView($student_id, $viewer_id, 'mitra-industri');

$query_filter = isset($_GET['q']) ? sanitize($_GET['q']) : '';
$specialization_filter = isset($_GET['specialization']) ? sanitize($_GET['specialization']) : '';
$skill_filter = isset($_GET['skill']) ? sanitize($_GET['skill']) : '';
$show_all = isset($_GET['show_all']) && $_GET['show_all'] == '1';

$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$students_per_page = 8;
$offset = ($current_page - 1) * $students_per_page;

$all_specializations = [];
try {
    $specializations_query = "SELECT name FROM specializations ORDER BY name ASC";
    $specializations_result = $conn->query($specializations_query);
    if ($specializations_result) {
        while ($row = $specializations_result->fetch_assoc()) {
            $all_specializations[] = $row['name']; 
        }
    }
} catch (Exception $e) {
    error_log("Error fetching specializations list: " . $e->getMessage()); 
}

$all_skills = [];
try {
    $skills_query = "
        SELECT DISTINCT name, skill_type 
        FROM skills 
        ORDER BY skill_type, name
    ";
    $skills_result = $conn->query($skills_query);

    if ($skills_result instanceof mysqli_result) {
        while ($row = $skills_result->fetch_assoc()) {
            $all_skills[] = $row;
        }
        $skills_result->free();
    } else {
        error_log("Error fetching skills for filter: " . $conn->error);
    }

} catch (Exception $e) {
    error_log("Exception fetching skills for filter: " . $e->getMessage());
}

$search_suggestions = [
    'skills' => [],
    'universities' => [],
    'majors' => []
];

try {
    $skills_suggest_query = "SELECT DISTINCT name FROM skills ORDER BY name LIMIT 10";
    $skills_suggest_result = $conn->query($skills_suggest_query);
    if ($skills_suggest_result) {
        while ($row = $skills_suggest_result->fetch_assoc()) {
            $search_suggestions['skills'][] = $row['name'];
        }
    }
    
    $uni_suggest_query = "SELECT DISTINCT university FROM users WHERE role = 'student' AND university IS NOT NULL AND university != '' ORDER BY university LIMIT 8";
    $uni_suggest_result = $conn->query($uni_suggest_query);
    if ($uni_suggest_result) {
        while ($row = $uni_suggest_result->fetch_assoc()) {
            $search_suggestions['universities'][] = $row['university'];
        }
    }
    
    $major_suggest_query = "SELECT DISTINCT major FROM users WHERE role = 'student' AND major IS NOT NULL AND major != '' ORDER BY major LIMIT 8";
    $major_suggest_result = $conn->query($major_suggest_query);
    if ($major_suggest_result) {
        while ($row = $major_suggest_result->fetch_assoc()) {
            $search_suggestions['majors'][] = $row['major'];
        }
    }
} catch (Exception $e) {}

$total_students = 0;
$students = [];
$total_pages = 0;

$query = "
    SELECT DISTINCT u.id, u.name, u.profile_picture, u.university, u.major, u.semester, u.specializations, u.bio 
    FROM users u
    LEFT JOIN projects p ON u.id = p.student_id
    LEFT JOIN project_skills ps ON p.id = ps.project_id
    LEFT JOIN skills s ON ps.skill_id = s.id
    WHERE u.role = 'student' AND u.eligibility_status = 'eligible'
";

$count_query = "
    SELECT COUNT(DISTINCT u.id) as total
    FROM users u
    LEFT JOIN projects p ON u.id = p.student_id
    LEFT JOIN project_skills ps ON p.id = ps.project_id
    LEFT JOIN skills s ON ps.skill_id = s.id
    WHERE u.role = 'student' AND u.eligibility_status = 'eligible'
";

$params = [];
$types = "";

if (!empty($query_filter)) {
    $query .= " AND (
        u.name LIKE ? 
        OR u.university LIKE ? 
        OR u.major LIKE ? 
        OR u.specializations LIKE ? 
        OR s.name LIKE ?
    )";
    $count_query .= " AND (
        u.name LIKE ? 
        OR u.university LIKE ? 
        OR u.major LIKE ? 
        OR u.specializations LIKE ? 
        OR s.name LIKE ?
    )";
    $search_term = "%" . $query_filter . "%";
    array_push($params, $search_term, $search_term, $search_term, $search_term, $search_term);
    $types .= "sssss";
}

if (!empty($specialization_filter)) {
    $query .= " AND u.specializations LIKE ?";
    $count_query .= " AND u.specializations LIKE ?";
    $params[] = "%" . $specialization_filter . "%";
    $types .= "s";
}

if (!empty($skill_filter)) {
    $query .= " AND s.name = ?";
    $count_query .= " AND s.name = ?";
    $params[] = $skill_filter;
    $types .= "s";
}

$query .= " GROUP BY u.id ORDER BY u.name ASC";

$is_filter_active = !empty($query_filter) || !empty($specialization_filter) || !empty($skill_filter) || $show_all;
if (!$is_filter_active) {
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $students_per_page;
    $params[] = $offset;
    $types .= "ii";
}

$count_stmt = $conn->prepare($count_query);
if ($count_stmt) {
    if (!empty($params)) {
        $count_params = array_slice($params, 0, count($params) - (!$is_filter_active ? 2 : 0));
        $count_types = $is_filter_active ? $types : substr($types, 0, -2);
        if (!empty($count_params)) {
            $count_stmt->bind_param($count_types, ...$count_params);
        }
    }
    if ($count_stmt->execute()) {
        $count_result = $count_stmt->get_result();
        $total_row = $count_result->fetch_assoc();
        $total_students = $total_row['total'];
        $total_pages = ceil($total_students / $students_per_page);
    }
    $count_stmt->close();
}

$stmt = $conn->prepare($query);
if ($stmt) {
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $students = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
}

function shortenName($full_name, $max_length = 20) {
    if (strlen($full_name) <= $max_length) {
        return $full_name;
    }
    
    $parts = explode(' ', $full_name);
    if (count($parts) <= 2) {
        return substr($full_name, 0, $max_length - 3) . '...';
    }
    
    $shortened = $parts[0] . ' ' . $parts[1];
    
    if (strlen($shortened) > $max_length - 3) {
        $shortened = $parts[0] . ' ' . substr($parts[1], 0, 3) . '.';
    } else {
        $last_word = end($parts);
        $shortened .= ' ' . substr($last_word, 0, 1) . '.';
    }
    
    return $shortened;
}

function shortenBio($bio, $max_length = 140) {
    if (empty($bio) || trim($bio) === '') {
        return 'Mahasiswa ini belum menambahkan bio.';
    }
    
    if (trim($bio) === 'Mahasiswa ini belum menambahkan bio.') {
        return $bio;
    }
    
    $clean_bio = trim(strip_tags($bio));
    
    if (strlen($clean_bio) <= $max_length) {
        return $clean_bio;
    }
    
    $shortened = substr($clean_bio, 0, $max_length);
    
    $last_space = strrpos($shortened, ' ');
    
    if ($last_space !== false && $last_space > $max_length - 20) {
        $shortened = substr($shortened, 0, $last_space);
    }
    
    return $shortened . '...';
}

$is_show_all_mode = $show_all || (empty($query_filter) && empty($specialization_filter) && empty($skill_filter));
?>

<?php include '../../includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Hero Section -->
    <div class="bg-gradient-to-r from-[#2A8FA9] to-[#51A3B9] text-white rounded-2xl p-8 mb-8 shadow-lg">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-8">
            <div class="flex-1">
                <h1 class="text-3xl lg:text-4xl font-bold mb-4 flex items-center gap-3">
                    <span class="iconify" data-icon="mdi:account-search" data-width="40"></span>
                    Halo, <?php echo htmlspecialchars($_SESSION['name']); ?>!
                </h1>
                <p class="text-[#E0F7FF] text-lg opacity-90 mb-6">Temukan talenta-talenta terbaik untuk kebutuhan perusahaan Anda.</p>
                
                <!-- Cara Kerja -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
                    <div class="bg-white/10 rounded-xl p-6 border border-white/20">
                        <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center mb-4">
                            <span class="iconify text-white" data-icon="mdi:account-multiple" data-width="24"></span>
                        </div>
                        <h3 class="text-xl font-bold mb-2">Jelajahi Talenta</h3>
                        <p class="text-[#E0F7FF] text-sm">Cari berdasarkan skill, spesialisasi, atau jurusan</p>
                    </div>
                    
                    <div class="bg-white/10 rounded-xl p-6 border border-white/20">
                        <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center mb-4">
                            <span class="iconify text-white" data-icon="mdi:file-document-edit" data-width="24"></span>
                        </div>
                        <h3 class="text-xl font-bold mb-2">Lihat Portofolio</h3>
                        <p class="text-[#E0F7FF] text-sm">Review project dan skill yang telah diverifikasi</p>
                    </div>
                    
                    <div class="bg-white/10 rounded-xl p-6 border border-white/20">
                        <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center mb-4">
                            <span class="iconify text-white" data-icon="mdi:handshake" data-width="24"></span>
                        </div>
                        <h3 class="text-xl font-bold mb-2">Hubungi & Kolaborasi</h3>
                        <p class="text-[#E0F7FF] text-sm">Mulai percakapan dan bangun tim terbaik</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filter Section -->
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 mb-8">
        <h2 class="text-2xl font-bold text-[#2A8FA9] mb-6 flex items-center gap-2">
            <span class="iconify" data-icon="mdi:magnify" data-width="28"></span>
            Cari Talenta
        </h2>
        
        <form method="GET" action="" class="space-y-6">
            <!-- Main Search -->
            <div class="relative">
                <label class="block text-sm font-medium text-gray-700 mb-2">Cari Talenta</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <span class="iconify" data-icon="mdi:magnify" data-width="20"></span>
                    </span>
                    <input type="text" 
                        name="q" 
                        value="<?php echo htmlspecialchars($query_filter); ?>" 
                        class="w-full pl-10 pr-3 py-4 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] transition-colors text-base" 
                        placeholder="Ketik nama, skill (React, Python), atau jurusan..."
                        id="main-search"
                        autocomplete="off">
                </div>
                
                <!-- Search Suggestions -->
                <div id="search-suggestions" class="hidden absolute top-full left-0 right-0 bg-white border border-gray-200 rounded-lg shadow-lg z-50 mt-1 max-h-80 overflow-y-auto">
                    <div class="p-3 border-b border-gray-100">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Suggestions</p>
                    </div>
                    
                    <?php if (!empty($search_suggestions['skills'])): ?>
                    <div class="p-2">
                        <p class="text-xs font-medium text-gray-600 mb-2 flex items-center gap-1">
                            <span class="iconify" data-icon="mdi:code-braces" data-width="12"></span>
                            Skills
                        </p>
                        <div class="flex flex-wrap gap-1">
                            <?php foreach ($search_suggestions['skills'] as $skill): ?>
                                <button type="button" class="suggestion-tag bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm hover:bg-blue-200 transition-colors" data-value="<?php echo htmlspecialchars($skill); ?>">
                                    <?php echo htmlspecialchars($skill); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($search_suggestions['majors'])): ?>
                    <div class="p-2">
                        <p class="text-xs font-medium text-gray-600 mb-2 flex items-center gap-1">
                            <span class="iconify" data-icon="mdi:book-education" data-width="12"></span>
                            Jurusan
                        </p>
                        <div class="flex flex-wrap gap-1">
                            <?php foreach ($search_suggestions['majors'] as $major): ?>
                                <button type="button" class="suggestion-tag bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-sm hover:bg-purple-200 transition-colors" data-value="<?php echo htmlspecialchars($major); ?>">
                                    <?php echo htmlspecialchars($major); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Filter Row -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Specialization Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Spesialisasi</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <span class="iconify" data-icon="mdi:tag" data-width="18"></span>
                        </span>
                        <select name="specialization" class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] text-sm appearance-none bg-white">
                            <option value="">Semua Spesialisasi</option>
                            <?php foreach ($all_specializations as $spec): ?>
                                <option value="<?php echo htmlspecialchars($spec); ?>" 
                                        <?php echo $specialization_filter == $spec ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($spec); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 pointer-events-none">
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="16"></span>
                        </span>
                    </div>
                </div>

                <!-- Skill Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Skill Tertentu</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <span class="iconify" data-icon="mdi:code-braces" data-width="18"></span>
                        </span>
                        <select name="skill" class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#51A3B9] focus:border-[#51A3B9] text-sm appearance-none bg-white">
                            <option value="">Semua Skill</option>
                            <?php foreach ($all_skills as $skill): ?>
                                <option value="<?php echo htmlspecialchars($skill['name']); ?>" 
                                        <?php echo $skill_filter == $skill['name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($skill['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 pointer-events-none">
                            <span class="iconify" data-icon="mdi:chevron-down" data-width="16"></span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-end space-x-4 pt-4">
                <button type="submit" class="bg-[#51A3B9] text-white px-8 py-3 rounded-xl font-bold hover:bg-[#409BB2] transition-colors duration-300 shadow-sm flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:magnify" data-width="20"></span>
                    Cari Talenta
                </button>
                <a href="index.php?show_all=1" class="bg-gray-200 text-gray-700 px-8 py-3 rounded-xl font-bold hover:bg-gray-300 transition-colors duration-300 flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:refresh" data-width="20"></span>
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Results Section -->
    <?php if ($is_show_all_mode || !empty($query_filter) || !empty($specialization_filter) || !empty($skill_filter) || $total_students > 0): ?>
        
        <!-- Results Info -->
        <div class="mb-6 flex justify-between items-center">
            <p class="text-gray-600 text-sm">
                Menampilkan <span class="font-bold text-[#2A8FA9]"><?php echo count($students); ?></span> dari <span class="font-bold text-[#2A8FA9]"><?php echo $total_students; ?></span> talenta
                <?php if ($is_show_all_mode && empty($query_filter) && empty($specialization_filter) && empty($skill_filter)): ?>
                    (Semua Mahasiswa Eligible)
                <?php elseif (!empty($query_filter) || !empty($specialization_filter) || !empty($skill_filter)): ?>
                    berdasarkan filter yang dipilih
                <?php elseif (!$is_filter_active): ?>
                    (Halaman <?php echo $current_page; ?> dari <?php echo $total_pages; ?>)
                <?php endif; ?>
            </p>
            
            <?php if (!empty($query_filter) || !empty($specialization_filter) || !empty($skill_filter)): ?>
            <div class="flex flex-wrap gap-2">
                <?php if (!empty($query_filter)): ?>
                    <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm flex items-center gap-1">
                        <span class="iconify" data-icon="mdi:magnify" data-width="14"></span>
                        "<?php echo htmlspecialchars($query_filter); ?>"
                        <button onclick="removeFilter('q')" class="text-blue-600 hover:text-blue-800 ml-1">
                            <span class="iconify" data-icon="mdi:close" data-width="14"></span>
                        </button>
                    </span>
                <?php endif; ?>
                <?php if (!empty($specialization_filter)): ?>
                    <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm flex items-center gap-1">
                        <span class="iconify" data-icon="mdi:tag" data-width="14"></span>
                        <?php echo htmlspecialchars($specialization_filter); ?>
                        <button onclick="removeFilter('specialization')" class="text-green-600 hover:text-green-800 ml-1">
                            <span class="iconify" data-icon="mdi:close" data-width="14"></span>
                        </button>
                    </span>
                <?php endif; ?>
                <?php if (!empty($skill_filter)): ?>
                    <span class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm flex items-center gap-1">
                        <span class="iconify" data-icon="mdi:code-braces" data-width="14"></span>
                        <?php echo htmlspecialchars($skill_filter); ?>
                        <button onclick="removeFilter('skill')" class="text-purple-600 hover:text-purple-800 ml-1">
                            <span class="iconify" data-icon="mdi:close" data-width="14"></span>
                        </button>
                    </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Talent Grid -->
        <?php if (count($students) > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
                <?php foreach ($students as $student): ?>
                    <?php
                    $skills_with_count = [];
                    try {
                        $skills_query = "
                            SELECT s.name, s.skill_type, COUNT(DISTINCT p.id) as project_count 
                            FROM project_skills ps 
                            JOIN projects p ON ps.project_id = p.id
                            JOIN skills s ON ps.skill_id = s.id 
                            WHERE p.student_id = ?
                            GROUP BY s.id
                            ORDER BY project_count DESC, s.skill_type, s.name
                            LIMIT 10
                        ";
                        $skills_stmt = $conn->prepare($skills_query);
                        if ($skills_stmt) {
                            $skills_stmt->bind_param("i", $student['id']);
                            $skills_stmt->execute();
                            $skills_result = $skills_stmt->get_result();
                            while ($skill_row = $skills_result->fetch_assoc()) {
                                $skills_with_count[] = $skill_row;
                            }
                            $skills_stmt->close();
                        }
                    } catch (Exception $e) {}
                    
                    $display_name = shortenName($student['name'], 22);
                    $display_bio = shortenBio($student['bio'] ?? '');
                    ?>
                    
                    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden hover:shadow-lg transition-all duration-300 group talent-card h-full flex flex-col">
                        <!-- Profile Info -->
                        <a href="student-profile.php?id=<?php echo $student['id']; ?>" class="block flex-1 p-5">
                            <div class="flex items-center space-x-4 mb-4">
                                <?php if (!empty($student['profile_picture'])): ?>
                                    <img class="h-16 w-16 rounded-full object-cover border-2 border-[#E0F7FF]" 
                                        src="<?php echo htmlspecialchars($student['profile_picture']); ?>" 
                                        alt="<?php echo htmlspecialchars($student['name']); ?>">
                                <?php else: ?>
                                    <div class="h-16 w-16 rounded-full bg-gradient-to-br from-[#51A3B9] to-[#2A8FA9] flex items-center justify-center border-2 border-[#E0F7FF]">
                                        <span class="iconify text-white" data-icon="mdi:account" data-width="32"></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-lg font-bold text-[#2A8FA9] group-hover:text-[#51A3B9] transition-colors truncate" 
                                        title="<?php echo htmlspecialchars($student['name']); ?>">
                                        <?php echo htmlspecialchars($display_name); ?>
                                    </h3>
                                    <p class="text-gray-600 text-sm truncate" title="<?php echo htmlspecialchars($student['major'] ?? 'Jurusan'); ?>">
                                        <?php echo htmlspecialchars($student['major'] ?? 'Jurusan'); ?>
                                    </p>
                                    <p class="text-gray-500 text-sm truncate" title="Semester <?php echo htmlspecialchars($student['semester'] ?? ''); ?>">
                                        Semester <?php echo htmlspecialchars($student['semester'] ?? ''); ?>
                                    </p>
                                </div>
                            </div>

                            <div class="mb-1 flex-1 min-h-[96px]">
                                <p class="text-gray-700 text-sm leading-relaxed break-words">
                                    <?php 
                                    echo htmlspecialchars($display_bio, ENT_QUOTES, 'UTF-8');
                                    ?>
                                </p>
                            </div>
                        </a>

                        <!-- Skills Section dengan Arrow Scroll -->
                        <?php if (!empty($skills_with_count)): ?>
                        <div class="px-0 pb-1">
                            <div class="flex items-center gap-0">
                                <!-- Left Scroll Button -->
                                <button class="skill-scroll-btn flex-shrink-0 bg-gray-100 hover:bg-gray-200 text-gray-600 hover:text-[#51A3B9] rounded-full p-1 transition-all duration-200"
                                        onclick="scrollSkills(this, -120)">
                                    <span class="iconify" data-icon="mdi:chevron-left" data-width="16"></span>
                                </button>
                                
                                <!-- Skills Container -->
                                <div class="horizontal-scroll flex flex-nowrap gap-2 overflow-x-auto flex-1"
                                    style="scrollbar-width: none; -ms-overflow-style: none;">
                                    <?php foreach ($skills_with_count as $skill): 
                                        $color_class = [
                                            'technical' => 'bg-blue-100 text-blue-800',
                                            'soft' => 'bg-green-100 text-green-800',
                                            'tool' => 'bg-purple-100 text-purple-800'
                                        ][$skill['skill_type']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                        <span class="inline-block <?php echo $color_class; ?> text-xs px-3 py-1 rounded-full font-medium whitespace-nowrap flex-shrink-0"
                                            title="Digunakan di <?php echo $skill['project_count']; ?> project">
                                            <?php echo htmlspecialchars($skill['name']); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Right Scroll Button -->
                                <button class="skill-scroll-btn flex-shrink-0 bg-gray-100 hover:bg-gray-200 text-gray-600 hover:text-[#51A3B9] rounded-full p-1 transition-all duration-200"
                                        onclick="scrollSkills(this, 120)">
                                    <span class="iconify" data-icon="mdi:chevron-right" data-width="16"></span>
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- View Profile Button -->
                        <div class="p-4 bg-gray-50 border-t border-gray-100">
                            <a href="student-profile.php?id=<?php echo $student['id']; ?>" 
                            class="block w-full text-center bg-[#51A3B9] text-white px-4 py-2 rounded-lg font-semibold hover:bg-[#409BB2] transition-colors duration-300 text-sm">
                                Lihat Profil
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination Navigation -->
            <?php if (!$is_filter_active && $total_pages > 1): ?>
            <div class="flex justify-center items-center space-x-4 mt-8 mb-8">
                <!-- Previous Button -->
                <a href="?page=<?php echo max(1, $current_page - 1); ?>" 
                class="flex items-center gap-2 px-6 py-3 bg-white border border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-50 transition-colors duration-300 <?php echo $current_page == 1 ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                    <span class="iconify" data-icon="mdi:chevron-left" data-width="20"></span>
                    Sebelumnya
                </a>

                <!-- Page Info -->
                <div class="flex items-center gap-2 bg-[#E0F7FF] rounded-xl px-6 py-3">
                    <span class="text-[#2A8FA9] font-bold">Halaman <?php echo $current_page; ?> dari <?php echo $total_pages; ?></span>
                </div>

                <!-- Next Button -->
                <a href="?page=<?php echo min($total_pages, $current_page + 1); ?>" 
                class="flex items-center gap-2 px-6 py-3 bg-white border border-gray-300 rounded-xl font-semibold text-gray-700 hover:bg-gray-50 transition-colors duration-300 <?php echo $current_page == $total_pages ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                    Selanjutnya
                    <span class="iconify" data-icon="mdi:chevron-right" data-width="20"></span>
                </a>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Empty Search Results -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-12 text-center">
                <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-6">
                    <span class="iconify text-gray-400" data-icon="mdi:account-search" data-width="48"></span>
                </div>
                <h3 class="text-2xl font-bold text-[#2A8FA9] mb-3">Tidak ada talenta yang ditemukan</h3>
                <p class="text-gray-600 text-lg mb-8 max-w-md mx-auto">Coba ubah filter pencarian Anda atau lihat semua talenta tanpa filter.</p>
                <a href="index.php?show_all=1" class="bg-[#51A3B9] text-white px-8 py-3 rounded-xl font-bold hover:bg-[#409BB2] transition-colors duration-300 inline-flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:refresh" data-width="20"></span>
                    Tampilkan Semua Mahasiswa Eligible
                </a>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <!-- Empty State -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-12 text-center">
            <div class="mx-auto w-24 h-24 bg-[#E0F7FF] rounded-full flex items-center justify-center mb-6">
                <span class="iconify text-[#51A3B9]" data-icon="mdi:magnify" data-width="48"></span>
            </div>
            <h3 class="text-2xl font-bold text-[#2A8FA9] mb-3">Mulai Pencarian Talenta</h3>
            <p class="text-gray-600 text-lg mb-8 max-w-md mx-auto">Gunakan form pencarian di atas untuk menemukan talenta terbaik berdasarkan skill, spesialisasi, atau latar belakang pendidikan.</p>
            
            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-2xl mx-auto mb-8">
                <div class="bg-[#E0F7FF] rounded-xl p-4 border border-[#51A3B9]">
                    <div class="text-2xl font-bold text-[#2A8FA9] mb-1">
                        <?php 
                        $total_students_count = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'student' AND eligibility_status = 'eligible'")->fetch_assoc()['total'];
                        echo $total_students_count;
                        ?>
                    </div>
                    <div class="text-[#409BB2] text-sm">Talenta Eligible</div>
                </div>
                <div class="bg-green-50 rounded-xl p-4 border border-green-100">
                    <div class="text-2xl font-bold text-green-900 mb-1">
                        <?php echo count($all_specializations); ?>
                    </div>
                    <div class="text-green-700 text-sm">Spesialisasi</div>
                </div>
                <div class="bg-purple-50 rounded-xl p-4 border border-purple-100">
                    <div class="text-2xl font-bold text-purple-900 mb-1">
                        <?php echo count($all_skills); ?>
                    </div>
                    <div class="text-purple-700 text-sm">Skills Tersedia</div>
                </div>
            </div>
            
            <div class="flex justify-center gap-4">
                <a href="index.php?show_all=1" class="bg-[#51A3B9] text-white px-8 py-3 rounded-xl font-bold hover:bg-[#409BB2] transition-colors duration-300 inline-flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:account-multiple" data-width="20"></span>
                    Lihat Semua Talenta Eligible
                </a>
                <button onclick="document.getElementById('main-search').focus()" class="bg-[#2A8FA9] text-white px-8 py-3 rounded-xl font-bold hover:bg-[#409BB2] transition-colors duration-300 inline-flex items-center gap-2">
                    <span class="iconify" data-icon="mdi:magnify" data-width="20"></span>
                    Mulai Pencarian
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.line-clamp-4 {
    display: -webkit-box;
    -webkit-line-clamp: 4;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.horizontal-scroll::-webkit-scrollbar {
    display: none;
}

.talent-card {
    display: flex;
    flex-direction: column;
    min-height: 280px;
}

.talent-card > a {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.talent-card .line-clamp-4 {
    display: -webkit-box;
    -webkit-line-clamp: 4;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 96px;
    line-height: 1.5;
    margin-bottom: -8px; 
}


.talent-card .px-5.pb-2 {
    margin-top: auto;
    margin-top: 4px; 
}

.talent-card > a {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding-bottom: 8px;
}

.talent-card:hover {
    transform: translateY(-4px);
}

.skill-scroll-btn {
    opacity: 0.7;
}
.skill-scroll-btn:hover {
    opacity: 1;
}

.suggestion-tag {
    transition: all 0.2s ease;
}
.suggestion-tag:hover {
    transform: translateY(-1px);
}
</style>

<script>
function scrollSkills(button, scrollAmount) {
    event.stopPropagation();
    event.preventDefault();
    
    const skillsContainer = button.closest('.flex').querySelector('.horizontal-scroll');
    if (skillsContainer) {
        skillsContainer.scrollBy({
            left: scrollAmount,
            behavior: 'smooth'
        });
    }
}

function updateArrowVisibility(container, leftButton, rightButton) {
    if (!container || !leftButton || !rightButton) return;
    
    if (container.scrollLeft > 0) {
        leftButton.style.opacity = '0.7';
    } else {
        leftButton.style.opacity = '0.3';
    }
    
    if (container.scrollLeft < (container.scrollWidth - container.clientWidth - 1)) {
        rightButton.style.opacity = '0.7';
    } else {
        rightButton.style.opacity = '0.3';
    }
}

function removeFilter(filterName) {
    const url = new URL(window.location.href);
    url.searchParams.delete(filterName);
    window.location.href = url.toString();
}

document.addEventListener('DOMContentLoaded', function() {
    const talentCards = document.querySelectorAll('.talent-card');
    
    talentCards.forEach(card => {
        const skillsContainer = card.querySelector('.horizontal-scroll');
        const leftButton = card.querySelector('.skill-scroll-btn:first-child');
        const rightButton = card.querySelector('.skill-scroll-btn:last-child');
        
        if (skillsContainer && leftButton && rightButton) {
            updateArrowVisibility(skillsContainer, leftButton, rightButton);
            
            skillsContainer.addEventListener('scroll', () => {
                updateArrowVisibility(skillsContainer, leftButton, rightButton);
            });
        }
    });

    const searchInput = document.getElementById('main-search');
    const suggestionsBox = document.getElementById('search-suggestions');
    
    if (searchInput && suggestionsBox) {
        searchInput.addEventListener('focus', function() {
            if (this.value.length === 0) {
                suggestionsBox.classList.remove('hidden');
            }
        });
        
        searchInput.addEventListener('input', function() {
            if (this.value.length > 0) {
                suggestionsBox.classList.remove('hidden');
            } else {
                suggestionsBox.classList.remove('hidden'); 
            }
        });
        
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
                suggestionsBox.classList.add('hidden');
            }
        });
        
        const suggestionTags = document.querySelectorAll('.suggestion-tag');
        suggestionTags.forEach(tag => {
            tag.addEventListener('click', function() {
                searchInput.value = this.getAttribute('data-value');
                suggestionsBox.classList.add('hidden');
                searchInput.focus();
            });
        });
        
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                suggestionsBox.classList.add('hidden');
                this.form.submit();
            }
        });
    }

    let searchTimeout;
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
            }, 500);
        });
    }
});
</script>

<?php include '../../includes/footer.php'; ?>