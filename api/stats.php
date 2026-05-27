<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

capstone_cors(['GET', 'OPTIONS']);
capstone_require_method('GET');

try {
    $pdo = capstone_pdo();
    $userSession = capstone_user_session();

    $totalStmt = $pdo->prepare('SELECT COUNT(*) FROM chat_logs WHERE cas_uid = :uid');
    $totalStmt->execute([':uid' => $userSession]);
    $totalQuestions = (int) $totalStmt->fetchColumn();

    $topicStmt = $pdo->prepare(
        'SELECT
            COALESCE(t.topic_name, "Uncategorized") AS topic_name,
            COUNT(*) AS question_count
         FROM chat_logs cl
         LEFT JOIN topics t ON cl.topic_id = t.topic_id
         WHERE cl.cas_uid = :uid
         GROUP BY COALESCE(t.topic_name, "Uncategorized")
         ORDER BY question_count DESC, topic_name ASC'
    );
    $topicStmt->execute([':uid' => $userSession]);
    $questionsPerTopic = array_map(static function (array $row): array {
        return [
            'topic_name' => $row['topic_name'],
            'question_count' => (int) $row['question_count'],
        ];
    }, $topicStmt->fetchAll());

    $dailyCounts = [];
    $today = new DateTimeImmutable('today');
    for ($offset = 6; $offset >= 0; $offset--) {
        $date = $today->sub(new DateInterval('P' . $offset . 'D'));
        $dailyCounts[$date->format('Y-m-d')] = 0;
    }

    $dailyStmt = $pdo->prepare(
        'SELECT DATE(created_at) AS activity_date, COUNT(*) AS question_count
         FROM chat_logs
         WHERE cas_uid = :uid
           AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY DATE(created_at)
         ORDER BY activity_date ASC'
    );
    $dailyStmt->execute([':uid' => $userSession]);

    foreach ($dailyStmt->fetchAll() as $row) {
        $activityDate = (string) $row['activity_date'];
        if (array_key_exists($activityDate, $dailyCounts)) {
            $dailyCounts[$activityDate] = (int) $row['question_count'];
        }
    }

    $questionsPerDay = [];
    foreach ($dailyCounts as $activityDate => $count) {
        $questionsPerDay[] = [
            'date' => $activityDate,
            'question_count' => $count,
        ];
    }

    $conversationCountStmt = $pdo->prepare(
        'SELECT COUNT(*) AS conversation_count
         FROM (
            SELECT 1
            FROM chat_logs
            WHERE cas_uid = :uid
            GROUP BY CASE
                WHEN dify_conversation_id IS NULL OR dify_conversation_id = "" THEN CONCAT("local-", log_id)
                ELSE dify_conversation_id
            END
         ) grouped_conversations'
    );
    $conversationCountStmt->execute([':uid' => $userSession]);
    $conversationCount = (int) $conversationCountStmt->fetchColumn();
    $averageQuestionsPerConversation = $conversationCount > 0
        ? round($totalQuestions / $conversationCount, 2)
        : 0.0;

    $conversationStmt = $pdo->prepare(
        'SELECT conversation_id, question_count, last_activity, preview
         FROM (
            SELECT
                CASE
                    WHEN dify_conversation_id IS NULL OR dify_conversation_id = "" THEN CONCAT("local-", log_id)
                    ELSE dify_conversation_id
                END AS conversation_id,
                COUNT(*) AS question_count,
                MAX(created_at) AS last_activity,
                SUBSTRING_INDEX(GROUP_CONCAT(user_query ORDER BY created_at ASC SEPARATOR "||"), "||", 1) AS preview
            FROM chat_logs
            WHERE cas_uid = :uid
            GROUP BY CASE
                WHEN dify_conversation_id IS NULL OR dify_conversation_id = "" THEN CONCAT("local-", log_id)
                ELSE dify_conversation_id
            END
         ) grouped_conversations
         ORDER BY question_count DESC, last_activity DESC
         LIMIT 1'
    );
    $conversationStmt->execute([':uid' => $userSession]);
    $mostActiveConversationRow = $conversationStmt->fetch();
    $mostActiveConversation = $mostActiveConversationRow ? [
        'conversation_id' => (string) $mostActiveConversationRow['conversation_id'],
        'question_count' => (int) $mostActiveConversationRow['question_count'],
        'last_activity' => $mostActiveConversationRow['last_activity'],
        'preview' => (string) $mostActiveConversationRow['preview'],
    ] : null;

    $latestTopicStmt = $pdo->prepare(
        'SELECT
            COALESCE(t.topic_name, "Uncategorized") AS topic_name,
            cl.created_at
         FROM chat_logs cl
         LEFT JOIN topics t ON cl.topic_id = t.topic_id
         WHERE cl.cas_uid = :uid
         ORDER BY cl.created_at DESC, cl.log_id DESC
         LIMIT 1'
    );
    $latestTopicStmt->execute([':uid' => $userSession]);
    $latestTopicRow = $latestTopicStmt->fetch();
    $latestActiveTopic = $latestTopicRow ? [
        'topic_name' => (string) $latestTopicRow['topic_name'],
        'created_at' => $latestTopicRow['created_at'],
    ] : null;

    $recentStmt = $pdo->prepare(
        'SELECT
            cl.log_id,
            cl.user_query,
            cl.created_at,
            COALESCE(t.topic_name, "Uncategorized") AS topic_name,
            f.rating AS feedback_rating
         FROM chat_logs cl
         LEFT JOIN topics t ON cl.topic_id = t.topic_id
         LEFT JOIN feedback f
            ON cl.log_id = f.log_id
           AND f.cas_uid = :feedback_uid
         WHERE cl.cas_uid = :uid
         ORDER BY cl.created_at DESC, cl.log_id DESC
         LIMIT 8'
    );
    $recentStmt->execute([
        ':uid' => $userSession,
        ':feedback_uid' => $userSession,
    ]);
    $recentPrompts = array_map(static function (array $row): array {
        return [
            'log_id' => (int) $row['log_id'],
            'user_query' => (string) $row['user_query'],
            'created_at' => $row['created_at'],
            'topic_name' => $row['topic_name'],
            'feedback_rating' => $row['feedback_rating'],
        ];
    }, $recentStmt->fetchAll());

    $feedbackSummaryStmt = $pdo->prepare(
        'SELECT
            SUM(CASE WHEN rating = "up" THEN 1 ELSE 0 END) AS thumbs_up_count,
            SUM(CASE WHEN rating = "down" THEN 1 ELSE 0 END) AS thumbs_down_count,
            COUNT(*) AS total_feedback
         FROM feedback
         WHERE cas_uid = :uid'
    );
    $feedbackSummaryStmt->execute([':uid' => $userSession]);
    $feedbackSummaryRow = $feedbackSummaryStmt->fetch() ?: [];
    $totalFeedback = (int) ($feedbackSummaryRow['total_feedback'] ?? 0);
    $feedbackSummary = [
        'thumbs_up_count' => (int) ($feedbackSummaryRow['thumbs_up_count'] ?? 0),
        'thumbs_down_count' => (int) ($feedbackSummaryRow['thumbs_down_count'] ?? 0),
        'total_feedback' => $totalFeedback,
        'feedback_rate' => $totalQuestions > 0 ? round(($totalFeedback / $totalQuestions) * 100, 1) : 0.0,
    ];

    $negativeFeedbackStmt = $pdo->prepare(
        'SELECT
            cl.log_id,
            cl.user_query,
            cl.ai_response,
            cl.created_at,
            f.created_at AS feedback_created_at,
            COALESCE(t.topic_name, "Uncategorized") AS topic_name,
            CASE
                WHEN cl.dify_conversation_id IS NULL OR cl.dify_conversation_id = "" THEN CONCAT("local-", cl.log_id)
                ELSE cl.dify_conversation_id
            END AS conversation_id
         FROM feedback f
         INNER JOIN chat_logs cl ON cl.log_id = f.log_id
         LEFT JOIN topics t ON cl.topic_id = t.topic_id
         WHERE f.cas_uid = :uid
           AND f.rating = "down"
         ORDER BY f.created_at DESC, f.feedback_id DESC
         LIMIT 5'
    );
    $negativeFeedbackStmt->execute([':uid' => $userSession]);
    $recentNegativeFeedback = array_map(static function (array $row): array {
        return [
            'log_id' => (int) $row['log_id'],
            'user_query' => (string) $row['user_query'],
            'ai_response' => (string) $row['ai_response'],
            'created_at' => $row['created_at'],
            'feedback_created_at' => $row['feedback_created_at'],
            'topic_name' => (string) $row['topic_name'],
            'conversation_id' => (string) $row['conversation_id'],
        ];
    }, $negativeFeedbackStmt->fetchAll());
} catch (PDOException $exception) {
    capstone_json_response([
        'error' => 'Failed to load admin stats',
        'details' => $exception->getMessage(),
    ], 500);
}

capstone_json_response([
    'total_questions' => $totalQuestions,
    'conversation_count' => $conversationCount,
    'average_questions_per_conversation' => $averageQuestionsPerConversation,
    'questions_per_topic' => $questionsPerTopic,
    'questions_per_day' => $questionsPerDay,
    'most_active_conversation' => $mostActiveConversation,
    'latest_active_topic' => $latestActiveTopic,
    'feedback_summary' => $feedbackSummary,
    'recent_prompts' => $recentPrompts,
    'recent_negative_feedback' => $recentNegativeFeedback,
]);
