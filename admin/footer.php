</main>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer mt-5 py-3 bg-dark text-white">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo APP_AUTHOR; ?> - <?php echo APP_NAME; ?> v<?php echo APP_VERSION; ?></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">Admin Paneli</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo BASE_URL; ?>/assets/js/admin.js"></script>
    
    <!-- DataTables genel ayarları -->
    <script>
        $(document).ready(function() {
            $('.datatable').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/tr.json'
                },
                pageLength: 25,
                responsive: true
            });
        });
    </script>
    
    <!-- Okunmamış bildirimler için AJAX sorgusu -->
    <script>
        // 60 saniyede bir okunmamış bildirimleri kontrol et
        setInterval(function() {
            $.get('<?php echo BASE_URL; ?>/ajax/check_notifications.php', function(data) {
                if (data.count > 0) {
                    $('#notificationsDropdown .badge').text(data.count).show();
                } else {
                    $('#notificationsDropdown .badge').hide();
                }
            }, 'json');
        }, 60000);
    </script>
</body>
</html>