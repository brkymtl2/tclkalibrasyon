<?php
$pageTitle = "Cihaz Ekle - " . APP_NAME;
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
        header("Location: " . BASE_URL . "/admin/add_device.php");
        exit;
    }
    
    // Form verilerini al
    $userId = intval($_POST['user_id']);
    $deviceName = sanitize($_POST['device_name']);
    $deviceModel = sanitize($_POST['device_model']);
    $serialNumber = sanitize($_POST['serial_number']);
    $description = sanitize($_POST['description']);
    
    // Basit doğrulama
    $errors = [];
    
    if (empty($userId)) {
        $errors[] = 'Kullanıcı seçilmelidir.';
    }
    
    if (empty($deviceName)) {
        $errors[] = 'Cihaz adı gereklidir.';
    }
    
    if (empty($serialNumber)) {
        $errors[] = 'Seri numarası gereklidir.';
    }
    
    // Hata yoksa kaydı oluştur
    if (empty($errors)) {
        $query = "INSERT INTO calibration_devices (user_id, device_name, device_model, serial_number, description) 
                  VALUES (
                     $userId, 
                     '" . $db->escape($deviceName) . "', 
                     '" . $db->escape($deviceModel) . "', 
                     '" . $db->escape($serialNumber) . "', 
                     '" . $db->escape($description) . "'
                  )";
                  
        $result = $db->query($query);
        
        if ($result) {
            $deviceId = $db->lastInsertId();
            
            // Kullanıcıya bildirim gönder
            $title = 'Yeni Cihaz Eklendi';
            $message = 'Sistemimize yeni bir cihaz kaydedildi: ' . $deviceName . ' (' . $serialNumber . ')';
            sendNotification($userId, $title, $message);
            
            // Aktiviteyi logla
            $user = getUserInfo($userId);
            logActivity($_SESSION['user_id'], 'Cihaz Ekleme', "Yeni cihaz eklendi: {$deviceName} - {$serialNumber} - {$user['company_name']}");
            
            $_SESSION['message'] = 'Cihaz başarıyla eklendi!';
            $_SESSION['message_type'] = 'success';
            
            header("Location: " . BASE_URL . "/admin/devices.php");
            exit;
        } else {
            $errors[] = 'Cihaz eklenirken bir hata oluştu: ' . $db->error();
        }
    }
}
?>

<h1 class="h3 mb-4">Cihaz Ekle</h1>

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
        <h6 class="m-0 font-weight-bold text-primary">Cihaz Bilgileri</h6>
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
                    <label for="device_name" class="form-label">Cihaz Adı <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="device_name" name="device_name" required>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="device_model" class="form-label">Cihaz Modeli</label>
                    <input type="text" class="form-control" id="device_model" name="device_model">
                </div>
                <div class="col-md-6">
                    <label for="serial_number" class="form-label">Seri Numarası <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="serial_number" name="serial_number" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label">Açıklama</label>
                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-2"></i> Cihazı Kaydet
            </button>
            <a href="<?php echo BASE_URL; ?>/admin/devices.php" class="btn btn-secondary">
                <i class="fas fa-times me-2"></i> İptal
            </a>
        </form>
    </div>
</div>

<?php require_once 'footer.php'; ?>