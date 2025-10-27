<?php
function sanitize($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim(htmlspecialchars(strip_tags($data), ENT_QUOTES, 'UTF-8')));
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function formatText($text) {
    if (empty($text)) return $text;
    return ucfirst(strtolower(trim($text)));
}

function getStatusBadge($status) {
    $statusLower = strtolower($status);
    $formattedStatus = formatText($status);
    
    $colorClasses = [
        'completed' => 'bg-green-500',
        'in progress' => 'bg-yellow-500',
        'planned' => 'bg-blue-500'
    ];
    
    $color = $colorClasses[$statusLower] ?? 'bg-gray-500';
    
    return '<span class="font-semibold text-white px-2 py-1 rounded-full text-xs ' . $color . '">' . $formattedStatus . '</span>';
}

function getEligibilityBadge($status) {
    $statusLower = strtolower($status);
    
    $statusConfig = [
        'eligible' => [
            'color' => 'bg-[#E0F7FF] text-[#2A8FA9] border-[#51A3B9] border-opacity-30',
            'icon' => 'mdi:check-circle',
            'iconColor' => 'text-[#2A8FA9]',
            'text' => 'ELIGIBLE'
        ],
        'not_eligible' => [
            'color' => 'bg-[#E0F7FF] text-[#2A8FA9] border-[#51A3B9] border-opacity-30',
            'icon' => 'mdi:close-circle',
            'iconColor' => 'text-[#2A8FA9]', 
            'text' => 'NOT ELIGIBLE'
        ],
        'pending' => [
            'color' => 'bg-[#E0F7FF] text-[#2A8FA9] border-[#51A3B9] border-opacity-30',
            'icon' => 'mdi:clock',
            'iconColor' => 'text-[#2A8FA9]',
            'text' => 'PENDING'
        ]
    ];
    
    $config = $statusConfig[$statusLower] ?? [
        'color' => 'bg-[#E0F7FF] text-[#2A8FA9] border-[#51A3B9] border-opacity-30',
        'icon' => 'mdi:help-circle',
        'iconColor' => 'text-[#2A8FA9]',
        'text' => 'BELUM DIATUR'
    ];
    
    return $config;
}

function recordProfileView($student_id, $viewer_id = null, $viewer_role = 'other') {
    global $conn;
    
    if (!$student_id) {
        error_log("Error: student_id is required");
        return false;
    }
    
    if ($viewer_id) {
        $role_stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $role_stmt->bind_param("i", $viewer_id);
        $role_stmt->execute();
        $role_result = $role_stmt->get_result();
        if ($role_result->num_rows > 0) {
            $user_role = $role_result->fetch_assoc()['role'];
            if ($user_role === 'mitra_industri') {
                $viewer_role = 'mitra_industri';
            }
        }
    }
    
    $ip = $_SERVER['REMOTE_ADDR'];
    $check_stmt = $conn->prepare("
        SELECT id FROM profile_views 
        WHERE student_id = ? AND viewer_ip = ? AND viewed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        LIMIT 1
    ");
    $check_stmt->bind_param("is", $student_id, $ip);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows == 0) {
        $insert_stmt = $conn->prepare("
            INSERT INTO profile_views (student_id, viewer_id, viewer_role, viewer_ip, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $insert_stmt->bind_param("iisss", $student_id, $viewer_id, $viewer_role, $ip, $_SERVER['HTTP_USER_AGENT']);
        
        if ($insert_stmt->execute()) {
            error_log("✅ NEW profile view recorded - Student: $student_id, Viewer: $viewer_id, Role: $viewer_role");
            return true;
        } else {
            error_log("❌ FAILED to record profile view: " . $insert_stmt->error);
            return false;
        }
    } else {
        error_log("⏩ DUPLICATE profile view skipped - Student: $student_id, IP: $ip");
        return false; 
    }
}

function getProfileViewsCount($student_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_views 
        FROM profile_views 
        WHERE student_id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['total_views'];
}

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'tahun',
        'm' => 'bulan',
        'w' => 'minggu',
        'd' => 'hari',
        'h' => 'jam',
        'i' => 'menit',
        's' => 'detik',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? '' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' yang lalu' : 'baru saja';
}
?>