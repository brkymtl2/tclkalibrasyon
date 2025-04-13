<?php
require_once 'config.php';
require_once 'db_connect.php';
require_once 'functions.php';

// Session başlat
initSession();

// Kullanıcı zaten giriş yapmış mı kontrol et
if (isLoggedIn()) {
    // Kullanıcı türüne göre yönlendir
    if (isAdmin()) {
        header("Location: " . BASE_URL . "/admin/index.php");
    } else {
        header("Location: " . BASE_URL . "/dashboard.php");
    }
    exit;
}

$errors = [];
$formData = [
    'username' => '',
    'email' => '',
    'company_name' => '',
    'address' => '',
    'phone' => ''
];

// Form gönderilmiş mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !checkCSRF($_POST['csrf_token'])) {
        $_SESSION['message'] = 'Güvenlik hatası! Lütfen sayfayı yenileyip tekrar deneyin.';
        $_SESSION['message_type'] = 'danger';
        header("Location: " . BASE_URL . "/register.php");
        exit;
    }
    
    // Form verilerini al ve temizle
    $formData = [
        'username' => sanitize($_POST['username'] ?? ''),
        'email' => sanitize($_POST['email'] ?? ''),
        'company_name' => sanitize($_POST['company_name'] ?? ''),
        'address' => sanitize($_POST['address'] ?? ''),
        'phone' => sanitize($_POST['phone'] ?? '')
    ];
    
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Form doğrulama
    if (empty($formData['username'])) {
        $errors[] = 'Kullanıcı adı gereklidir.';
    } elseif (strlen($formData['username']) < 3) {
        $errors[] = 'Kullanıcı adı en az 3 karakter olmalıdır.';
    }
    
    if (empty($formData['email'])) {
        $errors[] = 'E-posta adresi gereklidir.';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Geçerli bir e-posta adresi giriniz.';
    }
    
    if (empty($formData['company_name'])) {
        $errors[] = 'Şirket adı gereklidir.';
    }
    
    if (empty($password)) {
        $errors[] = 'Şifre gereklidir.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Şifre en az 6 karakter olmalıdır.';
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = 'Şifreler eşleşmiyor.';
    }
    
    // Hata yoksa kaydı oluştur
    if (empty($errors)) {
        $db = Database::getInstance();
        
        // Kullanıcı adı veya e-posta zaten kullanılıyor mu kontrol et
        $query = "SELECT COUNT(*) FROM calibration_users WHERE username = '" . $db->escape($formData['username']) . "' OR email = '" . $db->escape($formData['email']) . "'";
        $count = $db->queryValue($query);
        
        if ($count > 0) {
            $errors[] = 'Bu kullanıcı adı veya e-posta adresi zaten kullanılıyor.';
        } else {
            // Şifreyi hashle
            $hashedPassword = hashPassword($password);
            
            // Kullanıcıyı veritabanına ekle
            $query = "INSERT INTO calibration_users (username, password, email, company_name, address, phone, status, user_type) 
                      VALUES (
                          '" . $db->escape($formData['username']) . "', 
                          '" . $hashedPassword . "', 
                          '" . $db->escape($formData['email']) . "', 
                          '" . $db->escape($formData['company_name']) . "', 
                          '" . $db->escape($formData['address']) . "', 
                          '" . $db->escape($formData['phone']) . "', 
                          'pending', 
                          'user'
                      )";
            
            $result = $db->query($query);
            
            if ($result) {
                $userId = $db->lastInsertId();
                
                // Admin kullanıcılarına bildirim gönder
                $db->query("INSERT INTO calibration_notifications (user_id, title, message) 
                            SELECT user_id, 'Yeni Kullanıcı Kaydı', 'Yeni bir kullanıcı kaydı yapıldı: " . $db->escape($formData['username']) . " - " . $db->escape($formData['company_name']) . "' 
                            FROM calibration_users WHERE user_type = 'admin'");
                
                // Aktiviteyi logla
                logActivity($userId, 'Kayıt', 'Yeni kullanıcı kaydı yapıldı');
                
                // Başarı mesajı göster ve login sayfasına yönlendir
                $_SESSION['message'] = 'Kaydınız başarıyla tamamlandı! Hesabınız onaylandıktan sonra giriş yapabilirsiniz.';
                $_SESSION['message_type'] = 'success';
                header("Location: " . BASE_URL . "/login.php");
                exit;
            } else {
                $errors[] = 'Kayıt sırasında bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
            }
        }
    }
}

$pageTitle = "Kayıt Ol - " . APP_NAME;
?>

<?php require_once 'header.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-user-plus me-2"></i> Kayıt Ol
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label">Kullanıcı Adı <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($formData['username']); ?>" required>
                            </div>
                            <div class="form-text">En az 3 karakter olmalıdır.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">E-posta Adresi <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($formData['email']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="company_name" class="form-label">Şirket Adı <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-building"></i></span>
                            <input type="text" class="form-control" id="company_name" name="company_name" value="<?php echo htmlspecialchars($formData['company_name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Adres</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                            <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($formData['address']); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Telefon</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($formData['phone']); ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">Şifre <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">En az 6 karakter olmalıdır.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label">Şifre Tekrar <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <button type="button" class="btn btn-outline-secondary" id="toggleConfirmPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                        <label class="form-check-label" for="terms">
                            <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Hizmet şartlarını</a> kabul ediyorum.
                        </label>
                    </div>
                    
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle me-2"></i> Kaydınız, yönetici onayı sonrasında aktif hale gelecektir.
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-plus me-2"></i> Kayıt Ol
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center">
                <p class="mb-0">Zaten hesabınız var mı? <a href="<?php echo BASE_URL; ?>/login.php">Giriş Yap</a></p>
            </div>
        </div>
    </div>
</div>

<!-- Hizmet Şartları Modal -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="termsModalLabel">Hizmet Şartları</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h5>TCL Türkel Kalibrasyon Erişim Sistemi Kullanım Şartları</h5>
                <p>Bu hizmet şartları, TCL Türkel Kalibrasyon Erişim Sistemi'ni kullanımınızı düzenler.</p>
                
                <h6>1. Hizmet Kullanımı</h6>
                <p>TCL Türkel Kalibrasyon Erişim Sistemi, kalibrasyon hizmetlerinin takibi ve yönetimi için tasarlanmıştır. Sistemi amacı dışında kullanmayacağınızı kabul edersiniz.</p>
                
                <h6>2. Hesap Güvenliği</h6>
                <p>Hesabınızın güvenliğinden siz sorumlusunuz. Güçlü bir şifre kullanmanız ve şifrenizi gizli tutmanız gerekmektedir.</p>
                
                <h6>3. Gizlilik</h6>
                <p>Kişisel ve kurumsal bilgileriniz, kalibrasyon hizmetleri kapsamında kullanılacak ve gizli tutulacaktır.</p>
                
                <h6>4. Ödeme Koşulları</h6>
                <p>Kalibrasyon hizmetlerine ilişkin ödemeler, belirlenen koşullarda ve sürelerde yapılmalıdır.</p>
                
                <h6>5. Hizmet Değişiklikleri</h6>
                <p>TCL Türkel, hizmet şartlarını ve sistemi önceden haber vermeksizin değiştirme hakkını saklı tutar.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Anladım</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Şifre göster/gizle butonu
    document.getElementById('togglePassword').addEventListener('click', function() {
        const passwordField = document.getElementById('password');
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
    
    // Şifre tekrar göster/gizle butonu
    document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
        const confirmPasswordField = document.getElementById('confirm_password');
        const icon = this.querySelector('i');
        
        if (confirmPasswordField.type === 'password') {
            confirmPasswordField.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            confirmPasswordField.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });
</script>

<?php require_once 'footer.php'; ?>