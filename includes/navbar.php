<?php
// navbar.php - Componente de navegação CORRIGIDO

// Definir a base URL do projeto
$base_url = '/ecgmanager';

// Se estiver em subdiretório dashboard, ajustar
$current_dir = dirname($_SERVER['PHP_SELF']);
if (strpos($current_dir, 'dashboard') !== false) {
    $base_url = '..';
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo $base_url; ?>/index.php">
            <i class="bi bi-heart-pulse"></i> ECG Manager
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/index.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/dashboard/patients.php">
                        <i class="bi bi-people"></i> Pacientes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/dashboard/exams.php">
                        <i class="bi bi-clipboard-data"></i> Exames
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/dashboard/reports.php">
                        <i class="bi bi-graph-up"></i> Relatórios
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/dashboard/cleanup.php">
                        <i class="bi bi-trash-fill"></i> Excluir Todos Exames Pendentes
                    </a>
		 </li>
		 <li class="nav-item">
		     <a class="nav-link" href="<?php echo $base_url; ?>/dashboard/cleanup_patients.php">
		        <i class="bi bi-person-x"></i> Excluir Pacientes sem Exame e Laudo
		    </a>
		</li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" 
                       role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> 
                        <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Usuário'); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <span class="dropdown-item-text">
                                <small><?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?></small>
                            </span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <li>
                            <a class="dropdown-item" href="<?php echo $base_url; ?>/dashboard/settings.php">
                                <i class="bi bi-gear"></i> Configurações
                            </a>
                        </li>
                        <?php endif; ?>
                        <li>
                            <a class="dropdown-item" href="<?php echo $base_url; ?>/logout.php">
                                <i class="bi bi-box-arrow-right"></i> Sair
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
