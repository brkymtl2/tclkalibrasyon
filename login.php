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

$error = '';
$username = '';

// Form gönderilmiş mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !checkCSRF($_POST['csrf_token'])) {
        $_SESSION['message'] = 'Güvenlik hatası! Lütfen sayfayı yenileyip tekrar deneyin.';
        $_SESSION['message_type'] = 'danger';
        header("Location: " . BASE_URL . "/login.php");
        exit;
    }
    
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Kullanıcı adı ve şifre gereklidir!';
    } else {
        $db = Database::getInstance();
        
        // Kullanıcıyı veritabanında ara
        $query = "SELECT * FROM calibration_users WHERE username = '" . $db->escape($username) . "' LIMIT 1";
        $user = $db->queryOne($query);
        
        if ($user && verifyPassword($password, $user['password'])) {
            // Kullanıcı durumunu kontrol et
            if ($user['status'] === 'pending') {
                $error = 'Hesabınız henüz onaylanmamış. Lütfen onay için bekleyin.';
            } elseif ($user['status'] === 'rejected') {
                $error = 'Hesabınız reddedilmiş. Daha fazla bilgi için lütfen bizimle iletişime geçin.';
            } else {
                // Session'a kullanıcı bilgilerini kaydet
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_type'] = $user['user_type'];
                
                // Son giriş tarihini güncelle
                $db->query("UPDATE calibration_users SET last_login = NOW() WHERE user_id = " . intval($user['user_id']));
                
                // Giriş aktivitesini logla
                logActivity($user['user_id'], 'Giriş', 'Kullanıcı giriş yaptı');
                
                // Kullanıcı türüne göre yönlendir
                if ($user['user_type'] === 'admin') {
                    header("Location: " . BASE_URL . "/admin/index.php");
                } else {
                    header("Location: " . BASE_URL . "/dashboard.php");
                }
                exit;
            }
        } else {
            $error = 'Geçersiz kullanıcı adı veya şifre!';
            // Başarısız giriş denemesini logla
            logActivity(0, 'Başarısız Giriş', "Kullanıcı adı: $username");
        }
    }
}

$pageTitle = "Giriş Yap - " . APP_NAME;
?>

<?php require_once 'header.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-sign-in-alt me-2"></i> Giriş Yap
                </h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Kullanıcı Adı</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required autofocus>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Şifre</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i> Giriş Yap
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center">
                <p class="mb-0">Hesabınız yok mu? <a href="<?php echo BASE_URL; ?>/register.php">Kayıt Ol</a></p>
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
</script>

<?php require_once 'footer.php'; ?>