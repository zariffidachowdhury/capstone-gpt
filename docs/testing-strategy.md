# Capstone GPT Testing Strategy

## Goal
Use a pragmatic testing approach that fits a lightweight PHP and vanilla JS prototype while still demonstrating a test-driven engineering mindset.

## Testing Philosophy
- Start with the behavior we want to prove, then implement the smallest change that satisfies it.
- Favor fast feedback loops that can be repeated during a live sprint.
- Use documentation and repeatable commands to make manual and command-line testing auditable.

## Current Verification Layers
### 1. PHP syntax checks
- Run `php -l` against every changed PHP file before closing a sprint.
- This is the fastest way to prevent obvious parse errors in middleware and endpoint code.

### 2. Curl-based endpoint checks
- Use `curl` against localhost endpoints to confirm JSON structure, status codes, and failure handling.
- Priority checks:
  - `POST /api/chat_handler.php`
  - `GET /api/conversations.php`
  - `GET /api/stats.php`

### 3. Manual UI verification
- Open the chat UI in a browser and confirm:
  - new chat resets the interface
  - suggested prompts send correctly
  - failed sends produce a retry affordance
  - conversation history loads and restores a previous thread
  - admin page renders stats cleanly on desktop and mobile widths

## Suggested TDD Loop for This Repo
1. Define the next expected behavior in a note or issue acceptance criteria.
2. Run the smallest verification that would currently fail or remain unproven.
3. Implement the code change.
4. Re-run the verification.
5. Record any environment blockers and move on without hiding them.

## Near-Term Improvements
- Add a lightweight script or checklist for repeated curl smoke tests.
- Add fixture data or seeded chat logs to make analytics verification easier.
- Introduce simple regression coverage for backend response shapes once the project stabilizes.

## Definition of Done
- Code compiles or passes syntax checks.
- The targeted user workflow has been manually exercised.
- Any unverified areas are documented explicitly.
