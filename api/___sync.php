<?php
// api/sync.php
session_start();
require_once '../config/database.php';
require_once '../includes/Database.class.php';
require_once '../includes/Auth.class.php';

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
$remoteComputer = '\\\\192.168.140.199';
$remoteHost = ltrim($remoteComputer, '\\');
$sourceDir = "//$remoteHost/Compartilhamento/"; // Ajuste conforme necessário
$localDir = '../uploads/sync/';
$processedDir = $localDir . 'processed/';
$errorDir = $localDir . 'errors/';

// ==================== SISTEMA DE LOGS ====================
class SimpleLog {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
        $this->createLogsTableIfNotExists();
    }
    
    private function createLogsTableIfNotExists() {
        try {
            $sql = "SHOW TABLES LIKE 'logs'";
            $result = $this->db->query($sql);
            
            if ($result->num_rows == 0) {
                $createTableSQL = "
                CREATE TABLE logs (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NULL,
                    action VARCHAR(50) NOT NULL,
                    details TEXT NOT NULL,
                    ip_address VARCHAR(45) NULL,
                    log_type VARCHAR(20) DEFAULT 'info',
                    filename VARCHAR(255) NULL,
                    records_affected INT DEFAULT 0,
                    execution_time VARCHAR(20) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ";
                
                $this->db->query($createTableSQL);
                
                // Criar índices básicos
                $this->db->query("CREATE INDEX idx_created_at ON logs(created_at)");
                $this->db->query("CREATE INDEX idx_action ON logs(action)");
                $this->db->query("CREATE INDEX idx_log_type ON logs(log_type)");
            }
        } catch (Exception $e) {
            // Se falhar, apenas registra no arquivo
            error_log("Não foi possível criar tabela logs: " . $e->getMessage());
        }
    }
    
    public function add($type, $filename, $status, $message, $records = 0, $time = '0s', $ip = null) {
        // Sempre registrar no arquivo de log
        $this->logToFile($type, $filename, $status, $message, $ip);
        
        // Tentar registrar no banco
        try {
            $ip = $ip ?: ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
            
            $stmt = $this->db->prepare("
                INSERT INTO logs (user_id, action, details, ip_address, log_type, filename, records_affected, execution_time)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
            
            $stmt->bind_param('isssssis', 
                $userId, 
                $type, 
                $message, 
                $ip,
                $status,
                $filename,
                $records,
                $time
            );
            
            return $stmt->execute();
            
        } catch (Exception $e) {
            // Se falhar no banco, apenas continua
            error_log("Erro ao registrar log no banco: " . $e->getMessage());
            return false;
        }
    }
    
    private function logToFile($type, $filename, $status, $message, $ip) {
        $logDir = '../logs/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . 'sync_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$status] [$type] " . 
                   ($filename && $filename != '-' ? "[$filename] " : "") . 
                   "[$ip] $message\n";
        
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
}

// Inicializar sistema de logs
$log = new SimpleLog($db);

// ==================== FUNÇÕES AUXILIARES ====================

/**
 * Verifica conexão com computador remoto
 */
function checkRemoteConnection($host) {
    // Método 1: Ping
    $command = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' 
        ? "ping -n 1 -w 1000 $host"
        : "ping -c 1 -W 1 $host";
    
    exec($command, $output, $result);
    
    if ($result === 0) {
        return [
            'success' => true, 
            'method' => 'ping', 
            'message' => 'Ping bem-sucedido',
            'details' => isset($output[1]) ? $output[1] : ''
        ];
    }
    
    // Método 2: Porta SMB (445)
    $fp = @fsockopen($host, 445, $errno, $errstr, 2);
    if ($fp) {
        fclose($fp);
        return [
            'success' => true, 
            'method' => 'smb_port', 
            'message' => 'Porta SMB acessível'
        ];
    }
    
    // Método 3: Tentar acessar compartilhamento
    $testDir = "//$host/Compartilhamento";
    if (@is_dir($testDir) || @opendir($testDir)) {
        return [
            'success' => true, 
            'method' => 'smb_share', 
            'message' => 'Compartilhamento acessível'
        ];
    }
    
    return [
        'success' => false, 
        'method' => 'none', 
        'message' => 'Não foi possível conectar ao computador remoto',
        'details' => "Host: $host, Erro: " . ($errstr ?? 'Desconhecido') . " (" . ($errno ?? '0') . ")"
    ];
}

/**
 * Extrai número do exame do nome do arquivo
 */
function extractExamNumber($filename) {
    $filename = pathinfo($filename, PATHINFO_FILENAME);
    
    // Padrões comuns de nomes de arquivos ECG
    $patterns = [
        '/^(\d{5,})/',                   // 32289.pdf
        '/ECG[_\-\s]?(\d+)/i',           // ECG_32289.pdf
        '/EXAME[_\-\s]?(\d+)/i',         // EXAME_32289.pdf
        '/LAUDO[_\-\s]?(\d+)/i',         // LAUDO_32289.pdf
        '/^(\d+)[_\-\s]/',               // 32289_20241217.pdf
        '/^(\d+)$/',                     // 32289 (sem extensão)
        '/^[A-Z]+[_\-]?(\d+)/i',         // ECG_32289, EXAME-32289
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $filename, $matches)) {
            return $matches[1];
        }
    }
    
    return null;
}

/**
 * Busca exame no banco pelo número
 */
function findExamByNumber($examNumber, $db) {
    $stmt = $db->prepare("
        SELECT e.*, p.full_name as patient_name 
        FROM exams e 
        LEFT JOIN patients p ON e.patient_id = p.id 
        WHERE e.exam_number = ? 
        AND e.status != 'cancelado'
        LIMIT 1
    ");
    
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('s', $examNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

/**
 * Processa um arquivo PDF
 */
function processPdfFile($filePath, $filename, $db, $log, $userId, $ip) {
    // IGNORAR arquivos com nomes genéricos
    $filenameUpper = strtoupper($filename);
    $genericNames = [
        'REPORT.PDF', 'DOCUMENT.PDF', 'DOC.PDF', 'FILE.PDF', 
        'ARQUIVO.PDF', 'LAUDO.PDF', 'EXAME.PDF', 'ECG.PDF',
        'NEW_FILE.PDF', 'SCAN.PDF', 'OUTPUT.PDF'
    ];
    
    if (in_array($filenameUpper, $genericNames)) {
        $log->add('pdf', $filename, 'warning', 
                 "Arquivo ignorado: $filename (nome genérico)", 
                 0, '0s', $ip);
        return ['success' => false, 'ignored' => true, 'reason' => 'nome_genérico'];
    }
    
    // Extrair número do exame
    $examNumber = extractExamNumber($filename);
    
    if (!$examNumber) {
        $log->add('pdf', $filename, 'error', 
                 "Formato de nome de arquivo inválido: $filename", 
                 0, '0s', $ip);
        return ['success' => false, 'reason' => 'formato_invalido'];
    }
    
    // Buscar exame no banco
    $exam = findExamByNumber($examNumber, $db);
    
    if (!$exam) {
        $log->add('pdf', $filename, 'error', 
                 "Exame não encontrado para número: $examNumber", 
                 0, '0s', $ip);
        return ['success' => false, 'reason' => 'exame_nao_encontrado'];
    }
    
    // Verificar se já existe PDF para este exame
    $existingQuery = $db->query("SELECT id, stored_filename FROM pdf_reports WHERE exam_id = {$exam['id']} LIMIT 1");
    if ($existingQuery && $existingQuery->num_rows > 0) {
        $oldPdf = $existingQuery->fetch_assoc();
        $log->add('pdf', $filename, 'warning', 
                 "Exame {$examNumber} já possui PDF. Substituindo...", 
                 0, '0s', $ip);
        
        // Remover PDF antigo se existir
        if ($oldPdf && file_exists('../uploads/pdf_reports/' . $oldPdf['stored_filename'])) {
            @unlink('../uploads/pdf_reports/' . $oldPdf['stored_filename']);
        }
        
        $db->query("DELETE FROM pdf_reports WHERE exam_id = {$exam['id']}");
    }
    
    // Criar diretório de PDFs se não existir
    $pdfDir = '../uploads/pdf_reports/';
    if (!is_dir($pdfDir)) {
        mkdir($pdfDir, 0755, true);
    }
    
    // Gerar nome único para o arquivo
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    $uniqueName = uniqid('report_', true) . '.' . $extension;
    $destination = $pdfDir . $uniqueName;
    
    // Copiar arquivo
    if (!@copy($filePath, $destination)) {
        $log->add('pdf', $filename, 'error', 
                 "Falha ao copiar arquivo: $filename", 
                 0, '0s', $ip);
        return ['success' => false, 'reason' => 'falha_copia'];
    }
    
    // Preservar data original do arquivo
    $originalTimestamp = date('Y-m-d H:i:s', filemtime($filePath));
    $fileSize = filesize($filePath);
    
    // Inserir no banco de dados
    $stmt = $db->prepare("
        INSERT INTO pdf_reports 
        (exam_id, original_filename, stored_filename, file_path, file_size, 
         report_date, report_time, file_original_timestamp, created_at) 
        VALUES (?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?, NOW())
    ");
    
    if (!$stmt) {
        @unlink($destination);
        $log->add('pdf', $filename, 'error', 
                 "Erro na preparação da query: " . $db->error, 
                 0, '0s', $ip);
        return ['success' => false, 'reason' => 'erro_preparacao'];
    }
    
    $stmt->bind_param('isssis', 
        $exam['id'], 
        $filename, 
        $uniqueName, 
        $destination, 
        $fileSize, 
        $originalTimestamp
    );
    
    if (!$stmt->execute()) {
        @unlink($destination);
        $log->add('pdf', $filename, 'error', 
                 "Erro ao salvar no banco: " . $db->error, 
                 0, '0s', $ip);
        return ['success' => false, 'reason' => 'erro_banco'];
    }
    
    // Atualizar status do exame
    $db->query("UPDATE exams SET pdf_processed = TRUE WHERE id = {$exam['id']}");
    
    $log->add('pdf', $filename, 'success', 
             "PDF processado com sucesso: $filename (Exame: $examNumber, Paciente: " . ($exam['patient_name'] ?? 'N/A') . ")", 
             1, '0s', $ip);
    
    return [
        'success' => true, 
        'exam_number' => $examNumber,
        'patient_name' => $exam['patient_name'] ?? '',
        'file_size' => $fileSize,
        'file_id' => $stmt->insert_id
    ];
}

/**
 * Processa um arquivo WXML
 */
function processWxmlFile($filePath, $filename, $db, $log, $userId, $ip) {
    // Criar diretório para WXML se não existir
    $wxmlDir = '../uploads/wxml_processed/';
    if (!is_dir($wxmlDir)) {
        mkdir($wxmlDir, 0755, true);
    }
    
    $uniqueName = uniqid('wxml_', true) . '.wxml';
    $destination = $wxmlDir . $uniqueName;
    
    if (!@copy($filePath, $destination)) {
        $log->add('wxml', $filename, 'error', 
                 "Falha ao processar WXML: $filename", 
                 0, '0s', $ip);
        return ['success' => false];
    }
    
    $log->add('wxml', $filename, 'success', 
             "WXML processado: $filename", 
             1, '0s', $ip);
    
    return ['success' => true];
}

/**
 * Verifica se um diretório remoto está acessível
 */
function isRemoteDirectoryAccessible($dir) {
    if (@is_dir($dir)) {
        return true;
    }
    
    // Tentar abrir o diretório
    $handle = @opendir($dir);
    if ($handle) {
        closedir($handle);
        return true;
    }
    
    return false;
}

// ==================== PREPARAR DIRETÓRIOS LOCAIS ====================
foreach ([$localDir, $processedDir, $errorDir] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// Criar subdiretórios para organização
@mkdir($errorDir . 'ignored/', 0755, true);
@mkdir($processedDir . 'wxml/', 0755, true);

// ==================== HANDLE REQUESTS ====================

// Testar conexão com computador remoto
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'test_connection') {
    $testType = $_GET['test'] ?? 'all';
    $connectionResult = checkRemoteConnection($remoteHost);
    
    // Registrar teste no log
    $log->add('connection', '-', $connectionResult['success'] ? 'success' : 'error', 
             "Teste de conexão: " . $connectionResult['message'], 
             0, '0s', $ip);
    
    header('Content-Type: application/json');
    echo json_encode($connectionResult);
    exit();
}

// Sincronização manual
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'manual') {
    // Verificar conexão antes de sincronizar
    $connectionCheck = checkRemoteConnection($remoteHost);
    
    if (!$connectionCheck['success']) {
        $log->add('sync', '-', 'error', 
                 "Sincronização falhou: " . $connectionCheck['message'], 
                 0, '0s', $ip);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Computador remoto não está acessível. Verifique a conexão de rede.',
            'connection_error' => $connectionCheck['message'],
            'details' => $connectionCheck['details'] ?? ''
        ]);
        exit();
    }
    
    // Registrar início da sincronização
    $log->add('sync', '-', 'info', 
             "Iniciando sincronização manual", 
             0, '0s', $ip);
    
    $startTime = microtime(true);
    
    // Verificar se o diretório remoto está acessível
    if (!isRemoteDirectoryAccessible($sourceDir)) {
        $log->add('sync', '-', 'error', 
                 "Diretório remoto não acessível: $sourceDir", 
                 0, '0s', $ip);
        
        echo json_encode([
            'success' => false,
            'message' => 'Diretório remoto não acessível. Verifique o compartilhamento.',
            'directory' => $sourceDir
        ]);
        exit();
    }
    
    // Buscar arquivos
    $pdfFiles = @glob($sourceDir . '*.pdf') ?: [];
    $wxmlFiles = @glob($sourceDir . '*.wxml') ?: [];
    
    $totalFiles = count($pdfFiles) + count($wxmlFiles);
    $processedCount = 0;
    $successCount = 0;
    $errorCount = 0;
    $ignoredCount = 0;
    
    $results = [
        'pdf' => ['success' => 0, 'errors' => 0, 'ignored' => 0, 'details' => []],
        'wxml' => ['success' => 0, 'errors' => 0, 'details' => []]
    ];
    
    // Processar PDFs
    foreach ($pdfFiles as $pdfFile) {
        $filename = basename($pdfFile);
        $processedCount++;
        
        $result = processPdfFile($pdfFile, $filename, $db, $log, $userId, $ip);
        
        if (isset($result['ignored']) && $result['ignored']) {
            $ignoredCount++;
            $results['pdf']['ignored']++;
            $results['pdf']['details'][] = "$filename: Ignorado (nome genérico)";
            
            // Mover para pasta de ignorados
            $ignoredPath = $errorDir . 'ignored/' . $filename;
            @rename($pdfFile, $ignoredPath);
            
        } elseif ($result['success']) {
            $successCount++;
            $results['pdf']['success']++;
            $results['pdf']['details'][] = "$filename: Processado com sucesso (Exame: " . ($result['exam_number'] ?? 'N/A') . ")";
            
            // Mover para pasta de processados
            @rename($pdfFile, $processedDir . $filename);
        } else {
            $errorCount++;
            $results['pdf']['errors']++;
            $reason = $result['reason'] ?? 'desconhecido';
            $results['pdf']['details'][] = "$filename: Erro ($reason)";
            
            // Mover para pasta de erros
            @rename($pdfFile, $errorDir . $filename);
        }
    }
    
    // Processar WXMLs
    foreach ($wxmlFiles as $wxmlFile) {
        $filename = basename($wxmlFile);
        $processedCount++;
        
        $result = processWxmlFile($wxmlFile, $filename, $db, $log, $userId, $ip);
        
        if ($result['success']) {
            $successCount++;
            $results['wxml']['success']++;
            $results['wxml']['details'][] = "$filename: Processado com sucesso";
            
            // Mover para pasta de processados
            @rename($wxmlFile, $processedDir . 'wxml/' . $filename);
        } else {
            $errorCount++;
            $results['wxml']['errors']++;
            $results['wxml']['details'][] = "$filename: Erro no processamento";
            
            // Mover para pasta de erros
            @rename($wxmlFile, $errorDir . 'wxml/' . $filename);
        }
    }
    
    // Calcular tempo de execução
    $executionTime = microtime(true) - $startTime;
    $formattedTime = round($executionTime, 2) . 's';
    
    // Log de resumo
    $log->add('sync', '-', $errorCount > 0 ? 'warning' : 'success', 
             "Sincronização concluída. Total: $totalFiles, Processados: $processedCount, Sucessos: $successCount, Erros: $errorCount, Ignorados: $ignoredCount", 
             $successCount, $formattedTime, $ip);
    
    // Retornar resultado
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $successCount > 0 || $ignoredCount > 0,
        'message' => $totalFiles == 0 ? 'Nenhum arquivo encontrado para sincronizar' : "Sincronização concluída",
        'stats' => [
            'total_files' => $totalFiles,
            'processed' => $processedCount,
            'success' => $successCount,
            'errors' => $errorCount,
            'ignored' => $ignoredCount,
            'execution_time' => $formattedTime
        ],
        'connection' => $connectionCheck,
        'details' => $results,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    exit();
}

// Upload manual de PDF (via formulário web)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_pdf') {
    $examId = $_POST['exam_id'] ?? 0;
    
    if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_OK) {
        echo json_encode(['success' => false, 'message' => 'Nenhum arquivo enviado ou erro no upload']);
        exit();
    }
    
    $file = $_FILES['pdf_file'];
    $filename = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    
    // Validar se é PDF
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $fileTmpName);
    finfo_close($finfo);
    
    if ($mimeType !== 'application/pdf') {
        echo json_encode(['success' => false, 'message' => 'Apenas arquivos PDF são permitidos']);
        exit();
    }
    
    // Verificar se exame existe
    $exam = $db->query("SELECT * FROM exams WHERE id = $examId")->fetch_assoc();
    if (!$exam) {
        echo json_encode(['success' => false, 'message' => 'Exame não encontrado']);
        exit();
    }
    
    // Verificar se já existe PDF para este exame
    $existing = $db->query("SELECT id, stored_filename FROM pdf_reports WHERE exam_id = $examId LIMIT 1");
    if ($existing && $existing->num_rows > 0) {
        // Remover PDF antigo
        $oldPdf = $existing->fetch_assoc();
        if (file_exists('../uploads/pdf_reports/' . $oldPdf['stored_filename'])) {
            @unlink('../uploads/pdf_reports/' . $oldPdf['stored_filename']);
        }
        $db->query("DELETE FROM pdf_reports WHERE exam_id = $examId");
    }
    
    // Criar diretório se não existir
    $uploadDir = '../uploads/pdf_reports/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Gerar nome único
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    $uniqueName = uniqid('report_', true) . '.' . $extension;
    $destination = $uploadDir . $uniqueName;
    
    // Mover arquivo
    if (move_uploaded_file($fileTmpName, $destination)) {
        // Preservar data original
        $originalTimestamp = date('Y-m-d H:i:s', filemtime($fileTmpName));
        
        // Inserir no banco
        $stmt = $db->prepare("
            INSERT INTO pdf_reports 
            (exam_id, original_filename, stored_filename, file_path, file_size, 
             report_date, report_time, file_original_timestamp, created_at) 
            VALUES (?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?, NOW())
        ");
        
        if ($stmt) {
            $stmt->bind_param('isssis', $examId, $filename, $uniqueName, $destination, $fileSize, $originalTimestamp);
            
            if ($stmt->execute()) {
                // Atualizar status do exame
                $db->query("UPDATE exams SET pdf_processed = TRUE WHERE id = $examId");
                
                // Log
                $log->add('pdf_upload', $filename, 'success', 
                         "Upload manual de PDF realizado: $filename (Exame ID: $examId, Número: " . ($exam['exam_number'] ?? 'N/A') . ")", 
                         1, '0s', $ip);
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Arquivo enviado com sucesso',
                    'exam_number' => $exam['exam_number'] ?? '',
                    'original_date' => $originalTimestamp,
                    'file_name' => $filename,
                    'file_id' => $stmt->insert_id
                ]);
            } else {
                @unlink($destination);
                echo json_encode(['success' => false, 'message' => 'Erro ao salvar no banco: ' . $db->error]);
            }
        } else {
            @unlink($destination);
            echo json_encode(['success' => false, 'message' => 'Erro na preparação da query: ' . $db->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao mover arquivo']);
    }
    
    exit();
}

// Sincronização automática (para cron jobs)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'auto') {
    // Verificar conexão
    $connectionCheck = checkRemoteConnection($remoteHost);
    
    if (!$connectionCheck['success']) {
        $log->add('sync_auto', '-', 'warning', 
                 "Sincronização automática cancelada: " . $connectionCheck['message'], 
                 0, '0s', 'CRON');
        exit();
    }
    
    // Executar sincronização simplificada
    $pdfFiles = @glob($sourceDir . '*.pdf') ?: [];
    $processed = 0;
    $ignored = 0;
    
    foreach ($pdfFiles as $pdfFile) {
        $filename = basename($pdfFile);
        
        // Ignorar nomes genéricos
        if (in_array(strtoupper($filename), ['REPORT.PDF', 'DOCUMENT.PDF', 'FILE.PDF', 'ARQUIVO.PDF'])) {
            $ignored++;
            @rename($pdfFile, $errorDir . 'ignored/' . $filename);
            continue;
        }
        
        $result = processPdfFile($pdfFile, $filename, $db, $log, 0, 'CRON');
        
        if ($result['success']) {
            $processed++;
            @rename($pdfFile, $processedDir . $filename);
        }
    }
    
    if ($processed > 0 || $ignored > 0) {
        $log->add('sync_auto', '-', 'info', 
                 "Sincronização automática concluída. Processados: $processed, Ignorados: $ignored", 
                 $processed, '0s', 'CRON');
    }
    
    exit();
}

// Status do sistema
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'status') {
    $connectionCheck = checkRemoteConnection($remoteHost);
    
    // Verificar diretórios
    $directories = [
        'remote' => isRemoteDirectoryAccessible($sourceDir),
        'local_sync' => is_dir($localDir),
        'pdf_reports' => is_dir('../uploads/pdf_reports/'),
        'logs' => is_dir('../logs/')
    ];
    
    // Contar arquivos pendentes
    $pendingFiles = @glob($sourceDir . '*.pdf') ?: [];
    $pendingCount = count($pendingFiles);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'connection' => $connectionCheck,
        'directories' => $directories,
        'pending_files' => $pendingCount,
        'system' => [
            'php_version' => PHP_VERSION,
            'server_os' => PHP_OS,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        ]
    ]);
    
    exit();
}

// Ação não reconhecida
header('Content-Type: application/json');
echo json_encode([
    'success' => false,
    'message' => 'Ação não especificada ou inválida',
    'available_actions' => [
        'manual' => 'Sincronização manual (GET)',
        'upload_pdf' => 'Upload manual de PDF (POST com exam_id e pdf_file)',
        'test_connection' => 'Testar conexão com computador remoto (GET)',
        'auto' => 'Sincronização automática (para cron jobs)',
        'status' => 'Status do sistema (GET)'
    ]
]);
?>
