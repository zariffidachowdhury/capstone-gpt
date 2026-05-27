# Constraints and Future Work

## Known Constraints

### Authentication
- Current auth is email + password, self-contained in the app. There is no Miami SSO integration. CAS (with Duo MFA on top) is the natural target for institutional rollout, requires registering the app with Miami IT, and would replace `auth.php` with phpCAS-based session handling.
- Passwords are bcrypt-hashed via `password_hash()` with `PASSWORD_DEFAULT`, but there is no rate limiting on login attempts and no account lockout policy.

### Single-User Per Session
- Each student has their own profile, history, and feedback. There is no concept of a team workspace, shared backlog, or joint project state. Capstone is a team activity by design, so multi-user team features are the highest-value missing capability.

### No Production Deployment
- The system runs on `php -S` on developer laptops. It has not been hardened for multi-user concurrent traffic. See [Deployment](Deployment) for the path to production.

### No Automated Tests
- All testing is manual or curl-based. See [Testing](Testing) for the gaps.

### Knowledge Base Freshness
- The Dify knowledge base contains the Spring 2026 versions of the syllabus, agile catalog, assignments map, and FAQ. If the course instructor revises any of these, the documents must be re-uploaded to Dify manually. There is no automation watching for changes.

### AI Output Quality
- Responses depend on `gpt-4o-mini` and the retrieval quality. Occasional hallucinations or off-topic answers are possible. The thumbs-down feedback mechanism is currently the only signal collected; there is no human review loop.

### Single Browser Per User
- The session token lives in `localStorage`. Logging in on a second browser issues a second session token; both remain valid until expiry. This is fine for development but a real product would want session listing and revocation.

## Future Work

### Tier 1: High-Value, Low-Risk
1. **Semester timeline and deadline calendar** — Visual timeline of CSE 448/449 phases, current week highlighted, upcoming deliverables surfaced, ICS export.
2. **Deliverable checklist** — A "Capstone Tracker" page listing all required deliverables with status, AI-help button, and due date.
3. **Document upload + AI review** — Student uploads a draft working agreement, charter, or technical standards essay; AI reviews against the course rubric.
4. **Team workspace** — Multi-user teams with shared chat history, joint backlog, and combined project context.

### Tier 2: Higher Value, More Complex
5. **CAS authentication** — Replace email/password with Miami SSO using phpCAS.
6. **Canvas integration** — Pull a student's CSE 448/449 assignments and due dates via a personal access token; AI uses this for context.
7. **Sprint planning assistant** — Guided wizard that walks a team through sprint planning, with output saved as a sprint plan and exportable to GitLab issues.
8. **Retrospective facilitator mode** — Built-in templates (Heartbeat, Start/Stop/Continue, 4 Ls); AI prompts the team and captures action items.

### Tier 3: Stretch
9. **GitLab activity awareness** — Pull a team's commits, MRs, and issues; AI references them in suggestions.
10. **Per-team instructor dashboard** — Cohort-level analytics, deliverable submission rates, common questions, weak-area heat map.
11. **Project idea generator** — For students with no idea, a guided ideation flow that produces 5-10 tailored project ideas with mock charters.
12. **Weekly auto-summary email** — Sunday digest of accomplishments, due items, and next-sprint focus.

### Engineering Debt
- Add a PHPUnit test suite for the middleware helpers (`capstone_profile_context`, `capstone_normalize_teammates`, etc.).
- Add Playwright or Cypress smoke tests for the critical user paths (signup → chat → feedback).
- Configure GitLab CI to run lint on push and integration tests on merge requests.
- Add structured logging (JSON to stdout) instead of `error_log` for production observability.
- Move the design tokens from inline `<style>` blocks into a shared CSS file loaded by all pages.

## Roadmap Summary

A pragmatic next-semester roadmap, in priority order:

1. Semester timeline and deliverable checklist (1-2 weeks)
2. Document upload + AI review (1 week)
3. CAS authentication (2 weeks; pending Miami IT app registration)
4. Team workspaces (2-3 weeks)
5. Canvas integration via personal access token (1-2 weeks)
6. Production deployment to a Miami-approved host (1 week of infra work)
