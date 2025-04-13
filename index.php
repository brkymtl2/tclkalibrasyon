<?php
require_once 'config.php';
require_once 'db_connect.php';
require_once 'functions.php';

// Session başlat
initSession();

// Kullanıcı giriş yapmış mı kontrol et
if (isLoggedIn()) {
    // Kullanıcı türüne göre yönlendir
    if (isAdmin()) {
        header("Location: " . BASE_URL . "/admin/index.php");
    } else {
        header("Location: " . BASE_URL . "/dashboard.php");
    }
    exit;
}

$pageTitle = APP_NAME;
?>

<?php require_once 'header.php'; ?>

<div class="row align-items-center py-5">
    <div class="col-md-6">
        <h1 class="display-5 fw-bold">TCL Türkel Kalibrasyon Erişim Sistemi</h1>
        <p class="lead">Kalibrasyon hizmetlerinizi yönetmek için profesyonel bir platform.</p>
        <p>TCL Türkel Kalibrasyon Erişim Sistemi ile kalibrasyon süreçlerinizi kolayca takip edin, belgelerinize her yerden erişin ve yaklaşan kalibrasyonlardan haberdar olun.</p>
        <div class="d-grid gap-2 d-md-flex justify-content-md-start mt-4">
            <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-primary btn-lg me-md-2">
                <i class="fas fa-sign-in-alt me-2"></i> Giriş Yap
            </a>
            <a href="<?php echo BASE_URL; ?>/register.php" class="btn btn-outline-primary btn-lg">
                <i class="fas fa-user-plus me-2"></i> Kayıt Ol
            </a>
        </div>
</div>

<hr class="my-5">

<div class="row justify-content-center mb-5">
    <div class="col-md-10">
        <h2 class="text-center mb-4">Sıkça Sorulan Sorular</h2>
        <div class="accordion" id="faqAccordion">
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingOne">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                        Kalibrasyon belgeleri ne kadar sürede hazırlanır?
                    </button>
                </h2>
                <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        Kalibrasyon belgeleri, cihaz tipine ve yoğunluğa bağlı olarak genellikle 3-5 iş günü içerisinde hazırlanmaktadır. Acil durumlar için hızlandırılmış servisimiz mevcuttur.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingTwo">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                        Kalibrasyon süreci nasıl işler?
                    </button>
                </h2>
                <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        Kalibrasyon süreci, cihazların laboratuvarımıza ulaşması veya yerinde kalibrasyon talebinin alınmasıyla başlar. Ardından uzman teknisyenlerimiz tarafından uygun standartlar kullanılarak kalibrasyon işlemi gerçekleştirilir. Son olarak raporlama ve sertifikalandırma yapılarak süreç tamamlanır.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingThree">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                        Kalibrasyon yenileme sıklığı ne olmalıdır?
                    </button>
                </h2>
                <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        Kalibrasyon yenileme sıklığı cihaz tipine, kullanım yoğunluğuna ve uygulama alanına göre değişiklik gösterir. Genel olarak, kritik ölçüm cihazları için 6 ay, standart cihazlar için 12 ay, düşük riskli cihazlar için 24 ay aralıklarla kalibrasyon önerilmektedir. Sizin için en uygun kalibrasyon periyodunu belirlemek için uzmanlarımızla iletişime geçebilirsiniz.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
    </div>
    <div class="col-md-6">
        <img src="<?php echo BASE_URL; ?>/assets/img/calibration.svg" alt="TCL Türkel Kalibrasyon" class="img-fluid">
    </div>
</div>

<hr class="my-5">

<div class="row g-4 py-4">
    <div class="col-md-4">
        <div class="card h-100 shadow-sm">
            <div class="card-body text-center">
                <i class="fas fa-tasks fa-4x text-primary mb-3"></i>
                <h3 class="card-title">Kolay Takip</h3>
                <p class="card-text">Tüm kalibrasyon işlemlerinizi tek bir arayüzden yönetin ve takip edin.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 shadow-sm">
            <div class="card-body text-center">
                <i class="fas fa-bell fa-4x text-primary mb-3"></i>
                <h3 class="card-title">Bildirimler</h3>
                <p class="card-text">Yaklaşan kalibrasyonlar için otomatik bildirimler alın, hiçbir işlemi kaçırmayın.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 shadow-sm">
            <div class="card-body text-center">
                <i class="fas fa-file-pdf fa-4x text-primary mb-3"></i>
                <h3 class="card-title">Belge Arşivi</h3>
                <p class="card-text">Kalibrasyon belgelerinizi güvenle saklayın, istediğiniz zaman erişin.</p>
            </div>
        </div>
    </div>
</div>

    <div class="bg-light p-5 rounded mt-5">
    <div class="row">
        <div class="col-md-8">
            <h2>Neden TCL Türkel Kalibrasyon?</h2>
            <p class="lead">Profesyonel kalibrasyon hizmetleri için doğru adres.</p>
            <ul class="list-group list-group-flush bg-transparent">
                <li class="list-group-item bg-transparent"><i class="fas fa-check-circle text-success me-2"></i> Konusunda uzman teknik ekip</li>
                <li class="list-group-item bg-transparent"><i class="fas fa-check-circle text-success me-2"></i> Modern kalibrasyon cihazları</li>
                <li class="list-group-item bg-transparent"><i class="fas fa-check-circle text-success me-2"></i> Uluslararası standartlara uygunluk</li>
                <li class="list-group-item bg-transparent"><i class="fas fa-check-circle text-success me-2"></i> Hızlı servis ve hizmet kalitesi</li>
                <li class="list-group-item bg-transparent"><i class="fas fa-check-circle text-success me-2"></i> 7/24 teknik destek</li>
            </ul>
        </div>
        <div class="col-md-4 d-flex align-items-center justify-content-center">
            <a href="<?php echo BASE_URL; ?>/contact.php" class="btn btn-lg btn-primary">
                <i class="fas fa-phone-alt me-2"></i> Bizimle İletişime Geçin
            </a>
        </div>
    </div>