<?php
$pageTitle = "Sistem Logları - " . APP_NAME;
require_once 'header.php';

$db = Database::getInstance();

// Filtreleme parametreleri
$userFilter = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$actionFilter = isset($_GET['action']) ? sanitize($_GET['action']) : '';
$dateFrom = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Sayfalama
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$recordsPerPage = 50;
$offset = ($page - 1) * $recordsPerPage;

// SQL sorgusu oluşturma
$sqlFilters = [];
$params = [];

// Temel sorgu
$sql = "SELECT 
            l.*,
            u.username,
            u.company_name
        FROM 
            calibration_logs l
        LEFT JOIN 
            calibration_users u ON l.user_id = u.user_id
        WHERE 1=1";

// Filtreler
if ($userFilter > 0) {
    $sqlFilters[] = "l.user_id = ?";
    $params[] = $userFilter;
}

if (!empty($actionFilter)) {
    $sqlFilters[] = "l.action = ?";
    $params[] = $actionFilter;
}

if (!empty($dateFrom)) {
    $sqlFilters[] = "l.log_date >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}

if (!empty($dateTo)) {
    $sqlFilters[] = "l.log_date <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

if (!empty($search)) {
    $sqlFilters[] = "(l.description LIKE ? OR l.action LIKE ? OR u.username LIKE ? OR u.company_name LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Filtreleri SQL'e ekle
if (!empty($sqlFilters)) {
    $sql .= " AND " . implode(" AND ", $sqlFilters);
}

// Toplam kayıt sayısı
$countSql = "SELECT COUNT(*) as total FROM (" . $sql . ") as sub";
$countStmt = $db->prepare($countSql);

// Parametre tipleri
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $countStmt->bind_param($types, ...$params);
}

$countStmt->execute();
$countResult = $countStmt->get_result();
$totalRecords = $countResult->fetch_assoc()['total'];
$countStmt->close();

// Sayfalama
$totalPages = ceil($totalRecords / $recordsPerPage);
$page = min($page, max(1, $totalPages));

// Sorgu için sayfalama ekle
$sql .= " ORDER BY l.log_date DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $recordsPerPage;

// Sorguyu çalıştır
$stmt = $db->prepare($sql);

// Parametre tipleri (sayfalama için iki integer ekledik)
if (!empty($params)) {
    $types = str_repeat('s', count($params) - 2) . 'ii';
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$logs = [];

while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}

$stmt->close();

// Sayfalama bağlantıları
$pagination = getPagination($page, $totalRecords, $recordsPerPage, BASE_URL . '/admin/logs.php');

// Kullanıcı listesi (filtreleme için)
$users = $db->query("SELECT user_id, username, company_name FROM calibration_users ORDER BY username ASC");

// Aksiyon listesi (filtreleme için)
$actions = $db->query("SELECT DISTINCT action FROM calibration_logs ORDER BY action ASC");

// Log temizleme işlemi
if (isset($_POST['clean_logs']) && $_POST['clean_logs'] == 1) {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !checkCSRF($_POST['csrf_token'])) {
        $_SESSION['message'] = 'Güvenlik hatası! Lütfen sayfayı yenileyip tekrar deneyin.';
        $_SESSION['message_type'] = 'danger';
        header("Location: " . BASE_URL . "/admin/logs.php");
        exit;
    }
    
    $days = isset($_POST['days']) ? intval($_POST['days']) : 90;
    
    if ($days < 1) {
        $_SESSION['message'] = 'Geçersiz gün sayısı!';
        $_SESSION['message_type'] = 'danger';
    } else {
        // Belirtilen günden eski kayıtları sil
        $cleanupDate = date('Y-m-d H:i:s', strtotime("-$days days"));
        $cleanupSql = "DELETE FROM calibration_logs WHERE log_date < ?";
        $cleanupStmt = $db->prepare($cleanupSql);
        $cleanupStmt->bind_param('s', $cleanupDate);
        $cleanupStmt->execute();
        $affectedRows = $cleanupStmt->affected_rows;
        $cleanupStmt->close();
        
        // Aktiviteyi logla
        logActivity($_SESSION['user_id'], 'Log Temizleme', "$days günden eski $affectedRows kayıt silindi.");
        
        $_SESSION['message'] = "$days günden eski $affectedRows log kaydı başarıyla silindi!";
        $_SESSION['message_type'] = 'success';
    }
    
    header("Location: " . BASE_URL . "/admin/logs.php");
    exit;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Sistem Logları</h1>
    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#cleanLogsModal">
        <i class="fas fa-trash-alt me-2"></i> Eski Logları Temizle
    </button>
</div>

<!-- Filtreleme Kartı -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filtreleme</h6>
    </div>
    <div class="card-body">
        <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row g-3">
            <div class="col-md-3">
                <label for="user_id" class="form-label">Kullanıcı</label>
                <select class="form-select" id="user_id" name="user_id">
                    <option value="">Tümü</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['user_id']; ?>" <?php echo $userFilter == $user['user_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['company_name']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="action" class="form-label">İşlem</label>
                <select class="form-select" id="action" name="action">
                    <option value="">Tümü</option>
                    <?php foreach ($actions as $action): ?>
                        <option value="<?php echo htmlspecialchars($action['action']); ?>" <?php echo $actionFilter === $action['action'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($action['action']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="date_from" class="form-label">Başlangıç Tarihi</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>">
            </div>
            <div class="col-md-2">
                <label for="date_to" class="form-label">Bitiş Tarihi</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $dateTo; ?>">
            </div>
            <div class="col-md-2">
                <label for="search" class="form-label">Arama</label>
                <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Arama...">
            </div>
            <div class="col-12 mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter me-2"></i> Filtrele
                </button>
                <a href="<?php echo BASE_URL; ?>/admin/logs.php" class="btn btn-secondary">
                    <i class="fas fa-sync-alt me-2"></i> Sıfırla
                </a>
                <button type="button" class="btn btn-info" id="printBtn">
                    <i class="fas fa-print me-2"></i> Yazdır
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Loglar Tablosu -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Log Listesi</h6>
        <span>Toplam: <?php echo $totalRecords; ?> kayıt</span>
    </div>
    <div class="card-body">
        <?php if (empty($logs)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> Gösterilecek log kaydı bulunamadı.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tarih</th>
                            <th>Kullanıcı</th>
                            <th>İşlem</th>
                            <th>Açıklama</th>
                            <th>IP Adresi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo $log['log_id']; ?></td>
                                <td><?php echo formatDate($log['log_date'], 'd.m.Y H:i:s'); ?></td>
                                <td>
                                    <?php if ($log['user_id']): ?>
                                        <?php echo htmlspecialchars($log['username']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Sistem</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($log['action']); ?></td>
                                <td><?php echo htmlspecialchars($log['description']); ?></td>
                                <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Sayfalama -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Sayfalama">
                    <ul class="pagination justify-content-center mt-4">
                        <?php foreach ($pagination['links'] as $link): ?>
                            <li class="page-item <?php echo isset($link['active']) && $link['active'] ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo $link['url']; ?><?php echo !empty($userFilter) ? '&user_id='.$userFilter : ''; ?><?php echo !empty($actionFilter) ? '&action='.$actionFilter : ''; ?><?php echo !empty($dateFrom) ? '&date_from='.$dateFrom : ''; ?><?php echo !empty($dateTo) ? '&date_to='.$dateTo : ''; ?><?php echo !empty($search) ? '&search='.$search : ''; ?>">
                                    <?php echo $link['text']; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Log Temizleme Modal -->
<div class="modal fade" id="cleanLogsModal" tabindex="-1" aria-labelledby="cleanLogsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="clean_logs" value="1">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="cleanLogsModalLabel">Eski Logları Temizle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i> <strong>Dikkat!</strong> Bu işlem belirtilen günden eski log kayıtlarını kalıcı olarak silecektir.
                    </div>
                    
                    <div class="mb-3">
                        <label for="days" class="form-label">Kaç günden eski loglar silinsin?</label>
                        <select class="form-select" id="days" name="days">
                            <option value="30">30 günden eski</option>
                            <option value="60">60 günden eski</option>
                            <option value="90" selected>90 günden eski</option>
                            <option value="180">180 günden eski</option>
                            <option value="365">1 yıldan eski</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt me-2"></i> Logları Temizle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Yazdırma işlemi
        document.getElementById('printBtn').addEventListener('click', function() {
            window.print();
        });
    });
</script>

<?php require_once 'footer.php'; ?>