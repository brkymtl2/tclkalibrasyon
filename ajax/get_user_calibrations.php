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
    echo json_encode(['status' => 'error', 'message' => 'Yetki hatası! Giriş yapmalısınız.']);
    exit;
}

// User ID'yi al
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($userId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Geçersiz kullanıcı ID!']);
    exit;
}

$db = Database::getInstance();

// Kullanıcının kalibrasyonlarını al
$calibrations = $db->query("SELECT 
                                cr.calibration_id, 
                                cr.calibration_number, 
                                cr.calibration_date,
                                d.device_name, 
                                d.serial_number
                            FROM 
                                calibration_records cr
                            JOIN 
                                calibration_devices d ON cr.device_id = d.device_id
                            WHERE 
                                cr.user_id = $userId
                            ORDER BY 
                                cr.calibration_date DESC");

if ($calibrations === false) {
    echo json_encode(['status' => 'error', 'message' => 'Veritabanı hatası: ' . $db->error()]);
    exit;
}

// Tarihi formatla
foreach ($calibrations as &$calibration) {
    $calibration['calibration_date'] = formatDate($calibration['calibration_date']);
}

echo json_encode(['status' => 'success', 'calibrations' => $calibrations]);
exit;