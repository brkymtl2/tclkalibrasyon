<?php
$pageTitle = "Kalibrasyon Belgesi Ekle - " . APP_NAME;
require_once 'header.php';

$db = Database::getInstance();

// Kalibrasyon ID'sini al
$calibrationId = isset($_GET['calibration_id']) ? intval($_GET['calibration_id']) : 0;

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

// Bu kalibrasyon için zaten belge var mı kontrol et
$existingDocument = $db->queryOne("SELECT * FROM calibration_documents WHERE calibration_id = $calibrationId");

// Form gönderilmiş mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !checkCSRF($_POST['csrf_token'])) {
        $_SESSION['message'] = 'Güvenlik hatası! Lütfen sayfayı yenileyip tekrar deneyin.';
        $_SESSION['message_type'] = 'danger';
        header("Location: " . BASE_URL . "/admin/add_document.php?calibration_id=$calibrationId");
        exit;
    }
    
    // Dosya yüklenmiş mi kontrol et
    if (!isset($_FILES['document']) || $_FILES['document']['error'] != 0) {
        $_SESSION['message'] = 'Lütfen bir belge seçin!';
        $_SESSION['message_type'] = 'danger';
        header("Location: " . BASE_URL . "/admin/add_document.php?calibration_id=$calibrationId");
        exit;
    }
    
    // Doküman klasörünü oluştur
    $uploadDir = '../uploads/documents/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Dosyayı yükle
    $fileUpload = uploadFile($_FILES['document'], $uploadDir);
    
    if ($fileUpload['status']) {
        // Eğer var olan bir belge güncellendiyse eski dosyayı sil
        if ($existingDocument) {
            // Eski dosyayı silme
            if (file_exists('../' . $existingDocument['file_path']) && is_file('../' . $existingDocument['file_path'])) {
                unlink('../' . $existingDocument['file_path']);
            }
            
            // Kaydı güncelle
            $query = "UPDATE calibration_documents SET 
                        file_name = '" . $db->escape($fileUpload['file_name']) . "',
                        file_path = '" . $db->escape($uploadDir . $fileUpload['file_name']) . "',
                        upload_date = NOW(),
                        uploaded_by = " . $_SESSION['user_id'] . "
                      WHERE document_id = " . $existingDocument['document_id'];
        } else {
            // Yeni kayıt ekle
            $query = "INSERT INTO calibration_documents (calibration_id, file_name, file_path, uploaded_by) 
                      VALUES (
                          $calibrationId,
                          '" . $db->escape($fileUpload['file_name']) . "',
                          '" . $db->escape($uploadDir . $fileUpload['file_name']) . "',
                          " . $_SESSION['user_id'] . "
                      )";
        }
        
        $result = $db->query($query);
        
        if ($result) {
            // Kullanıcıya bildirim gönder
            $title = 'Kalibrasyon Belgesi Eklendi';
            $message = 'Kalibrasyon belgeniz sisteme yüklenmiştir. Kalibrasyon No: ' . $calibration['calibration_number'];
            sendNotification($calibration['user_id'], $title, $message);
            
            // Aktiviteyi logla
            logActivity($_SESSION['user_id'], 'Belge Yükleme', "Kalibrasyon belgesi yüklendi: {$calibration['calibration_number']} - {$calibration['device_name']} - {$calibration['company_name']}");
            
            $_SESSION['message'] = 'Belge başarıyla yüklendi!';
            $_SESSION['message_type'] = 'success';
            header("Location: " . BASE_URL . "/admin/documents.php");
            exit;
        } else {
            $_SESSION['message'] = 'Belge kaydedilirken bir hata oluştu: ' . $db->error();
            $_SESSION['message_type'] = 'danger';
        }
    } else {
        $_SESSION['message'] = 'Dosya yüklenirken bir hata oluştu: ' . $fileUpload['message'];
        $_SESSION['message_type'] = 'danger';
    }
}
?>

<h1 class="h3 mb-4">Kalibrasyon Belgesi Ekle</h1>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Kalibrasyon Bilgileri</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Kalibrasyon No:</strong> <?php echo htmlspecialchars($calibration['calibration_number']); ?></p>
                <p><strong>Şirket:</strong> <?php echo htmlspecialchars($calibration['company_name']); ?></p>
                <p><strong>Cihaz:</strong> <?php echo htmlspecialchars($calibration['device_name']); ?></p>
                <p><strong>Seri No:</strong> <?php echo htmlspecialchars($calibration['serial_number']); ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Kalibrasyon Tipi:</strong> <?php echo htmlspecialchars($calibration['calibration_type']); ?></p>
                <p><strong>Kalibrasyon Tarihi:</strong> <?php echo formatDate($calibration['calibration_date']); ?></p>
                <p><strong>Sonraki Kalibrasyon:</strong> <?php echo formatDate($calibration['next_calibration_date']); ?></p>
                <p><strong>Standardı:</strong> <?php echo htmlspecialchars($calibration['calibration_standard'] ?? 'Belirtilmemiş'); ?></p>
            </div>
        </div>
        
        <?php if ($existingDocument): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> Bu kalibrasyon için zaten bir belge yüklenmiş. Yeni belge yüklerseniz mevcut belgenin üzerine yazılacaktır.
                <p class="mt-2">
                    <strong>Mevcut Belge:</strong> 
                    <a href="<?php echo BASE_URL . '/' . $existingDocument['file_path']; ?>" target="_blank" class="btn btn-sm btn-primary">
                        <i class="fas fa-file-pdf me-1"></i> <?php echo htmlspecialchars($existingDocument['file_name']); ?>
                    </a>
                    <small class="text-muted ms-2">
                        Yüklenme Tarihi: <?php echo formatDate($existingDocument['upload_date'], 'd.m.Y H:i'); ?>
                    </small>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Belge Yükleme</h6>
    </div>
    <div class="card-body">
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?calibration_id=' . $calibrationId; ?>" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="mb-3">
                <label for="document" class="form-label">Kalibrasyon Belgesi (PDF) <span class="text-danger">*</span></label>
                <input type="file" class="form-control" id="document" name="document" accept="application/pdf" required>
                <div class="form-text">Yalnızca PDF dosyaları kabul edilmektedir. Maksimum dosya boyutu: 10MB</div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-upload me-2"></i> Belgeyi Yükle
            </button>
            <a href="<?php echo BASE_URL; ?>/admin/documents.php" class="btn btn-secondary">
                <i class="fas fa-times me-2"></i> İptal
            </a>
        </form>
    </div>
</div>

<?php require_once 'footer.php'; ?>