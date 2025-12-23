<?php

class WXMLProcessor {
    private $db;
    private $uploadPath;
    private $basePath;
    private $config;

    public function __construct() {
        // Definir o caminho base
        $this->basePath = dirname(__DIR__);

        // Carregar configuração
        $configFile = $this->basePath . '/config/config.php';
        if (file_exists($configFile)) {
            $this->config = require $configFile;
        } else {
            throw new Exception("Arquivo de configuração não encontrado: " . $configFile);
        }

        // Configurar banco de dados
        $this->db = Database::getInstance();
        
        // Configurar diretório de upload
        $this->uploadPath = $this->config['upload_path'] . 'wxml/';

        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }

    public function parseWXML($xmlContent) {
        try {
            // Tentar parsear como XML
            $xml = simplexml_load_string($xmlContent);
            if ($xml === false) {
                throw new Exception("XML inválido");
            }
            
            // Retornar objeto XML
            return $xml;
            
        } catch (Exception $e) {
            error_log("Erro ao parsear WXML: " . $e->getMessage());
            return false;
        }
    }

    public function saveWXMLData($xmlData, $filename) {
        try {
            $fullPath = $this->uploadPath . $filename;
            
            // Salvar dados (exemplo: salvar como arquivo)
            if (file_put_contents($fullPath, $xmlData) !== false) {
                // Opcional: salvar no banco de dados
                $this->saveToDatabase($filename, $fullPath);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Erro ao salvar WXML: " . $e->getMessage());
            return false;
        }
    }

    private function saveToDatabase($filename, $filepath) {
        try {
            // Exemplo: salvar referência no banco de dados
            $query = "INSERT INTO wxml_files (filename, filepath, created_at) 
                      VALUES (:filename, :filepath, NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':filename' => $filename,
                ':filepath' => $filepath
            ]);
            
            return $this->db->lastInsertId();
            
        } catch (Exception $e) {
            error_log("Erro ao salvar no banco: " . $e->getMessage());
            return false;
        }
    }

    public function getWXMLData($filename) {
        try {
            $fullPath = $this->uploadPath . $filename;
            
            if (!file_exists($fullPath)) {
                return false;
            }
            
            $content = file_get_contents($fullPath);
            return $this->parseWXML($content);
            
        } catch (Exception $e) {
            error_log("Erro ao ler WXML: " . $e->getMessage());
            return false;
        }
    }

    public function listWXMLFiles() {
        try {
            $files = [];
            
            if (is_dir($this->uploadPath)) {
                $dirFiles = scandir($this->uploadPath);
                
                foreach ($dirFiles as $file) {
                    if ($file !== '.' && $file !== '..') {
                        $files[] = [
                            'filename' => $file,
                            'path' => $this->uploadPath . $file,
                            'size' => filesize($this->uploadPath . $file),
                            'modified' => filemtime($this->uploadPath . $file)
                        ];
                    }
                }
            }
            
            return $files;
            
        } catch (Exception $e) {
            error_log("Erro ao listar arquivos WXML: " . $e->getMessage());
            return [];
        }
    }

    public function getConfigValue($key) {
        return isset($this->config[$key]) ? $this->config[$key] : null;
    }

    public function getUploadPath() {
        return $this->uploadPath;
    }

    public function getBasePath() {
        return $this->basePath;
    }

    public function getDB() {
        return $this->db;
    }
}
