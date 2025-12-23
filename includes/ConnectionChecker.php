<?php
// includes/ConnectionChecker.php - VERSÃO CORRIGIDA
class ConnectionChecker {
    // O computador do eletro (alvo) está em 192.168.140.199
    private $host = '192.168.140.199';  // IP DO COMPUTADOR DO ELETRO
    private $share = 'Compartilhamento';
    
    public function getStatus() {
        // Tentar diferentes formatos de caminho
        $paths = [
            "\\\\{$this->host}\\{$this->share}",
            "//{$this->host}/{$this->share}/"
        ];
        
        foreach ($paths as $path) {
            if (@file_exists($path)) {
                return 'connected';
            }
        }
        
        // Se não conseguir acessar o compartilhamento, testar ping
        if ($this->pingHost()) {
            return 'disconnected'; // Responde ping mas não tem acesso ao compartilhamento
        }
        
        return 'disconnected'; // Nem ping responde
    }
    
    private function pingHost() {
        $pingCmd = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' 
            ? "ping -n 1 -w 1000 {$this->host}"
            : "ping -c 1 -W 1 {$this->host}";
        
        exec($pingCmd, $output, $returnCode);
        return $returnCode === 0;
    }
    
    public function getMessage() {
        $status = $this->getStatus();
        
        switch($status) {
            case 'connected':
                return "Conectado ao computador do eletro (192.168.140.199)";
            case 'disconnected':
                return "Sem conexão com o computador do eletro";
            default:
                return "Verificando conexão...";
        }
    }
    
    // Método para diagnóstico
    public function getDetailedStatus() {
        return [
            'server_ip' => $_SERVER['SERVER_ADDR'] ?? 'desconhecido',
            'target_ip' => $this->host,
            'target_share' => $this->share,
            'status' => $this->getStatus(),
            'message' => $this->getMessage(),
            'can_ping' => $this->pingHost(),
            'tested_paths' => [
                "\\\\{$this->host}\\{$this->share}",
                "//{$this->host}/{$this->share}/"
            ]
        ];
    }
}