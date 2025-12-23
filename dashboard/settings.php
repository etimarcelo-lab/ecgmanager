<?php
session_start();
require_once '../config/database.php';
require_once '../includes/Database.class.php';
require_once '../includes/Auth.class.php';
require_once '../includes/Utils.class.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = Database::getInstance();

// Buscar configurações
$settings = $db->query("SELECT * FROM system_settings ORDER BY category, setting_key");
$settingsByCategory = [];

while ($setting = $settings->fetch_assoc()) {
    $category = $setting['category'] ?: 'outros';
    if (!isset($settingsByCategory[$category])) {
        $settingsByCategory[$category] = [];
    }
    $settingsByCategory[$category][] = $setting;
}

// Processar atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = false;
    
    foreach ($_POST['settings'] as $key => $value) {
        $stmt = $db->prepare("
            UPDATE system_settings 
            SET setting_value = ?, updated_at = NOW()
            WHERE setting_key = ?
        ");
        $stmt->bind_param("ss", $value, $key);
        $stmt->execute();
    }
    
    $success = 'Configurações atualizadas com sucesso!';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - ECG Manager</title>
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
                        <i class="bi bi-gear"></i> Configurações do Sistema
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="submit" form="settingsForm" class="btn btn-primary">
                            <i class="bi bi-save"></i> Salvar
                        </button>
                    </div>
                </div>

                <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form method="POST" action="" id="settingsForm">
                    <?php foreach ($settingsByCategory as $category => $categorySettings): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="bi bi-folder"></i> 
                            <?php echo ucfirst($category); ?>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <?php foreach ($categorySettings as $setting): ?>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="setting_<?php echo $setting['setting_key']; ?>" 
                                               class="form-label">
                                            <?php echo $setting['setting_key']; ?>
                                            <?php if ($setting['description']): ?>
                                            <br><small class="text-muted"><?php echo $setting['description']; ?></small>
                                            <?php endif; ?>
                                        </label>
                                        
                                        <?php if ($setting['setting_type'] === 'boolean'): ?>
                                        <select class="form-control" 
                                                id="setting_<?php echo $setting['setting_key']; ?>" 
                                                name="settings[<?php echo $setting['setting_key']; ?>]">
                                            <option value="true" <?php echo $setting['setting_value'] === 'true' ? 'selected' : ''; ?>>Sim</option>
                                            <option value="false" <?php echo $setting['setting_value'] === 'false' ? 'selected' : ''; ?>>Não</option>
                                        </select>
                                        <?php elseif ($setting['setting_type'] === 'integer'): ?>
                                        <input type="number" class="form-control" 
                                               id="setting_<?php echo $setting['setting_key']; ?>" 
                                               name="settings[<?php echo $setting['setting_key']; ?>]" 
                                               value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                        <?php else: ?>
                                        <input type="text" class="form-control" 
                                               id="setting_<?php echo $setting['setting_key']; ?>" 
                                               name="settings[<?php echo $setting['setting_key']; ?>]" 
                                               value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </form>

                <!-- Informações do Sistema -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-info-circle"></i> Informações do Sistema
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Estatísticas</h6>
                                <table class="table table-sm">
                                    <?php
                                    $stats = [
                                        'Pacientes' => $db->query("SELECT COUNT(*) FROM patients")->fetch_row()[0],
                                        'Exames' => $db->query("SELECT COUNT(*) FROM exams")->fetch_row()[0],
                                        'Laudos' => $db->query("SELECT COUNT(*) FROM pdf_reports")->fetch_row()[0],
                                        'Médicos' => $db->query("SELECT COUNT(*) FROM doctors")->fetch_row()[0],
                                        'Usuários' => $db->query("SELECT COUNT(*) FROM users")->fetch_row()[0],
                                        'Logs' => $db->query("SELECT COUNT(*) FROM sync_logs")->fetch_row()[0]
                                    ];
                                    
                                    foreach ($stats as $label => $value):
                                    ?>
                                    <tr>
                                        <td><?php echo $label; ?></td>
                                        <td><strong><?php echo $value; ?></strong></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Status do Servidor</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td>PHP Version</td>
                                        <td><code><?php echo phpversion(); ?></code></td>
                                    </tr>
                                    <tr>
                                        <td>Server Software</td>
                                        <td><code><?php echo $_SERVER['SERVER_SOFTWARE']; ?></code></td>
                                    </tr>
                                    <tr>
                                        <td>Database</td>
                                        <td><code><?php echo $db->getConnection()->server_info; ?></code></td>
                                    </tr>
                                    <tr>
                                        <td>Memory Limit</td>
                                        <td><code><?php echo ini_get('memory_limit'); ?></code></td>
                                    </tr>
                                    <tr>
                                        <td>Max Upload</td>
                                        <td><code><?php echo ini_get('upload_max_filesize'); ?></code></td>
                                    </tr>
                                    <tr>
                                        <td>Server Time</td>
                                        <td><code><?php echo date('Y-m-d H:i:s'); ?></code></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>