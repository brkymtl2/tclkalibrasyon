<?php
$pageTitle = "Ödemeler - " . APP_NAME;
require_once 'header.php';

$db = Database::getInstance();

// Filtreleme parametreleri
$userFilter = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$dateFrom = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// SQL sorgusu oluşturma
$sqlFilters = [];
$params = [];

// Temel sorgu
$sql = "SELECT 
            p.*,
            u.username,
            u.company_name,
            cr.calibration_number,
            d.device_name
        FROM 
            calibration_payments p
        JOIN 
            calibration_users u ON p.user_id = u.user_id
        LEFT JOIN 
            calibration_records cr ON p.calibration_id = cr.calibration_id
        LEFT JOIN 
            calibration_devices d ON cr.device_id = d.device_id
        WHERE 1=1";

// Filtreler
if ($userFilter > 0) {
    $sqlFilters[] = "p.user_id = ?";
    $params[] = $userFilter;
}

if (!empty($dateFrom)) {
    $sqlFilters[] = "p.payment_date >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $sqlFilters[] = "p.payment_date <= ?";
    $params[] = $dateTo;
}

if (!empty($search)) {
    $sqlFilters[] = "(u.company_name LIKE ? OR cr.calibration_number LIKE ? OR p.payment_method LIKE ? OR p.notes LIKE ?)";
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
$sql .= " ORDER BY p.payment_date DESC";

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
$payments = [];

while ($row = $result->fetch_assoc()) {
    $payments[] = $row;
}

$stmt->close();

// Toplam ödeme miktarını hesapla
$totalAmount = 0;
foreach ($payments as $payment) {
    $totalAmount += $payment['amount'];
}

// Kullanıcı listesi (filtreleme için)
$users = $db->query("SELECT user_id, username, company_name FROM calibration_users WHERE status = 'approved' AND user_type = 'user' ORDER BY company_name ASC");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Ödemeler</h1>
    <a href="<?php echo BASE_URL; ?>/admin/add_payment.php" class="btn btn-primary">
        <i class="fas fa-plus-circle me-2"></i> Yeni Ödeme Ekle
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