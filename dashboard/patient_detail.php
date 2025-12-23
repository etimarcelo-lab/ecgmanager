<?php
session_start();
require_once '../config/database.php';
require_once '../includes/Database.class.php';
require_once '../includes/Auth.class.php';
require_once '../includes/Patient.class.php';
require_once '../includes/Exam.class.php';
require_once '../includes/Utils.class.php';

$auth = new Auth();
$auth->requireRole('admin');

$patientId = $_GET['id'] ?? 0;
if (!$patientId) {
    header('Location: patients.php');
    exit();
}

$patientObj = new Patient();
$examObj = new Exam();
$patient = $patientObj->getById($patientId);

if (!$patient) {
    header('Location: patients.php');
    exit();
}

$exams = $patientObj->getExams($patientId);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes do Paciente - ECG Manager</title>
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
                <!-- Cabeçalho -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-person"></i> Detalhes do Paciente
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="patient_edit.php?id=<?php echo $patientId; ?>" class="btn btn-primary me-2">
                            <i class="bi bi-pencil"></i> Editar
                        </a>
                        <a href="patients.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Voltar
                        </a>
                    </div>
                </div>

                <!-- Informações do Paciente -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-info-circle"></i> Informações Pessoais
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5><?php echo htmlspecialchars($patient['full_name']); ?></h5>
                                        <p class="text-muted">
                                            <i class="bi bi-gender-<?php echo strtolower($patient['gender']); ?>"></i>
                                            <?php echo $patient['gender']; ?> | 
                                            Idade: <?php echo Utils::getAgeFromBirthDate($patient['birth_date']); ?> anos
                                        </p>
                                        
                                        <table class="table table-sm">
                                            <tr>
                                                <th width="40%">Data Nascimento:</th>
                                                <td><?php echo Utils::formatDate($patient['birth_date']); ?></td>
                                            </tr>
                                            <tr>
                                                <th>CPF:</th>
                                                <td><?php echo Utils::formatCPF($patient['cpf']); ?></td>
                                            </tr>
                                            <tr>
                                                <th>RG:</th>
                                                <td><?php echo $patient['rg']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Registro Clínico:</th>
                                                <td><code><?php echo $patient['clinical_record']; ?></code></td>
                                            </tr>
                                        </table>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-telephone"></i> Contato</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <th width="40%">Email:</th>
                                                <td><?php echo $patient['email'] ?: '-'; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Telefone:</th>
                                                <td><?php echo $patient['phone'] ?: '-'; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Endereço:</th>
                                                <td><?php echo $patient['address'] ?: '-'; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Cidade/UF:</th>
                                                <td>
                                                    <?php 
                                                    if ($patient['city'] && $patient['state']) {
                                                        echo $patient['city'] . '/' . $patient['state'];
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>CEP:</th>
                                                <td><?php echo $patient['zip_code'] ?: '-'; ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                
                                <?php if ($patient['notes']): ?>
                                <div class="mt-3">
                                    <h6><i class="bi bi-journal-text"></i> Observações</h6>
                                    <div class="alert alert-light">
                                        <?php echo nl2br(htmlspecialchars($patient['notes'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-clipboard-data"></i> Estatísticas
                            </div>
                            <div class="card-body text-center">
                                <div class="display-4 mb-3"><?php echo $patient['total_exams']; ?></div>
                                <p class="text-muted">Exames Realizados</p>
                                
                                <hr>
                                
                                <div class="row">
                                    <div class="col-6">
                                        <div class="text-primary">
                                            <i class="bi bi-check-circle-fill fs-4"></i>
                                            <div>Com Laudo</div>
                                            <small>
                                                <?php 
                                                // Calcular exames com laudo
                                                $withReport = 0;
                                                $exams->data_seek(0); // Reset pointer
                                                while ($exam = $exams->fetch_assoc()) {
                                                    if (!empty($exam['stored_filename'])) {
                                                        $withReport++;
                                                    }
                                                }
                                                echo $withReport;
                                                ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-warning">
                                            <i class="bi bi-clock-fill fs-4"></i>
                                            <div>Pendentes</div>
                                            <small><?php echo $patient['total_exams'] - $withReport; ?></small>
                                        </div>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="text-muted">
                                    <small>
                                        Cadastrado em: <?php echo Utils::formatDateTime($patient['created_at']); ?><br>
                                        Última atualização: <?php echo Utils::formatDateTime($patient['updated_at']); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Histórico de Exames -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-clipboard-check"></i> Histórico de Exames
                        </h5>
                        <a href="exam_edit.php?action=create&patient_id=<?php echo $patientId; ?>" 
                           class="btn btn-sm btn-success">
                            <i class="bi bi-plus-circle"></i> Novo Exame
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($exams->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Data/Hora</th>
                                        <th>Nº Exame</th>
                                        <th>Médico Resp.</th>
                                        <th>FC</th>
                                        <th>Status</th>
                                        <th>Laudo</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $exams->data_seek(0); // Reset pointer ?>
                                    <?php while ($exam = $exams->fetch_assoc()): 
                                        $hasReport = !empty($exam['stored_filename']);
                                        $statusClass = $hasReport ? 'success' : 'warning';
                                        $statusText = $hasReport ? 'Com Laudo' : 'Pendente';
                                    ?>
                                    <tr>
                                        <td>
                                            <?php echo Utils::formatDate($exam['exam_date']); ?><br>
                                            <small class="text-muted"><?php echo $exam['exam_time']; ?></small>
                                        </td>
                                        <td><code><?php echo $exam['exam_number']; ?></code></td>
                                        <td><?php echo $exam['resp_doctor'] ?: '-'; ?></td>
                                        <td>
                                            <?php if ($exam['heart_rate']): ?>
                                            <span class="badge bg-danger"><?php echo $exam['heart_rate']; ?> bpm</span>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $statusClass; ?>">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($hasReport): ?>
                                            <a href="../api/pdf_viewer.php?exam_id=<?php echo $exam['id']; ?>" 
                                               target="_blank" class="btn btn-sm btn-outline-info">
                                                <i class="bi bi-file-pdf"></i> Ver
                                            </a>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="exam_detail.php?id=<?php echo $exam['id']; ?>" 
                                                   class="btn btn-outline-primary" title="Detalhes">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="exam_edit.php?id=<?php echo $exam['id']; ?>" 
                                                   class="btn btn-outline-secondary" title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-clipboard-x display-1 text-muted"></i>
                            <h4 class="mt-3">Nenhum exame encontrado</h4>
                            <p class="text-muted">Este paciente ainda não realizou exames.</p>
                            <a href="exam_edit.php?action=create&patient_id=<?php echo $patientId; ?>" 
                               class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Criar Primeiro Exame
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>