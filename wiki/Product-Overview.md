# Product Overview

## Purpose

Capstone GPT is an AI-powered assistant designed specifically for students enrolled in CSE 448 (Senior Design I) and CSE 449 (Senior Design II) at Miami University. It addresses a recurring problem in capstone education: students often struggle with course-specific deliverables (working agreements, sprint planning, technical standards essays, retrospectives, expo preparation) and turn to general-purpose AI tools that produce generic, off-target answers.

By grounding the AI in the actual course materials — the syllabus, agile practices catalog, assignments map, and Senior Design FAQ — Capstone GPT delivers responses that reflect the course instructor's expectations and the structure of the course rather than generic capstone advice.

## Target Users

| Role | What they get |
|------|---------------|
| **Student** | Course-aware chat assistant, personalized to their major, project idea, teammates, and course section. Topic-routed answers grounded in actual course documents. |
| **Instructor** | Analytics dashboard showing question volume, topic distribution, conversation depth, feedback rates, and exportable interaction logs in CSV. |

## Key Features

- **Login and profile system** — Students sign up with an email and password, then complete a profile (major, project idea, teammates, course section). Profile data is sent to the AI as context so responses are personalized.
- **Topic-aware chat** — Ten CSE 448/449-specific topic categories (Project Ideas, Working Agreement, Sprint Planning, Technical Standards, Agile Practices, GitLab, Expo Prep, Retrospectives, ABET Outcomes, General). Selecting a topic prepends contextual framing to the query before sending it to the AI.
- **Knowledge-grounded AI** — Dify retrieval pulls relevant chunks from uploaded course materials before the LLM generates a response.
- **Conversation history** — Past chats are grouped per student and re-loadable.
- **Per-response feedback** — Students rate individual answers (thumbs up or down) tied to a specific log entry, not to a session.
- **Instructor dashboard** — Aggregated analytics (total questions, top topic, conversation count, average depth, feedback rate, daily activity, recent prompts, low-rated prompts, most active conversation).
- **CSV export** — Faculty-reviewable export of all logged interactions with timestamps, user identifiers, topics, and full prompt-response pairs.
- **Server-side AI key** — The Dify API key is stored only in PHP middleware. The browser never sees it.

## Constraints

- Currently runs only on a local development server (`localhost:8080`). No production deployment is configured.
- Authentication uses a self-contained email-and-password flow rather than Miami CAS. CAS integration is planned future work.
- The product is single-user per session; team features (shared workspaces, joint backlogs) are not yet implemented.
- The AI's accuracy depends on the quality and freshness of the documents loaded into the Dify knowledge base.

## Non-Goals

- Capstone GPT is not a replacement for the course instructor's guidance, instructor meetings, or formal course deliverables.
- It does not auto-submit assignments to Canvas.
- It does not enforce academic integrity policy beyond a visible "prototype" disclosure.
