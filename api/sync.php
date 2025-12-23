<?php
// api/sync.php - VERSÃO SIMPLIFICADA E FUNCIONAL
session_start();

// ==================== CAMINHOS ABSOLUTOS ====================
define('APP_ROOT', dirname(__DIR__)); // /var/www/html/ecgmanager

// Incluir dependências
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/Database.class.php';
require_once APP_ROOT . '/includes/Auth.class.php';

// Inicializar
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

$db = Database::getInstance();
$userId = $_SESSION['user_id'] ?? 0;
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// ==================== CONFIGURAÇÕES ====================
$remoteHost = '192.168.140.199';
$sourceDir = "//{$remoteHost}/Compartilhamento/";
$localDir = APP_ROOT . '/uploads/sync/';
$processedDir = $localDir . 'processed/';
$errorDir = $localDir . 'errors/';

// ==================== FUNÇÃO DE TESTE SIMPLES ====================
function testConnection() {
    global $remoteHost;
    
    $testDir = "//{$remoteHost}/Compartilhamento";
    
    if (@file_exists($testDir)) {
        return [
            'success' => true,
            'message' => 'Compartilhamento acessível',
            'path' => $testDir
        ];
    }
    
    // Tentar ping
    exec("ping -c 1 -W 1 {$remoteHost} 2>&1", $output, $returnCode);
    
    if ($returnCode === 0) {
        return [
            'success' => false,
            'message' => 'Ping funciona, mas compartilhamento não está acessível',
            'ping' => true
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Não é possível acessar o computador remoto',
        'ping' => false
    ];
}

// ==================== HANDLE REQUESTS ====================
header('Content-Type: application/json');

// Teste de conexão
if (isset($_GET['test'])) {
    $result = testConnection();
    echo json_encode($result);
    exit();
}

// Sincronização manual
if (isset($_GET['sync'])) {
    // Testar conexão primeiro
    $connection = testConnection();
    
    if (!$connection['success']) {
        echo json_encode([
            'success' => false,
            'message' => 'Não é possível sincronizar. ' . $connection['message'],
            'connection' => $connection
        ]);
        exit();
    }
    
    // Verificar se o diretório existe
    if (!@file_exists($sourceDir)) {
        echo json_encode([
            'success' => false,
            'message' => "Diretório remoto não encontrado: {$sourceDir}",
            'connection' => $connection
        ]);
        exit();
    }
    
    // Buscar arquivos PDF
    $files = @glob($sourceDir . '*.pdf') ?: [];
    $fileCount = count($files);
    
    // Criar diretórios locais se não existirem
    foreach ([$localDir, $processedDir, $errorDir] as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }
    
    $results = [];
    $processed = 0;
    
    foreach ($files as $file) {
        $filename = basename($file);
        
        // Ignorar nomes genéricos
        $genericNames = ['REPORT.PDF', 'DOCUMENT.PDF', 'FILE.PDF'];
        if (in_array(strtoupper($filename), $genericNames)) {
            @rename($file, $errorDir . $filename);
            $results[] = "$filename: Ignorado (nome genérico)";
            continue;
        }
        
        // Tentar extrair número do exame (simplificado)
        preg_match('/(\d{4,})/', $filename, $matches);
        $examNumber = $matches[1] ?? null;
        
        if ($examNumber) {
            // Buscar exame no banco
            $stmt = $db->prepare("SELECT id, exam_number FROM exams WHERE exam_number = ? AND status != 'cancelado' LIMIT 1");
            $stmt->bind_param('s', $examNumber);
            $stmt->execute();
            $result = $stmt->get_result();
            $exam = $result->fetch_assoc();
            
            if ($exam) {
                // Mover para processados
                @rename($file, $processedDir . $filename);
                $results[] = "$filename: Processado (Exame: $examNumber)";
                $processed++;
            } else {
                // Mover para erros
                @rename($file, $errorDir . $filename);
                $results[] = "$filename: Erro (Exame $examNumber não encontrado)";
            }
        } else {
            // Mover para erros
            @rename($file, $errorDir . $filename);
            $results[] = "$filename: Erro (não é possível extrair número do exame)";
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Sincronização concluída. {$processed}/{$fileCount} arquivos processados.",
        'stats' => [
            'total_files' => $fileCount,
            'processed' => $processed,
            'errors' => $fileCount - $processed
        ],
        'results' => $results,
        'connection' => $connection
    ]);
    
    exit();
}

// Status
if (isset($_GET['status'])) {
    $connection = testConnection();
    
    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'connection' => $connection,
        'paths' => [
            'app_root' => APP_ROOT,
            'source_dir' => $sourceDir,
            'local_dir' => $localDir,
            'exists_source' => @file_exists($sourceDir),
            'exists_local' => is_dir($localDir)
        ],
        'server' => [
            'php_version' => PHP_VERSION,
            'server_ip' => $_SERVER['SERVER_ADDR'] ?? 'unknown'
        ]
    ]);
    exit();
}

// Ação padrão
echo json_encode([
    'success' => false,
    'message' => 'Parâmetro inválido',
    'available_actions' => [
        '?test' => 'Testar conexão',
        '?sync' => 'Sincronizar arquivos',
        '?status' => 'Verificar status'
    ]
]);
?>