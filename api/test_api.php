<?php
// test_api.php na raiz do sistema
echo "<h2>Testando API de Conexão</h2>";

// Testar test_connection.php
$url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/api/test_connection.php?log=true';
echo "URL: <code>$url</code><br><br>";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);

// Se usar sessão, passar cookie
session_start();
if (isset($_SESSION['session_id'])) {
    curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID=' . $_SESSION['session_id']);
}

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "HTTP Code: $httpCode<br>";
echo "Resposta: <pre>" . htmlspecialchars($response) . "</pre>";

curl_close($ch);
?>