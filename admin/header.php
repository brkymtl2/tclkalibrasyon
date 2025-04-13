<?php
require_once '../config.php';
require_once '../db_connect.php';
require_once '../functions.php';

// Session başlat
initSession();

// Kullanıcı giriş yapmamış veya admin değilse, login sayfasına yönlendir
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['message'] = 'Bu sayfaya erişebilmek için admin olarak giriş yapmalısınız.';
    $_SESSION['message_type'] = 'danger';
    header("Location: " . BASE_URL . "/login.php");
    exit;
}

// Kullanıcı bilgisini al
$currentUser = getUserInfo();
$unreadNotifications = getUnreadNotificationCount($_SESSION['user_id']);

// Sayfa başlığı
$pageTitle = $pageTitle ?? "Admin Paneli - " . APP_NAME;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Özel CSS veya JS dosyaları için yer tutucu -->
    <?php if (isset($extraCSS)) echo $extraCSS; ?>
    <?php if (isset($extraJS)) echo $extraJS; ?>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>/admin/index.php">
                <i class="fas fa-tools me-2"></i>
                <?php echo APP_NAME; ?> - Admin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarAdmin" aria-controls="navbarAdmin" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarAdmin">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/index.php" target="_blank">
                            <i class="fas fa-external-link-alt me-1"></i> Siteyi Görüntüle
                        </a>
                    </li>
                    
                    <!-- Bildirimler -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell me-1"></i> Bildirimler
                            <?php if ($unreadNotifications > 0): ?>
                                <span class="badge bg-danger"><?php echo $unreadNotifications; ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown">
                            <li><h6 class="dropdown-header">Bildirimleriniz</h6></li>
                            
                            <?php
                            $db = Database::getInstance();
                            $userId = $_SESSION['user_id'];
                            $notifications = $db->query("SELECT * FROM calibration_notifications WHERE user_id = $userId ORDER BY creation_date DESC LIMIT 5");
                            
                            if (!empty($notifications)): 
                                foreach ($notifications as $notification):
                            ?>
                                <li>
                                    <a class="dropdown-item <?php echo $notification['is_read'] ? '' : 'fw-bold'; ?>" href="<?php echo BASE_URL; ?>/admin/notifications.php?id=<?php echo $notification['notification_id']; ?>">
                                        <small class="text-muted"><?php echo formatDate($notification['creation_date'], 'd.m.Y H:i'); ?></small><br>
                                        <?php echo $notification['title']; ?>
                                    </a>
                                </li>
                            <?php 
                                endforeach;
                            else:
                            ?>
                                <li><a class="dropdown-item" href="#">Bildiriminiz bulunmuyor</a></li>
                            <?php endif; ?>
                            
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/notifications.php">Tüm Bildirimleri Görüntüle</a></li>
                        </ul>
                    </li>
                    
                    <!-- Kullanıcı menüsü -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($currentUser['username']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/profile.php"><i class="fas fa-user-cog me-2"></i>Profilim</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/change_password.php"><i class="fas fa-key me-2"></i>Şifre Değiştir</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Çıkış Yap</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Admin bölümü ana yapısı -->
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'sidebar.php'; ?>
            
            <!-- Ana içerik alanı -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['message_type'] ?? 'info'; ?> alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
                <?php endif; ?>