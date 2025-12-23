<?php
/**
 * Processamento de arquivos WXML
 * Executado via cron a cada 5 minutos
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Database.class.php';
require_once __DIR__ . '/../includes/SyncLogger.class.php';
require_once __DIR__ . '/../includes/WXMLProcessor.class.php';

class WXMLProcessorCLI {
    private $logger;
    private $processor;
    private $wxmlDir = '/var/www/html/ecgmanager/Enviados';
    private $wxmlColumn = 'wxml_file_path';

	private function normalizeGender($gender) {
	    if (empty($gender)) {
        	return 'Outro';
    	}
    
   	 $gender = strtoupper(trim($gender));
    
    	// Mapeamento para o ENUM da tabela
    	$mapping = [
	        'M' => 'Masculino',
	        'F' => 'Feminino',
	        'MASCULINO' => 'Masculino',
	        'FEMININO' => 'Feminino',
	        'MALE' => 'Masculino',
	        'FEMALE' => 'Feminino',
	        'H' => 'Masculino',
	        'HOMEM' => 'Masculino',
	        'MULHER' => 'Feminino',
	        '1' => 'Masculino',
	        '2' => 'Feminino'
	    ];
    
	    return isset($mapping[$gender]) ? $mapping[$gender] : 'Outro';
	}







    
    public function __construct() {
        $this->logger = new SyncLogger();
        $this->processor = new WXMLProcessor();
    }
    
    public function processNewWXMLs() {
        $processed = 0;
        $errors = 0;
        
        try {
            if (!is_dir($this->wxmlDir)) {
                $this->logger->log('wxml', 'error', 
                    "Diretório WXML não encontrado: {$this->wxmlDir}");
                return 0;
            }
            
            // Buscar arquivos .WXML novos
            $files = glob($this->wxmlDir . '/*.{WXML,wxml,xml}', GLOB_BRACE);
            $this->logger->log('wxml', 'info', 
                "Encontrados " . count($files) . " arquivos WXML");
            
            foreach ($files as $file) {
                $filename = basename($file);
                $this->logger->log('wxml', 'info', "Verificando: {$filename}");
                
                // Verificar se já foi processado
                if (!$this->isAlreadyProcessed($filename)) {
                    $this->logger->log('wxml', 'info', "Processando arquivo novo: {$filename}");
                    
                    // Processar arquivo
                    if ($this->processWXMLFile($file, $filename)) {
                        $processed++;
                        
                        // Mover para processados
                        if ($this->moveToProcessed($file, $filename)) {
                            $this->logger->log('wxml', 'success', 
                                "Arquivo movido para processados: {$filename}");
                        } else {
                            $this->logger->log('wxml', 'warning', 
                                "Arquivo processado mas não movido: {$filename}");
                        }
                        
                    } else {
                        $errors++;
                        $this->logger->log('wxml', 'error', 
                            "Falha ao processar WXML: {$filename}");
                    }
                } else {
                    $this->logger->log('wxml', 'info', 
                        "Arquivo já processado: {$filename}");
                }
            }
            
            $message = "Processamento WXML concluído. Sucessos: {$processed}, Erros: {$errors}";
            $this->logger->log('wxml', $errors === 0 ? 'success' : 'warning', $message);
            
        } catch (Exception $e) {
            $this->logger->log('wxml', 'error', 
                "Erro no processamento WXML: " . $e->getMessage());
        }
        
        return $processed;
    }
    
    private function isAlreadyProcessed($filename) {
        try {
            $db = Database::getInstance();
            $query = "SELECT id FROM exams WHERE wxml_file_path = ? AND wxml_processed = 1 LIMIT 1";
            $stmt = $db->prepare($query);
            if (!$stmt) {
                $this->logger->log('wxml', 'error', "Erro ao preparar query: " . $db->error);
                return false;
            }
            
            $stmt->bind_param("s", $filename);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->num_rows > 0;
        } catch (Exception $e) {
            $this->logger->log('wxml', 'error', "Erro ao verificar processamento: " . $e->getMessage());
            return false;
        }
    }
    
    private function processWXMLFile($filepath, $filename) {
        try {
            $this->logger->log('wxml', 'info', "Processando arquivo: {$filename}");
            
            // Ler conteúdo do arquivo
            $content = file_get_contents($filepath);
            if (!$content) {
                throw new Exception("Arquivo vazio ou não legível");
            }
            
            // Parsear XML
            $xml = simplexml_load_string($content);
            if ($xml === false) {
                throw new Exception("XML inválido");
            }
            
            // Extrair dados
            $data = $this->extractWXMLData($xml, $filename);
            
            // Salvar no banco
            $db = Database::getInstance();
            
            // 1. Encontrar ou criar paciente
            $patientId = $this->findOrCreatePatient($db, $data['patient']);
            $this->logger->log('wxml', 'info', "Paciente ID: {$patientId}");
            
            // 2. Encontrar ou criar médicos
            $respDoctorId = $this->findOrCreateDoctor($db, $data['doctors']['responsavel']);
            $reqDoctorId = $this->findOrCreateDoctor($db, $data['doctors']['solicitante']);
            $this->logger->log('wxml', 'info', 
                "Médicos - Responsável: {$respDoctorId}, Solicitante: {$reqDoctorId}");
            
            // 3. Criar exame
            $examId = $this->createExam($db, $data['exam'], $patientId, $respDoctorId, $reqDoctorId, $filename);
            
            if ($examId) {
                $this->logger->log('wxml', 'success', 
                    "WXML processado: {$filename} -> Exame ID: {$examId}", 
                    ['exam_number' => $data['exam']['nro_exame']]);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logger->log('wxml', 'error', 
                "Erro no arquivo {$filename}: " . $e->getMessage());
            return false;
        }
    }
    
    private function extractWXMLData($xml, $filename) {
        $data = [
            'patient' => [],
            'exam' => [],
            'doctors' => [
                'responsavel' => [],
                'solicitante' => []
            ]
        ];
        
        // Extrair paciente
        if (isset($xml->Paciente)) {
            $paciente = $xml->Paciente;
            $data['patient'] = [
                'id' => (string)$paciente->ID ?: '',
                'nome' => (string)$paciente->Nome ?: '',
                'data_nascimento' => $this->formatDate((string)$paciente->DataNascimento),
                'sexo' => $this->normalizeGender((string)$paciente->Sexo),
                'registro_clinico' => (string)$paciente->RegistroClinico ?: '',
                'rg' => (string)$paciente->RG ?: '',
                'cpf' => (string)$paciente->CPF ?: ''
            ];
        }
        
        // Extrair exame
        $data['exam'] = [
            'id' => (string)$xml->ID ?: '',
            'nro_exame' => (string)$xml->NroExame ?: '',
            'data' => $this->formatDate((string)$xml->Data),
            'hora' => $this->formatTime((string)$xml->Hora)
        ];
        
        // Extrair médicos
        if (isset($xml->Medicos)) {
            $medicos = $xml->Medicos;
            
            if (isset($medicos->Responsavel)) {
                $data['doctors']['responsavel'] = [
                    'nome' => (string)$medicos->Responsavel->Nome ?: '',
                    'crm' => (string)$medicos->Responsavel->CRM ?: ''
                ];
            }
            
            if (isset($medicos->Solicitante)) {
                $data['doctors']['solicitante'] = [
                    'nome' => (string)$medicos->Solicitante->Nome ?: '',
                    'crm' => (string)$medicos->Solicitante->CRM ?: '',
                    'funcao' => (string)$medicos->Solicitante->Funcao ?: ''
                ];
            }
        }
        
        $this->logger->log('wxml', 'info', "Dados extraídos: " . json_encode($data, JSON_PRETTY_PRINT));
        return $data;
    }
    
    private function formatDate($date) {
        // Converter de DD/MM/YYYY para YYYY-MM-DD
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
            return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }
        
        // Tentar outros formatos
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date)) {
            return $date;
        }
        
        // Se não for uma data válida, usar data atual
        return date('Y-m-d');
    }
    
    private function formatTime($time) {
        if (empty($time)) {
            return '00:00:00';
        }
        
        $time = trim($time);
        
        // Se já tem formato HH:MM:SS, retornar como está
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            return $time;
        }
        
        // Se tem formato HH:MM, adicionar segundos
        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $time . ':00';
        }
        
        // Remover segundos extras se existirem (ex: 14:30:00:00 -> 14:30:00)
        if (preg_match('/^(\d{2}:\d{2}:\d{2}):\d+$/', $time, $matches)) {
            return $matches[1];
        }
        
        // Tentar extrair hora de qualquer formato
        if (preg_match('/(\d{2}):(\d{2})/', $time, $matches)) {
            return $matches[1] . ':' . $matches[2] . ':00';
        }
        
        // Padrão se não conseguir parsear
        return '00:00:00';
    }
    
    private function findOrCreatePatient($db, $patientData) {
        if (empty($patientData['nome'])) {
            throw new Exception("Nome do paciente não informado");
        }
        
        // Verificar se paciente já existe pelo CPF ou nome + data nascimento
        $query = "SELECT id FROM patients WHERE cpf = ? LIMIT 1";
        if (empty($patientData['cpf'])) {
            $query = "SELECT id FROM patients WHERE full_name = ? AND birth_date = ? LIMIT 1";
        }
        
        $stmt = $db->prepare($query);
        
        if (empty($patientData['cpf'])) {
            $stmt->bind_param("ss", $patientData['nome'], $patientData['data_nascimento']);
        } else {
            $stmt->bind_param("s", $patientData['cpf']);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return $row['id'];
        }
        
        // Criar novo paciente
        $stmt = $db->prepare("
            INSERT INTO patients (
                patient_id, full_name, birth_date, gender,
                clinical_record, rg, cpf, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->bind_param(
            "sssssss",
            $patientData['id'],
            $patientData['nome'],
            $patientData['data_nascimento'],
            $patientData['sexo'],
            $patientData['registro_clinico'],
            $patientData['rg'],
            $patientData['cpf']
        );
        
        if ($stmt->execute()) {
            return $db->getLastInsertId();
        }
        
        throw new Exception("Falha ao criar paciente: " . $stmt->error);
    }
    
    private function findOrCreateDoctor($db, $doctorData) {
        if (empty($doctorData['nome']) || empty($doctorData['crm'])) {
            return null;
        }
        
        // Verificar se médico já existe
        $stmt = $db->prepare("
            SELECT id FROM doctors WHERE crm = ? LIMIT 1
        ");
        $stmt->bind_param("s", $doctorData['crm']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return $row['id'];
        }
        
        // Criar novo médico
        $stmt = $db->prepare("
            INSERT INTO doctors (name, crm, created_at) 
            VALUES (?, ?, NOW())
        ");
        
        $stmt->bind_param("ss", $doctorData['nome'], $doctorData['crm']);
        
        if ($stmt->execute()) {
            return $db->getLastInsertId();
        }
        
        return null;
    }
    
    private function createExam($db, $examData, $patientId, $respDoctorId, $reqDoctorId, $filename) {
        try {
            $stmt = $db->prepare("
                INSERT INTO exams (
                    exam_number, patient_id, exam_date, exam_time,
                    responsible_doctor_id, requesting_doctor_id,
                    wxml_file_path, wxml_processed, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'realizado', NOW())
                ON DUPLICATE KEY UPDATE
                    responsible_doctor_id = VALUES(responsible_doctor_id),
                    requesting_doctor_id = VALUES(requesting_doctor_id),
                    wxml_file_path = VALUES(wxml_file_path),
                    wxml_processed = VALUES(wxml_processed),
                    updated_at = NOW()
            ");
            
            if (!$stmt) {
                throw new Exception("Erro ao preparar query: " . $db->error);
            }
        
            $stmt->bind_param(
                "sissiis",
                $examData['nro_exame'],
                $patientId,
                $examData['data'],
                $examData['hora'],
                $respDoctorId,
                $reqDoctorId,
                $filename
            );
            
            if ($stmt->execute()) {
                return $db->getLastInsertId() ?: $this->getExamIdByNumber($db, $examData['nro_exame']);
            }
            
            throw new Exception("Falha ao criar exame: " . $stmt->error);
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    private function getExamIdByNumber($db, $examNumber) {
        $stmt = $db->prepare("SELECT id FROM exams WHERE exam_number = ? LIMIT 1");
        $stmt->bind_param("s", $examNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row ? $row['id'] : false;
    }
    
    private function moveToProcessed($filepath, $filename) {
        $processedDir = $this->wxmlDir . '/processed';
        if (!is_dir($processedDir)) {
            if (!mkdir($processedDir, 0755, true)) {
                $this->logger->log('wxml', 'error', 
                    "Falha ao criar diretório: {$processedDir}");
                return false;
            }
        }
        
        $newPath = $processedDir . '/' . $filename;
        
        // Verificar se já existe no destino
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
        
        // Se falhar ao mover, tentar copiar e deletar
        if (copy($filepath, $newPath)) {
            unlink($filepath);
            return true;
        }
        
        $this->logger->log('wxml', 'error', 
            "Falha ao mover arquivo: {$filename} para {$newPath}");
        return false;
    }
}

// Execução via CLI
if (php_sapi_name() === 'cli') {
    $startTime = microtime(true);
    
    try {
        echo "=== Processamento WXML ===\n";
        echo "Iniciando em: " . date('Y-m-d H:i:s') . "\n\n";
        
        $processor = new WXMLProcessorCLI();
        $processed = $processor->processNewWXMLs();
        
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
