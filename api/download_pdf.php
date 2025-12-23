<?php
// api/download_pdf.php
session_start();
require_once '../config/database.php';
require_once '../includes/Database.class.php';

$db = Database::getInstance();
$reportId = $_GET['id'] ?? 0;

if (!$reportId) {
    die('ID do relatório não especificado');
}

$stmt = $db->prepare("
    SELECT pr.*, e.exam_number, p.full_name as patient_name
    FROM pdf_reports pr
    INNER JOIN exams e ON pr.exam_id = e.id
    INNER JOIN patients p ON e.patient_id = p.id
    WHERE pr.id = ?
");
$stmt->bind_param('i', $reportId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Relatório não encontrado');
}

$report = $result->fetch_assoc();
$filePath = $report['file_path'];

if (!file_exists($filePath)) {
    die('Arquivo PDF não encontrado');
}

// Configurar headers para download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . basename($report['original_filename']) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($filePath);
exit();
?>
