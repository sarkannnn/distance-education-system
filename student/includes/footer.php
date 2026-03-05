</div><!-- End app-container -->

<!-- Custom JavaScript -->
<script src="assets/js/main.js?v=<?php echo time(); ?>"></script>

<!-- Lucide Icons Initialize -->
<script>
    // Lucide ikonlarını yarat (main.js-də də ola bilər amma burada qalması zərərli deyil)
    lucide.createIcons();

    // Əgər main.js geciksə və ya DOM artıq hazır olsa, funksiyanı yoxlayaq
    if (typeof initMobileSidebar === 'function') {
        initMobileSidebar();
    }
</script>
</body>

</html>