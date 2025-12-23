<?php
session_start();
require_once '../config/database.php';
require_once '../includes/Database.class.php';
require_once '../includes/Auth.class.php';
require_once '../includes/Utils.class.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Ações
$action = $_GET['action'] ?? '';
$userId = $_GET['id'] ?? 0;
$errors = [];
$success = '';

if ($action === 'toggle' && $userId) {
    $stmt = $db->prepare("UPDATE users SET active = NOT active WHERE id = ?");
    $stmt->bind_param("i", $userId);
    if ($stmt->execute()) {
        $success = 'Status do usuário alterado com sucesso!';
    } else {
        $errors[] = 'Erro ao alterar status do usuário';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction === 'create') {
        $username = Utils::sanitizeInput($_POST['username']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];
        $fullName = Utils::sanitizeInput($_POST['full_name']);
        $email = Utils::sanitizeInput($_POST['email']);
        $role = $_POST['role'];
        $crm = Utils::sanitizeInput($_POST['crm'] ?? '');
        
        // Validações
        if (empty($username) || empty($password) || empty($fullName)) {
            $errors[] = 'Campos obrigatórios não preenchidos';
        }
        
        if ($password !== $confirmPassword) {
            $errors[] = 'As senhas não coincidem';
        }
        
        if (strlen($password) < 6) {
            $errors[] = 'A senha deve ter pelo menos 6 caracteres';
        }
        
        if (empty($errors)) {
            // Verificar se usuário já existe
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = 'Nome de usuário já existe';
            } else {
                // Criar usuário
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("
                    INSERT INTO users (username, password, full_name, email, role, crm, active)
                    VALUES (?, ?, ?, ?, ?, ?, TRUE)
                ");
                $stmt->bind_param("ssssss", $username, $hashedPassword, $fullName, $email, $role, $crm);
                
                if ($stmt->execute()) {
                    $success = 'Usuário criado com sucesso!';
                } else {
                    $errors[] = 'Erro ao criar usuário';
                }
            }
        }
    }
}

// Buscar usuários
$users = $db->query("SELECT * FROM users ORDER BY role, username");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - ECG Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-person-gear"></i> Gerenciar Usuários
                    </h1>
                </div>

                <!-- Mensagens -->
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Formulário de Novo Usuário -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-person-plus"></i> Novo Usuário
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="create">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Nome de Usuário *</label>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Senha *</label>
                                        <input type="password" class="form-control" id="password" name="password" 
                                               required minlength="6">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirmar Senha *</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                               required minlength="6">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label">Nome Completo *</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" 
                                               required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email">
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="role" class="form-label">Perfil *</label>
                                            <select class="form-control" id="role" name="role" required>
                                                <option value="admin">Administrador</option>
                                                <option value="medico">Médico</option>
                                                <option value="enfermagem">Enfermagem</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="crm" class="form-label">CRM (se médico)</label>
                                            <input type="text" class="form-control" id="crm" name="crm">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Criar Usuário
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Lista de Usuários -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-people"></i> Usuários do Sistema
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Usuário</th>
                                        <th>Nome Completo</th>
                                        <th>Email</th>
                                        <th>Perfil</th>
                                        <th>CRM</th>
                                        <th>Status</th>
                                        <th>Último Login</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($user = $users->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td><?php echo $user['email'] ?: '-'; ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                switch($user['role']) {
                                                    case 'admin': echo 'danger'; break;
                                                    case 'medico': echo 'primary'; break;
                                                    case 'enfermagem': echo 'success'; break;
                                                    default: echo 'secondary';
                                                }
                                            ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $user['crm'] ?: '-'; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['active'] ? 'success' : 'danger'; ?>">
                                                <?php echo $user['active'] ? 'Ativo' : 'Inativo'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $user['last_login'] ? Utils::formatDateTime($user['last_login']) : 'Nunca'; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button onclick="toggleUser(<?php echo $user['id']; ?>)" 
                                                        class="btn btn-outline-<?php echo $user['active'] ? 'warning' : 'success'; ?>"
                                                        title="<?php echo $user['active'] ? 'Desativar' : 'Ativar'; ?>">
                                                    <i class="bi bi-power"></i>
                                                </button>
                                                <button onclick="resetPassword(<?php echo $user['id']; ?>)" 
                                                        class="btn btn-outline-info" title="Redefinir Senha">
                                                    <i class="bi bi-key"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function toggleUser(userId) {
        if (confirm('Tem certeza que deseja alterar o status deste usuário?')) {
            window.location.href = 'users.php?action=toggle&id=' + userId;
        }
    }
    
    function resetPassword(userId) {
        const newPassword = prompt('Digite a nova senha para o usuário (mínimo 6 caracteres):');
        
        if (newPassword && newPassword.length >= 6) {
            fetch('../api/users.php?action=reset_password', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    user_id: userId,
                    new_password: newPassword
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Senha redefinida com sucesso!');
                } else {
                    alert('Erro: ' + data.message);
                }
            });
        } else if (newPassword !== null) {
            alert('A senha deve ter pelo menos 6 caracteres');
        }
    }
    </script>
</body>
</html>