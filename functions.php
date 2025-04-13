<?php
require_once 'config.php';
require_once 'db_connect.php';

// XSS saldırılarına karşı girdi temizleme
function sanitize($input) {
    if (is_array($input)) {
        foreach ($input as $key => $value) {
            $input[$key] = sanitize($value);
        }
        return $input;
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Session başlatma ve yönetme
function initSession() {
    // Session ayarlarını düzenle
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    
    session_name(SESSION_NAME);
    session_start();
    
    // Session süresini ayarlama
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > SESSION_LIFETIME) {
        // Session süresi dolduysa yeniden başlat
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
    
    // CSRF token'ı oluştur (her sayfa yüklenişinde)
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

// CSRF token kontrolü
function checkCSRF($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        logActivity(0, 'CSRF saldırısı tespit edildi', 'Geçersiz token: ' . $token);
        die('Güvenlik ihlali tespit edildi. İşlem reddedildi.');
    }
    return true;
}

// Oturum açma durumunu kontrol eder
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Kullanıcının admin olup olmadığını kontrol eder
function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

// Kullanıcı bilgisini alma
function getUserInfo($userId = null) {
    $db = Database::getInstance();
    $id = $userId ?? $_SESSION['user_id'] ?? 0;
    
    if (!$id) return false;
    
    $query = "SELECT * FROM calibration_users WHERE user_id = " . intval($id);
    return $db->queryOne($query);
}

// Sistem aktivitelerini loglama
function logActivity($userId, $action, $description = '', $ip = null, $userAgent = null) {
    $db = Database::getInstance();
    $userId = intval($userId);
    $action = $db->escape($action);
    $description = $db->escape($description);
    $ip = $ip ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $query = "INSERT INTO calibration_logs (user_id, action, description, ip_address, user_agent) 
              VALUES ($userId, '$action', '$description', '$ip', '$userAgent')";
    
    return $db->query($query);
}

// Kullanıcıya bildirim gönderme
function sendNotification($userId, $title, $message) {
    $db = Database::getInstance();
    $userId = intval($userId);
    $title = $db->escape($title);
    $message = $db->escape($message);
    
    $query = "INSERT INTO calibration_notifications (user_id, title, message) 
              VALUES ($userId, '$title', '$message')";
    
    return $db->query($query);
}

// Tarihi formatla
function formatDate($date, $format = 'd.m.Y') {
    if (!$date) return '';
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return date($format, $timestamp);
}

// Para birimini formatla
function formatMoney($amount, $decimals = 2, $decimalSeparator = ',', $thousandsSeparator = '.') {
    return number_format($amount, $decimals, $decimalSeparator, $thousandsSeparator) . ' ₺';
}

// Dosya yükleme işlemi
function uploadFile($file, $targetDir = UPLOAD_DIR, $allowedTypes = null) {
    // Dosya var mı kontrol et
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['status' => false, 'message' => 'Dosya yüklenmedi.'];
    }
    
    // Klasör yoksa oluştur
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    // İzin verilen dosya türleri
    $allowedTypes = $allowedTypes ?? unserialize(ALLOWED_FILE_TYPES);
    
    // Dosya türünü kontrol et
    $fileType = mime_content_type($file['tmp_name']);
    if (!in_array($fileType, $allowedTypes)) {
        return ['status' => false, 'message' => 'Bu dosya türüne izin verilmiyor.'];
    }
    
    // Dosya boyutunu kontrol et
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['status' => false, 'message' => 'Dosya boyutu çok büyük.'];
    }
    
    // Dosya adını güvenli hale getir
    $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '', $file['name']);
    $targetFile = $targetDir . $fileName;
    
    // Dosyayı taşı
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        return [
            'status' => true,
            'file_name' => $fileName,
            'file_path' => $targetFile,
            'message' => 'Dosya başarıyla yüklendi.'
        ];
    } else {
        return ['status' => false, 'message' => 'Dosya yüklenirken bir hata oluştu.'];
    }
}

// Sayfalama yardımcısı
function getPagination($currentPage, $totalRecords, $recordsPerPage, $url) {
    $totalPages = ceil($totalRecords / $recordsPerPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    
    $pagination = [
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'total_records' => $totalRecords,
        'records_per_page' => $recordsPerPage,
        'offset' => ($currentPage - 1) * $recordsPerPage,
    ];
    
    // Sayfa bağlantıları oluşturma
    $links = [];
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    
    if ($startPage > 1) {
        $links[] = ['page' => 1, 'text' => '«', 'url' => $url . '?page=1'];
    }
    
    if ($currentPage > 1) {
        $links[] = ['page' => $currentPage - 1, 'text' => '‹', 'url' => $url . '?page=' . ($currentPage - 1)];
    }
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        $links[] = [
            'page' => $i,
            'text' => $i,
            'url' => $url . '?page=' . $i,
            'active' => $i == $currentPage
        ];
    }
    
    if ($currentPage < $totalPages) {
        $links[] = ['page' => $currentPage + 1, 'text' => '›', 'url' => $url . '?page=' . ($currentPage + 1)];
    }
    
    if ($endPage < $totalPages) {
        $links[] = ['page' => $totalPages, 'text' => '»', 'url' => $url . '?page=' . $totalPages];
    }
    
    $pagination['links'] = $links;
    return $pagination;
}

// Bir kullanıcı için toplam ödemeyi hesapla
function getTotalPaymentForUser($userId) {
    $db = Database::getInstance();
    $userId = intval($userId);
    
    $query = "SELECT SUM(amount) AS total FROM calibration_payments WHERE user_id = $userId";
    $result = $db->queryValue($query);
    
    return $result ?: 0;
}

// Bir kullanıcı için toplam ödeme tutarını hesapla
function getTotalAmountForUser($userId) {
    $db = Database::getInstance();
    $userId = intval($userId);
    
    $query = "SELECT 
                SUM(p.price) AS total_amount
              FROM 
                calibration_records cr
              JOIN 
                calibration_pricing p ON cr.calibration_type = p.calibration_type
              WHERE 
                cr.user_id = $userId AND p.active = 1";
                
    $result = $db->queryValue($query);
    
    return $result ?: 0;
}

// Bir kullanıcı için kalan ödeme hesapla
function getRemainingPaymentForUser($userId) {
    $totalAmount = getTotalAmountForUser($userId);
    $totalPayment = getTotalPaymentForUser($userId);
    
    return max(0, $totalAmount - $totalPayment);
}

// Yaklaşan kalibrasyonları al
function getUpcomingCalibrations($userId, $days = 30) {
    $db = Database::getInstance();
    $userId = intval($userId);
    $today = date('Y-m-d');
    $futureDate = date('Y-m-d', strtotime("+$days days"));
    
    $query = "SELECT 
                cr.calibration_id,
                cr.calibration_number,
                cr.calibration_type,
                cr.next_calibration_date,
                d.device_name,
                d.serial_number
              FROM 
                calibration_records cr
              JOIN 
                calibration_devices d ON cr.device_id = d.device_id
              WHERE 
                cr.user_id = $userId 
                AND cr.next_calibration_date BETWEEN '$today' AND '$futureDate'
              ORDER BY 
                cr.next_calibration_date ASC";
                
    return $db->query($query);
}

// Sistem günlüklerini temizleme (belirtilen günden eski kayıtları sil)
function cleanupLogs($days = 90) {
    $db = Database::getInstance();
    $date = date('Y-m-d H:i:s', strtotime("-$days days"));
    
    $query = "DELETE FROM calibration_logs WHERE log_date < '$date'";
    return $db->query($query);
}

// Kullanıcı hesabını etkinleştirme
function activateUser($userId) {
    $db = Database::getInstance();
    $userId = intval($userId);
    
    $query = "UPDATE calibration_users SET status = 'approved' WHERE user_id = $userId";
    $result = $db->query($query);
    
    if ($result) {
        $user = getUserInfo($userId);
        sendNotification($userId, 'Hesabınız Onaylandı', 'Hesabınız yönetici tarafından onaylanmıştır. Artık sisteme giriş yapabilirsiniz.');
        logActivity($_SESSION['user_id'] ?? 0, 'Kullanıcı Onayı', "Kullanıcı onaylandı: {$user['username']} (ID: $userId)");
        return true;
    }
    
    return false;
}

// Kullanıcı hesabını reddetme
function rejectUser($userId) {
    $db = Database::getInstance();
    $userId = intval($userId);
    
    $query = "UPDATE calibration_users SET status = 'rejected' WHERE user_id = $userId";
    $result = $db->query($query);
    
    if ($result) {
        $user = getUserInfo($userId);
        sendNotification($userId, 'Hesabınız Reddedildi', 'Hesabınız yönetici tarafından reddedilmiştir. Daha fazla bilgi için lütfen bizimle iletişime geçin.');
        logActivity($_SESSION['user_id'] ?? 0, 'Kullanıcı Reddi', "Kullanıcı reddedildi: {$user['username']} (ID: $userId)");
        return true;
    }
    
    return false;
}

// Okunmamış bildirim sayısını al
function getUnreadNotificationCount($userId) {
    $db = Database::getInstance();
    $userId = intval($userId);
    
    $query = "SELECT COUNT(*) FROM calibration_notifications WHERE user_id = $userId AND is_read = 0";
    return $db->queryValue($query);
}

// Şifre hashleme
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
}

// Şifre doğrulama
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Rastgele şifre oluşturma
function generateRandomPassword($length = 10) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    
    return $password;
}

// Menü oluşturma
function getMenu($userType = 'user') {
    $menu = [];
    
    if ($userType === 'admin') {
        $menu = [
            ['title' => 'Dashboard', 'url' => 'admin/index.php', 'icon' => 'fas fa-tachometer-alt'],
            ['title' => 'Kullanıcılar', 'url' => 'admin/users.php', 'icon' => 'fas fa-users'],
            ['title' => 'Cihazlar', 'url' => 'admin/devices.php', 'icon' => 'fas fa-tools'],
            ['title' => 'Kalibrasyonlar', 'url' => 'admin/calibrations.php', 'icon' => 'fas fa-clipboard-check'],
            ['title' => 'Ödemeler', 'url' => 'admin/payments.php', 'icon' => 'fas fa-money-bill-wave'],
            ['title' => 'Belgeler', 'url' => 'admin/documents.php', 'icon' => 'fas fa-file-pdf'],
            ['title' => 'Fiyatlandırma', 'url' => 'admin/pricing.php', 'icon' => 'fas fa-tag'],
            ['title' => 'Onay Bekleyenler', 'url' => 'admin/approvals.php', 'icon' => 'fas fa-user-check'],
            ['title' => 'Raporlar', 'url' => 'admin/reports.php', 'icon' => 'fas fa-chart-bar'],
            ['title' => 'Sistem Logları', 'url' => 'admin/logs.php', 'icon' => 'fas fa-history'],
            ['title' => 'Ayarlar', 'url' => 'admin/settings.php', 'icon' => 'fas fa-cog'],
        ];
    } else {
        $menu = [
            ['title' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'fas fa-tachometer-alt'],
            ['title' => 'Cihazlarım', 'url' => 'devices.php', 'icon' => 'fas fa-tools'],
            ['title' => 'Kalibrasyonlarım', 'url' => 'calibrations.php', 'icon' => 'fas fa-clipboard-check'],
            ['title' => 'Ödemelerim', 'url' => 'payments.php', 'icon' => 'fas fa-money-bill-wave'],
            ['title' => 'Belgelerim', 'url' => 'documents.php', 'icon' => 'fas fa-file-pdf'],
            ['title' => 'Bildirimlerim', 'url' => 'notifications.php', 'icon' => 'fas fa-bell'],
            ['title' => 'Profilim', 'url' => 'profile.php', 'icon' => 'fas fa-user'],
        ];
    }
    
    return $menu;
}

// Döküman türünü okuyabilir URL'ye dönüştür
function getDocumentUrl($documentPath) {
    return BASE_URL . '/' . $documentPath;
}

// İşlem başarı veya hata mesajı döndür
function showMessage($message, $type = 'success') {
    return '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">
                ' . $message . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
}

// Cihazın son kalibrasyon tarihini al
function getLastCalibrationDate($deviceId) {
    $db = Database::getInstance();
    $deviceId = intval($deviceId);
    
    $query = "SELECT calibration_date 
              FROM calibration_records 
              WHERE device_id = $deviceId 
              ORDER BY calibration_date DESC 
              LIMIT 1";
              
    return $db->queryValue($query);
}

// Bir kalibrasyon için belge olup olmadığını kontrol et
function hasCalibrationDocument($calibrationId) {
    $db = Database::getInstance();
    $calibrationId = intval($calibrationId);
    
    $query = "SELECT COUNT(*) 
              FROM calibration_documents 
              WHERE calibration_id = $calibrationId";
              
    return (bool)$db->queryValue($query);
}

// Yeni bildirim ekleyip, e-posta da gönderebilen gelişmiş fonksiyon
function addNotification($userId, $title, $message, $sendEmail = false) {
    $db = Database::getInstance();
    
    // Bildirim ekle
    sendNotification($userId, $title, $message);
    
    // E-posta gönderme seçeneği etkinse
    if ($sendEmail) {
        $user = getUserInfo($userId);
        if ($user && !empty($user['email'])) {
            // E-posta gönderme işlemi burada yapılabilir
            // Bu örnekte sadece log kayıt ediyoruz
            logActivity(0, 'E-posta gönderildi', "Alıcı: {$user['email']}, Konu: $title");
        }
    }
    
    return true;
}