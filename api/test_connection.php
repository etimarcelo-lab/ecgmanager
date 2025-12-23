<?php
// api/test_connection.php
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

$remoteComputer = '\\\\192.168.140.199';

function testConnection($host) {
    // Remove as barras duplas no início se existirem
    $host = ltrim($host, '\\');
    
    // Tenta diferentes métodos de conexão
    $methods = [
        'ping' => function($host) {
            // Usa ping (Windows)
            $output = [];
            $command = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' 
                ? "ping -n 1 -w 1000 $host"
                : "ping -c 1 -W 1 $host";
            
            exec($command, $output, $result);
            return $result === 0;
        },
        
        'fsockopen' => function($host) {
            // Tenta conexão via porta 445 (SMB/CIFS)
            $fp = @fsockopen($host, 445, $errno, $errstr, 2);
            if ($fp) {
                fclose($fp);
                return true;
            }
            return false;
        },
        
        'smbclient' => function($host) {
            // Tenta listar compartilhamentos (se smbclient estiver disponível)
            $output = [];
            exec("smbclient -L $host -N 2>&1", $output, $result);
            return $result === 0 || stripos(implode(' ', $output), 'sharename') !== false;
        },
        
        'mount_test' => function($host) {
            // Tenta montar temporariamente (Linux/Unix)
            $testDir = sys_get_temp_dir() . '/smb_test_' . uniqid();
            if (!mkdir($testDir)) return false;
            
            $command = "mount -t cifs //$host/test $testDir -o guest 2>&1";
            exec($command, $output, $result);
            
            // Tenta desmontar se conseguiu
            if ($result === 0) {
                exec("umount $testDir");
            }
            
            rmdir($testDir);
            return $result === 0;
        }
    ];
    
    // Tenta cada método
    foreach ($methods as $methodName => $method) {
        try {
            if ($method($host)) {
                return [
                    'success' => true,
                    'method' => $methodName,
                    'message' => "Conexão estabelecida via $methodName"
                ];
            }
        } catch (Exception $e) {
            continue;
        }
    }
    
    return [
        'success' => false,
        'message' => 'Não foi possível estabelecer conexão'
    ];
}

// Testar conexão
$result = testConnection($remoteComputer);

// Registrar no log se configurado
if (isset($_GET['log']) && $_GET['log'] == 'true') {
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'] ?? 0;
    $ip = $_SERVER['REMOTE_ADDR'];
    $message = $result['success'] ? 
        "Conexão com $remoteComputer estabelecida" : 
        "Falha na conexão com $remoteComputer";
    
    $db->query("INSERT INTO logs (user_id, action, details, ip_address) 
                VALUES ($userId, 'connection_test', '$message', '$ip')");
}

header('Content-Type: application/json');
echo json_encode($result);
?>
