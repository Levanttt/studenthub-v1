<?php
include '../../includes/config.php';
include '../../includes/functions.php';

if (!isLoggedIn() || getUserRole() != 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['student_id']) || !isset($_POST['availability_status'])) {
            throw new Exception('Data tidak lengkap');
        }
        
        $student_id = intval($_POST['student_id']);
        $availability_status = $_POST['availability_status'];
        
        $allowed_statuses = ['available', 'interview', 'accepted', 'inactive'];
        if (!in_array($availability_status, $allowed_statuses)) {
            throw new Exception('Status tidak valid');
        }
        
        // Mulai transaction
        $conn->begin_transaction();
        
        try {
            // 1. Update status availability mahasiswa
            $update_stmt = $conn->prepare("UPDATE users SET availability_status = ? WHERE id = ?");
            $update_stmt->bind_param('si', $availability_status, $student_id);
            
            if (!$update_stmt->execute()) {
                throw new Exception('Gagal update status availability: ' . $update_stmt->error);
            }
            
            // 2. LOGIKA RESET: Jika status diubah kembali ke 'available', hapus semua interest
            if ($availability_status === 'available') {
                $delete_stmt = $conn->prepare("DELETE FROM mitra_interest WHERE student_id = ?");
                $delete_stmt->bind_param('i', $student_id);
                
                if (!$delete_stmt->execute()) {
                    throw new Exception('Gagal reset interest: ' . $delete_stmt->error);
                }
                
                $delete_stmt->close();
                
                // Log reset activity
                error_log("🔄 RESET: Student ID $student_id diubah ke available, semua interest dihapus");
            }
            
            $update_stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Status ketersediaan berhasil diupdate' . 
                            ($availability_status === 'available' ? ' dan riwayat interest direset' : ''),
                'student_id' => $student_id,
                'new_status' => $availability_status,
                'reset_performed' => ($availability_status === 'available')
            ]);
            
        } catch (Exception $e) {
            // Rollback transaction jika ada error
            $conn->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Update availability error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Gagal mengupdate status: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Method tidak diizinkan'
    ]);
}
?>