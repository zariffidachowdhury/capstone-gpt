# Deployment

## Current State

Capstone GPT runs only on local developer machines using PHP's built-in development server (`php -S`). There is no production deployment, no staging environment, and no CI/CD pipeline. This is intentional for the Spring 2026 prototype scope, where the focus was on building a working, course-aware product rather than productionizing infrastructure.

This page documents both how to run the current local setup and a recommended path to production.

## Local Development

Documented in detail on the [Getting Started](Getting-Started) page. Summary:

```bash
# 1. Configure
cp api/config.php api/config.local.php  # then fill in real values

# 2. Set up the database
mysql -u root < sql/001_schema.sql
mysql -u root capstone_gpt < sql/002_feedback.sql
mysql -u root capstone_gpt < sql/003_users.sql
mysql -u root capstone_gpt < sql/004_sessions.sql
mysql -u root capstone_gpt < sql/005_topics_update.sql

# 3. Run
php -S localhost:8080
```

The PHP built-in server is single-threaded and not suitable for production traffic.

## Recommended Production Path

A reasonable production deployment for a Miami-hosted version would look like:

### Option A: Miami-hosted shared environment

| Layer | Choice |
|-------|--------|
| Web server | Apache 2.4 with `mod_php` (or Nginx + php-fpm) |
| PHP | 8.2+ |
| Database | MySQL 8 on a shared institutional server |
| Domain | A subdomain under `miamioh.edu` (requires IT approval) |
| TLS | Provisioned by Miami IT or via Let's Encrypt if self-managed |
| Authentication | Switch from email/password to Miami CAS using phpCAS |

### Option B: Cloud VPS

| Layer | Choice |
|-------|--------|
| Host | DigitalOcean, Linode, or AWS Lightsail |
| OS | Ubuntu 22.04 LTS |
| Web server | Nginx + php-fpm |
| Database | Managed MySQL (DigitalOcean Managed Database, RDS, etc.) |
| Domain | Custom domain with DNS pointing to the VPS |
| TLS | Let's Encrypt via certbot |
| Backups | Daily snapshots of the database |
| Monitoring | UptimeRobot or similar for endpoint health |

## Configuration for Production

Several values must change before deploying:

### `api/bootstrap.php`
- **CORS** — Currently `Access-Control-Allow-Origin: *`. Restrict to the production domain.
- **Cookie or token storage** — Tokens currently live in `localStorage`. For higher security, switch to httpOnly cookies with the `Secure` and `SameSite=Strict` flags.

### `api/config.local.php`
- **Dify key** — Use a separate production Dify app key, not the development key.
- **Database password** — Use a strong password (current default is empty for local convenience).

### Web server configuration
- **Document root** — Should point to `public/` only. The `api/` and `sql/` directories must not be web-accessible.
- **Rewrite rules** — Optional. Currently `app.js` uses `${window.location.origin}/api/...` which works as long as `api/` is accessible at the same origin.
- **Disable directory listing** — Standard production hygiene.

### Database
- Run the migrations in numeric order on the production database.
- Create a dedicated MySQL user for the app with only the privileges it needs (SELECT, INSERT, UPDATE, DELETE on the `capstone_gpt` database — not GRANT, not DROP).

## Suggested First-Pass `.gitlab-ci.yml`

```yaml
stages:
  - lint

lint-php:
  stage: lint
  image: php:8.2-cli
  script:
    - for file in api/*.php; do php -l "$file"; done

lint-js:
  stage: lint
  image: node:20-alpine
  script:
    - node --check public/app.js
    - node --check public/admin.js
```

This catches syntax errors on every push without requiring database setup in CI.

## Backup Strategy

For any production deployment, daily logical backups of the database are the minimum:

```bash
mysqldump -u backup_user -p capstone_gpt > /backups/capstone_gpt_$(date +%F).sql
```

Combined with a 30-day retention policy.

## Rollback

The product has no migration tooling beyond the numbered SQL files. Adding a new column to an existing table is therefore safe (additive). Renaming or dropping columns is risky and should be done in two steps:

1. Add the new column, dual-write from the application, deploy.
2. Backfill the new column from the old, deploy a release that reads from the new column.
3. Drop the old column in a later release.

## Currently Out of Scope

- Container packaging (Docker, Kubernetes)
- Load balancing or horizontal scaling
- Multi-region failover
- Centralized logging (the app uses PHP's `error_log`)
- APM or distributed tracing
