<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

capstone_cors(['GET', 'OPTIONS']);
capstone_require_method('GET');

try {
    $pdo = capstone_pdo();
    $userSession = capstone_user_session();
    $stmt = $pdo->prepare(
        'SELECT
            cl.log_id,
            cl.topic_id,
            cl.user_query,
            cl.ai_response,
            cl.dify_conversation_id,
            cl.created_at,
            t.topic_name,
            f.rating AS feedback_rating,
            f.created_at AS feedback_created_at
         FROM chat_logs cl
         LEFT JOIN topics t ON cl.topic_id = t.topic_id
         LEFT JOIN feedback f
            ON cl.log_id = f.log_id
           AND f.cas_uid = :feedback_uid
         WHERE cl.cas_uid = :uid
         ORDER BY cl.created_at ASC, cl.log_id ASC'
    );
    $stmt->execute([
        ':uid' => $userSession,
        ':feedback_uid' => $userSession,
    ]);
    $rows = $stmt->fetchAll();
} catch (PDOException $exception) {
    capstone_json_response([
        'error' => 'Failed to load conversation history',
        'details' => $exception->getMessage(),
    ], 500);
}

$conversations = [];

foreach ($rows as $row) {
    $conversationKey = $row['dify_conversation_id'] ?: 'local-' . $row['log_id'];

    if (!isset($conversations[$conversationKey])) {
        $conversations[$conversationKey] = [
            'local_key' => $conversationKey,
            'conversation_id' => (string) ($row['dify_conversation_id'] ?? ''),
            'topic_id' => $row['topic_id'] !== null ? (int) $row['topic_id'] : null,
            'topic_name' => $row['topic_name'],
            'preview' => (string) $row['user_query'],
            'message_count' => 0,
            'started_at' => $row['created_at'],
            'updated_at' => $row['created_at'],
            'messages' => [],
        ];
    }

    $conversations[$conversationKey]['message_count']++;
    $conversations[$conversationKey]['updated_at'] = $row['created_at'];

    if ($conversations[$conversationKey]['topic_id'] === null && $row['topic_id'] !== null) {
        $conversations[$conversationKey]['topic_id'] = (int) $row['topic_id'];
        $conversations[$conversationKey]['topic_name'] = $row['topic_name'];
    }

    $conversations[$conversationKey]['messages'][] = [
        'log_id' => (int) $row['log_id'],
        'query' => (string) $row['user_query'],
        'response' => (string) $row['ai_response'],
        'created_at' => $row['created_at'],
        'topic_id' => $row['topic_id'] !== null ? (int) $row['topic_id'] : null,
        'topic_name' => $row['topic_name'],
        'feedback_rating' => $row['feedback_rating'],
        'feedback_created_at' => $row['feedback_created_at'],
    ];
}

$conversationList = array_values($conversations);

usort($conversationList, static function (array $left, array $right): int {
    return strcmp($right['updated_at'], $left['updated_at']);
});

capstone_json_response([
    'conversations' => $conversationList,
]);
