<?php
/**
 * Teste do processamento WXML
 */

// Definir o diretório raiz do projeto
define('ROOT_PATH', '/var/www/html/ecgmanager/');


echo "=== Teste de Processamento WXML ===\n\n";

// Carregar classes
require_once __DIR__ . '/../includes/Database.class.php';
require_once __DIR__ . '/../includes/SyncLogger.class.php';
require_once __DIR__ . '/../includes/WXMLProcessor.class.php';
require_once __DIR__ . '/../config/config.php';


try {
    echo "1. Inicializando classes...\n";
    $logger = new SyncLogger();
    echo "   ✓ Logger criado\n";
    
    $processor = new WXMLProcessor();
    echo "   ✓ Processor criado\n";
    
    echo "\n2. Verificando diretórios...\n";
    
    $directories = [
        '/var/www/html/ecgmanager/Enviados',
        '/var/www/html/ecgmanager/Enviados/processed',
        '/var/www/html/ecgmanager/uploads/wxml',
        '/mnt/wincardio/laudos/Enviados'
    ];
    
    foreach ($directories as $dir) {
        if (is_dir($dir)) {
            echo "   ✓ {$dir} existe\n";
            
            // Testar escrita
            $testFile = $dir . '/test_' . time() . '.txt';
            if (@file_put_contents($testFile, 'test')) {
                echo "     → Escrita OK\n";
                unlink($testFile);
            } else {
                echo "     → Sem permissão de escrita\n";
            }
        } else {
            echo "   ✗ {$dir} não existe\n";
            
            // Tentar criar diretórios locais
            if (strpos($dir, '/var/www/html/ecgmanager/') === 0) {
                if (@mkdir($dir, 0755, true)) {
                    echo "     → Criado\n";
                }
            }
        }
    }
    
    echo "\n3. Verificando arquivos WXML...\n";
    
    $wxmlDir = '/var/www/html/ecgmanager/Enviados';
    $files = glob($wxmlDir . '/*.{WXML,wxml,xml}', GLOB_BRACE);
    
    echo "   Encontrados " . count($files) . " arquivos\n";
    
    if (count($files) > 0) {
        foreach ($files as $file) {
            $filename = basename($file);
            $size = filesize($file);
            echo "   • {$filename} ({$size} bytes)\n";
        }
    }
    
    echo "\n4. Testando processamento de um arquivo...\n";
    
    if (count($files) > 0) {
        $testFile = $files[0];
        $filename = basename($testFile);
        
        echo "   Processando: {$filename}\n";
        
        // Tentar processar
        try {
            $content = file_get_contents($testFile);
            
            if ($content) {
                echo "   ✓ Arquivo lido (" . strlen($content) . " bytes)\n";
                
                // Tentar parse XML
                $xml = @simplexml_load_string($content);
                
                if ($xml !== false) {
                    echo "   ✓ XML válido\n";
                    
                    // Extrair informações básicas
                    $data = [];
                    
                    if (isset($xml->Paciente)) {
                        $paciente = $xml->Paciente;
                        $data['paciente'] = (string)$paciente->Nome;
                        echo "   ✓ Paciente: " . $data['paciente'] . "\n";
                    }
                    
                    if (isset($xml->NroExame)) {
                        $data['exame'] = (string)$xml->NroExame;
                        echo "   ✓ Número Exame: " . $data['exame'] . "\n";
                    }
                    
                } else {
                    echo "   ✗ XML inválido\n";
                    echo "   Primeiros 200 caracteres:\n";
                    echo substr($content, 0, 200) . "...\n";
                }
            } else {
                echo "   ✗ Arquivo vazio ou não legível\n";
            }
            
        } catch (Exception $e) {
            echo "   ✗ Erro: " . $e->getMessage() . "\n";
        }
    } else {
        echo "   Nenhum arquivo para processar\n";
        
        // Criar arquivo de teste
        $testFile = $wxmlDir . '/test_wxml.WXML';
        $testContent = '<?xml version="1.0"?>
<WinCardio Tipo="exame">
  <Paciente>
    <ID>TEST001</ID>
    <Nome>Paciente Teste</Nome>
    <DataNascimento>15/05/1950</DataNascimento>
    <Sexo>Masculino</Sexo>
    <RegistroClinico>RC999</RegistroClinico>
    <RG>9999999</RG>
    <CPF>99988877766</CPF>
  </Paciente>
  <ID>EXM001</ID>
  <NroExame>ECG_TEST_001</NroExame>
  <Data>10/12/2025</Data>
  <Hora>14:30:00</Hora>
  <Medicos>
    <Responsavel>
      <Nome>Dr. Teste</Nome>
      <CRM>CRM-TEST</CRM>
    </Responsavel>
  </Medicos>
</WinCardio>';
        
        if (file_put_contents($testFile, $testContent)) {
            echo "   ✓ Arquivo de teste criado: {$testFile}\n";
            $files = [$testFile];
        }
    }
    
    echo "\n=== Teste Concluído ===\n";
    
} catch (Exception $e) {
    echo "✗ Erro geral: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
