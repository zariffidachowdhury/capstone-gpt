# Tech Stack

## Summary

| Layer | Technology | Version |
|-------|-----------|---------|
| Backend language | PHP | 8.x |
| Database | MySQL | 8.x |
| AI orchestration | Dify Cloud | n/a (managed) |
| Frontend markup | HTML5 | n/a |
| Frontend styling | Tailwind CSS | Play CDN |
| Frontend scripting | Vanilla JavaScript (ES2020+) | n/a |
| Fonts | Source Serif 4, Space Grotesk | Google Fonts |
| Version control | Git, GitLab (Miami CSI instance) | n/a |
| CLI tooling | glab, mysql client, php CLI | n/a |

## Why These Choices

### PHP 8.x
PHP runs natively on macOS, Linux, and most shared hosting. The language is stable, the standard library has everything needed for an HTTP middleware (PDO, curl, password_hash, random_bytes), and the deployment story is simpler than a Node or Python service. PHP 8 strict types are used in every API file (`declare(strict_types=1)`).

### MySQL
Required by the original project scope and well-supported on Miami's infrastructure. The schema is small (five tables) and uses InnoDB defaults with foreign keys for referential integrity.

### Dify Cloud
Selected over hand-rolled prompt engineering because:
- It provides a no-code workflow editor (USER INPUT → Knowledge Retrieval → LLM → ANSWER).
- It handles document chunking, embedding, and vector search out of the box.
- It exposes a single chat-messages HTTP endpoint that returns conversation IDs for multi-turn memory.
- The LLM model can be swapped (currently gpt-4o-mini) without code changes.

### Vanilla JavaScript and Tailwind Play CDN
The product is small enough that a build toolchain (Webpack, Vite, Next.js, etc.) would add more friction than value. Tailwind via CDN keeps `index.html`, `login.html`, `profile.html`, and `admin.html` self-contained. Code is split across `app.js` (chat) and `admin.js` (dashboard).

## Setting Up an IDE

Any editor works. Recommended setup:

### VS Code
Install these extensions:
- **PHP Intelephense** (bmewburn.vscode-intelephense-client) — type checking, completion, go-to-definition.
- **Tailwind CSS IntelliSense** (bradlc.vscode-tailwindcss) — class name completion in HTML.
- **MySQL** (cweijan.vscode-mysql-client2) — query the local database from inside the editor.
- **GitLens** (eamodio.gitlens) — inline blame and history.

### PhpStorm
Works out of the box for PHP. Add the Tailwind plugin and a database tool window pointed at `localhost:3306`.

## Required Local Tooling

- `php >= 8.0` with the `pdo_mysql` and `curl` extensions enabled (both ship with PHP 8 by default on macOS Homebrew installs).
- `mysql` server >= 8.0 running on `127.0.0.1:3306`.
- `git` for local version control.

## Build and Bundling

There is no build step. The frontend ships static files directly. PHP serves itself.
