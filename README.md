# 🎓 NDU Distant Təhsil Sistemi (v2.0)

Naxçıvan Dövlət Universiteti üçün hazırlanmış müasir, dinamik və inteqrasiya olunmuş distant təhsil platforması. Bu sistem həm müəllimlər, həm də tələbələr üçün dərslərin idarə olunması, arxivləşdirilməsi və canlı yayımı üçün nəzərdə tutulub.

---

## 🚀 Əsas Funksiyalar

### 👨‍🎓 Tələbə Paneli
- **Dashboard:** Şəxsi dərs qrafiki, həftəlik və aylıq canlı dərs sayları.
- **Canlı Dərslər:** Hal-hazırda davam edən dərslərə birbaşa qoşulma.
- **Arxiv:** Keçirilmiş dərslərin video yazılarına baxmaq və materialları (PDF) yükləmək.
- **Profil:** Fərdi məlumatların və akademik statusun idarə olunması.

### 👨‍🏫 Müəllim Paneli
- **Dərs İdarəetməsi:** Yeni canlı dərslərin yaradılması və idarə olunması.
- **Analitika:** Dərslərə baxış sayları, tələbə iştirakı və performans hesabatları.
- **Arxivləşdirmə:** Keçmiş dərslərin avtomatik və ya manual arxivə əlavə edilməsi.
- **Jurnal:** Canlı dərslərdə iştirak edən tələbələrin davamiyyətinin izlənilməsi.

### 📅 Publik Cədvəl
- Giriş etmədən (qonaq istifadəçi kimi) gündəlik dərs cədvəlinə baxış və arxiv materiallarına əlçatanlıq.

---

## � TMİS İnteqrasiyası

Sistem **Təhsil İdarəetmə İnformasiya Sistemi (TMİS)** ilə tam inteqrasiyalı işləyir:
- **Tək Giriş (SSO):** Tələbələr və müəllimlər öz TMİS (FIN kod) hesabları ilə sistemə daxil olurlar.
- **Data Sync:** Fənn adları, ixtisaslar və tələbə siyahıları avtomatik olaraq TMİS-dən götürülür.
- **Təhlükəsizlik:** Məlumat mübadiləsi üçün **HMAC SHA256** şifrələmə protokolundan istifadə olunur.

---

## 🛠 Texnoloji Stek

- **Backend:** PHP (Native / MVC pattern)
- **Frontend:** Vanilla CSS, JavaScript, Lucide Icons
- **Database:** MariaDB / MySQL
- **Integration:** REST API & HMAC Auth

---

## 📁 Qovluq Strukturu

- `/api` - Xarici inteqrasiyalar üçün API sonluqları.
- `/database` - Verilənlər bazası sxemləri (SQL).
- `/student` - Tələbə paneli kodları və interfeysi.
- `/teacher` - Müəllim paneli və analitika modulu.
- `/uploads` - Dərs materialları, PDF-lər və qeydlər üçün depo.
- `public-schedule.php` - Publik cədvəl səhifəsi.

---

## 🛠 Quraşdırma (Local Development - Laragon)

1. Layihəni `C:\laragon\www\distant-tehsil` qovluğuna yerləşdirin.
2. `student/config/database.php` faylında verilənlər bazası ayarlarını edin.
3. `database/schema.sql` faylını bazaya import edin.
4. virtual host vasitəsilə `distant-tehsil.test` ünvanına daxil olun.

---

## 📋 Qeydlər və Təhlükəsizlik
- Bütün test və müvəqqəti fayllar (`tmp/`) təmizlənmişdir.
- API girişləri üçün `DISTANT_BRIDGE_2024_NDU` gizli açarı (Secret Key) istifadə olunur.

---
© 2024-2026 NDU Distant Təhsil Portalı
