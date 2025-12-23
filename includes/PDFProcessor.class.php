<?php

class PDFProcessor {
    private $config;
    private $basePath;
    private $logger;
    
    public function __construct($logger = null) {
        // Definir o caminho base
        $this->basePath = dirname(__DIR__);
        $this->logger = $logger;
        
        // Carregar configuração
        $configFile = $this->basePath . '/config/config.php';
        if (file_exists($configFile)) {
            $this->config = require $configFile;
            if ($this->logger) {
                $this->logger->log('pdf', 'info', "Configuração carregada de: " . $configFile);
            }
        } else {
            $errorMsg = "Arquivo de configuração não encontrado: " . $configFile;
            if ($this->logger) {
                $this->logger->log('pdf', 'error', $errorMsg);
            }
            throw new Exception($errorMsg);
        }
    }
    
    /**
     * Parse do nome do arquivo PDF para extrair informações
     * Suporta formato: MMD#NOME##DDMMYYYYHHMM#MEDICO#E.PDF
     */
    public function parseFilename($filename) {
        try {
            // Remover extensão .pdf
            $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
            
            // FORMATO WinCardio: MMD#NOME##DDMMYYYYHHMM#MEDICO#E
            if (preg_match('/^MMD#(.+?)##(\d{12})#(.+?)#E$/', $nameWithoutExt, $matches)) {
                $patientName = $matches[1];
                $datetimeStr = $matches[2];
                
                // Extrair data e hora: DDMMYYYYHHMM
                $datePart = substr($datetimeStr, 0, 8); // DDMMYYYY
                $timePart = substr($datetimeStr, 8, 4); // HHMM
                
                // Converter data: DDMMYYYY para YYYY-MM-DD
                $day = substr($datePart, 0, 2);
                $month = substr($datePart, 2, 2);
                $year = substr($datePart, 4, 4);
                
                // Converter hora: HHMM para HH:MM
                $hour = substr($timePart, 0, 2);
                $minute = substr($timePart, 2, 2);
                
                return [
                    'patient_name' => $patientName,
                    'date' => "{$year}-{$month}-{$day}",
                    'time' => "{$hour}:{$minute}",
                    'original_filename' => $filename
                ];
            }
            
            // Se não conseguir parsear, tentar extrair apenas nome
            $patientName = $nameWithoutExt;
            
            // Remover prefixo MMD# se existir
            if (strpos($patientName, 'MMD#') === 0) {
                $patientName = substr($patientName, 4);
            }
            
            // Remover partes após ##
            if (strpos($patientName, '##') !== false) {
                $parts = explode('##', $patientName);
                $patientName = $parts[0];
            }
            
            return [
                'patient_name' => $patientName,
                'date' => date('Y-m-d'),
                'time' => '00:00',
                'original_filename' => $filename
            ];
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log('pdf', 'error', "Erro ao parsear nome do arquivo '{$filename}': " . $e->getMessage());
            }
            return null;
        }
    }
    
    /**
     * Extrair texto do PDF
     */
    public function extractTextFromPDF($filepath) {
        // Verificar se o arquivo existe
        if (!file_exists($filepath)) {
            throw new Exception("Arquivo não encontrado: {$filepath}");
        }
        
        // Verificar se a extensão pdftotext está disponível
        $output = [];
        $returnCode = 0;
        exec("which pdftotext", $output, $returnCode);
        
        if ($returnCode === 0) {
            // Usar pdftotext do sistema
            $tempTextFile = tempnam(sys_get_temp_dir(), 'pdf_text_') . '.txt';
            exec("pdftotext '{$filepath}' '{$tempTextFile}' 2>&1", $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($tempTextFile)) {
                $text = file_get_contents($tempTextFile);
                unlink($tempTextFile);
                return $text;
            }
        }
        
        // Fallback: retornar string vazia
        if ($this->logger) {
            $this->logger->log('pdf', 'warning', "Não foi possível extrair texto do PDF: {$filepath}");
        }
        
        return '';
    }
    
    /**
     * Métodos auxiliares
     */
    public function getConfig() {
        return $this->config;
    }
    
    public function getBasePath() {
        return $this->basePath;
    }
}
