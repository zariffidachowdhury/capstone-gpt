# Capstone GPT Working Agreement

## Purpose
This working agreement defines how the project will be developed so the prototype grows with clear ownership, traceability, and presentation-ready discipline.

## Issue Discipline
- Every meaningful feature, fix, or documentation task should begin as an issue.
- While no Git remote is configured, tasks should be captured in `docs/ISSUE_BACKLOG.md` so they are ready for later GitLab import.
- Issues should include a short goal, why it matters, and a definition of done.

## Branch Naming
- Preferred format: `codex/<short-feature-name>` or `issue-<number>-<short-name>` when a remote workflow is available.
- For local-only work, branch names should still describe the sprint or feature focus.

## Commit Expectations
- Use small, meaningful commits grouped by outcome rather than by file type.
- Commit messages should clearly describe the user-visible or project-visible change.
- Documentation, backend, and frontend work may be committed separately when that improves traceability.

## Review Expectations
- Treat reviews as a check for behavior, clarity, and presentation readiness, not only syntax correctness.
- Before marking work complete, verify changed PHP files with `php -l` and perform at least one focused manual or curl-based endpoint check when feasible.
- Capture known gaps explicitly instead of leaving them implicit.

## Documentation Expectations
- Keep project-level docs current enough that an instructor or teammate can understand the prototype without a code walkthrough.
- Diagrams, testing notes, mock usage content, and backlog artifacts are project deliverables, not optional extras.
- When a feature changes the demo narrative, update the relevant presentation or status document in the same sprint.

## Team Norms
- Keep the API key server-side only.
- Preserve the modular CAS scaffold even while using `test_student`.
- Prefer simple, testable changes that can be demoed locally.
- Write with the assumption that the repo may be shown directly during evaluation.
