<?php
session_start();
require_once '../config/database.php';
require_once '../includes/Database.class.php';
require_once '../includes/Auth.class.php';
require_once '../includes/Patient.class.php';

$auth = new Auth();
$auth->requireRole('admin');

$patient = new Patient();
$db = Database::getInstance();

// Paginação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filtros
$search = $_GET['search'] ?? '';
$filterDate = $_GET['filter_date'] ?? '';

// Query base
$where = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where[] = "(p.full_name LIKE ? OR p.cpf LIKE ? OR p.clinical_record LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    $types .= 'sss';
}

if (!empty($filterDate)) {
    $where[] = "DATE(p.created_at) = ?";
    $params[] = $filterDate;
    $types .= 's';
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Total de registros
$countQuery = "SELECT COUNT(*) as total FROM patients p $whereClause";
$stmt = $db->prepare($countQuery);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$totalResult = $stmt->get_result();
$totalPatients = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalPatients / $limit);

// Buscar pacientes
$query = "
    SELECT p.*, 
           COUNT(e.id) as total_exams,
           MAX(e.exam_date) as last_exam
    FROM patients p
    LEFT JOIN exams e ON p.id = e.patient_id
    $whereClause
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt = $db->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$patients = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pacientes - ECG Manager</title>
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
                        <i class="bi bi-people"></i> Gerenciamento de Pacientes
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="patient_edit.php?action=create" class="btn btn-success">
                            <i class="bi bi-plus-circle"></i> Novo Paciente
                        </a>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-funnel"></i> Filtros
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Nome, CPF ou Registro Clínico" 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <input type="date" name="filter_date" class="form-control" 
                                       value="<?php echo htmlspecialchars($filterDate); ?>">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Filtrar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabela de Pacientes -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            <i class="bi bi-list"></i> Pacientes (<?php echo $totalPatients; ?>)
                        </span>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                    type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-download"></i> Exportar
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="../api/export.php?type=patients&format=csv">CSV</a></li>
                                <li><a class="dropdown-item" href="../api/export.php?type=patients&format=excel">Excel</a></li>
                                <li><a class="dropdown-item" href="../api/export.php?type=patients&format=pdf">PDF</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>Data Nasc.</th>
                                        <th>CPF</th>
                                        <th>Registro</th>
                                        <th>Exames</th>
                                        <th>Último Exame</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($p = $patients->fetch_assoc()): ?>
                                    <tr>
                                        <td><code><?php echo $p['id']; ?></code></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($p['full_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo $p['gender']; ?></small>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($p['birth_date'])); ?></td>
                                        <td><?php echo $p['cpf'] ?: '-'; ?></td>
                                        <td><?php echo $p['clinical_record'] ?: '-'; ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $p['total_exams']; ?></span>
                                        </td>
                                        <td>
                                            <?php if ($p['last_exam']): ?>
                                            <?php echo date('d/m/Y', strtotime($p['last_exam'])); ?>
                                            <?php else: ?>
                                            <span class="text-muted">Nenhum</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="patient_detail.php?id=<?php echo $p['id']; ?>" 
                                                   class="btn btn-outline-primary" title="Detalhes">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="patient_edit.php?id=<?php echo $p['id']; ?>" 
                                                   class="btn btn-outline-secondary" title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button onclick="deletePatient(<?php echo $p['id']; ?>)" 
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
                            <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>">
                                Anterior
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>">
                                Próxima
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/patients.js"></script>
    <script>
    function deletePatient(id) {
        if (confirm('Tem certeza que deseja excluir este paciente? Todos os exames relacionados também serão excluídos!')) {
            fetch('../api/patients.php?action=delete&id=' + id, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Paciente excluído com sucesso!');
                    location.reload();
                } else {
                    alert('Erro: ' + data.message);
                }
            });
        }
    }
    </script>
</body>
</html>