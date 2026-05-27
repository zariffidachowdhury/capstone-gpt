<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

capstone_cors(['PUT', 'OPTIONS']);
capstone_require_method('PUT');

function capstone_normalize_optional_string(array $input, string $field, int $maxLength = 0): ?string
{
    if (!array_key_exists($field, $input)) {
        return null;
    }

    $value = trim((string) $input[$field]);
    if ($value === '') {
        return null;
    }

    if ($maxLength > 0 && strlen($value) > $maxLength) {
        capstone_json_response(['error' => $field . ' must be ' . $maxLength . ' characters or fewer'], 400);
    }

    return $value;
}

function capstone_normalize_teammates(mixed $value): array
{
    if ($value === null || $value === '') {
        return [];
    }

    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $value = $decoded;
        } else {
            $value = preg_split('/[\r\n,]+/', $value) ?: [];
        }
    }

    if (!is_array($value)) {
        capstone_json_response(['error' => 'teammates must be an array or a comma-separated string'], 400);
    }

    $teammates = [];
    foreach ($value as $name) {
        if (!is_scalar($name)) {
            continue;
        }

        $trimmed = trim((string) $name);
        if ($trimmed !== '') {
            $teammates[] = substr($trimmed, 0, 100);
        }
    }

    return array_values(array_unique($teammates));
}

try {
    $pdo = capstone_pdo();
    $user = capstone_auth_user($pdo);

    if (!is_array($user)) {
        capstone_json_response(['error' => 'Not authenticated'], 401);
    }

    $input = capstone_get_json_input();
    $major = capstone_normalize_optional_string($input, 'major', 100);
    $projectIdea = capstone_normalize_optional_string($input, 'project_idea');
    $teammates = capstone_normalize_teammates($input['teammates'] ?? []);
    $courseSection = (string) ($input['course_section'] ?? 'CSE449');

    if (!in_array($courseSection, ['CSE448', 'CSE449'], true)) {
        capstone_json_response(['error' => 'course_section must be CSE448 or CSE449'], 400);
    }

    $stmt = $pdo->prepare(
        'UPDATE users
         SET major = :major,
             project_idea = :project_idea,
             teammates = :teammates,
             course_section = :course_section
         WHERE user_id = :user_id'
    );
    $stmt->execute([
        ':major' => $major,
        ':project_idea' => $projectIdea,
        ':teammates' => json_encode($teammates, JSON_UNESCAPED_SLASHES),
        ':course_section' => $courseSection,
        ':user_id' => (int) $user['user_id'],
    ]);

    $userStmt = $pdo->prepare(
        'SELECT user_id, email, display_name, major, project_idea, teammates, course_section, created_at
         FROM users
         WHERE user_id = :user_id
         LIMIT 1'
    );
    $userStmt->execute([':user_id' => (int) $user['user_id']]);
    $updatedUser = $userStmt->fetch();

    if (!is_array($updatedUser)) {
        capstone_json_response(['error' => 'Profile was saved but could not be loaded'], 500);
    }

    capstone_json_response([
        'success' => true,
        'user' => capstone_public_user_profile($updatedUser),
    ]);
} catch (PDOException $exception) {
    capstone_json_response([
        'error' => 'Failed to update profile',
        'details' => $exception->getMessage(),
    ], 500);
}
