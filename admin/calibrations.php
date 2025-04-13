<?php
$pageTitle = "Kalibrasyonlar - " . APP_NAME;
require_once 'header.php';

$db = Database::getInstance();

// Filtreleme parametreleri
$userFilter = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
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
            u.company_name,
            (SELECT COUNT(*) FROM calibration_documents WHERE calibration_id = cr.calibration_id) as has_document
        FROM 
            calibration_records cr
        JOIN 
            calibration_devices d ON cr.device_id = d.device_id
        JOIN 
            calibration_users u ON cr.user_id = u.user_id
        WHERE 1=1";

// Filtreler
if ($userFilter > 0) {
    $sqlFilters[] = "cr.user_id = ?";
    $params[] = $userFilter;
}

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
    $sqlFilters[] = "(cr.calibration_number LIKE ? OR d.device_name LIKE ? OR d.serial_number LIKE ? OR u.company_name LIKE ?)";
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

// Kullanıcı listesi (filtreleme için)
$users = $db->query("SELECT user_id, username, company_name FROM calibration_users WHERE status = 'approved' AND user_type = 'user' ORDER BY company_name ASC");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Kalibrasyonlar</h1>
    <a href="<?php echo BASE_URL; ?>/admin/add_calibration.php" class="btn btn-primary">
        <i class="fas fa-plus-circle me-2"></i> Yeni Kalibrasyon Ekle
    </a>
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
            <div class="col-md-2">
                <label for="search" class="form-label">Arama</label>
                <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Arama...">
            </div>
            <div class="col-12 mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter me-2"></i> Filtrele
                </button>
                <a href="<?php echo BASE_URL; ?>/admin/calibrations.php" class="btn btn-secondary">
                    <i class="fas fa-sync-alt me-2"></i> Sıfırla
                </a>
                <button type="button" class="btn btn-success" id="exportBtn">
                    <i class="fas fa-file-excel me-2"></i> Excel'e Aktar
                </button>
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
        <h6 class="m-0 font-weight-bold text-primary">Kalibrasyon Listesi</h6>
    </div>
    <div class="card-body">
        <?php if (empty($calibrations)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> Gösterilecek kalibrasyon bulunamadı.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered datatable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kalibrasyon No</th>
                            <th>Şirket</th>
                            <th>Cihaz</th>
                            <th>Seri No</th>
                            <th>Kalibrasyon Tarihi</th>
                            <th>Sonraki Kalibrasyon</th>
                            <th>Tip</th>
                            <th>Belge</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($calibrations as $calibration): ?>
                            <tr>
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
                                        
                                        <button type="button" class="btn btn-sm btn-danger delete-btn" 
                                                data-url="<?php echo BASE_URL; ?>/admin/delete_calibration.php?id=<?php echo $calibration['calibration_id']; ?>"
                                                data-name="'<?php echo htmlspecialchars($calibration['calibration_number']); ?>' numaralı kalibrasyonu"
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
        
        // Excel'e aktarma işlemi
        document.getElementById('exportBtn').addEventListener('click', function() {
            // Table2Excel kütüphanesini kullanmak için buraya kod eklenebilir
            // Basit bir çözüm olarak tarayıcının indirme özelliğini kullanabiliriz
            const table = document.querySelector('.datatable');
            const rows = table.querySelectorAll('tr');
            
            let csvContent = "data:text/csv;charset=utf-8,";
            
            // Başlıkları al
            const headers = [];
            const headerCells = rows[0].querySelectorAll('th');
            headerCells.forEach(cell => {
                headers.push(cell.innerText);
            });
            csvContent += headers.join(',') + "\r\n";
            
            // Verileri al (başlık satırını atla)
            for (let i = 1; i < rows.length; i++) {
                const row = [];
                const cells = rows[i].querySelectorAll('td');
                cells.forEach(cell => {
                    // Son sütundaki işlem butonlarını atla
                    if (!cell.querySelector('.btn-group')) {
                        // Virgülleri ve çift tırnakları kontrol et
                        let cellText = cell.innerText.replace(/"/g, '""');
                        if (cellText.includes(',')) {
                            cellText = `"${cellText}"`;
                        }
                        row.push(cellText);
                    }
                });
                csvContent += row.join(',') + "\r\n";
            }
            
            // CSV dosyasını indir
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "kalibrasyonlar.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    });
</script>

<?php require_once 'footer.php'; ?>
php echo $calibration['calibration_id']; ?></td>
                                <td><?php echo htmlspecialchars($calibration['calibration_number']); ?></td>
                                <td><?php echo htmlspecialchars($calibration['company_name']); ?></td>
                                <td><?php echo htmlspecialchars($calibration['device_name']); ?></td>
                                <td><?php echo htmlspecialchars($calibration['serial_number']); ?></td>
                                <td><?