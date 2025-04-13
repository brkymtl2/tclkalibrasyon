<?php
$pageTitle = "Kalibrasyon Düzenle - " . APP_NAME;
require_once 'header.php';

$db = Database::getInstance();

// Kalibrasyon ID'sini al
$calibrationId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($calibrationId <= 0) {
    $_SESSION['message'] = 'Geçersiz kalibrasyon ID!';
    $_SESSION['message_type'] = 'danger';
    header("Location: " . BASE_URL . "/admin/calibrations.php");
    exit;
}

// Kalibrasyon bilgilerini al
$calibration = $db->queryOne("SELECT 
                                cr.*, 
                                d.device_name, 
                                d.serial_number,
                                u.username,
                                u.company_name
                            FROM 
                                calibration_records cr
                            JOIN 
                                calibration_devices d ON cr.device_id = d.device_id
                            JOIN 
                                calibration_users u ON cr.user_id = u.user_id
                            WHERE 
                                cr.calibration_id = $calibrationId");

if (!$calibration) {
    $_SESSION['message'] = 'Kalibrasyon bulunamadı!';
    $_SESSION['message_type'] = 'danger';
    header("Location: " . BASE_URL . "/admin/calibrations.php");
    exit;
}

// Kalibrasyon tipleri listesi
$calibrationTypes = $db->query("SELECT DISTINCT calibration_type FROM calibration_pricing WHERE active = 1 ORDER BY calibration_type ASC");

// Form gönderilmiş mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !checkCSRF($_POST['csrf_token'])) {
        $_SESSION['message'] = 'Güvenlik hatası! Lütfen sayfayı yenileyip tekrar deneyin.';
        $_SESSION['message_type'] = 'danger';
        header("Location: " . BASE_URL . "/admin/edit_calibration.php?id=$calibrationId");
        exit;
    }
    
    // Form verilerini al
    $calibrationType = sanitize($_POST['calibration_type']);
    $calibrationNumber = sanitize($_POST['calibration_number']);
    $calibrationDate = sanitize($_POST['calibration_date']);
    $nextCalibrationDate = sanitize($_POST['next_calibration_date']);
    $calibrationStandard = sanitize($_POST['calibration_standard']);
    $status = sanitize($_POST['status']);
    $notes = sanitize($_POST['notes']);
    
    // Basit doğrulama
    $errors = [];
    
    if (empty($calibrationType)) {
        $errors[] = 'Kalibrasyon tipi gereklidir.';
    }
    
    if (empty($calibrationNumber)) {
        $errors[] = 'Kalibrasyon numarası gereklidir.';
    }
    
    if (empty($calibrationDate)) {
        $errors[] = 'Kalibrasyon tarihi gereklidir.';
    }
    
    // Hata yoksa kaydı güncelle
    if (empty($errors)) {
        $query = "UPDATE calibration_records SET 
                     calibration_type = '" . $db->escape($calibrationType) . "', 
                     calibration_number = '" . $db->escape($calibrationNumber) . "', 
                     calibration_date = '" . $db->escape($calibrationDate) . "', 
                     next_calibration_date = " . (!empty($nextCalibrationDate) ? "'" . $db->escape($nextCalibrationDate) . "'" : "NULL") . ", 
                     calibration_standard = '" . $db->escape($calibrationStandard) . "', 
                     status = '" . $db->escape($status) . "', 
                     notes = '" . $db->escape($notes) . "'
                  WHERE calibration_id = $calibrationId";
                  
        $result = $db->query($query);
        
        if ($result) {
            // Kullanıcıya bildirim gönder
            $title = 'Kalibrasyon Kaydı Güncellendi';
            $message = 'Kalibrasyon bilgileriniz güncellendi. Kalibrasyon No: ' . $calibrationNumber;
            sendNotification($calibration['user_id'], $title, $message);
            
            // Aktiviteyi logla
            logActivity($_SESSION['user_id'], 'Kalibrasyon Güncelleme', "Kalibrasyon güncellendi: {$calibrationNumber} - {$calibration['device_name']} - {$calibration['company_name']}");
            
            $_SESSION['message'] = 'Kalibrasyon başarıyla güncellendi!';
            $_SESSION['message_type'] = 'success';
            
            header("Location: " . BASE_URL . "/admin/calibrations.php");
            exit;
        } else {
            $errors[] = 'Kalibrasyon güncellenirken bir hata oluştu: ' . $db->error();
        }
    }
}
?>

<h1 class="h3 mb-4">Kalibrasyon Düzenle</h1>

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
        <div class="row mb-4">
            <div class="col-md-6">
                <h5>Cihaz Bilgileri</h5>
                <p><strong>Kullanıcı/Şirket:</strong> <?php echo htmlspecialchars($calibration['company_name']); ?> (<?php echo htmlspecialchars($calibration['username']); ?>)</p>
                <p><strong>Cihaz:</strong> <?php echo htmlspecialchars($calibration['device_name']); ?></p>
                <p><strong>Seri No:</strong> <?php echo htmlspecialchars($calibration['serial_number']); ?></p>
            </div>
            <div class="col-md-6">
                <h5>Kalibrasyon Detayları</h5>
                <p><strong>Kalibrasyon ID:</strong> <?php echo $calibration['calibration_id']; ?></p>
                <p><strong>Oluşturulma Tarihi:</strong> <?php echo formatDate($calibration['creation_date'], 'd.m.Y H:i'); ?></p>
                <p><strong>Oluşturan:</strong> <?php 
                    $createdBy = $db->queryOne("SELECT username FROM calibration_users WHERE user_id = " . $calibration['created_by']);
                    echo htmlspecialchars($createdBy['username'] ?? 'Bilinmiyor'); 
                ?></p>
            </div>
        </div>
        
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?id=' . $calibrationId); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="calibration_type" class="form-label">Kalibrasyon Tipi <span class="text-danger">*</span></label>
                    <select class="form-select" id="calibration_type" name="calibration_type" required>
                        <option value="">-- Kalibrasyon Tipi Seçin --</option>
                        <?php foreach ($calibrationTypes as $type): ?>
                            <option value="<?php echo htmlspecialchars($type['calibration_type']); ?>" <?php echo $calibration['calibration_type'] === $type['calibration_type'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['calibration_type']); ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="other" <?php echo !in_array($calibration['calibration_type'], array_column($calibrationTypes, 'calibration_type')) ? 'selected' : ''; ?>>
                            Diğer (Manuel Giriş)
                        </option>
                    </select>
                    <div id="other_type_container" class="mt-2 <?php echo !in_array($calibration['calibration_type'], array_column($calibrationTypes, 'calibration_type')) ? '' : 'd-none'; ?>">
                        <input type="text" class="form-control" id="other_type" placeholder="Kalibrasyon Tipini Girin" value="<?php echo !in_array($calibration['calibration_type'], array_column($calibrationTypes, 'calibration_type')) ? htmlspecialchars($calibration['calibration_type']) : ''; ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="calibration_number" class="form-label">Kalibrasyon Numarası <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="calibration_number" name="calibration_number" value="<?php echo htmlspecialchars($calibration['calibration_number']); ?>" required>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="calibration_date" class="form-label">Kalibrasyon Tarihi <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="calibration_date" name="calibration_date" value="<?php echo $calibration['calibration_date']; ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="next_calibration_date" class="form-label">Bir Sonraki Kalibrasyon Tarihi</label>
                    <input type="date" class="form-control" id="next_calibration_date" name="next_calibration_date" value="<?php echo $calibration['next_calibration_date']; ?>">
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="calibration_standard" class="form-label">Kalibrasyon Standardı</label>
                    <input type="text" class="form-control" id="calibration_standard" name="calibration_standard" value="<?php echo htmlspecialchars($calibration['calibration_standard']); ?>">
                </div>
                <div class="col-md-6">
                    <label for="status" class="form-label">Durum <span class="text-danger">*</span></label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="pending" <?php echo $calibration['status'] === 'pending' ? 'selected' : ''; ?>>Bekliyor</option>
                        <option value="completed" <?php echo $calibration['status'] === 'completed' ? 'selected' : ''; ?>>Tamamlandı</option>
                        <option value="canceled" <?php echo $calibration['status'] === 'canceled' ? 'selected' : ''; ?>>İptal Edildi</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="notes" class="form-label">Notlar</label>
                <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($calibration['notes']); ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-2"></i> Kaydet
            </button>
            <a href="<?php echo BASE_URL; ?>/admin/calibrations.php" class="btn btn-secondary">
                <i class="fas fa-times me-2"></i> İptal
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/add_document.php?calibration_id=<?php echo $calibrationId; ?>" class="btn btn-success">
                <i class="fas fa-file-upload me-2"></i> Belge Yükle
            </a>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
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
