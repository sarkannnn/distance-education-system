# Distant Təhsil Sistemi

Azərbaycan dilində onlayn təhsil platforması - PHP backend ilə tam funksional distant təhsil sistemi.

## 📁 Layihə Strukturu

```
distant_tehsil/
├── api/                    # API endpoint-ləri
│   ├── assignments.php     # Tapşırıqlar API
│   ├── courses.php         # Kurslar API
│   ├── live-classes.php    # Canlı dərslər API
│   ├── notifications.php   # Bildirişlər API
│   ├── search.php          # Axtarış API
│   ├── settings.php        # Parametrlər API
│   └── statistics.php      # Statistika API
├── assets/
│   ├── css/
│   │   └── styles.css      # Əsas CSS stilləri
│   └── js/
│       └── main.js         # JavaScript funksiyalar
├── config/
│   └── database.php        # Verilənlər bazası konfiqurasiyası
├── database/
│   └── schema.sql          # SQL cədvəllər və nümunə verilənlər
├── includes/
│   ├── auth.php            # Autentifikasiya sinifi
│   ├── footer.php          # Footer template
│   ├── header.php          # Header template
│   ├── helpers.php         # Köməkçi funksiyalar
│   ├── sidebar.php         # Sidebar template
│   └── topnav.php          # Top navigation template
├── archive.php             # Arxiv və Resurslar səhifəsi
├── assignments.php         # Tapşırıq və Quizlər səhifəsi
├── index.php               # Dashboard (İdarəetmə Paneli)
├── lessons.php             # Dərslərim səhifəsi
├── live-classes.php        # Canlı Dərslər səhifəsi
├── login.php               # Giriş səhifəsi
├── logout.php              # Çıxış
├── settings.php            # Parametrlər səhifəsi
├── statistics.php          # Statistika səhifəsi
└── README.md               # Bu fayl
```

## 🚀 Quraşdırma

### 1. Verilənlər Bazasını Yaradın

MySQL-də verilənlər bazasını yaratmaq üçün `database/schema.sql` faylını import edin:

```bash
mysql -u root -p < database/schema.sql
```

Və ya phpMyAdmin vasitəsilə import edin.

### 2. Konfiqurasiyanı Yeniləyin

`config/database.php` faylında verilənlər bazası məlumatlarını öz mühitinizə uyğun şəkildə yeniləyin:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'distant_tehsil');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 3. Veb Serverini İşə Salın

Laragon istifadə edirsinizsə, avtomatik işləyəcək. Apache/Nginx konfiqurasiyasında document root-u `distant_tehsil` qovluğuna yönləndirin.

### 4. Sistemi Açın

Brauzerdə açın:
```
http://localhost/distant_tehsil/
```

## 🔐 Demo Giriş Məlumatları

```
Email: farida.ahmadova@example.com
Şifrə: password
```

## 🎨 Xüsusiyyətlər

### Tələbə Paneli
- **Dashboard** - Ümumi baxış, bu günün cədvəli, həftəlik review
- **Dərslərim** - Qeydiyyatdan keçilmiş kurslar, irəliləyiş
- **Canlı Dərslər** - Zoom, WebRTC, Teams dəstəyi ilə canlı yayımlar
- **Tapşırıqlar** - Quiz və tapşırıq idarəetməsi
- **Arxiv** - Keçmiş dərslərin video yazıları
- **Statistika** - Performans qrafikləri (Chart.js)

### Backend
- PDO ilə MySQL verilənlər bazası
- RESTful API endpoint-ləri
- Sessiya əsaslı autentifikasiya
- CSRF qoruması
- Şifrə hash-ləmə (bcrypt)

### Dizayn
- Modern, responsive dizayn
- Dark mode dəstəyi
- Lucide icons
- Chart.js qrafiklər
- Google Fonts (Inter)

## 📊 Verilənlər Bazası Cədvəlləri

- `users` - İstifadəçilər
- `instructors` - Müəllimlər
- `categories` - Kateqoriyalar
- `courses` - Kurslar
- `lessons` - Dərslər
- `enrollments` - Kurs qeydiyyatları
- `lesson_completions` - Dərs tamamlanmaları
- `live_classes` - Canlı dərslər
- `live_class_participants` - Canlı dərs iştirakçıları
- `assignments` - Tapşırıqlar
- `assignment_submissions` - Tapşırıq təqdimatları
- `archived_lessons` - Arxiv dərsləri
- `schedule` - Cədvəl
- `user_statistics` - İstifadəçi statistikaları
- `weekly_performance` - Həftəlik performans
- `weekly_activity` - Həftəlik fəaliyyət
- `notifications` - Bildirişlər
- `user_sessions` - Aktiv sessiyalar

## 🔧 API Endpoint-ləri

| Endpoint | Method | Təsvir |
|----------|--------|--------|
| `/api/courses.php` | GET | Kursları al |
| `/api/courses.php` | POST | Kursa qeydiyyat |
| `/api/live-classes.php` | GET | Canlı dərsləri al |
| `/api/live-classes.php` | POST | Dərsə qoşul |
| `/api/assignments.php` | GET | Tapşırıqları al |
| `/api/assignments.php` | POST | Tapşırıq təqdim et |
| `/api/notifications.php` | GET | Bildirişləri al |
| `/api/notifications.php` | POST | Oxunmuş işarələ |
| `/api/statistics.php` | GET | Statistika al |
| `/api/settings.php` | GET | Parametrləri al |
| `/api/settings.php` | POST | Parametri yenilə |
| `/api/search.php` | GET | Axtarış |

## 📝 Lisenziya

Bu layihə təhsil məqsədi ilə yaradılmışdır.

## 👨‍💻 Müəllif

Distant Təhsil Sistemi - 2026
