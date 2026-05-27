# Capstone GPT Project Charter

## Objective
Build a course-ready AI assistant that helps senior capstone students ask better questions, understand course processes, and access guidance through a safe local web interface backed by Dify, PHP, and MySQL.

## Users
- Senior capstone students who need fast answers about deliverables, teamwork, Agile process, GitLab workflow, and professional expectations.
- Faculty and project supervisors who need visibility into how the assistant is being used and what support students seek most often.

## Problem Statement
Capstone students often rely on static course materials, scattered notes, and repeated instructor clarification for recurring process questions. That makes it harder for students to find help quickly and harder for faculty to see where confusion is happening. Capstone GPT addresses that gap with a guided assistant that centralizes responses and records usage for later review.

## Scope
- Student-facing chat interface optimized for desktop and mobile.
- PHP middleware that keeps the Dify API key server-side.
- MySQL logging for each prompt/response pair.
- Topic-aware routing so responses can be guided by the selected course topic.
- Conversation history and basic admin analytics for instructor review.
- Project documentation and workflow artifacts that support a professional capstone process.

## Non-Goals
- Replacing the official LMS, CAS, or university authentication stack in this prototype phase.
- Building a production-scale multi-user deployment.
- Implementing advanced role-based admin permissions before the core assistant and analytics are stable.
- Over-engineering the frontend with build tooling or framework dependencies.

## Success Criteria
- A student can ask a question from a phone or laptop and receive a response through the local prototype.
- The Dify API key remains server-side and never appears in frontend code.
- Every successful interaction is logged to MySQL with user, topic, timestamp, and conversation metadata.
- The project includes a presentation-ready set of technical and process documentation.
- Faculty can review usage trends and recent prompts through a simple local analytics view.

## Milestones
1. Establish end-to-end prototype scaffold: frontend, PHP middleware, Dify integration, and DB logging.
2. Improve demo readiness: new chat flow, suggested questions, retry handling, topic-aware routing, and conversation history.
3. Add faculty insight: stats endpoint, admin dashboard, and backlog for future export/reporting work.
4. Strengthen engineering discipline: project charter, architecture docs, testing strategy, working agreement, and issue backlog.
5. Prepare course presentation assets and a repeatable demo script.
