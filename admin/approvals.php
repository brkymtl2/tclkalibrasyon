<?php
$pageTitle = "Kullanıcı Onayları - " . APP_NAME;
require_once 'header.php';

$db = Database::getInstance();

// Onay bekleyen kullanıcıları listele
$pendingUsers = $db->query("SELECT 
                            user_id, 
                            username, 
                            email, 
                            company_name, 
                            address,
                            phone,
                            registration_date 
                        FROM 
                            calibration_users 
                        WHERE 
                            status = 'pending'
                        ORDER BY 
                            registration_date DESC");
?>

<h1 class="h3 mb-4">Onay Bekleyen Kullanıcılar</h1>

<?php if (empty($pendingUsers)): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i> Onay bekleyen kullanıcı bulunmamaktadır.
    </div>
<?php else: ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Onay Bekleyen Kullanıcılar Listesi</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered datatable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kullanıcı Adı</th>
                            <th>E-posta</th>
                            <th>Şirket</th>
                            <th>Telefon</th>
                            <th>Kayıt Tarihi</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingUsers as $user): ?>
                            <tr>
                                <td><?php echo $user['user_id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['company_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['phone'] ?? 'Belirtilmemiş'); ?></td>
                                <td><?php echo formatDate($user['registration_date'], 'd.m.Y H:i'); ?></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-info btn-sm view-details" 
                                            data-id="<?php echo $user['user_id']; ?>"
                                            data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                            data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                            data-company="<?php echo htmlspecialchars($user['company_name']); ?>"
                                            data-address="<?php echo htmlspecialchars($user['address'] ?? ''); ?>"
                                            data-phone="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                            data-date="<?php echo formatDate($user['registration_date'], 'd.m.Y H:i'); ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    
                                    <button type="button" class="btn btn-success btn-sm approve-user" 
                                            data-id="<?php echo $user['user_id']; ?>" 
                                            data-username="<?php echo htmlspecialchars($user['username']); ?>">
                                        <i class="fas fa-check"></i> Onayla
                                    </button>
                                    
                                    <button type="button" class="btn btn-danger btn-sm reject-user" 
                                            data-id="<?php echo $user['user_id']; ?>" 
                                            data-username="<?php echo htmlspecialchars($user['username']); ?>">
                                        <i class="fas fa-times"></i> Reddet
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Kullanıcı Detayları Modal -->
<div class="modal fade" id="userDetailsModal" tabindex="-1" aria-labelledby="userDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userDetailsModalLabel">Kullanıcı Detayları</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Kullanıcı Adı:</strong> <span id="modal-username"></span></p>
                        <p><strong>E-posta:</strong> <span id="modal-email"></span></p>
                        <p><strong>Şirket:</strong> <span id="modal-company"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Telefon:</strong> <span id="modal-phone"></span></p>
                        <p><strong>Kayıt Tarihi:</strong> <span id="modal-date"></span></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <p><strong>Adres:</strong></p>
                        <p id="modal-address" class="border p-2 bg-light"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                <button type="button" class="btn btn-success" id="modal-approve">Onayla</button>
                <button type="button" class="btn btn-danger" id="modal-reject">Reddet</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Detayları görüntüleme
        var viewButtons = document.querySelectorAll('.view-details');
        viewButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                var userId = this.getAttribute('data-id');
                var username = this.getAttribute('data-username');
                var email = this.getAttribute('data-email');
                var company = this.getAttribute('data-company');
                var address = this.getAttribute('data-address');
                var phone = this.getAttribute('data-phone');
                var date = this.getAttribute('data-date');
                
                document.getElementById('modal-username').textContent = username;
                document.getElementById('modal-email').textContent = email;
                document.getElementById('modal-company').textContent = company;
                document.getElementById('modal-address').textContent = address || 'Belirtilmemiş';
                document.getElementById('modal-phone').textContent = phone || 'Belirtilmemiş';
                document.getElementById('modal-date').textContent = date;
                
                document.getElementById('modal-approve').setAttribute('data-id', userId);
                document.getElementById('modal-reject').setAttribute('data-id', userId);
                
                var userDetailsModal = new bootstrap.Modal(document.getElementById('userDetailsModal'));
                userDetailsModal.show();
            });
        });
        
        // Kullanıcı onaylama
        var approveButtons = document.querySelectorAll('.approve-user, #modal-approve');
        approveButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                var userId = this.getAttribute('data-id');
                var username = this.getAttribute('data-username');
                
                Swal.fire({
                    title: 'Kullanıcıyı Onayla',
                    text: username + ' kullanıcısını onaylamak istediğinize emin misiniz?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Evet, Onayla',
                    cancelButtonText: 'İptal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '<?php echo BASE_URL; ?>/admin/process_approval.php?action=approve&id=' + userId;
                    }
                });
            });
        });
        
        // Kullanıcı reddetme
        var rejectButtons = document.querySelectorAll('.reject-user, #modal-reject');
        rejectButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                var userId = this.getAttribute('data-id');
                var username = this.getAttribute('data-username');
                
                Swal.fire({
                    title: 'Kullanıcıyı Reddet',
                    text: username + ' kullanıcısını reddetmek istediğinize emin misiniz?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Evet, Reddet',
                    cancelButtonText: 'İptal',
                    confirmButtonColor: '#d33'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '<?php echo BASE_URL; ?>/admin/process_approval.php?action=reject&id=' + userId;
                    }
                });
            });
        });
    });
</script>

<?php require_once 'footer.php'; ?>