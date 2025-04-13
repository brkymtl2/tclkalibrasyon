<?php
require_once '../config.php';
require_once '../db_connect.php';
require_once '../functions.php';

// Session başlat
initSession();

// JSON yanıtı hazırla
header('Content-Type: application/json');

// Kullanıcı giriş yapmış mı kontrol et
if (!isLoggedIn() || !isAdmin()) {
    echo json_encode(['status' => 'error', 'message' => 'Yetki hatası! Giriş yapmalısınız.']);
    exit;
}

// User ID'yi al
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($userId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Geçersiz kullanıcı ID!']);
    exit;
}

try {
    // Toplam tutarı hesapla
    $totalAmount = getTotalAmountForUser($userId);
    
    // Ödenen tutarı hesapla
    $paidAmount = getTotalPaymentForUser($userId);
    
    // Kalan tutarı hesapla
    $remainingAmount = max(0, $totalAmount - $paidAmount);
    
    echo json_encode([
        'status' => 'success',
        'total_amount' => formatMoney($totalAmount),
        'paid_amount' => formatMoney($paidAmount),
        'remaining_amount' => formatMoney($remainingAmount)
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Veritabanı hatası: ' . $e->getMessage()]);
}
exit;