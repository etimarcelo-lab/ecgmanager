<?php
// Configurações gerais do sistema ECG Manager

// Determinar se estamos em modo CLI ou web
$isCli = (php_sapi_name() === 'cli');

// Configurações base
$config = [
    'system_name' => 'ECG Manager',
    'version' => '1.0.0',
    'environment' => 'production',
    'debug' => false,
    
    // Paths absolutos
    'base_path' => '/var/www/html/ecgmanager/',
    'upload_path' => '/var/www/html/ecgmanager/uploads/',
    'log_path' => '/var/www/html/ecgmanager/logs/',
    
    // Sincronização
    'sync_interval' => 300,
    'max_file_size' => 10485760,
    'allowed_extensions' => ['pdf', 'wxml', 'xml'],
    
    // Segurança
    'session_timeout' => 3600,
    'csrf_protection' => true,
    'password_min_length' => 6,
    
    // Email
    'mail_enabled' => false,
    'mail_from' => 'noreply@hospital.com',
    'mail_from_name' => 'ECG Manager',
    
    // Backup
    'backup_enabled' => true,
    'backup_path' => '/var/backups/ecgmanager/',
    'backup_retention_days' => 30,
    
    // Diretórios de sincronização
    'directories' => [
        'source_wxml' => '/mnt/wincardio/laudos/Enviados',
        'source_pdf' => '/mnt/wincardio/laudos/ECG',
        'dest_wxml' => '/var/www/html/ecgmanager/Enviados',
        'dest_pdf' => '/var/www/html/ecgmanager/ECG',
        'processed_wxml' => '/var/www/html/ecgmanager/Enviados/processed',
        'processed_pdf' => '/var/www/html/ecgmanager/ECG/processed'
    ]
];

// Adicionar URLs apenas se não for CLI
if (!$isCli && isset($_SERVER['HTTP_HOST'])) {
    $config['base_url'] = 'http://' . $_SERVER['HTTP_HOST'] . '/ecgmanager/';
    $config['site_url'] = 'http://' . $_SERVER['HTTP_HOST'];
} else {
    $config['base_url'] = 'http://localhost/ecgmanager/';
    $config['site_url'] = 'http://localhost';
}

// Se estiver em CLI, ajustar alguns paths
if ($isCli) {
    // Garantir que os diretórios de log existam
    if (!is_dir($config['log_path'])) {
        @mkdir($config['log_path'], 0755, true);
    }
    
    // Garantir que os diretórios de upload existam
    if (!is_dir($config['upload_path'])) {
        @mkdir($config['upload_path'], 0755, true);
    }
}

return $config;
?>
