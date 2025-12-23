<?php
class SyncLogger {
    private $db;
    private static $logCache = [];
    private static $rateLimit = [];
    
    // Configurações para controle de volume
    private $config = [
        'max_logs_per_minute' => 60,      // Máximo 60 logs por minuto por tipo
        'min_interval_same_log' => 30,    // 30 segundos entre logs idênticos
        'batch_log_threshold' => 10,      // Se mais de 10 operações similares, logar em batch
        'skip_success_logs' => false,     // Pular logs de sucesso para operações rotineiras
    ];
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Método principal para registrar logs com controle de volume
     */
    public function log($type, $status, $message, $records = 0) {
        // 1. Verificar rate limiting por tipo
        $minuteKey = date('Y-m-d-H-i') . '-' . $type;
        if (!isset(self::$rateLimit[$minuteKey])) {
            self::$rateLimit[$minuteKey] = 0;
        }
        
        self::$rateLimit[$minuteKey]++;
        if (self::$rateLimit[$minuteKey] > $this->config['max_logs_per_minute']) {
            // Excedeu o limite por minuto - não logar
            return false;
        }
        
        // 2. Verificar logs idênticos recentes
        $logKey = md5($type . $status . substr($message, 0, 200));
        $now = time();
        
        if (isset(self::$logCache[$logKey])) {
            $timeDiff = $now - self::$logCache[$logKey]['last_log'];
            
            // Se o mesmo log foi registrado recentemente
            if ($timeDiff < $this->config['min_interval_same_log']) {
                // Incrementar contador em cache
                self::$logCache[$logKey]['count']++;
                self::$logCache[$logKey]['last_update'] = $now;
                
                // Se tiver muitos logs similares, logar como batch
                if (self::$logCache[$logKey]['count'] >= $this->config['batch_log_threshold'] && 
                    $now - self::$logCache[$logKey]['last_batch_log'] > 60) {
                    
                    // Logar como batch
                    $batchMessage = "[BATCH] " . self::$logCache[$logKey]['count'] . "x - " . 
                                   substr($message, 0, 100) . "...";
                    $this->insertLog($type, $status, $batchMessage, 
                                    self::$logCache[$logKey]['total_records']);
                    
                    // Resetar contador
                    self::$logCache[$logKey]['count'] = 0;
                    self::$logCache[$logKey]['last_batch_log'] = $now;
                    self::$logCache[$logKey]['total_records'] = 0;
                }
                
                // Atualizar total de registros
                self::$logCache[$logKey]['total_records'] += $records;
                return false;
            }
        }
        
        // 3. Pular logs de sucesso para operações rotineiras (opcional)
        if ($this->config['skip_success_logs'] && $status === 'success') {
            // Para certos tipos rotineiros, não logar sucesso
            $routineTypes = ['sync', 'file_copy', 'heartbeat'];
            if (in_array($type, $routineTypes)) {
                return false;
            }
        }
        
        // 4. Registrar o log
        $result = $this->insertLog($type, $status, $message, $records);
        
        // 5. Atualizar cache
        self::$logCache[$logKey] = [
            'last_log' => $now,
            'last_batch_log' => $now,
            'count' => 1,
            'total_records' => $records,
            'last_update' => $now
        ];
        
        return $result;
    }
    
    /**
     * Método privado para inserir log no banco
     */
    private function insertLog($type, $status, $message, $records = 0) {
        try {
            $query = "INSERT INTO sync_logs (sync_type, status, message, records_processed, processing_time, ip_address, user_agent) 
                      VALUES (?, ?, ?, ?, 0.0, ?, ?)";
            
            $stmt = $this->db->prepare($query);
            
            if (!$stmt) {
                error_log("Erro ao preparar query de log: " . $this->db->error);
                return false;
            }
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            $stmt->bind_param('ssisss', 
                $type,        // sync_type
                $status,      // status
                $message,     // message
                $records,     // records_processed
                $ipAddress,   // ip_address
                $userAgent    // user_agent
            );
            
            if (!$stmt->execute()) {
                error_log("Erro ao executar query de log: " . $stmt->error);
                return false;
            }
            
            $insertId = $stmt->insert_id;
            $stmt->close();
            
            return $insertId;
            
        } catch (Exception $e) {
            error_log("Exception ao registrar log: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Método para log de erro crítico (sempre registra)
     */
    public function logCritical($type, $message, $records = 0) {
        return $this->insertLog($type, 'error', "[CRITICAL] " . $message, $records);
    }
    
    /**
     * Método para log de resumo (útil para batch operations)
     */
    public function logSummary($type, $status, $totalOperations, $successful, $failed, $message = '') {
        $summary = "Resumo: Total={$totalOperations}, Sucesso={$successful}, Falhas={$failed}";
        if (!empty($message)) {
            $summary .= " - " . $message;
        }
        
        return $this->insertLog($type, $status, $summary, $totalOperations);
    }
    
    /**
     * Limpar cache (útil para testes ou reinicialização)
     */
    public static function clearCache() {
        self::$logCache = [];
        self::$rateLimit = [];
    }
    
    /**
     * Configurar parâmetros
     */
    public function setConfig($key, $value) {
        if (array_key_exists($key, $this->config)) {
            $this->config[$key] = $value;
        }
    }
}
?>