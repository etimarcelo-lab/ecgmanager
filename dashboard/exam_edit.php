<?php
session_start();
require_once '../config/database.php';
require_once '../includes/Database.class.php';
require_once '../includes/Auth.class.php';
require_once '../includes/Exam.class.php';
require_once '../includes/Patient.class.php';
require_once '../includes/Utils.class.php';

$auth = new Auth();
$auth->requireRole('admin');

$examObj = new Exam();
$patientObj = new Patient();
$db = Database::getInstance();

$action = $_GET['action'] ?? '';
$examId = $_GET['id'] ?? 0;
$patientId = $_GET['patient_id'] ?? 0;
$exam = null;
$errors = [];
$success = false;

if ($examId) {
    $exam = $examObj->getById($examId);
    if (!$exam) {
        header('Location: exams.php');
        exit();
    }
    $patientId = $exam['patient_id'];
}

// Buscar pacientes para dropdown
$patients = $patientObj->getAll(1, 100);

// Buscar médicos
$doctors = $db->query("SELECT id, name, crm FROM doctors WHERE active = TRUE ORDER BY name");

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'exam_number' => Utils::sanitizeInput($_POST['exam_number']),
        'patient_id' => (int)$_POST['patient_id'],
        'exam_date' => $_POST['exam_date'],
        'exam_time' => $_POST['exam_time'],
        'responsible_doctor_id' => !empty($_POST['responsible_doctor_id']) ? (int)$_POST['responsible_doctor_id'] : null,
        'requesting_doctor_id' => !empty($_POST['requesting_doctor_id']) ? (int)$_POST['requesting_doctor_id'] : null,
        'heart_rate' => !empty($_POST['heart_rate']) ? (int)$_POST['heart_rate'] : null,
        'blood_pressure' => Utils::sanitizeInput($_POST['blood_pressure']),
        'weight' => !empty($_POST['weight']) ? (float)$_POST['weight'] : null,
        'height' => !empty($_POST['height']) ? (float)$_POST['height'] : null,
        'observations' => Utils::sanitizeInput($_POST['observations']),
        'diagnosis' => Utils::sanitizeInput($_POST['diagnosis']),
        'status' => $_POST['status'],
        'priority' => $_POST['priority']
    ];

    // Validações
    if (empty($data['exam_number'])) {
        $errors[] = 'Número do exame é obrigatório';
    }

    if (empty($data['patient_id'])) {
        $errors[] = 'Paciente é obrigatório';
    }

    if (!Utils::validateDate($data['exam_date'])) {
        $errors[] = 'Data do exame inválida';
    }

    if (empty($errors)) {
        if ($examId) {
            // Atualizar
            if ($examObj->update($examId, $data)) {
                $success = 'Exame atualizado com sucesso!';
                $exam = $examObj->getById($examId); // Recarregar dados
            } else {
                $errors[] = 'Erro ao atualizar exame: ' . $db->getLastError();
            }
        } else {
            // Criar novo
            if ($examObj->create($data)) {
                $success = 'Exame criado com sucesso!';
                header('Location: exams.php');
                exit();
            } else {
                $errors[] = 'Erro ao criar exame: ' . $db->getLastError();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $examId ? 'Editar' : 'Novo'; ?> Exame - ECG Manager</title>
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
                        <i class="bi bi-clipboard-check"></i> 
                        <?php echo $examId ? 'Editar Exame' : 'Novo Exame'; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="<?php echo $examId ? 'exam_detail.php?id=' . $examId : 'exams.php'; ?>" 
                           class="btn btn-outline-secondary">
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
                        <i class="bi bi-clipboard-data"></i> Dados do Exame
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row g-3">
                                <!-- Informações Básicas -->
                                <div class="col-md-6">
                                    <h5 class="border-bottom pb-2 mb-3">Informações Básicas</h5>
                                    
                                    <div class="mb-3">
                                        <label for="exam_number" class="form-label">Número do Exame *</label>
                                        <input type="text" class="form-control" id="exam_number" name="exam_number" 
                                               value="<?php echo htmlspecialchars($exam['exam_number'] ?? ''); ?>" 
                                               required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="patient_id" class="form-label">Paciente *</label>
                                        <select class="form-control" id="patient_id" name="patient_id" required>
                                            <option value="">Selecione um paciente</option>
                                            <?php while ($p = $patients->fetch_assoc()): ?>
                                            <option value="<?php echo $p['id']; ?>" 
                                                <?php echo ($patientId == $p['id'] || ($exam && $exam['patient_id'] == $p['id'])) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($p['full_name']); ?> 
                                                (Nasc: <?php echo Utils::formatDate($p['birth_date']); ?>)
                                            </option>
                                            <?php endwhile; ?>
                                        </select>
                                        <div class="form-text">
                                            <a href="patient_edit.php?action=create" class="text-decoration-none">
                                                <i class="bi bi-plus-circle"></i> Cadastrar novo paciente
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="exam_date" class="form-label">Data do Exame *</label>
                                            <input type="date" class="form-control" id="exam_date" name="exam_date" 
                                                   value="<?php echo $exam['exam_date'] ?? date('Y-m-d'); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="exam_time" class="form-label">Hora *</label>
                                            <input type="time" class="form-control" id="exam_time" name="exam_time" 
                                                   value="<?php echo $exam['exam_time'] ?? date('H:i'); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="status" class="form-label">Status</label>
                                            <select class="form-control" id="status" name="status">
                                                <option value="realizado" <?php echo ($exam['status'] ?? 'realizado') == 'realizado' ? 'selected' : ''; ?>>Realizado</option>
                                                <option value="processando" <?php echo ($exam['status'] ?? '') == 'processando' ? 'selected' : ''; ?>>Processando</option>
                                                <option value="finalizado" <?php echo ($exam['status'] ?? '') == 'finalizado' ? 'selected' : ''; ?>>Finalizado</option>
                                                <option value="cancelado" <?php echo ($exam['status'] ?? '') == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="priority" class="form-label">Prioridade</label>
                                            <select class="form-control" id="priority" name="priority">
                                                <option value="normal" <?php echo ($exam['priority'] ?? 'normal') == 'normal' ? 'selected' : ''; ?>>Normal</option>
                                                <option value="urgente" <?php echo ($exam['priority'] ?? '') == 'urgente' ? 'selected' : ''; ?>>Urgente</option>
                                                <option value="emergencia" <?php echo ($exam['priority'] ?? '') == 'emergencia' ? 'selected' : ''; ?>>Emergência</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Médicos e Parâmetros -->
                                <div class="col-md-6">
                                    <h5 class="border-bottom pb-2 mb-3">Médicos e Parâmetros</h5>
                                    
                                    <div class="mb-3">
                                        <label for="responsible_doctor_id" class="form-label">Médico Responsável</label>
                                        <select class="form-control" id="responsible_doctor_id" name="responsible_doctor_id">
                                            <option value="">Selecione</option>
                                            <?php while ($doc = $doctors->fetch_assoc()): ?>
                                            <option value="<?php echo $doc['id']; ?>" 
                                                <?php echo ($exam['responsible_doctor_id'] ?? '') == $doc['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($doc['name']); ?> (CRM: <?php echo $doc['crm']; ?>)
                                            </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="requesting_doctor_id" class="form-label">Médico Solicitante</label>
                                        <select class="form-control" id="requesting_doctor_id" name="requesting_doctor_id">
                                            <option value="">Selecione</option>
                                            <?php $doctors->data_seek(0); // Reset pointer ?>
                                            <?php while ($doc = $doctors->fetch_assoc()): ?>
                                            <option value="<?php echo $doc['id']; ?>" 
                                                <?php echo ($exam['requesting_doctor_id'] ?? '') == $doc['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($doc['name']); ?> (CRM: <?php echo $doc['crm']; ?>)
                                            </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="heart_rate" class="form-label">Frequência Cardíaca (bpm)</label>
                                            <input type="number" class="form-control" id="heart_rate" name="heart_rate" 
                                                   min="30" max="300" step="1"
                                                   value="<?php echo $exam['heart_rate'] ?? ''; ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="blood_pressure" class="form-label">Pressão Arterial</label>
                                            <input type="text" class="form-control" id="blood_pressure" name="blood_pressure" 
                                                   value="<?php echo htmlspecialchars($exam['blood_pressure'] ?? ''); ?>"
                                                   placeholder="120/80">
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="weight" class="form-label">Peso (kg)</label>
                                            <input type="number" class="form-control" id="weight" name="weight" 
                                                   min="0" max="300" step="0.1"
                                                   value="<?php echo $exam['weight'] ?? ''; ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="height" class="form-label">Altura (m)</label>
                                            <input type="number" class="form-control" id="height" name="height" 
                                                   min="0.5" max="2.5" step="0.01"
                                                   value="<?php echo $exam['height'] ?? ''; ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Observações -->
                                <div class="col-12">
                                    <h5 class="border-bottom pb-2 mb-3">Observações e Diagnóstico</h5>
                                    
                                    <div class="mb-3">
                                        <label for="observations" class="form-label">Observações</label>
                                        <textarea class="form-control" id="observations" name="observations" rows="3"><?php echo htmlspecialchars($exam['observations'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="diagnosis" class="form-label">Diagnóstico</label>
                                        <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3"><?php echo htmlspecialchars($exam['diagnosis'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Salvar
                                </button>
                                <a href="<?php echo $examId ? 'exam_detail.php?id=' . $examId : 'exams.php'; ?>" 
                                   class="btn btn-outline-secondary">
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
</body>
</html>