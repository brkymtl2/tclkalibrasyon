<?php
$pageTitle = "Fiyatlandırma - " . APP_NAME;
require_once 'header.php';

$db = Database::getInstance();

// Fiyatlandırma listesini al
$pricing = $db->query("SELECT * FROM calibration_pricing ORDER BY active DESC, calibration_type ASC");

// Form gönderilmiş mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !checkCSRF($_POST['csrf_token'])) {
        $_SESSION['message'] = 'Güvenlik hatası! Lütfen sayfayı yenileyip tekrar deneyin.';
        $_SESSION['message_type'] = 'danger';
        header("Location: " . BASE_URL . "/admin/pricing.php");
        exit;
    }
    
    // İşlem türünü belirle
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_pricing') {
        // Yeni fiyatlandırma ekle
        $calibrationType = sanitize($_POST['calibration_type']);
        $price = floatval(str_replace(',', '.', $_POST['price']));
        $description = sanitize($_POST['description']);
        $active = isset($_POST['active']) ? 1 : 0;
        
        // Basit doğrulama
        $errors = [];
        
        if (empty($calibrationType)) {
            $errors[] = 'Kalibrasyon tipi gereklidir.';
        }
        
        if ($price <= 0) {
            $errors[] = 'Fiyat sıfırdan büyük olmalıdır.';
        }
        
        // Aynı tipte kayıt var mı kontrol et
        $existingCount = $db->queryValue("SELECT COUNT(*) FROM calibration_pricing WHERE calibration_type = '" . $db->escape($calibrationType) . "'");
        if ($existingCount > 0) {
            $errors[] = 'Bu kalibrasyon tipi için zaten bir fiyatlandırma kaydı mevcut!';
        }
        
        // Hata yoksa fiyatlandırma ekle
        if (empty($errors)) {
            $query = "INSERT INTO calibration_pricing (calibration_type, price, description, active, created_by) 
                      VALUES (
                         '" . $db->escape($calibrationType) . "', 
                         $price, 
                         '" . $db->escape($description) . "', 
                         $active, 
                         " . $_SESSION['user_id'] . ")";
                          
            $result = $db->query($query);
            
            if ($result) {
                // Aktiviteyi logla
                logActivity($_SESSION['user_id'], 'Fiyatlandırma Ekleme', "Yeni fiyatlandırma eklendi: $calibrationType - " . formatMoney($price));
                
                $_SESSION['message'] = 'Fiyatlandırma başarıyla eklendi!';
                $_SESSION['message_type'] = 'success';
                
                header("Location: " . BASE_URL . "/admin/pricing.php");
                exit;
            } else {
                $errors[] = 'Fiyatlandırma eklenirken bir hata oluştu: ' . $db->error();
            }
        }
    } elseif ($action === 'edit_pricing') {
        // Fiyatlandırma düzenle
        $pricingId = intval($_POST['pricing_id']);
        $price = floatval(str_replace(',', '.', $_POST['price']));
        $description = sanitize($_POST['description']);
        $active = isset($_POST['active']) ? 1 : 0;
        
        // Basit doğrulama
        $errors = [];
        
        if ($price <= 0) {
            $errors[] = 'Fiyat sıfırdan büyük olmalıdır.';
        }
        
        // Hata yoksa fiyatlandırma güncelle
        if (empty($errors)) {
            $query = "UPDATE calibration_pricing SET 
                         price = $price, 
                         description = '" . $db->escape($description) . "', 
                         active = $active,
                         last_update = NOW()
                      WHERE pricing_id = $pricingId";
                      
            $result = $db->query($query);
            
            if ($result) {
                // Aktiviteyi logla
                $pricingInfo = $db->queryOne("SELECT calibration_type FROM calibration_pricing WHERE pricing_id = $pricingId");
                logActivity($_SESSION['user_id'], 'Fiyatlandırma Güncelleme', "Fiyatlandırma güncellendi: {$pricingInfo['calibration_type']} - " . formatMoney($price));
                
                $_SESSION['message'] = 'Fiyatlandırma başarıyla güncellendi!';
                $_SESSION['message_type'] = 'success';
                
                header("Location: " . BASE_URL . "/admin/pricing.php");
                exit;
            } else {
                $errors[] = 'Fiyatlandırma güncellenirken bir hata oluştu: ' . $db->error();
            }
        }
    } elseif ($action === 'delete_pricing') {
        // Fiyatlandırma sil
        $pricingId = intval($_POST['pricing_id']);
        
        // Bu fiyatlandırma kullanılıyor mu kontrol et
        $usageCount = $db->queryValue("SELECT COUNT(*) FROM calibration_records cr 
                                      JOIN calibration_pricing cp ON cr.calibration_type = cp.calibration_type 
                                      WHERE cp.pricing_id = $pricingId");
        
        if ($usageCount > 0) {
            $_SESSION['message'] = 'Bu fiyatlandırma kaydı kullanımda olduğu için silinemez!';
            $_SESSION['message_type'] = 'danger';
        } else {
            // Fiyatlandırma kaydını sil
            $pricingInfo = $db->queryOne("SELECT calibration_type, price FROM calibration_pricing WHERE pricing_id = $pricingId");
            
            $result = $db->query("DELETE FROM calibration_pricing WHERE pricing_id = $pricingId");
            
            if ($result) {
                // Aktiviteyi logla
                logActivity($_SESSION['user_id'], 'Fiyatlandırma Silme', "Fiyatlandırma silindi: {$pricingInfo['calibration_type']} - " . formatMoney($pricingInfo['price']));
                
                $_SESSION['message'] = 'Fiyatlandırma başarıyla silindi!';
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Fiyatlandırma silinirken bir hata oluştu: ' . $db->error();
                $_SESSION['message_type'] = 'danger';
            }
        }
        
        header("Location: " . BASE_URL . "/admin/pricing.php");
        exit;
    }
}

// Fiyatlandırma listesini yeniden al
$pricing = $db->query("SELECT * FROM calibration_pricing ORDER BY active DESC, calibration_type ASC");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Fiyatlandırma Yönetimi</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPricingModal">
        <i class="fas fa-plus-circle me-2"></i> Yeni Fiyatlandırma Ekle
    </button>
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

<!-- Fiyatlandırma Tablosu -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Fiyatlandırma Listesi</h6>
    </div>
    <div class="card-body">
        <?php if (empty($pricing)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> Henüz fiyatlandırma kaydı bulunmamaktadır.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered datatable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kalibrasyon Tipi</th>
                            <th>Fiyat</th>
                            <th>Açıklama</th>
                            <th>Durum</th>
                            <th>Son Güncelleme</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pricing as $price): ?>
                            <tr<?php echo $price['active'] ? '' : ' class="table-secondary"'; ?>>
                                <td><?php echo $price['pricing_id']; ?></td>
                                <td><?php echo htmlspecialchars($price['calibration_type']); ?></td>
                                <td><?php echo formatMoney($price['price']); ?></td>
                                <td><?php echo htmlspecialchars($price['description'] ?? ''); ?></td>
                                <td>
                                    <?php if ($price['active']): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $price['last_update'] ? formatDate($price['last_update'], 'd.m.Y H:i') : formatDate($price['creation_date'], 'd.m.Y H:i'); ?>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-primary edit-price-btn" 
                                            data-id="<?php echo $price['pricing_id']; ?>"
                                            data-type="<?php echo htmlspecialchars($price['calibration_type']); ?>"
                                            data-price="<?php echo $price['price']; ?>"
                                            data-description="<?php echo htmlspecialchars($price['description'] ?? ''); ?>"
                                            data-active="<?php echo $price['active']; ?>"
                                            title="Düzenle">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <button type="button" class="btn btn-sm btn-danger delete-price-btn" 
                                            data-id="<?php echo $price['pricing_id']; ?>"
                                            data-type="<?php echo htmlspecialchars($price['calibration_type']); ?>"
                                            title="Sil">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Yeni Fiyatlandırma Ekleme Modal -->
<div class="modal fade" id="addPricingModal" tabindex="-1" aria-labelledby="addPricingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="add_pricing">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="addPricingModalLabel">Yeni Fiyatlandırma Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="calibration_type" class="form-label">Kalibrasyon Tipi <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="calibration_type" name="calibration_type" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="price" class="form-label">Fiyat <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="price" name="price" required>
                            <span class="input-group-text">₺</span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Açıklama</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="active" name="active" checked>
                        <label class="form-check-label" for="active">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Fiyatlandırma Düzenleme Modal -->
<div class="modal fade" id="editPricingModal" tabindex="-1" aria-labelledby="editPricingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="edit_pricing">
                <input type="hidden" name="pricing_id" id="edit_pricing_id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="editPricingModalLabel">Fiyatlandırma Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_calibration_type" class="form-label">Kalibrasyon Tipi</label>
                        <input type="text" class="form-control" id="edit_calibration_type" disabled readonly>
                        <div class="form-text">Kalibrasyon tipi değiştirilemez.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_price" class="form-label">Fiyat <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="edit_price" name="price" required>
                            <span class="input-group-text">₺</span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Açıklama</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_active" name="active">
                        <label class="form-check-label" for="edit_active">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Güncelle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Fiyatlandırma Silme Modal -->
<div class="modal fade" id="deletePricingModal" tabindex="-1" aria-labelledby="deletePricingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="delete_pricing">
                <input type="hidden" name="pricing_id" id="delete_pricing_id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="deletePricingModalLabel">Fiyatlandırma Sil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Bu fiyatlandırma kaydını silmek istediğinize emin misiniz?</p>
                    <p><strong>Kalibrasyon Tipi:</strong> <span id="delete_calibration_type"></span></p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i> Bu işlem geri alınamaz!
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-danger">Sil</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sayısal alanlarda sadece rakam ve virgül girişine izin ver
        const priceInputs = document.querySelectorAll('#price, #edit_price');
        priceInputs.forEach(input => {
            input.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9.,]/g, '');
            });
        });
        
        // Fiyatlandırma düzenleme modal
        const editButtons = document.querySelectorAll('.edit-price-btn');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const type = this.getAttribute('data-type');
                const price = this.getAttribute('data-price');
                const description = this.getAttribute('data-description');
                const active = this.getAttribute('data-active') === '1';
                
                document.getElementById('edit_pricing_id').value = id;
                document.getElementById('edit_calibration_type').value = type;
                document.getElementById('edit_price').value = price;
                document.getElementById('edit_description').value = description;
                document.getElementById('edit_active').checked = active;
                
                const editModal = new bootstrap.Modal(document.getElementById('editPricingModal'));
                editModal.show();
            });
        });
        
        // Fiyatlandırma silme modal
        const deleteButtons = document.querySelectorAll('.delete-price-btn');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const type = this.getAttribute('data-type');
                
                document.getElementById('delete_pricing_id').value = id;
                document.getElementById('delete_calibration_type').textContent = type;
                
                const deleteModal = new bootstrap.Modal(document.getElementById('deletePricingModal'));
                deleteModal.show();
            });
        });
    });
</script>

<?php require_once 'footer.php'; ?>