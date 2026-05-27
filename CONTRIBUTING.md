# Contributing

Thanks for taking a look at Capstone GPT. This repository is primarily a portfolio artifact, but small fixes and documentation improvements are welcome.

## Local Setup

1. Fork or clone the repository.
2. Copy `api/config.local.example.php` to `api/config.local.php`.
3. Add your own local Dify and MySQL settings. Never commit real secrets.
4. Run the SQL migrations in order from the README.
5. Start the PHP development server with `php -S localhost:8080`.

## Contribution Guidelines

- Keep API keys, credentials, student records, chat exports, and screenshots with private data out of commits.
- Prefer small pull requests with a clear description of the behavior changed.
- Run syntax checks before opening a pull request:

```bash
find api -name '*.php' -print0 | xargs -0 -n1 php -l
node --check public/app.js
node --check public/admin.js
```

## Security Reports

If you notice a security issue, please do not open a public issue with sensitive details. Email `zariffidachowdhury@gmail.com` with a short description and the affected file or workflow.
