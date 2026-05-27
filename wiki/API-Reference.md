# API Reference

All endpoints live under `/api/` and return `Content-Type: application/json`. Authenticated endpoints require an `Authorization: Bearer <token>` header where `<token>` is a 64-character hex session token issued by `auth.php?action=login` or `auth.php?action=register`.

## Authentication

### `POST /api/auth.php?action=register`

Create a new account and immediately receive a session token.

**Request body:**
```json
{
  "email": "student@example.edu",
  "password": "at-least-8-chars",
  "display_name": "Zarif"
}
```

**Responses:**
- `201 Created` — account created, returns `{ success, token, expires_at, user }`
- `400 Bad Request` — invalid email format, password under 8 characters, or display name missing
- `409 Conflict` — email already registered

### `POST /api/auth.php?action=login`

Authenticate with email and password.

**Request body:**
```json
{ "email": "student@example.edu", "password": "..." }
```

**Responses:**
- `200 OK` — `{ success, token, expires_at, user }`
- `401 Unauthorized` — wrong email or password (intentionally vague)

### `GET /api/auth.php?action=me`

Return the authenticated user's profile.

**Headers:** `Authorization: Bearer <token>`

**Responses:**
- `200 OK` — `{ success, user }`
- `401 Unauthorized` — token missing, expired, or invalid

### `POST /api/auth.php?action=logout`

Invalidate the current session token.

**Headers:** `Authorization: Bearer <token>`

**Response:** `200 OK` — `{ success: true }`

## Profile

### `PUT /api/profile.php`

Update the authenticated user's profile fields.

**Headers:** `Authorization: Bearer <token>`

**Request body:**
```json
{
  "major": "Computer Science and Engineering",
  "project_idea": "AI assistant for capstone students",
  "teammates": ["Alice", "Bob"],
  "course_section": "CSE449"
}
```

`teammates` accepts an array of strings or a comma-separated string. `course_section` must be `"CSE448"` or `"CSE449"`.

**Responses:**
- `200 OK` — `{ success, user }` with the updated profile
- `400 Bad Request` — invalid `course_section` or oversized field
- `401 Unauthorized` — not logged in

## Chat

### `POST /api/chat_handler.php`

Send a query to the AI and log the exchange.

**Headers:** `Authorization: Bearer <token>` (optional; falls back to `test_student` user if absent)

**Request body:**
```json
{
  "query": "What should be in my working agreement?",
  "topic_id": 3,
  "conversation_id": ""
}
```

- `topic_id` is optional. If provided, the topic name is prepended to the query as `[Topic: <name>]`.
- `conversation_id` is the Dify conversation ID returned in the prior response. Pass an empty string for a new conversation.

**Response (200 OK):**
```json
{
  "answer": "Your working agreement should include...",
  "conversation_id": "abc-123-def-456",
  "topic_id": 3,
  "log_id": 42
}
```

**Errors:**
- `400 Bad Request` — empty query
- `502 Bad Gateway` — Dify API unreachable or returned a non-200

### `GET /api/conversations.php`

Return the authenticated user's conversation history grouped by Dify conversation ID.

**Headers:** `Authorization: Bearer <token>` (falls back to `test_student` if absent)

**Response:** `{ conversations: [...] }` where each conversation has `conversation_id`, `topic_id`, `topic_name`, `preview`, `message_count`, `updated_at`, and `messages` (array of `{ log_id, query, response, feedback_rating }`).

## Feedback

### `POST /api/feedback.php`

Attach a thumbs-up or thumbs-down rating to a specific chat log.

**Request body:**
```json
{ "log_id": 42, "rating": "up" }
```

`rating` must be `"up"` or `"down"`. Each `(log_id, user)` pair can only be rated once.

**Responses:**
- `200 OK` — `{ success: true, rating }`
- `400 Bad Request` — invalid rating or missing log_id
- `404 Not Found` — log_id does not exist

## Analytics

### `GET /api/stats.php`

Return aggregated dashboard data.

**Response:** JSON with `total_questions`, `top_topic`, `conversation_count`, `average_depth`, `feedback_rate`, `thumbs_up_count`, `thumbs_down_count`, `latest_active_topic`, `topic_distribution`, `daily_activity`, `recent_prompts`, `negative_feedback`, `most_active_conversation`.

### `GET /api/export.php?format=csv`

Stream a CSV of all logged interactions and feedback.

**Headers:** `Content-Type: text/csv`, `Content-Disposition: attachment; filename="capstone_gpt_export.csv"`

Columns: `log_id, created_at, cas_uid, topic, conversation_id, prompt, response, feedback_rating, feedback_at`.

## Conventions

- All POST and PUT requests expect `Content-Type: application/json`.
- All endpoints respond to `OPTIONS` for CORS preflight with `204 No Content`.
- Session tokens last 7 days from issuance. Expired tokens return 401.
- Errors always return `{ "error": "<human readable>" }` with the appropriate HTTP status.
