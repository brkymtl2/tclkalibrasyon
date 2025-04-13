/**
 * TCL Türkel Kalibrasyon Erişim Sistemi
 * Ana JavaScript dosyası
 */

document.addEventListener('DOMContentLoaded', function() {
    // Bootstrap bileşenlerini etkinleştir
    enableBootstrapComponents();
    
    // Silme işlemleri için onay
    setupDeleteConfirmations();
    
    // Form doğrulama
    setupFormValidation();
    
    // Tarih seçicileri
    setupDatePickers();
    
    // Dosya yükleme isim gösterimi
    setupFileInputs();
    
    // Bildirim badgeleri
    setupNotificationBadges();
    
    // Yazdırma işlevi
    setupPrintButtons();
});

/**
 * Bootstrap bileşenlerini etkinleştir
 */
function enableBootstrapComponents() {
    // Tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

/**
 * Silme işlemleri için onay kutuları
 */
function setupDeleteConfirmations() {
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const url = this.getAttribute('href');
            const name = this.getAttribute('data-name') || 'Bu öğeyi';
            
            Swal.fire({
                title: 'Emin misiniz?',
                text: name + " silmek istediğinize emin misiniz? Bu işlem geri alınamaz!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Evet, sil!',
                cancelButtonText: 'İptal',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        });
    });
}

/**
 * Form doğrulama
 */
function setupFormValidation() {
    // HTML5 doğrulamasını kullan
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
}

/**
 * Tarih seçicileri
 */
function setupDatePickers() {
    // Bu örnekte tarih seçici için HTML'in native date input'unu kullanıyoruz
    // Daha gelişmiş tarih seçiciler için flatpickr veya bootstrap-datepicker gibi kütüphaneler kullanılabilir
}

/**
 * Dosya yükleme isim gösterimi
 */
function setupFileInputs() {
    document.querySelectorAll('.custom-file-input').forEach(input => {
        input.addEventListener('change', function(e) {
            var fileName = this.files[0].name;
            var label = this.nextElementSibling;
            label.textContent = fileName;
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
 * Yazdırma butonları
 */
function setupPrintButtons() {
    document.querySelectorAll('.print-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            window.print();
        });
    });
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