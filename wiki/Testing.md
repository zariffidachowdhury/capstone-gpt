# Testing

The project does not currently use an automated test suite or CI pipeline. Verification is performed through a combination of static checks, manual API testing with curl, and end-to-end smoke testing in the browser. This page documents the testing approach used during development and the procedure a new developer should follow before merging changes.

## Static Checks

Run before every commit:

```bash
# PHP lint
for file in api/*.php; do php -l "$file"; done

# JavaScript syntax
node --check public/app.js
node --check public/admin.js
```

All files should print `No syntax errors detected`.

## Manual API Testing

The following curl sequence verifies the full backend after a fresh database setup. Run with the local server on port 8080.

```bash
# 1. Register a new user
curl -s -X POST http://localhost:8080/api/auth.php?action=register \
  -H 'Content-Type: application/json' \
  -d '{"email":"qa@example.edu","password":"password123","display_name":"QA"}'
# Expect: 201, returns { token, user, expires_at }

# Capture the token for the next calls (replace TOKEN with the returned value)
TOKEN="<paste token here>"

# 2. Verify auth works
curl -s http://localhost:8080/api/auth.php?action=me \
  -H "Authorization: Bearer $TOKEN"
# Expect: 200, returns { user }

# 3. Update the profile
curl -s -X PUT http://localhost:8080/api/profile.php \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"major":"CSE","project_idea":"Test","teammates":["Alice"],"course_section":"CSE449"}'
# Expect: 200, returns updated user

# 4. Send a chat message
curl -s -X POST http://localhost:8080/api/chat_handler.php \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"query":"What goes in a working agreement?","topic_id":3}'
# Expect: 200, returns { answer, conversation_id, log_id }

# 5. Submit feedback (use the log_id from step 4)
curl -s -X POST http://localhost:8080/api/feedback.php \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"log_id":1,"rating":"up"}'
# Expect: 200

# 6. Load conversation history
curl -s http://localhost:8080/api/conversations.php \
  -H "Authorization: Bearer $TOKEN"
# Expect: 200, returns { conversations: [...] }

# 7. Load stats
curl -s http://localhost:8080/api/stats.php
# Expect: 200, returns aggregated dashboard payload

# 8. Export CSV
curl -s "http://localhost:8080/api/export.php?format=csv" -o /tmp/export.csv
head -3 /tmp/export.csv
# Expect: header row plus the test_student or QA log

# 9. Logout
curl -s -X POST http://localhost:8080/api/auth.php?action=logout \
  -H "Authorization: Bearer $TOKEN"
# Expect: 200

# 10. Verify the token is dead
curl -s http://localhost:8080/api/auth.php?action=me \
  -H "Authorization: Bearer $TOKEN"
# Expect: 401
```

## Browser Smoke Test

After backend verification, walk through the UI manually:

1. Navigate to `http://localhost:8080/public/login.html`
2. Sign up with a new email and password
3. Verify the redirect to `index.html`
4. Click into the profile page, fill in major, project idea, teammates, course section
5. Save, confirm the success toast
6. Return to the chat page
7. Select the "Working Agreement" topic
8. Send: "What should be in my working agreement?"
9. Verify a response appears with course-specific content (mentions agile practices, definition of done, etc.)
10. Click thumbs up, confirm the button locks
11. Open `admin.html`, verify the question appears in stats
12. Click Export CSV, confirm the file downloads
13. Click logout, confirm redirect to login

## What Is NOT Tested

Honest assessment of test coverage gaps:

- **No unit tests** for the PHP middleware. Functions like `capstone_profile_context()` and `capstone_normalize_teammates()` would be ideal candidates for PHPUnit but are currently only verified through end-to-end testing.
- **No frontend tests**. `app.js` and `admin.js` have no Jest, Vitest, or Playwright coverage. Manual smoke testing is the only check.
- **No CI pipeline**. GitLab CI is available but not configured. A future improvement would add a `.gitlab-ci.yml` that runs `php -l` and `node --check` on push.
- **No load testing**. The system has only been exercised with a single user at a time.
- **No security testing**. No OWASP Top 10 scan, no dependency audit, no penetration testing has been performed.

## CI/CD

There is no CI/CD pipeline at present. The roadmap (see Constraints and Future Work) includes a recommended `.gitlab-ci.yml` to run lint and basic curl-based integration checks on every merge request.
