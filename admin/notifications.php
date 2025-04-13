<?php
$pageTitle = "Bildirimler - " . APP_NAME;
require_once 'header.php';

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// Tek bir bildirim ID'si gelmiş mi kontrol et (bildirim detayı için)
$notificationId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($notificationId > 0) {
    // Bildirimi al
    $notification = $db->queryOne("SELECT * FROM calibration_notifications WHERE notification_id = $notificationId AND user_id = $userId");
    
    if ($notification) {
        // Bildirimi okundu olarak işaretle
        $db->query("UPDATE calibration_notifications SET is_read = 1 WHERE notification_id = $notificationId");
        
        // Aktiviteyi logla
        logActivity($userId, 'Bildirim Okundu', "Bildirim ID: $notificationId, Başlık: {$notification['title']}");
    } else {
        $_SESSION['message'] = 'Bildirim bulunamadı!';
        $_SESSION['message_type'] = 'danger';
        header("Location: " . BASE_URL . "/admin/notifications.php");
        exit;
    }
}

// Tüm bildirimleri al (sayfalama ile)
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$recordsPerPage = 20;
$offset = ($page - 1) * $recordsPerPage;

// Toplam kayıt sayısını al
$totalRecords = $db->queryValue("SELECT COUNT(*) FROM calibration_notifications WHERE user_id = $userId");

// Bildirimleri al
$notifications = $db->query("SELECT * FROM calibration_notifications 
                           WHERE user_id = $userId 
                           ORDER BY creation_date DESC 
                           LIMIT $offset, $recordsPerPage");

// Sayfalama bilgilerini al
$pagination = getPagination($page, $totalRecords, $recordsPerPage, BASE_URL . '/admin/notifications.php');

// Tüm bildirimleri okundu olarak işaretle butonu
if (isset($_GET['mark_all_read']) && $_GET['mark_all_read'] == 1) {
    $db->query("UPDATE calibration_notifications SET is_read = 1 WHERE user_id = $userId AND is_read = 0");
    
    // Aktiviteyi logla
    logActivity($userId, 'Tüm Bildirimler Okundu', "Admin tüm bildirimlerini okundu olarak işaretledi.");
    
    $_SESSION['message'] = 'Tüm bildirimleriniz okundu olarak işaretlendi.';
    $_SESSION['message_type'] = 'success';
    
    header("Location: " . BASE_URL . "/admin/notifications.php");
    exit;
}

// Bildirim gönderme formu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !checkCSRF($_POST['csrf_token'])) {
        $_SESSION['message'] = 'Güvenlik hatası! Lütfen sayfayı yenileyip tekrar deneyin.';
        $_SESSION['message_type'] = 'danger';
        header("Location: " . BASE_URL . "/admin/notifications.php");
        exit;
    }
    
    $recipientId = intval($_POST['recipient_id']);
    $title = sanitize($_POST['title']);
    $message = sanitize($_POST['message']);
    
    // Basit doğrulama
    $errors = [];
    
    if (empty($title)) {
        $errors[] = 'Başlık gereklidir.';
    }
    
    if (empty($message)) {
        $errors[] = 'Mesaj gereklidir.';
    }
    
    if (!$errors) {
        if ($recipientId > 0) {
            // Tek kullanıcıya bildirim gönder
            $result = sendNotification($recipientId, $title, $message);
            
            if ($result) {
                $_SESSION['message'] = 'Bildirim başarıyla gönderildi!';
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Bildirim gönderilirken bir hata oluştu!';
                $_SESSION['message_type'] = 'danger';
            }
        } else {
            // Tüm kullanıcılara bildirim gönder
            $allUsers = $db->query("SELECT user_id FROM calibration_users WHERE status = 'approved'");
            $successCount = 0;
            
            foreach ($allUsers as $user) {
                if (sendNotification($user['user_id'], $title, $message)) {
                    $successCount++;
                }
            }
            
            $_SESSION['message'] = "Bildirim başarıyla $successCount kullanıcıya gönderildi!";
            $_SESSION['message_type'] = 'success';
        }
        
        // Aktiviteyi logla
        logActivity($userId, 'Bildirim Gönderildi', "Başlık: $title, Alıcı ID: " . ($recipientId > 0 ? $recipientId : 'Tüm Kullanıcılar'));
        
        header("Location: " . BASE_URL . "/admin/notifications.php");
        exit;
    }
}

// Kullanıcı listesini al (bildirim gönderme için)
$users = $db->query("SELECT user_id, username, company_name FROM calibration_users WHERE status = 'approved' ORDER BY company_name ASC");
?>

<h1 class="h3 mb-4">Bildirimler</h1>

<div class="row">
    <div class="col-lg-8">
        <?php if ($notificationId > 0 && $notification): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo htmlspecialchars($notification['title']); ?></h6>
                    <small class="text-muted"><?php echo formatDate($notification['creation_date'], 'd.m.Y H:i'); ?></small>
                </div>
                <div class="card-body">
                    <p><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                </div>
                <div class="card-footer">
                    <a href="<?php echo BASE_URL; ?>/admin/notifications.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i> Tüm Bildirimlere Dön
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Bildirimlerim</h6>
                    
                    <?php if ($db->queryValue("SELECT COUNT(*) FROM calibration_notifications WHERE user_id = $userId AND is_read = 0") > 0): ?>
                        <a href="<?php echo BASE_URL; ?>/admin/notifications.php?mark_all_read=1" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-check-double me-2"></i> Tümünü Okundu İşaretle
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($notifications)): ?>
                        <div class="p-3 text-center">
                            <p class="mb-0">Henüz bildiriminiz bulunmamaktadır.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($notifications as $notification): ?>
                                <a href="<?php echo BASE_URL; ?>/admin/notifications.php?id=<?php echo $notification['notification_id']; ?>" 
                                   class="list-group-item list-group-item-action <?php echo $notification['is_read'] ? '' : 'fw-bold'; ?>">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <h5 class="mb-1">
                                            <?php if (!$notification['is_read']): ?>
                                                <span class="badge bg-primary me-2">Yeni</span>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($notification['title']); ?>
                                        </h5>
                                        <small class="text-muted"><?php echo formatDate($notification['creation_date'], 'd.m.Y H:i'); ?></small>
                                    </div>
                                    <p class="mb-1 text-truncate"><?php echo htmlspecialchars($notification['message']); ?></p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($totalRecords > $recordsPerPage): ?>
                            <div class="p-3">
                                <nav aria-label="Bildirim sayfaları">
                                    <ul class="pagination justify-content-center mb-0">
                                        <?php foreach ($pagination['links'] as $link): ?>
                                            <li class="page-item <?php echo $link['active'] ?? false ? 'active' : ''; ?>">
                                                <a class="page-link" href="<?php echo $link['url']; ?>"><?php echo $link['text']; ?></a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Bildirim Gönder</h6>
            </div>
            <div class="card-body">
                <?php if (isset($errors) && !empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="send_notification" value="1">
                    
                    <div class="mb-3">
                        <label for="recipient_id" class="form-label">Alıcı</label>
                        <select class="form-select" id="recipient_id" name="recipient_id">
                            <option value="0">Tüm Kullanıcılar</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['user_id']; ?>">
                                    <?php echo htmlspecialchars($user['company_name']); ?> (<?php echo htmlspecialchars($user['username']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Başlık <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="message" class="form-label">Mesaj <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i> Bildirimi Gönder
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>