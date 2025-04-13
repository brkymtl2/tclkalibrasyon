/**
 * TCL Türkel Kalibrasyon Erişim Sistemi
 * Admin Panel JavaScript dosyası
 */

document.addEventListener('DOMContentLoaded', function() {
    // DataTables'ı etkinleştir
    setupDataTables();
    
    // Dashboard grafikleri
    setupCharts();
    
    // Sidebar toggle
    setupSidebar();
    
    // Silme işlemleri için onay
    setupDeleteConfirmations();
    
    // Bildirim badgeleri
    setupNotificationBadges();
});

/**
 * DataTables yapılandırması
 */
function setupDataTables() {
    // DataTables'ı otomatik olarak footer.php'de başlatıyoruz
    // Özel ayarlar gerektiren tablolar için buraya kod eklenebilir
}

/**
 * Dashboard grafikleri
 */
function setupCharts() {
    // Chart.js grafikleri dashboard sayfasında tanımlanmaktadır
    // Buraya genel grafik ayarları eklenebilir
    
    // Chart.js varsayılan ayarları
    if (typeof Chart !== 'undefined') {
        Chart.defaults.font.family = "'Nunito', 'Segoe UI', sans-serif";
        Chart.defaults.font.size = 12;
        Chart.defaults.color = '#858796';
        Chart.defaults.plugins.legend.display = true;
        Chart.defaults.plugins.legend.position = 'top';
    }
}

/**
 * Sidebar toggle işlevselliği
 */
function setupSidebar() {
    const sidebarToggle = document.querySelector('.navbar-toggler');
    const sidebarMenu = document.getElementById('sidebarMenu');
    
    if (sidebarToggle && sidebarMenu) {
        sidebarToggle.addEventListener('click', function() {
            document.body.classList.toggle('sidebar-toggled');
            sidebarMenu.classList.toggle('show');
        });
    }
    
    // Küçük ekranlarda sidebar'ı gizle
    const handleWindowResize = function() {
        if (window.innerWidth < 768) {
            document.body.classList.remove('sidebar-toggled');
            sidebarMenu.classList.remove('show');
        }
    };
    
    window.addEventListener('resize', handleWindowResize);
    handleWindowResize();
}

/**
 * Silme işlemleri için onay kutuları
 */
function setupDeleteConfirmations() {
    document.querySelectorAll('.delete-btn, .delete-item').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const url = this.getAttribute('href') || this.getAttribute('data-url');
            const name = this.getAttribute('data-name') || 'Bu öğeyi';
            const type = this.getAttribute('data-type') || 'warning';
            
            Swal.fire({
                title: 'Emin misiniz?',
                text: name + " silmek istediğinize emin misiniz? Bu işlem geri alınamaz!",
                icon: type,
                showCancelButton: true,
                confirmButtonText: 'Evet, sil!',
                cancelButtonText: 'İptal',
                confirmButtonColor: '#e74a3b'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        });
    });
}

/**
 * Bildirim badgeleri
 */
function setupNotificationBadges() {
    // Bildirim kontrolü AJAX ile footer.php'de yapılmaktadır
}

/**
 * Para birimini formatla
 * @param {number} amount - Formatlanacak tutar
 * @returns {string} - Formatlanmış tutar (örn: 1.234,56 ₺)
 */
function formatMoney(amount) {
    return new Intl.NumberFormat('tr-TR', {
        style: 'currency',
        currency: 'TRY',
        minimumFractionDigits: 2
    }).format(amount);
}

/**
 * Tarihi formatla
 * @param {string} dateString - Formatlanacak tarih string'i
 * @param {string} format - Çıktı formatı (varsayılan: dd.mm.yyyy)
 * @returns {string} - Formatlanmış tarih
 */
function formatDate(dateString, format = 'dd.mm.yyyy') {
    if (!dateString) return '';
    
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;
    
    const day = date.getDate().toString().padStart(2, '0');
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const year = date.getFullYear();
    const hours = date.getHours().toString().padStart(2, '0');
    const minutes = date.getMinutes().toString().padStart(2, '0');
    
    if (format === 'dd.mm.yyyy') {
        return `${day}.${month}.${year}`;
    } else if (format === 'dd.mm.yyyy HH:MM') {
        return `${day}.${month}.${year} ${hours}:${minutes}`;
    } else if (format === 'yyyy-mm-dd') {
        return `${year}-${month}-${day}`;
    }
    
    return dateString;
}