<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

capstone_cors(['GET', 'POST', 'OPTIONS']);

$action = strtolower((string) ($_GET['action'] ?? ''));

function capstone_session_payload(PDO $pdo, array $user): array
{
    $pdo->exec('DELETE FROM sessions WHERE expires_at <= NOW()');

    $sessionId = bin2hex(random_bytes(32));
    $expiresAt = (new DateTimeImmutable('+7 days'))->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare(
        'INSERT INTO sessions (session_id, user_id, expires_at)
         VALUES (:session_id, :user_id, :expires_at)'
    );
    $stmt->execute([
        ':session_id' => $sessionId,
        ':user_id' => (int) $user['user_id'],
        ':expires_at' => $expiresAt,
    ]);

    return [
        'token' => $sessionId,
        'expires_at' => $expiresAt,
        'user' => capstone_public_user_profile($user),
    ];
}

function capstone_require_auth_fields(array $input, array $fields): void
{
    foreach ($fields as $field) {
        if (trim((string) ($input[$field] ?? '')) === '') {
            capstone_json_response(['error' => $field . ' is required'], 400);
        }
    }
}

try {
    $pdo = capstone_pdo();

    if ($action === 'register') {
        capstone_require_method('POST');

        $input = capstone_get_json_input();
        capstone_require_auth_fields($input, ['email', 'password', 'display_name']);

        $email = strtolower(trim((string) $input['email']));
        $password = (string) $input['password'];
        $displayName = trim((string) $input['display_name']);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            capstone_json_response(['error' => 'A valid email is required'], 400);
        }

        if (strlen($password) < 8) {
            capstone_json_response(['error' => 'Password must be at least 8 characters'], 400);
        }

        if (strlen($displayName) > 100) {
            capstone_json_response(['error' => 'Display name must be 100 characters or fewer'], 400);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO users (email, password_hash, display_name)
             VALUES (:email, :password_hash, :display_name)'
        );

        try {
            $stmt->execute([
                ':email' => $email,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ':display_name' => $displayName,
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                capstone_json_response(['error' => 'An account with this email already exists'], 409);
            }

            throw $exception;
        }

        $userStmt = $pdo->prepare(
            'SELECT user_id, email, display_name, major, project_idea, teammates, course_section, created_at
             FROM users
             WHERE user_id = :user_id
             LIMIT 1'
        );
        $userStmt->execute([':user_id' => (int) $pdo->lastInsertId()]);
        $user = $userStmt->fetch();

        if (!is_array($user)) {
            capstone_json_response(['error' => 'Account was created but could not be loaded'], 500);
        }

        $payload = capstone_session_payload($pdo, $user);
        capstone_json_response([
            'success' => true,
            'token' => $payload['token'],
            'expires_at' => $payload['expires_at'],
            'user' => $payload['user'],
        ], 201);
    }

    if ($action === 'login') {
        capstone_require_method('POST');

        $input = capstone_get_json_input();
        capstone_require_auth_fields($input, ['email', 'password']);

        $email = strtolower(trim((string) $input['email']));
        $password = (string) $input['password'];

        $stmt = $pdo->prepare(
            'SELECT user_id, email, password_hash, display_name, major, project_idea, teammates, course_section, created_at
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!is_array($user) || !password_verify($password, (string) $user['password_hash'])) {
            capstone_json_response(['error' => 'Invalid email or password'], 401);
        }

        $payload = capstone_session_payload($pdo, $user);
        capstone_json_response([
            'success' => true,
            'token' => $payload['token'],
            'expires_at' => $payload['expires_at'],
            'user' => $payload['user'],
        ]);
    }

    if ($action === 'logout') {
        capstone_require_method('POST');

        $token = capstone_auth_token();
        if ($token !== null) {
            $stmt = $pdo->prepare('DELETE FROM sessions WHERE session_id = :session_id');
            $stmt->execute([':session_id' => $token]);
        }

        capstone_json_response(['success' => true]);
    }

    if ($action === 'me') {
        capstone_require_method('GET');

        $user = capstone_auth_user($pdo);
        if (!is_array($user)) {
            capstone_json_response(['error' => 'Not authenticated'], 401);
        }

        capstone_json_response([
            'success' => true,
            'user' => capstone_public_user_profile($user),
        ]);
    }

    capstone_json_response(['error' => 'Unknown auth action'], 404);
} catch (PDOException $exception) {
    capstone_json_response([
        'error' => 'Authentication service is unavailable',
        'details' => $exception->getMessage(),
    ], 500);
}
