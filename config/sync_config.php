<?php
// config/sync_config.php
return [
    'remote_computer' => '\\\\192.168.150.199',
    'remote_share' => 'Compartilhamento', // Nome do compartilhamento
    'local_dirs' => [
        'sync' => '../uploads/sync/',
        'pdf_reports' => '../uploads/pdf_reports/',
        'wxml_processed' => '../uploads/wxml_processed/',
        'processed' => '../uploads/sync/processed/',
        'errors' => '../uploads/sync/errors/'
    ],
    'file_patterns' => [
        'pdf' => '*.pdf',
        'wxml' => '*.wxml'
    ],
    'ignore_patterns' => [
        '/^REPORT\.PDF$/i',
        '/^DOCUMENT\.PDF$/i',
        '/^DOC\.PDF$/i',
        '/^FILE\.PDF$/i',
        '/^ARQUIVO\.PDF$/i',
        '/^LAUDO\.PDF$/i',
        '/^EXAME\.PDF$/i'
    ],
    'connection_timeout' => 5, // segundos
    'max_file_size' => 50 * 1024 * 1024, // 50MB
];
?>
