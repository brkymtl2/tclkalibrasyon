<?php
$pageTitle = "Ödeme Ekle - " . APP_NAME;
require_once 'header.php';

$db = Database::getInstance();

// Kayıtlı kullanıcılar listesini al
$users = $db->query("SELECT user_id, username, company_name FROM calibration_users WHERE status = 'approved' AND user_type = 'user' ORDER BY company_name ASC");

// Form gönderilmiş mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !checkCSRF($_POST['csrf_token'])) {
        $_SESSION['message'] = 'Güvenlik hatası! Lütfen sayfayı yenileyip tekrar deneyin.';
        $_SESSION['message_type'] = 'danger';
        header("Location: " . BASE_URL . "/admin/add_payment.php");
        exit;
    }
    
    // Form verilerini al
    $userId = intval($_POST['user_id']);
    $calibrationId = isset($_POST['calibration_id']) ? intval($_POST['calibration_id']) : null;
    $amount = floatval(str_replace(',', '.', $_POST['amount']));
    $paymentDate = sanitize($_POST['payment_date']);
    $paymentMethod = sanitize($_POST['payment_method']);
    $notes = sanitize($_POST['notes']);
    
    $adminId = $_SESSION['user_id'];
    
    // Basit doğrulama
    $errors = [];
    
    if (empty($userId)) {
        $errors[] = 'Kullanıcı seçilmelidir.';
    }
    
    if ($amount <= 0) {
        $errors[] = 'Ödeme tutarı sıfırdan büyük olmalıdır.';
    }
    
    if (empty($paymentDate)) {
        $errors[] = 'Ödeme tarihi gereklidir.';
    }
    
    if (empty($paymentMethod)) {
        $errors[] = 'Ödeme yöntemi gereklidir.';
    }
    
    // Hata yoksa kaydı oluştur
    if (empty($errors)) {
        $query = "INSERT INTO calibration_payments (user_id, calibration_id, amount, payment_date, payment_method, notes, created_by) 
                  VALUES (
                     $userId, 
                     " . ($calibrationId ? $calibrationId : "NULL") . ", 
                     $amount, 
                     '" . $db->escape($paymentDate) . "', 
                     '" . $db->escape($paymentMethod) . "', 
                     '" . $db->escape($notes) . "',
                     $adminId
                  )";
                  
        $result = $db->query($query);
        
        if ($result) {
            $paymentId = $db->lastInsertId();
            
            // Kullanıcıya bildirim gönder
            $title = 'Yeni Ödeme Kaydı Oluşturuldu';
            $message = 'Ödeme tutarı: ' . formatMoney($amount) . ', Ödeme yöntemi: ' . $paymentMethod;
            if ($calibrationId) {
                $calibration = $db->queryOne("SELECT calibration_number FROM calibration_records WHERE calibration_id = $calibrationId");
                if ($calibration) {
                    $message .= ', Kalibrasyon No: ' . $calibration['calibration_number'];
                }
            }
            sendNotification($userId, $title, $message);
            
            // Aktiviteyi logla
            $user = getUserInfo($userId);
            logActivity($_SESSION['user_id'], 'Ödeme Ekleme', "Yeni ödeme eklendi: " . formatMoney($amount) . " - {$user['company_name']}");
            
            $_SESSION['message'] = 'Ödeme başarıyla eklendi!';
            $_SESSION['message_type'] = 'success';
            
            header("Location: " . BASE_URL . "/admin/payments.php");
            exit;
        } else {
            $errors[] = 'Ödeme eklenirken bir hata oluştu: ' . $db->error();
        }
    }
}
?>

<h1 class="h3 mb-4">Ödeme Ekle</h1>

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
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="user_id" class="form-label">Kullanıcı/Şirket <span class="text-danger">*</span></label>
                    <select class="form-select" id="user_id" name="user_id" required>
                        <option value="">-- Kullanıcı Seçin --</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['user_id']; ?>">
                                <?php echo htmlspecialchars($user['company_name']); ?> (<?php echo htmlspecialchars($user['username']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="calibration_id" class="form-label">Kalibrasyon (Opsiyonel)</label>
                    <select class="form-select" id="calibration_id" name="calibration_id" disabled>
                        <option value="">-- Önce Kullanıcı Seçin --</option>
                    </select>
                    <div class="form-text">Belirli bir kalibrasyona ödeme girişi yapmak isterseniz seçin. Boş bırakırsanız, genel ödeme olarak kaydedilir.</div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="amount" class="form-label">Ödeme Tutarı <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="amount" name="amount" required>
                        <span class="input-group-text">₺</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <label for="payment_date" class="form-label">Ödeme Tarihi <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="payment_method" class="form-label">Ödeme Yöntemi <span class="text-danger">*</span></label>
                    <select class="form-select" id="payment_method" name="payment_method" required>
                        <option value="">-- Ödeme Yöntemi Seçin --</option>
                        <option value="Nakit">Nakit</option>
                        <option value="Kredi Kartı">Kredi Kartı</option>
                        <option value="Banka Havalesi">Banka Havalesi</option>
                        <option value="EFT">EFT</option>
                        <option value="Çek">Çek</option>
                        <option value="Senet">Senet</option>
                        <option value="Diğer">Diğer</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="notes" class="form-label">Notlar</label>
                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
            </div>
            
            <div class="mb-3" id="payment_summary" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Ödeme Özeti</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <p class="mb-1"><strong>Toplam Tutar:</strong></p>
                                <h4 id="total_amount">0,00 ₺</h4>
                            </div>
                            <div class="col-md-4">
                                <p class="mb-1"><strong>Önceki Ödemeler:</strong></p>
                                <h4 id="paid_amount">0,00 ₺</h4>
                            </div>
                            <div class="col-md-4">
                                <p class="mb-1"><strong>Kalan Tutar:</strong></p>
                                <h4 id="remaining_amount">0,00 ₺</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-2"></i> Ödemeyi Kaydet
            </button>
            <a href="<?php echo BASE_URL; ?>/admin/payments.php" class="btn btn-secondary">
                <i class="fas fa-times me-2"></i> İptal
            </a>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Kullanıcı değiştiğinde kalibrasyonları getir
        const userSelect = document.getElementById('user_id');
        const calibrationSelect = document.getElementById('calibration_id');
        const paymentSummary = document.getElementById('payment_summary');
        const totalAmountEl = document.getElementById('total_amount');
        const paidAmountEl = document.getElementById('paid_amount');
        const remainingAmountEl = document.getElementById('remaining_amount');
        
        userSelect.addEventListener('change', function() {
            const userId = this.value;
            calibrationSelect.disabled = !userId;
            
            if (!userId) {
                calibrationSelect.innerHTML = '<option value="">-- Önce Kullanıcı Seçin --</option>';
                paymentSummary.style.display = 'none';
                return;
            }
            
            // AJAX ile kalibrasyonları getir
            calibrationSelect.innerHTML = '<option value="">Yükleniyor...</option>';
            
            fetch('<?php echo BASE_URL; ?>/ajax/get_user_calibrations.php?user_id=' + userId)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        calibrationSelect.innerHTML = '<option value="">-- Genel Ödeme (Kalibrasyona Bağlı Değil) --</option>';
                        
                        if (data.calibrations && data.calibrations.length > 0) {
                            data.calibrations.forEach(calibration => {
                                const option = document.createElement('option');
                                option.value = calibration.calibration_id;
                                option.textContent = `${calibration.calibration_number} - ${calibration.device_name} (${calibration.calibration_date})`;
                                calibrationSelect.appendChild(option);
                            });
                        }
                    } else {
                        calibrationSelect.innerHTML = '<option value="">-- Hata: ' + data.message + ' --</option>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    calibrationSelect.innerHTML = '<option value="">-- Hata: Kalibrasyonlar yüklenemedi --</option>';
                });
            
            // AJAX ile ödeme özetini getir
            fetch('<?php echo BASE_URL; ?>/ajax/get_payment_summary.php?user_id=' + userId)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        totalAmountEl.textContent = data.total_amount;
                        paidAmountEl.textContent = data.paid_amount;
                        remainingAmountEl.textContent = data.remaining_amount;
                        paymentSummary.style.display = 'block';
                    } else {
                        paymentSummary.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    paymentSummary.style.display = 'none';
                });
        });
        
        // Sayısal alanlarda sadece rakam ve virgül girişine izin ver
        const amountInput = document.getElementById('amount');
        amountInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9.,]/g, '');
        });
    });
</script>

<?php require_once 'footer.php'; ?>