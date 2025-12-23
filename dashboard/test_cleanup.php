<?php
session_start();
require_once '../config/database.php';
require_once '../includes/Database.class.php';

$db = Database::getInstance();

// Teste 1: Limpeza normal
$cutoffDate = date('Y-m-d H:i:s', strtotime('-7 days'));
$query = "DELETE FROM sync_logs WHERE processed_at < ?";
$stmt = $db->prepare($query);
$stmt->bind_param('s', $cutoffDate);
$stmt->execute();
echo "Limpeza normal: " . $stmt->affected_rows . " registros removidos<br>";

// Teste 2: Limpeza de erros
$cutoffDate = date('Y-m-d H:i:s', strtotime('-1 day'));
$query = "DELETE FROM sync_logs WHERE sync_type IN ('wxml', 'pdf') AND status = 'error' AND processed_at < ?";
$stmt = $db->prepare($query);
$stmt->bind_param('s', $cutoffDate);
$stmt->execute();
echo "Limpeza de erros: " . $stmt->affected_rows . " registros removidos<br>";
?>