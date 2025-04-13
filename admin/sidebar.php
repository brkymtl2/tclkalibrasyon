<?php
// Admin menüsünü oluştur
$adminMenu = getMenu('admin');
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<div class="col-md-3 col-lg-2 d-md-block sidebar bg-light collapse" id="sidebarMenu">
    <div class="position-sticky pt-3">
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Admin Paneli</span>
        </h6>
        <ul class="nav flex-column">
            <?php foreach ($adminMenu as $item): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo (basename($item['url']) === $currentPage) ? 'active' : ''; ?>" href="<?php echo BASE_URL . '/' . $item['url']; ?>">
                        <i class="<?php echo $item['icon']; ?> me-2"></i>
                        <?php echo $item['title']; ?>
                        
                        <?php if ($item['title'] === 'Onay Bekleyenler'): 
                            $db = Database::getInstance();
                            $pendingApprovals = $db->queryValue("SELECT COUNT(*) FROM calibration_users WHERE status = 'pending'");
                            if ($pendingApprovals > 0):
                        ?>
                            <span class="badge bg-danger float-end"><?php echo $pendingApprovals; ?></span>
                        <?php endif; endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Hızlı Erişim</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link" href="<?php echo BASE_URL; ?>/admin/add_calibration.php">
                    <i class="fas fa-plus-circle me-2"></i>
                    Yeni Kalibrasyon
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo BASE_URL; ?>/admin/add_payment.php">
                    <i class="fas fa-money-bill-wave me-2"></i>
                    Yeni Ödeme
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo BASE_URL; ?>/admin/add_user.php">
                    <i class="fas fa-user-plus me-2"></i>
                    Yeni Kullanıcı
                </a>
            </li>
        </ul>
    </div>
</div>