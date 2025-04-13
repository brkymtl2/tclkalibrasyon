<?php
$pageTitle = "Cihaz Düzenle - " . APP_NAME;
require_once 'header.php';

$db = Database::getInstance();

// Cihaz ID'sini al
$deviceId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($deviceId <= 0) {
    $_SESSION['message'] = 'Geçersiz cihaz ID!';
    $_SESSION['message_type'] = 'danger';
    header("Location: " . BASE_URL . "/admin/devices.php");
    exit;
}

// Cihaz bilgilerini al
$device = $db->queryOne("SELECT 
                            d.*, 
                            u.username,
                            u.company_name
                        FROM 
                            calibration_devices d
                        JOIN 
                            calibration_users u ON d.user_id = u.user_id
                        WHERE 
                            d.device_id = $deviceId");

if (!$device) {
    $_SESSION['message'] = 'Cihaz bulunamadı!';
    $_SESSION['message_type'] = 'danger';
    header("Location: " . BASE_URL . "/admin/devices.php");
    exit;
}

// Kullanıcı listesini al
$users = $db->query("SELECT user_id, username, company_name FROM calibration_users WHERE status = 'approved' AND user_type = 'user' ORDER BY company_name ASC");

// Form gönderilmiş mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !checkCSRF($_POST['csrf_token'])) {
        $_SESSION['message'] = 'Güvenlik hatası! Lütfen sayfayı yenileyip tekrar deneyin.';
        $_SESSION['message_type'] = 'danger';
        header("Location: " . BASE_URL . "/admin/edit_device.php?id=$deviceId");
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
    
    // Hata yoksa kaydı güncelle
    if (empty($errors)) {
        // Kullanıcı değişti mi kontrol et
        $userChanged = $userId !== $device['user_id'];
        
        // Cihaza ait kalibrasyon var mı kontrol et (kullanıcı değiştiyse)
        if ($userChanged) {
            $calibrationCount = $db->queryValue("SELECT COUNT(*) FROM calibration_records WHERE device_id = $deviceId");
            
            if ($calibrationCount > 0) {
                $errors[] = 'Bu cihaza ait kalibrasyon kaydı olduğu için kullanıcısı değiştirilemez!';
            }
        }
        
        if (empty($errors)) {
            $query = "UPDATE calibration_devices SET 
                     user_id = $userId,
                     device_name = '" . $db->escape($deviceName) . "', 
                     device_model = '" . $db->escape($deviceModel) . "', 
                     serial_number = '" . $db->escape($serialNumber) . "', 
                     description = '" . $db->escape($description) . "'
                  WHERE device_id = $deviceId";
                  
            $result = $db->query($query);
            
            if ($result) {
                // Kullanıcıya bildirim gönder
                $title = 'Cihaz Kaydı Güncellendi';
                $message = 'Cihaz bilgileriniz güncellendi: ' . $deviceName . ' (' . $serialNumber . ')';
                sendNotification($userId, $title, $message);
                
                // Aktiviteyi logla
                $user = getUserInfo($userId);
                logActivity($_SESSION['user_id'], 'Cihaz Güncelleme', "Cihaz güncellendi: {$deviceName} - {$serialNumber} - {$user['company_name']}");
                
                $_SESSION['message'] = 'Cihaz başarıyla güncellendi!';
                $_SESSION['message_type'] = 'success';
                
                header("Location: " . BASE_URL . "/admin/devices.php");
                exit;
            } else {
                $errors[] = 'Cihaz güncellenirken bir hata oluştu: ' . $db->error();
            }
        }
    }
}
?>

<h1 class="h3 mb-4">Cihaz Düzenle</h1>

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
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?id=' . $deviceId); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="user_id" class="form-label">Kullanıcı/Şirket <span class="text-danger">*</span></label>
                    <select class="form-select" id="user_id" name="user_id" required>
                        <option value="">-- Kullanıcı Seçin --</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['user_id']; ?>" <?php echo $device['user_id'] == $user['user_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['company_name']); ?> (<?php echo htmlspecialchars($user['username']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="device_name" class="form-label">Cihaz Adı <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="device_name" name="device_name" value="<?php echo htmlspecialchars($device['device_name']); ?>" required>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="device_model" class="form-label">Cihaz Modeli</label>
                    <input type="text" class="form-control" id="device_model" name="device_model" value="<?php echo htmlspecialchars($device['device_model']); ?>">
                </div>
                <div class="col-md-6">
                    <label for="serial_number" class="form-label">Seri Numarası <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="serial_number" name="serial_number" value="<?php echo htmlspecialchars($device['serial_number']); ?>" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label">Açıklama</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($device['description']); ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-2"></i> Kaydet
            </button>
            <a href="<?php echo BASE_URL; ?>/admin/devices.php" class="btn btn-secondary">
                <i class="fas fa-times me-2"></i> İptal
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/add_calibration.php?device_id=<?php echo $deviceId; ?>" class="btn btn-success">
                <i class="fas fa-plus-circle me-2"></i> Kalibrasyon Ekle
            </a>
        </form>
    </div>
</div>

<!-- Cihaz Kalibrasyon Geçmişi -->
<?php
// Cihazın kalibrasyon geçmişini al
$calibrations = $db->query("SELECT 
                                cr.calibration_id, 
                                cr.calibration_number, 
                                cr.calibration_date, 
                                cr.next_calibration_date, 
                                cr.calibration_type, 
                                cr.status,
                                (SELECT COUNT(*) FROM calibration_documents WHERE calibration_id = cr.calibration_id) as has_document
                            FROM 
                                calibration_records cr
                            WHERE 
                                cr.device_id = $deviceId
                            ORDER BY 
                                cr.calibration_date DESC");
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Kalibrasyon Geçmişi</h6>
    </div>
    <div class="card-body">
        <?php if (empty($calibrations)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> Bu cihaza ait kalibrasyon kaydı bulunmamaktadır.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered datatable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Kalibrasyon No</th>
                            <th>Kalibrasyon Tarihi</th>
                            <th>Sonraki Kalibrasyon</th>
                            <th>Kalibrasyon Tipi</th>
                            <th>Belge</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($calibrations as $calibration): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($calibration['calibration_number']); ?></td>
                                <td><?php echo formatDate($calibration['calibration_date']); ?></td>
                                <td><?php echo formatDate($calibration['next_calibration_date']); ?></td>
                                <td><?php echo htmlspecialchars($calibration['calibration_type']); ?></td>
                                <td class="text-center">
                                    <?php if ($calibration['has_document']): ?>
                                        <span class="badge bg-success">Var</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Yok</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($calibration['status'] == 'completed'): ?>
                                        <span class="badge bg-success">Tamamlandı</span>
                                    <?php elseif ($calibration['status'] == 'pending'): ?>
                                        <span class="badge bg-warning">Bekliyor</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">İptal</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <a href="<?php echo BASE_URL; ?>/admin/edit_calibration.php?id=<?php echo $calibration['calibration_id']; ?>" class="btn btn-sm btn-primary" title="Düzenle">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <?php if (!$calibration['has_document']): ?>
                                            <a href="<?php echo BASE_URL; ?>/admin/add_document.php?calibration_id=<?php echo $calibration['calibration_id']; ?>" class="btn btn-sm btn-success" title="Belge Ekle">
                                                <i class="fas fa-file-upload"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="<?php echo BASE_URL; ?>/admin/view_document.php?calibration_id=<?php echo $calibration['calibration_id']; ?>" class="btn btn-sm btn-info" title="Belgeyi Görüntüle">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'footer.php'; ?>