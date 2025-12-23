<?php
/**
 * Forçar processamento de arquivos de HOJE
 * Versão com truncamento de exam_number
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Database.class.php';
require_once __DIR__ . '/../includes/SyncLogger.class.php';

class ForceProcessToday {
    public $wxmlDir = '/var/www/html/ecgmanager/Enviados';
    public $pdfDir = '/var/www/html/ecgmanager/ECG';
    
    public function __construct() {
        $this->logger = new SyncLogger();
    }
    
    public function processToday() {
        $todayDMY = date('dmY');
        echo "=== Processamento de Arquivos de HOJE ({$todayDMY}) ===\n\n";
        
        $totalProcessed = 0;
        
        // Processar WXML
        $wxmlCount = $this->processWXMLToday();
        $totalProcessed += $wxmlCount;
        
        // Processar PDF
        $pdfCount = $this->processPDFToday();
        $totalProcessed += $pdfCount;
        
        echo "\n=== RESUMO ===\n";
        echo "WXML processados: {$wxmlCount}\n";
        echo "PDF processados: {$pdfCount}\n";
        echo "TOTAL: {$totalProcessed}\n";
        
        return $totalProcessed;
    }
    
    private function processWXMLToday() {
        if (!is_dir($this->wxmlDir)) {
            echo "❌ Diretório WXML não existe: {$this->wxmlDir}\n";
            return 0;
        }
        
        $todayDMY = date('dmY');
        $count = 0;
        
        // Buscar apenas arquivos de hoje para ser mais rápido
        $pattern = "*##{$todayDMY}*.WXML";
        $files = glob($this->wxmlDir . '/' . $pattern, GLOB_BRACE);
        
        if (empty($files)) {
            $files = glob($this->wxmlDir . '/*.{WXML,wxml,xml}', GLOB_BRACE);
        }
        
        echo "Analisando " . count($files) . " arquivos WXML...\n";
        
        foreach ($files as $file) {
            $filename = basename($file);
            
            // Extrair data do nome do arquivo: ##DDMMYYYYHHMM#
            if (preg_match('/##(\d{2})(\d{2})(\d{4})/', $filename, $matches)) {
                $day = $matches[1];
                $month = $matches[2];
                $year = $matches[3];
                $fileDateDMY = $day . $month . $year;
                
                if ($fileDateDMY === $todayDMY) {
                    echo "📄 WXML de HOJE: {$filename}\n";
                    
                    if (!$this->isWXMLProcessed($filename)) {
                        if ($this->processWXMLFile($file, $filename)) {
                            $count++;
                            echo "  ✅ Processado com sucesso\n";
                        } else {
                            echo "  ❌ Falha no processamento\n";
                        }
                    } else {
                        echo "  ⚠️  Já processado anteriormente\n";
                    }
                }
            }
        }
        
        if ($count == 0) {
            echo "Nenhum arquivo WXML com data de hoje encontrado.\n";
        }
        
        return $count;
    }
    
    private function processWXMLFile($filepath, $filename) {
        try {
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
            
            // Extrair dados básicos
            $data = $this->extractBasicWXMLData($xml, $filename);
            
            // Salvar no banco
            $db = Database::getInstance();
            
            // 1. Encontrar ou criar paciente
            $patientId = $this->findOrCreatePatient($db, $data['patient']);
            
            // 2. Encontrar ou criar médicos
            $respDoctorId = $this->findOrCreateDoctor($db, $data['doctors']['responsavel']);
            $reqDoctorId = $this->findOrCreateDoctor($db, $data['doctors']['solicitante']);
            
            // 3. Criar exame
            $examId = $this->createExam($db, $data['exam'], $patientId, $respDoctorId, $reqDoctorId, $filename);
            
            if ($examId) {
                echo "  ✅ Exame criado: ID {$examId}\n";
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            echo "  ❌ Erro: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    private function extractBasicWXMLData($xml, $filename) {
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
                'nome' => (string)$paciente->Nome ?: '',
                'data_nascimento' => $this->formatDate((string)$paciente->DataNascimento),
                'sexo' => $this->normalizeGender((string)$paciente->Sexo),
                'cpf' => (string)$paciente->CPF ?: ''
            ];
        }
        
        // Extrair data/hora do nome do arquivo
        if (preg_match('/##(\d{2})(\d{2})(\d{4})(\d{2})(\d{2})#/', $filename, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
            $hour = $matches[4];
            $minute = $matches[5];
            
            // TRUNCAR exam_number para máximo 50 caracteres
            $examNumber = str_replace('.WXML', '', $filename);
            if (strlen($examNumber) > 50) {
                $examNumber = substr($examNumber, 0, 47) . '...';
            }
            
            $data['exam'] = [
                'data' => $year . '-' . $month . '-' . $day,
                'hora' => $hour . ':' . $minute . ':00',
                'nro_exame' => $examNumber,
                'original_filename' => $filename
            ];
        } else {
            // Usar data atual se não conseguir extrair
            $examNumber = str_replace('.WXML', '', $filename);
            if (strlen($examNumber) > 50) {
                $examNumber = substr($examNumber, 0, 47) . '...';
            }
            
            $data['exam'] = [
                'data' => date('Y-m-d'),
                'hora' => date('H:i:s'),
                'nro_exame' => $examNumber,
                'original_filename' => $filename
            ];
        }
        
        // Extrair médicos (se existirem)
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
                    'crm' => (string)$medicos->Solicitante->CRM ?: ''
                ];
            }
        }
        
        return $data;
    }
    
    private function normalizeGender($gender) {
        if (empty($gender)) return 'Outro';
        
        $gender = strtoupper(trim($gender));
        $mapping = [
            'M' => 'Masculino',
            'F' => 'Feminino',
            'MASCULINO' => 'Masculino',
            'FEMININO' => 'Feminino',
            'MALE' => 'Masculino',
            'FEMALE' => 'Feminino'
        ];
        
        return isset($mapping[$gender]) ? $mapping[$gender] : 'Outro';
    }
    
    private function formatDate($date) {
        // Converter de DD/MM/YYYY para YYYY-MM-DD
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
            return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }
        return date('Y-m-d');
    }
    
    private function findOrCreatePatient($db, $patientData) {
        if (empty($patientData['nome'])) {
            throw new Exception("Nome do paciente não informado");
        }
        
        // Limpar e normalizar nome
        $patientName = trim($patientData['nome']);
        
        // Verificar por CPF
        if (!empty($patientData['cpf'])) {
            $cpf = preg_replace('/[^0-9]/', '', $patientData['cpf']);
            if (!empty($cpf)) {
                $stmt = $db->prepare("SELECT id FROM patients WHERE cpf = ? LIMIT 1");
                $stmt->bind_param("s", $cpf);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    return $row['id'];
                }
            }
        }
        
        // Verificar por nome (LIKE)
        $stmt = $db->prepare("SELECT id FROM patients WHERE full_name LIKE ? LIMIT 1");
        $searchName = "%{$patientName}%";
        $stmt->bind_param("s", $searchName);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return $row['id'];
        }
        
        // Criar novo paciente
        $patientId = 'PAT' . date('Ymd') . substr(md5($patientName), 0, 6);
        
        $stmt = $db->prepare("
            INSERT INTO patients (
                patient_id, full_name, birth_date, gender, cpf, created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $cpf = !empty($patientData['cpf']) ? preg_replace('/[^0-9]/', '', $patientData['cpf']) : '';
        
        $stmt->bind_param(
            "sssss",
            $patientId,
            $patientName,
            $patientData['data_nascimento'],
            $patientData['sexo'],
            $cpf
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
        
        // Limpar CRM (só números)
        $crm = preg_replace('/[^0-9]/', '', $doctorData['crm']);
        if (empty($crm)) {
            return null;
        }
        
        // Verificar se médico já existe
        $stmt = $db->prepare("SELECT id FROM doctors WHERE crm = ? LIMIT 1");
        $stmt->bind_param("s", $crm);
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
        
        $doctorName = trim($doctorData['nome']);
        
        $stmt->bind_param("ss", $doctorName, $crm);
        
        if ($stmt->execute()) {
            return $db->getLastInsertId();
        }
        
        return null;
    }
    
    private function createExam($db, $examData, $patientId, $respDoctorId, $reqDoctorId, $filename) {
        // Usar exam_number truncado para INSERT, mas guardar filename completo
        $stmt = $db->prepare("
            INSERT INTO exams (
                exam_number, patient_id, exam_date, exam_time,
                responsible_doctor_id, requesting_doctor_id,
                wxml_file_path, wxml_processed, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'realizado', NOW())
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
            return $db->getLastInsertId();
        }
        
        // Se falhar por duplicado, tentar UPDATE
        if ($stmt->errno == 1062) { // Duplicate entry
            echo "  ⚠️  Exame já existe, atualizando...\n";
            return $this->updateExam($db, $examData['nro_exame'], $respDoctorId, $reqDoctorId, $filename);
        }
        
        throw new Exception("Falha ao criar exame: " . $stmt->error . " (Código: " . $stmt->errno . ")");
    }
    
    private function updateExam($db, $examNumber, $respDoctorId, $reqDoctorId, $filename) {
        $stmt = $db->prepare("
            UPDATE exams 
            SET responsible_doctor_id = ?, 
                requesting_doctor_id = ?,
                wxml_file_path = ?,
                wxml_processed = 1,
                updated_at = NOW()
            WHERE exam_number = ?
        ");
        
        $stmt->bind_param("iiss", $respDoctorId, $reqDoctorId, $filename, $examNumber);
        
        if ($stmt->execute()) {
            // Obter ID do exame atualizado
            $stmt2 = $db->prepare("SELECT id FROM exams WHERE exam_number = ? LIMIT 1");
            $stmt2->bind_param("s", $examNumber);
            $stmt2->execute();
            $result = $stmt2->get_result();
            $row = $result->fetch_assoc();
            return $row ? $row['id'] : false;
        }
        
        throw new Exception("Falha ao atualizar exame: " . $stmt->error);
    }
    
    private function isWXMLProcessed($filename) {
        try {
            $db = Database::getInstance();
            $query = "SELECT id FROM exams WHERE wxml_file_path = ? LIMIT 1";
            $stmt = $db->prepare($query);
            if (!$stmt) return false;
            
            $stmt->bind_param("s", $filename);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->num_rows > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function processPDFToday() {
        // Simplesmente informar que precisa executar o process_pdf.php
        echo "\n⚠️  Para processar PDFs, execute:\n";
        echo "   php " . __DIR__ . "/process_pdf.php\n\n";
        return 0;
    }
}

// Execução
if (php_sapi_name() === 'cli') {
    echo "=== Processamento de Arquivos de HOJE ===\n";
    echo "Data: " . date('d/m/Y') . " (" . date('dmY') . ")\n";
    echo "Hora: " . date('H:i:s') . "\n\n";
    
    $processor = new ForceProcessToday();
    $processor->processToday();
    
    echo "\n=== PRÓXIMOS PASSOS ===\n";
    echo "1. Execute o processador de PDFs: php " . __DIR__ . "/process_pdf.php\n";
    echo "2. Verifique no banco: mysql -u ecg_user -p ecg_manager -e \"SELECT DATE(created_at) as data, COUNT(*) as total FROM exams WHERE DATE(created_at) >= CURDATE() GROUP BY DATE(created_at);\"\n";
}
