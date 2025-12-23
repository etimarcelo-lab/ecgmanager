<?php
session_start();
require_once '../config/database.php';
require_once '../includes/Database.class.php';
require_once '../includes/Auth.class.php';
require_once '../includes/Utils.class.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('HTTP/1.0 401 Unauthorized');
    exit();
}

$type = $_GET['type'] ?? 'exams';
$format = $_GET['format'] ?? 'csv';

if (!in_array($format, ['csv', 'excel', 'pdf'])) {
    $format = 'csv';
}

$db = Database::getInstance();

switch ($type) {
    case 'patients':
        $query = "SELECT * FROM patients ORDER BY full_name";
        $filename = 'pacientes';
        break;
        
    case 'exams':
        $query = "
            SELECT e.*, p.full_name as patient_name, 
                   d1.name as resp_doctor, d2.name as req_doctor
            FROM exams e
            LEFT JOIN patients p ON e.patient_id = p.id
            LEFT JOIN doctors d1 ON e.responsible_doctor_id = d1.id
            LEFT JOIN doctors d2 ON e.requesting_doctor_id = d2.id
            ORDER BY e.exam_date DESC
        ";
        $filename = 'exames';
        break;
        
    case 'reports':
        $query = "
            SELECT pr.*, e.exam_number, p.full_name as patient_name
            FROM pdf_reports pr
            INNER JOIN exams e ON pr.exam_id = e.id
            INNER JOIN patients p ON e.patient_id = p.id
            ORDER BY pr.report_date DESC
        ";
        $filename = 'laudos';
        break;
        
    default:
        header('HTTP/1.0 400 Bad Request');
        exit();
}

$result = $db->query($query);
if (!$result) {
    header('HTTP/1.0 500 Internal Server Error');
    exit();
}

switch ($format) {
    case 'csv':
        exportCSV($result, $filename);
        break;
        
    case 'excel':
        exportExcel($result, $filename);
        break;
        
    case 'pdf':
        // Em produção, usar biblioteca como TCPDF ou mPDF
        exportPDF($result, $filename);
        break;
}

function exportCSV($result, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Ymd') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Cabeçalho
    $fields = $result->fetch_fields();
    $headers = [];
    foreach ($fields as $field) {
        $headers[] = $field->name;
    }
    fputcsv($output, $headers, ';');
    
    // Dados
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
    exit();
}

function exportExcel($result, $filename) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Ymd') . '.xls"');
    
    echo '<table border="1">';
    
    // Cabeçalho
    $fields = $result->fetch_fields();
    echo '<tr>';
    foreach ($fields as $field) {
        echo '<th>' . htmlspecialchars($field->name) . '</th>';
    }
    echo '</tr>';
    
    // Dados
    while ($row = $result->fetch_assoc()) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . htmlspecialchars($cell) . '</td>';
        }
        echo '</tr>';
    }
    
    echo '</table>';
    exit();
}

function exportPDF($result, $filename) {
    // Implementação simplificada - em produção usar TCPDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Ymd') . '.pdf"');
    
    $html = '<h1>' . ucfirst($filename) . '</h1>';
    $html .= '<p>Exportado em: ' . date('d/m/Y H:i:s') . '</p>';
    $html .= '<table border="1" cellpadding="5">';
    
    // Cabeçalho
    $fields = $result->fetch_fields();
    $html .= '<tr>';
    foreach ($fields as $field) {
        $html .= '<th>' . htmlspecialchars($field->name) . '</th>';
    }
    $html .= '</tr>';
    
    // Dados
    while ($row = $result->fetch_assoc()) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . htmlspecialchars($cell) . '</td>';
        }
        $html .= '</tr>';
    }
    
    $html .= '</table>';
    
    // Em produção, gerar PDF real
    echo $html;
    exit();
}
?>