<?php
// api/update_connection_status.php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $_SESSION['connection_status'] = $data['status'] ?? 'checking';
    $_SESSION['connection_message'] = $data['message'] ?? '';
    $_SESSION['connection_check_time'] = time();
    
    echo json_encode(['success' => true]);
}
?>