<?php
// dashboard/cleanup_patients.php
session_start();
require_once '../config/database.php';
require_once '../includes/Database.class.php';
require_once '../includes/Auth.class.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Buscar pacientes sem exames
$orphanPatients = $db->query("
    SELECT p.*, 
           COUNT(e.id) as exam_count
    FROM patients p
    LEFT JOIN exams e ON p.id = e.patient_id
    GROUP BY p.id
    HAVING exam_count = 0
    ORDER BY p.created_at DESC
    LIMIT 100
");

$totalOrphanPatients = $db->query("
    SELECT COUNT(*) as total 
    FROM (
        SELECT p.id
        FROM patients p
        LEFT JOIN exams e ON p.id = e.patient_id
        GROUP BY p.id
        HAVING COUNT(e.id) = 0
    ) as orphan_patients
")->fetch_assoc()['total'];

// Processar exclusão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    // Primeiro, verificar se há pacientes vinculados a PDFs diretamente (caso exista relação direta)
    $pdfCheck = $db->query("
        SELECT COUNT(*) as total 
        FROM pdf_reports pr
        INNER JOIN exams e ON pr.exam_id = e.id
        INNER JOIN patients p ON e.patient_id = p.id
    ")->fetch_assoc()['total'];
    
    if ($pdfCheck > 0) {
        // Se houver PDFs vinculados, excluir apenas pacientes sem exames
        $query = "
            DELETE p FROM patients p
            LEFT JOIN exams e ON p.id = e.patient_id
            WHERE e.id IS NULL
        ";
    } else {
        // Excluir pacientes sem exames
        $query = "
            DELETE FROM patients 
            WHERE id NOT IN (SELECT DISTINCT patient_id FROM exams WHERE patient_id IS NOT NULL)
        ";
    }
    
    if ($db->query($query)) {
        $deletedCount = $db->affected_rows;
        $success = "Foram excluídos {$deletedCount} pacientes sem exames com sucesso!";
        
        // Limpar variáveis após exclusão
        $orphanPatients = null;
        $totalOrphanPatients = 0;
    } else {
        $error = "Erro ao excluir pacientes: " . $db->error;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Limpeza de Pacientes - ECG Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        body {
            padding-top: 70px;
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
                        <i class="bi bi-person-x"></i> Limpeza de Pacientes Sem Exames
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
                    <p>Esta ação irá excluir permanentemente todos os pacientes que não possuem exames registrados no sistema.</p>
                    <p><strong>Total de pacientes sem exames: <?php echo $totalOrphanPatients; ?></strong></p>
                    <p class="mb-0">Esta ação é irreversível. Recomenda-se fazer um backup antes de prosseguir.</p>
                </div>

                <?php if ($totalOrphanPatients > 0): ?>
                <!-- Lista de pacientes que serão excluídos -->
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white">
                        <i class="bi bi-list"></i> Pacientes que Serão Excluídos (últimos 100)
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>CPF</th>
                                        <th>Data Nasc.</th>
                                        <th>Telefone</th>
                                        <th>Criado em</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($orphanPatients): while ($patient = $orphanPatients->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $patient['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($patient['full_name']); ?></strong></td>
                                        <td><?php echo $patient['cpf'] ?: '-'; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($patient['birth_date'])); ?></td>
                                        <td><?php echo $patient['phone'] ?: '-'; ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($patient['created_at'])); ?></td>
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
                                    Digite "EXCLUIR PACIENTES" para confirmar:
                                </label>
                                <input type="text" class="form-control" id="confirmation" name="confirmation" 
                                       placeholder="EXCLUIR PACIENTES" required>
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
                                <i class="bi bi-trash"></i> EXCLUIR PACIENTES SEM EXAMES
                            </button>
                            <a href="patients.php" class="btn btn-outline-secondary">Cancelar</a>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Não há pacientes sem exames para excluir.
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function confirmDelete() {
        const input = document.getElementById('confirmation');
        if (input.value !== 'EXCLUIR PACIENTES') {
            alert('Digite exatamente "EXCLUIR PACIENTES" para confirmar');
            return false;
        }
        
        return confirm('ATENÇÃO: Você está prestes a excluir permanentemente <?php echo $totalOrphanPatients; ?> pacientes sem exames.\n\nEsta ação NÃO pode ser desfeita!\n\nDeseja continuar?');
    }
    </script>
</body>
</html>
