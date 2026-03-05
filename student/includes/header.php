<?php
/**
 * Header Template
 */
require_once __DIR__ . '/auth.php';
$auth = new Auth();
$currentUser = $auth->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="az">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Naxçıvan Dövlət Universiteti Tələbə Distant Təhsil Sistemi">
    <title>
        <?php echo isset($pageTitle) ? e($pageTitle) . ' - ' : ''; ?>NSU Distant Təhsil
    </title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Theme Initialization -->
    <script>
        (function () {
            const savedTheme = localStorage.getItem('theme') || 'light';
            if (savedTheme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>

    <!-- Global Auth Interceptor: 401 → redirect to login -->
    <script>
        (function () {
            const _origFetch = window.fetch;
            let _redirecting = false;
            window.fetch = function () {
                return _origFetch.apply(this, arguments).then(function (response) {
                    if (response.status === 401 && !_redirecting) {
                        _redirecting = true;
                        // Show brief notification
                        var toast = document.createElement('div');
                        toast.textContent = 'Sessiya müddəti bitib. Giriş səhifəsinə yönləndirilirsiniz...';
                        toast.style.cssText = 'position:fixed;top:20px;left:50%;transform:translateX(-50%);background:#ef4444;color:#fff;padding:14px 28px;border-radius:12px;font-size:14px;font-weight:600;z-index:99999;box-shadow:0 8px 30px rgba(0,0,0,0.3);font-family:Inter,sans-serif;';
                        document.body.appendChild(toast);
                        setTimeout(function () {
                            window.location.href = 'login.php?expired=1';
                        }, 1500);
                    }
                    return response;
                });
            };
        })();
    </script>


    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/styles.css?v=<?php echo time(); ?>">
</head>

<body>
    <div class="app-container">