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
    header("Location: " . BASE_URL . "/admin/documents.php");
    exit;
}

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// Filtreleme parametreleri
$dateFrom = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// SQL sorgusu oluşturma
$sql = "SELECT 
            cd.*,
            cr.calibration_number,
            cr.calibration_date,
            cr.calibration_type,
            d.device_name,
            d.serial_number
        FROM 
            calibration_documents cd
        JOIN 
            calibration_records cr ON cd.calibration_id = cr.calibration_id
        JOIN 
            calibration_devices d ON cr.device_id = d.device_id
        WHERE 
            cr.user_id = ?";

// Filtreler
$params = [$userId];

if (!empty($dateFrom)) {
    $sql .= " AND cd.upload_date >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}

if (!empty($dateTo)) {
    $sql .= " AND cd.upload_date <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

if (!empty($search)) {
    $sql .= " AND (cr.calibration_number LIKE ? OR d.device_name LIKE ? OR d.serial_number LIKE ? OR cd.file_name LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Sıralama
$sql .= " ORDER BY cd.upload_date DESC";

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
$documents = [];

while ($row = $result->fetch_assoc()) {
    $documents[] = $row;
}

$stmt->close();

$pageTitle = "Belgelerim - " . APP_NAME;
?>

<?php require_once 'header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Kalibrasyon Belgelerim</h1>
</div>

<!-- Filtreleme Kartı -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filtreleme</h6>
    </div>
    <div class="card-body">
        <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row g-3">
            <div class="col-md-3">
                <label for="date_from" class="form-label">Başlangıç Tarihi</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>">
            </div>
            <div class="col-md-3">
                <label for="date_to" class="form-label">Bitiş Tarihi</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $dateTo; ?>">
            </div>
            <div class="col-md-4">
                <label for="search" class="form-label">Arama</label>
                <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Arama...">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-2"></i> Filtrele
                </button>
            </div>
            <div class="col-12 mt-3">
                <a href="<?php echo BASE_URL; ?>/documents.php" class="btn btn-secondary">
                    <i class="fas fa-sync-alt me-2"></i> Sıfırla
                </a>
                <button type="button" class="btn btn-info" id="printBtn">
                    <i class="fas fa-print me-2"></i> Yazdır
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Belgeler Tablosu -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Belge Listem</h6>
    </div>
    <div class="card-body">
        <?php if (empty($documents)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> Gösterilecek belge bulunamadı.
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-3 g-4">
                <?php foreach ($documents as $document): ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 text-truncate" title="<?php echo htmlspecialchars($document['calibration_number']); ?>">
                                    <?php echo htmlspecialchars($document['calibration_number']); ?>
                                </h6>
                                <span class="badge bg-primary"><?php echo formatDate($document['calibration_date']); ?></span>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title text-truncate"><?php echo htmlspecialchars($document['device_name']); ?></h5>
                                <p class="card-text">
                                    <strong>Seri No:</strong> <?php echo htmlspecialchars($document['serial_number']); ?><br>
                                    <strong>Kalibrasyon Tipi:</strong> <?php echo htmlspecialchars($document['calibration_type']); ?><br>
                                    <strong>Yükleme Tarihi:</strong> <?php echo formatDate($document['upload_date'], 'd.m.Y H:i'); ?>
                                </p>
                            </div>
                            <div class="card-footer bg-white border-top-0">
                                <div class="d-grid gap-2">
                                    <a href="<?php echo BASE_URL . '/' . $document['file_path']; ?>" class="btn btn-primary" target="_blank">
                                        <i class="fas fa-eye me-2"></i> Görüntüle
                                    </a>
                                    <a href="<?php echo BASE_URL . '/' . $document['file_path']; ?>" class="btn btn-outline-success" download>
                                        <i class="fas fa-download me-2"></i> İndir
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Belge Kontrolü İçin Bilgi Kartı -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Belge Doğrulama</h6>
    </div>
    <div class="card-body">
        <div class="alert alert-info mb-0">
            <div class="d-flex">
                <div class="me-3">
                    <i class="fas fa-info-circle fa-2x"></i>
                </div>
                <div>
                    <h5 class="alert-heading">Belgelerin Önemi</h5>
                    <p>Kalibrasyon belgeleri, cihazlarınızın ölçüm doğruluğunun kanıtıdır ve düzenleyici denetimler için önemlidir. Lütfen aşağıdaki hususlara dikkat edin:</p>
                    <ul class="mb-0">
                        <li>Belgeleri güvenli bir yerde saklayın</li>
                        <li>Denetimlerde kullanılmak üzere hazır bulundurun</li>
                        <li>Belge üzerindeki sonraki kalibrasyon tarihini kontrol edin</li>
                        <li>Herhangi bir sorun durumunda bizimle iletişime geçin</li>
                    </ul>
                </div>
            </div>
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