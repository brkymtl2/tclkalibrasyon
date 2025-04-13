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

// Form gönderilmiş mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !checkCSRF($_POST['csrf_token'])) {
        $_SESSION['message'] = 'Güvenlik hatası! Lütfen sayfayı yenileyip tekrar deneyin.';
        $_SESSION['message_type'] = 'danger';
        header("Location: " . BASE_URL . "/change_password.php");
        exit;
    }
    
    // Form verilerini al
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Basit doğrulama
    $errors = [];
    
    if (empty($currentPassword)) {
        $errors[] = 'Mevcut şifre gereklidir.';
    }
    
    if (empty($newPassword)) {
        $errors[] = 'Yeni şifre gereklidir.';
    } elseif (strlen($newPassword) < 6) {
        $errors[] = 'Yeni şifre en az 6 karakter olmalıdır.';
    }
    
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'Yeni şifreler eşleşmiyor.';
    }
    
    // Mevcut şifreyi doğrula
    if (empty($errors)) {
        $query = "SELECT password FROM calibration_users WHERE user_id = $userId";
        $result = $db->queryOne($query);
        
        if (!$result || !verifyPassword($currentPassword, $result['password'])) {
            $errors[] = 'Mevcut şifre yanlış.';
        }
    }
    
    // Hata yoksa şifreyi güncelle
    if (empty($errors)) {
        // Yeni şifreyi hashle
        $hashedPassword = hashPassword($newPassword);
        
        $query = "UPDATE calibration_users SET password = '$hashedPassword' WHERE user_id = $userId";
        $result = $db->query($query);
        
        if ($result) {
            // Aktiviteyi logla
            logActivity($userId, 'Şifre Değiştirme', "Kullanıcı şifresini değiştirdi.");
            
            $_SESSION['message'] = 'Şifreniz başarıyla değiştirildi!';
            $_SESSION['message_type'] = 'success';
            
            // Admin kullanıcısı mı kontrol et
            if (isAdmin()) {
                header("Location: " . BASE_URL . "/admin/index.php");
            } else {
                header("Location: " . BASE_URL . "/dashboard.php");
            }
            exit;
        } else {
            $errors[] = 'Şifre değiştirilirken bir hata oluştu: ' . $db->error();
        }
    }
}

$pageTitle = "Şifre Değiştir - " . APP_NAME;
?>

<?php require_once 'header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Şifre Değiştir</h1>
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

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Şifre Değiştirme Formu</h6>
            </div>
            <div class="card-body">
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Mevcut Şifre <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                            <button type="button" class="btn btn-outline-secondary toggle-password" data-target="current_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Yeni Şifre <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <button type="button" class="btn btn-outline-secondary toggle-password" data-target="new_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">En az 6 karakter olmalıdır.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Yeni Şifre Tekrar <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <button type="button" class="btn btn-outline-secondary toggle-password" data-target="confirm_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <button type="button" class="btn btn-outline-secondary" id="generatePassword">
                            <i class="fas fa-key me-2"></i> Rastgele Şifre Oluştur
                        </button>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Şifreyi Değiştir
                    </button>
                    
                    <?php if (isAdmin()): ?>
                        <a href="<?php echo BASE_URL; ?>/admin/index.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i> İptal
                        </a>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>/dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i> İptal
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <!-- Şifre Güvenliği Kartı -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Şifre Güvenliği Önerileri</h6>
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li>En az 8 karakter uzunluğunda bir şifre seçin</li>
                    <li>Büyük harf, küçük harf, rakam ve özel karakter kombinasyonu kullanın</li>
                    <li>Kişisel bilgilerinizi (doğum tarihi, isim) şifrenizde kullanmayın</li>
                    <li>Farklı hesaplar için aynı şifreyi kullanmayın</li>
                    <li>Şifrenizi belirli aralıklarla değiştirin</li>
                    <li>Şifrenizi kimseyle paylaşmayın</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Şifre göster/gizle butonları
        const toggleButtons = document.querySelectorAll('.toggle-password');
        toggleButtons.forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const passwordField = document.getElementById(targetId);
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
        });
        
        // Rastgele şifre oluştur
        document.getElementById('generatePassword').addEventListener('click', function() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()';
            let password = '';
            
            for (let i = 0; i < 10; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            
            document.getElementById('new_password').value = password;
            document.getElementById('confirm_password').value = password;
            
            // Şifreyi göster
            document.getElementById('new_password').type = 'text';
            document.getElementById('confirm_password').type = 'text';
            
            // Buton ikonlarını güncelle
            document.querySelectorAll('.toggle-password[data-target="new_password"] i, .toggle-password[data-target="confirm_password"] i').forEach(icon => {
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            });
            
            // 3 saniye sonra gizle
            setTimeout(function() {
                document.getElementById('new_password').type = 'password';
                document.getElementById('confirm_password').type = 'password';
                
                // Buton ikonlarını güncelle
                document.querySelectorAll('.toggle-password[data-target="new_password"] i, .toggle-password[data-target="confirm_password"] i').forEach(icon => {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                });
            }, 3000);
        });
    });
</script>

<?php require_once 'footer.php'; ?>