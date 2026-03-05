# TMİS İnteqrasiya Yol Xəritəsi — Müəllim Paneli (Teacher Panel)

Bu sənəd Distant Təhsil sisteminin **müəllim panelinin** TMİS (Tədris Məlumat İdarəetmə Sistemi) ilə tam inteqrasiyası üçün **addım-addım** təlimatdır. Müəllim məlumatları, fənlər, tələbə siyahıları və dərs qrafiki TMİS serverindən çəkilir; canlı dərs fəaliyyətləri və arxiv materialları isə TMİS-ə sinxronizasiya olunur.

---

## 🔐 Addım 0: Autentifikasiya və Giriş

Müəllim sistemə TMİS (FIN kod və şifrə) vasitəsilə daxil olur. Bearer Token bütün sorğularda istifadə olunur.

*   **Base URL:** `https://tmis.ndu.edu.az/api`
*   **Endpoint (Login):** `POST /api/login`
*   **Header:** `Authorization: Bearer {token}`

### 🔧 Nə etməli:
1.  `teacher/includes/auth.php` faylında TMİS-dən gələn JWT token-i session-da saxlayın.
2.  `TmisApi::getToken()` metodu vasitəsilə sessiyadakı tokeni əldə edin.
3.  Token vaxtı bitdikdə (`401 Unauthorized`), müəllimi login səhifəsinə yönləndirin.

---

## 🌍 Addım 1: Müəllim Profili (Topnav & Sidebar)

Müəllim haqqında məlumatlar hər səhifədə göstərilməlidir.

**Fayl:** `teacher/includes/sidebar.php`, `teacher/includes/topnav.php`

### Çəkiləcək APİ:
| # | Endpoint | Metod | Açıqlama |
|---|----------|-------|----------|
| 1 | `/api/me` | GET | Müəllimin adı, soyadı, vəzifəsi, kafedrası |

### Gözlənilən Cavab:
```json
{
  "success": true,
  "data": {
    "id": 987,
    "first_name": "Kamil",
    "last_name": "Əliyev",
    "title": "Professor",
    "department": "Kompüter Elmləri",
    "avatar_url": "https://tmis.ndu.edu.az/storage/avatars/987.jpg"
  }
}
```

---

## 📊 Addım 2: Müəllim Dashboard (İdarəetmə Paneli)

**Fayl:** `teacher/index.php`

### A. Statistika Kartları
| # | Endpoint | Metod | Açıqlama |
|---|----------|-------|----------|
| 1 | `/api/teacher/dashboard-stats` | GET | Müəllim üçün bütün statistikalar |

### Xəritə (Hazırkı → TMİS):
| Hazırkı dəyişən | TMİS sahəsi | UI-da göstərildiyi yer |
|---|---|---|
| `$stats['totalCourses']` | `total_subjects` | "Fənn" kartı |
| `$stats['totalStudents']` | `total_students` | "Tələbə" kartı |
| `$stats['liveClassesThisMonth']` | `total_live_lessons` | "Canlı Dərslər" kartı |
| `$stats['totalHours']` | `total_teaching_hours` | "Tədris Saatı" kartı |

### B. Bu Günün Dərs Cədvəli
| # | Endpoint | Metod | Açıqlama |
|---|----------|-------|----------|
| 1 | `/api/student/schedule/today` | GET | Müəllimin bu gün keçəcəyi dərslər |

*(Qeyd: Müəllim və tələbə cədvəli eyni mərkəzi endpointdən çəkilə bilər).*

---

## 🎥 Addım 3: Canlı Dərslərin İdarə Edilməsi

**Fayl:** `teacher/live-lessons.php`

### A. Aktiv Sessiyanın Yoxlanılması
| # | Endpoint | Metod | Açıqlama |
|---|----------|-------|----------|
| 1 | `/api/teacher/live-session/status` | GET | Hazırda hər hansı sessiyanın aktiv olub-olmaması |

### B. Canlı Dərsə Başlama (GÖNDƏRİLİR)
Müəllim dərsi başlatdıqda TMİS-ə sessiya ID-si göndərilməlidir.
| # | Endpoint | Metod | Açıqlama |
|---|----------|-------|----------|
| 1 | `/api/teacher/live-sessions/start` | POST | Yeni canlı dərs sessiyasını başlatmaq |

---

## 📈 Addım 4: Analitika və Hesabatlar

**Fayl:** `teacher/analytics.php`

### A. Fənn üzrə Statistika
| # | Endpoint | Metod | Açıqlama |
|---|----------|-------|----------|
| 1 | `/api/teacher/subjects` | GET | Müəllimin tədris etdiyi fənlərin siyahısı |
| 2 | `/api/teacher/subject/{id}/details` | GET | Fənn üzrə tələbə sayı və ümumi davamiyyət |

### B. Arxiv və Baxışlar
| # | Endpoint | Metod | Açıqlama |
|---|----------|-------|----------|
| 1 | `/api/teacher/archive` | GET | Arxivlərin və video baxışlarının siyahısı |

---

## 📜 Addım 5: Davamiyyət Hesabatı (Attendance)

**Fayl:** `teacher/attendance_report.php`

Müəllim dərslər üzrə tələbələrin iştirakına baxır.
| # | Endpoint | Metod | Açıqlama |
|---|----------|-------|----------|
| 1 | `/api/teacher/attendance/{lesson_id}` | GET | Müəyyən dərs üzrə tələbə iştirakı siyahısı |

---

## 🚀 Addım 6: İcra Təlimatı

### Addım 6.1: APİ Client-in Genişləndirilməsi
```php
Fayl: teacher/includes/tmis_api.php
```
Müəllim panelində `TmisApi` sinfindən istifadə edərək aşağıdakı metodları tətbiq edin:
- `loginTeacher($username, $password)`
- `getDashboardStats($token)`
- `getScheduleToday($token)`
- `getSubjectsList($token)`
- `getSubjectDetails($token, $courseId)`
- `getArchive($token)`

### Addım 6.2: Sinxronizasiya (Sinyallar)
- **Dərs Başlayanda:** `live-studio.php` səhifəsində sessiya yaradılanda TMİS-ə sinyal göndərilir.
- **Tələbə Girişi:** Tələbə qoşulan kimi `live_attendance` cədvəli həm lokalda, həm də API vasitəsilə TMİS-də yenilənir.

---

## 📋 Yekun Cədvəl: Müəllim APİ Endpointləri

| # | Endpoint | Metod | Funksiya |
|---|----------|-------|----------|
| 1 | `/api/login` | POST | Giriş (Teacher Auth) |
| 2 | `/api/me` | GET | Müəllim profili |
| 3 | `/api/teacher/dashboard-stats` | GET | Dashboard məlumatları |
| 4 | `/api/student/schedule/today` | GET | Günlük cədvəl |
| 5 | `/api/teacher/subjects` | GET | Fənn siyahısı |
| 6 | `/api/teacher/subject/{id}` | GET | Fənn detalları |
| 7 | `/api/teacher/live-sessions/start` | POST | Canlı dərsi qeydə al |
| 8 | `/api/teacher/attendance/{id}` | GET | İştirak siyahısı |

**Nəticə:** Müəllim paneli bu sənədə uyğun inteqrasiya edildikdə, bütün pedaqoji fəaliyyət real vaxt rejimində TMİS ilə sinxronlaşacaqdır.
