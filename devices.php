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
    header("Location: " . BASE_URL . "/admin/devices.php");
    exit;
}

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// Arama filtresi
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// SQL sorgusu oluşturma
$sql = "SELECT 
            d.*, 
            (SELECT COUNT(*) FROM calibration_records WHERE device_id = d.device_id) as calibration_count,
            (SELECT MAX(calibration_date) FROM calibration_records WHERE device_id = d.device_id) as last_calibration,
            (SELECT calibration_date FROM calibration_records WHERE device_id = d.device_id ORDER BY calibration_date DESC LIMIT 1) as last_calibration_date,
            (SELECT next_calibration_date FROM calibration_records WHERE device_id = d.device_id ORDER BY calibration_date DESC LIMIT 1) as next_calibration_date
        FROM 
            calibration_devices d
        WHERE 
            d.user_id = ?";

// Arama filtresi
$params = [$userId];

if (!empty($search)) {
    $sql .= " AND (d.device_name LIKE ? OR d.serial_number LIKE ? OR d.device_model LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Sıralama
$sql .= " ORDER BY d.device_name ASC";

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
$devices = [];

while ($row = $result->fetch_assoc()) {
    $devices[] = $row;
}

$stmt->close();

$pageTitle = "Cihazlarım - " . APP_NAME;
?>

<?php require_once 'header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Cihazlarım</h1>
</div>

<!-- Filtreleme Kartı -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Arama</h6>
    </div>
    <div class="card-body">
        <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row g-3">
            <div class="col-md-8">
                <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cihaz adı, seri no veya model ara...">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-2"></i> Ara
                </button>
            </div>
            <div class="col-12 mt-3">
                <a href="<?php echo BASE_URL; ?>/devices.php" class="btn btn-secondary">
                    <i class="fas fa-sync-alt me-2"></i> Sıfırla
                </a>
                <button type="button" class="btn btn-info" id="printBtn">
                    <i class="fas fa-print me-2"></i> Yazdır
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Cihazlar Tablosu -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Cihaz Listem</h6>
    </div>
    <div class="card-body">
        <?php if (empty($devices)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> Gösterilecek cihaz bulunamadı.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Cihaz Adı</th>
                            <th>Model</th>
                            <th>Seri No</th>
                            <th>Son Kalibrasyon</th>
                            <th>Sonraki Kalibrasyon</th>
                            <th>Kalibrasyon Sayısı</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($devices as $device): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($device['device_name']); ?></td>
                                <td><?php echo htmlspecialchars($device['device_model'] ?? 'Belirtilmemiş'); ?></td>
                                <td><?php echo htmlspecialchars($device['serial_number']); ?></td>
                                <td><?php echo $device['last_calibration_date'] ? formatDate($device['last_calibration_date']) : 'Yok'; ?></td>
                                <td>
                                    <?php 
                                    if ($device['next_calibration_date']) {
                                        echo formatDate($device['next_calibration_date']);
                                        
                                        // Yaklaşan kalibrasyon için uyarı
                                        $nextDate = $device['next_calibration_date'];
                                        $daysRemaining = (strtotime($nextDate) - time()) / (60 * 60 * 24);
                                        $daysRemaining = round($daysRemaining);
                                        
                                        if ($daysRemaining <= 0) {
                                            echo ' <span class="badge bg-danger">Gecikti!</span>';
                                        } elseif ($daysRemaining <= 30) {
                                            echo ' <span class="badge bg-warning">' . $daysRemaining . ' gün kaldı</span>';
                                        }
                                    } else {
                                        echo 'Belirtilmemiş';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>/device_calibrations.php?id=<?php echo $device['device_id']; ?>" class="text-primary">
                                        <?php echo $device['calibration_count']; ?> kalibrasyon
                                    </a>
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
        <h6 class="m-0 font-weight-bold text-warning">Kalibrasyon Durumu</h6>
    </div>
    <div class="card-body">
        <?php
        // Kalibrasyon durumu istatistikleri
        $upToDate = 0;
        $needsCalibration = 0;
        $expired = 0;
        $noCalibration = 0;
        
        foreach ($devices as $device) {
            if (!$device['next_calibration_date']) {
                $noCalibration++;
            } else {
                $daysRemaining = (strtotime($device['next_calibration_date']) - time()) / (60 * 60 * 24);
                $daysRemaining = round($daysRemaining);
                
                if ($daysRemaining <= 0) {
                    $expired++;
                } elseif ($daysRemaining <= 30) {
                    $needsCalibration++;
                } else {
                    $upToDate++;
                }
            }
        }
        
        $totalDevices = count($devices);
        $upToDatePercent = $totalDevices > 0 ? round(($upToDate / $totalDevices) * 100) : 0;
        $needsCalibrationPercent = $totalDevices > 0 ? round(($needsCalibration / $totalDevices) * 100) : 0;
        $expiredPercent = $totalDevices > 0 ? round(($expired / $totalDevices) * 100) : 0;
        $noCalibrationPercent = $totalDevices > 0 ? round(($noCalibration / $totalDevices) * 100) : 0;
        ?>
        
        <div class="row">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card bg-success text-white shadow">
                    <div class="card-body">
                        <div class="text-center">
                            <h5 class="font-weight-bold">Güncel</h5>
                            <div class="h1 mb-0"><?php echo $upToDate; ?></div>
                            <div class="small"><?php echo $upToDatePercent; ?>% of total</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card bg-warning text-white shadow">
                    <div class="card-body">
                        <div class="text-center">
                            <h5 class="font-weight-bold">Yaklaşan (< 30 gün)</h5>
                            <div class="h1 mb-0"><?php echo $needsCalibration; ?></div>
                            <div class="small"><?php echo $needsCalibrationPercent; ?>% of total</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card bg-danger text-white shadow">
                    <div class="card-body">
                        <div class="text-center">
                            <h5 class="font-weight-bold">Geçmiş</h5>
                            <div class="h1 mb-0"><?php echo $expired; ?></div>
                            <div class="small"><?php echo $expiredPercent; ?>% of total</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card bg-secondary text-white shadow">
                    <div class="card-body">
                        <div class="text-center">
                            <h5 class="font-weight-bold">Kalibrasyonsuz</h5>
                            <div class="h1 mb-0"><?php echo $noCalibration; ?></div>
                            <div class="small"><?php echo $noCalibrationPercent; ?>% of total</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($expired > 0): ?>
            <div class="alert alert-danger mt-3">
                <i class="fas fa-exclamation-triangle me-2"></i> <strong>Dikkat!</strong> <?php echo $expired; ?> cihazınızın kalibrasyon süresi geçmiştir. Lütfen en kısa sürede yeni kalibrasyon için bizimle iletişime geçin.
            </div>
        <?php elseif ($needsCalibration > 0): ?>
            <div class="alert alert-warning mt-3">
                <i class="fas fa-exclamation-circle me-2"></i> <strong>Bilgi!</strong> <?php echo $needsCalibration; ?> cihazınızın kalibrasyon süresi yaklaşıyor. Lütfen yeni kalibrasyon planlamanızı yapın.
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