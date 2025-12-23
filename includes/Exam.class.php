<?php
class Exam {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getAll($filters = [], $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        $where = [];
        $params = [];
        $types = '';
        
        // Filtros
        if (!empty($filters['search'])) {
            $where[] = "(p.full_name LIKE ? OR e.exam_number LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params = [$searchTerm, $searchTerm];
            $types = 'ss';
        }
        
        if (!empty($filters['start_date'])) {
            $where[] = "e.exam_date >= ?";
            $params[] = $filters['start_date'];
            $types .= 's';
        }
        
        if (!empty($filters['end_date'])) {
            $where[] = "e.exam_date <= ?";
            $params[] = $filters['end_date'];
            $types .= 's';
        }
        
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'with_report') {
                $where[] = "e.pdf_processed = TRUE";
            } elseif ($filters['status'] === 'without_report') {
                $where[] = "e.pdf_processed = FALSE";
            }
        }
        
        if (!empty($filters['patient_id'])) {
            $where[] = "e.patient_id = ?";
            $params[] = $filters['patient_id'];
            $types .= 'i';
        }
        
        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
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
        
        $stmt = $this->db->prepare($query);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt->get_result();
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT e.*, p.full_name as patient_name, p.birth_date, p.gender,
                   p.cpf, p.clinical_record,
                   d1.name as resp_doctor, d1.crm as resp_crm,
                   d2.name as req_doctor, d2.crm as req_crm,
                   pr.*
            FROM exams e
            LEFT JOIN patients p ON e.patient_id = p.id
            LEFT JOIN doctors d1 ON e.responsible_doctor_id = d1.id
            LEFT JOIN doctors d2 ON e.requesting_doctor_id = d2.id
            LEFT JOIN pdf_reports pr ON e.id = pr.exam_id
            WHERE e.id = ?
        ");
        
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO exams (
                exam_number, patient_id, exam_date, exam_time,
                responsible_doctor_id, requesting_doctor_id,
                heart_rate, blood_pressure, weight, height,
                observations, diagnosis, status, priority
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "sissiisddsssss",
            $data['exam_number'],
            $data['patient_id'],
            $data['exam_date'],
            $data['exam_time'],
            $data['responsible_doctor_id'],
            $data['requesting_doctor_id'],
            $data['heart_rate'],
            $data['blood_pressure'],
            $data['weight'],
            $data['height'],
            $data['observations'],
            $data['diagnosis'],
            $data['status'],
            $data['priority']
        );
        
        return $stmt->execute();
    }
    
    public function update($id, $data) {
        $stmt = $this->db->prepare("
            UPDATE exams SET
                exam_number = ?,
                patient_id = ?,
                exam_date = ?,
                exam_time = ?,
                responsible_doctor_id = ?,
                requesting_doctor_id = ?,
                heart_rate = ?,
                blood_pressure = ?,
                weight = ?,
                height = ?,
                observations = ?,
                diagnosis = ?,
                status = ?,
                priority = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->bind_param(
            "sissiisddsssssi",
            $data['exam_number'],
            $data['patient_id'],
            $data['exam_date'],
            $data['exam_time'],
            $data['responsible_doctor_id'],
            $data['requesting_doctor_id'],
            $data['heart_rate'],
            $data['blood_pressure'],
            $data['weight'],
            $data['height'],
            $data['observations'],
            $data['diagnosis'],
            $data['status'],
            $data['priority'],
            $id
        );
        
        return $stmt->execute();
    }
    
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM exams WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
    
    public function attachPDF($examId, $pdfData) {
        $stmt = $this->db->prepare("
            INSERT INTO pdf_reports (
                exam_id, original_filename, stored_filename, file_path,
                file_size, report_date, report_time, findings,
                conclusion, recommendations, medications, next_appointment,
                uploaded_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "isssisssssssi",
            $examId,
            $pdfData['original_filename'],
            $pdfData['stored_filename'],
            $pdfData['file_path'],
            $pdfData['file_size'],
            $pdfData['report_date'],
            $pdfData['report_time'],
            $pdfData['findings'],
            $pdfData['conclusion'],
            $pdfData['recommendations'],
            $pdfData['medications'],
            $pdfData['next_appointment'],
            $pdfData['uploaded_by']
        );
        
        if ($stmt->execute()) {
            // Atualizar status do exame
            $updateStmt = $this->db->prepare("
                UPDATE exams 
                SET pdf_processed = TRUE, status = 'finalizado', updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->bind_param("i", $examId);
            $updateStmt->execute();
            return true;
        }
        
        return false;
    }
    
    public function getStats($startDate = null, $endDate = null) {
        $where = '';
        $params = [];
        $types = '';
        
        if ($startDate && $endDate) {
            $where = "WHERE exam_date BETWEEN ? AND ?";
            $params = [$startDate, $endDate];
            $types = 'ss';
        }
        
        $query = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN pdf_processed = TRUE THEN 1 ELSE 0 END) as with_report,
                SUM(CASE WHEN pdf_processed = FALSE THEN 1 ELSE 0 END) as without_report,
                ROUND((SUM(CASE WHEN pdf_processed = TRUE THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as coverage_rate,
                COUNT(DISTINCT patient_id) as unique_patients
            FROM exams
            $where
        ";
        
        $stmt = $this->db->prepare($query);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
}
?>