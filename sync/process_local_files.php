<?php
/**
 * Processa apenas arquivos locais existentes
 * Útil quando o Windows está offline
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Database.class.php';
require_once __DIR__ . '/../includes/SyncLogger.class.php';
require_once __DIR__ . '/../includes/WXMLProcessor.class.php';
require_once __DIR__ . '/../includes/PDFProcessor.class.php';

class LocalFileProcessor {
    private $logger;
    
    // Diretórios locais
    private $localWxmlDir = '/var/www/html/ecgmanager/Enviados';
    private $localPdfDir = '/var/www/html/ecgmanager/ECG';
    
    public function __construct() {
        $this->logger = new SyncLogger();
    }
    
    public function processAll() {
        $total = 0;
        
        echo "=== Processamento de Arquivos Locais ===\n";
        echo "Iniciando em: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Processar WXMLs
        $wxmlCount = $this->processWXMLs();
        echo "WXMLs processados: {$wxmlCount}\n";
        
        // Processar PDFs
        $pdfCount = $this->processPDFs();
        echo "PDFs processados: {$pdfCount}\n";
        
        $total = $wxmlCount + $pdfCount;
        
        echo "\n=== Processamento Concluído ===\n";
        echo "Total de arquivos processados: {$total}\n";
        echo "Concluído em: " . date('Y-m-d H:i:s') . "\n";
        
        return $total;
    }
    
    private function processWXMLs() {
        if (!is_dir($this->localWxmlDir)) {
            $this->logger->log('local', 'error', "Diretório WXML local não encontrado: {$this->localWxmlDir}");
            return 0;
        }
        
        $files = glob($this->localWxmlDir . '/*.{WXML,wxml,xml}', GLOB_BRACE);
        $count = 0;
        
        foreach ($files as $file) {
            $filename = basename($file);
            $this->logger->log('local', 'info', "Processando WXML local: {$filename}");
            
            // Aqui você pode chamar sua lógica de processamento WXML
            // Ou apenas contar os arquivos
            $count++;
        }
        
        return $count;
    }
    
    private function processPDFs() {
        if (!is_dir($this->localPdfDir)) {
            $this->logger->log('local', 'error', "Diretório PDF local não encontrado: {$this->localPdfDir}");
            return 0;
        }
        
        $files = glob($this->localPdfDir . '/*.{PDF,pdf}', GLOB_BRACE);
        $count = 0;
        
        foreach ($files as $file) {
            $filename = basename($file);
            $this->logger->log('local', 'info', "Processando PDF local: {$filename}");
            
            // Aqui você pode chamar sua lógica de processamento PDF
            // Ou apenas contar os arquivos
            $count++;
        }
        
        return $count;
    }
    
    public function listLocalFiles() {
        echo "=== Arquivos Locais Disponíveis ===\n\n";
        
        echo "Diretório WXML: {$this->localWxmlDir}\n";
        if (is_dir($this->localWxmlDir)) {
            $wxmlFiles = glob($this->localWxmlDir . '/*.{WXML,wxml,xml}', GLOB_BRACE);
            echo "  Arquivos WXML: " . count($wxmlFiles) . "\n";
            
            if (count($wxmlFiles) > 0) {
                echo "  Últimos 5 arquivos:\n";
                foreach (array_slice($wxmlFiles, 0, 5) as $file) {
                    echo "  - " . basename($file) . " (" . filesize($file) . " bytes)\n";
                }
            }
        } else {
            echo "  ✗ Diretório não existe\n";
        }
        
        echo "\nDiretório PDF: {$this->localPdfDir}\n";
        if (is_dir($this->localPdfDir)) {
            $pdfFiles = glob($this->localPdfDir . '/*.{PDF,pdf}', GLOB_BRACE);
            echo "  Arquivos PDF: " . count($pdfFiles) . "\n";
            
            if (count($pdfFiles) > 0) {
                echo "  Últimos 5 arquivos:\n";
                foreach (array_slice($pdfFiles, 0, 5) as $file) {
                    echo "  - " . basename($file) . " (" . filesize($file) . " bytes)\n";
                }
            }
        } else {
            echo "  ✗ Diretório não existe\n";
        }
    }
}

// Execução via CLI
if (php_sapi_name() === 'cli') {
    $processor = new LocalFileProcessor();
    
    // Verificar argumentos
    if (isset($argv[1]) && $argv[1] === 'list') {
        $processor->listLocalFiles();
    } else {
        $processor->processAll();
    }
}

