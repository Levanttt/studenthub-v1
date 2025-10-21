<?php
// test-profile-view.php - Letakkan di dashboard/stakeholder/
echo "Current directory: " . __DIR__ . "<br>";

// Coba berbagai path
$possible_paths = [
    '../../includes/config.php',
    '../../../includes/config.php', 
    '/includes/config.php'
];

$config_loaded = false;
foreach ($possible_paths as $path) {
    echo "Trying: $path<br>";
    if (file_exists($path)) {
        include $path;
        $config_loaded = true;
        echo "✅ Successfully loaded: $path<br>";
        break;
    }
}

if (!$config_loaded) {
    die("❌ Could not find config.php. Check the file structure.<br>");
}

// Load functions
include '../../includes/functions.php';
echo "✅ Functions loaded<br>";

// Test koneksi database
if ($conn) {
    echo "✅ Database connected successfully<br>";
    
    // Test apakah tabel profile_views ada
    $table_check = $conn->query("SHOW TABLES LIKE 'profile_views'");
    if ($table_check->num_rows > 0) {
        echo "✅ Table 'profile_views' exists<br>";
        
        // Test manual insert
        $student_id = 2; // Ganti dengan ID student yang valid
        $viewer_id = 1;  // Ganti dengan ID stakeholder yang valid
        
        $insert_sql = "INSERT INTO profile_views (student_id, viewer_id, viewer_role, viewer_ip, user_agent) 
                       VALUES (?, ?, 'stakeholder', ?, 'Test Browser')";
        
        $stmt = $conn->prepare($insert_sql);
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param("iis", $student_id, $viewer_id, $ip);
        
        if ($stmt->execute()) {
            echo "✅ Manual insert successful<br>";
        } else {
            echo "❌ Manual insert failed: " . $stmt->error . "<br>";
        }
        
        // Tampilkan data
        $result = $conn->query("SELECT * FROM profile_views WHERE student_id = $student_id ORDER BY viewed_at DESC");
        echo "Total views for student $student_id: " . $result->num_rows . "<br>";
        
        while ($row = $result->fetch_assoc()) {
            echo "View: {$row['viewer_role']} at {$row['viewed_at']} (IP: {$row['viewer_ip']})<br>";
        }
        
    } else {
        echo "❌ Table 'profile_views' does NOT exist<br>";
    }
    
} else {
    echo "❌ Database connection failed<br>";
}
?>