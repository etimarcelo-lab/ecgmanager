<?php
session_start();
require_once '../config/database.php';
require_once '../includes/Database.class.php';
require_once '../includes/Auth.class.php';
require_once '../includes/Exam.class.php';

$auth = new Auth();
$auth->requireRole('admin');

$exam = new Exam();
$db = Database::getInstance();

// Filtros
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$statusFilter = $_GET['status_filter'] ?? ''; // Renomeado para evitar conflito

// Query
$where = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where[] = "(p.full_name LIKE ? OR e.exam_number LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm]);
    $types .= 'ss';
}

if (!empty($startDate)) {
    $where[] = "e.exam_date >= ?";
    $params[] = $startDate;
    $types .= 's';
}

if (!empty($endDate)) {
    $where[] = "e.exam_date <= ?";
    $params[] = $endDate;
    $types .= 's';
}

if (!empty($statusFilter)) {
    if ($statusFilter === 'with_report') {
        $where[] = "e.pdf_processed = TRUE";
    } elseif ($statusFilter === 'without_report') {
        $where[] = "e.pdf_processed = FALSE";
    } elseif ($statusFilter === 'cancelado') {
        $where[] = "e.status = 'cancelado'";
    } elseif ($statusFilter === 'finalizado') {
        $where[] = "e.status = 'finalizado'";
    } elseif ($statusFilter === 'processando') {
        $where[] = "e.status = 'processando'";
    } elseif ($statusFilter === 'realizado') {
        $where[] = "e.status = 'realizado'";
    }
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Total
$countQuery = "SELECT COUNT(*) as total FROM exams e 
               LEFT JOIN patients p ON e.patient_id = p.id 
               $whereClause";
$stmt = $db->prepare($countQuery);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$totalResult = $stmt->get_result();
$totalExams = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalExams / $limit);

// Exames
$query = "
    SELECT e.*, p.full_name as patient_name, p.birth_date,
           d1.name as resp_doctor, d2.name as req_doctor,
           pr.stored_filename, pr.report_date
    FROM exams e
    LEFT JOIN patients p ON e.patient_id = p.id
    LEFT JOIN doctors d1 ON e.responsible_doctor_id = d1.id
    LEFT JOIN doctors d2 ON e.requesting_doctor_id = d2.id
    LEFT JOIN pdf_reports pr ON e.id = pr.exam_id
    $whereClause
    ORDER BY e.exam_date DESC, e.exam_time DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt = $db->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$exams = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exames - ECG Manager</title>
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
                        <i class="bi bi-clipboard-data"></i> Gerenciamento de Exames
                    </h1>
					<div class="btn-toolbar mb-2 mb-md-0">
						<a href="exam_edit.php?action=create" class="btn btn-success me-2">
							<i class="bi bi-plus-circle"></i> Novo Exame
						</a>
						<button type="button" class="btn btn-danger" onclick="deleteAllPending()">
							<i class="bi bi-trash"></i> Excluir Pendentes
						</button>
					</div>
                </div>

                <!-- Filtros -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-funnel"></i> Filtros Avançados
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Paciente ou Nº Exame" 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <input type="date" name="start_date" class="form-control" 
                                       value="<?php echo htmlspecialchars($startDate); ?>">
                            </div>
                            <div class="col-md-2">
                                <input type="date" name="end_date" class="form-control" 
                                       value="<?php echo htmlspecialchars($endDate); ?>">
                            </div>
                            <div class="col-md-2">
                                <select name="status_filter" class="form-control">
                                    <option value="">Todos Status</option>
                                    <option value="with_report" <?php echo $statusFilter === 'with_report' ? 'selected' : ''; ?>>Com Laudo</option>
                                    <option value="without_report" <?php echo $statusFilter === 'without_report' ? 'selected' : ''; ?>>Sem Laudo</option>
                                    <option value="realizado" <?php echo $statusFilter === 'realizado' ? 'selected' : ''; ?>>Realizado</option>
                                    <option value="processando" <?php echo $statusFilter === 'processando' ? 'selected' : ''; ?>>Processando</option>
                                    <option value="finalizado" <?php echo $statusFilter === 'finalizado' ? 'selected' : ''; ?>>Finalizado</option>
                                    <option value="cancelado" <?php echo $statusFilter === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Filtrar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabela de Exames -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            <i class="bi bi-list"></i> Exames (<?php echo $totalExams; ?>)
                        </span>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                    type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-download"></i> Exportar
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="../api/export.php?type=exams&format=csv">CSV</a></li>
                                <li><a class="dropdown-item" href="../api/export.php?type=exams&format=excel">Excel</a></li>
                                <li><a class="dropdown-item" href="../api/export.php?type=exams&format=pdf">PDF</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Data/Hora</th>
                                        <th>Paciente</th>
                                        <th>Nº Exame</th>
                                        <th>Médico Resp.</th>
                                        <th>FC</th>
                                        <th>Status</th>
                                        <th>Laudo</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($e = $exams->fetch_assoc()): 
                                        $hasReport = !empty($e['stored_filename']);
                                        // Determinar status baseado no campo status e na presença de PDF
                                        if ($e['status'] === 'cancelado') {
                                            $statusClass = 'secondary';
                                            $statusText = 'Cancelado';
                                            $statusIcon = 'bi-x-circle';
                                        } elseif ($hasReport) {
                                            $statusClass = 'success';
                                            $statusText = 'Com Laudo';
                                            $statusIcon = 'bi-check-circle';
                                        } elseif ($e['status'] === 'finalizado') {
                                            $statusClass = 'info';
                                            $statusText = 'Finalizado';
                                            $statusIcon = 'bi-check-circle';
                                        } elseif ($e['status'] === 'processando') {
                                            $statusClass = 'warning';
                                            $statusText = 'Processando';
                                            $statusIcon = 'bi-gear';
                                        } else {
                                            $statusClass = 'warning';
                                            $statusText = 'Pendente';
                                            $statusIcon = 'bi-clock';
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($e['exam_date'])); ?><br>
                                            <small class="text-muted"><?php echo $e['exam_time']; ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($e['patient_name']); ?></strong><br>
                                            <small class="text-muted">
                                                Nasc: <?php echo date('d/m/Y', strtotime($e['birth_date'])); ?>
                                            </small>
                                        </td>
                                        <td><code><?php echo $e['exam_number']; ?></code></td>
                                        <td><?php echo $e['resp_doctor'] ?: '-'; ?></td>
                                        <td>
                                            <?php if ($e['heart_rate']): ?>
                                            <span class="badge bg-danger"><?php echo $e['heart_rate']; ?> bpm</span>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $statusClass; ?>">
                                                <i class="bi <?php echo $statusIcon; ?>"></i>
                                                <?php echo $statusText; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($hasReport): ?>
                                            <a href="../api/pdf_viewer.php?exam_id=<?php echo $e['id']; ?>" 
                                               target="_blank" class="btn btn-sm btn-outline-info">
                                                <i class="bi bi-file-pdf"></i> Ver
                                            </a>
                                            <?php else: ?>
                                            <button onclick="uploadReport(<?php echo $e['id']; ?>)" 
                                                    class="btn btn-sm btn-outline-warning">
                                                <i class="bi bi-upload"></i> Upload
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="exam_detail.php?id=<?php echo $e['id']; ?>" 
                                                   class="btn btn-outline-primary" title="Detalhes">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="exam_edit.php?id=<?php echo $e['id']; ?>" 
                                                   class="btn btn-outline-secondary" title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button onclick="deleteExam(<?php echo $e['id']; ?>)" 
                                                        class="btn btn-outline-danger" title="Excluir">
                                                    <i class="bi bi-trash"></i>
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

                <!-- Paginação -->
                <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?><?php echo $statusFilter ? '&status_filter='.urlencode($statusFilter) : ''; ?><?php echo $startDate ? '&start_date='.urlencode($startDate) : ''; ?><?php echo $endDate ? '&end_date='.urlencode($endDate) : ''; ?>">
                                Anterior
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?><?php echo $statusFilter ? '&status_filter='.urlencode($statusFilter) : ''; ?><?php echo $startDate ? '&start_date='.urlencode($startDate) : ''; ?><?php echo $endDate ? '&end_date='.urlencode($endDate) : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?><?php echo $statusFilter ? '&status_filter='.urlencode($statusFilter) : ''; ?><?php echo $startDate ? '&start_date='.urlencode($startDate) : ''; ?><?php echo $endDate ? '&end_date='.urlencode($endDate) : ''; ?>">
                                Próxima
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Modal para Upload -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload de Laudo PDF</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadForm" enctype="multipart/form-data">
                        <input type="hidden" id="uploadExamId" name="exam_id">
                        <div class="mb-3">
                            <label for="pdfFile" class="form-label">Selecione o arquivo PDF</label>
                            <input type="file" class="form-control" id="pdfFile" name="pdf_file" accept=".pdf" required>
                            <div class="form-text">Apenas arquivos PDF são permitidos.</div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Auto-refresh a cada 3 minutos (180000 milissegundos)
    setTimeout(function() {
        location.reload();
    }, 180000);
    
    function deleteExam(id) {
        if (confirm('Tem certeza que deseja excluir este exame?')) {
            fetch('../api/exams.php?action=delete&id=' + id, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Exame excluído com sucesso!');
                    location.reload();
                } else {
                    alert('Erro: ' + data.message);
                }
            });
        }
    }
    
    function uploadReport(examId) {
        document.getElementById('uploadExamId').value = examId;
        const modal = new bootstrap.Modal(document.getElementById('uploadModal'));
        modal.show();
    }
    
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
        });
    }

	    function deleteAllPending() {
	    if (confirm('ATENÇÃO: Você está prestes a excluir TODOS os exames pendentes (sem laudo).\n\nEsta ação é irreversível!\n\nDeseja continuar?')) {
	        const btn = document.createElement('button');
	        btn.disabled = true;
	        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Excluindo...';
	        
	        fetch('../api/exams.php?action=delete_all_pending', {
	            method: 'DELETE'
	        })
	        .then(response => response.json())
	        .then(data => {
	            if (data.success) {
	                alert(`Foram excluídos ${data.deleted_count} exames pendentes com sucesso!`);
	                location.reload();
	            } else {
	                alert('Erro: ' + data.message);
	            }
	        })
	        .catch(error => {
	            alert('Erro: ' + error.message);
	        });
	    }
	}
    </script>
</body>
</html>
