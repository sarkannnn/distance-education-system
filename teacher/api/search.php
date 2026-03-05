<?php
/**
 * Teacher Search API
 */
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Giriş tələb olunur'], 401);
}

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    jsonResponse(['success' => true, 'results' => []]);
}

// Find instructor_id
$instructor = $db->fetch(
    "SELECT id FROM instructors WHERE user_id = ? OR email = ?",
    [$currentUser['id'], $currentUser['email']]
);

if (!$instructor) {
    jsonResponse(['success' => false, 'message' => 'Müəllim məlumatları tapılmadı'], 404);
}

$searchTerm = '%' . $query . '%';
$instructorTerm = $instructor['id'];

// 1. Kurslarda axtar (Müəllimin öz kursları)
$courses = $db->fetchAll("
    SELECT 
        'course' as type,
        id,
        title,
        status as meta
    FROM courses 
    WHERE instructor_id = ? AND (title LIKE ? OR description LIKE ?)
    LIMIT 5
", [$instructorTerm, $searchTerm, $searchTerm]);

// 2. Tələbələrdə axtar (Müəllimin kurslarında olan tələbələr)
$students = $db->fetchAll("
    SELECT DISTINCT
        'student' as type,
        u.id,
        CONCAT(u.first_name, ' ', u.last_name) as title,
        u.email as meta
    FROM users u
    JOIN enrollments e ON u.id = e.user_id
    JOIN courses c ON e.course_id = c.id
    WHERE c.instructor_id = ? AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)
    LIMIT 5
", [$instructorTerm, $searchTerm, $searchTerm, $searchTerm]);

// 3. Arxiv materiallarında axtar (Müəllimin öz materialları)
$archive = $db->fetchAll("
    SELECT 
        'archive' as type,
        a.id,
        a.title as title,
        c.title as meta
    FROM archived_lessons a
    JOIN courses c ON a.course_id = c.id
    WHERE a.instructor_id = ? AND (a.title LIKE ? OR c.title LIKE ?)
    LIMIT 5
", [$instructorTerm, $searchTerm, $searchTerm]);

$results = array_merge($courses, $students, $archive);

jsonResponse([
    'success' => true,
    'results' => $results,
    'count' => count($results)
]);
