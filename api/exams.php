<?php
session_start();
require_once '../config/database.php';
require_once '../includes/Database.class.php';
require_once '../includes/Auth.class.php';
require_once '../includes/Exam.class.php';
require_once '../includes/Utils.class.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    Utils::sendJsonResponse(['error' => 'Não autenticado'], 401);
}

$db = Database::getInstance();
$examObj = new Exam();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

switch ($method) {
    case 'GET':
        if ($id) {
            // Buscar exame específico
            $exam = $examObj->getById($id);
            if ($exam) {
                Utils::sendJsonResponse($exam);
            } else {
                Utils::sendJsonResponse(['error' => 'Exame não encontrado'], 404);
            }
        } elseif ($action === 'stats') {
            // Estatísticas
            $startDate = $_GET['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? null;
            
            $stats = $examObj->getStats($startDate, $endDate);
            Utils::sendJsonResponse($stats);
        } else {
            // Listar exames com filtros
            $page = $_GET['page'] ?? 1;
            $limit = $_GET['limit'] ?? 20;
            
            $filters = [
                'search' => $_GET['search'] ?? '',
                'start_date' => $_GET['start_date'] ?? '',
                'end_date' => $_GET['end_date'] ?? '',
                'status' => $_GET['status'] ?? '',
                'patient_id' => $_GET['patient_id'] ?? ''
            ];
            
            $result = $examObj->getAll($filters, $page, $limit);
            $exams = [];
            
            while ($row = $result->fetch_assoc()) {
                $exams[] = $row;
            }
            
            // Contar total (simplificado)
            $countQuery = "SELECT COUNT(*) as total FROM exams";
            $countResult = $db->query($countQuery);
            $total = $countResult->fetch_assoc()['total'];
            
            Utils::sendJsonResponse([
                'exams' => $exams,
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
        
        if ($examObj->create($data)) {
            Utils::sendJsonResponse(['success' => true, 'message' => 'Exame criado com sucesso']);
        } else {
            Utils::sendJsonResponse(['error' => 'Erro ao criar exame'], 500);
        }
        break;
        
    case 'PUT':
        if (!$auth->hasRole('admin')) {
            Utils::sendJsonResponse(['error' => 'Acesso negado'], 403);
        }
        
        if (!$id) {
            Utils::sendJsonResponse(['error' => 'ID do exame não informado'], 400);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data)) {
            Utils::sendJsonResponse(['error' => 'Dados inválidos'], 400);
        }
        
        if ($examObj->update($id, $data)) {
            Utils::sendJsonResponse(['success' => true, 'message' => 'Exame atualizado com sucesso']);
        } else {
            Utils::sendJsonResponse(['error' => 'Erro ao atualizar exame'], 500);
        }
        break;
        
    case 'DELETE':
        if (!$auth->hasRole('admin')) {
            Utils::sendJsonResponse(['error' => 'Acesso negado'], 403);
        }
        
        if (!$id) {
            Utils::sendJsonResponse(['error' => 'ID do exame não informado'], 400);
        }
        
        if ($examObj->delete($id)) {
            Utils::sendJsonResponse(['success' => true, 'message' => 'Exame excluído com sucesso']);
        } else {
            Utils::sendJsonResponse(['error' => 'Erro ao excluir exame'], 500);
        }
        break;
        
    default:
        Utils::sendJsonResponse(['error' => 'Método não permitido'], 405);
}
?>