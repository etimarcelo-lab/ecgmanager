<?php
session_start();
require_once '../config/database.php';
require_once '../includes/Database.class.php';
require_once '../includes/Auth.class.php';
require_once '../includes/SyncLogger.class.php';
require_once '../includes/Utils.class.php';

// Habilitar exibição de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

$auth = new Auth();
$auth->requireRole('admin');

$logger = new SyncLogger();
$db = Database::getInstance();

// DEBUG: Verificar contagem de logs por tipo e data
if (isset($_GET['debug_stats'])) {
    $debugQuery = "
        SELECT 
            DATE(processed_at) as data,
            sync_type,
            status,
            COUNT(*) as quantidade,
            AVG(processing_time) as tempo_medio
        FROM sync_logs 
        WHERE processed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(processed_at), sync_type, status
        ORDER BY data DESC, quantidade DESC
        LIMIT 20
    ";
    
    $debugResult = $db->query($debugQuery);
    echo "<!DOCTYPE html><html><head><title>Debug Logs</title></head><body>";
    echo "<h3>DEBUG - Estatísticas de Logs (últimos 7 dias)</h3>";
    echo "<table border='1'><tr><th>Data</th><th>Tipo</th><th>Status</th><th>Quantidade</th><th>Tempo Médio</th></tr>";
    while ($row = $debugResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['data'] . "</td>";
        echo "<td>" . $row['sync_type'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td>" . number_format($row['quantidade']) . "</td>";
        echo "<td>" . round($row['tempo_medio'], 4) . "s</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</body></html>";
    exit;
}

// Verificar se foi solicitada a limpeza de logs
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'cleanup_logs') {
        cleanupOldLogs($db, $logger);
    } elseif ($_GET['action'] === 'cleanup_error_logs') {
        cleanupErrorLogs($db, $logger);
    }
}

// Verificar se foi solicitado export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    exportLogsToCSV($db);
    exit;
}

// Função para limpar logs antigos - VERSÃO CORRIGIDA
function cleanupOldLogs($db, $logger) {
    try {
        // Limpar logs com mais de 7 dias
        $cutoffDate = date('Y-m-d H:i:s', strtotime('-7 days'));
        
        // Query mais simples e segura
        $query = "DELETE FROM sync_logs WHERE processed_at < ?";
        $stmt = $db->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Erro ao preparar query: " . $db->error);
        }
        
        $stmt->bind_param('s', $cutoffDate);
        $stmt->execute();
        
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        // Registrar a limpeza - USANDO APENAS O MÉTODO BÁSICO log()
        $logger->log('log_cleanup', 'success', "Limpeza de logs antigos: {$affectedRows} registros removidos", $affectedRows);
        
        $_SESSION['success_message'] = "{$affectedRows} logs antigos (mais de 7 dias) foram removidos com sucesso.";
        
    } catch (Exception $e) {
        error_log("Erro em cleanupOldLogs: " . $e->getMessage());
        $_SESSION['error_message'] = "Erro ao limpar logs: " . $e->getMessage();
    }
    
    header('Location: logs.php');
    exit;
}

// Função para limpar logs de erro - VERSÃO CORRIGIDA
function cleanupErrorLogs($db, $logger) {
    try {
        // Limpar logs de erro antigos de wxml e pdf (mais de 1 dia)
        $cutoffDate = date('Y-m-d H:i:s', strtotime('-1 day'));
        
        $query = "DELETE FROM sync_logs WHERE sync_type IN ('wxml', 'pdf') AND status = 'error' AND processed_at < ?";
        $stmt = $db->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Erro ao preparar query: " . $db->error);
        }
        
        $stmt->bind_param('s', $cutoffDate);
        $stmt->execute();
        
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        // Registrar a limpeza - USANDO APENAS O MÉTODO BÁSICO log()
        $logger->log('log_cleanup', 'success', "Limpeza de logs de erro: {$affectedRows} registros removidos", $affectedRows);
        
        $_SESSION['success_message'] = "{$affectedRows} logs de erro (wxml/pdf) foram removidos com sucesso.";
        
    } catch (Exception $e) {
        error_log("Erro em cleanupErrorLogs: " . $e->getMessage());
        $_SESSION['error_message'] = "Erro ao limpar logs de erro: " . $e->getMessage();
    }
    
    header('Location: logs.php');
    exit;
}

// Função para exportar logs para CSV
function exportLogsToCSV($db) {
    // Aplicar filtros da página atual
    $type = $_GET['type'] ?? '';
    $status = $_GET['status'] ?? '';
    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    
    // Construir query com filtros
    $where = ["processed_at BETWEEN ? AND ?"];
    $params = [$startDate, $endDate . ' 23:59:59'];
    
    if (!empty($type)) {
        $where[] = "sync_type = ?";
        $params[] = $type;
    }
    
    if (!empty($status)) {
        $where[] = "status = ?";
        $params[] = $status;
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Query para exportar
    $query = "
        SELECT 
            id,
            sync_type,
            filename,
            status,
            message,
            records_processed,
            processing_time,
            ip_address,
            user_agent,
            processed_at
        FROM sync_logs 
        WHERE {$whereClause}
        ORDER BY processed_at DESC
        LIMIT 10000
    ";
    
    $stmt = $db->prepare($query);
    
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Configurar headers para download CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=logs_' . date('Y-m-d_H-i-s') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Cabeçalho do CSV
    fputcsv($output, [
        'ID', 'Tipo', 'Arquivo', 'Status', 'Mensagem', 
        'Registros Processados', 'Tempo Processamento', 
        'IP', 'User Agent', 'Data/Hora Processamento'
    ], ';');
    
    // Dados
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['sync_type'],
            $row['filename'],
            $row['status'],
            $row['message'],
            $row['records_processed'],
            $row['processing_time'],
            $row['ip_address'],
            $row['user_agent'],
            $row['processed_at']
        ], ';');
    }
    
    fclose($output);
    exit;
}

// Filtros da página principal
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Construir query
$where = ["processed_at BETWEEN ? AND ?"];
$params = [$startDate, $endDate . ' 23:59:59'];
$types = 'ss';

if (!empty($type)) {
    $where[] = "sync_type = ?";
    $params[] = $type;
    $types .= 's';
}

if (!empty($status)) {
    $where[] = "status = ?";
    $params[] = $status;
    $types .= 's';
}

$whereClause = implode(' AND ', $where);

// Contar total
$totalLogs = 0;
$totalPages = 0;
$logs = false;

// Primeiro verificar se há parâmetros para a query
$countQuery = "SELECT COUNT(*) as total FROM sync_logs WHERE {$whereClause}";
$stmt = $db->prepare($countQuery);

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if ($stmt->execute()) {
        $totalResult = $stmt->get_result();
        if ($totalResult) {
            $row = $totalResult->fetch_assoc();
            $totalLogs = $row['total'] ?? 0;
            $totalPages = ceil($totalLogs / $limit);
        }
        $stmt->close();
    }
}

// Garantir que a página atual está dentro dos limites
if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

// Buscar logs
if ($totalLogs > 0) {
    $query = "
        SELECT * FROM sync_logs 
        WHERE {$whereClause}
        ORDER BY processed_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt = $db->prepare($query);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            $logs = $stmt->get_result();
        } else {
            error_log("Erro ao executar query de logs: " . $stmt->error);
        }
    } else {
        error_log("Erro ao preparar query de logs: " . $db->error);
    }
}

// Estatísticas
$statsQuery = "
    SELECT 
        sync_type,
        status,
        COUNT(*) as count,
        AVG(processing_time) as avg_time,
        MIN(processed_at) as first_log,
        MAX(processed_at) as last_log
    FROM sync_logs
    WHERE processed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY sync_type, status
    ORDER BY sync_type, status
";

$statsResult = $db->query($statsQuery);
$stats = [];
if ($statsResult) {
    while ($row = $statsResult->fetch_assoc()) {
        $stats[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs do Sistema - ECG Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .btn-toolbar {
            display: flex;
            flex-wrap: nowrap;
            overflow-x: auto;
            padding-bottom: 5px;
        }
        .btn-toolbar .btn {
            white-space: nowrap;
            margin-right: 0.5rem;
        }
        .pagination {
            flex-wrap: wrap;
        }
        .table-responsive {
            max-height: 500px;
            overflow-y: auto;
        }
        @media (max-width: 768px) {
            .btn-toolbar {
                justify-content: flex-start;
            }
            .btn-toolbar .btn {
                margin-bottom: 0.25rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 col-lg-10 px-md-4">
                <!-- Mensagens de Sucesso/Erro/Info -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                        <i class="bi bi-check-circle-fill"></i> <?php echo $_SESSION['success_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $_SESSION['error_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['info_message'])): ?>
                    <div class="alert alert-info alert-dismissible fade show mt-3" role="alert">
                        <i class="bi bi-info-circle-fill"></i> <?php echo $_SESSION['info_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['info_message']); ?>
                <?php endif; ?>
                
				<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
					<h1 class="h2">
						<i class="bi bi-list-check"></i> Logs do Sistema
					</h1>
					<div class="btn-toolbar mb-2 mb-md-0">
						<button class="btn btn-outline-danger me-2" onclick="clearOldLogs()">
							<i class="bi bi-trash"></i> Limpar Logs Antigos
						</button>
						<button class="btn btn-outline-warning me-2" onclick="clearErrorLogs()">
							<i class="bi bi-exclamation-triangle"></i> Limpar Logs de Erro
						</button>
						<a href="logs.php?action=analyze_logs" class="btn btn-outline-primary me-2">
							<i class="bi bi-bar-chart"></i> Analisar Volume
						</a>
						<a href="logs.php?debug_stats" class="btn btn-outline-info me-2">
							<i class="bi bi-bug"></i> Debug
						</a>
						<a href="logs.php?export=csv<?php 
							echo $type ? '&type=' . urlencode($type) : '';
							echo $status ? '&status=' . urlencode($status) : '';
							echo '&start_date=' . urlencode($startDate);
							echo '&end_date=' . urlencode($endDate);
						?>" class="btn btn-outline-success">
							<i class="bi bi-download"></i> Exportar CSV
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
                            <div class="col-md-2">
                                <select class="form-control" name="type">
                                    <option value="">Todos os Tipos</option>
                                    <option value="wxml" <?php echo $type == 'wxml' ? 'selected' : ''; ?>>WXML</option>
                                    <option value="pdf" <?php echo $type == 'pdf' ? 'selected' : ''; ?>>PDF</option>
                                    <option value="file_copy" <?php echo $type == 'file_copy' ? 'selected' : ''; ?>>Cópia de Arquivos</option>
                                    <option value="manual" <?php echo $type == 'manual' ? 'selected' : ''; ?>>Manual</option>
                                    <option value="log_cleanup" <?php echo $type == 'log_cleanup' ? 'selected' : ''; ?>>Limpeza de Logs</option>
                                    <option value="sync" <?php echo $type == 'sync' ? 'selected' : ''; ?>>Sincronização</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-control" name="status">
                                    <option value="">Todos os Status</option>
                                    <option value="success" <?php echo $status == 'success' ? 'selected' : ''; ?>>Sucesso</option>
                                    <option value="error" <?php echo $status == 'error' ? 'selected' : ''; ?>>Erro</option>
                                    <option value="warning" <?php echo $status == 'warning' ? 'selected' : ''; ?>>Aviso</option>
                                    <option value="skipped" <?php echo $status == 'skipped' ? 'selected' : ''; ?>>Ignorado</option>
                                    <option value="info" <?php echo $status == 'info' ? 'selected' : ''; ?>>Informação</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="date" class="form-control" name="start_date" 
                                       value="<?php echo htmlspecialchars($startDate); ?>">
                            </div>
                            <div class="col-md-2">
                                <input type="date" class="form-control" name="end_date" 
                                       value="<?php echo htmlspecialchars($endDate); ?>">
                            </div>
                            <div class="col-md-2">
                                <input type="hidden" name="page" value="1">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Filtrar
                                </button>
                            </div>
                            <div class="col-md-2">
                                <a href="logs.php" class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-x-circle"></i> Limpar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Estatísticas -->
                <?php if (!empty($stats)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-graph-up"></i> Estatísticas dos Últimos 7 Dias
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Status</th>
                                        <th>Quantidade</th>
                                        <th>Tempo Médio</th>
                                        <th>Primeiro Log</th>
                                        <th>Último Log</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats as $stat): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($stat['sync_type']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                switch($stat['status']) {
                                                    case 'success': echo 'success'; break;
                                                    case 'error': echo 'danger'; break;
                                                    case 'warning': echo 'warning'; break;
                                                    case 'info': echo 'info'; break;
                                                    default: echo 'secondary';
                                                }
                                            ?>">
                                                <?php echo htmlspecialchars($stat['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($stat['count']); ?></td>
                                        <td><?php echo round($stat['avg_time'], 4); ?>s</td>
                                        <td><?php echo Utils::formatDateTime($stat['first_log']); ?></td>
                                        <td><?php echo Utils::formatDateTime($stat['last_log']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tabela de Logs -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            <i class="bi bi-list"></i> Logs (<?php echo number_format($totalLogs); ?> registros)
                        </span>
                        <span class="text-muted">
                            Página <?php echo $page; ?> de <?php echo $totalPages; ?>
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($logs && $totalLogs > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Data/Hora</th>
                                        <th>Tipo</th>
                                        <th>Arquivo</th>
                                        <th>Status</th>
                                        <th>Mensagem</th>
                                        <th>Registros</th>
                                        <th>Tempo</th>
                                        <th>IP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($log = $logs->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <?php echo Utils::formatDateTime($log['processed_at']); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($log['sync_type']); ?></span>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo htmlspecialchars($log['filename'] ?: '-'); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                switch($log['status']) {
                                                    case 'success': echo 'success'; break;
                                                    case 'error': echo 'danger'; break;
                                                    case 'warning': echo 'warning'; break;
                                                    case 'skipped': echo 'info'; break;
                                                    case 'info': echo 'primary'; break;
                                                    default: echo 'secondary';
                                                }
                                            ?>">
                                                <?php echo htmlspecialchars($log['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small title="<?php echo htmlspecialchars($log['message']); ?>">
                                                <?php echo htmlspecialchars(substr($log['message'], 0, 100)); ?>
                                                <?php if (strlen($log['message']) > 100): ?>...<?php endif; ?>
                                            </small>
                                        </td>
                                        <td><?php echo $log['records_processed']; ?></td>
                                        <td>
                                            <small class="text-muted"><?php echo round($log['processing_time'], 4); ?>s</small>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo htmlspecialchars($log['ip_address'] ?: '-'); ?></small>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox" style="font-size: 3rem; color: #6c757d;"></i>
                            <p class="mt-3 text-muted">Nenhum log encontrado com os filtros aplicados.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Paginação -->
                <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page-1; ?><?php 
                                echo $type ? '&type=' . urlencode($type) : '';
                                echo $status ? '&status=' . urlencode($status) : '';
                                echo '&start_date=' . urlencode($startDate);
                                echo '&end_date=' . urlencode($endDate);
                            ?>">
                                <i class="bi bi-chevron-left"></i> Anterior
                            </a>
                        </li>
                        
                        <!-- Mostrar apenas algumas páginas ao redor da atual -->
                        <?php 
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        // Primeira página
                        if ($startPage > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=1<?php 
                                echo $type ? '&type=' . urlencode($type) : '';
                                echo $status ? '&status=' . urlencode($status) : '';
                                echo '&start_date=' . urlencode($startDate);
                                echo '&end_date=' . urlencode($endDate);
                            ?>">1</a>
                        </li>
                        <?php if ($startPage > 2): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                        <?php endif; ?>
                        <?php endif; ?>
                        
                        <!-- Páginas ao redor da atual -->
                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?><?php 
                                echo $type ? '&type=' . urlencode($type) : '';
                                echo $status ? '&status=' . urlencode($status) : '';
                                echo '&start_date=' . urlencode($startDate);
                                echo '&end_date=' . urlencode($endDate);
                            ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <!-- Última página -->
                        <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $totalPages; ?><?php 
                                echo $type ? '&type=' . urlencode($type) : '';
                                echo $status ? '&status=' . urlencode($status) : '';
                                echo '&start_date=' . urlencode($startDate);
                                echo '&end_date=' . urlencode($endDate);
                            ?>"><?php echo $totalPages; ?></a>
                        </li>
                        <?php endif; ?>
                        
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page+1; ?><?php 
                                echo $type ? '&type=' . urlencode($type) : '';
                                echo $status ? '&status=' . urlencode($status) : '';
                                echo '&start_date=' . urlencode($startDate);
                                echo '&end_date=' . urlencode($endDate);
                            ?>">
                                Próxima <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function clearOldLogs() {
        if (confirm('Tem certeza que deseja limpar logs antigos (mais de 7 dias)? Esta ação não pode ser desfeita.')) {
            window.location.href = 'logs.php?action=cleanup_logs';
        }
    }
    
    function clearErrorLogs() {
        if (confirm('ATENÇÃO: Isso irá limpar logs de ERRO dos tipos wxml e pdf (mais de 1 dia).\n\nEsta ação é recomendada para reduzir o volume de logs de erro.\n\nContinuar?')) {
            window.location.href = 'logs.php?action=cleanup_error_logs';
        }
    }
    
    // Adicionar tooltips para mensagens longas
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
    </script>
</body>
</html>