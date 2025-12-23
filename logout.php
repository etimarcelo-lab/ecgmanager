<?php
// Desativa cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Inicia sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Limpa tudo
$_SESSION = array();

// Destrói a sessão
session_destroy();

// Redireciona
header('Location: login.php');
exit();
?>
