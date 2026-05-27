<?php
declare(strict_types=1);

function capstone_cors(array $methods): void
{
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: ' . implode(', ', $methods));
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function capstone_require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
        capstone_json_response(['error' => 'Method not allowed'], 405);
    }
}

function capstone_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function capstone_load_config(): array
{
    static $config;

    if (is_array($config)) {
        return $config;
    }

    $configPath = file_exists(__DIR__ . '/config.local.php')
        ? __DIR__ . '/config.local.php'
        : __DIR__ . '/config.php';

    $config = require $configPath;
    return $config;
}

function capstone_user_session(): string
{
    $user = capstone_auth_user();

    if (is_array($user) && isset($user['user_id'])) {
        return (string) $user['user_id'];
    }

    return 'test_student';
}

function capstone_pdo(): PDO
{
    static $pdo;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = capstone_load_config();
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        $config['db']['host'],
        $config['db']['dbname']
    );

    $pdo = new PDO($dsn, $config['db']['username'], $config['db']['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function capstone_get_json_input(): array
{
    $rawInput = file_get_contents('php://input');

    if ($rawInput === false || trim($rawInput) === '') {
        return [];
    }

    $input = json_decode($rawInput, true);

    if (!is_array($input)) {
        capstone_json_response(['error' => 'Invalid JSON request body'], 400);
    }

    return $input;
}

function capstone_authorization_header(): string
{
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        return trim((string) $_SERVER['HTTP_AUTHORIZATION']);
    }

    if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return trim((string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) === 'authorization') {
                return trim((string) $value);
            }
        }
    }

    return '';
}

function capstone_auth_token(): ?string
{
    $header = capstone_authorization_header();

    if ($header === '') {
        return null;
    }

    if (!preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $header, $matches)) {
        return null;
    }

    return strtolower($matches[1]);
}

function capstone_auth_user(?PDO $pdo = null): ?array
{
    static $cache = [];

    $token = capstone_auth_token();
    if ($token === null) {
        return null;
    }

    if (array_key_exists($token, $cache)) {
        return $cache[$token];
    }

    try {
        $pdo = $pdo instanceof PDO ? $pdo : capstone_pdo();
        $stmt = $pdo->prepare(
            'SELECT
                u.user_id,
                u.email,
                u.display_name,
                u.major,
                u.project_idea,
                u.teammates,
                u.course_section,
                u.created_at
             FROM sessions s
             INNER JOIN users u ON u.user_id = s.user_id
             WHERE s.session_id = :session_id
               AND s.expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([':session_id' => $token]);
        $user = $stmt->fetch();
    } catch (PDOException $exception) {
        error_log('Capstone GPT auth lookup error: ' . $exception->getMessage());
        $cache[$token] = null;
        return null;
    }

    $cache[$token] = is_array($user) ? $user : null;
    return $cache[$token];
}

function capstone_public_user_profile(array $user): array
{
    $teammates = [];
    $rawTeammates = $user['teammates'] ?? null;

    if (is_string($rawTeammates) && trim($rawTeammates) !== '') {
        $decoded = json_decode($rawTeammates, true);
        if (is_array($decoded)) {
            foreach ($decoded as $name) {
                if (is_scalar($name)) {
                    $trimmed = trim((string) $name);
                    if ($trimmed !== '') {
                        $teammates[] = $trimmed;
                    }
                }
            }
        }
    } elseif (is_array($rawTeammates)) {
        foreach ($rawTeammates as $name) {
            if (is_scalar($name)) {
                $trimmed = trim((string) $name);
                if ($trimmed !== '') {
                    $teammates[] = $trimmed;
                }
            }
        }
    }

    return [
        'user_id' => isset($user['user_id']) ? (int) $user['user_id'] : null,
        'email' => (string) ($user['email'] ?? ''),
        'display_name' => (string) ($user['display_name'] ?? ''),
        'major' => (string) ($user['major'] ?? ''),
        'project_idea' => (string) ($user['project_idea'] ?? ''),
        'teammates' => $teammates,
        'course_section' => (string) ($user['course_section'] ?? 'CSE449'),
        'created_at' => $user['created_at'] ?? null,
    ];
}

function capstone_profile_inputs(?array $user): array
{
    if (!is_array($user)) {
        return [];
    }

    $profile = capstone_public_user_profile($user);

    return [
        'display_name' => $profile['display_name'],
        'major' => $profile['major'],
        'project_idea' => $profile['project_idea'],
        'teammates' => implode(', ', $profile['teammates']),
        'course_section' => $profile['course_section'],
    ];
}

function capstone_profile_context(?array $user): string
{
    if (!is_array($user)) {
        return '';
    }

    $profile = capstone_public_user_profile($user);
    $lines = [];

    if ($profile['display_name'] !== '') {
        $lines[] = 'Display name: ' . $profile['display_name'];
    }

    if ($profile['course_section'] !== '') {
        $lines[] = 'Course section: ' . $profile['course_section'];
    }

    if ($profile['major'] !== '') {
        $lines[] = 'Major: ' . $profile['major'];
    }

    if ($profile['project_idea'] !== '') {
        $lines[] = 'Project idea: ' . $profile['project_idea'];
    }

    if (count($profile['teammates']) > 0) {
        $lines[] = 'Teammates: ' . implode(', ', $profile['teammates']);
    }

    if (count($lines) === 0) {
        return '';
    }

    return "[Student Profile]\n" . implode("\n", $lines);
}

function capstone_topic_name(PDO $pdo, ?int $topicId): ?string
{
    if ($topicId === null || $topicId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT topic_name FROM topics WHERE topic_id = :topic_id LIMIT 1');
    $stmt->execute([':topic_id' => $topicId]);

    $topicName = $stmt->fetchColumn();
    return $topicName !== false ? (string) $topicName : null;
}
