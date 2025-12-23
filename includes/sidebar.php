<?php
// sidebar.php - Componente de barra lateral para admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    return;
}

// Incluir a verificação de conexão
require_once __DIR__ . '/ConnectionChecker.php';
$connectionChecker = new ConnectionChecker();
$connectionStatus = $connectionChecker->getStatus();
$connectionMessage = $connectionChecker->getMessage();

// Determinar a página atual para highlights do menu
$currentPage = basename($_SERVER['PHP_SELF']);

// Determinar o caminho base dependendo de onde estamos
// Se estamos no index.php (root), links apontam para dashboard/
// Se estamos em uma página do dashboard, links são relativos
$isDashboardPage = strpos($_SERVER['PHP_SELF'], 'dashboard/') !== false;
$basePath = $isDashboardPage ? '' : 'dashboard/';
?>
<div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <!-- Menu Principal (primeiro) -->
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage === 'index.php' ? 'active' : ''; ?>" 
                   href="<?php echo $isDashboardPage ? '../index.php' : 'index.php'; ?>">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($currentPage, ['patients.php', 'patient_detail.php', 'patient_edit.php']) ? 'active' : ''; ?>" 
                   href="<?php echo $basePath; ?>patients.php">
                    <i class="bi bi-people"></i> Pacientes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($currentPage, ['exams.php', 'exam_detail.php', 'exam_edit.php']) ? 'active' : ''; ?>" 
                   href="<?php echo $basePath; ?>exams.php">
                    <i class="bi bi-clipboard-data"></i> Exames
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>" 
                   href="<?php echo $basePath; ?>reports.php">
                    <i class="bi bi-file-text"></i> Relatórios
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage === 'logs.php' ? 'active' : ''; ?>" 
                   href="<?php echo $basePath; ?>logs.php">
                    <i class="bi bi-list-check"></i> Logs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage === 'users.php' ? 'active' : ''; ?>" 
                   href="<?php echo $basePath; ?>users.php">
                    <i class="bi bi-person-gear"></i> Usuários
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>" 
                   href="<?php echo $basePath; ?>settings.php">
                    <i class="bi bi-gear"></i> Configurações
                </a>
            </li>
        </ul>
        
        <hr class="my-3">
        
        <hr class="my-3">
        
        <!-- Hora do servidor -->
        <hr class="my-3">
        
        <div class="px-3">
            <small class="text-muted">
                ECG Manager v2.0.0 <span id="serverTime"><?php echo date('H:i'); ?></span>
            </small>
        </div>
    </div>
</div>

<style>
/* Estilos específicos para a sidebar */
.connection-status-sidebar .card {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 1px solid #dee2e6;
}

.connection-dot-sidebar {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}

.status-connected {
    background-color: #28a745;
    box-shadow: 0 0 8px rgba(40, 167, 69, 0.6);
    animation: pulse 2s infinite;
}

.status-disconnected {
    background-color: #dc3545;
    box-shadow: 0 0 8px rgba(220, 53, 69, 0.6);
}

.status-checking {
    background-color: #ffc107;
    animation: blink 1.5s infinite;
}

@keyframes pulse {
    0%, 100% {
        box-shadow: 0 0 8px rgba(40, 167, 69, 0.6);
    }
    50% {
        box-shadow: 0 0 12px rgba(40, 167, 69, 0.9);
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

.connection-status-sidebar .alert {
    padding: 4px 8px;
    font-size: 0.75rem;
    margin-bottom: 0;
}

/* Estilo para o menu ativo */
.nav-link.active {
    background-color: #0d6efd;
    color: white !important;
    border-radius: 0.25rem;
}

.nav-link:hover:not(.active) {
    background-color: #e9ecef;
}

.sidebar-heading {
    font-size: 0.75rem;
    font-weight: 600;
    color: #6c757d;
    margin-bottom: 0.5rem;
}
</style>

<script>
// Função para testar conexão
function testConnection() {
    const btn = document.getElementById('sidebarTestBtn');
    const originalHtml = btn.innerHTML;
    const messageDiv = document.getElementById('sidebarConnectionMessage');
    const dot = document.querySelector('.connection-dot-sidebar');
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    
    // Alterar status
    dot.className = 'connection-dot-sidebar me-2 status-checking';
    messageDiv.textContent = 'Testando conexão...';
    
    // Determinar o caminho correto para a API
    const apiPath = window.location.pathname.includes('dashboard/') ? '../api/' : 'api/';
    
    fetch(apiPath + 'test_connection.php?log=true')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                dot.className = 'connection-dot-sidebar me-2 status-connected';
                messageDiv.textContent = data.message || 'Conectado ao computador remoto';
            } else {
                dot.className = 'connection-dot-sidebar me-2 status-disconnected';
                messageDiv.textContent = data.message || 'Sem conexão com computador remoto';
            }
            
            // Recarregar a página para atualizar o dashboard
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        })
        .catch(error => {
            dot.className = 'connection-dot-sidebar me-2 status-disconnected';
            messageDiv.textContent = 'Erro ao testar conexão';
            console.error('Erro:', error);
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i>';
        });
}

// Função para mostrar modal de solução de problemas
function showTroubleshoot() {
    // Se estiver no index.php principal, use o modal do index
    if (window.parent && window.parent.showTroubleshoot) {
        window.parent.showTroubleshoot();
    } else {
        // Tenta encontrar o modal na página atual
        const modalElement = document.getElementById('troubleshootModal');
        if (modalElement) {
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        } else {
            alert('Modal de solução de problemas não encontrado. Acesse o Dashboard principal.');
        }
    }
}

// Funções de sincronização
function manualSync() {
    // Determinar o caminho correto para a API
    const apiPath = window.location.pathname.includes('dashboard/') ? '../api/' : 'api/';
    
    fetch(apiPath + 'sync.php?action=manual')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Sincronização iniciada!');
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                alert('Erro: ' + data.message);
            }
        });
}

function checkSyncStatus() {
    // Determinar o caminho correto para a API
    const apiPath = window.location.pathname.includes('dashboard/') ? '../api/' : 'api/';
    
    fetch(apiPath + 'sync.php?action=status')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`Status da Sincronização:
                Pacientes: ${data.data.total_patients}
                Exames: ${data.data.total_exams}
                Pendentes: ${data.data.pending_reports}
                Sinc. Hoje: ${data.data.syncs_today}`);
            }
        });
}

// Atualizar hora do servidor
function updateServerTime() {
    const now = new Date();
    document.getElementById('serverTime').textContent = 
        now.getHours().toString().padStart(2, '0') + ':' + 
        now.getMinutes().toString().padStart(2, '0');
}

setInterval(updateServerTime, 60000);

// Verificar conexão periodicamente (a cada 2 minutos)
setInterval(() => {
    testConnection();
}, 120000);

// Testar conexão ao carregar a sidebar
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        testConnection();
    }, 1000);
});
</script>