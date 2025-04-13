<?php
$pageTitle = "Cihazlar - " . APP_NAME;
require_once 'header.php';

$db = Database::getInstance();

// Filtreleme parametreleri
$userFilter = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// SQL sorgusu oluşturma
$sql = "SELECT 
            d.*, 
            u.company_name,
            (SELECT COUNT(*) FROM calibration_records WHERE device_id = d.device_id) as calibration_count,
            (SELECT MAX(calibration_date) FROM calibration_records WHERE device_id = d.device_id) as last_calibration
        FROM 
            calibration_devices d
        JOIN 
            calibration_users u ON d.user_id = u.user_id
        WHERE 1=1";

// Filtreler
$params = [];

if ($userFilter > 0) {
    $sql .= " AND d.user_id = ?";
    $params[] = $userFilter;
}

if (!empty($search)) {
    $sql .= " AND (d.device_name LIKE ? OR d.serial_number LIKE ? OR d.device_model LIKE ? OR u.company_name LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Sıralama
$sql .= " ORDER BY u.company_name ASC, d.device_name ASC";

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

// Kullanıcı listesi (filtreleme için)
$users = $db->query("SELECT user_id, username, company_name FROM calibration_users WHERE status = 'approved' AND user_type = 'user' ORDER BY company_name ASC");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Cihazlar</h1>
    <a href="<?php echo BASE_URL; ?>/admin/add_device.php" class="btn btn-primary">
        <i class="fas fa-plus-circle me-2"></i> Yeni Cihaz Ekle
    </a>
</div>

<!-- Filtreleme Kartı -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filtreleme</h6>
    </div>
    <div class="card-body">
        <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row g-3">
            <div class="col-md-5">
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
            <div class="col-md-5">
                <label for="search" class="form-label">Arama</label>
                <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cihaz adı, seri no, model...">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-2"></i> Filtrele
                </button>
            </div>
            <div class="col-12 mt-3">
                <a href="<?php echo BASE_URL; ?>/admin/devices.php" class="btn btn-secondary">
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

<!-- Cihazlar Tablosu -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Cihaz Listesi</h6>
    </div>
    <div class="card-body">
        <?php if (empty($devices)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> Gösterilecek cihaz bulunamadı.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered datatable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Şirket</th>
                            <th>Cihaz Adı</th>
                            <th>Model</th>
                            <th>Seri No</th>
                            <th>Son Kalibrasyon</th>
                            <th>Kalibrasyon Sayısı</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($devices as $device): ?>
                            <tr>
                                <td><?php echo $device['device_id']; ?></td>
                                <td><?php echo htmlspecialchars($device['company_name']); ?></td>
                                <td><?php echo htmlspecialchars($device['device_name']); ?></td>
                                <td><?php echo htmlspecialchars($device['device_model'] ?? 'Belirtilmemiş'); ?></td>
                                <td><?php echo htmlspecialchars($device['serial_number']); ?></td>
                                <td><?php echo $device['last_calibration'] ? formatDate($device['last_calibration']) : 'Yok'; ?></td>
                                <td><?php echo $device['calibration_count']; ?></td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <a href="<?php echo BASE_URL; ?>/admin/edit_device.php?id=<?php echo $device['device_id']; ?>" class="btn btn-sm btn-primary" title="Düzenle">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <a href="<?php echo BASE_URL; ?>/admin/device_calibrations.php?id=<?php echo $device['device_id']; ?>" class="btn btn-sm btn-info" title="Kalibrasyonlar">
                                            <i class="fas fa-clipboard-list"></i>
                                        </a>
                                        
                                        <a href="<?php echo BASE_URL; ?>/admin/add_calibration.php?device_id=<?php echo $device['device_id']; ?>" class="btn btn-sm btn-success" title="Kalibrasyon Ekle">
                                            <i class="fas fa-plus-circle"></i>
                                        </a>
                                        
                                        <button type="button" class="btn btn-sm btn-danger delete-btn" 
                                                data-url="<?php echo BASE_URL; ?>/admin/delete_device.php?id=<?php echo $device['device_id']; ?>"
                                                data-name="'<?php echo htmlspecialchars($device['device_name']); ?>' cihazını"
                                                <?php echo $device['calibration_count'] > 0 ? 'disabled' : ''; ?>
                                                title="<?php echo $device['calibration_count'] > 0 ? 'Kalibrasyonu olan cihaz silinemez' : 'Sil'; ?>">
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
                // Son sütundaki işlem butonlarını hariç tut
                if (cell.innerText !== 'İşlemler') {
                    headers.push(cell.innerText);
                }
            });
            csvContent += headers.join(',') + "\r\n";
            
            // Verileri al (başlık satırını atla)
            for (let i = 1; i < rows.length; i++) {
                const row = [];
                const cells = rows[i].querySelectorAll('td');
                for (let j = 0; j < cells.length - 1; j++) { // Son sütunu atla (işlemler)
                    // Virgülleri ve çift tırnakları kontrol et
                    let cellText = cells[j].innerText.replace(/"/g, '""');
                    if (cellText.includes(',')) {
                        cellText = `"${cellText}"`;
                    }
                    row.push(cellText);
                }
                csvContent += row.join(',') + "\r\n";
            }
            
            // CSV dosyasını indir
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "cihazlar.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    });
</script>

<?php require_once 'footer.php'; ?>