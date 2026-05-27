# Getting Started

This page walks a new developer from zero to a running local instance.

## Prerequisites

- macOS, Linux, or WSL2 on Windows
- PHP 8.0 or higher with `pdo_mysql` and `curl` extensions
- MySQL 8.0 or higher running on `127.0.0.1:3306`
- Git
- A Dify Cloud account with an API key for the configured Chat App

Verify your tooling:

```bash
php --version       # expect PHP 8.x
mysql --version     # expect 8.x
git --version
```

## 1. Clone The Repository

This public portfolio version is packaged as one combined repository with backend, frontend, SQL, and documentation.

```bash
git clone https://github.com/zariffidachowdhury/capstone-gpt.git
cd capstone-gpt
```

## 2. Configure the Backend

Copy the config template and fill in real values:

```bash
cp api/config.local.example.php api/config.local.php
```

Open `api/config.local.php` and set:

```php
return [
    'dify' => [
        'api_url' => 'https://api.dify.ai/v1',
        'api_key' => 'YOUR_DIFY_API_KEY_HERE',
    ],
    'db' => [
        'host'     => '127.0.0.1',
        'dbname'   => 'capstone_gpt',
        'username' => 'root',
        'password' => '',
    ],
];
```

`config.local.php` is gitignored. Never commit a real Dify key.

## 3. Create the Database

Run the SQL migrations in order:

```bash
mysql -u root < sql/001_schema.sql
mysql -u root capstone_gpt < sql/002_feedback.sql
mysql -u root capstone_gpt < sql/003_users.sql
mysql -u root capstone_gpt < sql/004_sessions.sql
mysql -u root capstone_gpt < sql/005_topics_update.sql
```

After this you will have:
- `capstone_gpt` database
- Tables: `topics` (10 CSE 448/449 categories), `chat_logs`, `feedback`, `users`, `sessions`

## 4. Start the Local Server

From the project root:

```bash
php -S localhost:8080
```

You should see:

```
PHP 8.x.x Development Server (http://localhost:8080) started
```

## 5. Open the App

Navigate to:

```
http://localhost:8080/public/login.html
```

Sign up with any email and an 8+ character password. After signup you are redirected to the chat page.

## 6. Test the End-to-End Flow

1. **Login** at `login.html`, complete signup
2. **Profile** — click your name in the header, set major, project idea, teammates, course section
3. **Chat** — pick a topic (e.g. "Working Agreement"), send a message, verify a response appears
4. **Feedback** — click thumbs up or thumbs down on the response
5. **Instructor Dashboard** — open `admin.html`, verify your interaction appears in stats
6. **Export** — click Export CSV, verify the file downloads with your data

## Common Issues

| Problem | Cause | Fix |
|---------|-------|-----|
| `Failed to reach Dify API` | Wrong API key in config.local.php | Re-copy the key from Dify > Plugins > API Access |
| `Database connection failed` | MySQL not running | `mysql.server start` (macOS Homebrew) or `sudo systemctl start mysql` (Linux) |
| `Method not allowed` (405) on profile save | Browser sending wrong HTTP method | Profile updates are PUT requests, not POST. Check `app.js` if customizing |
| Login page redirects in a loop | Stale session token | Open browser devtools, run `localStorage.clear()`, refresh |
