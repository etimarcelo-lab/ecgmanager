<?php
session_start();
require_once '../config/database.php';
require_once '../includes/Database.class.php';
require_once '../includes/Auth.class.php';
require_once '../includes/Patient.class.php';
require_once '../includes/Utils.class.php';

$auth = new Auth();
$auth->requireRole('admin');

$patientObj = new Patient();
$db = Database::getInstance();

$action = $_GET['action'] ?? '';
$patientId = $_GET['id'] ?? 0;
$patient = null;
$errors = [];
$success = false;

if ($patientId) {
    $patient = $patientObj->getById($patientId);
    if (!$patient) {
        header('Location: patients.php');
        exit();
    }
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'patient_id' => $_POST['patient_id'] ?? '',
        'full_name' => Utils::sanitizeInput($_POST['full_name']),
        'birth_date' => $_POST['birth_date'],
        'gender' => $_POST['gender'],
        'clinical_record' => Utils::sanitizeInput($_POST['clinical_record']),
        'rg' => Utils::sanitizeInput($_POST['rg']),
        'cpf' => preg_replace('/[^0-9]/', '', $_POST['cpf']),
        'email' => Utils::sanitizeInput($_POST['email']),
        'phone' => Utils::sanitizeInput($_POST['phone']),
        'address' => Utils::sanitizeInput($_POST['address']),
        'city' => Utils::sanitizeInput($_POST['city']),
        'state' => $_POST['state'],
        'zip_code' => Utils::sanitizeInput($_POST['zip_code']),
        'notes' => Utils::sanitizeInput($_POST['notes'])
    ];

    // Validações
    if (empty($data['full_name'])) {
        $errors[] = 'Nome completo é obrigatório';
    }

    if (!Utils::validateDate($data['birth_date'])) {
        $errors[] = 'Data de nascimento inválida';
    }

    if (!empty($data['email']) && !Utils::validateEmail($data['email'])) {
        $errors[] = 'Email inválido';
    }

    if (!empty($data['cpf']) && strlen($data['cpf']) !== 11) {
        $errors[] = 'CPF deve conter 11 dígitos';
    }

    if (empty($errors)) {
        if ($patientId) {
            // Atualizar
            if ($patientObj->update($patientId, $data)) {
                $success = 'Paciente atualizado com sucesso!';
                $patient = $patientObj->getById($patientId); // Recarregar dados
            } else {
                $errors[] = 'Erro ao atualizar paciente';
            }
        } else {
            // Criar novo
            if ($patientObj->create($data)) {
                $success = 'Paciente criado com sucesso!';
                header('Location: patients.php');
                exit();
            } else {
                $errors[] = 'Erro ao criar paciente';
            }
        }
    }
}

$states = Utils::getBrazilianStates();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $patientId ? 'Editar' : 'Novo'; ?> Paciente - ECG Manager</title>
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
                        <i class="bi bi-person"></i> 
                        <?php echo $patientId ? 'Editar Paciente' : 'Novo Paciente'; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="patients.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Voltar
                        </a>
                    </div>
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

                <!-- Formulário -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-person-fill"></i> Dados do Paciente
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row g-3">
                                <!-- Dados Pessoais -->
                                <div class="col-md-6">
                                    <h5 class="border-bottom pb-2 mb-3">Dados Pessoais</h5>
                                    
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label">Nome Completo *</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" 
                                               value="<?php echo htmlspecialchars($patient['full_name'] ?? ''); ?>" 
                                               required>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="birth_date" class="form-label">Data de Nascimento *</label>
                                            <input type="date" class="form-control" id="birth_date" name="birth_date" 
                                                   value="<?php echo $patient['birth_date'] ?? ''; ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="gender" class="form-label">Sexo *</label>
                                            <select class="form-control" id="gender" name="gender" required>
                                                <option value="">Selecione</option>
                                                <option value="Masculino" <?php echo ($patient['gender'] ?? '') == 'Masculino' ? 'selected' : ''; ?>>Masculino</option>
                                                <option value="Feminino" <?php echo ($patient['gender'] ?? '') == 'Feminino' ? 'selected' : ''; ?>>Feminino</option>
                                                <option value="Outro" <?php echo ($patient['gender'] ?? '') == 'Outro' ? 'selected' : ''; ?>>Outro</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="cpf" class="form-label">CPF</label>
                                            <input type="text" class="form-control" id="cpf" name="cpf" 
                                                   value="<?php echo Utils::formatCPF($patient['cpf'] ?? ''); ?>"
                                                   placeholder="000.000.000-00">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="rg" class="form-label">RG</label>
                                            <input type="text" class="form-control" id="rg" name="rg" 
                                                   value="<?php echo htmlspecialchars($patient['rg'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="clinical_record" class="form-label">Registro Clínico</label>
                                        <input type="text" class="form-control" id="clinical_record" name="clinical_record" 
                                               value="<?php echo htmlspecialchars($patient['clinical_record'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="patient_id" class="form-label">ID do Paciente (Sistema)</label>
                                        <input type="text" class="form-control" id="patient_id" name="patient_id" 
                                               value="<?php echo htmlspecialchars($patient['patient_id'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <!-- Contato e Endereço -->
                                <div class="col-md-6">
                                    <h5 class="border-bottom pb-2 mb-3">Contato e Endereço</h5>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($patient['email'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Telefone</label>
                                        <input type="text" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($patient['phone'] ?? ''); ?>"
                                               placeholder="(00) 00000-0000">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Endereço</label>
                                        <input type="text" class="form-control" id="address" name="address" 
                                               value="<?php echo htmlspecialchars($patient['address'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="city" class="form-label">Cidade</label>
                                            <input type="text" class="form-control" id="city" name="city" 
                                                   value="<?php echo htmlspecialchars($patient['city'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label for="state" class="form-label">Estado</label>
                                            <select class="form-control" id="state" name="state">
                                                <option value="">Selecione</option>
                                                <?php foreach ($states as $uf => $name): ?>
                                                <option value="<?php echo $uf; ?>" 
                                                    <?php echo ($patient['state'] ?? '') == $uf ? 'selected' : ''; ?>>
                                                    <?php echo $uf; ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label for="zip_code" class="form-label">CEP</label>
                                            <input type="text" class="form-control" id="zip_code" name="zip_code" 
                                                   value="<?php echo htmlspecialchars($patient['zip_code'] ?? ''); ?>"
                                                   placeholder="00000-000">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Observações</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($patient['notes'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Salvar
                                </button>
                                <a href="patients.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i> Cancelar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Máscaras para os campos
    document.addEventListener('DOMContentLoaded', function() {
        // Máscara para CPF
        const cpfInput = document.getElementById('cpf');
        if (cpfInput) {
            cpfInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 3) value = value.replace(/^(\d{3})(\d)/, '$1.$2');
                if (value.length > 6) value = value.replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3');
                if (value.length > 9) value = value.replace(/^(\d{3})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4');
                e.target.value = value.substring(0, 14);
            });
        }
        
        // Máscara para telefone
        const phoneInput = document.getElementById('phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 0) value = '(' + value;
                if (value.length > 3) value = value.replace(/^(\d{2})(\d)/, '$1) $2');
                if (value.length > 9) value = value.replace(/(\d{5})(\d)/, '$1-$2');
                e.target.value = value.substring(0, 15);
            });
        }
        
        // Máscara para CEP
        const zipInput = document.getElementById('zip_code');
        if (zipInput) {
            zipInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 5) value = value.replace(/^(\d{5})(\d)/, '$1-$2');
                e.target.value = value.substring(0, 9);
            });
        }
    });
    </script>
</body>
</html>