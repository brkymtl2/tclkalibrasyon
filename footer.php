</div><!-- /.container -->

    <!-- Footer -->
    <footer class="footer mt-5 py-3 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo APP_AUTHOR; ?> - <?php echo APP_NAME; ?> v<?php echo APP_VERSION; ?></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">İletişim: <a href="mailto:<?php echo APP_EMAIL; ?>"><?php echo APP_EMAIL; ?></a></p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
    
    <!-- Okunmamış bildirimler için AJAX sorgusu -->
    <?php if (isLoggedIn()): ?>
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
    <?php endif; ?>
</body>
</html>