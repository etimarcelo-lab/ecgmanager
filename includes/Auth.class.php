<?php
// Carregar classe Database corretamente
require_once __DIR__ . '/Database.class.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function login($username, $password) {
        $stmt = $this->db->prepare("
            SELECT id, username, password, role, full_name 
            FROM users 
            WHERE username = ? AND active = TRUE
        ");
        
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            // Em produção: usar password_verify()
            if ($password === $user['password'] || password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['logged_in'] = true;
                
                $this->logLogin($user['id']);
                return true;
            }
        }
        return false;
    }
    
    private function logLogin($userId) {
        $stmt = $this->db->prepare("
            INSERT INTO audit_logs (user_id, action, table_name, ip_address, user_agent)
            VALUES (?, 'login', 'users', ?, ?)
        ");
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $stmt->bind_param("iss", $userId, $ip, $agent);
        $stmt->execute();
    }
    
    public function logout() {
        session_destroy();
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public function hasRole($role) {
        return $this->isLoggedIn() && $_SESSION['role'] === $role;
    }
    
    public function requireRole($role) {
        if (!$this->hasRole($role)) {
            header('Location: ../index.php');
            exit();
        }
    }
}
?>
