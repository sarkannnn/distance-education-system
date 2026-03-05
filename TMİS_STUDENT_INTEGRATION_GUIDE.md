# TMİS İnteqrasiya Yol Xəritəsi — Tələbə Paneli (Student Panel)

Bu sənəd Distant Təhsil sisteminin **tələbə panelinin** TMİS (Tədris Məlumat İdarəetmə Sistemi) ilə tam inteqrasiyası üçün **addım-addım** təlimatdır. Bütün məlumatlar TMİS serverindən çəkiləcək, bütün tələbə hərəkətləri (dərsə qoşulma, arxivə baxış, davamiyyət) TMİS-ə ötürüləcəkdir.

---

## 🔐 Addım 0: Autentifikasiya və Giriş

Tələbə sisteme TMİS vasitəsilə daxil olur. Token bütün sorğularda istifadə olunur.

*   **Base URL:** `https://tmis.ndu.edu.az/api`
*   **Header:** `Authorization: Bearer {token}`
*   **Token mənbəyi:** Tələbə TMİS-dən login olduqda JWT token alır → bu token Distant Təhsil-ə ötürülür.
*   **Xəta formatı:**
    ```json
    {"success": false, "message": "Xəta mətni"}
    ```

### 🔧 Nə etməli:
1.  `student/includes/auth.php` faylında TMİS-dən gələn JWT token-i session-da saxlayın.
2.  Bütün APİ sorğularında bu token-i `Authorization` header-ə əlavə edin.
3.  Token vaxtı bitdikdə (`401 Unauthorized`) tələbəni login səhifəsinə yönləndirin.

---

## 🌍 Addım 1: Qlobal Məlumatlar (Hər Səhifədə Lazım)

Sidebar və topnav hissəsində tələbə haqqında məlumat göstərilməlidir.

**Fayl:** `student/includes/sidebar.php`, `student/includes/topnav.php`

### Çəkiləcək APİ:

| # | Endpoint | Metod | Açıqlama |
|---|----------|-------|----------|
| 1 | `/api/me` | GET | Tələbənin adı, soyadı, profil şəkli, ixtisas adı |

### Gözlənilən Cavab:
```json
{
  "success": true,
  "data": {
    "id": 1234,
    "first_name": "Əli",
    "last_name": "Hüseynov",
    "email": "ali.huseynov@ndu.edu.az",
    "avatar_url": "https://tmis.ndu.edu.az/storage/avatars/1234.jpg",
    "role": "student",
    "faculty": "Riyaziyyat və İnformatika",
    "department": "Kompüter Elmləri",
    "specialty": "Kompüter Mühəndisliyi",
    "course_year": 3,
    "group": "KM-301"
  }
}
```

### 🔧 Nə etməli:
1.  `sidebar.php`-də tələbənin adı, soyadı, ixtisas adını göstər.
2.  `topnav.php`-də profil şəklini və adı göstər.
3.  Bu məlumatı session-a keshləyin ki, hər səhifə yükləndikdə sorğu göndərməsin.

---

## 📊 Addım 2: İdarəetmə Paneli (Dashboard)

**Fayl:** `student/index.php`

Bu səhifədə 4 statistika kartı, bu günün cədvəli, son arxiv materialları göstərilir. Hal-hazırda bütün məlumatlar lokal `courses` və `live_classes` cədvəlindən çəkilir. TMİS inteqrasiyasından sonra bunlar TMİS APİ-dən gələcək.

### A. Statistika Kartları (4 ədəd)

| # | Endpoint | Metod | Açıqlama |
|---|----------|-------|----------|
| 1 | `/api/student/dashboard-stats` | GET | Dashboard üçün bütün statistikalar |

### Gözlənilən Cavab:
```json
{
  "success": true,
  "data": {
    "total_courses": 6,
    "live_this_week": 3,
    "live_this_month": 12,
    "total_archives": 28
  }
}
```

### Xəritə (Hazırkı → TMİS):
| Hazırkı dəyişən | TMİS sahəsi | UI-da göstərildiyi yer |
|---|---|---|
| `$stats['onlineTotal']` | `data.total_courses` | "Distant Tədris Olunan Fənlər" kartı |
| `$stats['liveThisWeek']` | `data.live_this_week` | "Bu Həftə Canlı Dərslər" kartı |
| `$stats['liveThisMonth']` | `data.live_this_month` | "Bu Ay Canlı Dərslər" kartı |
| `$stats['totalArchives']` | `data.total_archives` | "Arxiv Materialları" kartı |

### 🔧 Nə etməli:
1.  `index.php`-nin əvvəlindəki SQL sorğularını (`$db->fetch(...)`) silin.
2.  Əvəzinə `tmis_api_get('/api/student/dashboard-stats')` funksiyası çağırın.
3.  Gələn JSON-dan `$stats` array-ini doldurun.

---

### B. Bu Günün Cədvəli

| # | Endpoint | Metod | Açıqlama |
|---|----------|-------|----------|
| 1 | `/api/student/schedule/today` | GET | Bu günün dərsləri, vaxtları və statusu |

### Gözlənilən Cavab:
```json
{
  "success": true,
  "data": [
    {
      "id": 101,
      "course_title": "Riyazi Analiz II",
      "instructor_name": "Prof. Məmmədov Fuad",
      "lesson_type": "lecture",
      "start_time": "09:00",
      "end_time": "10:30",
      "status": "scheduled",
      "live_class_id": null,
      "specialty": "Kompüter Mühəndisliyi"
    },
    {
      "id": 102,
      "course_title": "Proqramlaşdırma II",
      "instructor_name": "Prof. Əliyev Kamil",
      "lesson_type": "seminar",
      "start_time": "11:00",
      "end_time": "12:30",
      "status": "in-progress",
      "live_class_id": 45,
      "specialty": "Kompüter Mühəndisliyi"
    }
  ]
}
```

### Xəritə (Hazırkı → TMİS):
| Hazırkı sahə | TMİS sahəsi |
|---|---|
| `$lesson['time']` | `start_time + " - " + end_time` |
| `$lesson['course']` | `course_title` |
| `$lesson['instructor']` | `instructor_name` |
| `$lesson['type']` | `status === 'in-progress' ? 'live' : lesson_type` |
| `$lesson['status']` | `status` |
| `$lesson['live_class_id']` | `live_class_id` (null deyilsə → "Qoşul" düyməsi göstərilir) |

### 🔧 Nə etməli:
1.  `$todaySchedule` massivini dolduran bütün SQL sorğularını silin (sətir 17-92).
2.  TMİS APİ-dən `schedule/today` endpointi çağırın.
3.  Gələn JSON-ı `$todaySchedule` formatına uyğunlaşdırın.
4.  **Status məntiqi:** `live_class_id` null deyilsə → "Qoşul" düyməsi göstərilir (link: `live-view.php?id={live_class_id}`).

---

### C. Son Arxiv Materialları

| # | Endpoint | Metod | Açıqlama |
|---|----------|-------|----------|
| 1 | `/api/student/recent-archives` | GET | Son 4 arxiv materialı |

### Gözlənilən Cavab:
```json
{
  "success": true,
  "data": [
    {
      "id": 55,
      "type": "live",
      "title": "Riyazi Analiz II - Həftə 14",
      "course_title": "Riyazi Analiz II",
      "date": "2026-02-18T14:30:00",
      "duration_minutes": 85,
      "status": "completed"
    },
    {
      "id": 12,
      "type": "manual",
      "title": "Əlavə Material - Diferensial Tənliklər",
      "course_title": "Riyazi Analiz II",
      "date": "2026-02-17T10:00:00",
      "duration_minutes": null,
      "status": "completed"
    }
  ]
}
```

### 🔧 Nə etməli:
1.  `$recentActivities` üçün olan SQL sorğularını silin (sətir 182-248).
2.  TMİS APİ-dən `recent-archives` endpointi çağırın.
3.  `type: "live"` → video ikonası, `type: "manual"` → fayl ikonası.

---

## 🎥 Addım 3: Canlı Dərslər Paneli

**Fayl:** `student/live-classes.php`

Bu səhifə 2 hissədən ibarətdir: aktiv canlı dərslər və gələcək dərslər.

### A. Aktiv Canlı Dərslər (Çəkilir)

| # | Endpoint | Metod | Açıqlama |
|---|----------|-------|----------|
| 1 | `/api/student/live-sessions/active` | GET | Hazırda canlı olan dərslər |

### Gözlənilən Cavab:
```json
{
  "success": true,
  "data": [
    {
      "id": 204,
      "title": "Həftə 16 - Dərs 31",
      "course_title": "Proqramlaşdırma II",
      "instructor_name": "Prof. Əliyev Kamil",
      "start_time": "11:00",
      "duration_minutes": 90,
      "participants_count": 23,
      "max_participants": 50,
      "status": "live"
    }
  ]
}
```

### 🔧 Nə etməli:
1.  `$liveClasses` üçün SQL sorğusunu silin (sətir 17-44).
2.  TMİS APİ-dən `live-sessions/active` çağırın.
3.  `status === 'live'` → qırmızı "Canlı Dərsə Qoşul" düyməsi. Link: `live-view.php?id={id}`.
4.  `status === 'starting-soon'` → "Tezliklə Başlayır" düyməsi.

---

### B. Gələcək Dərslər (Çəkilir)

| # | Endpoint | Metod | Açıqlama |
|---|----------|-------|----------|
| 1 | `/api/student/schedule/upcoming` | GET | Növbəti 5 planlaşdırılmış dərs |

### Gözlənilən Cavab:
```json
{
  "success": true,
  "data": [
    {
      "course_title": "Riyazi Analiz II",
      "lesson_type": "Mühazirə",
      "instructor_name": "Prof. Məmmədov Fuad",
      "date": "2026-02-22",
      "time": "09:00",
      "day_name": "Şənbə"
    }
  ]
}
```

### 🔧 Nə etməli:
1. `$upcomingClasses` üçün olan bütün SQL-i silin (sətir 48-170).
2. TMİS APİ-dən `schedule/upcoming` çağırın.
3. Gələn nəticəni kartlara doldurub göstərin.

---

### C. Canlı Dərsə Qoşulma (GÖNDƏRİLİR)

Tələbə canlı dərsə qoşulduqda TMİS-ə bildiriş göndərilməlidir.

| # | Endpoint | Metod | Açıqlama |
|---|----------|-------|----------|
| 1 | `/api/student/live-sessions/join` | POST | Tələbənin dərsə qoşulduğunu qeyd edir |

### Göndəriləcək Data:
```json
{
  "live_session_id": 204,
  "joined_at": "2026-02-20 11:03:22"
}
```

### Gözlənilən Cavab:
```json
{
  "success": true,
  "message": "Uğurla qoşuldunuz"
}
```

### 🔧 Nə etməli:
1.  `live-view.php` səhifəsi yüklənəndə JavaScript ilə POST sorğusu göndərin.
2.  Bu, TMİS-ə tələbənin dərsə qoşulduğunu xəbər verir (davamiyyət üçün lazımdır).

---

### D. Canlı Dərsdən Çıxma (GÖNDƏRİLİR)

Tələbə canlı dərsi tərk etdikdə TMİS-ə göndərilir.

| # | Endpoint | Metod | Açıqlama |
|---|----------|-------|----------|
| 1 | `/api/student/live-sessions/leave` | POST | Tələbənin dərsdən çıxdığını qeyd edir |

### Göndəriləcək Data:
```json
{
  "live_session_id": 204,
  "left_at": "2026-02-20 12:28:45",
  "duration_minutes": 85
}
```

### 🔧 Nə etməli:
1.  `live-view.php`-da tələbə "Çıxış" düyməsini basdıqda və ya browser bağlandıqda (`beforeunload` event) POST göndərin.
2.  `duration_minutes` = `left_at - joined_at` (front-end-də hesablanır).

---

## 📘 Addım 4: Fənlərim (Qeydiyyatlı Fənlər)

**Fayl:** `student/lessons.php`

Bu səhifədə tələbənin qeydiyyatdan keçdiyi bütün fənlər görünür.

### A. Fənn Siyahısı (Çəkilir)

| # | Endpoint | Metod | Açıqlama |
|---|----------|-------|----------|
| 1 | `/api/student/subjects` | GET | Tələbənin bütün aktiv fənləri |

### Gözlənilən Cavab:
```json
{
  "success": true,
  "data": [
    {
      "id": 38,
      "title": "Proqramlaşdırma II",
      "category": "Fənn",
      "instructor_name": "Prof. Əliyev Kamil",
      "progress": 65,
      "status": "active",
      "enrolled_date": "2026-02-01",
      "total_lessons": 30,
      "completed_lessons": 20,
      "lecture_count": 15,
      "seminar_count": 15,
      "lecture_done": 10,
      "seminar_done": 10,
      "weekly_days": "Çərşənbə axşamı, Cümə axşamı",
      "start_time": "11:00",
      "course_level": 3,
      "next_lesson": "Dərs 21",
      "has_live_class": true,
      "live_class_id": 204
    }
  ]
}
```

### Xəritə (Hazırkı → TMİS):
| Hazırkı sahə | TMİS sahəsi |
|---|---|
| `$course['title']` | `title` |
| `$course['instructor']` | `instructor_name` |
| `$course['progress']` | `progress` |
| `$course['stats']['lecture_count']` | `lecture_count` |
| `$course['stats']['seminar_count']` | `seminar_count` |
| `$course['stats']['lecture_done']` | `lecture_done` |
| `$course['stats']['seminar_done']` | `seminar_done` |
| `$course['schedule']['days']` | `weekly_days` |
| `$course['schedule']['time']` | `start_time` |
| `$course['hasLiveClass']` | `has_live_class` |
| `$course['liveClassId']` | `live_class_id` |

### 🔧 Nə etməli:
1.  `$enrolledCourses` üçün olan bütün SQL sorğularını silin (sətir 17-113).
2.  TMİS APİ-dən `student/subjects` çağırın.
3.  `has_live_class: true` → qırmızı "Canlı Dərsə Qoşul" düyməsi göstərin.
4.  Progress bar-ı `progress` faizi ilə doldurun.
5.  Mühazirə/Seminar statistikalarını göstərin.

---

## 📦 Addım 5: Arxiv və Resurslar

**Fayl:** `student/archive.php`

Bu səhifədə keçmiş dərslərin video yazıları, PDF materialları göstərilir.

### A. Arxiv Siyahısı (Çəkilir)

| # | Endpoint | Metod | Açıqlama |
|---|----------|-------|----------|
| 1 | `/api/student/archive` | GET | Bütün əlçatan arxiv materialları |

### Gözlənilən Cavab:
```json
{
  "success": true,
  "stats": {
    "total_lessons": 28,
    "total_videos": 20,
    "total_pdfs": 15,
    "total_views": 342
  },
  "data": [
    {
      "id": 55,
      "type": "live",
      "title": "Canlı Dərs Yazısı: Proqramlaşdırma II - Həftə 14",
      "course_title": "Proqramlaşdırma II",
      "instructor_name": "Prof. Əliyev Kamil",
      "date": "2026-02-18",
      "duration": "1:25:00",
      "views": 45,
      "has_video": true,
      "has_pdf": false,
      "description": "Sistem tərəfindən avtomatik qeydə alınmış canlı dərs yazısı.",
      "video_url": "https://tmis.ndu.edu.az/storage/recordings/55.webm",
      "pdf_url": null
    },
    {
      "id": 12,
      "type": "manual",
      "title": "Əlavə Material - Diferensial Tənliklər",
      "course_title": "Riyazi Analiz II",
      "instructor_name": "Prof. Məmmədov Fuad",
      "date": "2026-02-17",
      "duration": null,
      "views": 12,
      "has_video": false,
      "has_pdf": true,
      "description": "Müəllim tərəfindən yüklənmiş PDF material.",
      "video_url": null,
      "pdf_url": "https://tmis.ndu.edu.az/storage/materials/12.pdf"
    }
  ]
}
```

### Xəritə (Hazırkı → TMİS):
| Hazırkı sahə | TMİS sahəsi | Açıqlama |
|---|---|---|
| `count($archivedLessons)` | `stats.total_lessons` | Ümumi Dərslər kartı |
| `$totalVideos` | `stats.total_videos` | Video Yazılar kartı |
| `$totalPdfs` | `stats.total_pdfs` | PDF Materiallar kartı |
| `$totalViews` | `stats.total_views` | Ümumi Baxışlar kartı |
| `$lesson['video_raw']` | `video_url` | Video URL (tam URL gələcək) |
| `$lesson['pdf_url']` | `pdf_url` | PDF URL (tam URL gələcək) |

### 🔧 Nə etməli:
1.  `$archivedLessons` üçün olan bütün SQL-i silin (sətir 17-125).
2.  TMİS APİ-dən `student/archive` çağırın.
3.  Statistika kartlarını `stats` hissəsindən doldurun.
4.  Grid-ə materialları `data` massivindən doldurun.
5.  **Video URL** və **PDF URL** artıq tam URL olaraq gələcək (lokal yol hesablamasına ehtiyac yox).

---

### B. Baxış Sayğacı (GÖNDƏRİLİR)

Tələbə bir materialı izlədikdə/açdıqda TMİS-ə bildirilməlidir.

| # | Endpoint | Metod | Açıqlama |
|---|----------|-------|----------|
| 1 | `/api/student/archive/{id}/view` | POST | Baxış qeydiyyatı |

### Göndəriləcək Data:
```json
{
  "archive_id": 55,
  "viewed_at": "2026-02-20 14:30:00"
}
```

### 🔧 Nə etməli:
1.  `student/api/increment_views.php`-dəki lokal SQL əvəzinə TMİS APİ-yə POST göndərin.
2.  `watch.php` açıldıqda avtomatik view count artırın.

---

## 📈 Addım 6: Statistika Səhifəsi

**Fayl:** `student/statistics.php`

### A. Xülasə Statistikalar (Çəkilir)

| # | Endpoint | Metod | Açıqlama |
|---|----------|-------|----------|
| 1 | `/api/student/statistics` | GET | Ümumi statistika |

### Gözlənilən Cavab:
```json
{
  "success": true,
  "data": {
    "total_courses": 6,
    "total_archives": 28,
    "live_lessons_this_month": 12,
    "total_views": 342,
    "attendance_rate": 87.5,
    "total_study_hours": 45.5
  }
}
```

### Xəritə (Hazırkı → TMİS):
| Hazırkı dəyişən | TMİS sahəsi |
|---|---|
| `$stats['totalCourses']` | `data.total_courses` |
| `$stats['totalArchives']` | `data.total_archives` |
| `$stats['liveLessons']` | `data.live_lessons_this_month` |
| `$stats['totalViews']` | `data.total_views` |

### Yeni: Əlavə Göstəricilər (TMİS-dən)
- **Davamiyyət faizi:** `attendance_rate` — tələbənin ümumilikdə nə qədər dərsə qatıldığını göstərir.
- **Tədris saatı:** `total_study_hours` — tələbənin canlı dərslərdə cəmi keçirdiyi vaxt.

### 🔧 Nə etməli:
1.  `statistics.php`-dəki SQL sorğularını silin (sətir 17-70).
2.  TMİS APİ-dən `student/statistics` çağırın.
3.  Yeni 2 kart əlavə edin: **Davamiyyət** və **Tədris Saatı**.

---

## 📜 Addım 7: Davamiyyət Jurnalı (YENİ)

Bu, müəllim panelindəki **attendance_report** səhifəsinin tələbə versiyasıdır. Tələbə öz davamiyyətini görə bilər.

**Yeni fayl yaradılacaq:** `student/attendance.php`

### A. Fərdiyyət Davamiyyəti (Çəkilir)

| # | Endpoint | Metod | Açıqlama |
|---|----------|-------|----------|
| 1 | `/api/student/attendance` | GET | Tələbənin bütün dərslərdəki davamiyyəti |

### Gözlənilən Cavab:
```json
{
  "success": true,
  "summary": {
    "total_lessons": 45,
    "attended": 40,
    "absent": 5,
    "attendance_rate": 88.9
  },
  "data": [
    {
      "id": 101,
      "course_title": "Proqramlaşdırma II",
      "lesson_title": "Həftə 16 - Dərs 31",
      "lesson_type": "seminar",
      "date": "2026-02-20",
      "start_time": "11:00",
      "end_time": "12:30",
      "joined_at": "11:03",
      "left_at": "12:28",
      "duration_minutes": 85,
      "attendance_percent": 94.4,
      "status": "present"
    },
    {
      "id": 102,
      "course_title": "Riyazi Analiz II",
      "lesson_title": "Həftə 16 - Dərs 32",
      "lesson_type": "lecture",
      "date": "2026-02-20",
      "start_time": "09:00",
      "end_time": "10:30",
      "joined_at": null,
      "left_at": null,
      "duration_minutes": 0,
      "attendance_percent": 0,
      "status": "absent"
    }
  ]
}
```

### 🔧 Nə etməli:
1.  Yeni `student/attendance.php` faylı yaradın.
2.  TMİS APİ-dən `student/attendance` çağırın.
3.  **Xülasə kartları:** Cəmi dərs, İştirak, Qayıb, Davamiyyət faizi.
4.  **Cədvəl:** Fənn, tarix, giriş-çıxış vaxtı, müddət, status (İştirak/Qayıb).
5.  Rəng kodlaması: `present` → yaşıl, `absent` → qırmızı.
6.  Sidebar-a yeni menyu əlavə edin: "Davamiyyət".

---

## 🚀 Addım 8: İcra Təlimatı (Texniki Addımlar)

### Addım 8.1: APİ Client Yaradılması
```
Fayl: student/includes/tmis_api.php
```
Bu faylda bütün TMİS sorğuları üçün reusable funksiyalar yazılmalıdır:

```php
<?php
/**
 * TMİS APİ Client - Tələbə
 */

define('TMIS_BASE_URL', 'https://tmis.ndu.edu.az/api');

function tmis_api_get($endpoint) {
    $token = $_SESSION['tmis_token'] ?? '';
    
    $ch = curl_init(TMIS_BASE_URL . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 401) {
        // Token vaxtı bitib - login-ə yönləndir
        header('Location: login.php?error=session_expired');
        exit;
    }
    
    return json_decode($response, true);
}

function tmis_api_post($endpoint, $data) {
    $token = $_SESSION['tmis_token'] ?? '';
    
    $ch = curl_init(TMIS_BASE_URL . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}
```

### Addım 8.2: Auth Kontrolu
1.  Tələbə TMİS-dən token alaraq Distant Təhsil-ə daxil olur.
2.  Token `$_SESSION['tmis_token']`-da saxlanır.
3.  Hər APİ sorğusunda bu token avtomatik əlavə olunur.

### Addım 8.3: Frontend Dinamikləşdirilməsi
Səhifələri **sıra ilə** yeniləyin:

| # | Fayl | Dəyişiklik | Prioritet |
|---|------|------------|-----------|
| 1 | `includes/tmis_api.php` | APİ Client yaradılması | 🔴 Yüksək |
| 2 | `includes/sidebar.php` | Tələbə adı/ixtisası TMİS-dən | 🔴 Yüksək |
| 3 | `index.php` | Dashboard statistikaları TMİS-dən | 🔴 Yüksək |
| 4 | `live-classes.php` | Canlı dərs siyahısı TMİS-dən | 🔴 Yüksək |
| 5 | `lessons.php` | Fənn siyahısı TMİS-dən | 🟡 Orta |
| 6 | `archive.php` | Arxiv materialları TMİS-dən | 🟡 Orta |
| 7 | `statistics.php` | Statistikalar TMİS-dən | 🟢 Aşağı |
| 8 | `attendance.php` | Yeni səhifə yaradılması | 🟢 Aşağı |

### Addım 8.4: Live Studio Eventləri
Canlı dərs keçən vaxt avtomatik TMİS-ə göndərilən eventlər:

| Event | Trigger | Endpoint | Data |
|-------|---------|----------|------|
| Dərsə qoşulma | `live-view.php` yüklənəndə | `POST /student/live-sessions/join` | `{live_session_id, joined_at}` |
| Dərsdən çıxma | "Çıxış" basıldıqda / browser bağlananda | `POST /student/live-sessions/leave` | `{live_session_id, left_at, duration_minutes}` |
| Arxiv izlənməsi | `watch.php` açıldıqda | `POST /student/archive/{id}/view` | `{archive_id, viewed_at}` |

---

## 📋 Yekun Cədvəl: Bütün APİ Endpointləri

| # | Endpoint | Metod | Səhifə | Tipi |
|---|----------|-------|--------|------|
| 1 | `/api/me` | GET | Hər yerdə | Çəkilir |
| 2 | `/api/student/dashboard-stats` | GET | index.php | Çəkilir |
| 3 | `/api/student/schedule/today` | GET | index.php | Çəkilir |
| 4 | `/api/student/recent-archives` | GET | index.php | Çəkilir |
| 5 | `/api/student/live-sessions/active` | GET | live-classes.php | Çəkilir |
| 6 | `/api/student/schedule/upcoming` | GET | live-classes.php | Çəkilir |
| 7 | `/api/student/live-sessions/join` | POST | live-view.php | Göndərilir |
| 8 | `/api/student/live-sessions/leave` | POST | live-view.php | Göndərilir |
| 9 | `/api/student/subjects` | GET | lessons.php | Çəkilir |
| 10 | `/api/student/archive` | GET | archive.php | Çəkilir |
| 11 | `/api/student/archive/{id}/view` | POST | watch.php | Göndərilir |
| 12 | `/api/student/statistics` | GET | statistics.php | Çəkilir |
| 13 | `/api/student/attendance` | GET | attendance.php | Çəkilir |

---

**Nəticə:** Bu sənədin tam tətbiqi ilə tələbə paneli tamamilə TMİS-ə inteqrasiya olunmuş modul halına gələcəkdir. Tələbənin fənləri, cədvəli, canlı dərsləri, arxiv materialları və davamiyyəti — hamısı TMİS-dən real vaxt rejimində çəkiləcəkdir.
