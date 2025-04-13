<?php
require_once 'config.php';
require_once 'db_connect.php';
require_once 'functions.php';

// Session başlat
initSession();

// Kullanıcı giriş yapmış mı kontrol et
if (isLoggedIn()) {
    // Çıkış aktivitesini logla
    logActivity($_SESSION['user_id'], 'Çıkış', 'Kullanıcı çıkış yaptı');
    
    // Session'ı temizle
    $_SESSION = array();
    
    // Session cookie'sini sil
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Session'ı sonlandır
    session_destroy();
}

// Giriş sayfasına yönlendir
header("Location: " . BASE_URL . "/login.php");
exit;
?>