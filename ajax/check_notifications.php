<?php
require_once '../config.php';
require_once '../db_connect.php';
require_once '../functions.php';

// Session başlat
initSession();

// JSON yanıtı hazırla
header('Content-Type: application/json');

// Kullanıcı giriş yapmış mı kontrol et
if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Yetki hatası!', 'count' => 0]);
    exit;
}

// Okunmamış bildirimleri al
$unreadCount = getUnreadNotificationCount($_SESSION['user_id']);

echo json_encode(['status' => 'success', 'count' => (int)$unreadCount]);
exit;