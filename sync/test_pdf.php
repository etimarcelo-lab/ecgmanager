<?php
// test_pdf.php - Script de teste simples
$baseDir = dirname(__DIR__) . '/';
echo "Testando diretórios...\n";
echo "Base: $baseDir\n";

// Verifica se os arquivos existem
$files = [
    'config/database.php' => $baseDir . 'config/database.php',
    'includes/Database.class.php' => $baseDir . 'includes/Database.class.php',
    'includes/Log.class.php' => $baseDir . 'includes/Log.class.php',
];

foreach ($files as $name => $path) {
    if (file_exists($path)) {
        echo "✓ $name: ENCONTRADO\n";
    } else {
        echo "✗ $name: NÃO ENCONTRADO ($path)\n";
    }
}

// Verifica PDFs
$pdfDir = $baseDir . 'ECG/';
echo "\nVerificando PDFs em: $pdfDir\n";
if (is_dir($pdfDir)) {
    $pdfs = glob($pdfDir . '*.pdf');
    echo "PDFs encontrados: " . count($pdfs) . "\n";
    if (count($pdfs) > 0) {
        echo "Primeiros 5:\n";
        foreach (array_slice($pdfs, 0, 5) as $pdf) {
            echo "- " . basename($pdf) . "\n";
        }
    }
} else {
    echo "Diretório não existe!\n";
}

// Testa conexão com banco
echo "\nTestando conexão com banco...\n";
try {
    require_once $baseDir . 'config/database.php';
    require_once $baseDir . 'includes/Database.class.php';
    
    $db = Database::getInstance();
    echo "✓ Conexão com banco OK\n";
    
    // Testa consulta
    $result = $db->getConnection()->query("SELECT COUNT(*) as total FROM exams");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "✓ Exames no banco: " . $row['total'] . "\n";
    }
} catch (Exception $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
}
?>
