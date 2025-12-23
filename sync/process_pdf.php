<?php
/**
 * Processamento de arquivos PDF
 * Executado via cron a cada 3 minutos
 * Vincula PDFs aos exames existentes pelo nome do arquivo WXML
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Database.class.php';
require_once __DIR__ . '/../includes/SyncLogger.class.php';
require_once __DIR__ . '/../includes/PDFProcessor.class.php';

class PDFProcessorCLI {
    private $logger;
    private $processor;
    private $pdfDir = '/var/www/html/ecgmanager/ECG';
    private $uploadDir = '/var/www/html/ecgmanager/uploads/pdf';
    
    public function __construct() {
        $this->logger = new SyncLogger();
        $this->processor = new PDFProcessor($this->logger);
    }
    
    public function processNewPDFs() {
        $processed = 0;
        $errors = 0;
        
        try {
            if (!is_dir($this->pdfDir)) {
                $this->logger->log('pdf', 'error', 
                    "Diretório PDF não encontrado: {$this->pdfDir}");
                return 0;
            }
            
            // Garantir que o diretório de uploads existe
            if (!is_dir($this->uploadDir)) {
                mkdir($this->uploadDir, 0755, true);
                $this->logger->log('pdf', 'info', "Diretório de uploads criado: {$this->uploadDir}");
            }
            
            // Buscar arquivos .PDF novos
            $files = glob($this->pdfDir . '/*.{PDF,pdf}', GLOB_BRACE);
            $this->logger->log('pdf', 'info', 
                "Encontrados " . count($files) . " arquivos PDF");
            
            // Ordenar por data de modificação (mais recentes primeiro)
            usort($files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            
            // Limitar processamento para evitar sobrecarga
            $files = array_slice($files, 0, 50);
            
            foreach ($files as $file) {
                $filename = basename($file);
                $this->logger->log('pdf', 'info', "Verificando: {$filename}");
                
                // Verificar se já foi processado
                if (!$this->isAlreadyProcessed($filename)) {
                    $this->logger->log('pdf', 'info', "Processando arquivo novo: {$filename}");
                    
                    // Processar arquivo
                    if ($this->processPDFFile($file, $filename)) {
                        $processed++;
                        
                        // Mover para processados (local)
                        if ($this->moveToProcessed($file, $filename)) {
                            $this->logger->log('pdf', 'success', 
                                "Arquivo movido para processados: {$filename}");
                        } else {
                            $this->logger->log('pdf', 'warning', 
                                "Arquivo processado mas não movido: {$filename}");
                        }
                        
                    } else {
                        $errors++;
                        $this->logger->log('pdf', 'error', 
                            "Falha ao processar PDF: {$filename}");
                    }
                } else {
                    $this->logger->log('pdf', 'info', 
                        "Arquivo já processado: {$filename}");
                }
            }
            
            $message = "Processamento PDF concluído. Sucessos: {$processed}, Erros: {$errors}";
            $this->logger->log('pdf', $errors === 0 ? 'success' : 'warning', $message);
            
        } catch (Exception $e) {
            $this->logger->log('pdf', 'error', 
                "Erro no processamento PDF: " . $e->getMessage());
        }
        
        return $processed;
    }
    
    private function isAlreadyProcessed($filename) {
        try {
            $db = Database::getInstance();
            $query = "SELECT id FROM pdf_reports WHERE original_filename = ? LIMIT 1";
            $stmt = $db->prepare($query);
            if (!$stmt) {
                $this->logger->log('pdf', 'error', "Erro ao preparar query: " . $db->error);
                return false;
            }
            
            $stmt->bind_param("s", $filename);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->num_rows > 0;
        } catch (Exception $e) {
            $this->logger->log('pdf', 'error', "Erro ao verificar processamento: " . $e->getMessage());
            return false;
        }
    }
    
    private function processPDFFile($filepath, $filename) {
        try {
            $this->logger->log('pdf', 'info', "Processando arquivo: {$filename}");
            
            // Extrair informações do nome do arquivo
            $fileInfo = $this->extractInfoFromFilename($filename);
            
            if (!$fileInfo) {
                throw new Exception("Formato de nome de arquivo inválido: {$filename}");
            }
            
            $this->logger->log('pdf', 'info', "Informações extraídas: " . json_encode($fileInfo));
            
            // 1. PRIMEIRA TENTATIVA: Buscar exame pelo nome do arquivo WXML correspondente
            $wxmlFilename = $this->getCorrespondingWXMLFilename($filename);
            $exam = $this->findExamByWXMLFilename($wxmlFilename);
            
            if ($exam) {
                $this->logger->log('pdf', 'info', "✅ Exame encontrado pelo WXML: {$exam['id']} - {$exam['exam_number']}");
                $patientId = $exam['patient_id'];
            } else {
                // 2. SEGUNDA TENTATIVA: Buscar paciente e exame pelo nome e data
                $patientName = $this->extractPatientNameFromFilename($filename);
                
                if (!$patientName) {
                    throw new Exception("Não foi possível extrair nome do paciente do arquivo: {$filename}");
                }
                
                $this->logger->log('pdf', 'info', "Nome do paciente extraído: '{$patientName}'");
                
                // Buscar paciente
                $patient = $this->findPatientByName($patientName);
                
                if (!$patient) {
                    $this->logger->log('pdf', 'warning', "Paciente não encontrado: '{$patientName}'");
                    
                    // Se não encontrar paciente, NÃO criar novo (pois o exame deve já existir via WXML)
                    // Em vez disso, buscar exame diretamente pelo número do exame
                    $examNumberFromFilename = $this->getExamNumberFromFilename($filename);
                    $exam = $this->findExamByExamNumber($examNumberFromFilename);
                    
                    if (!$exam) {
                        throw new Exception("Paciente não encontrado e exame não localizado para: {$filename}. Verifique se o WXML foi processado primeiro.");
                    }
                    
                    $patientId = $exam['patient_id'];
                    $this->logger->log('pdf', 'info', "Exame encontrado pelo número: {$exam['id']}");
                } else {
                    $patientId = $patient['id'];
                    $this->logger->log('pdf', 'info', "Paciente encontrado: ID {$patientId} - {$patient['full_name']}");
                    
                    // Buscar exame deste paciente na data do PDF
                    $exam = $this->findExamForPatient($patientId, $fileInfo['date']);
                    
                    if (!$exam) {
                        // Buscar exame mais recente do paciente
                        $exam = $this->findRecentExamForPatient($patientId, $fileInfo['date']);
                        
                        if (!$exam) {
                            throw new Exception("Nenhum exame encontrado para o paciente '{$patient['full_name']}' na data {$fileInfo['date']}");
                        }
                    }
                }
            }
            
            // Se ainda não temos um exame, buscar pelo paciente encontrado
            if (!isset($exam) && isset($patientId)) {
                $exam = $this->findExamForPatient($patientId, $fileInfo['date']);
                
                if (!$exam) {
                    throw new Exception("Não foi possível encontrar exame para vincular o PDF");
                }
            }
            
            if (!$exam) {
                throw new Exception("Nenhum exame encontrado para vincular o PDF: {$filename}");
            }
            
            $this->logger->log('pdf', 'info', "Exame para vincular: ID {$exam['id']} - {$exam['exam_number']}");
            
            // Verificar se já existe PDF para este exame
            if ($this->examHasPDF($exam['id'])) {
                $this->logger->log('pdf', 'warning', "Exame ID {$exam['id']} já tem PDF vinculado");
                // Não consideramos erro, apenas ignoramos
                return true;
            }
            
            // Gerar nome único para armazenamento
            $uniqueName = 'pdf_' . $exam['id'] . '_' . time() . '.pdf';
            $destination = $this->uploadDir . '/' . $uniqueName;
            
            // Copiar arquivo para storage
            if (!copy($filepath, $destination)) {
                $error = error_get_last();
                throw new Exception("Falha ao copiar arquivo para storage: " . ($error['message'] ?? 'Erro desconhecido'));
            }
            
            // Extrair texto do PDF (tentativa)
            $textContent = '';
            try {
                $textContent = $this->processor->extractTextFromPDF($destination);
                $this->logger->log('pdf', 'info', "Texto extraído do PDF: " . strlen($textContent) . " caracteres");
            } catch (Exception $e) {
                // Ignorar erro de extração de texto, continuar processamento
                $this->logger->log('pdf', 'warning', "Erro ao extrair texto do PDF: " . $e->getMessage());
            }
            
            // Salvar no banco de dados
            $db = Database::getInstance();
            
            $stmt = $db->prepare("
                INSERT INTO pdf_reports (
                    exam_id, original_filename, stored_filename, file_path,
                    file_size, report_date, report_time, findings, conclusion,
                    uploaded_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NOW())
            ");
            
            if (!$stmt) {
                throw new Exception("Erro ao preparar query: " . $db->error);
            }
            
            $fileSize = filesize($destination);
            $findings = $this->extractFindings($textContent);
            $conclusion = $this->extractConclusion($textContent);
            
            $stmt->bind_param(
                "isssissss",
                $exam['id'],
                $filename,
                $uniqueName,
                $destination,
                $fileSize,
                $fileInfo['date'],
                $fileInfo['time'],
                $findings,
                $conclusion
            );
            
            if ($stmt->execute()) {
                // Atualizar status do exame
                $updateStmt = $db->prepare("
                    UPDATE exams 
                    SET pdf_processed = 1, status = 'finalizado', updated_at = NOW()
                    WHERE id = ?
                ");
                
                if ($updateStmt) {
                    $updateStmt->bind_param("i", $exam['id']);
                    $updateStmt->execute();
                    $this->logger->log('pdf', 'info', "Status do exame atualizado para 'finalizado'");
                }
                
                $this->logger->log('pdf', 'success', 
                    "PDF processado: {$filename} -> Exame ID: {$exam['id']}", 
                    ['exam_number' => $exam['exam_number']]);
                
                return true;
            } else {
                // Se for erro de duplicidade, verificar e atualizar
                if ($stmt->errno == 1062) {
                    $this->logger->log('pdf', 'warning', "PDF já existe para este exame, atualizando...");
                    return $this->updateExistingPDF($exam['id'], $filename, $destination, $fileSize, $fileInfo, $findings, $conclusion);
                }
                throw new Exception("Falha ao salvar no banco: " . $stmt->error . " (Código: " . $stmt->errno . ")");
            }
            
        } catch (Exception $e) {
            $this->logger->log('pdf', 'error', 
                "Erro no arquivo {$filename}: " . $e->getMessage());
            return false;
        }
    }
    
    private function extractInfoFromFilename($filename) {
        // Formato: MMD#NOME##DDMMYYYYHHMM#MEDICO#E.PDF
        if (preg_match('/##(\d{2})(\d{2})(\d{4})(\d{2})(\d{2})#/', $filename, $matches)) {
            return [
                'date' => $matches[3] . '-' . $matches[2] . '-' . $matches[1], // YYYY-MM-DD
                'time' => $matches[4] . ':' . $matches[5] . ':00', // HH:MM:SS
                'patient_name_raw' => $this->extractRawPatientName($filename)
            ];
        }
        
        return null;
    }
    
    private function extractRawPatientName($filename) {
        // Extrai o nome bruto do paciente do filename
        if (preg_match('/^MMD#([^#]+)##/', $filename, $matches)) {
            return $matches[1];
        }
        return '';
    }
    
    private function extractPatientNameFromFilename($filename) {
        $rawName = $this->extractRawPatientName($filename);
        
        if (empty($rawName)) {
            return null;
        }
        
        // Converter formato "ALMIROCARLOSDESOUZA" para "Almiro Carlos De Souza"
        
        // 1. Adicionar espaços entre maiúsculas consecutivas
        $name = preg_replace('/([a-z])([A-Z])/', '$1 $2', $rawName);
        $name = preg_replace('/([A-Z])([A-Z][a-z])/', '$1 $2', $name);
        
        // 2. Converter para minúsculas primeiro
        $name = mb_strtolower($name, 'UTF-8');
        
        // 3. Converter para título (capitaliza cada palavra)
        $name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
        
        // 4. Manter preposições em minúsculo (exceto no início)
        $lowercaseWords = ['de', 'da', 'do', 'dos', 'das', 'e'];
        $words = explode(' ', $name);
        foreach ($words as $i => &$word) {
            if ($i > 0 && in_array(mb_strtolower($word, 'UTF-8'), $lowercaseWords)) {
                $word = mb_strtolower($word, 'UTF-8');
            }
        }
        
        return implode(' ', $words);
    }
    
    private function getCorrespondingWXMLFilename($pdfFilename) {
        // Converte MMD#NOME##DDMMYYYYHHMM#MEDICO#E.PDF para MMD#NOME##DDMMYYYYHHMM#MEDICO#E.WXML
        $wxmlFilename = preg_replace('/\.(pdf|PDF)$/', '.WXML', $pdfFilename);
        return $wxmlFilename;
    }
    
    private function getExamNumberFromFilename($filename) {
        // Remove extensão para obter número do exame
        return preg_replace('/\.(pdf|PDF)$/', '', $filename);
    }
    
    private function findExamByWXMLFilename($wxmlFilename) {
        $db = Database::getInstance();
        
        $stmt = $db->prepare("
            SELECT id, exam_number, patient_id 
            FROM exams 
            WHERE wxml_file_path = ? 
            LIMIT 1
        ");
        
        if (!$stmt) {
            $this->logger->log('pdf', 'error', "Erro ao preparar busca por WXML: " . $db->error);
            return null;
        }
        
        $stmt->bind_param("s", $wxmlFilename);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    private function findExamByExamNumber($examNumber) {
        $db = Database::getInstance();
        
        $stmt = $db->prepare("
            SELECT id, exam_number, patient_id 
            FROM exams 
            WHERE exam_number = ? 
            LIMIT 1
        ");
        
        if (!$stmt) {
            $this->logger->log('pdf', 'error', "Erro ao preparar busca por número: " . $db->error);
            return null;
        }
        
        $stmt->bind_param("s", $examNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    private function findPatientByName($patientName) {
        $db = Database::getInstance();
        
        // 1. Busca exata
        $stmt = $db->prepare("
            SELECT id, full_name 
            FROM patients 
            WHERE full_name = ? 
            LIMIT 1
        ");
        
        $stmt->bind_param("s", $patientName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return $row;
        }
        
        // 2. Busca por LIKE (mais flexível)
        $stmt = $db->prepare("
            SELECT id, full_name 
            FROM patients 
            WHERE full_name LIKE ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        
        $searchName = "%{$patientName}%";
        $stmt->bind_param("s", $searchName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    private function findExamForPatient($patientId, $examDate) {
        $db = Database::getInstance();
        
        $stmt = $db->prepare("
            SELECT id, exam_number, patient_id 
            FROM exams 
            WHERE patient_id = ? AND exam_date = ? 
            ORDER BY exam_time DESC 
            LIMIT 1
        ");
        
        if (!$stmt) {
            $this->logger->log('pdf', 'error', "Erro ao preparar busca de exame: " . $db->error);
            return null;
        }
        
        $stmt->bind_param("is", $patientId, $examDate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    private function findRecentExamForPatient($patientId, $maxDays = 7) {
        $db = Database::getInstance();
        
        $stmt = $db->prepare("
            SELECT id, exam_number, patient_id, exam_date
            FROM exams 
            WHERE patient_id = ? 
            AND exam_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            ORDER BY exam_date DESC, exam_time DESC 
            LIMIT 1
        ");
        
        if (!$stmt) {
            $this->logger->log('pdf', 'error', "Erro ao preparar busca de exame recente: " . $db->error);
            return null;
        }
        
        $stmt->bind_param("ii", $patientId, $maxDays);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    private function examHasPDF($examId) {
        $db = Database::getInstance();
        
        $stmt = $db->prepare("
            SELECT id FROM pdf_reports WHERE exam_id = ? LIMIT 1
        ");
        
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param("i", $examId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
    
    private function updateExistingPDF($examId, $filename, $filePath, $fileSize, $fileInfo, $findings, $conclusion) {
        $db = Database::getInstance();
        
        $stmt = $db->prepare("
            UPDATE pdf_reports 
            SET original_filename = ?,
                stored_filename = ?,
                file_path = ?,
                file_size = ?,
                report_date = ?,
                report_time = ?,
                findings = ?,
                conclusion = ?,
                updated_at = NOW()
            WHERE exam_id = ?
        ");
        
        if (!$stmt) {
            return false;
        }
        
        $uniqueName = 'pdf_' . $examId . '_' . time() . '.pdf';
        
        $stmt->bind_param(
            "sssissssi",
            $filename,
            $uniqueName,
            $filePath,
            $fileSize,
            $fileInfo['date'],
            $fileInfo['time'],
            $findings,
            $conclusion,
            $examId
        );
        
        return $stmt->execute();
    }
    
    private function extractFindings($text) {
        if (empty($text)) {
            return "Texto não extraído do PDF";
        }
        
        $keywords = [
            'Fibrilação', 'Arritmia', 'Taquicardia', 'Bradicardia',
            'Isquemia', 'Infarto', 'Hipertrofia', 'Bloqueio',
            'FC:', 'bpm', 'PR:', 'QRS:', 'QT:', 'Eixo:',
            'P:', 'Q:', 'R:', 'S:', 'T:', 'Freq:', 'Ritmo:',
            'Onda P', 'Complexo QRS', 'Segmento ST', 'Onda T'
        ];
        
        $lines = explode("\n", $text);
        $findings = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (strlen($line) > 5) {
                foreach ($keywords as $keyword) {
                    if (stripos($line, $keyword) !== false) {
                        $findings[] = $line;
                        break;
                    }
                }
            }
        }
        
        if (empty($findings)) {
            $findings = array_slice($lines, 0, 10);
        }
        
        return implode("\n", array_slice($findings, 0, 10));
    }
    
    private function extractConclusion($text) {
        if (empty($text)) {
            return "Laudo processado automaticamente";
        }
        
        $conclusionMarkers = [
            'Conclusão:', 'Conclusões:', 'Conclui-se:', 
            'Diagnóstico:', 'Impressão:', 'Resultado:',
            'CONCLUSÃO', 'DIAGNÓSTICO', 'RESULTADO:',
            'Laudo:', 'LAUDO:', 'Considerações finais:'
        ];
        
        $lines = explode("\n", $text);
        $inConclusion = false;
        $conclusion = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            foreach ($conclusionMarkers as $marker) {
                if (stripos($line, $marker) !== false) {
                    $inConclusion = true;
                    $line = str_ireplace($marker, '', $line);
                    $line = trim($line);
                }
            }
            
            if ($inConclusion && !empty($line)) {
                $conclusion[] = $line;
                
                if (count($conclusion) >= 5) {
                    break;
                }
            }
        }
        
        if (empty($conclusion)) {
            $reversed = array_reverse($lines);
            $conclusion = array_slice($reversed, 0, 3);
        }
        
        return implode("\n", $conclusion);
    }
    
    private function moveToProcessed($filepath, $filename) {
        $processedDir = $this->pdfDir . '/processed';
        if (!is_dir($processedDir)) {
            if (!mkdir($processedDir, 0755, true)) {
                $this->logger->log('pdf', 'error', 
                    "Falha ao criar diretório: {$processedDir}");
                return false;
            }
        }
        
        $newPath = $processedDir . '/' . $filename;
        
        if (file_exists($newPath)) {
            $timestamp = date('Ymd_His');
            $newFilename = pathinfo($filename, PATHINFO_FILENAME) . 
                          '_' . $timestamp . '.' . 
                          pathinfo($filename, PATHINFO_EXTENSION);
            $newPath = $processedDir . '/' . $newFilename;
        }
        
        if (rename($filepath, $newPath)) {
            return true;
        }
        
        if (copy($filepath, $newPath)) {
            unlink($filepath);
            return true;
        }
        
        $this->logger->log('pdf', 'error', 
            "Falha ao mover arquivo: {$filename} para {$newPath}");
        return false;
    }
}

// Execução via CLI
if (php_sapi_name() === 'cli') {
    $startTime = microtime(true);
    
    try {
        echo "=== Processamento PDF ===\n";
        echo "Iniciando em: " . date('Y-m-d H:i:s') . "\n\n";
        
        $processor = new PDFProcessorCLI();
        $processed = $processor->processNewPDFs();
        
        $processingTime = round(microtime(true) - $startTime, 4);
        
        echo "\n=== Processamento Concluído ===\n";
        echo "Arquivos processados: {$processed}\n";
        echo "Tempo de processamento: {$processingTime}s\n";
        echo "Concluído em: " . date('Y-m-d H:i:s') . "\n";
        
    } catch (Exception $e) {
        echo "ERRO CRÍTICO: " . $e->getMessage() . "\n";
        echo "Trace: " . $e->getTraceAsString() . "\n";
        exit(1);
    }
}
