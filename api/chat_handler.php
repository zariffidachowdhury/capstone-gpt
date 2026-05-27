<?php
declare(strict_types=1);

/**
 * chat_handler.php — PHP middleware (Dify proxy + DB logger)
 *
 * Receives user queries from the frontend, forwards them to Dify.ai,
 * logs the interaction in MySQL, and returns the AI response.
 */

require __DIR__ . '/bootstrap.php';

capstone_cors(['POST', 'OPTIONS']);
capstone_require_method('POST');

$config = capstone_load_config();
$authUser = capstone_auth_user();
$userSession = capstone_user_session();
$input = capstone_get_json_input();

$query = trim((string) ($input['query'] ?? ''));
$conversationId = trim((string) ($input['conversation_id'] ?? ''));
$topicId = isset($input['topic_id']) ? (int) $input['topic_id'] : null;

if ($query === '') {
    capstone_json_response(['error' => 'Query is required'], 400);
}

$difyQuery = $query;
$pdo = null;

if ($topicId !== null && $topicId > 0) {
    try {
        $pdo = capstone_pdo();
        $topicName = capstone_topic_name($pdo, $topicId);

        if ($topicName !== null) {
            $difyQuery = sprintf('[Topic: %s] %s', $topicName, $query);
        }
    } catch (PDOException $exception) {
        error_log('Capstone GPT topic lookup error: ' . $exception->getMessage());
    }
}

$profileContext = capstone_profile_context($authUser);
if ($profileContext !== '') {
    $difyQuery = $profileContext . "\n\n[Student Question]\n" . $difyQuery;
}

$difyPayload = [
    'inputs' => new \stdClass(),
    'query' => $difyQuery,
    'response_mode' => 'blocking',
    'user' => $userSession,
];

if ($conversationId !== '') {
    $difyPayload['conversation_id'] = $conversationId;
}

$ch = curl_init($config['dify']['api_url'] . '/chat-messages');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $config['dify']['api_key'],
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode($difyPayload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 60,
]);

$difyResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

if ($curlError !== '') {
    capstone_json_response([
        'error' => 'Failed to reach Dify API',
        'details' => $curlError,
    ], 502);
}

$difyData = json_decode((string) $difyResponse, true);

if ($httpCode !== 200 || !is_array($difyData) || !isset($difyData['answer'])) {
    capstone_json_response([
        'error' => 'Dify API error',
        'status' => $httpCode,
        'details' => $difyData,
    ], 502);
}

$answer = (string) $difyData['answer'];
$returnedConversationId = (string) ($difyData['conversation_id'] ?? '');
$insertedLogId = null;

try {
    $pdo = $pdo instanceof PDO ? $pdo : capstone_pdo();

    $stmt = $pdo->prepare(
        'INSERT INTO chat_logs (cas_uid, topic_id, user_query, ai_response, dify_conversation_id)
         VALUES (:uid, :topic, :query, :response, :conv_id)'
    );
    $stmt->execute([
        ':uid' => $userSession,
        ':topic' => $topicId,
        ':query' => $query,
        ':response' => $answer,
        ':conv_id' => $returnedConversationId !== '' ? $returnedConversationId : null,
    ]);
    $insertedLogId = (int) $pdo->lastInsertId();
} catch (PDOException $exception) {
    // DB logging should never prevent the user from receiving the answer.
    error_log('Capstone GPT DB Error: ' . $exception->getMessage());
}

capstone_json_response([
    'answer' => $answer,
    'conversation_id' => $returnedConversationId,
    'topic_id' => $topicId,
    'log_id' => $insertedLogId,
]);
