<?php
session_start();
require_once 'config/database.php';
require_once 'includes/Auth.class.php';

$auth = new Auth();

if ($auth->isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';

// Processar o formulário de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($auth->login($username, $password)) {
        header('Location: index.php');
        exit();
    } else {
        $error = 'Usuário ou senha inválidos';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ECG Manager - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background: #1a6fb4 url('assets/backgrounds/coracao.jpg') center/cover no-repeat;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
        }
        .login-header {
            background: #f8f9fa;
            padding: 30px 20px;
            text-align: center;
            border-bottom: 3px solid #1a6fb4;
        }
        .logo-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 20px;
        }
        .hospital-logo {
            max-width: 280px;
            height: auto;
            margin: 0 auto;
        }
        .hospital-name {
            color: #1a6fb4;
            font-size: 1.4rem;
            margin-top: 15px;
            font-weight: bold;
            text-align: center;
        }
        .system-name {
            color: #666;
            font-size: 1.1rem;
            margin-top: 5px;
            font-weight: normal;
        }
        .form-container {
            padding: 30px;
        }
        .form-control {
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid #ddd;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #1a6fb4;
            box-shadow: 0 0 0 0.25rem rgba(26, 111, 180, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #1a6fb4 0%, #0d4d8a 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #0d4d8a 0%, #1a6fb4 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 0.9rem;
            border-top: 1px solid #eee;
            background: #f8f9fa;
        }
        .alert {
            border-radius: 8px;
            border: none;
        }
        .input-group-text {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-right: none;
        }
        .form-control:focus + .input-group-text {
            border-color: #1a6fb4;
        }
    </style>
</head>
<body>
    <!-- Card de Login -->
    <div class="login-card">
        <div class="login-header">
            <!-- Logo do Hospital -->
            <div class="logo-container">
                <img src="assets/img/logo.png" alt="Hospital Nosso Senhora Aparecida" class="hospital-logo">
            </div>
            <div class="hospital-name">
                Hospital Nosso Senhora Aparecida
                <div class="system-name">Sistema de Gerenciamento ECG</div>
            </div>
        </div>
        
        <div class="form-container">
            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-4">
                    <label for="username" class="form-label fw-semibold">
                        <i class="bi bi-person-circle me-2"></i>Usuário
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-person"></i>
                        </span>
                        <input type="text" class="form-control" id="username" name="username" 
                               required autofocus placeholder="Digite seu usuário">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label fw-semibold">
                        <i class="bi bi-key me-2"></i>Senha
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-lock"></i>
                        </span>
                        <input type="password" class="form-control" id="password" name="password" 
                               required placeholder="Digite sua senha">
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-login text-white w-100">
                    <i class="bi bi-box-arrow-in-right me-2"></i> Entrar no Sistema
                </button>

            </form>
        </div>
        
        <div class="footer">
            <div class="mb-2">
                <small>Desenvolvido por:</small><br>
                <small>Marcelo Kohlbach - Setor de TI do HNSA</small>
            </div>
        </div>
    </div>

    <script>
        // Alternar visibilidade da senha
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });

        // Focar no campo de usuário automaticamente
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });

        // Adicionar validação básica
        document.querySelector('form').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!username || !password) {
                e.preventDefault();
                alert('Por favor, preencha todos os campos obrigatórios.');
                return false;
            }
        });
    </script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
