# Frontend Guide

## Page Inventory

| File | Purpose |
|------|---------|
| `public/login.html` | Sign-in and signup forms (entry point) |
| `public/profile.html` | Edit student profile (major, project idea, teammates, course section) |
| `public/index.html` | Main chat interface |
| `public/admin.html` | Instructor analytics dashboard |
| `public/app.js` | All chat-page interactivity (history, feedback, sending, suggested prompts, retry) |
| `public/admin.js` | Dashboard data fetching and rendering |

There is no shared JS module system. `login.html` and `profile.html` contain inline `<script>` blocks because they are short. `index.html` and `admin.html` load the corresponding external `.js` file.

## Design System

The visual style is defined as CSS custom properties at the top of each HTML file:

```css
:root {
    --capstone-ink: #1f2937;
    --capstone-muted: #6b7280;
    --capstone-wine: #8b2331;
    --capstone-wine-dark: #631723;
    --capstone-cream: #f8f1eb;
    --capstone-sand: #eadfd4;
    --capstone-slate: #e5e7eb;
    --capstone-surface: rgba(255, 255, 255, 0.9);
}
```

Typography:
- **Headings**: Source Serif 4
- **Body**: Space Grotesk

Backgrounds use layered radial gradients in cream/wine tones for warmth without losing readability.

Cards and surfaces use:
- Border radius `[28px]` to `[32px]` for soft, modern look
- Backdrop blur for layered glassy panels
- Stone-colored borders at low opacity

## Authentication Flow

1. `login.html` loads. JavaScript checks `localStorage` for a session token.
2. If a token exists, calls `GET /api/auth.php?action=me`. On 200, redirects to `index.html`. On 401, clears localStorage and stays on the page.
3. User submits the login or signup form. On success, the token is saved to `localStorage` under `capstoneSessionToken`, the profile is cached under `capstoneUserProfile`, and the page redirects to `index.html`.
4. Every authenticated page sends `Authorization: Bearer <token>` with API requests.

`localStorage` keys:
- `capstoneSessionToken` — the bearer token
- `capstoneUserProfile` — JSON cache of the user object

## Chat Page (`index.html` + `app.js`)

Layout regions:
- **Sidebar** (`#history-sidebar`) — conversation history
- **Header** (`<header>`) — title, focus topic dropdown, new chat button, link to admin
- **Transparency panel** (`#about-panel`) — collapsible about section
- **Messages region** (`#chat-messages`) — chat transcript
- **Input footer** (`#chat-form`) — query input and send button

State (in `app.js`):
- `conversationId` — current Dify conversation
- `activeConversationKey` — which sidebar entry is highlighted
- `isSending` — disables input while waiting for a response
- `submittedFeedback` — Map of log_id → rating
- `currentUser` — cached profile

Message rendering uses a small custom Markdown subset implemented in `formatMessageHtml()` covering headings (h1-h3), bold, italic, inline code, links, ordered and unordered lists, and paragraphs.

## Admin Dashboard (`admin.html` + `admin.js`)

Dashboard sections:
- **Top stats row** — six summary tiles (total questions, top topic, conversation count, average depth, feedback rate, latest active topic)
- **Topic distribution** — bar visualization of questions per topic
- **Daily activity** — last seven days
- **Recent prompts** — list of the most recent user queries
- **Most active conversation** — featured card
- **Feedback summary** — aggregate counts and helpful breakdown
- **Recent low-rated prompts** — items that received thumbs-down

The page polls `GET /api/stats.php` once on load. There is no auto-refresh.

## Profile Page (`profile.html`)

Form fields:
- Display name (read-only, sourced from auth)
- Email (read-only)
- Major (text input)
- Course section (radio: CSE 448 or CSE 449)
- Project idea (textarea)
- Teammates (textarea, comma or newline separated)

Save sends `PUT /api/profile.php`. On success, the page updates the cached profile in `localStorage` and shows a success toast.

## Accessibility

The frontend was built with WCAG 2.2 success criteria in mind. See the Technical Standards Essay for specifics on which criteria are addressed. Key practices:

- Semantic HTML5 (`<header>`, `<main>`, `<aside>`, `<section>`, `<article>`)
- `lang="en"` on `<html>`
- Form `<label>` elements connected via `for` to controls
- `aria-label` on icon-only buttons (sidebar toggle)
- `aria-expanded` and `aria-controls` on the about-panel disclosure
- `aria-hidden` on overlays when not visible
- Sufficient contrast (stone-900 on near-white, wine-on-cream)
- Native keyboard accessibility throughout (no custom focus traps)
