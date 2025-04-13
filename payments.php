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
    header("Location: " . BASE_URL . "/admin/payments.php");
    exit;
}

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// Filtreleme parametreleri
$dateFrom = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// SQL sorgusu oluşturma
$sqlFilters = [];
$params = [];

// Temel sorgu
$sql = "SELECT 
            p.*,
            cr.calibration_number,
            d.device_name
        FROM 
            calibration_payments p
        LEFT JOIN 
            calibration_records cr ON p.calibration_id = cr.calibration_id
        LEFT JOIN 
            calibration_devices d ON cr.device_id = d.device_id
        WHERE 
            p.user_id = ?";
$params[] = $userId;

// Filtreler
if (!empty($dateFrom)) {
    $sqlFilters[] = "p.payment_date >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $sqlFilters[] = "p.payment_date <= ?";
    $params[] = $dateTo;
}

if (!empty($search)) {
    $sqlFilters[] = "(cr.calibration_number LIKE ? OR d.device_name LIKE ? OR p.payment_method LIKE ? OR p.notes LIKE ?)";
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

// Ödeme özetleri
$totalPaid = getTotalPaymentForUser($userId);
$totalAmount = getTotalAmountForUser($userId);
$remainingPayment = getRemainingPaymentForUser($userId);

$pageTitle = "Ödemelerim - " . APP_NAME;
?>

<?php require_once 'header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Ödemelerim</h1>
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
                <a href="<?php echo BASE_URL; ?>/payments.php" class="btn btn-secondary">
                    <i class="fas fa-sync-alt me-2"></i> Sıfırla
                </a>
                <button type="button" class="btn btn-info" id="printBtn">
                    <i class="fas fa-print me-2"></i> Yazdır
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Ödeme Özeti Kartları -->
<div class="row mb-4">
    <!-- Toplam Tutar -->
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Toplam Tutar</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo formatMoney($totalAmount); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-file-invoice-dollar fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Ödenen Tutar -->
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Ödenen Tutar</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo formatMoney($totalPaid); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-money-bill-wave fa-2x text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Kalan Tutar -->
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Kalan Tutar</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo formatMoney($remainingPayment); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-wallet fa-2x text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Ödeme Özeti Grafiği -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Ödeme Durumu</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-8">
                <canvas id="paymentChart" height="200"></canvas>
            </div>
            <div class="col-md-4">
                <div class="mt-4 text-center small">
                    <span class="me-2">
                        <i class="fas fa-circle text-success"></i> Ödenen (<?php echo number_format(($totalPaid / max(1, $totalAmount)) * 100, 1); ?>%)
                    </span>
                    <span class="me-2">
                        <i class="fas fa-circle text-danger"></i> Kalan (<?php echo number_format(($remainingPayment / max(1, $totalAmount)) * 100, 1); ?>%)
                    </span>
                </div>
                
                <div class="text-center mt-4">
                    <h4 class="small font-weight-bold">Ödeme Durumu <span class="float-end"><?php echo number_format(($totalPaid / max(1, $totalAmount)) * 100, 0); ?>%</span></h4>
                    <div class="progress mb-4">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo ($totalPaid / max(1, $totalAmount)) * 100; ?>%" aria-valuenow="<?php echo ($totalPaid / max(1, $totalAmount)) * 100; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Ödemeler Tablosu -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Ödeme Listem</h6>
    </div>
    <div class="card-body">
        <?php if (empty($payments)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> Gösterilecek ödeme bulunamadı.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Ödeme No</th>
                            <th>Kalibrasyon</th>
                            <th>Ödeme Tarihi</th>
                            <th>Ödeme Yöntemi</th>
                            <th>Tutar</th>
                            <th>Not</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo $payment['payment_id']; ?></td>
                                <td>
                                    <?php if ($payment['calibration_id']): ?>
                                        <?php echo htmlspecialchars($payment['calibration_number'] ?? ''); ?> - 
                                        <?php echo htmlspecialchars($payment['device_name'] ?? ''); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Genel Ödeme</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatDate($payment['payment_date']); ?></td>
                                <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                <td><?php echo formatMoney($payment['amount']); ?></td>
                                <td><?php echo htmlspecialchars($payment['notes'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Ödeme Bilgileri Kartı -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Ödeme Bilgileri</h6>
    </div>
    <div class="card-body">
        <div class="alert alert-info mb-0">
            <div class="d-flex">
                <div class="me-3">
                    <i class="fas fa-info-circle fa-2x"></i>
                </div>
                <div>
                    <h5 class="alert-heading">Ödemeleriniz Hakkında</h5>
                    <p>Kalibrasyon hizmetleri için yapılan ödemelerinizi bu sayfadan takip edebilirsiniz.</p>
                    <ul class="mb-0">
                        <li>Ödemeleriniz sistem tarafından otomatik olarak kaydedilir</li>
                        <li>Ödeme dekontları için yetkili personelle iletişime geçebilirsiniz</li>
                        <li>Ödemelerinizle ilgili herhangi bir sorun olduğunda bizimle iletişime geçin</li>
                        <li>Bakiye sorgulama için 7/24 müşteri hizmetlerimizi arayabilirsiniz</li>
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
        
        // Ödeme durumu grafiği
        var ctx = document.getElementById('paymentChart').getContext('2d');
        var paymentChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Ödenen', 'Kalan'],
                datasets: [{
                    data: [<?php echo $totalPaid; ?>, <?php echo $remainingPayment; ?>],
                    backgroundColor: ['#1cc88a', '#e74a3b'],
                    hoverBackgroundColor: ['#17a673', '#be2617'],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }],
            },
            options: {
                maintainAspectRatio: false,
                tooltips: {
                    backgroundColor: "rgb(255,255,255)",
                    bodyFontColor: "#858796",
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    xPadding: 15,
                    yPadding: 15,
                    displayColors: false,
                    caretPadding: 10,
                    callbacks: {
                        label: function(tooltipItem, data) {
                            return formatMoney(data.datasets[tooltipItem.datasetIndex].data[tooltipItem.index]);
                        }
                    }
                },
                legend: {
                    display: false
                },
                cutoutPercentage: 80,
            },
        });
        
        function formatMoney(amount) {
            return new Intl.NumberFormat('tr-TR', {
                style: 'currency',
                currency: 'TRY',
                minimumFractionDigits: 2
            }).format(amount);
        }
    });
</script>

<?php require_once 'footer.php'; ?>