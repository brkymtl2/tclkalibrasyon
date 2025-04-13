<?php
require_once 'config.php';
require_once 'db_connect.php';
require_once 'functions.php';

// Session başlat
initSession();

// Kullanıcı giriş yapmış mı kontrol et
if (!isLoggedIn()) {
    $_SESSION['message'] = 'Bu sayfaya erişebilmek için giriş yapmalısınız.';
    $_SESSION['message_type'] = 'danger';
    header("Location: " . BASE_URL . "/login.php");
    exit;
}

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
        header("Location: " . BASE_URL . "/notifications.php");
        exit;
    }
}

// Tüm bildirimleri al (sayfalama ile)
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// Toplam kayıt sayısını al
$totalRecords = $db->queryValue("SELECT COUNT(*) FROM calibration_notifications WHERE user_id = $userId");

// Bildirimleri al
$notifications = $db->query("SELECT * FROM calibration_notifications 
                           WHERE user_id = $userId 
                           ORDER BY creation_date DESC 
                           LIMIT $offset, $recordsPerPage");

// Sayfalama bilgilerini al
$pagination = getPagination($page, $totalRecords, $recordsPerPage, BASE_URL . '/notifications.php');

// Tüm bildirimleri okundu olarak işaretle butonu
if (isset($_GET['mark_all_read']) && $_GET['mark_all_read'] == 1) {
    $db->query("UPDATE calibration_notifications SET is_read = 1 WHERE user_id = $userId AND is_read = 0");
    
    // Aktiviteyi logla
    logActivity($userId, 'Tüm Bildirimler Okundu', "Kullanıcı tüm bildirimlerini okundu olarak işaretledi.");
    
    $_SESSION['message'] = 'Tüm bildirimleriniz okundu olarak işaretlendi.';
    $_SESSION['message_type'] = 'success';
    
    header("Location: " . BASE_URL . "/notifications.php");
    exit;
}

$pageTitle = "Bildirimlerim - " . APP_NAME;
?>

<?php require_once 'header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Bildirimlerim</h1>
    
    <?php if ($db->queryValue("SELECT COUNT(*) FROM calibration_notifications WHERE user_id = $userId AND is_read = 0") > 0): ?>
        <a href="<?php echo BASE_URL; ?>/notifications.php?mark_all_read=1" class="btn btn-outline-success">
            <i class="fas fa-check-double me-2"></i> Tümünü Okundu İşaretle
        </a>
    <?php endif; ?>
</div>

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
            <a href="<?php echo BASE_URL; ?>/notifications.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i> Tüm Bildirimlere Dön
            </a>
        </div>
    </div>
<?php else: ?>
    <?php if (empty($notifications)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i> Henüz bildiriminiz bulunmamaktadır.
        </div>
    <?php else: ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Bildirimlerim</h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $notification): ?>
                        <a href="<?php echo BASE_URL; ?>/notifications.php?id=<?php echo $notification['notification_id']; ?>" 
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
            </div>
            
            <?php if ($totalRecords > $recordsPerPage): ?>
                <div class="card-footer">
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
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once 'footer.php'; ?>