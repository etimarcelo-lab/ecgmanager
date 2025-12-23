<?php
// api/sync_simple.php - APENAS TESTE
session_start();
require_once '../config/database.php';
require_once '../includes/Auth.class.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

// Configurações
$host = '192.168.140.199';
$share = 'Compartilhamento';

// Tentar os formatos de caminho
$paths = [
    "\\\\{$host}\\{$share}",
    "//{$host}/{$share}"
];

$accessible = false;
$accessiblePath = '';

foreach ($paths as $path) {
    // Método mais seguro usando file_exists
    if (@file_exists($path)) {
        $accessible = true;
        $accessiblePath = $path;
        break;
    }
}

if ($accessible) {
    // Se acessível, tentar listar 1 arquivo
    $files = @scandir($accessiblePath);
    $fileCount = $files ? count($files) - 2 : 0;
    
    echo json_encode([
        'success' => true,
        'message' => 'Conexão OK. ' . $fileCount . ' itens no diretório.',
        'path' => $accessiblePath,
        'file_count' => $fileCount
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Não foi possível acessar o compartilhamento.',
        'debug' => [
            'tested_paths' => $paths,
            'server_os' => PHP_OS,
            'current_user' => get_current_user()
        ]
    ]);
}