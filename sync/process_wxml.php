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
            
            // Log da estrutura do XML para debug
            $this->debugXMLStructure($xml, $filename);
            
            // Extrair dados
            $data = $this->extractWXMLData($xml, $filename);
            
            // Validar dados mínimos
            if (empty($data['patient']['nome'])) {
                throw new Exception("Nome do paciente não encontrado no XML");
            }
            
            if (empty($data['exam']['nro_exame'])) {
                // Se não tem número do exame, usar o nome do arquivo como fallback
                $data['exam']['nro_exame'] = pathinfo($filename, PATHINFO_FILENAME);
                $this->logger->log('wxml', 'warning', 
                    "Número do exame não encontrado, usando nome do arquivo: {$data['exam']['nro_exame']}");
            }
            
            // Salvar no banco
            $db = Database::getInstance();
            
            // 1. Encontrar ou criar paciente
            $patientId = $this->findOrCreatePatient($db, $data['patient']);
            $this->logger->log('wxml', 'info', "Paciente ID: {$patientId}");
            
            // 2. Encontrar ou criar médicos
            $respDoctorId = null;
            $reqDoctorId = null;
            
            if (!empty($data['doctors']['responsavel']['nome']) || !empty($data['doctors']['responsavel']['crm'])) {
                $respDoctorId = $this->findOrCreateDoctor($db, $data['doctors']['responsavel']);
            }
            
            if (!empty($data['doctors']['solicitante']['nome']) || !empty($data['doctors']['solicitante']['crm'])) {
                $reqDoctorId = $this->findOrCreateDoctor($db, $data['doctors']['solicitante']);
            }
            
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
    
    private function debugXMLStructure($xml, $filename) {
        $tags = [];
        foreach ($xml as $key => $value) {
            $tags[] = $key;
            if ($value->count() > 0) {
                foreach ($value as $subkey => $subvalue) {
                    $tags[] = "  {$subkey}";
                    if ($subvalue->count() > 0) {
                        foreach ($subvalue as $subsubkey => $subsubvalue) {
                            $tags[] = "    {$subsubkey}";
                        }
                    }
                }
            }
        }
        
        $this->logger->log('wxml', 'debug', 
            "Estrutura XML de {$filename}: " . implode(', ', array_slice($tags, 0, 20)));
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
        
        $this->logger->log('wxml', 'debug', "Iniciando extração de dados do XML");
        
        // ========== EXTRAIR PACIENTE ==========
        if (isset($xml->Paciente)) {
            $paciente = $xml->Paciente;
            $data['patient'] = [
                'id' => $this->getStringValue($paciente->ID),
                'nome' => $this->getStringValue($paciente->Nome),
                'data_nascimento' => $this->formatDate($this->getStringValue($paciente->DataNascimento)),
                'sexo' => $this->normalizeGender($this->getStringValue($paciente->Sexo)),
                'registro_clinico' => $this->getStringValue($paciente->RegistroClinico),
                'rg' => $this->getStringValue($paciente->RG),
                'cpf' => $this->getStringValue($paciente->CPF)
            ];
            $this->logger->log('wxml', 'debug', "Paciente extraído: {$data['patient']['nome']}");
        } else {
            $this->logger->log('wxml', 'warning', "Tag <Paciente> não encontrada no XML");
        }
        
        // ========== EXTRAIR EXAME ==========
        // Primeiro tenta dentro da tag <Exame>
        $nro_exame = '';
        $exame_data = '';
        $exame_hora = '';
        $exame_id = '';
        
        if (isset($xml->Exame)) {
            $exame = $xml->Exame;
            $nro_exame = $this->getStringValue($exame->NroExame);
            $exame_data = $this->getStringValue($exame->Data);
            $exame_hora = $this->getStringValue($exame->Hora);
            $exame_id = $this->getStringValue($exame->ID);
            $this->logger->log('wxml', 'debug', "Exame encontrado na tag <Exame>: NroExame={$nro_exame}");
        }
        
        // Se não encontrou, tenta no nível raiz
        if (empty($nro_exame) && isset($xml->NroExame)) {
            $nro_exame = $this->getStringValue($xml->NroExame);
            $exame_data = $this->getStringValue($xml->Data);
            $exame_hora = $this->getStringValue($xml->Hora);
            $exame_id = $this->getStringValue($xml->ID);
            $this->logger->log('wxml', 'debug', "Exame encontrado no nível raiz: NroExame={$nro_exame}");
        }
        
        // Se ainda não tem data, tenta extrair do nome do arquivo
        if (empty($exame_data)) {
            // Formato: MMD#NOME##DDMMYYYYHHMM#MEDICO#E.WXML
            if (preg_match('/##(\d{2})(\d{2})(\d{4})(\d{2})(\d{2})#/', $filename, $matches)) {
                $exame_data = $matches[3] . '-' . $matches[2] . '-' . $matches[1]; // YYYY-MM-DD
                $exame_hora = $matches[4] . ':' . $matches[5] . ':00'; // HH:MM:SS
                $this->logger->log('wxml', 'debug', "Data/Hora extraídas do nome do arquivo: {$exame_data} {$exame_hora}");
            }
        }
        
        $data['exam'] = [
            'id' => $exame_id,
            'nro_exame' => $nro_exame,
            'data' => $this->formatDate($exame_data),
            'hora' => $this->formatTime($exame_hora)
        ];
        
        // ========== EXTRAIR MÉDICOS ==========
        // Tenta primeiro dentro de <Exame><Medicos>
        if (isset($xml->Exame) && isset($xml->Exame->Medicos)) {
            $medicos = $xml->Exame->Medicos;
            
            if (isset($medicos->Responsavel)) {
                $data['doctors']['responsavel'] = [
                    'nome' => $this->getStringValue($medicos->Responsavel->Nome),
                    'crm' => $this->getStringValue($medicos->Responsavel->CRM)
                ];
            }
            
            if (isset($medicos->Solicitante)) {
                $data['doctors']['solicitante'] = [
                    'nome' => $this->getStringValue($medicos->Solicitante->Nome),
                    'crm' => $this->getStringValue($medicos->Solicitante->CRM),
                    'funcao' => $this->getStringValue($medicos->Solicitante->Funcao)
                ];
            }
        } 
        // Se não encontrou, tenta no nível raiz
        elseif (isset($xml->Medicos)) {
            $medicos = $xml->Medicos;
            
            if (isset($medicos->Responsavel)) {
                $data['doctors']['responsavel'] = [
                    'nome' => $this->getStringValue($medicos->Responsavel->Nome),
                    'crm' => $this->getStringValue($medicos->Responsavel->CRM)
                ];
            }
            
            if (isset($medicos->Solicitante)) {
                $data['doctors']['solicitante'] = [
                    'nome' => $this->getStringValue($medicos->Solicitante->Nome),
                    'crm' => $this->getStringValue($medicos->Solicitante->CRM),
                    'funcao' => $this->getStringValue($medicos->Solicitante->Funcao)
                ];
            }
        }
        
        $this->logger->log('wxml', 'info', "Dados extraídos: " . json_encode($data, JSON_PRETTY_PRINT));
        return $data;
    }
    
    private function getStringValue($element) {
        if (isset($element)) {
            return (string)$element;
        }
        return '';
    }
    
    private function formatDate($date) {
        if (empty($date)) {
            $this->logger->log('wxml', 'warning', "Data vazia, usando data atual");
            return date('Y-m-d');
        }
        
        $date = trim($date);
        
        // Converter de DD/MM/YYYY para YYYY-MM-DD
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
            return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }
        
        // Tentar outros formatos
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date)) {
            return $date;
        }
        
        // Se não for uma data válida, usar data atual
        $this->logger->log('wxml', 'warning', "Formato de data inválido: {$date}, usando data atual");
        return date('Y-m-d');
    }
    
    private function formatTime($time) {
        if (empty($time)) {
            $this->logger->log('wxml', 'debug', "Hora vazia, usando 00:00:00");
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
        $this->logger->log('wxml', 'warning', "Formato de hora inválido: {$time}, usando 00:00:00");
        return '00:00:00';
    }
    
    private function findOrCreatePatient($db, $patientData) {
        if (empty($patientData['nome'])) {
            throw new Exception("Nome do paciente não informado");
        }
        
        $this->logger->log('wxml', 'debug', "Buscando paciente: {$patientData['nome']}");
        
        // Primeiro, tentar pelo CPF se existir
        if (!empty($patientData['cpf'])) {
            $query = "SELECT id FROM patients WHERE cpf = ? LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bind_param("s", $patientData['cpf']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $this->logger->log('wxml', 'debug', "Paciente encontrado pelo CPF: {$row['id']}");
                return $row['id'];
            }
        }
        
        // Se não encontrou pelo CPF, tentar pelo nome + data nascimento
        $query = "SELECT id FROM patients WHERE full_name = ? AND birth_date = ? LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bind_param("ss", $patientData['nome'], $patientData['data_nascimento']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $this->logger->log('wxml', 'debug', "Paciente encontrado pelo nome+data: {$row['id']}");
            return $row['id'];
        }
        
        // Criar novo paciente
        $this->logger->log('wxml', 'info', "Criando novo paciente: {$patientData['nome']}");
        
        $stmt = $db->prepare("
            INSERT INTO patients (
                patient_id, full_name, birth_date, gender,
                clinical_record, rg, cpf, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        if (!$stmt) {
            throw new Exception("Erro ao preparar query de paciente: " . $db->error);
        }
        
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
            $patientId = $db->insert_id;
            $this->logger->log('wxml', 'success', "Novo paciente criado: ID {$patientId}");
            return $patientId;
        }
        
        throw new Exception("Falha ao criar paciente: " . $stmt->error);
    }
    
    private function findOrCreateDoctor($db, $doctorData) {
        if (empty($doctorData['nome']) && empty($doctorData['crm'])) {
            $this->logger->log('wxml', 'debug', "Dados do médico vazios, retornando null");
            return null;
        }
        
        // Se tem CRM, buscar por ele
        if (!empty($doctorData['crm'])) {
            $stmt = $db->prepare("SELECT id FROM doctors WHERE crm = ? LIMIT 1");
            $stmt->bind_param("s", $doctorData['crm']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $this->logger->log('wxml', 'debug', "Médico encontrado pelo CRM: {$row['id']}");
                return $row['id'];
            }
        }
        
        // Se tem nome, buscar por nome
        if (!empty($doctorData['nome'])) {
            $stmt = $db->prepare("SELECT id FROM doctors WHERE name = ? LIMIT 1");
            $stmt->bind_param("s", $doctorData['nome']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $this->logger->log('wxml', 'debug', "Médico encontrado pelo nome: {$row['id']}");
                return $row['id'];
            }
        }
        
        // Criar novo médico apenas se tiver nome ou CRM
        if (!empty($doctorData['nome']) || !empty($doctorData['crm'])) {
            $this->logger->log('wxml', 'info', "Criando novo médico: {$doctorData['nome']} (CRM: {$doctorData['crm']})");
            
            $stmt = $db->prepare("
                INSERT INTO doctors (name, crm, created_at) 
                VALUES (?, ?, NOW())
            ");
            
            if (!$stmt) {
                $this->logger->log('wxml', 'error', "Erro ao preparar query de médico: " . $db->error);
                return null;
            }
            
            $stmt->bind_param("ss", $doctorData['nome'], $doctorData['crm']);
            
            if ($stmt->execute()) {
                $doctorId = $db->insert_id;
                $this->logger->log('wxml', 'success', "Novo médico criado: ID {$doctorId}");
                return $doctorId;
            } else {
                $this->logger->log('wxml', 'error', "Erro ao criar médico: " . $stmt->error);
            }
        }
        
        return null;
    }
    
    private function createExam($db, $examData, $patientId, $respDoctorId, $reqDoctorId, $filename) {
        try {
            $this->logger->log('wxml', 'debug', "Criando exame: {$examData['nro_exame']} para paciente {$patientId}");
            
            // Primeiro, verificar se o exame já existe
            $existingExamId = $this->getExamIdByNumber($db, $examData['nro_exame']);
            
            if ($existingExamId) {
                // Atualizar exame existente
                $stmt = $db->prepare("
                    UPDATE exams 
                    SET patient_id = ?,
                        responsible_doctor_id = ?,
                        requesting_doctor_id = ?,
                        exam_date = ?,
                        exam_time = ?,
                        wxml_file_path = ?,
                        wxml_processed = 1,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                if (!$stmt) {
                    throw new Exception("Erro ao preparar query de update: " . $db->error);
                }
                
                $stmt->bind_param(
                    "iiisssi",
                    $patientId,
                    $respDoctorId,
                    $reqDoctorId,
                    $examData['data'],
                    $examData['hora'],
                    $filename,
                    $existingExamId
                );
                
                if ($stmt->execute()) {
                    $this->logger->log('wxml', 'info', "Exame atualizado: ID {$existingExamId}");
                    return $existingExamId;
                } else {
                    throw new Exception("Falha ao atualizar exame: " . $stmt->error);
                }
            } else {
                // Criar novo exame
                $stmt = $db->prepare("
                    INSERT INTO exams (
                        exam_number, patient_id, exam_date, exam_time,
                        responsible_doctor_id, requesting_doctor_id,
                        wxml_file_path, wxml_processed, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'realizado', NOW())
                ");
                
                if (!$stmt) {
                    throw new Exception("Erro ao preparar query de insert: " . $db->error);
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
                    $examId = $db->insert_id;
                    $this->logger->log('wxml', 'success', "Novo exame criado: ID {$examId}");
                    return $examId;
                } else {
                    // Se for erro de duplicidade, tentar buscar o ID
                    if ($stmt->errno == 1062) {
                        $existingId = $this->getExamIdByNumber($db, $examData['nro_exame']);
                        if ($existingId) {
                            $this->logger->log('wxml', 'warning', "Exame já existe (duplicidade): ID {$existingId}");
                            return $existingId;
                        }
                    }
                    throw new Exception("Falha ao criar exame: " . $stmt->error . " (Código: " . $stmt->errno . ")");
                }
            }
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    private function getExamIdByNumber($db, $examNumber) {
        if (empty($examNumber)) {
            return false;
        }
        
        $stmt = $db->prepare("SELECT id FROM exams WHERE exam_number = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        
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
