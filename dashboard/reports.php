<?php
// dashboard/reports.php
session_start();
require_once '../config/database.php';
require_once '../includes/Database.class.php';
require_once '../includes/Auth.class.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Período padrão: último mês
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Total de laudos no período
$totalReportsQuery = $db->prepare("
    SELECT COUNT(*) as total
    FROM pdf_reports pr
    INNER JOIN exams e ON pr.exam_id = e.id
    WHERE pr.report_date BETWEEN ? AND ?
    AND e.status != 'cancelado'
");
$totalReportsQuery->bind_param('ss', $startDate, $endDate);
$totalReportsQuery->execute();
$totalReportsResult = $totalReportsQuery->get_result();
$totalReports = $totalReportsResult->fetch_assoc()['total'];

// Distribuição por médico
$doctorDistributionQuery = $db->prepare("
    SELECT 
        COALESCE(d.name, 'Não informado') as doctor_name,
        COUNT(pr.id) as report_count,
        ROUND((COUNT(pr.id) * 100.0 / ?), 2) as percentage
    FROM pdf_reports pr
    INNER JOIN exams e ON pr.exam_id = e.id
    LEFT JOIN doctors d ON e.responsible_doctor_id = d.id
    WHERE pr.report_date BETWEEN ? AND ?
    AND e.status != 'cancelado'
    GROUP BY COALESCE(d.id, 0), COALESCE(d.name, 'Não informado')
    ORDER BY report_count DESC
    LIMIT 10
");
$doctorDistributionQuery->bind_param('iss', $totalReports, $startDate, $endDate);
$doctorDistributionQuery->execute();
$doctorDistribution = $doctorDistributionQuery->get_result();

// Crescimento mensal (últimos 6 meses)
$monthlyGrowthQuery = $db->prepare("
    SELECT 
        DATE_FORMAT(pr.report_date, '%Y-%m') as month,
        COUNT(*) as report_count,
        DATE_FORMAT(pr.report_date, '%b/%Y') as month_formatted
    FROM pdf_reports pr
    INNER JOIN exams e ON pr.exam_id = e.id
    WHERE pr.report_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    AND e.status != 'cancelado'
    GROUP BY DATE_FORMAT(pr.report_date, '%Y-%m')
    ORDER BY month ASC
");
$monthlyGrowthQuery->execute();
$monthlyGrowth = $monthlyGrowthQuery->get_result();

// Laudos recentes
$recentReportsQuery = $db->prepare("
    SELECT 
        pr.*,
        e.exam_number,
        p.full_name as patient_name,
        d.name as doctor_name,
        pr.report_date,
        pr.stored_filename
    FROM pdf_reports pr
    INNER JOIN exams e ON pr.exam_id = e.id
    INNER JOIN patients p ON e.patient_id = p.id
    LEFT JOIN doctors d ON e.responsible_doctor_id = d.id
    WHERE e.status != 'cancelado'
    ORDER BY pr.report_date DESC, pr.created_at DESC
    LIMIT 20
");
$recentReportsQuery->execute();
$recentReports = $recentReportsQuery->get_result();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - ECG Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { padding-top: 70px; }
        .chart-container { position: relative; height: 300px; }
        .doctor-item { 
            border-left: 4px solid #007bff;
            padding-left: 10px;
            margin-bottom: 10px;
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
                        <i class="bi bi-file-text"></i> Relatórios e Estatísticas
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="printReport()">
                                <i class="bi bi-printer"></i> Imprimir
                            </button>
                            <a href="../api/export.php?type=reports&format=pdf" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-download"></i> Exportar
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-funnel"></i> Filtrar por Período
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="start_date" class="form-label">Data Inicial</label>
                                <input type="date" name="start_date" class="form-control" 
                                       value="<?php echo htmlspecialchars($startDate); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="end_date" class="form-label">Data Final</label>
                                <input type="date" name="end_date" class="form-control" 
                                       value="<?php echo htmlspecialchars($endDate); ?>">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-filter"></i> Aplicar Filtro
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Cards de Resumo -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body text-center">
                                <h1 class="display-6"><?php echo $totalReports; ?></h1>
                                <p class="card-text">Laudos no Período</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-success">
                            <div class="card-body text-center">
                                <?php
                                $avgMonthly = $totalReports > 0 ? round($totalReports / 30 * 30) : 0;
                                ?>
                                <h1 class="display-6"><?php echo $avgMonthly; ?></h1>
                                <p class="card-text">Média Mensal</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-info">
                            <div class="card-body text-center">
                                <?php
                                $daysDiff = (strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24);
                                $avgDaily = $daysDiff > 0 ? round($totalReports / $daysDiff, 1) : 0;
                                ?>
                                <h1 class="display-6"><?php echo $avgDaily; ?></h1>
                                <p class="card-text">Média Diária</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body text-center">
                                <?php
                                $recentReports->data_seek(0);
                                $totalPages = 0;
                                while ($report = $recentReports->fetch_assoc()) {
                                    // Estimativa: 1 laudo = 2 páginas em média
                                    $totalPages += 2;
                                }
                                $recentReports->data_seek(0); // Reset pointer
                                ?>
                                <h1 class="display-6"><?php echo $totalPages; ?></h1>
                                <p class="card-text">Páginas Estimadas</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos e Distribuição -->
                <div class="row mb-4">
                    <!-- Distribuição por Médico -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <i class="bi bi-person-badge"></i> Distribuição por Médico
                            </div>
                            <div class="card-body">
                                <?php if ($doctorDistribution->num_rows > 0): ?>
                                <div class="chart-container">
                                    <canvas id="doctorChart"></canvas>
                                </div>
                                <div class="mt-3">
                                    <h6>Top Médicos:</h6>
                                    <?php 
                                    $doctorDistribution->data_seek(0);
                                    $doctorData = [];
                                    $doctorLabels = [];
                                    $doctorColors = [
                                        '#007bff', '#28a745', '#ffc107', '#dc3545', 
                                        '#6f42c1', '#20c997', '#fd7e14', '#e83e8c', 
                                        '#17a2b8', '#6c757d'
                                    ];
                                    
                                    while ($row = $doctorDistribution->fetch_assoc()): 
                                        $doctorLabels[] = $row['doctor_name'];
                                        $doctorData[] = $row['report_count'];
                                    ?>
                                    <div class="doctor-item">
                                        <div class="d-flex justify-content-between">
                                            <span><strong><?php echo htmlspecialchars($row['doctor_name']); ?></strong></span>
                                            <span><?php echo $row['report_count']; ?> laudos (<?php echo $row['percentage']; ?>%)</span>
                                        </div>
                                        <div class="progress mt-1" style="height: 5px;">
                                            <div class="progress-bar" 
                                                 style="width: <?php echo min($row['percentage'], 100); ?>%; background-color: <?php echo $doctorColors[$doctorDistribution->num_rows % 10]; ?>;">
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-info-circle display-4 text-muted"></i>
                                    <p class="mt-2">Nenhum laudo encontrado no período selecionado.</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Crescimento Mensal -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <i class="bi bi-graph-up"></i> Crescimento Mensal (Últimos 6 meses)
                            </div>
                            <div class="card-body">
                                <?php if ($monthlyGrowth->num_rows > 0): ?>
                                <div class="chart-container">
                                    <canvas id="growthChart"></canvas>
                                </div>
                                <div class="mt-3">
                                    <h6>Detalhes:</h6>
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Mês</th>
                                                <th>Laudos</th>
                                                <th>Variação</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $prevCount = 0;
                                            $monthlyGrowth->data_seek(0);
                                            while ($row = $monthlyGrowth->fetch_assoc()): 
                                                $variation = $prevCount > 0 ? 
                                                    round((($row['report_count'] - $prevCount) / $prevCount) * 100, 1) : 
                                                    0;
                                                $variationClass = $variation > 0 ? 'text-success' : ($variation < 0 ? 'text-danger' : 'text-muted');
                                            ?>
                                            <tr>
                                                <td><?php echo $row['month_formatted']; ?></td>
                                                <td><strong><?php echo $row['report_count']; ?></strong></td>
                                                <td class="<?php echo $variationClass; ?>">
                                                    <?php echo $variation > 0 ? '+' : ''; ?><?php echo $variation; ?>%
                                                </td>
                                            </tr>
                                            <?php 
                                                $prevCount = $row['report_count'];
                                            endwhile; 
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-info-circle display-4 text-muted"></i>
                                    <p class="mt-2">Nenhum dado disponível para os últimos 6 meses.</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Laudos Recentes -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            <i class="bi bi-clock-history"></i> Laudos Recentes
                        </span>
                        <span class="badge bg-primary">
                            <?php echo $recentReports->num_rows; ?> laudos
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Data</th>
                                        <th>Paciente</th>
                                        <th>Nº Exame</th>
                                        <th>Médico</th>
                                        <th>Arquivo</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($report = $recentReports->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($report['report_date'])); ?><br>
                                            <small class="text-muted">
                                                <?php echo date('H:i', strtotime($report['created_at'])); ?>
                                            </small>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($report['patient_name']); ?></strong></td>
                                        <td><code><?php echo $report['exam_number']; ?></code></td>
                                        <td><?php echo $report['doctor_name'] ?: 'Não informado'; ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <i class="bi bi-file-pdf"></i> PDF
                                            </span>
                                            <small class="text-muted d-block">
                                                <?php echo round($report['file_size'] / 1024, 1); ?> KB
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="../api/pdf_viewer.php?report_id=<?php echo $report['id']; ?>" 
						   target="_blank" class="btn btn-outline-info" title="Ver PDF">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="../api/download_pdf.php?id=<?php echo $report['id']; ?>" 
                                                   class="btn btn-outline-success" title="Download">
                                                    <i class="bi bi-download"></i>
                                                </a>
                                                <button onclick="deleteReport(<?php echo $report['id']; ?>)" 
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
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Gráfico de Distribuição por Médico
    <?php if ($doctorDistribution->num_rows > 0): ?>
    const doctorCtx = document.getElementById('doctorChart').getContext('2d');
    const doctorChart = new Chart(doctorCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($doctorLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($doctorData); ?>,
                backgroundColor: <?php echo json_encode(array_slice($doctorColors, 0, count($doctorLabels))); ?>,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20
                    }
                }
            }
        }
    });
    <?php endif; ?>

    // Gráfico de Crescimento Mensal
    <?php if ($monthlyGrowth->num_rows > 0): 
        $monthlyGrowth->data_seek(0);
        $months = [];
        $counts = [];
        while ($row = $monthlyGrowth->fetch_assoc()) {
            $months[] = $row['month_formatted'];
            $counts[] = $row['report_count'];
        }
    ?>
    const growthCtx = document.getElementById('growthChart').getContext('2d');
    const growthChart = new Chart(growthCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($months); ?>,
            datasets: [{
                label: 'Laudos por Mês',
                data: <?php echo json_encode($counts); ?>,
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                borderColor: '#28a745',
                borderWidth: 2,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Quantidade de Laudos'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Mês'
                    }
                }
            }
        }
    });
    <?php endif; ?>

    function printReport() {
        window.print();
    }

    function deleteReport(id) {
        if (confirm('Tem certeza que deseja excluir este laudo?')) {
            fetch('../api/reports.php?action=delete&id=' + id, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Laudo excluído com sucesso!');
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
