<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
requireLogin();

if (isset($_GET['id'])) {
    $db = Database::getInstance();
    $user = $auth->getCurrentUser();
    $live_class_id = $_GET['id'];

    try {
        // 0. Ban/Kick yoxlaması - Əgər tələbə dərsdən uzaqlaşdırılıbsa, yenidən girə bilməz
        $kickCheck = $db->fetch(
            "SELECT id FROM live_attendance WHERE live_class_id = ? AND user_id = ? AND is_kicked = 1 ORDER BY id DESC LIMIT 1",
            [$live_class_id, $user['id']]
        );
        if ($kickCheck) {
            header('Location: ../live-classes.php?error=kicked');
            exit;
        }

        // 1. Dərsi bazadan tap - Axın dərsi məlumatları daxil olmaqla
        $class = $db->fetch("SELECT lc.zoom_link, lc.title, lc.course_id, lc.is_stream, lc.stream_course_ids FROM live_classes lc WHERE lc.id = ?", [$live_class_id]);

        if ($class && !empty($class['zoom_link'])) {
            // 2. İştirakı qeyd et
            $db->query("INSERT INTO live_class_participants (live_class_id, user_id, joined_at) 
                        VALUES (?, ?, CURRENT_TIMESTAMP) 
                        ON DUPLICATE KEY UPDATE joined_at = CURRENT_TIMESTAMP",
                [$live_class_id, $user['id']]
            );

            // 3. ULTRA-SMART AUTO-ENROLLMENT
            if ($user['role'] === 'student') {
                try {
                    // Detect enrollments table structure
                    $columns = $db->fetchAll("DESCRIBE enrollments");
                    $columnNames = array_column($columns, 'Field');

                    // Determine student column
                    $studentColumn = null;
                    $studentValue = null;

                    if (in_array('student_id', $columnNames)) {
                        $student = $db->fetch("SELECT id FROM students WHERE user_id = ?", [$user['id']]);
                        if ($student) {
                            $studentColumn = 'student_id';
                            $studentValue = $student['id'];
                        }
                    } elseif (in_array('user_id', $columnNames)) {
                        $studentColumn = 'user_id';
                        $studentValue = $user['id'];
                    }

                    if ($studentColumn && $studentValue) {
                        // Axın dərsi (Stream/Patok) məsələsi:
                        // Tələbənin hansı fənn/ixtisas üzrə qeydiyyatda olduğunu müəyyən etməliyik.
                        $targetCourseId = $class['course_id'];
                        
                        if (!empty($class['is_stream']) && !empty($class['stream_course_ids'])) {
                            $streamIds = explode(',', $class['stream_course_ids']);
                            // Tələbənin bu axındakı fənlərdən hansına qeydiyyatlı olduğunu tap
                            $placeholders = implode(',', array_fill(0, count($streamIds), '?'));
                            $enrollCheck = $db->fetch(
                                "SELECT course_id FROM enrollments WHERE {$studentColumn} = ? AND course_id IN ($placeholders) LIMIT 1",
                                array_merge([$studentValue], $streamIds)
                            );
                            if ($enrollCheck) {
                                $targetCourseId = $enrollCheck['course_id'];
                            }
                        }

                        // Check if already enrolled
                        $enrolled = $db->fetch(
                            "SELECT id FROM enrollments WHERE {$studentColumn} = ? AND course_id = ?",
                            [$studentValue, $targetCourseId]
                        );

                        if (!$enrolled) {
                            // Build dynamic INSERT
                            $insertColumns = [$studentColumn, 'course_id'];
                            $insertValues = [$studentValue, $targetCourseId];

                            // Add date column if exists
                            $dateColumns = ['enrollment_date', 'enrolled_at', 'created_at', 'date'];
                            foreach ($dateColumns as $col) {
                                if (in_array($col, $columnNames)) {
                                    $insertColumns[] = $col;
                                    $insertValues[] = date('Y-m-d H:i:s');
                                    break;
                                }
                            }

                            // Add status if exists
                            if (in_array('status', $columnNames)) {
                                $insertColumns[] = 'status';
                                $insertValues[] = 'active';
                            }

                            $columnsList = implode(', ', $insertColumns);
                            $placeholders = implode(', ', array_fill(0, count($insertValues), '?'));

                            $db->query(
                                "INSERT INTO enrollments ({$columnsList}) VALUES ({$placeholders})",
                                $insertValues
                            );

                            error_log("✅ Auto-enrolled: user {$user['id']} → course {$class['course_id']}");
                        }
                    }
                } catch (Exception $e) {
                    error_log("⚠️ Auto-enrollment failed: " . $e->getMessage());
                }
            }

            // 4. Zoom Linkinə tələbə adını əlavə et
            $displayName = urlencode($user['first_name'] . ' ' . $user['last_name']);
            $zoomLink = $class['zoom_link'];
            $separator = (strpos($zoomLink, '?') !== false) ? '&' : '?';
            $finalLink = $zoomLink . $separator . "dn=" . $displayName;

            // 5. Yönləndir
            header("Location: " . $finalLink);
            exit();
        } else {
            header('Location: ../live-classes?error=link_not_found');
        }
    } catch (Exception $e) {
        header('Location: ../live-classes?error=' . urlencode($e->getMessage()));
    }
} else {
    header('Location: ../live-classes');
}
