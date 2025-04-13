<?php
require_once '../config.php';
require_once '../db_connect.php';
require_once '../functions.php';

// Session başlat
initSession();

// Kullanıcı giriş yapmamış veya admin değilse, login sayfasına yönlendir
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['message'] = 'Bu sayfaya erişebilmek için admin olarak giriş yapmalısınız.';
    $_SESSION['message_type'] = 'danger';
    header("Location: " . BASE_URL . "/login.php");
    exit;
}

// GET parametrelerini kontrol et
if (!isset($_GET['action']) || !isset($_GET['id'])) {
    $_SESSION['message'] = 'Geçersiz istek! Eksik parametreler.';
    $_SESSION['message_type'] = 'danger';
    header("Location: " . BASE_URL . "/admin/approvals.php");
    exit;
}

$action = sanitize($_GET['action']);
$userId = intval($_GET['id']);

// ID geçerli mi kontrol et
if ($userId <= 0) {
    $_SESSION['message'] = 'Geçersiz kullanıcı ID!';
    $_SESSION['message_type'] = 'danger';
    header("Location: " . BASE_URL . "/admin/approvals.php");
    exit;
}

$db = Database::getInstance();

// Kullanıcıyı kontrol et
$user = $db->queryOne("SELECT * FROM calibration_users WHERE user_id = $userId");

if (!$user) {
    $_SESSION['message'] = 'Kullanıcı bulunamadı!';
    $_SESSION['message_type'] = 'danger';
    header("Location: " . BASE_URL . "/admin/approvals.php");
    exit;
}

// Kullanıcı zaten onaylanmış veya reddedilmiş mi kontrol et
if ($user['status'] !== 'pending') {
    $_SESSION['message'] = 'Bu kullanıcı için zaten bir karar verilmiş!';
    $_SESSION['message_type'] = 'warning';
    header("Location: " . BASE_URL . "/admin/approvals.php");
    exit;
}

// İşlemi gerçekleştir
if ($action === 'approve') {
    // Kullanıcı onaylandı
    $result = $db->query("UPDATE calibration_users SET status = 'approved' WHERE user_id = $userId");
    
    if ($result) {
        // Kullanıcıya bildirim gönder
        $title = 'Hesabınız Onaylandı';
        $message = 'Hesabınız onaylanmıştır. Artık sisteme giriş yapabilirsiniz.';
        sendNotification($userId, $title, $message);
        
        // Aktiviteyi logla
        logActivity($_SESSION['user_id'], 'Kullanıcı Onayı', "Kullanıcı onaylandı: {$user['username']} (ID: $userId)");
        
        $_SESSION['message'] = 'Kullanıcı başarıyla onaylandı!';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = 'Kullanıcı onaylanırken bir hata oluştu!';
        $_SESSION['message_type'] = 'danger';
    }
} elseif ($action === 'reject') {
    // Kullanıcı reddedildi
    $result = $db->query("UPDATE calibration_users SET status = 'rejected' WHERE user_id = $userId");
    
    if ($result) {
        // Kullanıcıya bildirim gönder
        $title = 'Hesabınız Reddedildi';
        $message = 'Hesabınız reddedilmiştir. Daha fazla bilgi için lütfen bizimle iletişime geçin.';
        sendNotification($userId, $title, $message);
        
        // Aktiviteyi logla
        logActivity($_SESSION['user_id'], 'Kullanıcı Reddi', "Kullanıcı reddedildi: {$user['username']} (ID: $userId)");
        
        $_SESSION['message'] = 'Kullanıcı başarıyla reddedildi!';
        $_SESSION['message_type'] = 'warning';
    } else {
        $_SESSION['message'] = 'Kullanıcı reddedilirken bir hata oluştu!';
        $_SESSION['message_type'] = 'danger';
    }
} else {
    $_SESSION['message'] = 'Geçersiz işlem!';
    $_SESSION['message_type'] = 'danger';
}

// Onay sayfasına yönlendir
header("Location: " . BASE_URL . "/admin/approvals.php");
exit;