<?php
/**
 * Search API
 */
require_once '../includes/auth.php';
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

$searchTerm = '%' . $query . '%';

// Kurslarda axtar
$courses = $db->fetchAll("
    SELECT 
        'course' as type,
        c.id,
        c.title,
        c.description,
        CONCAT(u.first_name, ' ', u.last_name) as instructor
    FROM courses c
    LEFT JOIN instructors i ON c.instructor_id = i.id
    LEFT JOIN users u ON i.user_id = u.id
    WHERE c.title LIKE ? OR c.description LIKE ?
    LIMIT 5
", [$searchTerm, $searchTerm]);

// Dərslərdə axtar
$lessons = $db->fetchAll("
    SELECT 
        'lesson' as type,
        l.id,
        l.title,
        l.description,
        c.title as course
    FROM lessons l
    JOIN courses c ON l.course_id = c.id
    WHERE l.title LIKE ? OR l.description LIKE ?
    LIMIT 5
", [$searchTerm, $searchTerm]);

// Arxivdə axtar
$archived = $db->fetchAll("
    SELECT 
        'archived' as type,
        a.id,
        a.title,
        a.description,
        c.title as course
    FROM archived_lessons a
    JOIN courses c ON a.course_id = c.id
    WHERE a.title LIKE ? OR a.description LIKE ?
    LIMIT 5
", [$searchTerm, $searchTerm]);

// Müəllimlərdə axtar
$instructors = $db->fetchAll("
    SELECT 
        'instructor' as type,
        i.id,
        CONCAT(u.first_name, ' ', u.last_name) as title,
        i.department as description
    FROM instructors i
    JOIN users u ON i.user_id = u.id
    WHERE u.first_name LIKE ? OR u.last_name LIKE ? OR i.department LIKE ?
    LIMIT 5
", [$searchTerm, $searchTerm, $searchTerm]);

$results = array_merge($courses, $lessons, $archived, $instructors);

jsonResponse([
    'success' => true,
    'query' => $query,
    'results' => $results,
    'count' => count($results)
]);
