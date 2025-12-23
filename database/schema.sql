-- ===========================================
-- BANCO DE DADOS ECG MANAGER
-- ===========================================

DROP DATABASE IF EXISTS ecg_manager;
CREATE DATABASE ecg_manager 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE ecg_manager;

-- ===========================================
-- TABELA: users
-- ===========================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    role ENUM('admin', 'medico', 'enfermagem') NOT NULL DEFAULT 'enfermagem',
    crm VARCHAR(20),
    specialty VARCHAR(100),
    active BOOLEAN DEFAULT TRUE,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role)
);

-- ===========================================
-- TABELA: patients
-- ===========================================
CREATE TABLE patients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id VARCHAR(50),
    full_name VARCHAR(100) NOT NULL,
    birth_date DATE NOT NULL,
    gender ENUM('Masculino', 'Feminino', 'Outro') NOT NULL,
    clinical_record VARCHAR(50),
    rg VARCHAR(20),
    cpf VARCHAR(14),
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(2),
    zip_code VARCHAR(10),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_full_name (full_name),
    INDEX idx_birth_date (birth_date),
    INDEX idx_cpf (cpf),
    INDEX idx_clinical_record (clinical_record),
    UNIQUE KEY unique_patient (full_name, birth_date, cpf)
);

-- ===========================================
-- TABELA: doctors
-- ===========================================
CREATE TABLE doctors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    crm VARCHAR(20) NOT NULL UNIQUE,
    specialty VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_crm (crm)
);

-- ===========================================
-- TABELA: exams
-- ===========================================
CREATE TABLE exams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exam_number VARCHAR(50) NOT NULL,
    patient_id INT NOT NULL,
    exam_date DATE NOT NULL,
    exam_time TIME NOT NULL,
    responsible_doctor_id INT,
    requesting_doctor_id INT,
    heart_rate INT,
    blood_pressure VARCHAR(20),
    weight DECIMAL(5,2),
    height DECIMAL(5,2),
    observations TEXT,
    diagnosis TEXT,
    wxml_file_path VARCHAR(500),
    wxml_processed BOOLEAN DEFAULT FALSE,
    pdf_file_path VARCHAR(500),
    pdf_processed BOOLEAN DEFAULT FALSE,
    status ENUM('agendado', 'realizado', 'processando', 'finalizado', 'cancelado') DEFAULT 'realizado',
    priority ENUM('normal', 'urgente', 'emergencia') DEFAULT 'normal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (responsible_doctor_id) REFERENCES doctors(id),
    FOREIGN KEY (requesting_doctor_id) REFERENCES doctors(id),
    INDEX idx_exam_number (exam_number),
    INDEX idx_exam_date (exam_date),
    INDEX idx_patient_id (patient_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    UNIQUE KEY unique_exam_number (exam_number)
);

-- ===========================================
-- TABELA: pdf_reports
-- ===========================================
CREATE TABLE pdf_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exam_id INT NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    report_date DATE NOT NULL,
    report_time TIME NOT NULL,
    findings TEXT,
    conclusion TEXT,
    recommendations TEXT,
    medications TEXT,
    next_appointment DATE,
    uploaded_by INT,
    verified BOOLEAN DEFAULT FALSE,
    verified_by INT,
    verified_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id),
    FOREIGN KEY (verified_by) REFERENCES users(id),
    INDEX idx_exam_id (exam_id),
    INDEX idx_report_date (report_date),
    INDEX idx_verified (verified)
);

-- ===========================================
-- TABELA: sync_logs
-- ===========================================
CREATE TABLE sync_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sync_type ENUM('wxml', 'pdf', 'file_copy', 'manual', 'api') NOT NULL,
    filename VARCHAR(255),
    status ENUM('success', 'error', 'warning', 'skipped') NOT NULL,
    message TEXT,
    records_processed INT DEFAULT 0,
    processing_time DECIMAL(10,4),
    ip_address VARCHAR(45),
    user_agent TEXT,
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sync_type (sync_type),
    INDEX idx_status (status),
    INDEX idx_processed_at (processed_at)
);

-- ===========================================
-- TABELA: audit_logs
-- ===========================================
CREATE TABLE audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_action (action),
    INDEX idx_table_name (table_name),
    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id)
);

-- ===========================================
-- TABELA: system_settings
-- ===========================================
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'boolean', 'json', 'array') DEFAULT 'string',
    category VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key),
    INDEX idx_category (category)
);

-- ===========================================
-- INSERIR DADOS INICIAIS
-- ===========================================

-- Usuários padrão
INSERT INTO users (username, password, full_name, role, email) VALUES
('admin', 'admin', 'Administrador do Sistema', 'admin', 'admin@hospital.com'),
('medico', 'medico', 'Dr. João Silva', 'medico', 'joao.silva@hospital.com'),
('enfermagem', 'enfermagem', 'Enfermeira Maria Santos', 'enfermagem', 'maria.santos@hospital.com');

-- Médicos exemplo
INSERT INTO doctors (name, crm, specialty) VALUES
('Dr. Carlos Mendes', 'CRM-SP 12345', 'Cardiologia'),
('Dra. Ana Paula Oliveira', 'CRM-RS 67890', 'Cardiologia'),
('Dr. Roberto Almeida', 'CRM-MG 54321', 'Clínico Geral');

-- Configurações do sistema
INSERT INTO system_settings (setting_key, setting_value, setting_type, category, description) VALUES
('system_name', 'ECG Manager', 'string', 'general', 'Nome do sistema'),
('hospital_name', 'Hospital Cardiológico', 'string', 'general', 'Nome do hospital'),
('sync_interval', '300', 'integer', 'sync', 'Intervalo de sincronização em segundos'),
('pdf_storage_path', '/var/www/html/ecg-manager/uploads/pdf/', 'string', 'storage', 'Pasta para armazenar PDFs'),
('max_file_size', '10485760', 'integer', 'upload', 'Tamanho máximo do arquivo (10MB)'),
('allowed_file_types', 'pdf,wxml', 'string', 'upload', 'Tipos de arquivo permitidos'),
('report_expiry_days', '365', 'integer', 'reports', 'Dias para expiração dos laudos'),
('auto_sync', 'true', 'boolean', 'sync', 'Sincronização automática ativada'),
('notify_new_exams', 'true', 'boolean', 'notifications', 'Notificar novos exames'),
('backup_enabled', 'true', 'boolean', 'backup', 'Backup automático ativado');

-- ===========================================
-- TRIGGERS PARA AUDITORIA
-- ===========================================

DELIMITER $$

-- Trigger para pacientes
CREATE TRIGGER patients_after_update
AFTER UPDATE ON patients
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values)
    VALUES (
        @current_user_id,
        'UPDATE',
        'patients',
        NEW.id,
        JSON_OBJECT(
            'full_name', OLD.full_name,
            'birth_date', OLD.birth_date,
            'cpf', OLD.cpf,
            'clinical_record', OLD.clinical_record
        ),
        JSON_OBJECT(
            'full_name', NEW.full_name,
            'birth_date', NEW.birth_date,
            'cpf', NEW.cpf,
            'clinical_record', NEW.clinical_record
        )
    );
END$$

-- Trigger para exames
CREATE TRIGGER exams_after_insert
AFTER INSERT ON exams
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values)
    VALUES (
        @current_user_id,
        'INSERT',
        'exams',
        NEW.id,
        JSON_OBJECT(
            'exam_number', NEW.exam_number,
            'patient_id', NEW.patient_id,
            'exam_date', NEW.exam_date,
            'status', NEW.status
        )
    );
END$$

DELIMITER ;

-- ===========================================
-- VIEWS ÚTEIS
-- ===========================================

CREATE VIEW vw_exams_with_details AS
SELECT 
    e.id,
    e.exam_number,
    e.exam_date,
    e.exam_time,
    e.heart_rate,
    e.status,
    e.priority,
    e.created_at,
    p.full_name as patient_name,
    p.birth_date,
    p.gender,
    p.cpf,
    d1.name as responsible_doctor,
    d2.name as requesting_doctor,
    pr.stored_filename as pdf_filename,
    pr.report_date,
    CASE 
        WHEN e.pdf_processed = TRUE THEN 'Com Laudo'
        ELSE 'Pendente'
    END as report_status
FROM exams e
LEFT JOIN patients p ON e.patient_id = p.id
LEFT JOIN doctors d1 ON e.responsible_doctor_id = d1.id
LEFT JOIN doctors d2 ON e.requesting_doctor_id = d2.id
LEFT JOIN pdf_reports pr ON e.id = pr.exam_id;

CREATE VIEW vw_daily_stats AS
SELECT 
    DATE(e.exam_date) as date,
    COUNT(*) as total_exams,
    SUM(CASE WHEN e.pdf_processed = TRUE THEN 1 ELSE 0 END) as exams_with_report,
    SUM(CASE WHEN e.pdf_processed = FALSE THEN 1 ELSE 0 END) as exams_pending,
    ROUND((SUM(CASE WHEN e.pdf_processed = TRUE THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as coverage_rate
FROM exams e
GROUP BY DATE(e.exam_date)
ORDER BY date DESC;

CREATE VIEW vw_patient_exam_history AS
SELECT 
    p.id as patient_id,
    p.full_name,
    p.birth_date,
    COUNT(e.id) as total_exams,
    MAX(e.exam_date) as last_exam_date,
    MIN(e.exam_date) as first_exam_date,
    SUM(CASE WHEN e.pdf_processed = TRUE THEN 1 ELSE 0 END) as completed_exams
FROM patients p
LEFT JOIN exams e ON p.id = e.patient_id
GROUP BY p.id, p.full_name, p.birth_date;

-- ===========================================
-- ÍNDICES ADICIONAIS
-- ===========================================

CREATE INDEX idx_exams_patient_date ON exams(patient_id, exam_date DESC);
CREATE INDEX idx_pdf_reports_exam_date ON pdf_reports(exam_id, report_date DESC);
CREATE INDEX idx_sync_logs_date_type ON sync_logs(processed_at DESC, sync_type);
CREATE INDEX idx_audit_logs_user_date ON audit_logs(user_id, created_at DESC);

-- ===========================================
-- PROCEDURES
-- ===========================================

DELIMITER $$

CREATE PROCEDURE sp_cleanup_old_logs(IN days_to_keep INT)
BEGIN
    DELETE FROM sync_logs WHERE processed_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
END$$

CREATE PROCEDURE sp_get_patient_exams(IN patient_id INT)
BEGIN
    SELECT 
        e.*,
        d1.name as resp_doctor,
        d2.name as req_doctor,
        pr.report_date,
        pr.stored_filename
    FROM exams e
    LEFT JOIN doctors d1 ON e.responsible_doctor_id = d1.id
    LEFT JOIN doctors d2 ON e.requesting_doctor_id = d2.id
    LEFT JOIN pdf_reports pr ON e.id = pr.exam_id
    WHERE e.patient_id = patient_id
    ORDER BY e.exam_date DESC, e.exam_time DESC;
END$$

CREATE PROCEDURE sp_generate_monthly_report(IN year_month VARCHAR(7))
BEGIN
    SELECT 
        DATE(e.exam_date) as exam_day,
        COUNT(*) as total_exams,
        COUNT(pr.id) as exams_with_report,
        ROUND((COUNT(pr.id) / COUNT(*)) * 100, 2) as daily_coverage
    FROM exams e
    LEFT JOIN pdf_reports pr ON e.id = pr.exam_id
    WHERE DATE_FORMAT(e.exam_date, '%Y-%m') = year_month
    GROUP BY DATE(e.exam_date)
    ORDER BY exam_day;
END$$

DELIMITER ;

-- ===========================================
-- EVENTOS AGENDADOS
-- ===========================================

DELIMITER $$

CREATE EVENT IF NOT EXISTS ev_daily_backup
ON SCHEDULE EVERY 1 DAY
STARTS CONCAT(CURDATE() + INTERVAL 1 DAY, ' 02:00:00')
DO
BEGIN
    -- Backup lógico das tabelas importantes
    SET @backup_file = CONCAT('/var/backups/ecg_manager_', DATE_FORMAT(NOW(), '%Y%m%d_%H%i%s'), '.sql');
    
    -- Executar mysqldump via sistema (seria configurado externamente)
    -- INSERT INTO sync_logs (sync_type, status, message) 
    -- VALUES ('backup', 'success', 'Backup diário agendado');
END$$

CREATE EVENT IF NOT EXISTS ev_cleanup_temp_files
ON SCHEDULE EVERY 1 DAY
STARTS CONCAT(CURDATE() + INTERVAL 1 DAY, ' 03:00:00')
DO
BEGIN
    -- Limpar logs antigos (mantém 90 dias)
    CALL sp_cleanup_old_logs(90);
    
    -- Log da limpeza
    INSERT INTO sync_logs (sync_type, status, message) 
    VALUES ('cleanup', 'success', 'Limpeza de logs antigos realizada');
END$$

DELIMITER ;