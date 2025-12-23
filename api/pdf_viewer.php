<?php
// api/pdf_viewer.php
session_start();
require_once '../config/database.php';
require_once '../includes/Database.class.php';

$db = Database::getInstance();

// Verificar se recebeu report_id ou exam_id
$reportId = $_GET['report_id'] ?? 0;
$examId = $_GET['exam_id'] ?? 0;

if ($reportId) {
    // Buscar pelo ID do relatório
    $stmt = $db->prepare("
        SELECT pr.*, e.exam_number, p.full_name as patient_name
        FROM pdf_reports pr
        INNER JOIN exams e ON pr.exam_id = e.id
        INNER JOIN patients p ON e.patient_id = p.id
        WHERE pr.id = ?
    ");
    $stmt->bind_param('i', $reportId);
} elseif ($examId) {
    // Buscar pelo ID do exame
    $stmt = $db->prepare("
        SELECT pr.*, e.exam_number, p.full_name as patient_name
        FROM pdf_reports pr
        INNER JOIN exams e ON pr.exam_id = e.id
        INNER JOIN patients p ON e.patient_id = p.id
        WHERE pr.exam_id = ?
        ORDER BY pr.created_at DESC
        LIMIT 1
    ");
    $stmt->bind_param('i', $examId);
} else {
    die('ID do relatório ou exame não especificado');
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Relatório não encontrado');
}

$report = $result->fetch_assoc();
$filePath = $report['file_path'];

if (!file_exists($filePath)) {
    die('Arquivo PDF não encontrado no servidor');
}

// Configurar headers para exibir PDF inline
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($report['original_filename']) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Ler e exibir o arquivo
readfile($filePath);
exit();
?>
