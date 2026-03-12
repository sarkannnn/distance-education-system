<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/tmis_api.php';

session_start();

$db = Database::getInstance();
$tmisToken = TmisApi::getToken();

if (!$tmisToken) {
    die("Error: No TMIS token found in session. Please login first.");
}

echo "<pre>Starting migration...\n";

// Get all stream classes
$streams = $db->fetchAll("SELECT id, stream_course_ids FROM live_classes WHERE is_stream = 1");

if (empty($streams)) {
    echo "No stream classes found.\n";
    exit;
}

try {
    $subsList = TmisApi::getSubjectsList($tmisToken);
    if (!$subsList['success']) {
        die("Error fetching subjects from TMIS: " . ($subsList['message'] ?? 'Unknown error'));
    }

    $nameMap = [];
    foreach ($subsList['data'] as $s) {
        $sid = $s['id'] ?? $s['subject_id'] ?? 0;
        if ($sid) $nameMap[$sid] = $s['profession_name'] ?? ($s['subject_name'] ?? 'Naməlum');
    }

    foreach ($streams as $stream) {
        $ids = array_filter(explode(',', $stream['stream_course_ids'] ?? ''));
        if (empty($ids)) continue;

        $names = [];
        foreach ($ids as $cid) {
            if (isset($nameMap[$cid])) {
                $names[] = $nameMap[$cid];
            }
        }

        if (!empty($names)) {
            $fullName = implode(', ', array_unique($names));
            echo "Updating Lesson #{$stream['id']} with: $fullName\n";
            $db->query("UPDATE live_classes SET specialty_name = ? WHERE id = ?", [$fullName, $stream['id']]);
        } else {
            echo "Could not resolve specialties for Lesson #{$stream['id']} (IDs: " . implode(',', $ids) . ")\n";
        }
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

echo "Migration finished.</pre>";
