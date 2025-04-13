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
    header("Location: " . BASE_URL . "/admin/index.php");
    exit;
}

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// Kullanıcının cihaz sayısını al
$totalDevices = $db->queryValue("SELECT COUNT(*) FROM calibration_devices WHERE user_id = $userId");

// Kullanıcının toplam kalibrasyon sayısını al
$totalCalibrations = $db->queryValue("SELECT COUNT(*) FROM calibration_records WHERE user_id = $userId");

// Kullanıcının bekleyen kalibrasyon belgelerini al
$pendingDocuments = $db->queryValue("SELECT COUNT(*) FROM calibration_records cr 
                                    LEFT JOIN calibration_documents cd ON cr.calibration_id = cd.calibration_id 
                                    WHERE cr.user_id = $userId AND cd.document_id IS NULL");

// Toplam ödeme tutarını hesapla
$totalAmount = getTotalAmountForUser($userId);

// Yapılan ödemeleri hesapla
$totalPayment = getTotalPaymentForUser($userId);

// Kalan ödeme miktarını hesapla
$remainingPayment = max(0, $totalAmount - $totalPayment);

// Son 5 kalibrasyonu al
$recentCalibrations = $db->query("SELECT 
                                    cr.calibration_id, 
                                    cr.calibration_number, 
                                    cr.calibration_date, 
                                    cr.calibration_type,
                                    cr.next_calibration_date,
                                    d.device_name, 
                                    d.serial_number
                                FROM 
                                    calibration_records cr
                                JOIN 
                                    calibration_devices d ON cr.device_id = d.device_id
                                WHERE 
                                    cr.user_id = $userId
                                ORDER BY 
                                    cr.calibration_date DESC
                                LIMIT 5");

// Yaklaşan kalibrasyonları al (30 gün içinde)
$upcomingCalibrations = getUpcomingCalibrations($userId, 30);

// Son 5 ödemeyi al
$recentPayments = $db->query("SELECT 
                                p.payment_id, 
                                p.amount, 
                                p.payment_date,
                                p.payment_method,
                                cr.calibration_number,
                                d.device_name
                            FROM 
                                calibration_payments p
                            LEFT JOIN 
                                calibration_records cr ON p.calibration_id = cr.calibration_id
                            LEFT JOIN 
                                calibration_devices d ON cr.device_id = d.device_id
                            WHERE 
                                p.user_id = $userId
                            ORDER BY 
                                p.payment_date DESC
                            LIMIT 5");

$pageTitle = "Dashboard - " . APP_NAME;
?>

<?php require_once 'header.php'; ?>

<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="display-6">Hoş Geldiniz, <?php echo htmlspecialchars($currentUser['company_name']); ?></h1>
        <p class="lead">Kalibrasyon ve ödeme bilgilerinizi bu panelden takip edebilirsiniz.</p>
    </div>
</div>

<!-- İstatistik Kartları -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Toplam Cihaz</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo $totalDevices; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-tools fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="<?php echo BASE_URL; ?>/devices.php" class="text-primary">
                    Tüm Cihazlarım <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Toplam Kalibrasyon</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo $totalCalibrations; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clipboard-check fa-2x text-success"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="<?php echo BASE_URL; ?>/calibrations.php" class="text-success">
                    Tüm Kalibrasyonlarım <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Bekleyen Belgeler</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo $pendingDocuments; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-file-pdf fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="<?php echo BASE_URL; ?>/documents.php" class="text-warning">
                    Tüm Belgelerim <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Kalan Ödeme</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo formatMoney($remainingPayment); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-money-bill-wave fa-2x text-danger"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="<?php echo BASE_URL; ?>/payments.php" class="text-danger">
                    Ödeme Detayları <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Son Kalibrasyonlar -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h6 class="m-0 font-weight-bold">Son Kalibrasyonlar</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($recentCalibrations)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Kalibrasyon No</th>
                                    <th>Cihaz</th>
                                    <th>Tarih</th>
                                    <th>Tip</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentCalibrations as $calibration): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($calibration['calibration_number']); ?></td>
                                        <td><?php echo htmlspecialchars($calibration['device_name']); ?></td>
                                        <td><?php echo formatDate($calibration['calibration_date']); ?></td>
                                        <td><?php echo htmlspecialchars($calibration['calibration_type']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center">Henüz kalibrasyon kaydınız bulunmamaktadır.</p>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-transparent">
                <a href="<?php echo BASE_URL; ?>/calibrations.php" class="text-primary">
                    Tüm Kalibrasyonları Görüntüle <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Yaklaşan Kalibrasyonlar -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header bg-warning text-white">
                <h6 class="m-0 font-weight-bold">Yaklaşan Kalibrasyonlar (30 gün)</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($upcomingCalibrations)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
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
                <?php else: ?>
                    <p class="text-center">30 gün içinde yaklaşan kalibrasyon bulunmamaktadır.</p>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-transparent">
                <a href="<?php echo BASE_URL; ?>/calibrations.php" class="text-warning">
                    Tüm Kalibrasyonları Görüntüle <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Son Ödemeler -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header bg-success text-white">
                <h6 class="m-0 font-weight-bold">Son Ödemeler</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($recentPayments)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Tarih</th>
                                    <th>Kalibrasyon</th>
                                    <th>Ödeme Yöntemi</th>
                                    <th>Tutar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentPayments as $payment): ?>
                                    <tr>
                                        <td><?php echo formatDate($payment['payment_date']); ?></td>
                                        <td>
                                            <?php if (!empty($payment['calibration_number'])): ?>
                                                <?php echo htmlspecialchars($payment['calibration_number']); ?> / <?php echo htmlspecialchars($payment['device_name']); ?>
                                            <?php else: ?>
                                                Genel Ödeme
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                        <td><?php echo formatMoney($payment['amount']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center">Henüz ödeme kaydınız bulunmamaktadır.</p>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-transparent">
                <a href="<?php echo BASE_URL; ?>/payments.php" class="text-success">
                    Tüm Ödemeleri Görüntüle <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Ödeme Özeti -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header bg-info text-white">
                <h6 class="m-0 font-weight-bold">Ödeme Özeti</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <canvas id="paymentChart"></canvas>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center mb-4">
                            <h5>Toplam Tutar</h5>
                            <h3 class="text-primary"><?php echo formatMoney($totalAmount); ?></h3>
                        </div>
                        <div class="text-center mb-4">
                            <h5>Ödenen</h5>
                            <h3 class="text-success"><?php echo formatMoney($totalPayment); ?></h3>
                        </div>
                        <div class="text-center mb-4">
                            <h5>Kalan</h5>
                            <h3 class="text-danger"><?php echo formatMoney($remainingPayment); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="<?php echo BASE_URL; ?>/payments.php" class="text-info">
                    Ödeme Detayları <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Ödeme grafiği için JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var ctx = document.getElementById('paymentChart').getContext('2d');
        var myChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Ödenen', 'Kalan'],
                datasets: [{
                    data: [<?php echo $totalPayment; ?>, <?php echo $remainingPayment; ?>],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.7)',
                        'rgba(220, 53, 69, 0.7)'
                    ],
                    borderColor: [
                        'rgba(40, 167, 69, 1)',
                        'rgba(220, 53, 69, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    });
</script>

<?php require_once 'footer.php'; ?>