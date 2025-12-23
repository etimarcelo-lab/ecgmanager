<?php
class Utils {
    
    public static function formatDate($date, $format = 'd/m/Y') {
        if (empty($date) || $date == '0000-00-00') {
            return '';
        }
        
        $dateTime = new DateTime($date);
        return $dateTime->format($format);
    }
    
    public static function formatDateTime($datetime, $format = 'd/m/Y H:i') {
        if (empty($datetime) || $datetime == '0000-00-00 00:00:00') {
            return '';
        }
        
        $dateTime = new DateTime($datetime);
        return $dateTime->format($format);
    }
    
    public static function formatCPF($cpf) {
        if (strlen($cpf) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
        }
        return $cpf;
    }
    
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        
        $input = trim($input);
        $input = stripslashes($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        return $input;
    }
    
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    public static function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return $randomString;
    }
    
    public static function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    public static function getAgeFromBirthDate($birthDate) {
        $birth = new DateTime($birthDate);
        $today = new DateTime();
        $age = $today->diff($birth);
        return $age->y;
    }
    
    public static function calculateHeartRateZone($bpm, $age) {
        $maxHR = 220 - $age;
        
        if ($bpm < ($maxHR * 0.5)) {
            return 'Muito Leve';
        } elseif ($bpm < ($maxHR * 0.6)) {
            return 'Leve';
        } elseif ($bpm < ($maxHR * 0.7)) {
            return 'Moderado';
        } elseif ($bpm < ($maxHR * 0.8)) {
            return 'Intenso';
        } elseif ($bpm < ($maxHR * 0.9)) {
            return 'Muito Intenso';
        } else {
            return 'Máximo';
        }
    }
    
    public static function getBrazilianStates() {
        return [
            'AC' => 'Acre',
            'AL' => 'Alagoas',
            'AP' => 'Amapá',
            'AM' => 'Amazonas',
            'BA' => 'Bahia',
            'CE' => 'Ceará',
            'DF' => 'Distrito Federal',
            'ES' => 'Espírito Santo',
            'GO' => 'Goiás',
            'MA' => 'Maranhão',
            'MT' => 'Mato Grosso',
            'MS' => 'Mato Grosso do Sul',
            'MG' => 'Minas Gerais',
            'PA' => 'Pará',
            'PB' => 'Paraíba',
            'PR' => 'Paraná',
            'PE' => 'Pernambuco',
            'PI' => 'Piauí',
            'RJ' => 'Rio de Janeiro',
            'RN' => 'Rio Grande do Norte',
            'RS' => 'Rio Grande do Sul',
            'RO' => 'Rondônia',
            'RR' => 'Roraima',
            'SC' => 'Santa Catarina',
            'SP' => 'São Paulo',
            'SE' => 'Sergipe',
            'TO' => 'Tocantins'
        ];
    }
    
    public static function sendJsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
    
    public static function isAjaxRequest() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }
    
    public static function createPagination($currentPage, $totalItems, $itemsPerPage, $url) {
        $totalPages = ceil($totalItems / $itemsPerPage);
        
        if ($totalPages <= 1) {
            return '';
        }
        
        $pagination = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
        
        // Botão anterior
        if ($currentPage > 1) {
            $pagination .= '<li class="page-item">';
            $pagination .= '<a class="page-link" href="' . $url . ($currentPage - 1) . '">Anterior</a>';
            $pagination .= '</li>';
        }
        
        // Páginas
        $start = max(1, $currentPage - 2);
        $end = min($totalPages, $currentPage + 2);
        
        for ($i = $start; $i <= $end; $i++) {
            $active = $i == $currentPage ? ' active' : '';
            $pagination .= '<li class="page-item' . $active . '">';
            $pagination .= '<a class="page-link" href="' . $url . $i . '">' . $i . '</a>';
            $pagination .= '</li>';
        }
        
        // Botão próximo
        if ($currentPage < $totalPages) {
            $pagination .= '<li class="page-item">';
            $pagination .= '<a class="page-link" href="' . $url . ($currentPage + 1) . '">Próximo</a>';
            $pagination .= '</li>';
        }
        
        $pagination .= '</ul></nav>';
        
        return $pagination;
    }
}
?>