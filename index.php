<?php
session_start();
require_once 'config/database.php';
require_once 'includes/Database.class.php';
require_once 'includes/Auth.class.php';
require_once 'includes/ConnectionChecker.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$connectionChecker = new ConnectionChecker();
$connectionStatus = $connectionChecker->getStatus();
$connectionMessage = $connectionChecker->getMessage();

$db = Database::getInstance();
$role = $_SESSION['role'];

// Estatísticas
$stats = [];

// Pacientes
$result = $db->query("SELECT COUNT(*) as total FROM patients");
$stats['patients'] = $result->fetch_assoc()['total'];

// Exames (excluindo cancelados)
$result = $db->query("SELECT COUNT(*) as total FROM exams WHERE status != 'cancelado'");
$stats['exams'] = $result->fetch_assoc()['total'];

// Laudos (PDFs) de exames ativos (não cancelados)
$result = $db->query("
    SELECT COUNT(DISTINCT pr.id) as total 
    FROM pdf_reports pr
    INNER JOIN exams e ON pr.exam_id = e.id 
    WHERE e.status != 'cancelado'
");
$stats['reports'] = $result->fetch_assoc()['total'];

// Pendentes: exames ativos sem PDF
$result = $db->query("
    SELECT COUNT(*) as total 
    FROM exams e
    LEFT JOIN pdf_reports pr ON e.id = pr.exam_id
    WHERE e.status != 'cancelado' 
    AND pr.id IS NULL
");
$stats['pending'] = $result->fetch_assoc()['total'];

// Exames hoje (excluindo cancelados)
$today = date('Y-m-d');
$result = $db->query("SELECT COUNT(*) as total FROM exams WHERE exam_date = '$today' AND status != 'cancelado'");
$stats['exams_today'] = $result->fetch_assoc()['total'];

// Exames com laudo / Total de exames ativos
$result = $db->query("
    SELECT 
        COUNT(DISTINCT e.id) as total_active_exams,
        COUNT(DISTINCT pr.exam_id) as exams_with_reports
    FROM exams e
    LEFT JOIN pdf_reports pr ON e.id = pr.exam_id
    WHERE e.status != 'cancelado'
");
$coverageData = $result->fetch_assoc();
$totalActiveExams = $coverageData['total_active_exams'];
$examsWithReports = $coverageData['exams_with_reports'];


// Últimos exames (excluindo cancelados)
$recentExams = $db->query("
    SELECT e.*, p.full_name as patient_name, 
           pr.report_date, pr.stored_filename,
           d1.name as resp_doctor, d2.name as req_doctor
    FROM exams e
    LEFT JOIN patients p ON e.patient_id = p.id
    LEFT JOIN pdf_reports pr ON e.id = pr.exam_id
    LEFT JOIN doctors d1 ON e.responsible_doctor_id = d1.id
    LEFT JOIN doctors d2 ON e.requesting_doctor_id = d2.id
    WHERE e.status != 'cancelado'
    ORDER BY e.exam_date DESC, e.exam_time DESC
    LIMIT 20
");

// Configurações do auto-refresh (em segundos)
$refreshInterval = 60;     // 1 minuto
$warningTime = 10;         // Aviso 10 segundos antes
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ECG Manager - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .stat-card { 
            transition: transform 0.3s; 
            height: 100%;
            min-height: 150px;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .bg-pending { background: linear-gradient(45deg, #ff9a00, #ff5e00); }
        .bg-completed { background: linear-gradient(45deg, #00b09b, #96c93d); }
        .bg-total { background: linear-gradient(45deg, #2193b0, #6dd5ed); }
        .bg-today { background: linear-gradient(45deg, #8e2de2, #4a00e0); }
        .bg-coverage { background: linear-gradient(45deg, #f46b45, #eea849); }
        .bg-patients { background: linear-gradient(45deg, #1d976c, #93f9b9); }
        .bg-connection { background: linear-gradient(45deg, #6a11cb, #2575fc); }
        
        /* Estilos para o card de conexão */
        .connection-dot {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            vertical-align: middle;
        }
        
        .connection-connected {
            background-color: #28a745;
            box-shadow: 0 0 15px rgba(40, 167, 69, 0.8);
            animation: pulse 2s infinite;
        }
        
        .connection-disconnected {
            background-color: #dc3545;
            box-shadow: 0 0 15px rgba(220, 53, 69, 0.8);
        }
        
        .connection-checking {
            background-color: #ffc107;
            animation: blink 1.5s infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                box-shadow: 0 0 15px rgba(40, 167, 69, 0.8);
            }
            50% {
                box-shadow: 0 0 25px rgba(40, 167, 69, 1);
            }
        }
        
        @keyframes blink {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }
        
        .connection-status-text {
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
            min-height: 1.2rem;
        }
        
        .connection-actions {
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        /* Estilo para o botão de sincronização quando desabilitado */
        .btn-sync:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        /* Garantir que os cards tenham altura uniforme */
        .card-body {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        /* Estilos para o auto-refresh */
        #refreshWarning {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 300px;
            transition: all 0.3s ease;
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from {
                transform: translateY(100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .refresh-countdown {
            font-weight: bold;
            font-size: 1.1rem;
            color: #dc3545;
        }
        
        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            z-index: 9998;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            display: none; /* Inicialmente escondido */
        }
        
        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #0d6efd;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        .loading-text {
            margin-top: 20px;
            font-size: 1.2rem;
            color: #333;
            font-weight: 500;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Ajustes de responsividade */
        @media (max-width: 768px) {
            #refreshWarning {
                bottom: 10px;
                right: 10px;
                left: 10px;
                max-width: none;
            }
            
            .loading-spinner {
                width: 50px;
                height: 50px;
            }
            
            .loading-text {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Atualizando dados...</div>
    </div>
    
    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Troubleshooting Modal -->
    <div class="modal fade" id="troubleshootModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="bi bi-tools"></i> Solução de Problemas de Conexão
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>Computador Remoto: <code>\\192.168.140.199</code> Computador do Eletro</h6>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <i class="bi bi-check2-circle"></i> Verificações Básicas
                                </div>
                                <div class="card-body">
                                    <ol class="small">
                                        <li>Verifique se o computador do Eletro está ligado</li>
                                        <li>Confirme o cabo de rede conectado</li>
                                        <li>Verifique o endereço IP: 192.168.140.199</li>
                                        <li>Teste o compartilhamento de arquivos ativo</li>
                                        <li>Verifique credenciais de acesso</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <i class="bi bi-terminal"></i> Testes de Rede
                                </div>
                                <div class="card-body">
                                    <button class="btn btn-sm btn-outline-primary mb-2" onclick="runPingTest()">
                                        <i class="bi bi-wifi"></i> Testar Ping
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary mb-2" onclick="runPortTest()">
                                        <i class="bi bi-hdd-network"></i> Testar Porta SMB
                                    </button>
                                    <div id="testResults" class="mt-2 small"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i>
                        <strong>Nota:</strong> A sincronização automática será retomada automaticamente quando a conexão for restabelecida.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary" onclick="testConnection(true)">
                        <i class="bi bi-arrow-clockwise"></i> Testar Conexão Novamente
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Refresh Warning Notification -->
    <div id="refreshWarning" style="display: none;">
        <div class="card shadow-lg">
            <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center py-2">
                <strong><i class="bi bi-clock-history"></i> Atualização Automática</strong>
                <button type="button" class="btn-close btn-close-sm" onclick="dismissRefreshWarning()"></button>
            </div>
            <div class="card-body p-3">
                <p class="mb-2">A página será atualizada automaticamente em:</p>
                <p class="text-center mb-3">
                    <span class="refresh-countdown" id="countdownTimer">10</span> segundos
                </p>
                <div class="d-grid gap-2">
                    <button class="btn btn-sm btn-primary" onclick="refreshNow()">
                        <i class="bi bi-arrow-clockwise"></i> Atualizar Agora
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="cancelAutoRefresh()">
                        <i class="bi bi-x-circle"></i> Cancelar por 5 minutos
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar (apenas para admin) -->
            <?php if ($role === 'admin'): ?>
                <?php include 'includes/sidebar.php'; ?>
            <?php endif; ?>

            <!-- Main Content -->
            <div class="<?php echo $role === 'admin' ? 'col-md-9 col-lg-10' : 'col-12'; ?> ms-sm-auto px-md-4">
                <!-- Cards de Estatísticas - Layout ajustado para 7 cards -->
                <div class="row mb-4">
                    <!-- Pacientes -->
                    <div class="col-6 col-md-3 col-lg-2 mb-3">
                        <div class="card text-white bg-patients stat-card">
                            <div class="card-body text-center d-flex flex-column justify-content-center">
                                <h1 class="display-6 mb-2"><?php echo $stats['patients']; ?></h1>
                                <p class="card-text mb-0"><i class="bi bi-people-fill"></i> Pacientes</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Exames -->
                    <div class="col-6 col-md-3 col-lg-2 mb-3">
                        <div class="card text-white bg-total stat-card">
                            <div class="card-body text-center d-flex flex-column justify-content-center">
                                <h1 class="display-6 mb-2"><?php echo $stats['exams']; ?></h1>
                                <p class="card-text mb-0"><i class="bi bi-clipboard-check"></i> Exames</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Laudos -->
                    <div class="col-6 col-md-3 col-lg-2 mb-3">
                        <div class="card text-white bg-completed stat-card">
                            <div class="card-body text-center d-flex flex-column justify-content-center">
                                <h1 class="display-6 mb-2"><?php echo $stats['reports']; ?></h1>
                                <p class="card-text mb-0"><i class="bi bi-file-pdf"></i> Laudos</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pendentes -->
                    <div class="col-6 col-md-3 col-lg-2 mb-3">
                        <div class="card text-white bg-pending stat-card">
                            <div class="card-body text-center d-flex flex-column justify-content-center">
                                <h1 class="display-6 mb-2"><?php echo $stats['pending']; ?></h1>
                                <p class="card-text mb-0"><i class="bi bi-clock-history"></i> Pendentes</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Hoje -->
                    <div class="col-6 col-md-3 col-lg-2 mb-3">
                        <div class="card text-white bg-today stat-card">
                            <div class="card-body text-center d-flex flex-column justify-content-center">
                                <h1 class="display-6 mb-2"><?php echo $stats['exams_today']; ?></h1>
                                <p class="card-text mb-0"><i class="bi bi-calendar-day"></i> Hoje</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Card de Status da Conexão (sempre visível) -->
                    <div class="col-6 col-md-3 col-lg-2 mb-3">
                        <div class="card text-white bg-connection stat-card" id="connectionCard">
                            <div class="card-body text-center d-flex flex-column justify-content-center">
                                <div class="mb-2">
                                    <span class="connection-dot connection-<?php echo $connectionStatus; ?>" 
                                          id="connectionDot"></span>
                                </div>
                                <p class="card-text mb-2">
                                    <i class="bi bi-wifi"></i> Conexão
                                </p>
                                <p class="connection-status-text mb-2" id="connectionStatusText">
                                    <?php 
                                    switch($connectionStatus) {
                                        case 'connected': echo 'Conectado'; break;
                                        case 'disconnected': echo 'Desconectado'; break;
                                        default: echo 'Verificando...';
                                    }
                                    ?>
                                </p>
                                
                                <div class="connection-actions mt-auto pt-2">
                                    <button class="btn btn-sm btn-outline-light w-100 mb-1" 
                                            onclick="testConnection()" 
                                            id="testConnectionBtn">
                                        <i class="bi bi-arrow-clockwise"></i> Testar
                                    </button>
                                    
                                    <?php if ($connectionStatus === 'disconnected'): ?>
                                    <button class="btn btn-sm btn-outline-warning w-100" 
                                            onclick="showTroubleshoot()">
                                        <i class="bi bi-tools"></i> Solucionar
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Aviso de sincronização (apenas quando desconectado) -->
                <?php if ($connectionStatus === 'disconnected'): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                        <div>
                            <h5 class="alert-heading mb-1">Sincronização Interrompida</h5>
                            <p class="mb-0">
                                Não há conexão com o <Strong>Computador do Eletro</Strong> (<code>\\192.168.140.199</code>). 
                                A sincronização automática de exames e laudos está suspensa até que a conexão seja restabelecida.
                            </p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Últimos Exames -->
                <div class="card shadow">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-card-list"></i> Últimos Exames</h5>
                        <div>
                            <button class="btn btn-sm btn-light me-2" onclick="manualSync()" id="syncBtn"
                                    <?php echo $connectionStatus === 'disconnected' ? 'disabled' : ''; ?>
                                    title="<?php echo $connectionStatus === 'disconnected' ? 'Conexão remota indisponível' : 'Sincronizar manualmente'; ?>">
                                <i class="bi bi-arrow-clockwise"></i> Sincronizar
                            </button>
                            <?php if ($role === 'admin'): ?>
                            <a href="dashboard/exams.php?view=all" class="btn btn-sm btn-outline-light">
                                <i class="bi bi-eye"></i> Ver Todos
                            </a>
                            <?php endif; ?>
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
                                        <th>FC (bpm)</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($exam = $recentExams->fetch_assoc()): 
                                        $hasReport = !empty($exam['stored_filename']);
                                        // Determinar status baseado no campo status e na presença de PDF
                                        if ($exam['status'] === 'cancelado') {
                                            $statusClass = 'danger';
                                            $statusText = 'Cancelado';
                                            $statusIcon = 'bi-x-circle';
                                        } elseif ($hasReport) {
                                            $statusClass = 'success';
                                            $statusText = 'Com Laudo';
                                            $statusIcon = 'bi-check-circle';
                                        } elseif ($exam['status'] === 'finalizado') {
                                            $statusClass = 'info';
                                            $statusText = 'Finalizado';
                                            $statusIcon = 'bi-check-circle';
                                        } elseif ($exam['status'] === 'processando') {
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
                                        <td><?php echo date('d/m/Y H:i', strtotime($exam['exam_date'] . ' ' . $exam['exam_time'])); ?></td>
                                        <td><strong><?php echo htmlspecialchars($exam['patient_name']); ?></strong></td>
                                        <td><code><?php echo $exam['exam_number']; ?></code></td>
                                        <td>
                                            <?php if ($exam['heart_rate']): ?>
                                            <span class="badge bg-danger"><?php echo $exam['heart_rate']; ?> bpm</span>
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
                                            <div class="btn-group btn-group-sm">
                                                <?php if ($hasReport): ?>
                                                <a href="api/pdf_viewer.php?exam_id=<?php echo $exam['id']; ?>" 
                                                   target="_blank" class="btn btn-outline-info" title="Ver PDF">
                                                    <i class="bi bi-file-pdf"></i>
                                                </a>
                                                <?php endif; ?>
                                                
                                                <!-- Botão Detalhes para TODOS os usuários logados -->
                                                <a href="dashboard/exam_detail.php?id=<?php echo $exam['id']; ?>" 
                                                   class="btn btn-outline-primary" title="Detalhes do Exame">
                                                    <i class="bi bi-info-circle"></i>
                                                </a>
                                                
                                                <!-- Botão Editar apenas para admin -->
                                                <?php if ($role === 'admin'): ?>
                                                <a href="dashboard/exam_edit.php?id=<?php echo $exam['id']; ?>" 
                                                   class="btn btn-outline-secondary" title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
   <!-- Restante do código HTML/PHP mantido igual até a parte do JavaScript -->
	<script>
	// Configurações do auto-refresh (em segundos)
	const REFRESH_INTERVAL = <?php echo $refreshInterval; ?>; // 60 segundos
	const WARNING_TIME = <?php echo $warningTime; ?>; // 10 segundos

	// Variáveis de controle
	let refreshTimer;
	let warningTimer;
	let cancelUntil = 0; // Timestamp até quando o refresh está cancelado
	let isWarningActive = false;

	// Função para testar conexão
	function testConnection(showAlert = false) {
		const btn = document.getElementById('testConnectionBtn');
		const originalHtml = btn.innerHTML;
		const dot = document.getElementById('connectionDot');
		const statusText = document.getElementById('connectionStatusText');
		const syncBtn = document.getElementById('syncBtn');
		
		btn.disabled = true;
		btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
		
		// Alterar status para checking
		dot.className = 'connection-dot connection-checking';
		statusText.textContent = 'Verificando...';
		
		fetch('api/test_connection.php?log=true')
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					dot.className = 'connection-dot connection-connected';
					statusText.textContent = 'Conectado';
					
					// Habilitar botão de sincronização
					if (syncBtn) {
						syncBtn.disabled = false;
						syncBtn.title = 'Sincronizar manualmente';
					}
					
					if (showAlert) {
						alert('✅ Conexão estabelecida com sucesso!');
					}
					
					// Recarregar a página após 1 segundo para atualizar tudo
					setTimeout(() => {
						location.reload();
					}, 1000);
					
				} else {
					dot.className = 'connection-dot connection-disconnected';
					statusText.textContent = 'Desconectado';
					
					// Desabilitar botão de sincronização
					if (syncBtn) {
						syncBtn.disabled = true;
						syncBtn.title = 'Conexão remota indisponível';
					}
					
					if (showAlert) {
						alert('❌ Falha na conexão: ' + data.message);
					}
				}
			})
			.catch(error => {
				dot.className = 'connection-dot connection-disconnected';
				statusText.textContent = 'Erro ao testar';
				
				if (syncBtn) {
					syncBtn.disabled = true;
					syncBtn.title = 'Conexão remota indisponível';
				}
				
				console.error('Erro:', error);
				if (showAlert) {
					alert('Erro ao testar conexão: ' + error.message);
				}
			})
			.finally(() => {
				btn.disabled = false;
				btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Testar';
			});
	}

	// Função para mostrar modal de solução de problemas
	function showTroubleshoot() {
		const modal = new bootstrap.Modal(document.getElementById('troubleshootModal'));
		modal.show();
	}

	// Funções de teste específicas para o modal
	function runPingTest() {
		const resultsDiv = document.getElementById('testResults');
		resultsDiv.innerHTML = '<div class="text-info"><i class="bi bi-hourglass-split"></i> Executando teste de ping...</div>';
		
		fetch('api/test_connection.php?test=ping')
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					resultsDiv.innerHTML = `<div class="text-success">
						<i class="bi bi-check-circle"></i> Ping bem-sucedido!<br>
						<small>${data.details || ''}</small>
					</div>`;
				} else {
					resultsDiv.innerHTML = `<div class="text-danger">
						<i class="bi bi-x-circle"></i> Ping falhou<br>
						<small>${data.details || 'O computador pode estar desligado ou desconectado da rede'}</small>
					</div>`;
				}
			});
	}

	function runPortTest() {
		const resultsDiv = document.getElementById('testResults');
		resultsDiv.innerHTML = '<div class="text-info"><i class="bi bi-hourglass-split"></i> Testando porta SMB (445)...</div>';
		
		fetch('api/test_connection.php?test=port')
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					resultsDiv.innerHTML = `<div class="text-success">
						<i class="bi bi-check-circle"></i> Porta SMB acessível!<br>
						<small>${data.details || 'O serviço de compartilhamento está respondendo'}</small>
					</div>`;
				} else {
					resultsDiv.innerHTML = `<div class="text-danger">
						<i class="bi bi-x-circle"></i> Porta SMB não acessível<br>
						<small>${data.details || 'O compartilhamento de arquivos pode estar desativado'}</small>
					</div>`;
				}
			});
	}

	// Função de sincronização manual
	function manualSync() {
		const btn = document.getElementById('syncBtn');
		const originalHtml = btn.innerHTML;
		
		btn.disabled = true;
		btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sincronizando...';
		
		fetch('api/sync.php?action=manual')
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					alert('✅ Sincronização realizada com sucesso!');
					setTimeout(() => location.reload(), 1000);
				} else {
					alert('❌ Erro na sincronização: ' + (data.message || 'Erro desconhecido'));
					btn.innerHTML = originalHtml;
					btn.disabled = false;
				}
			})
			.catch(error => {
				alert('❌ Erro na sincronização: ' + error.message);
				btn.innerHTML = originalHtml;
				btn.disabled = false;
			});
	}

	// ============================
	// SISTEMA DE AUTO-REFRESH SIMPLIFICADO E FUNCIONAL
	// ============================

	// Mostrar aviso de refresh
	function showRefreshWarning() {
		if (isWarningActive) return;
		isWarningActive = true;
		
		const warningDiv = document.getElementById('refreshWarning');
		const countdownElement = document.getElementById('countdownTimer');
		
		// Mostrar o aviso
		warningDiv.style.display = 'block';
		
		// Iniciar contagem regressiva
		let countdown = WARNING_TIME;
		countdownElement.textContent = countdown;
		
		warningTimer = setInterval(() => {
			countdown--;
			countdownElement.textContent = countdown;
			
			if (countdown <= 0) {
				clearInterval(warningTimer);
				startRefresh();
			}
		}, 1000);
	}

	// Iniciar o refresh (com loading)
	function startRefresh() {
		isWarningActive = false;
		
		// Mostrar loading overlay
		const loadingOverlay = document.getElementById('loadingOverlay');
		loadingOverlay.style.display = 'flex';
		
		// Esconder aviso
		document.getElementById('refreshWarning').style.display = 'none';
		
		// Aguardar 1 segundo para mostrar o loading e então recarregar
		setTimeout(() => {
			location.reload();
		}, 1000);
	}

	// Atualizar agora (manualmente)
	function refreshNow() {
		clearInterval(warningTimer);
		startRefresh();
	}

	// Cancelar auto-refresh temporariamente
	function cancelAutoRefresh() {
		clearInterval(warningTimer);
		isWarningActive = false;
		document.getElementById('refreshWarning').style.display = 'none';
		
		// Cancelar por 5 minutos (300 segundos)
		cancelUntil = Date.now() + (5 * 60 * 1000);
		
		// Mostrar mensagem de confirmação
		alert('Auto-refresh cancelado por 5 minutos.');
		
		// Reiniciar timer principal após o cancelamento
		if (refreshTimer) {
			clearTimeout(refreshTimer);
		}
		scheduleNextRefresh();
	}

	// Dispensar aviso (sem cancelar o refresh)
	function dismissRefreshWarning() {
		clearInterval(warningTimer);
		isWarningActive = false;
		document.getElementById('refreshWarning').style.display = 'none';
		
		// Reiniciar o timer para o próximo ciclo completo
		if (refreshTimer) {
			clearTimeout(refreshTimer);
		}
		scheduleNextRefresh();
	}

	// Agendar o próximo refresh
	function scheduleNextRefresh() {
		// Limpar timers existentes
		if (refreshTimer) {
			clearTimeout(refreshTimer);
		}
		
		// Verificar se o refresh está cancelado temporariamente
		if (cancelUntil > Date.now()) {
			const remainingTime = Math.ceil((cancelUntil - Date.now()) / 1000);
			console.log(`Auto-refresh pausado por mais ${remainingTime} segundos`);
			
			// Agendar para verificar novamente quando o tempo de cancelamento acabar
			refreshTimer = setTimeout(() => {
				cancelUntil = 0;
				scheduleNextRefresh();
			}, cancelUntil - Date.now());
			
			return;
		}
		
		// Calcular o tempo até o próximo aviso
		// O aviso aparece WARNING_TIME segundos antes do refresh
		const timeUntilWarning = (REFRESH_INTERVAL - WARNING_TIME) * 1000;
		
		console.log(`Próximo aviso de refresh em ${timeUntilWarning/1000} segundos (refresh total em ${REFRESH_INTERVAL} segundos)`);
		
		// Agendar o próximo aviso de refresh
		refreshTimer = setTimeout(() => {
			showRefreshWarning();
			
			// Agendar o refresh completo caso o usuário não interaja
			setTimeout(() => {
				if (isWarningActive) {
					startRefresh();
				}
			}, WARNING_TIME * 1000);
			
		}, timeUntilWarning);
	}

	// Sistema de monitoramento de atividade do usuário
	let userActivityTimeout;
	let isUserActive = true;

	function resetUserActivity() {
		if (!isUserActive) {
			isUserActive = true;
			console.log('Usuário ativo - monitoramento reiniciado');
		}
		
		// Reiniciar o timeout de inatividade
		clearTimeout(userActivityTimeout);
		userActivityTimeout = setTimeout(() => {
			isUserActive = false;
			console.log('Usuário inativo por 30 segundos');
		}, 30000); // 30 segundos de inatividade
	}

	// Inicializar quando a página carregar
	document.addEventListener('DOMContentLoaded', function() {
		// Testar conexão após 2 segundos
		setTimeout(() => {
			testConnection();
		}, 2000);
		
		// Iniciar sistema de auto-refresh
		scheduleNextRefresh();
		
		// Monitorar atividade do usuário
		['click', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
			document.addEventListener(event, resetUserActivity);
		});
		
		// Inicializar monitor de inatividade
		resetUserActivity();
		
		// Log para depuração
		console.log('Sistema de auto-refresh iniciado. Intervalo:', REFRESH_INTERVAL, 'segundos');
		console.log('Aviso aparecerá', WARNING_TIME, 'segundos antes do refresh');
	});

	// Verificar conexão periodicamente para atualizar botão sync (mantém desabilitado se desconectado)
	setInterval(() => {
		fetch('api/test_connection.php?simple=true')
			.then(response => response.json())
			.then(data => {
				const syncBtn = document.getElementById('syncBtn');
				if (syncBtn) {
					if (data.success) {
						syncBtn.disabled = false;
						syncBtn.title = 'Sincronizar manualmente';
					} else {
						syncBtn.disabled = true;
						syncBtn.title = 'Conexão remota indisponível';
					}
				}
			});
	}, 60000); // A cada 1 minuto

	// Verificar conexão periodicamente (a cada 2 minutos)
	setInterval(() => {
		testConnection();
	}, 120000);
	</script>
</body>
</html>