<?php
// Veritabanı bağlantı bilgileri
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'kortekgsmdatalog_gsmapi'); // Veritabanı kullanıcı adınızı buraya girin
define('DB_PASSWORD', 'Berkay0100'); // Veritabanı şifrenizi buraya girin
define('DB_NAME', 'kortekgsmdatalog_gsmarge');

// Uygulama URL'si
define('BASE_URL', 'http://kortekgsmdatalogger.com.tr/calibration');

// Dosya yükleme ayarları
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 10485760); // 10MB maksimum dosya boyutu
define('ALLOWED_FILE_TYPES', serialize(array('application/pdf'))); // İzin verilen dosya türleri

// Session ayarları
define('SESSION_NAME', 'TCL_CALIBRATION_SESSION');
define('SESSION_LIFETIME', 86400); // 24 saat

// Güvenlik ayarları
define('HASH_COST', 10); // bcrypt maliyeti, 10-12 arası önerilen değer

// Uygulama bilgileri
define('APP_NAME', 'TCL Türkel Kalibrasyon Erişim Sistemi');
define('APP_VERSION', '1.0.0');
define('APP_AUTHOR', 'TCL Türkel');
define('APP_EMAIL', 'info@tcl.com');

// Tarih ayarları
date_default_timezone_set('Europe/Istanbul');

// Hata raporlama (geliştirme ortamı için)
// Canlı ortamda bunları kapatın veya günlüğe yazdırın
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Dil ayarları
define('DEFAULT_LANG', 'tr'); // Varsayılan dil
setlocale(LC_TIME, 'tr_TR.UTF-8', 'tr_TR', 'tr', 'turkish');
?>