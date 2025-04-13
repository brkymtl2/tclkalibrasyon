<?php
$pageTitle = "Yeni Kalibrasyon Ekle - " . APP_NAME;
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
        header("Location: " . BASE_URL . "/admin/add_calibration.php");
        exit;
    }
    
    // Form verilerini al
    $userId = intval($_POST['user_id']);
    $deviceId = intval($_POST['device_id']);
    $calibrationType = sanitize($_POST['calibration_type']);
    $calibrationNumber = sanitize($_POST['calibration_number']);
    $calibrationDate = sanitize($_POST['calibration_date']);
    $calibrationStandard = sanitize($_POST['calibration_standard']);
    $notes = sanitize($_POST['notes']);
    
    // Next Calibration Date hesapla (varsayılan: 1 yıl sonra)
    $nextCalibrationDate = date('Y-m-d', strtotime($calibrationDate . ' + 1 year'));
    if (isset($_POST['next_calibration_date']) && !empty($_POST['next_calibration_date'])) {
        $nextCalibrationDate = sanitize($_POST['next_calibration_date']);
    }
    
    $adminId = $_SESSION['user_id'];
    
    // Basit doğrulama
    $errors = [];
    
    if (empty($userId)) {
        $errors[] = 'Kullanıcı seçilmelidir.';
    }
    
    if (empty($deviceId)) {
        $errors[] = 'Cihaz seçilmelidir.';
    }
    
    if (empty($calibrationType)) {
        $errors[] = 'Kalibrasyon tipi gereklidir.';
    }
    
    if (empty($calibrationNumber)) {
        $errors[] = 'Kalibrasyon numarası gereklidir.';
    }
    
    if (empty($calibrationDate)) {
        $errors[] = 'Kalibrasyon tarihi gereklidir.';
    }
    
    // Hata yoksa kaydı oluştur
    if (empty($errors)) {
        $query = "INSERT INTO calibration_records (device_id, user_id, calibration_number, calibration_type, 
                     calibration_date, next_calibration_date, calibration_standard, notes, status, created_by) 
                  VALUES (
                     $deviceId, 
                     $userId, 
                     '" . $db->escape($calibrationNumber) . "', 
                     '" . $db->escape($calibrationType) . "', 
                     '" . $db->escape($calibrationDate) . "', 
                     '" . $db->escape($nextCalibrationDate) . "', 
                     '" . $db->escape($calibrationStandard) . "', 
                     '" . $db->escape($notes) . "',
                     'completed',
                     $adminId
                  )";
                  
        $result = $db->query($query);
        
        if ($result) {
            $calibrationId = $db->lastInsertId();
            
            // Kullanıcıya bildirim gönder
            $title = 'Yeni Kalibrasyon Kaydı Oluşturuldu';
            $message = 'Cihazınız için yeni bir kalibrasyon kaydı oluşturulmuştur. Kalibrasyon No: ' . $calibrationNumber;
            sendNotification($userId, $title, $message);
            
            // Aktiviteyi logla
            $user = getUserInfo($userId);
            $device = $db->queryOne("SELECT device_name FROM calibration_devices WHERE device_id = $deviceId");
            logActivity($_SESSION['user_id'], 'Kalibrasyon Ekleme', "Yeni kalibrasyon eklendi: {$calibrationNumber} - {$device['device_name']} - {$user['company_name']}");
            
            $_SESSION['message'] = 'Kalibrasyon başarıyla eklendi!';
            $_SESSION['message_type'] = 'success';
            
            // Kalibrasyon belgesi eklemek üzere yönlendir
            header("Location: " . BASE_URL . "/admin/add_document.php?calibration_id=" . $calibrationId);
            exit;
        } else {
            $errors[] = 'Kalibrasyon eklenirken bir hata oluştu: ' . $db->error();
        }
    }
}
?>

<h1 class="h3 mb-4">Yeni Kalibrasyon Ekle</h1>

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
        <h6 class="m-0 font-weight-bold text-primary">Kalibrasyon Bilgileri</h6>
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
                    <label for="device_id" class="form-label">Cihaz <span class="text-danger">*</span></label>
                    <select class="form-select" id="device_id" name="device_id" required disabled>
                        <option value="">-- Önce Kullanıcı Seçin --</option>
                    </select>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="calibration_type" class="form-label">Kalibrasyon Tipi <span class="text-danger">*</span></label>
                    <select class="form-select" id="calibration_type" name="calibration_type" required>
                        <option value="">-- Kalibrasyon Tipi Seçin --</option>
                        <?php
                        // Fiyatlandırma tablosundan kalibrasyon tiplerini al
                        $calibrationTypes = $db->query("SELECT DISTINCT calibration_type FROM calibration_pricing WHERE active = 1 ORDER BY calibration_type ASC");
                        foreach ($calibrationTypes as $type): 
                        ?>
                            <option value="<?php echo htmlspecialchars($type['calibration_type']); ?>">
                                <?php echo htmlspecialchars($type['calibration_type']); ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="other">Diğer (Manuel Giriş)</option>
                    </select>
                    <div id="other_type_container" class="mt-2 d-none">
                        <input type="text" class="form-control" id="other_type" placeholder="Kalibrasyon Tipini Girin">
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="calibration_number" class="form-label">Kalibrasyon Numarası <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="calibration_number" name="calibration_number" required>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="calibration_date" class="form-label">Kalibrasyon Tarihi <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="calibration_date" name="calibration_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="next_calibration_date" class="form-label">Bir Sonraki Kalibrasyon Tarihi</label>
                    <input type="date" class="form-control" id="next_calibration_date" name="next_calibration_date" value="<?php echo date('Y-m-d', strtotime('+1 year')); ?>">
                    <div class="form-text">Boş bırakılırsa, otomatik olarak 1 yıl sonrası ayarlanır.</div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="calibration_standard" class="form-label">Kalibrasyon Standardı</label>
                <input type="text" class="form-control" id="calibration_standard" name="calibration_standard">
            </div>
            
            <div class="mb-3">
                <label for="notes" class="form-label">Notlar</label>
                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-2"></i> Kalibrasyonu Kaydet
            </button>
            <a href="<?php echo BASE_URL; ?>/admin/calibrations.php" class="btn btn-secondary">
                <i class="fas fa-times me-2"></i> İptal
            </a>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Kullanıcı değiştiğinde cihazları getir
        const userSelect = document.getElementById('user_id');
        const deviceSelect = document.getElementById('device_id');
        
        userSelect.addEventListener('change', function() {
            const userId = this.value;
            deviceSelect.disabled = !userId;
            
            if (!userId) {
                deviceSelect.innerHTML = '<option value="">-- Önce Kullanıcı Seçin --</option>';
                return;
            }
            
            // AJAX ile cihazları getir
            deviceSelect.innerHTML = '<option value="">Yükleniyor...</option>';
            
            fetch('<?php echo BASE_URL; ?>/ajax/get_user_devices.php?user_id=' + userId)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        deviceSelect.innerHTML = '<option value="">-- Cihaz Seçin --</option>';
                        
                        if (data.devices && data.devices.length > 0) {
                            data.devices.forEach(device => {
                                const option = document.createElement('option');
                                option.value = device.device_id;
                                option.textContent = `${device.device_name} (${device.serial_number})`;
                                deviceSelect.appendChild(option);
                            });
                        } else {
                            deviceSelect.innerHTML = '<option value="">-- Cihaz Bulunamadı --</option>';
                        }
                    } else {
                        deviceSelect.innerHTML = '<option value="">-- Hata: ' + data.message + ' --</option>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    deviceSelect.innerHTML = '<option value="">-- Hata: Cihazlar yüklenemedi --</option>';
                });
        });
        
        // Diğer kalibrasyon tipi seçildiğinde input göster
        const calibrationTypeSelect = document.getElementById('calibration_type');
        const otherTypeContainer = document.getElementById('other_type_container');
        const otherTypeInput = document.getElementById('other_type');
        
        calibrationTypeSelect.addEventListener('change', function() {
            if (this.value === 'other') {
                otherTypeContainer.classList.remove('d-none');
                otherTypeInput.focus();
                
                // Diğer input değiştiğinde select değerini güncelle
                otherTypeInput.addEventListener('input', function() {
                    const option = document.createElement('option');
                    option.value = this.value;
                    option.textContent = this.value;
                    
                    // Eski "other" seçeneğini kaldır ve yenisini ekle
                    const oldOtherOption = calibrationTypeSelect.querySelector('option[value="other"]');
                    if (oldOtherOption) {
                        calibrationTypeSelect.removeChild(oldOtherOption);
                    }
                    
                    calibrationTypeSelect.appendChild(option);
                    calibrationTypeSelect.value = this.value;
                });
            } else {
                otherTypeContainer.classList.add('d-none');
            }
        });
    });
</script>

<?php require_once 'footer.php'; ?>