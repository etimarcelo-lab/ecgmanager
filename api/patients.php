<?php
session_start();
require_once '../config/database.php';
require_once '../includes/Database.class.php';
require_once '../includes/Auth.class.php';
require_once '../includes/Patient.class.php';
require_once '../includes/Utils.class.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    Utils::sendJsonResponse(['error' => 'Não autenticado'], 401);
}

$db = Database::getInstance();
$patientObj = new Patient();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

switch ($method) {
    case 'GET':
        if ($id) {
            // Buscar paciente específico
            $patient = $patientObj->getById($id);
            if ($patient) {
                Utils::sendJsonResponse($patient);
            } else {
                Utils::sendJsonResponse(['error' => 'Paciente não encontrado'], 404);
            }
        } elseif ($action === 'search') {
            // Buscar pacientes
            $term = $_GET['term'] ?? '';
            $limit = $_GET['limit'] ?? 10;
            $results = $patientObj->search($term, $limit);
            
            $patients = [];
            while ($row = $results->fetch_assoc()) {
                $patients[] = $row;
            }
            
            Utils::sendJsonResponse($patients);
        } else {
            // Listar pacientes com paginação
            $page = $_GET['page'] ?? 1;
            $limit = $_GET['limit'] ?? 20;
            $search = $_GET['search'] ?? '';
            
            $result = $patientObj->getAll($page, $limit, $search);
            $patients = [];
            
            while ($row = $result->fetch_assoc()) {
                $patients[] = $row;
            }
            
            $total = $patientObj->countAll();
            
            Utils::sendJsonResponse([
                'patients' => $patients,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ]);
        }
        break;
        
    case 'POST':
        if (!$auth->hasRole('admin')) {
            Utils::sendJsonResponse(['error' => 'Acesso negado'], 403);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data)) {
            Utils::sendJsonResponse(['error' => 'Dados inválidos'], 400);
        }
        
        if ($patientObj->create($data)) {
            Utils::sendJsonResponse(['success' => true, 'message' => 'Paciente criado com sucesso']);
        } else {
            Utils::sendJsonResponse(['error' => 'Erro ao criar paciente'], 500);
        }
        break;
        
    case 'PUT':
        if (!$auth->hasRole('admin')) {
            Utils::sendJsonResponse(['error' => 'Acesso negado'], 403);
        }
        
        if (!$id) {
            Utils::sendJsonResponse(['error' => 'ID do paciente não informado'], 400);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data)) {
            Utils::sendJsonResponse(['error' => 'Dados inválidos'], 400);
        }
        
        if ($patientObj->update($id, $data)) {
            Utils::sendJsonResponse(['success' => true, 'message' => 'Paciente atualizado com sucesso']);
        } else {
            Utils::sendJsonResponse(['error' => 'Erro ao atualizar paciente'], 500);
        }
        break;
        
    case 'DELETE':
        if (!$auth->hasRole('admin')) {
            Utils::sendJsonResponse(['error' => 'Acesso negado'], 403);
        }
        
        if (!$id) {
            Utils::sendJsonResponse(['error' => 'ID do paciente não informado'], 400);
        }
        
        if ($patientObj->delete($id)) {
            Utils::sendJsonResponse(['success' => true, 'message' => 'Paciente excluído com sucesso']);
        } else {
            Utils::sendJsonResponse(['error' => 'Erro ao excluir paciente. Verifique se não há exames vinculados.'], 500);
        }
        break;
        
    default:
        Utils::sendJsonResponse(['error' => 'Método não permitido'], 405);
}
?>