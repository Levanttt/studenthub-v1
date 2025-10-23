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

    // DEBUG: Log informasi
    error_log("Like Handler - User: $user_id, Project: $project_id, Action: $action");

    // Initialize session cache - LOAD FROM DATABASE jika kosong
    if (!isset($_SESSION['likes_cache']) || isset($_GET['force_sync'])) {
        $_SESSION['likes_cache'] = [];
        
        // Load existing likes from database untuk user ini
        $load_sql = "SELECT project_id FROM project_likes WHERE mitra_industri_id = ?";
        $load_stmt = $conn->prepare($load_sql);
        $load_stmt->bind_param("i", $user_id);
        
        if ($load_stmt->execute()) {
            $result = $load_stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $_SESSION['likes_cache'][] = $row['project_id'];
            }
            $_SESSION['likes_cache'] = array_unique($_SESSION['likes_cache']);
            error_log("Loaded " . count($_SESSION['likes_cache']) . " likes from database for user $user_id");
        } else {
            error_log("Error loading likes from database: " . $load_stmt->error);
        }
        $load_stmt->close();
    }

    // Debug info
    $debug_sql = "SELECT COUNT(*) as db_likes FROM project_likes WHERE mitra_industri_id = ?";
    $debug_stmt = $conn->prepare($debug_sql);
    $debug_stmt->bind_param("i", $user_id);
    $debug_stmt->execute();
    $db_count = $debug_stmt->get_result()->fetch_assoc()['db_likes'];
    $debug_stmt->close();

    error_log("Session likes: " . count($_SESSION['likes_cache']) . ", Database likes: $db_count, Project: $project_id");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get current state from database
        $check_sql = "SELECT id FROM project_likes WHERE project_id = ? AND mitra_industri_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $project_id, $user_id);
        $check_stmt->execute();
        $current_state = $check_stmt->get_result()->num_rows > 0;
        $check_stmt->close();

        error_log("Current state from DB: " . ($current_state ? 'liked' : 'not liked'));

        if ($action === 'like') {
            if (!$current_state) {
                // Insert like to database
                $insert_sql = "INSERT INTO project_likes (project_id, mitra_industri_id) VALUES (?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("ii", $project_id, $user_id);
                
                if ($insert_stmt->execute()) {
                    // Update session cache
                    if (!in_array($project_id, $_SESSION['likes_cache'])) {
                        $_SESSION['likes_cache'][] = $project_id;
                    }
                    $_SESSION['likes_cache'] = array_unique($_SESSION['likes_cache']);
                    
                    error_log("Successfully liked project $project_id");
                    jsonResponse(['success' => true, 'action' => 'liked', 'new_state' => true]);
                } else {
                    error_log("Failed to like project $project_id: " . $insert_stmt->error);
                    jsonResponse(['success' => false, 'error' => 'Failed to like']);
                }
                $insert_stmt->close();
            } else {
                error_log("Project $project_id already liked by user $user_id");
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
                    
                    error_log("Successfully unliked project $project_id");
                    jsonResponse(['success' => true, 'action' => 'unliked', 'new_state' => false]);
                } else {
                    error_log("Failed to unlike project $project_id: " . $delete_stmt->error);
                    jsonResponse(['success' => false, 'error' => 'Failed to unlike']);
                }
                $delete_stmt->close();
            } else {
                error_log("Project $project_id not liked by user $user_id (cannot unlike)");
                jsonResponse(['success' => false, 'error' => 'not_liked']);
            }
        } else {
            error_log("Invalid action: $action");
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

    // Sync session cache with database (additional safety)
    $is_liked_session = in_array($project_id, $_SESSION['likes_cache']);
    
    if ($is_liked_db && !$is_liked_session) {
        // Database says liked, but session doesn't - fix session
        $_SESSION['likes_cache'][] = $project_id;
        $_SESSION['likes_cache'] = array_unique($_SESSION['likes_cache']);
        error_log("Fixed session cache - added project $project_id");
    } elseif (!$is_liked_db && $is_liked_session) {
        // Session says liked, but database doesn't - fix session
        $_SESSION['likes_cache'] = array_values(array_diff($_SESSION['likes_cache'], [$project_id]));
        error_log("Fixed session cache - removed project $project_id");
    }

    error_log("Final state - DB: " . ($is_liked_db ? 'liked' : 'not liked') . ", Session: " . ($is_liked_session ? 'liked' : 'not liked'));

    jsonResponse([
        'success' => true, 
        'is_liked' => $is_liked_db,
        'source' => 'database',
        'session_count' => count($_SESSION['likes_cache']),
        'db_count' => $db_count
    ]);

} catch (Exception $e) {
    error_log("Like handler exception: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
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
        
        error_log("Reset all likes for user $user_id");
        jsonResponse(['success' => true, 'message' => 'All likes cleared']);
    } catch (Exception $e) {
        error_log("Reset failed: " . $e->getMessage());
        jsonResponse(['success' => false, 'error' => 'Clear failed']);
    }
}

// Force sync for testing
if (isset($_GET['sync'])) {
    try {
        $user_id = $_SESSION['user_id'];
        unset($_SESSION['likes_cache']); // Force reload on next request
        
        jsonResponse(['success' => true, 'message' => 'Session cache cleared, will reload from database on next request']);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => 'Sync failed']);
    }
}
?>