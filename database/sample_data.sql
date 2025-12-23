-- Dados de exemplo para o sistema ECG Manager

USE ecg_manager;

-- Inserir mais médicos
INSERT INTO doctors (name, crm, specialty, active) VALUES
('Dr. João Silva', 'CRM-SP 12345', 'Cardiologia', TRUE),
('Dra. Maria Santos', 'CRM-RJ 67890', 'Cardiologia Pediátrica', TRUE),
('Dr. Pedro Oliveira', 'CRM-MG 54321', 'Eletrofisiologia', TRUE),
('Dra. Ana Costa', 'CRM-RS 98765', 'Arritmologia', TRUE),
('Dr. Carlos Mendes', 'CRM-PR 13579', 'Clínica Médica', TRUE);

-- Inserir pacientes de exemplo
INSERT INTO patients (
    patient_id, full_name, birth_date, gender, 
    clinical_record, rg, cpf, email, phone,
    address, city, state, zip_code
) VALUES
('P001', 'José da Silva', '1950-05-15', 'Masculino', 
 'RC001', '1234567', '11122233344', 'jose.silva@email.com', '(11) 99999-9999',
 'Rua das Flores, 123', 'São Paulo', 'SP', '01234-567'),

('P002', 'Maria Oliveira', '1962-08-22', 'Feminino', 
 'RC002', '7654321', '22233344455', 'maria.oliveira@email.com', '(11) 98888-8888',
 'Av. Paulista, 1000', 'São Paulo', 'SP', '01310-100'),

('P003', 'Carlos Santos', '1975-12-03', 'Masculino', 
 'RC003', '9876543', '33344455566', 'carlos.santos@email.com', '(21) 97777-7777',
 'Rua Copacabana, 500', 'Rio de Janeiro', 'RJ', '22050-000'),

('P004', 'Ana Costa', '1980-03-30', 'Feminino', 
 'RC004', '4567890', '44455566677', 'ana.costa@email.com', '(31) 96666-6666',
 'Av. Afonso Pena, 2000', 'Belo Horizonte', 'MG', '30130-000'),

('P005', 'Roberto Almeida', '1945-11-18', 'Masculino', 
 'RC005', '2345678', '55566677788', 'roberto.almeida@email.com', '(41) 95555-5555',
 'Rua XV de Novembro, 150', 'Curitiba', 'PR', '80020-310');

-- Inserir exames de exemplo
INSERT INTO exams (
    exam_number, patient_id, exam_date, exam_time,
    responsible_doctor_id, requesting_doctor_id,
    heart_rate, blood_pressure, weight, height,
    observations, diagnosis, status, priority,
    wxml_processed, pdf_processed
) VALUES
('ECG20231215001', 1, '2023-12-15', '09:30:00',
 1, 2, 75, '120/80', 78.5, 1.75,
 'Paciente relatou tonturas ocasionais', 'Arritmia sinusal', 'finalizado', 'normal',
 TRUE, TRUE),

('ECG20231215002', 2, '2023-12-15', '10:15:00',
 2, 3, 82, '130/85', 65.2, 1.62,
 'Exame de rotina', 'Ritmo sinusal normal', 'finalizado', 'normal',
 TRUE, TRUE),

('ECG20231216001', 3, '2023-12-16', '14:00:00',
 3, 1, 68, '118/78', 85.0, 1.80,
 'Paciente hipertenso em acompanhamento', 'Hipertrofia ventricular esquerda', 'finalizado', 'urgente',
 TRUE, TRUE),

('ECG20231216002', 4, '2023-12-16', '15:30:00',
 4, 2, 95, '140/90', 70.5, 1.68,
 'Queixa de palpitações', 'Taquicardia sinusal', 'finalizado', 'urgente',
 TRUE, FALSE),

('ECG20231217001', 5, '2023-12-17', '08:45:00',
 5, 4, 72, '125/82', 90.0, 1.78,
 'Pré-operatório', 'Bloqueio de ramo direito incompleto', 'realizado', 'normal',
 TRUE, FALSE),

('ECG20231217002', 1, '2023-12-17', '11:20:00',
 1, 5, 88, '135/88', 79.0, 1.75,
 'Retorno após medicação', 'Melhora do padrão arrítmico', 'finalizado', 'normal',
 TRUE, TRUE),

('ECG20231218001', 2, '2023-12-18', '13:15:00',
 2, 1, 65, '115/75', 64.8, 1.62,
 'Controle anual', 'Ritmo sinusal normal', 'realizado', 'normal',
 TRUE, FALSE),

('ECG20231218002', 3, '2023-12-18', '16:00:00',
 3, 2, 110, '150/95', 84.5, 1.80,
 'Sintomas de falta de ar', 'Fibrilação atrial', 'finalizado', 'emergencia',
 TRUE, TRUE);

-- Inserir laudos PDF de exemplo
INSERT INTO pdf_reports (
    exam_id, original_filename, stored_filename, file_path,
    file_size, report_date, report_time, findings, conclusion,
    uploaded_by, verified
) VALUES
(1, 'MMD#JOSÉSILVA##151220230930#DRJOÃOSILVA#E.PDF', 
 'laudo_ecg20231215001.pdf', '/var/www/html/ecg-manager/uploads/pdf/laudo_ecg20231215001.pdf',
 1024576, '2023-12-15', '10:00:00',
 'Frequência cardíaca: 75 bpm (variação 60-100)
Ritmo: Arritmia sinusal
Onda P: presente e positiva
Intervalo PR: 0.16 s
Complexo QRS: 0.08 s
Eixo elétrico: +60 graus',
 'Arritmia sinusal benigna. Recomenda-se controle anual.',
 1, TRUE),

(2, 'MMD#MARIAOLIVEIRA##151220231015#DRAMARIASANTOS#E.PDF',
 'laudo_ecg20231215002.pdf', '/var/www/html/ecg-manager/uploads/pdf/laudo_ecg20231215002.pdf',
 987654, '2023-12-15', '11:30:00',
 'Frequência cardíaca: 82 bpm
Ritmo: Sinusal regular
Onda P: normal
Intervalo PR: 0.14 s
Complexo QRS: 0.09 s
Segmento ST: isoelétrico
Onda T: positiva',
 'Eletrocardiograma dentro dos limites da normalidade. Nenhuma alteração significativa.',
 1, TRUE),

(3, 'MMD#CARLOSSANTOS##161220231400#DRPEDROOLIVEIRA#E.PDF',
 'laudo_ecg20231216001.pdf', '/var/www/html/ecg-manager/uploads/pdf/laudo_ecg20231216001.pdf',
 1123456, '2023-12-16', '15:00:00',
 'Frequência cardíaca: 68 bpm
Ritmo: Sinusal
Sobrecarga atrial esquerda
Hipertrofia ventricular esquerda (Sokolow-Lyon: 45 mm)
Onda T assimétrica em V5-V6',
 'Hipertrofia ventricular esquerda compatível com hipertensão arterial. 
Recomenda-se controle rigoroso da pressão arterial e ecocardiograma.',
 1, TRUE),

(6, 'MMD#JOSÉSILVA##171220231120#DRJOÃOSILVA#E.PDF',
 'laudo_ecg20231217002.pdf', '/var/www/html/ecg-manager/uploads/pdf/laudo_ecg20231217002.pdf',
 876543, '2023-12-17', '12:00:00',
 'Frequência cardíaca: 88 bpm
Ritmo: Sinusal com ocasionais extrassístoles supraventriculares
Melhora do padrão arrítmico prévio
Onda P: normal
Complexo QRS: 0.08 s',
 'Melhora significativa do padrão arrítmico com a medicação.
Manter dose atual e retorno em 3 meses.',
 1, TRUE),

(8, 'MMD#CARLOSSANTOS##181220231600#DRPEDROOLIVEIRA#E.PDF',
 'laudo_ecg20231218002.pdf', '/var/www/html/ecg-manager/uploads/pdf/laudo_ecg20231218002.pdf',
 1456789, '2023-12-18', '17:30:00',
 'Frequência cardíaca ventricular: 110 bpm
Ritmo: Fibrilação atrial com resposta ventricular rápida
Ausência de ondas P organizadas
Resposta ventricular irregular
Complexo QRS: 0.10 s',
 'Fibrilação atrial com alta resposta ventricular.
Recomenda-se cardioversão elétrica e início de anticoagulação.
Encaminhar para serviço de emergência.',
 1, TRUE);

-- Inserir logs de sincronização de exemplo
INSERT INTO sync_logs (sync_type, filename, status, message, records_processed, processing_time) VALUES
('file_copy', 'MMD#JOSÉSILVA##151220230930#DRJOÃOSILVA#E.PDF', 'success', 'Arquivo PDF copiado com sucesso', 1, 0.125),
('pdf', 'MMD#JOSÉSILVA##151220230930#DRJOÃOSILVA#E.PDF', 'success', 'PDF processado e vinculado ao exame ECG20231215001', 1, 1.234),
('file_copy', 'ECG20231215001.WXML', 'success', 'Arquivo WXML copiado com sucesso', 1, 0.098),
('wxml', 'ECG20231215001.WXML', 'success', 'WXML processado - Paciente: José da Silva, Exame: ECG20231215001', 1, 0.567),
('file_copy', 'MMD#MARIAOLIVEIRA##151220231015#DRAMARIASANTOS#E.PDF', 'success', 'Arquivo PDF copiado com sucesso', 1, 0.134),
('pdf', 'MMD#MARIAOLIVEIRA##151220231015#DRAMARIASANTOS#E.PDF', 'success', 'PDF processado e vinculado ao exame ECG20231215002', 1, 1.098),
('wxml', 'ECG20231215002.WXML', 'warning', 'Médico solicitante não encontrado no CRM informado', 1, 0.432),
('file_copy', 'arquivo_corrompido.PDF', 'error', 'Falha ao copiar arquivo: Permissão negada', 0, 0.012),
('manual', NULL, 'success', 'Sincronização manual realizada pelo usuário admin', 5, 3.456);

-- Atualizar exames com caminhos de arquivos
UPDATE exams SET 
    wxml_file_path = '/var/www/html/ecg-manager/uploads/wxml/ecg20231215001.wxml',
    pdf_file_path = '/var/www/html/ecg-manager/uploads/pdf/laudo_ecg20231215001.pdf'
WHERE id = 1;

UPDATE exams SET 
    wxml_file_path = '/var/www/html/ecg-manager/uploads/wxml/ecg20231215002.wxml',
    pdf_file_path = '/var/www/html/ecg-manager/uploads/pdf/laudo_ecg20231215002.pdf'
WHERE id = 2;

UPDATE exams SET 
    wxml_file_path = '/var/www/html/ecg-manager/uploads/wxml/ecg20231216001.wxml',
    pdf_file_path = '/var/www/html/ecg-manager/uploads/pdf/laudo_ecg20231216001.pdf'
WHERE id = 3;

UPDATE exams SET 
    wxml_file_path = '/var/www/html/ecg-manager/uploads/wxml/ecg20231216002.wxml'
WHERE id = 4;

UPDATE exams SET 
    wxml_file_path = '/var/www/html/ecg-manager/uploads/wxml/ecg20231217001.wxml'
WHERE id = 5;

UPDATE exams SET 
    wxml_file_path = '/var/www/html/ecg-manager/uploads/wxml/ecg20231217002.wxml',
    pdf_file_path = '/var/www/html/ecg-manager/uploads/pdf/laudo_ecg20231217002.pdf'
WHERE id = 6;

UPDATE exams SET 
    wxml_file_path = '/var/www/html/ecg-manager/uploads/wxml/ecg20231218001.wxml'
WHERE id = 7;

UPDATE exams SET 
    wxml_file_path = '/var/www/html/ecg-manager/uploads/wxml/ecg20231218002.wxml',
    pdf_file_path = '/var/www/html/ecg-manager/uploads/pdf/laudo_ecg20231218002.pdf'
WHERE id = 8;

-- Inserir logs de auditoria de exemplo
INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values, ip_address) VALUES
(1, 'login', 'users', 1, '{"username": "admin", "ip": "192.168.1.100"}', '192.168.1.100'),
(1, 'INSERT', 'patients', 1, '{"full_name": "José da Silva", "cpf": "11122233344"}', '192.168.1.100'),
(1, 'UPDATE', 'exams', 1, '{"status": "finalizado", "pdf_processed": true}', '192.168.1.100'),
(2, 'login', 'users', 2, '{"username": "medico", "ip": "192.168.1.101"}', '192.168.1.101'),
(2, 'VIEW', 'pdf_reports', 1, '{"exam_id": 1, "action": "view_pdf"}', '192.168.1.101'),
(3, 'login', 'users', 3, '{"username": "enfermagem", "ip": "192.168.1.102"}', '192.168.1.102'),
(3, 'SEARCH', 'patients', NULL, '{"search_term": "silva", "results": 2}', '192.168.1.102');

-- Mensagens informativas
SELECT 'Dados de exemplo inseridos com sucesso!' as message;
SELECT CONCAT('Total de pacientes: ', COUNT(*)) as info FROM patients;
SELECT CONCAT('Total de exames: ', COUNT(*)) as info FROM exams;
SELECT CONCAT('Total de laudos: ', COUNT(*)) as info FROM pdf_reports;
SELECT CONCAT('Total de logs: ', COUNT(*)) as info FROM sync_logs;