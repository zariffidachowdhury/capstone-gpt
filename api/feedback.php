<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

capstone_cors(['POST', 'OPTIONS']);
capstone_require_method('POST');

$input = capstone_get_json_input();
$logId = isset($input['log_id']) ? (int) $input['log_id'] : 0;
$rating = (string) ($input['rating'] ?? '');
$userSession = capstone_user_session();

if ($logId <= 0) {
    capstone_json_response(['error' => 'A valid log_id is required'], 400);
}

if (!in_array($rating, ['up', 'down'], true)) {
    capstone_json_response(['error' => 'Rating must be either "up" or "down"'], 400);
}

try {
    $pdo = capstone_pdo();

    $logStmt = $pdo->prepare(
        'SELECT log_id
         FROM chat_logs
         WHERE log_id = :log_id
           AND cas_uid = :uid
         LIMIT 1'
    );
    $logStmt->execute([
        ':log_id' => $logId,
        ':uid' => $userSession,
    ]);

    if (!$logStmt->fetch()) {
        capstone_json_response(['error' => 'Chat log not found for this user'], 404);
    }

    $existingStmt = $pdo->prepare(
        'SELECT feedback_id, rating
         FROM feedback
         WHERE log_id = :log_id
           AND cas_uid = :uid
         LIMIT 1'
    );
    $existingStmt->execute([
        ':log_id' => $logId,
        ':uid' => $userSession,
    ]);
    $existing = $existingStmt->fetch();

    if ($existing && $existing['rating'] === $rating) {
        capstone_json_response([
            'success' => true,
            'log_id' => $logId,
            'rating' => $rating,
            'already_submitted' => true,
            'updated' => false,
        ]);
    }

    if ($existing) {
        $updateStmt = $pdo->prepare(
            'UPDATE feedback
             SET rating = :rating, created_at = CURRENT_TIMESTAMP
             WHERE feedback_id = :feedback_id'
        );
        $updateStmt->execute([
            ':rating' => $rating,
            ':feedback_id' => $existing['feedback_id'],
        ]);

        capstone_json_response([
            'success' => true,
            'log_id' => $logId,
            'rating' => $rating,
            'already_submitted' => false,
            'updated' => true,
        ]);
    }

    $insertStmt = $pdo->prepare(
        'INSERT INTO feedback (log_id, cas_uid, rating)
         VALUES (:log_id, :uid, :rating)'
    );
    $insertStmt->execute([
        ':log_id' => $logId,
        ':uid' => $userSession,
        ':rating' => $rating,
    ]);
} catch (PDOException $exception) {
    capstone_json_response([
        'error' => 'Failed to save feedback',
        'details' => $exception->getMessage(),
    ], 500);
}

capstone_json_response([
    'success' => true,
    'log_id' => $logId,
    'rating' => $rating,
    'already_submitted' => false,
    'updated' => false,
]);
