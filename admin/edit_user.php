<?php
$pageTitle = "Kullanıcı Düzenle - " . APP_NAME;
require_once 'header.php';

$db = Database::getInstance();

// Kullanıcı ID'sini al
$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($userId <= 0) {
    $_SESSION['message'] = 'Geçersiz kullanıcı ID!';
    $_SESSION['message_type'] = 'danger';
    header("Location: " . BASE_URL . "/admin/users.php");
    exit;
}

// Admin kendi kendisini silmeyi engelle
if ($userId === $_SESSION['user_id']) {
    $_SESSION['message'] = 'Kendi hesabınızı bu sayfadan düzenleyemezsiniz! Lütfen profil sayfasını kullanın.';
    $_SESSION['message_type'] = 'warning';
    header("Location: " . BASE_URL . "/admin/users.php");
    exit;
}

// Kullanıcı bilgilerini al
$user = $db->queryOne("SELECT * FROM calibration_users WHERE user_id = $userId");

if (!$user) {
    $_SESSION['message'] = 'Kullanıcı bulunamadı!';
    $_SESSION['message_type'] = 'danger';
    header("Location: " . BASE_URL . "/admin/users.php");
    exit;
}

// Form gönderilmiş mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !checkCSRF($_POST['csrf_token'])) {
        $_SESSION['message'] = 'Güvenlik hatası! Lütfen sayfayı yenileyip tekrar deneyin.';
        $_SESSION['message_type'] = 'danger';
        header("Location: " . BASE_URL . "/admin/edit_user.php?id=$userId");
        exit;
    }
    
    // Form verilerini al
    $email = sanitize($_POST['email']);
    $companyName = sanitize($_POST['company_name']);
    $address = sanitize($_POST['address']);
    $phone = sanitize($_POST['phone']);
    $userType = sanitize($_POST['user_type']);
    $status = sanitize($_POST['status']);
    $newPassword = $_POST['new_password'] ?? '';
    
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
        $query = "SELECT COUNT(*) FROM calibration_users WHERE email = '" . $db->escape($email) . "' AND user_id != $userId";
        $count = $db->queryValue($query);
        
        if ($count > 0) {
            $errors[] = 'Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor.';
        }
    }
    
    // Yeni şifre doğrulama
    if (!empty($newPassword) && strlen($newPassword) < 6) {
        $errors[] = 'Şifre en az 6 karakter olmalıdır.';
    }
    
    // Hata yoksa kullanıcıyı güncelle
    if (empty($errors)) {
        // Güncelleme sorgusu
        $query = "UPDATE calibration_users SET 
                     email = '" . $db->escape($email) . "', 
                     company_name = '" . $db->escape($companyName) . "', 
                     address = '" . $db->escape($address) . "', 
                     phone = '" . $db->escape($phone) . "', 
                     user_type = '" . $db->escape($userType) . "', 
                     status = '" . $db->escape($status) . "'";
        
        // Şifre değişikliği varsa onu da ekle
        if (!empty($newPassword)) {
            $hashedPassword = hashPassword($newPassword);
            $query .= ", password = '" . $hashedPassword . "'";
        }
        
        $query .= " WHERE user_id = $userId";
        
        $result = $db->query($query);
        
        if ($result) {
            // Kullanıcıya bildirim gönder
            $title = 'Hesap Bilgileriniz Güncellendi';
            $message = 'Hesap bilgileriniz yönetici tarafından güncellenmiştir.';
            if (!empty($newPassword)) {
                $message .= ' Yeni şifreniz: ' . $newPassword;
            }
            sendNotification($userId, $title, $message);
            
            // Aktiviteyi logla
            logActivity($_SESSION['user_id'], 'Kullanıcı Güncelleme', "Kullanıcı güncellendi: {$user['username']} - {$companyName}");
            
            $_SESSION['message'] = 'Kullanıcı başarıyla güncellendi!';
            $_SESSION['message_type'] = 'success';
            
            header("Location: " . BASE_URL . "/admin/users.php");
            exit;
        } else {
            $errors[] = 'Kullanıcı güncellenirken bir hata oluştu: ' . $db->error();
        }
    }
}
?>

<h1 class="h3 mb-4">Kullanıcı Düzenle</h1>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Kullanıcı Bilgileri</h6>
    </div>
    <div class="card-body">
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?id=' . $userId); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="username" class="form-label">Kullanıcı Adı</label>
                    <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled readonly>
                    <div class="form-text">Kullanıcı adı değiştirilemez.</div>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">E-posta Adresi <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
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
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="user_type" class="form-label">Kullanıcı Tipi <span class="text-danger">*</span></label>
                    <select class="form-select" id="user_type" name="user_type" required>
                        <option value="user" <?php echo $user['user_type'] === 'user' ? 'selected' : ''; ?>>Kullanıcı</option>
                        <option value="admin" <?php echo $user['user_type'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="status" class="form-label">Hesap Durumu <span class="text-danger">*</span></label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="approved" <?php echo $user['status'] === 'approved' ? 'selected' : ''; ?>>Onaylandı</option>
                        <option value="pending" <?php echo $user['status'] === 'pending' ? 'selected' : ''; ?>>Onay Bekliyor</option>
                        <option value="rejected" <?php echo $user['status'] === 'rejected' ? 'selected' : ''; ?>>Reddedildi</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="new_password" class="form-label">Yeni Şifre <span class="text-muted">(Değiştirmek istiyorsanız doldurun)</span></label>
                <div class="input-group">
                    <input type="password" class="form-control" id="new_password" name="new_password">
                    <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="generatePassword">
                        <i class="fas fa-key me-2"></i> Oluştur
                    </button>
                </div>
                <div class="form-text">En az 6 karakter olmalıdır. Boş bırakırsanız şifre değişmez.</div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-2"></i> Kaydet
            </button>
            <a href="<?php echo BASE_URL; ?>/admin/users.php" class="btn btn-secondary">
                <i class="fas fa-times me-2"></i> İptal
            </a>
        </form>
    </div>
</div>

<!-- Kullanıcı İstatistikleri -->
<?php
// Kullanıcı istatistikleri
$deviceCount = $db->queryValue("SELECT COUNT(*) FROM calibration_devices WHERE user_id = $userId");
$calibrationCount = $db->queryValue("SELECT COUNT(*) FROM calibration_records WHERE user_id = $userId");
$documentCount = $db->queryValue("SELECT COUNT(*) FROM calibration_documents cd JOIN calibration_records cr ON cd.calibration_id = cr.calibration_id WHERE cr.user_id = $userId");
$paymentCount = $db->queryValue("SELECT COUNT(*) FROM calibration_payments WHERE user_id = $userId");
$totalPayments = $db->queryValue("SELECT SUM(amount) FROM calibration_payments WHERE user_id = $userId");
$lastLogin = $user['last_login'] ? formatDate($user['last_login'], 'd.m.Y H:i') : 'Hiç giriş yapmadı';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Kullanıcı İstatistikleri</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <th width="40%">Kayıt Tarihi</th>
                            <td><?php echo formatDate($user['registration_date'], 'd.m.Y H:i'); ?></td>
                        </tr>
                        <tr>
                            <th>Son Giriş</th>
                            <td><?php echo $lastLogin; ?></td>
                        </tr>
                        <tr>
                            <th>Cihaz Sayısı</th>
                            <td><?php echo $deviceCount; ?></td>
                        </tr>
                        <tr>
                            <th>Kalibrasyon Sayısı</th>
                            <td><?php echo $calibrationCount; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <th width="40%">Belge Sayısı</th>
                            <td><?php echo $documentCount; ?></td>
                        </tr>
                        <tr>
                            <th>Ödeme Sayısı</th>
                            <td><?php echo $paymentCount; ?></td>
                        </tr>
                        <tr>
                            <th>Toplam Ödeme</th>
                            <td><?php echo formatMoney($totalPayments ?? 0); ?></td>
                        </tr>
                        <tr>
                            <th>Kalan Ödeme</th>
                            <td><?php echo formatMoney(getRemainingPaymentForUser($userId)); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                    <a href="<?php echo BASE_URL; ?>/admin/devices.php?user_id=<?php echo $userId; ?>" class="btn btn-info">
                        <i class="fas fa-tools me-2"></i> Kullanıcının Cihazları
                    </a>
                    <a href="<?php echo BASE_URL; ?>/admin/calibrations.php?user_id=<?php echo $userId; ?>" class="btn btn-primary">
                        <i class="fas fa-clipboard-check me-2"></i> Kullanıcının Kalibrasyonları
                    </a>
                    <a href="<?php echo BASE_URL; ?>/admin/payments.php?user_id=<?php echo $userId; ?>" class="btn btn-success">
                        <i class="fas fa-money-bill-wave me-2"></i> Kullanıcının Ödemeleri
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Şifre göster/gizle butonu
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordField = document.getElementById('new_password');
            const icon = this.querySelector('i');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Rastgele şifre oluştur
        document.getElementById('generatePassword').addEventListener('click', function() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()';
            let password = '';
            
            for (let i = 0; i < 10; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            
            document.getElementById('new_password').value = password;
            document.getElementById('new_password').type = 'text';
            
            // Buton ikonunu güncelle
            document.querySelector('#togglePassword i').classList.remove('fa-eye');
            document.querySelector('#togglePassword i').classList.add('fa-eye-slash');
        });
    });
</script>

<?php require_once 'footer.php'; ?>