<?php
session_start();
require_once '../config/database.php';
require_once '../includes/Database.class.php';
require_once '../includes/Auth.class.php';
require_once '../includes/Utils.class.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    Utils::sendJsonResponse(['error' => 'Não autenticado'], 401);
}

$db = Database::getInstance();

$period = $_GET['period'] ?? 'today'; // today, week, month, year
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

// Definir período
switch ($period) {
    case 'today':
        $dateCondition = "DATE(created_at) = CURDATE()";
        break;
    case 'week':
        $dateCondition = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $dateCondition = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
    case 'year':
        $dateCondition = "created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
        break;
    default:
        if ($startDate && $endDate) {
            $dateCondition = "created_at BETWEEN '$startDate' AND '$endDate 23:59:59'";
        } else {
            $dateCondition = "1=1";
        }
}

// Estatísticas gerais
$stats = [];

// Total de pacientes
$result = $db->query("SELECT COUNT(*) as total FROM patients");
$stats['total_patients'] = $result->fetch_assoc()['total'];

// Total de exames
$result = $db->query("SELECT COUNT(*) as total FROM exams");
$stats['total_exams'] = $result->fetch_assoc()['total'];

// Exames com laudo
$result = $db->query("SELECT COUNT(*) as total FROM exams WHERE pdf_processed = TRUE");
$stats['exams_with_report'] = $result->fetch_assoc()['total'];

// Taxa de cobertura
$stats['coverage_rate'] = $stats['total_exams'] > 0 ? 
    round(($stats['exams_with_report'] / $stats['total_exams']) * 100, 2) : 0;

// Exames por status
$result = $db->query("
    SELECT status, COUNT(*) as count 
    FROM exams 
    GROUP BY status
");
$stats['exams_by_status'] = [];
while ($row = $result->fetch_assoc()) {
    $stats['exams_by_status'][$row['status']] = $row['count'];
}

// Exames recentes (últimos 7 dias)
$result = $db->query("
    SELECT 
        DATE(exam_date) as date,
        COUNT(*) as count,
        SUM(CASE WHEN pdf_processed = TRUE THEN 1 ELSE 0 END) as with_report
    FROM exams
    WHERE exam_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(exam_date)
    ORDER BY date
");
$stats['recent_exams'] = [];
while ($row = $result->fetch_assoc()) {
    $stats['recent_exams'][] = $row;
}

// Médicos mais ativos
$result = $db->query("
    SELECT d.name, COUNT(e.id) as exam_count
    FROM exams e
    INNER JOIN doctors d ON e.responsible_doctor_id = d.id
    GROUP BY d.id, d.name
    ORDER BY exam_count DESC
    LIMIT 5
");
$stats['top_doctors'] = [];
while ($row = $result->fetch_assoc()) {
    $stats['top_doctors'][] = $row;
}

// Pacientes com mais exames
$result = $db->query("
    SELECT p.full_name, COUNT(e.id) as exam_count
    FROM exams e
    INNER JOIN patients p ON e.patient_id = p.id
    GROUP BY p.id, p.full_name
    ORDER BY exam_count DESC
    LIMIT 10
");
$stats['top_patients'] = [];
while ($row = $result->fetch_assoc()) {
    $stats['top_patients'][] = $row;
}

// Sincronizações recentes
$result = $db->query("
    SELECT 
        sync_type,
        status,
        COUNT(*) as count,
        AVG(processing_time) as avg_time
    FROM sync_logs
    WHERE processed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY sync_type, status
");
$stats['sync_stats'] = [];
while ($row = $result->fetch_assoc()) {
    $stats['sync_stats'][] = $row;
}

Utils::sendJsonResponse($stats);
?>