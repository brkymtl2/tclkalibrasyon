<?php
$pageTitle = "Belgeler - " . APP_NAME;
require_once 'header.php';

$db = Database::getInstance();

// Filtreleme parametreleri
$userFilter = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
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
            d.serial_number,
            u.company_name,
            u.user_id
        FROM 
            calibration_documents cd
        JOIN 
            calibration_records cr ON cd.calibration_id = cr.calibration_id
        JOIN 
            calibration_devices d ON cr.device_id = d.device_id
        JOIN 
            calibration_users u ON cr.user_id = u.user_id
        WHERE 1=1";

// Filtreler
$params = [];

if ($userFilter > 0) {
    $sql .= " AND u.user_id = ?";
    $params[] = $userFilter;
}

if (!empty($dateFrom)) {
    $sql .= " AND cd.upload_date >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}

if (!empty($dateTo)) {
    $sql .= " AND cd.upload_date <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

if (!empty($search)) {
    $sql .= " AND (cr.calibration_number LIKE ? OR d.device_name LIKE ? OR d.serial_number LIKE ? OR u.company_name LIKE ? OR cd.file_name LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
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

// Kullanıcı listesi (filtreleme için)
$users = $db->query("SELECT user_id, username, company_name FROM calibration_users WHERE status = 'approved' AND user_type = 'user' ORDER BY company_name ASC");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Kalibrasyon Belgeleri</h1>
</div>

<!-- Filtreleme Kartı -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filtreleme</h6>
    </div>
    <div class="card-body">
        <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row g-3">
            <div class="col-md-3">
                <label for="user_id" class="form-label">Kullanıcı/Şirket</label>
                <select class="form-select" id="user_id" name="user_id">
                    <option value="">Tümü</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['user_id']; ?>" <?php echo $userFilter == $user['user_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['company_name']); ?>
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
                <a href="<?php echo BASE_URL; ?>/admin/documents.php" class="btn btn-secondary">
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
        <h6 class="m-0 font-weight-bold text-primary">Belge Listesi</h6>
    </div>
    <div class="card-body">
        <?php if (empty($documents)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> Gösterilecek belge bulunamadı.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered datatable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Şirket</th>
                            <th>Cihaz</th>
                            <th>Kalibrasyon No</th>
                            <th>Kalibrasyon Tarihi</th>
                            <th>Belge Adı</th>
                            <th>Yükleme Tarihi</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $document): ?>
                            <tr>
                                <td><?php echo $document['document_id']; ?></td>
                                <td><?php echo htmlspecialchars($document['company_name']); ?></td>
                                <td><?php echo htmlspecialchars($document['device_name']); ?> (<?php echo htmlspecialchars($document['serial_number']); ?>)</td>
                                <td><?php echo htmlspecialchars($document['calibration_number']); ?></td>
                                <td><?php echo formatDate($document['calibration_date']); ?></td>
                                <td><?php echo htmlspecialchars($document['file_name']); ?></td>
                                <td><?php echo formatDate($document['upload_date'], 'd.m.Y H:i'); ?></td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <a href="<?php echo BASE_URL . '/' . $document['file_path']; ?>" class="btn btn-sm btn-primary" target="_blank" title="Görüntüle">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <a href="<?php echo BASE_URL . '/' . $document['file_path']; ?>" class="btn btn-sm btn-success" download title="İndir">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        
                                        <a href="<?php echo BASE_URL; ?>/admin/add_document.php?calibration_id=<?php echo $document['calibration_id']; ?>" class="btn btn-sm btn-warning" title="Güncelle">
                                            <i class="fas fa-upload"></i>
                                        </a>
                                        
                                        <button type="button" class="btn btn-sm btn-danger delete-btn" 
                                                data-url="<?php echo BASE_URL; ?>/admin/delete_document.php?id=<?php echo $document['document_id']; ?>"
                                                data-name="'<?php echo htmlspecialchars($document['calibration_number']); ?>' kalibrasyonuna ait belgeyi"
                                                title="Sil">
                                            <i class="fas fa-trash"></i>
                                        </button>
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Yazdırma işlemi
        document.getElementById('printBtn').addEventListener('click', function() {
            window.print();
        });
    });
</script>

<?php require_once 'footer.php'; ?>