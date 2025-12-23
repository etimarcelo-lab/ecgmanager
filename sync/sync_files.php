<?php
/**
 * Sincronização de arquivos do Windows para o servidor local
 * Executado via cron a cada 1 minuto
 * APENAS COPIA - não remove ou move arquivos originais no Windows
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Database.class.php';
require_once __DIR__ . '/../includes/SyncLogger.class.php';

class FileSyncCLI {
    private $logger;
    
    // Diretórios Windows (montados) - APENAS LEITURA
    private $windowsWxmlDir = '/mnt/wincardio/laudos/Enviados';
    private $windowsPdfDir = '/mnt/wincardio/laudos/ECG';
    
    // Diretórios locais
    private $localWxmlDir = '/var/www/html/ecgmanager/Enviados';
    private $localPdfDir = '/var/www/html/ecgmanager/ECG';
    
    // Controle de arquivos já copiados (para evitar recopiar)
    private $copiedFilesCache = [];
    private $cacheFile = '/tmp/sync_copied_files.cache';
    
    public function __construct() {
        $this->logger = new SyncLogger();
        $this->loadCopiedFilesCache();
    }
    
    public function syncFiles() {
        $synced = 0;
        $warnings = 0;
        
        try {
            echo "=== Sincronização de Arquivos ===\n";
            echo "Iniciando em: " . date('Y-m-d H:i:s') . "\n\n";
            
            // Verificar se diretórios locais existem
            $this->ensureLocalDirectories();
            
            // Sincronizar WXML (apenas cópia)
            if ($this->isWindowsAccessible($this->windowsWxmlDir)) {
                $this->logger->log('sync', 'info', "Copiando WXML do Windows (APENAS CÓPIA)...");
                $wxmlSynced = $this->copyDirectory($this->windowsWxmlDir, $this->localWxmlDir, '*.{WXML,wxml,xml}', 'wxml');
                $synced += $wxmlSynced;
                $this->logger->log('sync', 'info', "WXML copiados: {$wxmlSynced} (originais preservados no Windows)");
            } else {
                $warnings++;
                $this->logger->log('sync', 'warning', "Windows offline ou diretório não acessível: {$this->windowsWxmlDir}");
                echo "⚠️  WXML: Windows offline ou não acessível\n";
            }
            
            // Sincronizar PDF (apenas cópia)
            if ($this->isWindowsAccessible($this->windowsPdfDir)) {
                $this->logger->log('sync', 'info', "Copiando PDF do Windows (APENAS CÓPIA)...");
                $pdfSynced = $this->copyDirectory($this->windowsPdfDir, $this->localPdfDir, '*.{PDF,pdf}', 'pdf');
                $synced += $pdfSynced;
                $this->logger->log('sync', 'info', "PDFs copiados: {$pdfSynced} (originais preservados no Windows)");
            } else {
                $warnings++;
                $this->logger->log('sync', 'warning', "Windows offline ou diretório não acessível: {$this->windowsPdfDir}");
                echo "⚠️  PDF: Windows offline ou não acessível\n";
            }
            
            // Salvar cache de arquivos copiados
            $this->saveCopiedFilesCache();
            
            // Verificar arquivos locais (para informação)
            $localWxmlCount = $this->countLocalFiles($this->localWxmlDir, '*.{WXML,wxml,xml}');
            $localPdfCount = $this->countLocalFiles($this->localPdfDir, '*.{PDF,pdf}');
            
            $message = "Sincronização concluída. " . 
                      "Arquivos copiados: {$synced}, " .
                      "Warnings: {$warnings}, " .
                      "Arquivos locais (WXML: {$localWxmlCount}, PDF: {$localPdfCount}) - ORIGINAIS PRESERVADOS NO WINDOWS";
            
            $this->logger->log('sync', ($warnings == 0 && $synced > 0) ? 'success' : 'info', $message);
            
            return [
                'synced' => $synced,
                'warnings' => $warnings,
                'local_wxml' => $localWxmlCount,
                'local_pdf' => $localPdfCount
            ];
            
        } catch (Exception $e) {
            $this->logger->log('sync', 'error', "Erro na sincronização: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function ensureLocalDirectories() {
        $dirs = [
            $this->localWxmlDir,
            $this->localPdfDir
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                if (mkdir($dir, 0755, true)) {
                    $this->logger->log('sync', 'info', "Diretório local criado: {$dir}");
                } else {
                    $this->logger->log('sync', 'error', "Falha ao criar diretório local: {$dir}");
                }
            }
        }
    }
    
    private function loadCopiedFilesCache() {
        if (file_exists($this->cacheFile)) {
            $data = file_get_contents($this->cacheFile);
            if ($data) {
                $this->copiedFilesCache = json_decode($data, true) ?: [];
            }
        }
    }
    
    private function saveCopiedFilesCache() {
        // Manter apenas os últimos 1000 registros para não crescer indefinidamente
        if (count($this->copiedFilesCache) > 1000) {
            $this->copiedFilesCache = array_slice($this->copiedFilesCache, -1000, 1000, true);
        }
        
        file_put_contents($this->cacheFile, json_encode($this->copiedFilesCache));
    }
    
    private function isWindowsAccessible($dir) {
        // Verifica se o diretório está montado
        if (!is_dir($dir)) {
            return false;
        }
        
        // Tenta verificar se está acessível
        try {
            // Usar file_exists com @ para suprimir warnings
            if (@file_exists($dir)) {
                // Tenta listar um item do diretório
                $files = @scandir($dir);
                return $files !== false;
            }
        } catch (Exception $e) {
            // Ignora exceções, retorna false
        }
        
        return false;
    }
    
    private function copyDirectory($sourceDir, $destDir, $pattern, $type) {
        $copied = 0;
        
        try {
            // Buscar arquivos no Windows
            $files = glob($sourceDir . '/' . $pattern, GLOB_BRACE);
            $totalFiles = count($files);
            
            $this->logger->log('sync', 'info', "Encontrados {$totalFiles} arquivos {$type} no Windows");
            
            foreach ($files as $sourceFile) {
                $filename = basename($sourceFile);
                $destFile = $destDir . '/' . $filename;
                
                // Gerar chave única para cache (arquivo + tamanho + data modificação)
                $fileKey = $filename . '_' . filesize($sourceFile) . '_' . filemtime($sourceFile);
                
                // Verificar se já foi copiado anteriormente (usando cache)
                if (isset($this->copiedFilesCache[$fileKey])) {
                    $this->logger->log('sync', 'debug', "Arquivo já copiado anteriormente: {$filename}");
                    continue;
                }
                
                // Verificar se já existe localmente (com mesmo tamanho)
                if (file_exists($destFile)) {
                    $sourceSize = filesize($sourceFile);
                    $destSize = filesize($destFile);
                    
                    if ($sourceSize === $destSize) {
                        // Mesmo tamanho, considerar como já copiado
                        $this->copiedFilesCache[$fileKey] = time();
                        $this->logger->log('sync', 'debug', "Arquivo já existe localmente com mesmo tamanho: {$filename}");
                        continue;
                    }
                }
                
                // Verificar tamanho do arquivo
                $fileSize = filesize($sourceFile);
                if ($fileSize === 0) {
                    $this->logger->log('sync', 'warning', "Arquivo vazio ignorado: {$filename}");
                    continue;
                }
                
                // Copiar arquivo (APENAS CÓPIA - original preservado)
                if (copy($sourceFile, $destFile)) {
                    // Verificar se a cópia foi bem-sucedida
                    if (filesize($destFile) === $fileSize) {
                        $copied++;
                        
                        // Registrar no cache
                        $this->copiedFilesCache[$fileKey] = time();
                        
                        $this->logger->log('sync', 'info', "{$type} copiado: {$filename} ({$fileSize} bytes) - ORIGINAL PRESERVADO NO WINDOWS");
                    } else {
                        $this->logger->log('sync', 'error', "Cópia incompleta: {$filename} (original: {$fileSize}, cópia: " . filesize($destFile) . ")");
                        // Remove a cópia incompleta
                        @unlink($destFile);
                    }
                } else {
                    $error = error_get_last();
                    $this->logger->log('sync', 'error', "Falha ao copiar: {$filename} - " . ($error['message'] ?? 'Erro desconhecido'));
                }
            }
            
        } catch (Exception $e) {
            $this->logger->log('sync', 'error', "Erro ao copiar diretório {$type}: " . $e->getMessage());
            // Continua com outros arquivos/diretórios
        }
        
        return $copied;
    }
    
    private function countLocalFiles($dir, $pattern) {
        if (!is_dir($dir)) {
            return 0;
        }
        
        $files = glob($dir . '/' . $pattern, GLOB_BRACE);
        return count($files);
    }
    
    public function showStatus() {
        echo "=== Status da Sincronização ===\n\n";
        
        echo "POLÍTICA: APENAS CÓPIA - arquivos originais NÃO são removidos do Windows\n\n";
        
        echo "Diretórios Windows (APENAS LEITURA):\n";
        echo "  WXML: {$this->windowsWxmlDir}\n";
        echo "    Acessível: " . ($this->isWindowsAccessible($this->windowsWxmlDir) ? '✅ SIM' : '❌ NÃO') . "\n";
        
        echo "  PDF: {$this->windowsPdfDir}\n";
        echo "    Acessível: " . ($this->isWindowsAccessible($this->windowsPdfDir) ? '✅ SIM' : '❌ NÃO') . "\n";
        
        echo "\nDiretórios Locais (cópias):\n";
        echo "  WXML: {$this->localWxmlDir}\n";
        $wxmlCount = $this->countLocalFiles($this->localWxmlDir, '*.{WXML,wxml,xml}');
        echo "    Arquivos: {$wxmlCount}\n";
        
        echo "  PDF: {$this->localPdfDir}\n";
        $pdfCount = $this->countLocalFiles($this->localPdfDir, '*.{PDF,pdf}');
        echo "    Arquivos: {$pdfCount}\n";
        
        echo "\nCache de arquivos já copiados: " . count($this->copiedFilesCache) . " registros\n";
        
        // Mostrar últimos 5 arquivos copiados
        if (!empty($this->copiedFilesCache)) {
            echo "Últimos arquivos copiados:\n";
            $recent = array_slice($this->copiedFilesCache, -5, 5, true);
            foreach ($recent as $fileKey => $timestamp) {
                $parts = explode('_', $fileKey, 3);
                $filename = $parts[0];
                $size = isset($parts[1]) ? $this->formatBytes($parts[1]) : 'N/A';
                $date = isset($parts[2]) ? date('d/m/Y H:i', $parts[2]) : 'N/A';
                echo "  - {$filename} ({$size}, mod: {$date}, copiado: " . date('d/m/Y H:i', $timestamp) . ")\n";
            }
        }
    }
    
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    public function clearCache() {
        $this->copiedFilesCache = [];
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
        $this->logger->log('sync', 'info', "Cache de arquivos copiados limpo");
        return true;
    }
    
    public function listWindowsFiles() {
        echo "=== Lista de Arquivos no Windows ===\n\n";
        
        echo "Diretório WXML: {$this->windowsWxmlDir}\n";
        if ($this->isWindowsAccessible($this->windowsWxmlDir)) {
            $files = glob($this->windowsWxmlDir . '/*.{WXML,wxml,xml}', GLOB_BRACE);
            echo "  Total: " . count($files) . " arquivos\n";
            
            if (count($files) > 0) {
                echo "  Últimos 10 arquivos:\n";
                foreach (array_slice($files, -10, 10) as $file) {
                    $size = filesize($file);
                    $date = date('d/m/Y H:i', filemtime($file));
                    echo "  - " . basename($file) . " (" . $this->formatBytes($size) . ", {$date})\n";
                }
            }
        } else {
            echo "  ❌ Não acessível\n";
        }
        
        echo "\nDiretório PDF: {$this->windowsPdfDir}\n";
        if ($this->isWindowsAccessible($this->windowsPdfDir)) {
            $files = glob($this->windowsPdfDir . '/*.{PDF,pdf}', GLOB_BRACE);
            echo "  Total: " . count($files) . " arquivos\n";
            
            if (count($files) > 0) {
                echo "  Últimos 10 arquivos:\n";
                foreach (array_slice($files, -10, 10) as $file) {
                    $size = filesize($file);
                    $date = date('d/m/Y H:i', filemtime($file));
                    echo "  - " . basename($file) . " (" . $this->formatBytes($size) . ", {$date})\n";
                }
            }
        } else {
            echo "  ❌ Não acessível\n";
        }
    }
}

// Execução via CLI
if (php_sapi_name() === 'cli') {
    $startTime = microtime(true);
    
    try {
        $syncer = new FileSyncCLI();
        
        // Verificar argumentos
        if (isset($argv[1])) {
            switch ($argv[1]) {
                case 'status':
                    $syncer->showStatus();
                    exit(0);
                    
                case 'test':
                    echo "=== Teste de Acesso ===\n";
                    echo "POLÍTICA: APENAS CÓPIA - arquivos NÃO são removidos\n\n";
                    echo "WXML dir: " . ($syncer->isWindowsAccessible('/mnt/wincardio/laudos/Enviados') ? '✅ Acessível' : '❌ Não acessível') . "\n";
                    echo "PDF dir: " . ($syncer->isWindowsAccessible('/mnt/wincardio/laudos/ECG') ? '✅ Acessível' : '❌ Não acessível') . "\n";
                    exit(0);
                    
                case 'list':
                    $syncer->listWindowsFiles();
                    exit(0);
                    
                case 'clear-cache':
                    echo "=== Limpar Cache ===\n";
                    if ($syncer->clearCache()) {
                        echo "✅ Cache limpo com sucesso\n";
                    } else {
                        echo "❌ Erro ao limpar cache\n";
                    }
                    exit(0);
                    
                case 'help':
                    echo "=== Ajuda - Sincronização de Arquivos ===\n\n";
                    echo "POLÍTICA: APENAS CÓPIA - arquivos originais NÃO são removidos do Windows\n\n";
                    echo "Uso:\n";
                    echo "  php sync_files.php          - Executa sincronização (apenas cópia)\n";
                    echo "  php sync_files.php status   - Mostra status\n";
                    echo "  php sync_files.php test     - Testa acesso\n";
                    echo "  php sync_files.php list     - Lista arquivos no Windows\n";
                    echo "  php sync_files.php clear-cache - Limpa cache de arquivos copiados\n";
                    echo "  php sync_files.php help     - Mostra esta ajuda\n";
                    echo "\nNota: Os arquivos no Windows são APENAS COPIADOS, nunca removidos ou movidos.\n";
                    exit(0);
            }
        }
        
        echo "=== Sincronização de Arquivos ===\n";
        echo "POLÍTICA: APENAS CÓPIA - arquivos originais NÃO são removidos do Windows\n\n";
        echo "Iniciando em: " . date('Y-m-d H:i:s') . "\n\n";
        
        $result = $syncer->syncFiles();
        
        $processingTime = round(microtime(true) - $startTime, 4);
        
        echo "\n=== Sincronização Concluída ===\n";
        echo "Arquivos copiados: " . $result['synced'] . "\n";
        echo "Warnings: " . $result['warnings'] . "\n";
        echo "Arquivos locais WXML: " . $result['local_wxml'] . "\n";
        echo "Arquivos locais PDF: " . $result['local_pdf'] . "\n";
        echo "Tempo de processamento: {$processingTime}s\n";
        echo "Concluído em: " . date('Y-m-d H:i:s') . "\n";
        echo "\n✅ ORIGINAIS PRESERVADOS NO WINDOWS\n";
        
        // Código de saída baseado no resultado
        if ($result['warnings'] == 0 || $result['synced'] > 0) {
            exit(0); // Sucesso
        } else {
            exit(1); // Aviso (Windows offline, mas sem erros)
        }
        
    } catch (Exception $e) {
        echo "ERRO CRÍTICO: " . $e->getMessage() . "\n";
        echo "Trace: " . $e->getTraceAsString() . "\n";
        exit(2); // Erro crítico
    }
}
