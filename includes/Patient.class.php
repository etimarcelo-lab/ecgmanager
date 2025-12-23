<?php
class Patient {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getAll($page = 1, $limit = 20, $search = '') {
        $offset = ($page - 1) * $limit;
        $params = [];
        $types = '';
        
        $where = '';
        if (!empty($search)) {
            $where = "WHERE p.full_name LIKE ? OR p.cpf LIKE ? OR p.clinical_record LIKE ?";
            $searchTerm = "%$search%";
            $params = [$searchTerm, $searchTerm, $searchTerm];
            $types = 'sss';
        }
        
        $query = "
            SELECT p.*, 
                   COUNT(e.id) as total_exams,
                   MAX(e.exam_date) as last_exam
            FROM patients p
            LEFT JOIN exams e ON p.id = e.patient_id
            $where
            GROUP BY p.id
            ORDER BY p.created_at DESC
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
            SELECT p.*, 
                   COUNT(e.id) as total_exams
            FROM patients p
            LEFT JOIN exams e ON p.id = e.patient_id
            WHERE p.id = ?
            GROUP BY p.id
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO patients (
                patient_id, full_name, birth_date, gender,
                clinical_record, rg, cpf, email, phone,
                address, city, state, zip_code, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "ssssssssssssss",
            $data['patient_id'],
            $data['full_name'],
            $data['birth_date'],
            $data['gender'],
            $data['clinical_record'],
            $data['rg'],
            $data['cpf'],
            $data['email'],
            $data['phone'],
            $data['address'],
            $data['city'],
            $data['state'],
            $data['zip_code'],
            $data['notes']
        );
        
        return $stmt->execute();
    }
    
    public function update($id, $data) {
        $stmt = $this->db->prepare("
            UPDATE patients SET
                patient_id = ?,
                full_name = ?,
                birth_date = ?,
                gender = ?,
                clinical_record = ?,
                rg = ?,
                cpf = ?,
                email = ?,
                phone = ?,
                address = ?,
                city = ?,
                state = ?,
                zip_code = ?,
                notes = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->bind_param(
            "ssssssssssssssi",
            $data['patient_id'],
            $data['full_name'],
            $data['birth_date'],
            $data['gender'],
            $data['clinical_record'],
            $data['rg'],
            $data['cpf'],
            $data['email'],
            $data['phone'],
            $data['address'],
            $data['city'],
            $data['state'],
            $data['zip_code'],
            $data['notes'],
            $id
        );
        
        return $stmt->execute();
    }
    
    public function delete($id) {
        // Verificar se existem exames vinculados
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM exams WHERE patient_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            return false; // Não deletar se houver exames
        }
        
        $stmt = $this->db->prepare("DELETE FROM patients WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
    
    public function search($term, $limit = 10) {
        $stmt = $this->db->prepare("
            SELECT id, full_name, birth_date, cpf, clinical_record
            FROM patients
            WHERE full_name LIKE ? OR cpf LIKE ? OR clinical_record LIKE ?
            ORDER BY full_name
            LIMIT ?
        ");
        
        $searchTerm = "%$term%";
        $stmt->bind_param("sssi", $searchTerm, $searchTerm, $searchTerm, $limit);
        $stmt->execute();
        return $stmt->get_result();
    }
    
    public function countAll() {
        $result = $this->db->query("SELECT COUNT(*) as total FROM patients");
        return $result->fetch_assoc()['total'];
    }
    
    public function getExams($patientId) {
        $stmt = $this->db->prepare("
            SELECT e.*, 
                   d1.name as resp_doctor,
                   d2.name as req_doctor,
                   pr.stored_filename,
                   pr.report_date
            FROM exams e
            LEFT JOIN doctors d1 ON e.responsible_doctor_id = d1.id
            LEFT JOIN doctors d2 ON e.requesting_doctor_id = d2.id
            LEFT JOIN pdf_reports pr ON e.id = pr.exam_id
            WHERE e.patient_id = ?
            ORDER BY e.exam_date DESC, e.exam_time DESC
        ");
        
        $stmt->bind_param("i", $patientId);
        $stmt->execute();
        return $stmt->get_result();
    }
}
?>