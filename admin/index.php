<?php
$pageTitle = "Admin Dashboard - " . APP_NAME;
require_once 'header.php';

$db = Database::getInstance();

// İstatistikleri hesapla
$totalUsers = $db->queryValue("SELECT COUNT(*) FROM calibration_users WHERE user_type = 'user'");
$totalDevices = $db->queryValue("SELECT COUNT(*) FROM calibration_devices");
$totalCalibrations = $db->queryValue("SELECT COUNT(*) FROM calibration_records");
$totalDocuments = $db->queryValue("SELECT COUNT(*) FROM calibration_documents");
$pendingApprovals = $db->queryValue("SELECT COUNT(*) FROM calibration_users WHERE status = 'pending'");

// Son 12 ayın verilerini al
$currentDate = date('Y-m-d');
$oneYearAgo = date('Y-m-d', strtotime('-12 months'));

// Aylık kalibrasyon sayılarını al
$monthlyStats = $db->query("SELECT 
                            DATE_FORMAT(calibration_date, '%Y-%m') as month,
                            COUNT(*) as count
                        FROM 
                            calibration_records
                        WHERE 
                            calibration_date BETWEEN '$oneYearAgo' AND '$currentDate'
                        GROUP BY 
                            DATE_FORMAT(calibration_date, '%Y-%m')
                        ORDER BY 
                            month ASC");

// Aylık ödeme tutarlarını al
$monthlyPayments = $db->query("SELECT 
                                DATE_FORMAT(payment_date, '%Y-%m') as month,
                                SUM(amount) as total
                            FROM 
                                calibration_payments
                            WHERE 
                                payment_date BETWEEN '$oneYearAgo' AND '$currentDate'
                            GROUP BY 
                                DATE_FORMAT(payment_date, '%Y-%m')
                            ORDER BY 
                                month ASC");

// Kalibrasyon tipleri dağılımını al
$calibrationTypes = $db->query("SELECT 
                                calibration_type,
                                COUNT(*) as count
                            FROM 
                                calibration_records
                            GROUP BY 
                                calibration_type
                            ORDER BY 
                                count DESC");

// Son 5 kalibrasyonu al
$recentCalibrations = $db->query("SELECT 
                                    cr.calibration_id, 
                                    cr.calibration_number, 
                                    cr.calibration_date, 
                                    cr.status,
                                    u.company_name,
                                    d.device_name, 
                                    d.serial_number
                                FROM 
                                    calibration_records cr
                                JOIN 
                                    calibration_devices d ON cr.device_id = d.device_id
                                JOIN 
                                    calibration_users u ON cr.user_id = u.user_id
                                ORDER BY 
                                    cr.creation_date DESC
                                LIMIT 5");

// Son 5 ödemeyi al
$recentPayments = $db->query("SELECT 
                                p.payment_id, 
                                p.amount, 
                                p.payment_date,
                                p.payment_method,
                                u.company_name
                            FROM 
                                calibration_payments p
                            JOIN 
                                calibration_users u ON p.user_id = u.user_id
                            ORDER BY 
                                p.creation_date DESC
                            LIMIT 5");

// Grafik verileri için JSON dizileri oluştur
$months = [];
$calibrationCounts = [];
$paymentAmounts = [];

// Son 12 ay için boş dizi oluştur
for ($i = 0; $i < 12; $i++) {
    $month = date('Y-m', strtotime("-$i months"));
    $months[] = date('M Y', strtotime($month));
    $calibrationCounts[$month] = 0;
    $paymentAmounts[$month] = 0;
}

// Kalibrasyon verilerini doldur
foreach ($monthlyStats as $stat) {
    $calibrationCounts[$stat['month']] = (int)$stat['count'];
}

// Ödeme verilerini doldur
foreach ($monthlyPayments as $payment) {
    $paymentAmounts[$payment['month']] = (float)$payment['total'];
}

// Dizileri doğru sırayla doldur
$calibrationData = array_values(array_reverse($calibrationCounts));
$paymentData = array_values(array_reverse($paymentAmounts));
$monthLabels = array_reverse($months);

// Onay bekleyen kullanıcılar
$pendingUsers = $db->query("SELECT 
                            user_id, 
                            username, 
                            email, 
                            company_name, 
                            registration_date 
                        FROM 
                            calibration_users 
                        WHERE 
                            status = 'pending'
                        ORDER BY 
                            registration_date DESC
                        LIMIT 5");
?>

<h1 class="h3 mb-4">Admin Dashboard</h1>

<!-- İstatistik Kartları -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Toplam Kullanıcı</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo $totalUsers; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="<?php echo BASE_URL; ?>/admin/users.php" class="text-primary">
                    Kullanıcıları Görüntüle <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Toplam Cihaz</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo $totalDevices; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-tools fa-2x text-success"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="<?php echo BASE_URL; ?>/admin/devices.php" class="text-success">
                    Cihazları Görüntüle <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Toplam Kalibrasyon</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo $totalCalibrations; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clipboard-check fa-2x text-info"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="<?php echo BASE_URL; ?>/admin/calibrations.php" class="text-info">
                    Kalibrasyonları Görüntüle <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Onay Bekleyen</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo $pendingApprovals; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-clock fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="<?php echo BASE_URL; ?>/admin/approvals.php" class="text-warning">
                    Onay Bekleyenleri Görüntüle <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Grafikler Satırı -->
<div class="row mb-4">
    <!-- Kalibrasyon İstatistikleri Grafiği -->
    <div class="col-xl-8 col-lg-7">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Aylık Kalibrasyon İstatistikleri</h6>
            </div>
            <div class="card-body">
                <div class="chart-area">
                    <canvas id="calibrationChart" style="height: 300px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Kalibrasyon Tipleri Grafiği -->
    <div class="col-xl-4 col-lg-5">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Kalibrasyon Tipleri Dağılımı</h6>
            </div>
            <div class="card-body">
                <div class="chart-pie">
                    <canvas id="calibrationTypeChart" style="height: 300px;"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- İçerik Satırı -->
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
                                    <th>Şirket</th>
                                    <th>Cihaz</th>
                                    <th>Tarih</th>
                                    <th>Durum</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentCalibrations as $calibration): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($calibration['calibration_number']); ?></td>
                                        <td><?php echo htmlspecialchars($calibration['company_name']); ?></td>
                                        <td><?php echo htmlspecialchars($calibration['device_name']); ?></td>
                                        <td><?php echo formatDate($calibration['calibration_date']); ?></td>
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
                <?php else: ?>
                    <p class="text-center">Henüz kalibrasyon kaydı bulunmamaktadır.</p>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-transparent">
                <a href="<?php echo BASE_URL; ?>/admin/calibrations.php" class="text-primary">
                    Tüm Kalibrasyonları Görüntüle <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Onay Bekleyen Kullanıcılar -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header bg-warning text-white">
                <h6 class="m-0 font-weight-bold">Onay Bekleyen Kullanıcılar</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($pendingUsers)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Kullanıcı Adı</th>
                                    <th>Firma</th>
                                    <th>E-posta</th>
                                    <th>Kayıt Tarihi</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingUsers as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['company_name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo formatDate($user['registration_date'], 'd.m.Y H:i'); ?></td>
                                        <td>
                                            <a href="<?php echo BASE_URL; ?>/admin/approve_user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-success">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>/admin/reject_user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-danger">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center">Onay bekleyen kullanıcı bulunmamaktadır.</p>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-transparent">
                <a href="<?php echo BASE_URL; ?>/admin/approvals.php" class="text-warning">
                    Tüm Onay Bekleyenleri Görüntüle <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Son Ödemeler -->
<div class="row">
    <div class="col-lg-12 mb-4">
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
                                    <th>ID</th>
                                    <th>Şirket</th>
                                    <th>Tarih</th>
                                    <th>Ödeme Yöntemi</th>
                                    <th>Tutar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentPayments as $payment): ?>
                                    <tr>
                                        <td><?php echo $payment['payment_id']; ?></td>
                                        <td><?php echo htmlspecialchars($payment['company_name']); ?></td>
                                        <td><?php echo formatDate($payment['payment_date']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                        <td><?php echo formatMoney($payment['amount']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center">Henüz ödeme kaydı bulunmamaktadır.</p>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-transparent">
                <a href="<?php echo BASE_URL; ?>/admin/payments.php" class="text-success">
                    Tüm Ödemeleri Görüntüle <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Grafik Scriptleri -->
<script>
    // Kalibrasyon İstatistikleri Grafiği
    document.addEventListener('DOMContentLoaded', function() {
        var ctx = document.getElementById('calibrationChart').getContext('2d');
        var myChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($monthLabels); ?>,
                datasets: [{
                    label: 'Kalibrasyon Sayısı',
                    data: <?php echo json_encode($calibrationData); ?>,
                    backgroundColor: 'rgba(78, 115, 223, 0.05)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgba(78, 115, 223, 1)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        precision: 0
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                }
            }
        });
        
        // Kalibrasyon Tipleri Grafiği
        var typeCtx = document.getElementById('calibrationTypeChart').getContext('2d');
        var typeData = [];
        var typeLabels = [];
        var typeColors = [
            'rgba(255, 99, 132, 0.7)',
            'rgba(54, 162, 235, 0.7)',
            'rgba(255, 206, 86, 0.7)',
            'rgba(75, 192, 192, 0.7)',
            'rgba(153, 102, 255, 0.7)',
            'rgba(255, 159, 64, 0.7)',
            'rgba(255, 99, 255, 0.7)',
            'rgba(54, 162, 190, 0.7)'
        ];
        
        <?php
        if (!empty($calibrationTypes)) {
            echo "typeLabels = " . json_encode(array_column($calibrationTypes, 'calibration_type')) . ";\n";
            echo "typeData = " . json_encode(array_column($calibrationTypes, 'count')) . ";\n";
        }
        ?>
        
        var typeChart = new Chart(typeCtx, {
            type: 'doughnut',
            data: {
                labels: typeLabels,
                datasets: [{
                    data: typeData,
                    backgroundColor: typeColors,
                    borderColor: typeColors.map(color => color.replace('0.7', '1')),
                    borderWidth: 1
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
    });
</script>

<?php require_once 'footer.php'; ?>