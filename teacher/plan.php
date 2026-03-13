<?php
/**
 * Teacher Archive - Arxivləşdirilmiş Materiallar (Premium Redesign)
 */
$currentPage = 'plan';
$pageTitle = 'Arxiv və Resurslar';

require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$auth = new Auth();
requireInstructor();

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

// Müəllimin instructor_id-sini tap
$instructor = $db->fetch(
    "SELECT id FROM instructors WHERE user_id = ? OR email = ?",
    [$currentUser['id'], $currentUser['email']]
);

// Context IDs for filtering
$myTeacherIds = [];
if ($instructor) {
    $myTeacherIds[] = (int) $instructor['id'];
}
if (isset($currentUser['id'])) {
    $myTeacherIds[] = (int) $currentUser['id'];
}
if (isset($_SESSION['tmis_id'])) {
    $myTeacherIds[] = (int) $_SESSION['tmis_id'];
}
$myTeacherIds = array_unique($myTeacherIds);

// DEBUG LOG
file_put_contents('uploads/plan_debug.log', date('Y-m-d H:i:s') . ' - MyIDs: ' . json_encode($myTeacherIds) . ' - User: ' . ($_SESSION['user_name'] ?? 'Guest') . "\n", FILE_APPEND);

// ============================================================
// TMİS Token
// ============================================================
$tmisToken = TmisApi::getToken();

// Statlar
$totalLessons = 0;
$videoCount = 0;
$pdfCount = 0;
$totalViews = 0;
$archivedLessons = [];
$tmisMatchedLocalLiveIds = []; // TMİS-dən gələn və lokal bazamızda uyğunlaşan ID-ləri saxlayır

$isAdmin = ($_SESSION['user_role'] === 'admin');

// ============================================================
// Fənləri Yüklə və SubjectMap yarad
// ============================================================
$courses = [];
$subjectMap = [];

if ($tmisToken) {
    try {
        $coursesResult = TmisApi::getSubjectsList($tmisToken);
        if ($coursesResult['success'] && isset($coursesResult['data'])) {
            $subjects = $coursesResult['data'];
            // Əlifba sırası ilə düz
            usort($subjects, function ($a, $b) {
                return strcmp($a['subject_name'] ?? '', $b['subject_name'] ?? '');
            });

            foreach ($subjects as $cs) {
                $subjectMap[$cs['id']] = $cs;
                $subjName = trim($cs['subject_name'] ?? '');
                $profName = trim($cs['profession_name'] ?? '');
                $courseLevel = isset($cs['course']) ? $cs['course'] . '-ci kurs' : '';

                $title = $subjName;
                if (!empty($profName)) {
                    $title .= " - " . $profName;
                }
                if (!empty($courseLevel)) {
                    $title .= " (" . $courseLevel . ")";
                }

                $courses[] = [
                    'id' => $cs['id'],
                    'title' => $title
                ];
            }
        }
    } catch (Exception $e) {
        error_log('TMİS Subjects List xətası: ' . $e->getMessage());
    }
}

// ============================================================
// TMİS API-dən Arxiv Məlumatlarını çək
// ============================================================
$tmisArchiveLoaded = false;

if ($tmisToken) {
    try {
        $archiveResult = TmisApi::getArchive($tmisToken);

        // ============================================================
        // TMİS Activities API-dən mövzu adları və müddətləri çək
        // Archive API topic/duration qaytarmır, Activities API qaytarır
        // ============================================================
        $tmisActivities = [];
        try {
            $activitiesResult = TmisApi::getActivities($tmisToken);
            if ($activitiesResult['success'] && isset($activitiesResult['data'])) {
                $actData = $activitiesResult['data'];
                // Activities data müxtəlif formatlarda gələ bilər
                $actList = [];
                if (isset($actData['activities'])) {
                    $actList = $actData['activities'];
                } elseif (isset($actData['data'])) {
                    $actList = $actData['data'];
                } elseif (is_array($actData) && !isset($actData['success'])) {
                    $actList = $actData;
                }
                foreach ($actList as $act) {
                    $tmisActivities[] = $act;
                }
            }
        } catch (Exception $e) {
            error_log('TMİS Activities xətası: ' . $e->getMessage());
        }

        // Debug: activities datanı saxla
        if (!file_exists(__DIR__ . '/test_activities.json') && !empty($tmisActivities)) {
            file_put_contents(__DIR__ . '/test_activities.json', json_encode($tmisActivities, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        if ($archiveResult['success'] && isset($archiveResult['data'])) {
            $archiveData = $archiveResult['data'];
            $tmisArchiveLoaded = true;



            // Statistika kartları
            if (isset($archiveData['cards'])) {
                $cards = $archiveData['cards'];
                $totalLessons = $cards['total_lessons'] ?? 0;
                $videoCount = $cards['total_videos'] ?? 0;
                $pdfCount = $cards['total_pdfs'] ?? 0;
                $totalViews = $cards['total_views'] ?? 0;
            }

            // Material siyahısını tapmaq (müxtəlif formatlara dözümlü)
            $materials = null;
            if (isset($archiveData['grid']['data']) && is_array($archiveData['grid']['data'])) {
                $materials = $archiveData['grid']['data'];
            } elseif (isset($archiveData['materials']) && is_array($archiveData['materials'])) {
                $materials = $archiveData['materials'];
            } elseif (isset($archiveData['recordings']) && is_array($archiveData['recordings'])) {
                $materials = $archiveData['recordings'];
            } elseif (isset($archiveData['archive']) && is_array($archiveData['archive'])) {
                $materials = $archiveData['archive'];
            } elseif (is_array($archiveData) && !isset($archiveData['success'])) {
                $materials = $archiveData;
            }

            if ($materials) {
                // Əgər siyahıda material varsa, API-nin verdiyi "13" (ümumi plan) rəqəmini deyil, 
                // faktiki arxiv sayını göstərək ki, istifadəçi çaşmasın.
                $actualItemsCount = count($materials);
                if ($actualItemsCount > 0 || !isset($archiveData['cards'])) {
                    $totalLessons = $actualItemsCount;
                    // Video/PDF saylarını da sıfırlayıb yenidən hesablayaq ki, dəqiq olsun
                    $videoCount = 0;
                    $pdfCount = 0;
                    $totalViews = 0;
                }

                // ============================================================
                // Lokal live_classes cədvəlindən bütün dərsləri yüklə
                // TMİS URL-ləri lesson_ID_ pattern-ə uyğun gəlmir, ona görə
                // subject_id + tarix əsasında match edəcəyik
                // ============================================================
                $localLiveClasses = [];
                if ($instructor) {
                    $localLiveClasses = $db->fetchAll(
                        "SELECT lc.id, lc.title, lc.course_id, lc.duration_minutes, lc.start_time, lc.recording_path, lc.views 
                         FROM live_classes lc 
                         WHERE lc.instructor_id = ? 
                         ORDER BY lc.start_time DESC",
                        [$instructor['id']]
                    );
                }

                foreach ($materials as $item) {
                    $type = strtolower($item['type'] ?? 'video');
                    $fileUrl = $item['file_url'] ?? ($item['url'] ?? ($item['path'] ?? '#'));
                    $fileType = strtolower($item['file_type'] ?? '');

                    // Sənəd Tespiti: fayl uzantısı və ya file_type əsasında
                    $docExtensions = ['.pdf', '.doc', '.docx', '.ppt', '.pptx', '.xls', '.xlsx', '.txt', '.odt', '.rtf'];
                    $isDocument = false;
                    $lowerUrl = strtolower((string) $fileUrl);
                    foreach ($docExtensions as $ext) {
                        if (substr($lowerUrl, -strlen($ext)) === $ext) {
                            $isDocument = true;
                            break;
                        }
                    }
                    // file_type 'other', 'document', 'material', 'pdf' isə də sənəddir
                    if (in_array($fileType, ['other', 'document', 'material', 'pdf', 'quiz'])) {
                        $isDocument = true;
                    }
                    if (!empty($item['pdf_url'])) {
                        $isDocument = true;
                    }
                    if ($isDocument) {
                        $type = 'pdf';
                    }

                    $isLive = ($item['source'] ?? '') === 'live' || !empty($item['is_live']) || strpos($type, 'video') !== false;

                    $cId = $item['course_id'] ?? ($item['subject_id'] ?? 0);
                    $sInfo = $subjectMap[$cId] ?? [];

                    if (!file_exists(__DIR__ . '/test_materials.json')) {
                        file_put_contents(__DIR__ . '/test_materials.json', json_encode($materials, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    }

                    // Müəllimin daxil etdiyi original başlıq ("Dərs:" sahəsi üçün)
                    $originalTitle = !empty($item['title']) ? $item['title'] : (!empty($item['material_title']) ? $item['material_title'] : '');

                    // Kartın əsas başlığı: "Canlı Dərs Yazısı #..." başlıqlarını fənn adı ilə əvəz et
                    $itemTitle = $originalTitle;
                    if (empty($itemTitle)) {
                        $itemTitle = !empty($sInfo['subject_name']) ? $sInfo['subject_name'] : 'Material';
                    }
                    if (strpos((string) $itemTitle, 'Canlı Dərs Yazısı #') === 0 || strpos((string) $itemTitle, ' - Video Yazı') !== false) {
                        $itemTitle = !empty($sInfo['subject_name']) ? $sInfo['subject_name'] : $itemTitle;
                    }

                    $itemDate = !empty($item['date']) ? $item['date'] : (!empty($item['created_at']) ? $item['created_at'] : date('Y-m-d H:i:s'));

                    $lessonDuration = $item['duration'] ?? ($item['duration_minutes'] ?? '');

                    // "Dərs:" sahəsi - müəllimin daxil etdiyi mövzu adını istifadə et
                    // "Canlı Dərs Yazısı #..." kimi standart başlıqları boş qəbul et
                    $topicName = $originalTitle;
                    if (empty($topicName) || strpos((string) $topicName, 'Canlı Dərs Yazısı #') === 0 || strpos((string) $topicName, ' - Video Yazı') !== false) {
                        $topicName = '-'; // Müəllim mövzu daxil etməyibsə
                    }

                    // ============================================================
                    // Mövzu adı və müddəti tapmaq üçün çoxpilləli axtarış
                    // ============================================================
                    $matchedTopic = null;
                    $matchedDuration = null;
                    $matchedLocalLiveId = null;

                    // Yanaşma 1: Fayl adından lesson ID tap (lokal fayllar üçün)
                    if (!empty($fileUrl) && preg_match('/lesson_(\d+)_/i', $fileUrl, $matches)) {
                        $localLiveClassId = (int) $matches[1];
                        $matchedLocalLiveId = $localLiveClassId;

                        // Fix: Only mark as matched if TMIS version actually has a video
                        // If it's just a placeholder (#), we want to keep the local record available.
                        if ($fileUrl != '#' && !empty($fileUrl)) {
                            $tmisMatchedLocalLiveIds[] = $localLiveClassId;
                        }

                        $localLive = $db->fetch("SELECT title, duration_minutes, recording_path, views FROM live_classes WHERE id = ?", [$localLiveClassId]);
                        if ($localLive) {
                            if (!empty($localLive['title']) && strpos((string) $localLive['title'], 'Canlı Dərs') === false) {
                                $matchedTopic = $localLive['title'];
                            }
                            if (!empty($localLive['duration_minutes']) && $localLive['duration_minutes'] > 0) {
                                $matchedDuration = $localLive['duration_minutes'];
                            }
                            // Store views from local DB
                            if (isset($localLive['views'])) {
                                $matchedViews = (int) $localLive['views'];
                            }
                            // Lokal video faylı mövcuddursa, TMIS URL-i əvəzinə onu istifadə et
                            if (!empty($localLive['recording_path'])) {
                                $localPath = __DIR__ . '/../uploads/videos/' . $localLive['recording_path'];
                                if (file_exists($localPath)) {
                                    $fileUrl = '../uploads/videos/' . $localLive['recording_path'];
                                }
                            }
                        }
                    }

                    // Yanaşma 2: Lokal live_classes cədvəlindən subject_id + tarix match
                    if (!$matchedTopic && !empty($localLiveClasses)) {
                        $itemTimestamp = strtotime($itemDate);
                        $bestMatch = null;
                        $bestDiff = PHP_INT_MAX;

                        foreach ($localLiveClasses as $llc) {
                            if ((int) $llc['course_id'] !== (int) $cId) {
                                continue;
                            }
                            $llcTimestamp = strtotime($llc['start_time']);
                            $diff = abs($itemTimestamp - $llcTimestamp);
                            if ($diff < 7200 && $diff < $bestDiff) {
                                $bestMatch = $llc;
                                $bestDiff = $diff;
                            }
                        }
                        if ($bestMatch) {
                            if (!$matchedLocalLiveId) {
                                $matchedLocalLiveId = $bestMatch['id'];
                            }
                            if ($fileUrl != '#' && !empty($fileUrl)) {
                                $tmisMatchedLocalLiveIds[] = $bestMatch['id'];
                            }
                            if (!empty($bestMatch['title']) && strpos((string) $bestMatch['title'], 'Canlı Dərs') === false) {
                                $matchedTopic = $bestMatch['title'];
                            }
                            if (!empty($bestMatch['duration_minutes']) && $bestMatch['duration_minutes'] > 0) {
                                $matchedDuration = $bestMatch['duration_minutes'];
                            }
                            // Store views from local DB
                            if (isset($bestMatch['views'])) {
                                $matchedViews = (int) $bestMatch['views'];
                            }
                            // Lokal video faylı mövcuddursa, TMIS URL-i əvəzinə onu istifadə et
                            if (!empty($bestMatch['recording_path'])) {
                                $localPath = __DIR__ . '/../uploads/videos/' . $bestMatch['recording_path'];
                                if (file_exists($localPath)) {
                                    $fileUrl = '../uploads/videos/' . $bestMatch['recording_path'];
                                }
                            }
                        }
                    }

                    // Yanaşma 3: TMİS Activities API-dən subject_id + tarix match
                    if ((!$matchedTopic || !$matchedDuration) && !empty($tmisActivities)) {
                        $itemTimestamp = strtotime($itemDate);
                        $bestMatch = null;
                        $bestDiff = PHP_INT_MAX;

                        foreach ($tmisActivities as $act) {
                            $actSubjectId = $act['subject_id'] ?? ($act['course_id'] ?? 0);
                            if ((int) $actSubjectId !== (int) $cId) {
                                continue;
                            }
                            $actDate = $act['started_at'] ?? ($act['date'] ?? ($act['created_at'] ?? ''));
                            if (empty($actDate))
                                continue;
                            $actTimestamp = strtotime($actDate);
                            $diff = abs($itemTimestamp - $actTimestamp);
                            // 3 saat (10800 san) aralığında ən yaxın aktiviti tap
                            if ($diff < 10800 && $diff < $bestDiff) {
                                $bestMatch = $act;
                                $bestDiff = $diff;
                            }
                        }
                        if ($bestMatch) {
                            $matchedTmisSessionId = $bestMatch['id'] ?? ($bestMatch['live_session_id'] ?? null);
                            $matchedSpecialization = $bestMatch['specialization'] ?? ($bestMatch['profession_name'] ?? null);
                            $matchedCourseLevel = $bestMatch['course'] ?? ($bestMatch['course_level'] ?? null);

                            if (!$matchedTopic) {
                                $actTopic = $bestMatch['topic'] ?? ($bestMatch['topic_name'] ?? ($bestMatch['title'] ?? ''));
                                if (!empty($actTopic) && strpos((string) $actTopic, 'Canlı Dərs') === false) {
                                    $matchedTopic = $actTopic;
                                }
                            }
                            if (!$matchedDuration) {
                                // started_at/ended_at fərqindən əsl müddəti hesabla
                                $actStarted = $bestMatch['started_at'] ?? '';
                                $actEnded = $bestMatch['ended_at'] ?? '';
                                if (!empty($actStarted) && !empty($actEnded)) {
                                    $realDuration = round((strtotime($actEnded) - strtotime($actStarted)) / 60);
                                    if ($realDuration > 2) {
                                        $matchedDuration = $realDuration;
                                    }
                                }
                                // Qeyd: Activities API-nin duration_minutes sahəsi planlı dərs
                                // müddətidir (hər zaman 90 dəq), videonun real müddəti deyil.
                                // Real müddət JavaScript ilə video metadata-dan alınır.
                            }
                        }
                    }

                    // Tapılan məlumatları tətbiq et
                    if ($matchedTopic) {
                        $itemTitle = $matchedTopic;
                        if ($topicName === '-') {
                            $topicName = $matchedTopic;
                        }
                    }
                    // matchedDuration tapıldısa, onu üstünlük ver (həqiqi müddətdir)
                    if ($matchedDuration && $matchedDuration > 1) {
                        $lessonDuration = $matchedDuration;
                    }

                    // duration_seconds varsa, onu dəqiqəyə çevir
                    if ((empty($lessonDuration) || $lessonDuration === 'N/A' || $lessonDuration === '00:00:00' || $lessonDuration === '0') && !empty($item['duration_seconds'])) {
                        $lessonDuration = ceil((int) $item['duration_seconds'] / 60);
                    }

                    // HH:MM:SS formatını dəqiqəyə çevir
                    if (is_string($lessonDuration) && preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $lessonDuration, $tParts)) {
                        $totalMinutes = (int) $tParts[1] * 60 + (int) $tParts[2] + round((int) $tParts[3] / 60);
                        if ($totalMinutes > 1) {
                            $lessonDuration = $totalMinutes;
                        }
                    }

                    // Qeyri-real dəyərləri filtirlə (1 dəqiqə və ya daha az real dərsin müddəti ola bilməz)
                    if (empty($lessonDuration) || $lessonDuration === 'N/A' || $lessonDuration === '00:00:00' || $lessonDuration === '0' || $lessonDuration === 0) {
                        $lessonDuration = '-';
                    } else if (is_numeric($lessonDuration)) {
                        $numDuration = (int) $lessonDuration;
                        if ($numDuration <= 1) {
                            $lessonDuration = '-'; // 1 dəqiqə və ya daha az real deyil
                        } else {
                            // Saat:dəqiqə formatında göstər (məs: 1 saat 30 dəq)
                            if ($numDuration >= 60) {
                                $hours = floor($numDuration / 60);
                                $mins = $numDuration % 60;
                                $lessonDuration = $hours . ' saat' . ($mins > 0 ? ' ' . $mins . ' dəq' : '');
                            } else {
                                $lessonDuration = $numDuration . ' dəq';
                            }
                        }
                    } else if (strpos((string) $lessonDuration, 'dəq') === false && strpos((string) $lessonDuration, ':') === false && strpos((string) $lessonDuration, 'saat') === false) {
                        $lessonDuration = $lessonDuration . ' dəq';
                    }

                    // ============================================================
                    // Final Fallback: Əgər URL hələ də TMIS remote serveri göstərirsə,
                    // kurs + tarix əsasında lokal recording axtar
                    // ============================================================
                    if (strpos((string) $fileUrl, 'tmis.ndu.edu.az') !== false || strpos((string) $fileUrl, 'storage/teacher-archive') !== false) {
                        $itemTs = strtotime($itemDate);
                        $localFallback = $db->fetchAll(
                            "SELECT id, recording_path, start_time, views FROM live_classes 
                             WHERE course_id = ? AND recording_path IS NOT NULL AND recording_path != ''
                             ORDER BY start_time DESC",
                            [$cId]
                        );
                        $bestLocal = null;
                        $bestDiff = PHP_INT_MAX;
                        foreach ($localFallback as $lf) {
                            $lfTs = strtotime($lf['start_time']);
                            $diff = abs($itemTs - $lfTs);
                            if ($diff < 86400 && $diff < $bestDiff) { // 24 saat aralığında
                                $localFile = __DIR__ . '/../uploads/videos/' . $lf['recording_path'];
                                if (file_exists($localFile)) {
                                    $bestLocal = $lf;
                                    $bestDiff = $diff;
                                    $matchedViews = (int) $lf['views'];
                                }
                            }
                        }
                        if ($bestLocal) {
                            $fileUrl = '../uploads/videos/' . $bestLocal['recording_path'];
                            $tmisMatchedLocalLiveIds[] = $bestLocal['id'];
                            if (!$matchedLocalLiveId) {
                                $matchedLocalLiveId = $bestLocal['id'];
                            }
                        }
                    }

                    $archivedLessons[] = [
                        'id' => ($isLive ? 'live_' : 'arch_') . ($item['id'] ?? 0),
                        'db_id' => $item['id'] ?? 0,
                        'tmis_session_id' => $matchedTmisSessionId ?? ($item['id'] ?? 0),
                        'local_live_id' => $matchedLocalLiveId,
                        'title' => $itemTitle, // Əsas Başlıq
                        'course_id' => $cId,
                        'course_name' => $sInfo['subject_name'] ?? ($item['course_name'] ?? ($item['subject_name'] ?? 'Fənn')),
                        'specialization_name' => $sInfo['profession_name'] ?? ($matchedSpecialization ?? 'Təyin edilməyib'),
                        'course_level' => isset($sInfo['course']) ? $sInfo['course'] . '-cü kurs' : (isset($matchedCourseLevel) ? $matchedCourseLevel . '-cü kurs' : 'Təyin edilməyib'),
                        'lesson_type' => $topicName, // "Dərs: " Label-i üçün mühazirənin adını ayarladıq
                        'instructor_name' => $item['instructor_name'] ?? ($currentUser['first_name'] . ' ' . $currentUser['last_name']),
                        'date' => $itemDate,
                        'views' => $matchedViews ?? ($item['views'] ?? 0),
                        'file_url' => $fileUrl,
                        'duration' => $lessonDuration,
                        'is_live' => $isLive,
                        'type' => $type
                    ];

                    // Sayları dəqiqləşdir
                    if ($type === 'pdf' || $type === 'material' || $type === 'quiz' || (isset($item['file_type']) && $item['file_type'] === 'pdf')) {
                        $pdfCount++;
                    } else {
                        $videoCount++;
                    }
                    $totalViews += (int) ($matchedViews ?? ($item['views'] ?? 0));
                    // Reset matched views for next item
                    $matchedViews = null;
                }
            } else {
                // Siyahı boşdursa, bütün statları 0 göstərək (Ümumi Dərslər 13 görünməsin deyə)
                $totalLessons = 0;
                $videoCount = 0;
                $pdfCount = 0;
                $totalViews = 0;
            }
        }
    } catch (Exception $e) {
        error_log('TMİS Archive xətası: ' . $e->getMessage());
    }
}

// ============================================================
// Həmişə Lokal bazadan arxiv məlumatlarını çək (TMİS-də olmayanları əlavə etmək üçün)
// ============================================================
if (!empty($myTeacherIds) || $isAdmin) {
    $idPlaceholder = implode(',', array_fill(0, count($myTeacherIds), '?'));

    $whereManual = $isAdmin ? "1=1" : "a.instructor_id IN ($idPlaceholder)";
    $paramsManual = $isAdmin ? [] : $myTeacherIds;

    // 1. Manual Archives
    $manualArchives = $db->fetchAll(
        "SELECT a.*, c.title as course_name, ins.name as instructor_name
         FROM archived_lessons a
         LEFT JOIN courses c ON a.course_id = c.id
         LEFT JOIN instructors ins ON a.instructor_id = ins.id
         WHERE {$whereManual}
         ORDER BY a.created_at DESC",
        $paramsManual
    );
    foreach ($manualArchives as $archive) {
        $isPdf = (strpos($archive['pdf_url'] ?? '', '.pdf') !== false || empty($archive['video_url']));
        if ($isPdf)
            $pdfCount++;
        else
            $videoCount++;
        $totalViews += (int) $archive['views'];

        $localTitle = $archive['title'];
        $archivedLessons[] = [
            'id' => 'arch_' . $archive['id'],
            'db_id' => $archive['id'],
            'title' => $localTitle,
            'course_id' => $archive['course_id'],
            'course_name' => $archive['course_name'],
            'specialization_name' => 'Təyin edilməyib',
            'course_level' => 'Təyin edilməyib',
            'lesson_type' => $localTitle, // "Dərs: " sahəsi
            'instructor_name' => $archive['instructor_name'],
            'date' => $archive['created_at'] ?? $archive['archived_date'],
            'views' => $archive['views'],
            'file_url' => ($archive['pdf_url'] ?: $archive['video_url']),
            'duration' => $archive['duration'] ?? 'N/A',
            'is_live' => false,
            'type' => $isPdf ? 'pdf' : 'video'
        ];
    }

    // 2. Live Recordings
    $whereLive = "lc.recording_path IS NOT NULL AND lc.recording_path != ''";
    $paramsLive = [];

    if (!$isAdmin && !empty($myTeacherIds)) {
        $idPlaceholder = implode(',', array_fill(0, count($myTeacherIds), '?'));
        $whereLive .= " AND lc.instructor_id IN ($idPlaceholder)";
        $paramsLive = $myTeacherIds;
    }

    $liveRecordings = $db->fetchAll(
        "SELECT lc.*, 
                (CASE WHEN lc.is_stream = 1 AND lc.specialty_name = 'Axın (çoxlu ixtisas)' THEN NULL ELSE lc.specialty_name END) as specialty_name,
                c.title as course_name, ins.name as instructor_name, ins.user_id as ins_user_id
         FROM live_classes lc
         LEFT JOIN courses c ON (lc.course_id = c.id OR lc.course_id = c.tmis_subject_id)
         LEFT JOIN instructors ins ON lc.instructor_id = ins.id
         WHERE {$whereLive}
         ORDER BY lc.start_time DESC",
        $paramsLive
    );
    foreach ($liveRecordings as $rec) {
        // Əgər bu dərs TMİS-dən gəlibsə və siyahıya əlavə olunubsa, 
        // yalnız o halda skip et ki, bu NORMAL dərsdir. 
        // Axın dərsləri (is_stream=1) üçün skip etmirik, çünki TMİS-dən gələn 
        // məlumatda yalnız bir fənn ID-si olur, bizə isə hamısı lazımdır.
        if (in_array($rec['id'], $tmisMatchedLocalLiveIds) && empty($rec['is_stream'])) {
            continue;
        }

        // tmis_session_id ilə də yoxla — eyni TMIS session artıq əlavə olunubsa skip et
        if (!empty($rec['tmis_session_id'])) {
            $alreadyExists = false;
            foreach ($archivedLessons as $existing) {
                if (isset($existing['tmis_session_id']) && $existing['tmis_session_id'] == $rec['tmis_session_id']) {
                    $alreadyExists = true;
                    break;
                }
            }
            if ($alreadyExists)
                continue;
        }

        // Başlıq + kurs ID ilə də yoxla — eyni mövzu artıq TMİS-dən əlavə olunubsa skip et
        $recTitle = trim($rec['title'] ?? '');
        if (!empty($recTitle)) {
            $titleDuplicate = false;
            foreach ($archivedLessons as $existing) {
                $existTitle = trim($existing['title'] ?? '');
                if ($existTitle === $recTitle && (int) ($existing['course_id'] ?? 0) === (int) ($rec['course_id'] ?? 0)) {
                    $titleDuplicate = true;
                    break;
                }
            }
            if ($titleDuplicate)
                continue;
        }

        $videoCount++;
        $totalViews += (int) ($rec['views'] ?? 0);

        $localTitle = $rec['title'] ?: ($rec['course_name'] ?: 'Canlı Dərs');
        if (strpos((string) $localTitle, 'Canlı Dərs Yazısı #') === 0) {
            $localTitle = $rec['course_name'] ?: 'Canlı Dərs';
        }

        $durationMinutes = (int) ($rec['duration_minutes'] ?? 0);
        // Əgər duration_minutes 0-dırsa, started_at/ended_at-dan hesabla
        if ($durationMinutes <= 0 && !empty($rec['started_at']) && !empty($rec['ended_at'])) {
            $startTs = strtotime($rec['started_at']);
            $endTs = strtotime($rec['ended_at']);
            if ($startTs && $endTs && $endTs > $startTs) {
                $durationMinutes = (int) ceil(($endTs - $startTs) / 60);
            }
        }
        if ($durationMinutes >= 60) {
            $hours = floor($durationMinutes / 60);
            $mins = $durationMinutes % 60;
            $formattedDuration = $hours . ' saat' . ($mins > 0 ? ' ' . $mins . ' dəq' : '');
        } elseif ($durationMinutes > 1) {
            $formattedDuration = $durationMinutes . ' dəq';
        } else {
            $formattedDuration = '-';
        }

        // "Dərs:" sahəsi üçün müəllimin daxil etdiyi mövzu adı
        $liveTopicName = $rec['title'] ?: '-';
        if (strpos((string) $liveTopicName, 'Canlı Dərs Yazısı #') === 0 || strpos((string) $liveTopicName, 'Canlı Dərs') === 0) {
            $liveTopicName = '-';
        }

        $sInfo = $subjectMap[$rec['course_id']] ?? [];
        $specName = (!empty($rec['specialty_name']) && $rec['specialty_name'] !== 'Axın (çoxlu ixtisas)') 
                    ? $rec['specialty_name'] 
                    : ($sInfo['profession_name'] ?? 'Təyin edilməyib');
        $courseLvl = isset($sInfo['course']) ? $sInfo['course'] . '-cü kurs' : 'Təyin edilməyib';

        $archivedLessons[] = [
            'id' => 'live_' . $rec['id'],
            'db_id' => $rec['id'],
            'local_live_id' => $rec['id'],
            'title' => $localTitle, // Kartın əsas başlığı
            'course_id' => $rec['course_id'],
            'stream_course_ids' => $rec['stream_course_ids'] ?? '', // Added for multi-filtering
            'course_name' => $rec['course_name'],
            'specialization_name' => $specName,
            'course_level' => $courseLvl,
            'lesson_type' => $liveTopicName, // Müəllimin daxil etdiyi mövzu adı
            'instructor_name' => $rec['instructor_name'],
            'date' => $rec['start_time'],
            'views' => (int) ($rec['views'] ?? 0),
            'file_url' => '../uploads/videos/' . $rec['recording_path'],
            'duration' => $formattedDuration,
            'is_live' => true,
            'type' => 'video'
        ];
    }

    $totalLessons = count($archivedLessons);
}

// Arxivləri tarixinə görə (təzədən köhnəyə) sırala
usort($archivedLessons, function ($a, $b) {
    return strtotime($b['date'] ?? '2000-01-01') - strtotime($a['date'] ?? '2000-01-01');
});

$totalLessons = count($archivedLessons);

// (Mövzu siyahısı artıq yuxarıda yüklənib - $courses)

require_once 'includes/header.php';
?>

<?php require_once 'includes/sidebar.php'; ?>

<div class="main-wrapper">
    <?php require_once 'includes/topnav.php'; ?>

    <main class="main-content">
        <div class="content-container">

            <div class="page-header flex justify-between items-center mb-8">
                <div>
                    <h1 style="font-size: 32px; font-weight: 900; color: var(--text-primary); margin: 0;">Arxiv və
                        Resurslar</h1>
                    <p style="color: var(--text-muted); font-weight: 500; font-size: 16px;">Keçmiş dərslərin video
                        yazıları və
                        materiallar</p>

                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success mt-4"
                            style="border-radius: 12px; border: none; background: #ecfdf5; color: #059669; padding: 15px 20px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                            <i data-lucide="check-circle" style="width: 20px;"></i>
                            Məlumat arxivə uğurla əlavə edildi.
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['tmis_success'])): ?>
                        <div class="alert alert-success mt-2"
                            style="border-radius: 12px; border: none; background: #f0f9ff; color: #0284c7; padding: 15px 20px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                            <i data-lucide="cloud-check" style="width: 20px;"></i>
                            <?php echo $_SESSION['tmis_success'];
                            unset($_SESSION['tmis_success']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['tmis_error'])): ?>
                        <div class="alert alert-danger mt-2"
                            style="border-radius: 12px; border: none; background: #fff1f2; color: #e11d48; padding: 15px 20px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                            <i data-lucide="alert-circle" style="width: 20px;"></i>
                            TMİS Xətası: <?php echo $_SESSION['tmis_error'];
                            unset($_SESSION['tmis_error']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger mt-4"
                            style="border-radius: 12px; border: none; background: #fff1f2; color: #e11d48; padding: 15px 20px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                            <i data-lucide="x-circle" style="width: 20px;"></i>
                            <?php echo htmlspecialchars($_GET['error']); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <button class="btn btn-primary" onclick="openModal('planModal')"
                    style="padding: 14px 28px; border-radius: 16px; font-weight: 700;">
                    <i data-lucide="plus-circle" style="width: 20px; height: 20px;"></i>
                    Yeni Arxiv
                </button>
            </div>

            <!-- Stats -->
            <div class="stats-grid-mockup">
                <div class="stat-card-mockup pink">
                    <div class="stat-icon-mockup pink">
                        <i data-lucide="book-open"></i>
                    </div>
                    <div class="stat-value-mockup"><?php echo $totalLessons; ?></div>
                    <div class="stat-label-mockup pink">Ümumi Dərslər</div>
                </div>

                <div class="stat-card-mockup blue">
                    <div class="stat-icon-mockup blue">
                        <i data-lucide="play-circle"></i>
                    </div>
                    <div class="stat-value-mockup"><?php echo $videoCount; ?></div>
                    <div class="stat-label-mockup blue">Video Yazılar</div>
                </div>

                <div class="stat-card-mockup green">
                    <div class="stat-icon-mockup green">
                        <i data-lucide="file-text"></i>
                    </div>
                    <div class="stat-value-mockup"><?php echo $pdfCount; ?></div>
                    <div class="stat-label-mockup green">PDF Materiallar</div>
                </div>

                <div class="stat-card-mockup purple">
                    <div class="stat-icon-mockup purple">
                        <i data-lucide="eye"></i>
                    </div>
                    <div class="stat-value-mockup"><?php echo $totalViews; ?></div>
                    <div class="stat-label-mockup purple">Ümumi Baxışlar</div>
                </div>
            </div>

            <!-- Filter -->
            <div class="card mb-10"
                style="padding: 24px; border-radius: 20px; border: 1px solid var(--border-color); background: var(--gray-50);">
                <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: center;">
                    <div style="position: relative; flex: 1; min-width: 250px;">
                        <i data-lucide="search"
                            style="position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                        <input type="text" id="archiveSearch" placeholder="Dərs, fənn və ya material axtar..."
                            class="form-input" style="padding-left: 50px; height: 55px; border-radius: 15px;"
                            onkeyup="filterArchives()">
                    </div>

                    <!-- NÖV Filter -->
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <span
                            style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em;">NÖV</span>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" class="type-filter-btn active" data-type="all"
                                onclick="setTypeFilter('all', this)"
                                style="width: 48px; height: 48px; border-radius: 14px; background: #3b82f6; border: 2px solid #3b82f6; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s;">
                                <i data-lucide="layers" style="width: 22px; height: 22px; color: white;"></i>
                            </button>
                            <button type="button" class="type-filter-btn" data-type="video"
                                onclick="setTypeFilter('video', this)"
                                style="width: 48px; height: 48px; border-radius: 14px; background: var(--bg-primary); border: 2px solid var(--border-color); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s;">
                                <i data-lucide="play" style="width: 22px; height: 22px; color: var(--text-muted);"></i>
                            </button>
                            <button type="button" class="type-filter-btn" data-type="pdf"
                                onclick="setTypeFilter('pdf', this)"
                                style="width: 48px; height: 48px; border-radius: 14px; background: var(--bg-primary); border: 2px solid var(--border-color); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s;">
                                <i data-lucide="file-text"
                                    style="width: 22px; height: 22px; color: var(--text-muted);"></i>
                            </button>
                        </div>
                    </div>

                    <select id="courseFilter" class="form-input"
                        style="width: auto; min-width: 180px; height: 55px; border-radius: 15px; max-width: 100%;"
                        onchange="filterArchives()">
                        <option value="all">Bütün fənlər</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo e(truncate($c['title'])); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="archiveGrid" class="grid-3" style="gap: 30px;">
                <?php foreach ($archivedLessons as $lesson): ?>
                    <?php
                    $isDoc = in_array($lesson['type'], ['pdf', 'material', 'quiz']);
                    $courseIds = [$lesson['course_id']];
                    if (!empty($lesson['stream_course_ids'])) {
                        $sIds = explode(',', $lesson['stream_course_ids']);
                        foreach ($sIds as $sid) {
                            $sid = trim($sid);
                            if (!empty($sid) && !in_array($sid, $courseIds)) {
                                $courseIds[] = $sid;
                            }
                        }
                    }
                    $courseDataAttr = implode(',', $courseIds);
                    ?>
                    <div class="archive-card card p-0 overflow-hidden"
                        data-title="<?php echo strtolower(e($lesson['title'])); ?>"
                        data-course="<?php echo $courseDataAttr; ?>"
                        data-type="<?php echo $isDoc ? 'pdf' : 'video'; ?>"
                        style="background: var(--bg-white) !important; border-radius: 24px; border: 1px solid var(--border-color); box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
                        <!-- Video Placeholder / Thumb -->
                        <div
                            style="height: 180px; background: <?php echo $isDoc ? '#f8fafc' : '#0f172a'; ?>; position: relative; display: flex; align-items: center; justify-content: center; color: <?php echo $isDoc ? '#1e293b' : 'white'; ?>;">
                            <div
                                style="width: 65px; height: 65px; border-radius: 50%; background: <?php echo $isDoc ? 'rgba(14, 89, 149, 0.05)' : 'rgba(255,255,255,0.1)'; ?>; border: 1px solid <?php echo $isDoc ? 'rgba(14, 89, 149, 0.1)' : 'rgba(255,255,255,0.2)'; ?>; display: flex; align-items: center; justify-content: center;">
                                <i data-lucide="<?php echo !$isDoc ? 'play' : 'file-text'; ?>"
                                    fill="<?php echo !$isDoc ? 'white' : 'rgba(14, 89, 149, 0.1)'; ?>"
                                    style="width: 30px; height: 30px; <?php echo !$isDoc ? 'margin-left: 3px;' : 'color: #0E5995;'; ?>"></i>
                            </div>
                            <?php if ($lesson['is_live']): ?>
                                <span id="dbadge-<?php echo $lesson['id']; ?>"
                                    style="position: absolute; bottom: 15px; right: 15px; background: rgba(14, 89, 149, 0.9); color: white; padding: 4px 12px; border-radius: 8px; font-size: 11px; font-weight: 800;"><?php echo $lesson['duration']; ?></span>
                            <?php elseif (!$isDoc): ?>
                                <span id="dbadge-<?php echo $lesson['id']; ?>"
                                    style="position: absolute; bottom: 15px; right: 15px; background: rgba(0,0,0,0.6); color: white; padding: 4px 12px; border-radius: 8px; font-size: 11px; font-weight: 800;"><?php echo $lesson['duration']; ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Content Body -->
                        <div style="padding: 24px;">
                            <h3
                                style="font-size: 20px; font-weight: 950; color: var(--text-primary); margin: 0 0 12px 0; line-height: 1.3; height: 52px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                                <?php echo e($lesson['title']); ?>
                            </h3>

                            <div style="display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px;">
                                <div
                                    style="font-size: 13px; font-weight: 700; color: var(--text-primary); display: flex; align-items: flex-start; gap: 8px;">
                                    <span style="color: var(--text-muted); min-width: 55px;">Fənn:</span>
                                    <span style="color: var(--primary);"><?php echo e($lesson['course_name']); ?></span>
                                </div>
                                <div
                                    style="font-size: 13px; font-weight: 600; color: var(--text-primary); display: flex; align-items: flex-start; gap: 8px;">
                                    <span style="color: var(--text-muted); min-width: 55px;">İxtisas:</span>
                                    <span><?php echo e($lesson['specialization_name']); ?></span>
                                </div>
                                <div
                                    style="font-size: 13px; font-weight: 600; color: var(--text-primary); display: flex; align-items: flex-start; gap: 8px;">
                                    <span style="color: var(--text-muted); min-width: 55px;">Kurs:</span>
                                    <span><?php echo e($lesson['course_level']); ?></span>
                                </div>
                                <div
                                    style="font-size: 13px; font-weight: 600; color: var(--text-primary); display: flex; align-items: flex-start; gap: 8px;">
                                    <span style="color: var(--text-muted); min-width: 55px;">Dərs mövzusu:</span>
                                    <span><?php echo e($lesson['lesson_type']); ?></span>
                                </div>
                                <?php if (!$isDoc): ?>
                                    <div
                                        style="font-size: 13px; font-weight: 600; color: var(--text-primary); display: flex; align-items: flex-start; gap: 8px;">
                                        <span style="color: var(--text-muted); min-width: 55px;">Müddət:</span>
                                        <span id="dtext-<?php echo $lesson['id']; ?>"
                                            data-video-url="<?php echo e($lesson['file_url']); ?>"
                                            data-lesson-id="<?php echo $lesson['id']; ?>"><?php echo e($lesson['duration']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($isAdmin && !empty($lesson['instructor_name'])): ?>
                                <div
                                    style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 12px; display: flex; align-items: center; gap: 4px;">
                                    <i data-lucide="user" style="width: 14px; height: 14px;"></i>
                                    <?php echo e($lesson['instructor_name']); ?>
                                </div>
                            <?php else: ?>
                                <div style="margin-bottom: 6px;"></div>
                            <?php endif; ?>

                            <div
                                style="display: flex; gap: 20px; font-size: 13px; color: var(--text-muted); font-weight: 600; margin-bottom: 24px;">
                                <span style="display: flex; align-items: center; gap: 6px;"><i data-lucide="calendar"
                                        style="width: 16px;"></i>
                                    <?php echo date('d.m.Y', strtotime($lesson['date'])); ?></span>
                                <span style="display: flex; align-items: center; gap: 6px;"><i data-lucide="eye"
                                        style="width: 16px;"></i> <?php echo $lesson['views']; ?> baxış</span>
                            </div>

                            <!-- Card Actions -->
                            <div
                                style="display: flex; gap: 10px; padding-top: 20px; border-top: 1px solid var(--border-color);">
                                <a href="<?php echo $lesson['file_url']; ?>" target="_blank" class="btn btn-primary flex-1"
                                    style="height: 48px; border-radius: 12px; font-weight: 800;">
                                    <i data-lucide="<?php echo !$isDoc ? 'play' : 'download'; ?>" style="width: 18px;"></i>
                                    <?php echo !$isDoc ? 'İzlə' : 'Yüklə'; ?>
                                </a>

                                <?php if (!$isDoc): ?>
                                    <?php
                                    $downloadUrl = 'api/download_file.php?url=' . urlencode($lesson['file_url']) . '&filename=' . urlencode(preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $lesson['title']) . '.mp4');
                                    ?>
                                    <a href="<?php echo $downloadUrl; ?>" class="btn btn-secondary"
                                        style="width: 48px; height: 48px; border-radius: 12px; padding: 0; color: #3b82f6;"
                                        title="Videonu Yüklə">
                                        <i data-lucide="download" style="width: 20px;"></i>
                                    </a>
                                <?php endif; ?>

                                <?php if ($lesson['is_live']): ?>
                                    <a href="attendance_report.php?id=<?php echo $lesson['local_live_id'] ?? $lesson['db_id']; ?>"
                                        class="btn btn-secondary"
                                        style="width: 48px; height: 48px; border-radius: 12px; padding: 0;" title="Hesabat">
                                        <i data-lucide="bar-chart-2" style="width: 20px;"></i>
                                    </a>
                                <?php endif; ?>
                                <?php
                                $userRole = strtolower($_SESSION['user_role'] ?? '');
                                $isLive = (bool) $lesson['is_live'];
                                $canDelete = false;

                                if ($userRole === 'admin') {
                                    $canDelete = true;
                                } elseif (($userRole === 'teacher' || $userRole === 'instructor') && !$isLive) {
                                    $canDelete = true;
                                }

                                if ($canDelete):
                                    ?>
                                    <button
                                        onclick="deleteArchive(<?php echo $lesson['db_id']; ?>, '<?php echo addslashes($lesson['title']); ?>', <?php echo $isLive ? 'true' : 'false'; ?>)"
                                        class="btn btn-secondary"
                                        style="width: 48px; height: 48px; border-radius: 12px; padding: 0; color: #ef4444; border: 1px solid #fee2e2;"
                                        title="Sil">
                                        <i data-lucide="trash-2" style="width: 20px; color: #ef4444;"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>

<!-- Modal Fix -->
<div id="planModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px; border-radius: 24px;">
        <div class="modal-header" style="padding: 25px;">
            <h2 style="font-weight: 800;">Yeni Arxiv Materialı</h2>
            <button class="modal-close" onclick="closeModal('planModal')"><i data-lucide="x"></i></button>
        </div>
        <form action="api/add_archive" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="course_name" id="modal_course_name">
            <input type="hidden" name="faculty_name" id="modal_faculty_name">
            <input type="hidden" name="specialty_name" id="modal_specialty_name">
            <input type="hidden" name="course_level" id="modal_course_level">

            <div class="modal-body" style="padding: 25px;">
                <div class="form-group mb-4">
                    <label class="form-label" style="font-weight: 700;">Fənn Seçin</label>
                    <select name="course_id" id="modal_course_id" class="form-input" required
                        style="border-radius: 12px; height: 50px;" onchange="updateModalMetadata(this.value)">
                        <option value="">Seçin...</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo e($c['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-4">
                    <label class="form-label" style="font-weight: 700;">Material Başlığı</label>
                    <input type="text" name="title" class="form-input" placeholder="Məs: I Mühazirə - Giriş" required
                        style="border-radius: 12px; height: 50px;">
                </div>
                <div class="form-group mb-4">
                    <label class="form-label" style="font-weight: 700;">Material Növü</label>
                    <select name="type" class="form-input" required style="border-radius: 12px; height: 50px;">
                        <option value="material">PDF Material</option>
                        <option value="video">Video Yazı (MP4)</option>
                        <option value="quiz">Tapşırıq (PDF)</option>
                    </select>
                </div>
                <div class="form-group mb-4">
                    <label class="form-label" style="font-weight: 700;">Fayl Seçin</label>
                    <input type="file" name="file" class="form-input"
                        style="border-radius: 12px; height: 50px; padding-top: 10px;">
                </div>
            </div>
            <div class="modal-footer" style="padding: 25px; border-top: 1px solid #f1f5f9;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('planModal')"
                    style="border-radius: 12px;">Ləğv et</button>
                <button type="submit" class="btn btn-primary" style="border-radius: 12px; padding: 12px 25px;">Əlavə
                    et</button>
            </div>
        </form>
    </div>
</div>

<script>
    let currentTypeFilter = 'all';
    const subjectMap = <?php echo json_encode($subjectMap); ?>;

    function updateModalMetadata(courseId) {
        const info = subjectMap[courseId];
        if (info) {
            document.getElementById('modal_course_name').value = info.subject_name || '';
            document.getElementById('modal_faculty_name').value = info.faculty_name || '';
            document.getElementById('modal_specialty_name').value = info.profession_name || '';
            document.getElementById('modal_course_level').value = info.course ? info.course + '-cü kurs' : '-';
        }
    }

    function filterArchives() {
        const q = document.getElementById('archiveSearch').value.toLowerCase();
        const cid = document.getElementById('courseFilter').value;
        document.querySelectorAll('.archive-card').forEach(c => {
            const t = c.getAttribute('data-title');
            const co = c.getAttribute('data-course'); // This is now a comma-separated list
            const tp = c.getAttribute('data-type');
            
            const matchesSearch = t.includes(q);
            const courseIds = co.split(',');
            const matchesCourse = (cid === 'all' || courseIds.includes(cid));
            const matchesType = (currentTypeFilter === 'all' || tp === currentTypeFilter);
            
            c.style.display = (matchesSearch && matchesCourse && matchesType) ? 'block' : 'none';
        });
    }

    function setTypeFilter(type, btn) {
        currentTypeFilter = type;
        // Reset all buttons
        document.querySelectorAll('.type-filter-btn').forEach(b => {
            b.classList.remove('active');
            b.style.background = 'var(--bg-primary)';
            b.style.borderColor = 'var(--border-color)';
            // Lucide converts <i> to <svg>, so check both
            const icon = b.querySelector('svg') || b.querySelector('i');
            if (icon) icon.style.color = 'var(--text-muted)';
        });
        // Activate clicked button
        btn.classList.add('active');
        btn.style.background = '#3b82f6';
        btn.style.borderColor = '#3b82f6';
        const activeIcon = btn.querySelector('svg') || btn.querySelector('i');
        if (activeIcon) activeIcon.style.color = 'white';
        // Apply filter
        filterArchives();
    }

    function openModal(id) { document.getElementById(id).style.display = 'flex'; document.body.style.overflow = 'hidden'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; document.body.style.overflow = ''; }
    function deleteArchive(id, title, isLive) {
        const displayTitle = title && title !== '-' ? title : 'bu materialı';
        if (!confirm(`"${displayTitle}" silinsin?`)) return;

        const fd = new FormData();
        const api = isLive ? 'api/delete_live_recording.php' : 'api/delete_archive.php';

        if (isLive) {
            fd.append('live_class_id', id);
        } else {
            fd.append('archive_id', id);
        }

        fetch(api, {
            method: 'POST',
            body: fd
        })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    location.reload();
                } else {
                    alert(d.message || 'Silinmə zamanı xəta baş verdi');
                }
            })
            .catch(err => {
                console.error('Delete error:', err);
                alert('Serverlə əlaqə kəsildi');
            });
    }
    // ============================================================
    // Real video müddətini video metadata-dan yüklə
    // WebM 'Infinity' bug-nı da həll edir
    // ============================================================
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-video-url]').forEach(function (el) {
            const url = el.getAttribute('data-video-url');
            const lessonId = el.getAttribute('data-lesson-id');
            if (!url || url === '#') return;

            if (!url.match(/\.(webm|mp4|ogg|mkv|avi)(\?|$)/i)) return;

            const video = document.createElement('video');
            video.preload = 'metadata';

            // Formatlama funksiyası
            const formatDuration = (totalSeconds) => {
                if (totalSeconds <= 0 || !isFinite(totalSeconds)) return null;
                const totalMinutes = Math.round(totalSeconds / 60);
                if (totalMinutes >= 60) {
                    const hours = Math.floor(totalMinutes / 60);
                    const mins = totalMinutes % 60;
                    return hours + ' saat' + (mins > 0 ? ' ' + mins + ' dəq' : '');
                } else if (totalMinutes > 0) {
                    return totalMinutes + ' dəq';
                } else {
                    return Math.round(totalSeconds) + ' san';
                }
            };

            const updateUI = (durationText) => {
                if (!durationText) return;
                el.textContent = durationText;
                const badge = document.getElementById('dbadge-' + lessonId);
                if (badge) badge.textContent = durationText;
            };

            // Düzgün duration tapıldıqda ediləcəklər
            const handleDuration = () => {
                let duration = video.duration;
                if (duration === Infinity) {
                    // WebM Infinity bug workaround
                    video.currentTime = 1e101;
                    video.onseeked = function () {
                        video.onseeked = null;
                        video.currentTime = 0;
                        updateUI(formatDuration(video.duration));
                    };
                } else {
                    updateUI(formatDuration(duration));
                }
            };

            video.addEventListener('loadedmetadata', handleDuration);
            video.src = url;
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>