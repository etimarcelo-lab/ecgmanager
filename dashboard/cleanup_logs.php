<?php
// cleanup_logs.php
session_start();
require_once '../config/database.php';
require_once '../includes/Database.class.php';
require_once '../includes/Auth.class.php';
require_once '../includes/SyncLogger.class.php';

$auth = new Auth();
$auth->requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance();
    $logger = new SyncLogger();
    
    try {
        // Calcular data de corte (30 dias atrás)
        $cutoffDate = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        // Primeiro, contar quantos registros serão removidos
        $countQuery = "SELECT COUNT(*) as total FROM sync_logs WHERE processed_at < ?";
        $stmt = $db->prepare($countQuery);
        $stmt->bind_param('s', $cutoffDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['total'];
        
        if ($count > 0) {
            // Deletar os logs
            $deleteQuery = "DELETE FROM sync_logs WHERE processed_at < ?";
            $stmt = $db->prepare($deleteQuery);
            $stmt->bind_param('s', $cutoffDate);
            $stmt->execute();
            
            $affectedRows = $stmt->affected_rows;
            
            $logger->log('log_cleanup', 'success', "Logs antigos limpos: {$affectedRows} registros removidos");
            
            $_SESSION['success_message'] = "{$affectedRows} logs antigos (mais de 30 dias) foram removidos com sucesso.";
        } else {
            $_SESSION['info_message'] = "Não há logs antigos para limpar.";
        }
        
    } catch (Exception $e) {
        $logger->log('log_cleanup', 'error', "Erro ao limpar logs: " . $e->getMessage());
        $_SESSION['error_message'] = "Erro ao limpar logs: " . $e->getMessage();
    }
    
    header('Location: logs.php');
    exit;
}

// Se não for POST, redirecionar para logs.php
header('Location: logs.php');
exit;
?>