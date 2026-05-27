# Dify Integration

Dify Cloud (https://cloud.dify.ai) provides the LLM orchestration, retrieval-augmented generation, and conversation memory for Capstone GPT. The PHP middleware never calls an OpenAI or Anthropic API directly — it forwards every query to Dify, which handles model selection, knowledge retrieval, and response generation.

## Workflow

The Chat App workflow has four nodes:

```
USER INPUT  →  KNOWLEDGE RETRIEVAL  →  LLM  →  ANSWER
```

| Node | Purpose |
|------|---------|
| **USER INPUT** | Receives the composed query (profile context + topic prefix + user message) from the PHP middleware |
| **KNOWLEDGE RETRIEVAL** | Searches the CSE449 Spring 2026 knowledge base for relevant chunks |
| **LLM** | Generates the response using `gpt-4o-mini`, with system prompt and retrieved context |
| **ANSWER** | Returns the LLM output to the caller |

The workflow is published. Updates require re-publishing in the Dify Studio UI.

## Knowledge Base

The knowledge base is named **CSE449 Spring 2026 Materials** and contains four documents, all with status `Available` and indexing complete:

| Document | Words | Chunks |
|----------|-------|--------|
| CSE449 Spring 2026 Syllabus.pdf | 24.5k | 33 |
| CSE Capstone Instructional Design v2 - Assignments Map | 39.4k | 136 |
| CSE Agile Practices Lookup - FULL CATALOG | 45.9k | 144 |
| SR. Design FAQ and Recommendations | 8.5k | 14 |

Chunking mode: General (1024 chars per chunk, ~5k tokens estimated for embeddings).

## System Prompt

The LLM node's SYSTEM prompt establishes the assistant's role and constraints:

> You are Capstone GPT, a specialized AI assistant for Senior Design students. Answer using the course materials provided in the knowledge base.
>
> Your role:
> - Help students navigate their capstone projects using the actual course materials, syllabus, agile practices catalog, and assignment expectations.
> - Give specific, actionable advice grounded in the course structure — not generic answers.
> - Help with: project ideation, working agreements, sprint planning, backlog grooming, technical standards essays, retrospectives, expo video preparation, and reflective essays.
> - When a student asks about a course requirement or deliverable, reference the specific assignment or agile practice from the course materials.
>
> Rules:
> - Be concise and direct. Students are busy.
> - When referencing agile practices, cite the specific practice name from the course catalog.
> - When referencing deliverables, mention which phase they belong to (Kickoff/Initiation, Early Planning, Recurring Sprints, Semester Closing).
> - If a student seems stuck or lost, proactively suggest the next most valuable thing they should work on based on where they likely are in the semester.
> - Never fabricate course policies. If you are unsure about a specific policy, say so and suggest that the student confirm with the course instructor.
> - Do not write complete assignments for students. Guide them, give examples, and help them think through the work.

## Query Composition

When a request reaches `chat_handler.php`, the final query sent to Dify is composed in this order:

1. **Profile block** (if authenticated):
   ```
   [Student Profile]
   Display name: <name>
   Course section: CSE449
   Major: <major>
   Project idea: <idea>
   Teammates: <comma-separated list>
   ```
2. **Topic prefix** (if a topic was selected): `[Topic: <topic name>]`
3. **The student's actual question**

The composed payload also includes the Dify `user` field (set to the database user ID, or `test_student` if unauthenticated) and the prior `conversation_id` if continuing a thread.

## Configuration

The Dify API key is stored in `api/config.local.php` and never reaches the browser:

```php
return [
    'dify' => [
        'api_url' => 'https://api.dify.ai/v1',
        'api_key' => 'YOUR_DIFY_API_KEY_HERE',
    ],
    // ...
];
```

To rotate the key:
1. In Dify, go to the Chat App > API Access
2. Generate a new key, copy it
3. Update `config.local.php` on the server
4. The middleware picks it up on the next request (no restart needed)

## Updating Course Materials

To refresh the knowledge base when new course materials are published:
1. Open the Dify knowledge base CSE449 Spring 2026 Materials
2. Add or replace the relevant document
3. Wait for embedding to complete (usually 1-3 minutes)
4. Verify in Retrieval Testing that queries surface the new content
5. No code changes required

## Model Choice

Currently using `gpt-4o-mini` for cost efficiency. The model can be swapped in the LLM node settings without any code or schema change. Trade-offs:
- `gpt-4o-mini` — fast, cheap, good enough for advisory content
- `gpt-4o` — higher quality on nuanced policy questions, ~10x cost
- `claude-sonnet` (via Dify) — strong on writing-quality feedback (working agreements, essays)

## Failure Modes and Handling

| Failure | Detection | User-facing behavior |
|---------|-----------|----------------------|
| Dify unreachable | curl error | 502 with `error: "Failed to reach Dify API"` |
| Dify returns non-200 | `httpCode !== 200` | 502 with `error: "Dify API error"`, includes details |
| Knowledge base returns no chunks | LLM still answers from system prompt alone | Response is more generic; not currently flagged to user |
| LLM exceeds timeout (60s) | curl timeout | 502 |
