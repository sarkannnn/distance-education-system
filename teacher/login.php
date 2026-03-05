<?php
/**
 * Distant Təhsil - Müəllim Giriş Səhifəsi (Ssenari 2)
 * 
 * İstifadəçi TMİS hesab məlumatlarını daxil edir,
 * doğrulama TMİS API üzərindən aparılır.
 * Ayrıca register YOXDUR.
 */
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$auth = new Auth();

// Artıq daxil olubsa, dashboard-a yönləndir
if ($auth->isLoggedIn()) {
    header('Location: ./');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'İstifadəçi adı və şifrəni daxil edin';
    } else {
        $result = $auth->loginViaTmis($username, $password);

        if ($result['success']) {
            header('Location: ./');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

// URL-dən gələn xəta mesajları
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'token_expired':
            $error = 'Sessiya müddəti bitib, yenidən daxil olun.';
            break;
        case 'access_denied':
            $error = 'Bu bölməyə girişiniz yoxdur.';
            break;
        case 'user_not_found':
            $error = 'İstifadəçi tapılmadı.';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="az">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Naxçıvan Dövlət Universiteti Distant Təhsil Sistemi - Müəllim Girişi">
    <title>Müəllim Girişi - NDU Distant Təhsil</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 50%, #093E67 100%);
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        .login-page::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 30% 40%, rgba(69, 69, 246, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 70% 60%, rgba(14, 89, 149, 0.1) 0%, transparent 50%);
            animation: bgFloat 20s ease-in-out infinite;
        }

        @keyframes bgFloat {

            0%,
            100% {
                transform: translate(0, 0) rotate(0deg);
            }

            33% {
                transform: translate(30px, -30px) rotate(1deg);
            }

            66% {
                transform: translate(-20px, 20px) rotate(-1deg);
            }
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 48px 40px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 25px 60px -12px rgba(0, 0, 0, 0.35),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            position: relative;
            z-index: 1;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-logo {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: white;
            border: 2px solid #eef2f6;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            padding: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .logo-circle img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .login-logo h1 {
            font-size: 20px;
            font-weight: 700;
            color: #1a2a47;
            margin-bottom: 4px;
        }

        .login-logo p {
            font-size: 14px;
            color: #64748b;
        }

        .tmis-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #dbeafe, #ede9fe);
            color: #3730a3;
            font-size: 12px;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 20px;
            margin-top: 12px;
            letter-spacing: 0.3px;
        }

        .tmis-badge svg {
            width: 14px;
            height: 14px;
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            color: #166534;
            font-size: 12px;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 20px;
            margin-top: 8px;
            letter-spacing: 0.3px;
        }

        .role-badge svg {
            width: 14px;
            height: 14px;
        }

        .error-message {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #dc2626;
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            text-align: center;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 1px solid rgba(220, 38, 38, 0.15);
        }

        .login-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-label {
            font-size: 14px;
            font-weight: 600;
            color: #334155;
        }

        .form-input-icon {
            position: relative;
        }

        .form-input-icon svg {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            color: #94a3b8;
            pointer-events: none;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px 14px 44px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            color: #1e293b;
            background: #f8fafc;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #1e293b;
            background: white;
            box-shadow: 0 0 0 4px rgba(30, 41, 59, 0.1);
        }

        .form-input::placeholder {
            color: #94a3b8;
        }

        .btn-primary {
            width: 100%;
            padding: 14px 24px;
            background: linear-gradient(135deg, #1e293b, #334155);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 4px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px -5px rgba(30, 41, 59, 0.5);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-primary svg {
            width: 20px;
            height: 20px;
        }

        .login-footer {
            margin-top: 28px;
            text-align: center;
        }

        .login-footer p {
            font-size: 13px;
            color: #94a3b8;
            line-height: 1.6;
        }

        .login-footer .tmis-link {
            color: #0E5995;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.3s;
        }

        .login-footer .tmis-link:hover {
            color: #093E67;
            text-decoration: underline;
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 4px 0;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }

        .divider span {
            font-size: 12px;
            color: #94a3b8;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
    </style>
</head>

<body>
    <div class="login-page">
        <div class="login-card">
            <div class="login-logo">
                <div class="logo-circle">
                    <img src="assets/img/nsu_logo.png" alt="NDU Logo">
                </div>
                <h1>Distant Təhsil Sistemi</h1>
                <p>Naxçıvan Dövlət Universiteti</p>
                <div class="tmis-badge">
                    <i data-lucide="shield-check"></i>
                    TMİS hesabı ilə giriş
                </div>
                <div class="role-badge">
                    <i data-lucide="graduation-cap"></i>
                    Müəllim Paneli
                </div>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <i data-lucide="alert-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form class="login-form" method="POST" action="" id="loginForm">
                <div class="form-group">
                    <label class="form-label" for="username">TMİS İstifadəçi adı</label>
                    <div class="form-input-icon">
                        <i data-lucide="user"></i>
                        <input type="text" id="username" name="username" class="form-input"
                            placeholder="TMİS istifadəçi adınızı daxil edin"
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                            required autocomplete="username">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">TMİS Şifrə</label>
                    <div class="form-input-icon">
                        <i data-lucide="lock"></i>
                        <input type="password" id="password" name="password" class="form-input"
                            placeholder="TMİS şifrənizi daxil edin" required autocomplete="current-password">
                    </div>
                </div>

                <button type="submit" class="btn-primary" id="loginBtn">
                    <i data-lucide="log-in"></i>
                    Daxil ol
                </button>
            </form>

            <div class="login-footer">
                <div class="divider">
                    <span>Məlumat</span>
                </div>
                <p style="margin-top: 12px;">
                    Bu sistemə giriş yalnız TMİS hesabı ilə mümkündür.<br>
                    TMİS hesabınız yoxdursa,
                    <a href="https://tmis.ndu.edu.az" target="_blank" class="tmis-link">TMİS platformasına</a>
                    müraciət edin.
                </p>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // Form submit zamanı loading göstər
        document.getElementById('loginForm').addEventListener('submit', function () {
            const btn = document.getElementById('loginBtn');
            btn.disabled = true;
            btn.innerHTML = '<svg class="animate-spin" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" stroke-opacity="0.3"></circle><path d="M4 12a8 8 0 0 1 8-8"></path></svg> Yoxlanılır...';
            btn.style.opacity = '0.8';
        });
    </script>
</body>

</html>