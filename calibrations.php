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

// Admin kullanıcısını admin paneline yönlendir
if (isAdmin()) {
    header("Location: " . BASE_URL . "/admin/calibrations.php");
    exit;
}

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// Filtreleme parametreleri
$statusFilter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$dateFrom = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// SQL sorgusu oluşturma
$sqlFilters = [];
$params = [];

// Temel sorgu
$sql = "SELECT 
            cr.*, 
            d.device_name, 
            d.serial_number,
            (SELECT COUNT(*) FROM calibration_documents WHERE calibration_id = cr.calibration_id) as has_document
        FROM 
            calibration_records cr
        JOIN 
            calibration_devices d ON cr.device_id = d.device_id
        WHERE 
            cr.user_id = ?";
$params[] = $userId;

// Filtreler
if (!empty($statusFilter)) {
    $sqlFilters[] = "cr.status = ?";
    $params[] = $statusFilter;
}

if (!empty($dateFrom)) {
    $sqlFilters[] = "cr.calibration_date >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $sqlFilters[] = "cr.calibration_date <= ?";
    $params[] = $dateTo;
}

if (!empty($search)) {
    $sqlFilters[] = "(cr.calibration_number LIKE ? OR d.device_name LIKE ? OR d.serial_number LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Filtreleri SQL'e ekle
if (!empty($sqlFilters)) {
    $sql .= " AND " . implode(" AND ", $sqlFilters);
}

// Sıralama
$sql .= " ORDER BY cr.calibration_date DESC";

// Prepare statement oluştur (güvenlik için)
$stmt = $db->prepare($sql);

// Parametreleri bind et
if (!empty($params)) {
    $types = str_repeat('s', count($params)); // Tüm parametreler string
    $stmt->bind_param($types, ...$params);
}

// Sorguyu çalıştır
$stmt->execute();
$result = $stmt->get_result();
$calibrations = [];

while ($row = $result->fetch_assoc()) {
    $calibrations[] = $row;
}

$stmt->close();

$pageTitle = "Kalibrasyonlarım - " . APP_NAME;
?>

<?php require_once 'header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Kalibrasyonlarım</h1>
</div>

<!-- Filtreleme Kartı -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filtreleme</h6>
    </div>
    <div class="card-body">
        <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row g-3">
            <div class="col-md-3">
                <label for="status" class="form-label">Durum</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tümü</option>
                    <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Bekliyor</option>
                    <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Tamamlandı</option>
                    <option value="canceled" <?php echo $statusFilter === 'canceled' ? 'selected' : ''; ?>>İptal Edildi</option>
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
            <div class="col-md-3">
                <label for="search" class="form-label">Arama</label>
                <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Arama...">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-2"></i> Filtrele
                </button>
            </div>
            <div class="col-12 mt-3">
                <a href="<?php echo BASE_URL; ?>/calibrations.php" class="btn btn-secondary">
                    <i class="fas fa-sync-alt me-2"></i> Sıfırla
                </a>
                <button type="button" class="btn btn-info" id="printBtn">
                    <i class="fas fa-print me-2"></i> Yazdır
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Kalibrasyonlar Tablosu -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Kalibrasyon Listem</h6>
    </div>
    <div class="card-body">
        <?php if (empty($calibrations)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> Gösterilecek kalibrasyon bulunamadı.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Kalibrasyon No</th>
                            <th>Cihaz</th>
                            <th>Seri No</th>
                            <th>Kalibrasyon Tarihi</th>
                            <th>Sonraki Kalibrasyon</th>
                            <th>Tip</th>
                            <th>Belge</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($calibrations as $calibration): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($calibration['calibration_number']); ?></td>
                                <td><?php echo htmlspecialchars($calibration['device_name']); ?></td>
                                <td><?php echo htmlspecialchars($calibration['serial_number']); ?></td>
                                <td><?php echo formatDate($calibration['calibration_date']); ?></td>
                                <td>
                                    <?php 
                                    $nextDate = $calibration['next_calibration_date'];
                                    echo formatDate($nextDate);
                                    
                                    // Yaklaşan kalibrasyon için uyarı
                                    if ($nextDate) {
                                        $daysRemaining = (strtotime($nextDate) - time()) / (60 * 60 * 24);
                                        $daysRemaining = round($daysRemaining);
                                        
                                        if ($daysRemaining <= 0) {
                                            echo ' <span class="badge bg-danger">Gecikti!</span>';
                                        } elseif ($daysRemaining <= 30) {
                                            echo ' <span class="badge bg-warning">' . $daysRemaining . ' gün kaldı</span>';
                                        }
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($calibration['calibration_type']); ?></td>
                                <td class="text-center">
                                    <?php if ($calibration['has_document']): ?>
                                        <a href="<?php echo BASE_URL; ?>/view_document.php?id=<?php echo $calibration['calibration_id']; ?>" class="btn btn-sm btn-success" target="_blank">
                                            <i class="fas fa-file-pdf me-1"></i> Görüntüle
                                        </a>
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
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Yaklaşan Kalibrasyonlar -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-warning">Yaklaşan Kalibrasyonlar (30 gün)</h6>
    </div>
    <div class="card-body">
        <?php 
        $upcomingCalibrations = getUpcomingCalibrations($userId, 30);
        
        if (empty($upcomingCalibrations)): 
        ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> 30 gün içinde yaklaşan kalibrasyon bulunmamaktadır.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Cihaz</th>
                            <th>Seri No</th>
                            <th>Kalibrasyon Tarihi</th>
                            <th>Kalan Gün</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingCalibrations as $calibration): 
                            $daysRemaining = (strtotime($calibration['next_calibration_date']) - time()) / (60 * 60 * 24);
                            $daysRemaining = round($daysRemaining);
                            $alertClass = $daysRemaining <= 7 ? 'table-danger' : ($daysRemaining <= 15 ? 'table-warning' : '');
                        ?>
                            <tr class="<?php echo $alertClass; ?>">
                                <td><?php echo htmlspecialchars($calibration['device_name']); ?></td>
                                <td><?php echo htmlspecialchars($calibration['serial_number']); ?></td>
                                <td><?php echo formatDate($calibration['next_calibration_date']); ?></td>
                                <td>
                                    <?php if ($daysRemaining <= 0): ?>
                                        <span class="badge bg-danger">Bugün</span>
                                    <?php else: ?>
                                        <?php echo $daysRemaining; ?> gün
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
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