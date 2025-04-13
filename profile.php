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

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// Admin kullanıcıları kendi profillerini düzenleyecek
$isAdminProfile = isAdmin();

// Kullanıcı bilgilerini al
$user = getUserInfo($userId);

if (!$user) {
    $_SESSION['message'] = 'Kullanıcı bilgileri alınamadı!';
    $_SESSION['message_type'] = 'danger';
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}

// Form gönderilmiş mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !checkCSRF($_POST['csrf_token'])) {
        $_SESSION['message'] = 'Güvenlik hatası! Lütfen sayfayı yenileyip tekrar deneyin.';
        $_SESSION['message_type'] = 'danger';
        header("Location: " . BASE_URL . "/profile.php");
        exit;
    }
    
    // Form verilerini al
    $email = sanitize($_POST['email']);
    $companyName = sanitize($_POST['company_name']);
    $address = sanitize($_POST['address']);
    $phone = sanitize($_POST['phone']);
    
    // Basit doğrulama
    $errors = [];
    
    if (empty($email)) {
        $errors[] = 'E-posta adresi gereklidir.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Geçerli bir e-posta adresi giriniz.';
    }
    
    if (empty($companyName)) {
        $errors[] = 'Şirket adı gereklidir.';
    }
    
    // E-posta adresinin başka kullanıcılarda kullanılıp kullanılmadığını kontrol et
    if ($email !== $user['email']) {
        $query = "SELECT COUNT(*) FROM calibration_users WHERE email = '" . $db->escape($email) . "' AND user_id != " . intval($userId);
        $count = $db->queryValue($query);
        
        if ($count > 0) {
            $errors[] = 'Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor.';
        }
    }
    
    // Hata yoksa profili güncelle
    if (empty($errors)) {
        $query = "UPDATE calibration_users SET 
                    email = '" . $db->escape($email) . "', 
                    company_name = '" . $db->escape($companyName) . "', 
                    address = '" . $db->escape($address) . "', 
                    phone = '" . $db->escape($phone) . "' 
                  WHERE user_id = " . intval($userId);
                  
        $result = $db->query($query);
        
        if ($result) {
            // Aktiviteyi logla
            logActivity($userId, 'Profil Güncelleme', "Kullanıcı kendi profilini güncelledi");
            
            $_SESSION['message'] = 'Profiliniz başarıyla güncellendi!';
            $_SESSION['message_type'] = 'success';
            
            // Değişiklikleri görmek için sayfayı yeniden yükle
            header("Location: " . BASE_URL . "/profile.php");
            exit;
        } else {
            $errors[] = 'Profil güncellenirken bir hata oluştu: ' . $db->error();
        }
    }
}

$pageTitle = "Profilim - " . APP_NAME;
?>

<?php require_once 'header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Profilim</h1>
</div>

<?php if (isset($errors) && !empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-4 mb-4">
        <!-- Profil Özet Kartı -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Hesap Bilgileri</h6>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <img class="img-fluid rounded-circle mb-3" src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['company_name']); ?>&background=random&color=fff&size=128" alt="<?php echo htmlspecialchars($user['company_name']); ?> Avatar">
                    
                    <h5><?php echo htmlspecialchars($user['company_name']); ?></h5>
                    <p class="text-muted">
                        <?php echo $user['user_type'] === 'admin' ? 'Yönetici' : 'Kullanıcı'; ?>
                    </p>
                    <hr>
                    <p class="text-muted mb-0">
                        <strong>Kullanıcı Adı:</strong> <?php echo htmlspecialchars($user['username']); ?>
                    </p>
                    <p class="text-muted mb-0">
                        <strong>Son Giriş:</strong> <?php echo formatDate($user['last_login'], 'd.m.Y H:i'); ?>
                    </p>
                    <p class="text-muted mb-0">
                        <strong>Kayıt Tarihi:</strong> <?php echo formatDate($user['registration_date'], 'd.m.Y'); ?>
                    </p>
                </div>
                
                <div class="d-grid gap-2">
                    <a href="<?php echo BASE_URL; ?>/change_password.php" class="btn btn-primary">
                        <i class="fas fa-key me-2"></i> Şifremi Değiştir
                    </a>
                    <?php if ($isAdminProfile): ?>
                    <a href="<?php echo BASE_URL; ?>/admin/index.php" class="btn btn-info">
                        <i class="fas fa-tachometer-alt me-2"></i> Admin Paneline Dön
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- İstatistikler Kartı -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">İstatistikler</h6>
            </div>
            <div class="card-body">
                <?php
                // Cihaz sayısı
                $deviceCount = $db->queryValue("SELECT COUNT(*) FROM calibration_devices WHERE user_id = $userId");
                
                // Kalibrasyon sayısı
                $calibrationCount = $db->queryValue("SELECT COUNT(*) FROM calibration_records WHERE user_id = $userId");
                
                // Belge sayısı
                $documentCount = $db->queryValue("SELECT COUNT(*) FROM calibration_documents cd 
                                                  JOIN calibration_records cr ON cd.calibration_id = cr.calibration_id 
                                                  WHERE cr.user_id = $userId");
                ?>
                <div class="row text-center">
                    <div class="col-4">
                        <div class="h4 mb-0"><?php echo $deviceCount; ?></div>
                        <div class="small text-muted">Cihazlar</div>
                    </div>
                    <div class="col-4">
                        <div class="h4 mb-0"><?php echo $calibrationCount; ?></div>
                        <div class="small text-muted">Kalibrasyonlar</div>
                    </div>
                    <div class="col-4">
                        <div class="h4 mb-0"><?php echo $documentCount; ?></div>
                        <div class="small text-muted">Belgeler</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <!-- Profil Düzenleme Kartı -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Profil Bilgilerini Düzenle</h6>
            </div>
            <div class="card-body">
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Kullanıcı Adı</label>
                        <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled readonly>
                        <div class="form-text">Kullanıcı adı değiştirilemez.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">E-posta Adresi <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="company_name" class="form-label">Şirket Adı <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="company_name" name="company_name" value="<?php echo htmlspecialchars($user['company_name']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Adres</label>
                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Telefon</label>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Kaydet
                    </button>
                    <a href="<?php echo BASE_URL; ?>/dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i> İptal
                    </a>
                </form>
            </div>
        </div>
        
        <!-- Şirket Bilgileri Kartı -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Şirket Bilgileri</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-2"><strong>Şirket Adı:</strong> <?php echo htmlspecialchars($user['company_name']); ?></p>
                        <p class="mb-2"><strong>E-posta:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                        <p class="mb-2"><strong>Telefon:</strong> <?php echo htmlspecialchars($user['phone'] ?? 'Belirtilmemiş'); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-2"><strong>Adres:</strong></p>
                        <p><?php echo nl2br(htmlspecialchars($user['address'] ?? 'Belirtilmemiş')); ?></p>
                    </div>
                </div>
                
                <hr>
                
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-2"></i> Şirket bilgileriniz kalibrasyon sertifikalarında kullanılacaktır. Lütfen bilgilerinizin doğru ve güncel olduğundan emin olun.
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>