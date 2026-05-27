<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

capstone_require_method('GET');

$format = (string) ($_GET['format'] ?? '');
if ($format !== 'csv') {
    capstone_json_response(['error' => 'Only format=csv is supported'], 400);
}

try {
    $pdo = capstone_pdo();
    $stmt = $pdo->prepare(
        'SELECT
            cl.created_at,
            cl.cas_uid,
            COALESCE(t.topic_name, "Uncategorized") AS topic_name,
            CASE
                WHEN cl.dify_conversation_id IS NULL OR cl.dify_conversation_id = "" THEN CONCAT("local-", cl.log_id)
                ELSE cl.dify_conversation_id
            END AS conversation_id,
            cl.user_query,
            cl.ai_response
         FROM chat_logs cl
         LEFT JOIN topics t ON cl.topic_id = t.topic_id
         WHERE cl.cas_uid = :uid
         ORDER BY cl.created_at DESC, cl.log_id DESC'
    );
    $stmt->execute([':uid' => capstone_user_session()]);
    $rows = $stmt->fetchAll();
} catch (PDOException $exception) {
    capstone_json_response([
        'error' => 'Failed to export chat logs',
        'details' => $exception->getMessage(),
    ], 500);
}

$filename = 'capstone-gpt-export-' . date('Y-m-d-His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'wb');
if ($output === false) {
    http_response_code(500);
    exit;
}

fputcsv($output, ['timestamp', 'user', 'topic', 'conversation_id', 'query', 'response'], ',', '"', '');

foreach ($rows as $row) {
    fputcsv($output, [
        $row['created_at'],
        $row['cas_uid'],
        $row['topic_name'],
        $row['conversation_id'],
        $row['user_query'],
        $row['ai_response'],
    ], ',', '"', '');
}

fclose($output);
exit;
