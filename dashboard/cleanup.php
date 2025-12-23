<?php
// dashboard/cleanup.php
session_start();
require_once '../config/database.php';
require_once '../includes/Database.class.php';
require_once '../includes/Auth.class.php';
require_once '../includes/Exam.class.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();
$examObj = new Exam();

// Buscar exames pendentes para exibir antes de excluir
$pendingExams = $db->query("
    SELECT e.*, p.full_name as patient_name
    FROM exams e
    LEFT JOIN patients p ON e.patient_id = p.id
    WHERE e.status = 'realizado' AND e.pdf_processed = FALSE
    ORDER BY e.exam_date DESC
    LIMIT 100
");

$totalPending = $db->query("
    SELECT COUNT(*) as total 
    FROM exams 
    WHERE status = 'realizado' AND pdf_processed = FALSE
")->fetch_assoc()['total'];

// Processar exclusão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $query = "DELETE FROM exams WHERE status = 'realizado' AND pdf_processed = FALSE";
    
    if ($db->query($query)) {
        $deletedCount = $db->affected_rows;
        $success = "Foram excluídos {$deletedCount} exames pendentes com sucesso!";
        
        // Limpar variáveis após exclusão
        $pendingExams = null;
        $totalPending = 0;
    } else {
        $error = "Erro ao excluir exames: " . $db->error;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Limpeza de Exames - ECG Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        body {
            padding-top: 70px; /* Compensa a navbar fixa */
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-trash"></i> Limpeza de Exames Pendentes
                    </h1>
                </div>

                <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="alert alert-warning">
                    <h5><i class="bi bi-exclamation-triangle"></i> ATENÇÃO</h5>
                    <p>Esta ação irá excluir permanentemente todos os exames pendentes (sem laudo PDF).</p>
                    <p><strong>Total de exames pendentes: <?php echo $totalPending; ?></strong></p>
                    <p class="mb-0">Esta ação é irreversível. Recomenda-se fazer um backup antes de prosseguir.</p>
                </div>

                <?php if ($totalPending > 0): ?>
                <!-- Lista de exames que serão excluídos -->
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white">
                        <i class="bi bi-list"></i> Exames que Serão Excluídos (últimos 100)
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Paciente</th>
                                        <th>Nº Exame</th>
                                        <th>FC</th>
                                        <th>Criado em</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($pendingExams): while ($exam = $pendingExams->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($exam['exam_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($exam['patient_name']); ?></td>
                                        <td><code><?php echo $exam['exam_number']; ?></code></td>
                                        <td><?php echo $exam['heart_rate'] ? $exam['heart_rate'] . ' bpm' : '-'; ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($exam['created_at'])); ?></td>
                                    </tr>
                                    <?php endwhile; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Formulário de confirmação -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-shield-exclamation"></i> Confirmação
                    </div>
                    <div class="card-body">
                        <form method="POST" onsubmit="return confirmDelete()">
                            <div class="mb-3">
                                <label for="confirmation" class="form-label">
                                    Digite "EXCLUIR PENDENTES" para confirmar:
                                </label>
                                <input type="text" class="form-control" id="confirmation" name="confirmation" 
                                       placeholder="EXCLUIR PENDENTES" required>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="backupCheck" required>
                                <label class="form-check-label" for="backupCheck">
                                    Confirmo que fiz backup dos dados
                                </label>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="understandCheck" required>
                                <label class="form-check-label" for="understandCheck">
                                    Entendo que esta ação é irreversível
                                </label>
                            </div>
                            
                            <button type="submit" name="confirm_delete" class="btn btn-danger">
                                <i class="bi bi-trash"></i> EXCLUIR TODOS OS EXAMES PENDENTES
                            </button>
                            <a href="exams.php" class="btn btn-outline-secondary">Cancelar</a>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Não há exames pendentes para excluir.
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function confirmDelete() {
        const input = document.getElementById('confirmation');
        if (input.value !== 'EXCLUIR PENDENTES') {
            alert('Digite exatamente "EXCLUIR PENDENTES" para confirmar');
            return false;
        }
        
        return confirm('ATENÇÃO: Você está prestes a excluir permanentemente <?php echo $totalPending; ?> exames pendentes.\n\nEsta ação NÃO pode ser desfeita!\n\nDeseja continuar?');
    }
    </script>
</body>
</html>
