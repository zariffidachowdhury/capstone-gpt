# Capstone GPT Demo Script

## Goal
Deliver a 3-5 minute live walkthrough that shows the project as both a working student-facing prototype and a professionally managed capstone system.

## Demo Order
1. Open `public/index.html`.
2. Open `public/admin.html` in a second tab.
3. Keep `docs/project-charter.md`, `docs/system-architecture.md`, and `docs/professor-update.md` ready for quick reference.

## Live Demo Flow
### 1. Set the frame
- Say: “This is a localhost prototype for Capstone GPT. The browser uses vanilla JS, the middleware is PHP, Dify stays server-side, and every interaction is logged in MySQL.”
- Point to the polished student interface and history sidebar.

### 2. Show transparency and scope
- Expand the “About this assistant” section.
- Explain that it covers GitLab, Agile process, sprint planning, ABET outcomes, professionalism, and general course support.
- Mention that it is powered through Dify using course-facing materials and that it is still a prototype that may make mistakes.

### 3. Run a live student prompt
- Select `Sprint Planning` from the topic menu.
- Use this example prompt:
  - `What should a capstone team accomplish during sprint planning?`
- Show that the response appears in the chat and becomes part of conversation history.

### 4. Show the feedback loop
- On the AI response, click `Thumbs up` or `Thumbs down`.
- Call out that each response is tied to a `log_id`, so feedback can be attached to a specific answer rather than a generic session.
- Note that the UI prevents accidental duplicate submissions during the session.

### 5. Switch to the admin view
- Open `public/admin.html`.
- Point out:
  - total questions
  - conversation count
  - average questions per conversation
  - top topic and latest active topic
  - feedback rate and thumbs up/down totals
  - recent prompts and any negatively rated items

### 6. Show exportability
- Click the `Export CSV` button.
- Explain that this gives a faculty-reviewable record of timestamps, user, topic, conversation, prompt, and response.

## Backup Prompts
- `How should a capstone team structure a GitLab workflow?`
- `What are ABET outcomes and why do they matter for senior design?`
- `What belongs in an executive summary for a capstone report?`

## Closing Line
“At this point the prototype is no longer just a connected scaffold. It now supports student interaction, logging, response-level feedback, professor-facing analytics, exportable data, and a documented roadmap for the next milestones.”
