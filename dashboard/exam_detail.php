<?php
session_start();
require_once '../config/database.php';
require_once '../includes/Database.class.php';
require_once '../includes/Auth.class.php';
require_once '../includes/Exam.class.php';
require_once '../includes/Patient.class.php';
require_once '../includes/Utils.class.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

$examId = $_GET['id'] ?? 0;
if (!$examId) {
    header('Location: exams.php');
    exit();
}

$examObj = new Exam();
$exam = $examObj->getById($examId);

if (!$exam) {
    header('Location: exams.php');
    exit();
}

$patientObj = new Patient();
$patient = $patientObj->getById($exam['patient_id']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes do Exame - ECG Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .card-laudo {
            height: 100%;
            min-height: 450px;
        }
        .card-laudo-content {
            max-height: 400px;
            overflow-y: auto;
        }
        .card-laudo-content .alert {
            font-size: 0.9rem;
            line-height: 1.4;
        }
        .card-laudo-content h6 {
            font-size: 0.95rem;
            font-weight: 600;
        }
        @media (max-width: 768px) {
            .card-laudo {
                min-height: auto;
            }
            .card-laudo-content {
                max-height: 300px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <?php include '../includes/sidebar.php'; ?>
            <main class="col-md-9 col-lg-10 px-md-4">
            <?php else: ?>
            <main class="col-12 px-md-4">
            <?php endif; ?>
                <!-- Cabeçalho -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-clipboard-check"></i> Detalhes do Exame
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <a href="exam_edit.php?id=<?php echo $examId; ?>" class="btn btn-primary me-2">
                            <i class="bi bi-pencil"></i> Editar
                        </a>
                        <?php endif; ?>
                        <a href="<?php echo $_SESSION['role'] === 'admin' ? 'exams.php' : '../index.php'; ?>" 
                           class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Voltar
                        </a>
                    </div>
                </div>

                <!-- Informações do Exame -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span>
                                    <i class="bi bi-clipboard-data"></i> Dados do Exame
                                </span>
                                <span class="badge bg-<?php echo $exam['pdf_processed'] ? 'success' : 'warning'; ?>">
                                    <?php echo $exam['pdf_processed'] ? 'Com Laudo' : 'Pendente'; ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5>Exame #<?php echo $exam['exam_number']; ?></h5>
                                        <table class="table table-sm">
                                            <tr>
                                                <th width="40%">Data:</th>
                                                <td><?php echo Utils::formatDate($exam['exam_date']); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Hora:</th>
                                                <td><?php echo $exam['exam_time']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Status:</th>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        switch ($exam['status']) {
                                                            case 'finalizado': echo 'success'; break;
                                                            case 'realizado': echo 'primary'; break;
                                                            case 'processando': echo 'warning'; break;
                                                            case 'cancelado': echo 'danger'; break;
                                                            default: echo 'secondary';
                                                        }
                                                    ?>">
                                                        <?php echo ucfirst($exam['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Prioridade:</th>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        switch ($exam['priority']) {
                                                            case 'urgente': echo 'danger'; break;
                                                            case 'emergencia': echo 'danger'; break;
                                                            default: echo 'secondary';
                                                        }
                                                    ?>">
                                                        <?php echo ucfirst($exam['priority']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <h6 class="mt-4">Parâmetros Clínicos</h6>
                                        <table class="table table-sm">
                                            <?php if ($exam['heart_rate']): ?>
                                            <tr>
                                                <th width="40%">Frequência Cardíaca:</th>
                                                <td>
                                                    <span class="badge bg-danger"><?php echo $exam['heart_rate']; ?> bpm</span>
                                                    <?php if ($patient && $patient['birth_date']): ?>
                                                    <br><small class="text-muted">
                                                        Zona: <?php echo Utils::calculateHeartRateZone($exam['heart_rate'], Utils::getAgeFromBirthDate($patient['birth_date'])); ?>
                                                    </small>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                            
                                            <?php if ($exam['blood_pressure']): ?>
                                            <tr>
                                                <th>Pressão Arterial:</th>
                                                <td><span class="badge bg-info"><?php echo $exam['blood_pressure']; ?></span></td>
                                            </tr>
                                            <?php endif; ?>
                                            
                                            <?php if ($exam['weight']): ?>
                                            <tr>
                                                <th>Peso:</th>
                                                <td><?php echo $exam['weight']; ?> kg</td>
                                            </tr>
                                            <?php endif; ?>
                                            
                                            <?php if ($exam['height']): ?>
                                            <tr>
                                                <th>Altura:</th>
                                                <td><?php echo $exam['height']; ?> m</td>
                                            </tr>
                                            <?php endif; ?>
                                        </table>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h6>Médicos</h6>
                                        <table class="table table-sm">
                                            <?php if ($exam['resp_doctor']): ?>
                                            <tr>
                                                <th width="40%">Responsável:</th>
                                                <td>
                                                    <?php echo $exam['resp_doctor']; ?>
                                                    <?php if ($exam['resp_crm']): ?>
                                                    <br><small class="text-muted">CRM: <?php echo $exam['resp_crm']; ?></small>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                            
                                            <?php if ($exam['req_doctor']): ?>
                                            <tr>
                                                <th>Solicitante:</th>
                                                <td>
                                                    <?php echo $exam['req_doctor']; ?>
                                                    <?php if ($exam['req_crm']): ?>
                                                    <br><small class="text-muted">CRM: <?php echo $exam['req_crm']; ?></small>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </table>
                                        
                                        <?php if ($exam['observations']): ?>
                                        <h6 class="mt-4">Observações</h6>
                                        <div class="alert alert-light">
                                            <?php echo nl2br(htmlspecialchars($exam['observations'])); ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($exam['diagnosis']): ?>
                                        <h6 class="mt-4">Diagnóstico</h6>
                                        <div class="alert alert-info">
                                            <?php echo nl2br(htmlspecialchars($exam['diagnosis'])); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <!-- Informações do Paciente -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-person"></i> Paciente
                            </div>
                            <div class="card-body">
                                <h5><?php echo htmlspecialchars($patient['full_name']); ?></h5>
                                <p class="text-muted">
                                    <?php echo $patient['gender']; ?> | 
                                    Idade: <?php echo Utils::getAgeFromBirthDate($patient['birth_date']); ?> anos
                                </p>
                                
                                <table class="table table-sm">
                                    <tr>
                                        <th>Nascimento:</th>
                                        <td><?php echo Utils::formatDate($patient['birth_date']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>CPF:</th>
                                        <td><?php echo Utils::formatCPF($patient['cpf']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Registro:</th>
                                        <td><code><?php echo $patient['clinical_record']; ?></code></td>
                                    </tr>
                                </table>
                                
                                <a href="patient_detail.php?id=<?php echo $patient['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary w-100">
                                    <i class="bi bi-person-lines-fill"></i> Ver Paciente
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Seção de Laudos -->
                <div class="row">
                    <!-- Laudo PDF (sempre visível) -->
                    <div class="col-md-4 mb-4">
                        <div class="card card-laudo">
                            <div class="card-header">
                                <i class="bi bi-file-pdf"></i> Laudo PDF
                            </div>
                            <div class="card-body text-center d-flex flex-column">
                                <?php if ($exam['stored_filename']): ?>
                                <div class="mb-3">
                                    <i class="bi bi-file-pdf display-1 text-danger"></i>
                                </div>
                                
                                <p>
                                    <strong>Laudo disponível</strong><br>
                                    <small class="text-muted">
                                        Gerado em: <?php echo Utils::formatDate($exam['report_date']); ?>
                                    </small>
                                </p>
                                
                                <div class="d-grid gap-2 mb-3">
                                    <a href="../api/pdf_viewer.php?exam_id=<?php echo $examId; ?>" 
                                       target="_blank" class="btn btn-danger">
                                        <i class="bi bi-file-pdf"></i> Visualizar PDF
                                    </a>
                                    <a href="../api/pdf_viewer.php?exam_id=<?php echo $examId; ?>&download=1" 
                                       class="btn btn-outline-danger">
                                        <i class="bi bi-download"></i> Baixar PDF
                                    </a>
                                </div>
                                
                                <?php if ($exam['findings']): ?>
                                <div class="mt-auto text-start">
                                    <h6><i class="bi bi-search"></i> Achados Principais</h6>
                                    <div class="alert alert-light small">
                                        <?php echo nl2br(htmlspecialchars(substr($exam['findings'], 0, 150) . '...')); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php else: ?>
                                <div class="mb-3 mt-auto">
                                    <i class="bi bi-file-earmark-x display-1 text-warning"></i>
                                </div>
                                
                                <p class="text-warning mt-auto">
                                    <strong>Laudo pendente</strong><br>
                                    <small>Aguardando processamento do PDF</small>
                                </p>
                                
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                <div class="mt-auto">
                                    <button class="btn btn-warning w-100" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                        <i class="bi bi-upload"></i> Enviar Laudo
                                    </button>
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Conteúdo do Laudo (apenas se existir) -->
                    <?php if ($exam['stored_filename'] && ($exam['findings'] || $exam['conclusion'] || $exam['recommendations'])): ?>
                    <div class="col-md-8 mb-4">
                        <div class="card card-laudo">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-journal-text"></i> Conteúdo do Laudo</span>
                                <span class="badge bg-info">
                                    <i class="bi bi-check-circle"></i> Laudo Processado
                                </span>
                            </div>
                            <div class="card-body card-laudo-content">
                                <?php if ($exam['findings']): ?>
                                <h6><i class="bi bi-search text-primary"></i> Achados</h6>
                                <div class="alert alert-light mb-3 p-3">
                                    <?php echo nl2br(htmlspecialchars($exam['findings'])); ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($exam['conclusion']): ?>
                                <h6><i class="bi bi-clipboard-check text-success"></i> Conclusão</h6>
                                <div class="alert alert-info mb-3 p-3">
                                    <?php echo nl2br(htmlspecialchars($exam['conclusion'])); ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($exam['recommendations']): ?>
                                <h6><i class="bi bi-lightbulb text-warning"></i> Recomendações</h6>
                                <div class="alert alert-success p-3">
                                    <?php echo nl2br(htmlspecialchars($exam['recommendations'])); ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!$exam['findings'] && !$exam['conclusion'] && !$exam['recommendations']): ?>
                                <div class="text-center text-muted py-5">
                                    <i class="bi bi-journal-x display-4"></i>
                                    <p class="mt-3">Conteúdo do laudo não disponível</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- Espaço vazio quando não há conteúdo do laudo -->
                    <div class="col-md-8 mb-4">
                        <div class="card card-laudo">
                            <div class="card-header">
                                <i class="bi bi-journal-text"></i> Conteúdo do Laudo
                            </div>
                            <div class="card-body d-flex align-items-center justify-content-center">
                                <div class="text-center text-muted">
                                    <i class="bi bi-journal-x display-4"></i>
                                    <p class="mt-3">
                                        <?php echo $exam['stored_filename'] ? 
                                            'Conteúdo do laudo não disponível' : 
                                            'Laudo pendente de processamento'; ?>
                                    </p>
                                    <?php if (!$exam['stored_filename'] && $_SESSION['role'] === 'admin'): ?>
                                    <button class="btn btn-sm btn-outline-warning mt-2" 
                                            data-bs-toggle="modal" data-bs-target="#uploadModal">
                                        <i class="bi bi-upload"></i> Enviar Laudo
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal para Upload de Laudo (apenas admin) -->
    <?php if ($_SESSION['role'] === 'admin' && !$exam['stored_filename']): ?>
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Enviar Laudo PDF</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadForm" enctype="multipart/form-data">
                        <input type="hidden" name="exam_id" value="<?php echo $examId; ?>">
                        <div class="mb-3">
                            <label for="pdfFile" class="form-label">Selecione o arquivo PDF</label>
                            <input type="file" class="form-control" id="pdfFile" name="pdf_file" 
                                   accept=".pdf" required>
                            <div class="form-text">
                                Tamanho máximo: 10MB. Apenas arquivos PDF são permitidos.
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="submitUpload()">Enviar</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    <?php if ($_SESSION['role'] === 'admin' && !$exam['stored_filename']): ?>
    function submitUpload() {
        const formData = new FormData(document.getElementById('uploadForm'));
        
        fetch('../api/sync.php?action=upload_pdf', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Laudo enviado com sucesso!');
                location.reload();
            } else {
                alert('Erro: ' + data.message);
            }
        })
        .catch(error => {
            alert('Erro ao enviar arquivo: ' + error.message);
        });
    }
    <?php endif; ?>
    </script>
</body>
</html>