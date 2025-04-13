<?php
$pageTitle = "Kullanıcı Ekle - " . APP_NAME;
require_once 'header.php';

$db = Database::getInstance();

// Form gönderilmiş mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !checkCSRF($_POST['csrf_token'])) {
        $_SESSION['message'] = 'Güvenlik hatası! Lütfen sayfayı yenileyip tekrar deneyin.';
        $_SESSION['message_type'] = 'danger';
        header("Location: " . BASE_URL . "/admin/add_user.php");
        exit;
    }
    
    // Form verilerini al
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $companyName = sanitize($_POST['company_name']);
    $address = sanitize($_POST['address']);
    $phone = sanitize($_POST['phone']);
    $userType = sanitize($_POST['user_type']);
    $status = sanitize($_POST['status']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Basit doğrulama
    $errors = [];
    
    if (empty($username)) {
        $errors[] = 'Kullanıcı adı gereklidir.';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Kullanıcı adı en az 3 karakter olmalıdır.';
    }
    
    if (empty($email)) {
        $errors[] = 'E-posta adresi gereklidir.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Geçerli bir e-posta adresi giriniz.';
    }
    
    if (empty($companyName)) {
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
    
    // Kullanıcı adı ve e-posta benzersiz mi?
    $query = "SELECT COUNT(*) FROM calibration_users WHERE username = '" . $db->escape($username) . "' OR email = '" . $db->escape($email) . "'";
    $count = $db->queryValue($query);
    
    if ($count > 0) {
        $errors[] = 'Bu kullanıcı adı veya e-posta adresi zaten kullanılıyor.';
    }
    
    // Hata yoksa kaydı oluştur
    if (empty($errors)) {
        // Şifreyi hashle
        $hashedPassword = hashPassword($password);
        
        $query = "INSERT INTO calibration_users (username, password, email, company_name, address, phone, status, user_type) 
                  VALUES (
                     '" . $db->escape($username) . "', 
                     '" . $hashedPassword . "', 
                     '" . $db->escape($email) . "', 
                     '" . $db->escape($companyName) . "', 
                     '" . $db->escape($address) . "', 
                     '" . $db->escape($phone) . "', 
                     '" . $db->escape($status) . "', 
                     '" . $db->escape($userType) . "'
                  )";
                  
        $result = $db->query($query);
        
        if ($result) {
            $userId = $db->lastInsertId();
            
            // Aktiviteyi logla
            logActivity($_SESSION['user_id'], 'Kullanıcı Ekleme', "Yeni kullanıcı eklendi: {$username} - {$companyName}");
            
            $_SESSION['message'] = 'Kullanıcı başarıyla eklendi!';
            $_SESSION['message_type'] = 'success';
            
            header("Location: " . BASE_URL . "/admin/users.php");
            exit;
        } else {
            $errors[] = 'Kullanıcı eklenirken bir hata oluştu: ' . $db->error();
        }
    }
}
?>

<h1 class="h3 mb-4">Kullanıcı Ekle</h1>

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
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="username" class="form-label">Kullanıcı Adı <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="username" name="username" required>
                    <div class="form-text">En az 3 karakter olmalıdır.</div>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">E-posta Adresi <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="company_name" class="form-label">Şirket Adı <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="company_name" name="company_name" required>
            </div>
            
            <div class="mb-3">
                <label for="address" class="form-label">Adres</label>
                <textarea class="form-control" id="address" name="address" rows="2"></textarea>
            </div>
            
            <div class="mb-3">
                <label for="phone" class="form-label">Telefon</label>
                <input type="text" class="form-control" id="phone" name="phone">
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="user_type" class="form-label">Kullanıcı Tipi <span class="text-danger">*</span></label>
                    <select class="form-select" id="user_type" name="user_type" required>
                        <option value="user">Kullanıcı</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="status" class="form-label">Hesap Durumu <span class="text-danger">*</span></label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="approved">Onaylandı</option>
                        <option value="pending">Onay Bekliyor</option>
                        <option value="rejected">Reddedildi</option>
                    </select>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="password" class="form-label">Şifre <span class="text-danger">*</span></label>
                    <div class="input-group">
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
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        <button type="button" class="btn btn-outline-secondary" id="toggleConfirmPassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <button type="button" class="btn btn-outline-secondary" id="generatePassword">
                    <i class="fas fa-key me-2"></i> Rastgele Şifre Oluştur
                </button>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-2"></i> Kullanıcıyı Kaydet
            </button>
            <a href="<?php echo BASE_URL; ?>/admin/users.php" class="btn btn-secondary">
                <i class="fas fa-times me-2"></i> İptal
            </a>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Şifre göster/gizle butonları
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
        
        // Rastgele şifre oluştur
        document.getElementById('generatePassword').addEventListener('click', function() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()';
            let password = '';
            
            for (let i = 0; i < 10; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            
            document.getElementById('password').value = password;
            document.getElementById('confirm_password').value = password;
            
            // Şifreyi göster
            document.getElementById('password').type = 'text';
            document.getElementById('confirm_password').type = 'text';
            
            // Buton ikonlarını güncelle
            document.querySelector('#togglePassword i').classList.remove('fa-eye');
            document.querySelector('#togglePassword i').classList.add('fa-eye-slash');
            document.querySelector('#toggleConfirmPassword i').classList.remove('fa-eye');
            document.querySelector('#toggleConfirmPassword i').classList.add('fa-eye-slash');
            
            // 3 saniye sonra gizle
            setTimeout(function() {
                document.getElementById('password').type = 'password';
                document.getElementById('confirm_password').type = 'password';
                
                // Buton ikonlarını güncelle
                document.querySelector('#togglePassword i').classList.remove('fa-eye-slash');
                document.querySelector('#togglePassword i').classList.add('fa-eye');
                document.querySelector('#toggleConfirmPassword i').classList.remove('fa-eye-slash');
                document.querySelector('#toggleConfirmPassword i').classList.add('fa-eye');
            }, 3000);
        });
    });
</script>

<?php require_once 'footer.php'; ?>