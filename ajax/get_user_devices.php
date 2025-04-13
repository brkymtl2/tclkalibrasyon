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
    echo json_encode(['status' => 'error', 'message' => 'Geçersiz kullanıcı ID!']);
    exit;
}

$db = Database::getInstance();

// Kullanıcının cihazlarını al
$devices = $db->query("SELECT device_id, device_name, serial_number, device_model FROM calibration_devices WHERE user_id = $userId ORDER BY device_name ASC");

if ($devices === false) {
    echo json_encode(['status' => 'error', 'message' => 'Veritabanı hatası: ' . $db->error()]);
    exit;
}

echo json_encode(['status' => 'success', 'devices' => $devices]);
exit;'Yetki hatası! Giriş yapmalısınız.']);
    exit;
}

// User ID'yi al
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($userId <= 0) {
    echo json_encode(['status' => 'error', 'message' =>