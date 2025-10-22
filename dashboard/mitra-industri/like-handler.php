<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

// Function untuk handle response
function jsonResponse($data) {
    echo json_encode($data);
    exit();
}

// Check if session is started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

try {
    // Include files
    $base_dir = __DIR__ . '/../../';
    
    if (!@include($base_dir . 'includes/config.php')) {
        jsonResponse(['success' => false, 'error' => 'Config not found']);
    }
    
    if (!@include($base_dir . 'includes/functions.php')) {
        jsonResponse(['success' => false, 'error' => 'Functions not found']);
    }

    // Cek autentikasi
    if (!isLoggedIn()) {
        jsonResponse(['success' => false, 'error' => 'Not logged in']);
    }

    if (getUserRole() != 'mitra_industri') {
        jsonResponse(['success' => false, 'error' => 'Not mitra_industri']);
    }

    $user_id = $_SESSION['user_id'];
    $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // Initialize session cache
    if (!isset($_SESSION['likes_cache'])) {
        $_SESSION['likes_cache'] = [];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get current state from database
        $check_sql = "SELECT id FROM project_likes WHERE project_id = ? AND mitra_industri_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $project_id, $user_id);
        $check_stmt->execute();
        $current_state = $check_stmt->get_result()->num_rows > 0;
        $check_stmt->close();

        if ($action === 'like') {
            if (!$current_state) {
                // Insert like to database
                $insert_sql = "INSERT INTO project_likes (project_id, mitra_industri_id) VALUES (?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("ii", $project_id, $user_id);
                
                if ($insert_stmt->execute()) {
                    // Update session cache
                    $_SESSION['likes_cache'][] = $project_id;
                    $_SESSION['likes_cache'] = array_unique($_SESSION['likes_cache']);
                    jsonResponse(['success' => true, 'action' => 'liked', 'new_state' => true]);
                } else {
                    jsonResponse(['success' => false, 'error' => 'Failed to like']);
                }
            } else {
                jsonResponse(['success' => false, 'error' => 'already_liked']);
            }
            
        } elseif ($action === 'unlike') {
            if ($current_state) {
                // Delete like from database
                $delete_sql = "DELETE FROM project_likes WHERE project_id = ? AND mitra_industri_id = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("ii", $project_id, $user_id);
                
                if ($delete_stmt->execute()) {
                    // Update session cache
                    $_SESSION['likes_cache'] = array_values(array_diff($_SESSION['likes_cache'], [$project_id]));
                    jsonResponse(['success' => true, 'action' => 'unliked', 'new_state' => false]);
                } else {
                    jsonResponse(['success' => false, 'error' => 'Failed to unlike']);
                }
            } else {
                jsonResponse(['success' => false, 'error' => 'not_liked']);
            }
        } else {
            jsonResponse(['success' => false, 'error' => 'invalid_action']);
        }
    }

    // GET request - return current state
    $check_sql = "SELECT id FROM project_likes WHERE project_id = ? AND mitra_industri_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $project_id, $user_id);
    $check_stmt->execute();
    $is_liked_db = $check_stmt->get_result()->num_rows > 0;
    $check_stmt->close();

    // Sync session cache with database
    $is_liked_session = in_array($project_id, $_SESSION['likes_cache']);
    if ($is_liked_db && !$is_liked_session) {
        $_SESSION['likes_cache'][] = $project_id;
    } elseif (!$is_liked_db && $is_liked_session) {
        $_SESSION['likes_cache'] = array_values(array_diff($_SESSION['likes_cache'], [$project_id]));
    }

    jsonResponse([
        'success' => true, 
        'is_liked' => $is_liked_db,
        'source' => 'database'
    ]);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => 'Server error']);
}

// Reset for testing
if (isset($_GET['reset'])) {
    try {
        $user_id = $_SESSION['user_id'];
        // Clear database likes
        $clear_sql = "DELETE FROM project_likes WHERE mitra_industri_id = ?";
        $clear_stmt = $conn->prepare($clear_sql);
        $clear_stmt->bind_param("i", $user_id);
        $clear_stmt->execute();
        $clear_stmt->close();
        
        // Clear session cache
        $_SESSION['likes_cache'] = [];
        
        jsonResponse(['success' => true, 'message' => 'All likes cleared']);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => 'Clear failed']);
    }
}
?>