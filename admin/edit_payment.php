<?php
$pageTitle = "Ödeme Düzenle - " . APP_NAME;
require_once 'header.php';

$db = Database::getInstance();

// Ödeme ID'sini al
$paymentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($paymentId <= 0) {
    $_SESSION['message'] = 'Geçersiz ödeme ID!';
    $_SESSION['message_type'] = 'danger';
    header("Location: " . BASE_URL . "/admin/payments.php");
    exit;
}

// Ödeme bilgilerini al
$payment = $db->queryOne("SELECT 
                            p.*,
                            u.username,
                            u.company_name
                        FROM 
                            calibration_payments p
                        JOIN 
                            calibration_users u ON p.user_id = u.user_id
                        WHERE 
                            p.payment_id = $paymentId");

if (!$payment) {
    $_SESSION['message'] = 'Ödeme bulunamadı!';
    $_SESSION['message_type'] = 'danger';
    header("Location: " . BASE_URL . "/admin/payments.php");
    exit;
}

// Kullanıcının kalibrasyonlarını al
$calibrations = $db->query("SELECT 
                                cr.calibration_id, 
                                cr.calibration_number,
                                cr.calibration_date,
                                d.device_name
                            FROM 
                                calibration_records cr
                            JOIN 
                                calibration_devices d ON cr.device_id = d.device_id
                            WHERE 
                                cr.user_id = " . $payment['user_id'] . "
                            ORDER BY 
                                cr.calibration_date DESC");

// Form gönderilmiş mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !checkCSRF($_POST['csrf_token'])) {
        $_SESSION['message'] = 'Güvenlik hatası! Lütfen sayfayı yenileyip tekrar deneyin.';
        $_SESSION['message_type'] = 'danger';
        header("Location: " . BASE_URL . "/admin/edit_payment.php?id=$paymentId");
        exit;
    }
    
    // Form verilerini al
    $calibrationId = isset($_POST['calibration_id']) && !empty($_POST['calibration_id']) ? intval($_POST['calibration_id']) : null;
    $amount = floatval(str_replace(',', '.', $_POST['amount']));
    $paymentDate = sanitize($_POST['payment_date']);
    $paymentMethod = sanitize($_POST['payment_method']);
    $notes = sanitize($_POST['notes']);
    
    // Basit doğrulama
    $errors = [];
    
    if ($amount <= 0) {
        $errors[] = 'Ödeme tutarı sıfırdan büyük olmalıdır.';
    }
    
    if (empty($paymentDate)) {
        $errors[] = 'Ödeme tarihi gereklidir.';
    }
    
    if (empty($paymentMethod)) {
        $errors[] = 'Ödeme yöntemi gereklidir.';
    }
    
    // Hata yoksa kaydı güncelle
    if (empty($errors)) {
        $query = "UPDATE calibration_payments SET 
                    calibration_id = " . ($calibrationId ? $calibrationId : "NULL") . ", 
                    amount = $amount, 
                    payment_date = '" . $db->escape($paymentDate) . "', 
                    payment_method = '" . $db->escape($paymentMethod) . "', 
                    notes = '" . $db->escape($notes) . "'
                  WHERE payment_id = $paymentId";
                  
        $result = $db->query($query);
        
        if ($result) {
            // Kullanıcıya bildirim gönder
            $title = 'Ödeme Bilgileri Güncellendi';
            $message = 'Ödeme bilgileriniz güncellendi: ' . formatMoney($amount) . ' (' . $paymentMethod . ')';
            if ($calibrationId) {
                $calibration = $db->queryOne("SELECT calibration_number FROM calibration_records WHERE calibration_id = $calibrationId");
                if ($calibration) {
                    $message .= ', Kalibrasyon No: ' . $calibration['calibration_number'];
                }
            }
            sendNotification($payment['user_id'], $title, $message);
            
            // Aktiviteyi logla
            logActivity($_SESSION['user_id'], 'Ödeme Güncelleme', "Ödeme güncellendi: " . formatMoney($amount) . " - {$payment['company_name']}");
            
            $_SESSION['message'] = 'Ödeme başarıyla güncellendi!';
            $_SESSION['message_type'] = 'success';
            
            header("Location: " . BASE_URL . "/admin/payments.php");
            exit;
        } else {
            $errors[] = 'Ödeme güncellenirken bir hata oluştu: ' . $db->error();
        }
    }
}
?>

<h1 class="h3 mb-4">Ödeme Düzenle</h1>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Ödeme Bilgileri</h6>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-6">
                <h5>Kullanıcı Bilgileri</h5>
                <p><strong>Şirket:</strong> <?php echo htmlspecialchars($payment['company_name']); ?></p>
                <p><strong>Kullanıcı Adı:</strong> <?php echo htmlspecialchars($payment['username']); ?></p>
                <p><strong>Ödeme ID:</strong> <?php echo $payment['payment_id']; ?></p>
            </div>
            <div class="col-md-6">
                <h5>Ödeme Özeti</h5>
                <?php
                $totalAmount = getTotalAmountForUser($payment['user_id']);
                $totalPaid = getTotalPaymentForUser($payment['user_id']);
                $remainingPayment = getRemainingPaymentForUser($payment['user_id']);
                ?>
                <p><strong>Toplam Tutar:</strong> <?php echo formatMoney($totalAmount); ?></p>
                <p><strong>Toplam Ödeme:</strong> <?php echo formatMoney($totalPaid); ?></p>
                <p><strong>Kalan Ödeme:</strong> <?php echo formatMoney($remainingPayment); ?></p>
            </div>
        </div>
        
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?id=' . $paymentId); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="mb-3">
                <label for="calibration_id" class="form-label">Kalibrasyon (Opsiyonel)</label>
                <select class="form-select" id="calibration_id" name="calibration_id">
                    <option value="">-- Genel Ödeme (Kalibrasyona Bağlı Değil) --</option>
                    <?php foreach ($calibrations as $calibration): ?>
                        <option value="<?php echo $calibration['calibration_id']; ?>" <?php echo $payment['calibration_id'] == $calibration['calibration_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($calibration['calibration_number']); ?> - 
                            <?php echo htmlspecialchars($calibration['device_name']); ?> 
                            (<?php echo formatDate($calibration['calibration_date']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Belirli bir kalibrasyona ödeme girişi yapmak isterseniz seçin. Boş bırakırsanız, genel ödeme olarak kaydedilir.</div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="amount" class="form-label">Ödeme Tutarı <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="amount" name="amount" value="<?php echo number_format($payment['amount'], 2, ',', '.'); ?>" required>
                        <span class="input-group-text">₺</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <label for="payment_date" class="form-label">Ödeme Tarihi <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?php echo $payment['payment_date']; ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="payment_method" class="form-label">Ödeme Yöntemi <span class="text-danger">*</span></label>
                    <select class="form-select" id="payment_method" name="payment_method" required>
                        <option value="">-- Ödeme Yöntemi Seçin --</option>
                        <option value="Nakit" <?php echo $payment['payment_method'] === 'Nakit' ? 'selected' : ''; ?>>Nakit</option>
                        <option value="Kredi Kartı" <?php echo $payment['payment_method'] === 'Kredi Kartı' ? 'selected' : ''; ?>>Kredi Kartı</option>
                        <option value="Banka Havalesi" <?php echo $payment['payment_method'] === 'Banka Havalesi' ? 'selected' : ''; ?>>Banka Havalesi</option>
                        <option value="EFT" <?php echo $payment['payment_method'] === 'EFT' ? 'selected' : ''; ?>>EFT</option>
                        <option value="Çek" <?php echo $payment['payment_method'] === 'Çek' ? 'selected' : ''; ?>>Çek</option>
                        <option value="Senet" <?php echo $payment['payment_method'] === 'Senet' ? 'selected' : ''; ?>>Senet</option>
                        <option value="Diğer" <?php echo $payment['payment_method'] === 'Diğer' || !in_array($payment['payment_method'], ['Nakit', 'Kredi Kartı', 'Banka Havalesi', 'EFT', 'Çek', 'Senet']) ? 'selected' : ''; ?>>Diğer</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="notes" class="form-label">Notlar</label>
                <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($payment['notes']); ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-2"></i> Kaydet
            </button>
            <a href="<?php echo BASE_URL; ?>/admin/payments.php" class="btn btn-secondary">
                <i class="fas fa-times me-2"></i> İptal
            </a>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sayısal alanlarda sadece rakam ve virgül girişine izin ver
        const amountInput = document.getElementById('amount');
        amountInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9.,]/g, '');
        });
    });
</script>

<?php require_once 'footer.php'; ?>