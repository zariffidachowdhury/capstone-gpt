/**
 * Capstone GPT — Frontend JavaScript
 * Handles chat interactions, history, trust messaging, and response feedback.
 */

const API_BASE = `${window.location.origin}/api`;
const API_URL = `${API_BASE}/chat_handler.php`;
const CONVERSATIONS_URL = `${API_BASE}/conversations.php`;
const FEEDBACK_URL = `${API_BASE}/feedback.php`;
const AUTH_URL = `${API_BASE}/auth.php`;
const SESSION_TOKEN_KEY = 'capstoneSessionToken';
const USER_PROFILE_KEY = 'capstoneUserProfile';
const SUGGESTED_PROMPTS = [
    'I have no project idea yet — can you suggest some based on current CS trends?',
    'Help me draft a working agreement for my capstone team',
    'What should my sprint planning look like for the first two weeks?',
    'How do I pick technical standards for the Technical Standards Essay?'
];

let conversationId = '';
let activeConversationKey = null;
let isSending = false;
let conversationsCache = [];
let currentUser = null;
const submittedFeedback = new Map();

const form = document.getElementById('chat-form');
const input = document.getElementById('query-input');
const sendBtn = document.getElementById('send-btn');
const messagesContainer = document.getElementById('messages-container');
const chatMessages = document.getElementById('chat-messages');
const topicSelect = document.getElementById('topic-select');
const historyList = document.getElementById('history-list');
const newChatBtn = document.getElementById('new-chat-btn');
const statusPill = document.getElementById('status-pill');
const sidebarToggle = document.getElementById('sidebar-toggle');
const closeSidebarBtn = document.getElementById('close-sidebar-btn');
const sidebar = document.getElementById('history-sidebar');
const sidebarOverlay = document.getElementById('sidebar-overlay');
const aboutToggleBtn = document.getElementById('about-toggle-btn');
const aboutPanel = document.getElementById('about-panel');
const aboutToggleLabel = document.getElementById('about-toggle-label');
const activeUserName = document.getElementById('active-user-name');
const activeUserDetail = document.getElementById('active-user-detail');
const logoutBtn = document.getElementById('logout-btn');

function getSessionToken() {
    return localStorage.getItem(SESSION_TOKEN_KEY) || '';
}

function redirectToLogin() {
    window.location.replace('login.html');
}

function clearSession() {
    localStorage.removeItem(SESSION_TOKEN_KEY);
    localStorage.removeItem(USER_PROFILE_KEY);
    currentUser = null;
}

function updateHeaderUser(user) {
    currentUser = user;

    if (activeUserName) {
        activeUserName.textContent = user?.display_name || 'Signed-in student';
    }

    if (activeUserDetail) {
        const section = user?.course_section || 'CSE449';
        const major = user?.major ? ` · ${user.major}` : '';
        activeUserDetail.textContent = `${section}${major}`;
    }
}

async function apiFetch(url, options = {}) {
    const headers = new Headers(options.headers || {});
    const token = getSessionToken();

    if (options.body && !headers.has('Content-Type')) {
        headers.set('Content-Type', 'application/json');
    }

    if (token) {
        headers.set('Authorization', `Bearer ${token}`);
    }

    const response = await fetch(url, {
        ...options,
        headers,
    });

    if (response.status === 401) {
        clearSession();
        redirectToLogin();
        throw new Error('Session expired. Please sign in again.');
    }

    return response;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

function applyInlineMarkdown(text) {
    let html = escapeHtml(text);

    html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
    html = html.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
    html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, '$1<em>$2</em>');

    return html;
}

function renderMarkdownBlock(block) {
    const trimmedBlock = block.trim();
    if (!trimmedBlock) {
        return '';
    }

    const lines = trimmedBlock.split('\n').map((line) => line.trimEnd());

    if (lines.length === 1) {
        const headingMatch = lines[0].match(/^(#{1,3})\s+(.*)$/);
        if (headingMatch) {
            const level = headingMatch[1].length;
            return `<h${level}>${applyInlineMarkdown(headingMatch[2])}</h${level}>`;
        }
    }

    const orderedItems = [];
    let orderedListOnly = true;
    for (const line of lines) {
        const match = line.match(/^\s*(\d+)\.\s+(.*)$/);
        if (!match) {
            orderedListOnly = false;
            break;
        }
        orderedItems.push(match[2]);
    }
    if (orderedListOnly && orderedItems.length > 0) {
        return `<ol>${orderedItems.map((item) => `<li>${applyInlineMarkdown(item)}</li>`).join('')}</ol>`;
    }

    const unorderedItems = [];
    let unorderedListOnly = true;
    for (const line of lines) {
        const match = line.match(/^\s*[-*+]\s+(.*)$/);
        if (!match) {
            unorderedListOnly = false;
            break;
        }
        unorderedItems.push(match[1]);
    }
    if (unorderedListOnly && unorderedItems.length > 0) {
        return `<ul>${unorderedItems.map((item) => `<li>${applyInlineMarkdown(item)}</li>`).join('')}</ul>`;
    }

    return `<p>${applyInlineMarkdown(lines.join('<br>'))}</p>`;
}

function formatMessageHtml(text, isUser = false) {
    if (isUser) {
        return escapeHtml(text).replace(/\n/g, '<br>');
    }

    const normalized = (text ?? '').replace(/\r\n/g, '\n').trim();
    if (!normalized) {
        return '';
    }

    return normalized
        .split(/\n\s*\n/)
        .map(renderMarkdownBlock)
        .filter(Boolean)
        .join('');
}

function formatTimestamp(timestamp) {
    return new Date(timestamp).toLocaleString([], {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function setStatus(message, tone = 'neutral') {
    const toneClasses = {
        neutral: 'bg-stone-100 text-stone-600',
        success: 'bg-emerald-100 text-emerald-700',
        warning: 'bg-amber-100 text-amber-800',
        danger: 'bg-rose-100 text-rose-700',
    };

    statusPill.className = `inline-flex rounded-full px-3 py-1 text-xs font-medium ${toneClasses[tone] || toneClasses.neutral}`;
    statusPill.textContent = message;
}

function setSendingState(nextState) {
    isSending = nextState;
    sendBtn.disabled = nextState;
    input.disabled = nextState;

    if (nextState) {
        sendBtn.textContent = 'Sending...';
        setStatus('Waiting for assistant response', 'warning');
    } else {
        sendBtn.textContent = 'Send Message';
        input.disabled = false;
    }
}

function getFeedbackMarkup(logId, selectedRating) {
    if (!logId) {
        return '';
    }

    const storedRating = submittedFeedback.get(logId) || selectedRating || '';
    const isLocked = storedRating !== '';
    const upClasses = storedRating === 'up'
        ? 'border-emerald-300 bg-emerald-100 text-emerald-800'
        : 'border-stone-300 bg-white text-stone-600 hover:border-stone-400 hover:text-stone-900';
    const downClasses = storedRating === 'down'
        ? 'border-rose-300 bg-rose-100 text-rose-800'
        : 'border-stone-300 bg-white text-stone-600 hover:border-stone-400 hover:text-stone-900';
    const feedbackCopy = isLocked
        ? `Feedback saved: ${storedRating === 'up' ? 'Thumbs up' : 'Thumbs down'}`
        : 'Was this response helpful for your capstone work?';

    return `
        <div class="mt-4 border-t border-stone-200 pt-3">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs uppercase tracking-[0.22em] text-stone-400" data-feedback-label="${logId}">${feedbackCopy}</p>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="feedback-btn inline-flex items-center justify-center rounded-full border px-3 py-1.5 text-xs font-medium transition ${upClasses} ${isLocked ? 'cursor-not-allowed opacity-80' : ''}"
                        data-log-id="${logId}"
                        data-rating="up"
                        ${isLocked ? 'disabled' : ''}
                    >
                        Thumbs up
                    </button>
                    <button
                        type="button"
                        class="feedback-btn inline-flex items-center justify-center rounded-full border px-3 py-1.5 text-xs font-medium transition ${downClasses} ${isLocked ? 'cursor-not-allowed opacity-80' : ''}"
                        data-log-id="${logId}"
                        data-rating="down"
                        ${isLocked ? 'disabled' : ''}
                    >
                        Thumbs down
                    </button>
                </div>
            </div>
        </div>
    `;
}

function addMessage(text, isUser, options = {}) {
    const { logId = null, feedbackRating = '' } = options;

    if (logId && feedbackRating && !submittedFeedback.has(logId)) {
        submittedFeedback.set(logId, feedbackRating);
    }

    const wrapper = document.createElement('div');
    wrapper.className = `message-enter flex ${isUser ? 'justify-end' : 'justify-start'}`;

    const bubble = document.createElement('div');
    bubble.className = isUser
        ? 'max-w-[85%] rounded-[24px] bg-[var(--capstone-wine)] px-4 py-3 text-white shadow-lg shadow-[rgba(139,35,49,0.18)] sm:max-w-[78%]'
        : 'max-w-[85%] rounded-[24px] border border-stone-200 bg-white px-4 py-3 text-stone-800 shadow-sm sm:max-w-[78%]';
    bubble.dataset.messageRole = isUser ? 'user' : 'assistant';

    const feedbackMarkup = !isUser ? getFeedbackMarkup(logId, feedbackRating) : '';
    bubble.innerHTML = `
        <div class="text-sm leading-7 ${isUser ? 'text-white' : 'assistant-markdown text-stone-800'}">${formatMessageHtml(text, isUser)}</div>
        ${feedbackMarkup}
    `;

    wrapper.appendChild(bubble);
    messagesContainer.appendChild(wrapper);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function addErrorMessage(message, prompt) {
    const wrapper = document.createElement('div');
    wrapper.className = 'message-enter flex justify-start';
    wrapper.dataset.errorCard = 'true';

    wrapper.innerHTML = `
        <div class="max-w-[85%] rounded-[24px] border border-rose-200 bg-rose-50 px-4 py-4 text-rose-900 shadow-sm sm:max-w-[78%]">
            <p class="text-sm font-semibold">Message failed to send</p>
            <p class="mt-1 text-sm leading-6 text-rose-800">${formatMessageHtml(message)}</p>
            <button
                type="button"
                class="retry-btn mt-3 inline-flex rounded-full bg-rose-700 px-4 py-2 text-xs font-medium text-white transition hover:bg-rose-800"
                data-prompt="${encodeURIComponent(prompt)}"
            >
                Retry this message
            </button>
        </div>
    `;

    messagesContainer.appendChild(wrapper);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function addTypingIndicator() {
    const wrapper = document.createElement('div');
    wrapper.className = 'flex justify-start';
    wrapper.id = 'typing-indicator';

    wrapper.innerHTML = `
        <div class="rounded-[24px] border border-stone-200 bg-white px-4 py-4 shadow-sm">
            <div class="flex items-center gap-2">
                <div class="h-2.5 w-2.5 animate-bounce rounded-full bg-stone-400" style="animation-delay: 0ms"></div>
                <div class="h-2.5 w-2.5 animate-bounce rounded-full bg-stone-400" style="animation-delay: 150ms"></div>
                <div class="h-2.5 w-2.5 animate-bounce rounded-full bg-stone-400" style="animation-delay: 300ms"></div>
            </div>
        </div>
    `;

    messagesContainer.appendChild(wrapper);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function removeTypingIndicator() {
    const indicator = document.getElementById('typing-indicator');
    if (indicator) {
        indicator.remove();
    }
}

function renderWelcomeState() {
    messagesContainer.innerHTML = `
        <div class="message-enter rounded-[28px] border border-stone-200 bg-[linear-gradient(135deg,rgba(255,255,255,0.96),rgba(248,241,235,0.96))] px-5 py-5 shadow-sm">
            <div class="max-w-3xl">
                <p class="text-xs uppercase tracking-[0.32em] text-stone-500">Welcome State</p>
                <h3 class="mt-2 text-3xl font-semibold text-stone-950">Ask for Senior Design help, process coaching, or course guidance.</h3>
                <p class="mt-3 text-sm leading-7 text-stone-600 sm:text-base">
                    Capstone GPT is tuned for CSE 448/449 questions about project ideas, agile practice, technical standards, ABET outcomes, expo prep, and the course instructor's course expectations.
                </p>
                <div class="mt-5">
                    <p class="text-sm font-medium text-stone-800">Suggested starters</p>
                    <div class="mt-3 flex flex-wrap gap-3">
                        ${SUGGESTED_PROMPTS.map((prompt) => `
                            <button
                                type="button"
                                class="suggested-chip rounded-full border border-stone-300 bg-white px-4 py-2 text-sm text-stone-700 transition hover:border-[var(--capstone-wine)] hover:text-[var(--capstone-wine)]"
                                data-prompt="${escapeHtml(prompt)}"
                            >
                                ${escapeHtml(prompt)}
                            </button>
                        `).join('')}
                    </div>
                </div>
            </div>
        </div>
    `;
    setStatus('Ready for a new message', 'neutral');
}

function resetChat() {
    conversationId = '';
    activeConversationKey = null;
    messagesContainer.innerHTML = '';
    renderWelcomeState();
    highlightActiveConversation();
    input.focus();
}

function openSidebar() {
    sidebar.classList.remove('-translate-x-full');
    sidebarOverlay.classList.remove('hidden');
}

function closeSidebar() {
    sidebar.classList.add('-translate-x-full');
    sidebarOverlay.classList.add('hidden');
}

function toggleAboutPanel() {
    const isHidden = aboutPanel.classList.contains('hidden');
    aboutPanel.classList.toggle('hidden', !isHidden);
    aboutToggleBtn.setAttribute('aria-expanded', String(isHidden));
    aboutToggleLabel.textContent = isHidden ? 'Collapse' : 'Expand';
}

function getConversationKey(conversation) {
    return conversation.conversation_id || conversation.local_key;
}

function highlightActiveConversation() {
    const cards = historyList.querySelectorAll('[data-conversation-key]');
    cards.forEach((card) => {
        const isActive = card.dataset.conversationKey === activeConversationKey;
        card.classList.toggle('border-[var(--capstone-wine)]', isActive);
        card.classList.toggle('bg-[rgba(139,35,49,0.06)]', isActive);
        card.classList.toggle('shadow-sm', isActive);
    });
}

function renderHistoryList(conversations) {
    conversationsCache = conversations;

    if (!conversations.length) {
        historyList.innerHTML = `
            <div class="rounded-2xl border border-dashed border-stone-300 bg-white/70 px-4 py-5 text-sm text-stone-500">
                No saved conversations yet. Start a chat to build the history panel.
            </div>
        `;
        return;
    }

    historyList.innerHTML = conversations.map((conversation) => `
        <button
            type="button"
            class="history-card w-full rounded-2xl border border-stone-200 bg-white px-4 py-4 text-left transition hover:border-stone-300 hover:shadow-sm"
            data-conversation-key="${escapeHtml(getConversationKey(conversation))}"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-stone-900">${escapeHtml(conversation.topic_name || 'General Course Help')}</p>
                    <p class="mt-1 text-sm text-stone-600">${escapeHtml(conversation.preview)}</p>
                </div>
                <span class="rounded-full bg-stone-100 px-2.5 py-1 text-xs text-stone-600">${conversation.message_count}</span>
            </div>
            <p class="mt-3 text-xs uppercase tracking-[0.24em] text-stone-400">${formatTimestamp(conversation.updated_at)}</p>
        </button>
    `).join('');

    highlightActiveConversation();
}

function renderConversation(conversation) {
    messagesContainer.innerHTML = '';

    conversation.messages.forEach((message) => {
        addMessage(message.query, true);
        addMessage(message.response, false, {
            logId: message.log_id,
            feedbackRating: message.feedback_rating || '',
        });
    });

    conversationId = conversation.conversation_id || '';
    activeConversationKey = getConversationKey(conversation);
    topicSelect.value = conversation.topic_id ? String(conversation.topic_id) : '';
    highlightActiveConversation();
    setStatus('Loaded previous conversation', 'success');
    closeSidebar();
}

async function sendMessage(query) {
    const topicId = topicSelect.value || null;
    const body = {
        query,
        conversation_id: conversationId,
    };

    if (topicId) {
        body.topic_id = parseInt(topicId, 10);
    }

    const response = await apiFetch(API_URL, {
        method: 'POST',
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        const err = await response.json().catch(() => ({}));
        throw new Error(err.error || `Server error (${response.status})`);
    }

    return response.json();
}

async function submitFeedback(logId, rating) {
    const response = await apiFetch(FEEDBACK_URL, {
        method: 'POST',
        body: JSON.stringify({
            log_id: logId,
            rating,
        }),
    });

    if (!response.ok) {
        const err = await response.json().catch(() => ({}));
        throw new Error(err.error || `Server error (${response.status})`);
    }

    return response.json();
}

async function loadConversations() {
    try {
        const response = await apiFetch(CONVERSATIONS_URL);
        if (!response.ok) {
            throw new Error('Unable to load history');
        }

        const data = await response.json();
        renderHistoryList(data.conversations || []);
    } catch (error) {
        conversationsCache = [];
        historyList.innerHTML = `
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-5 text-sm text-amber-900">
                Conversation history is unavailable right now.
            </div>
        `;
    }
}

async function loadCurrentUser() {
    const cached = localStorage.getItem(USER_PROFILE_KEY);
    if (cached) {
        try {
            updateHeaderUser(JSON.parse(cached));
        } catch (error) {
            localStorage.removeItem(USER_PROFILE_KEY);
        }
    }

    const response = await apiFetch(`${AUTH_URL}?action=me`);
    if (!response.ok) {
        throw new Error('Unable to load profile');
    }

    const data = await response.json();
    if (data.user) {
        localStorage.setItem(USER_PROFILE_KEY, JSON.stringify(data.user));
        updateHeaderUser(data.user);
    }
}

async function handleLogout() {
    if (logoutBtn) {
        logoutBtn.disabled = true;
        logoutBtn.textContent = 'Logging out...';
    }

    try {
        await apiFetch(`${AUTH_URL}?action=logout`, { method: 'POST' });
    } catch (error) {
        // A failed logout call should not keep the local browser signed in.
    }

    clearSession();
    redirectToLogin();
}

function getFriendlyErrorMessage(error) {
    const message = error instanceof Error ? error.message : 'Unknown error';

    if (message.includes('Failed to fetch')) {
        return 'The chat service could not be reached. Make sure the PHP server is running on localhost:8080.';
    }

    if (message.includes('Failed to reach Dify API')) {
        return 'The local middleware is running, but the Dify API could not be reached. You can retry this prompt once the upstream service is available.';
    }

    return message;
}

function lockFeedbackButtons(logId, rating) {
    submittedFeedback.set(logId, rating);

    const feedbackButtons = messagesContainer.querySelectorAll(`.feedback-btn[data-log-id="${logId}"]`);
    feedbackButtons.forEach((button) => {
        const isSelected = button.dataset.rating === rating;
        button.disabled = true;
        button.classList.add('cursor-not-allowed', 'opacity-80');
        button.classList.remove('hover:border-stone-400', 'hover:text-stone-900');

        if (isSelected) {
            button.classList.remove('border-stone-300', 'bg-white', 'text-stone-600');
            if (rating === 'up') {
                button.classList.add('border-emerald-300', 'bg-emerald-100', 'text-emerald-800');
            } else {
                button.classList.add('border-rose-300', 'bg-rose-100', 'text-rose-800');
            }
        }
    });

    const label = messagesContainer.querySelector(`[data-feedback-label="${logId}"]`);
    if (label) {
        label.textContent = `Feedback saved: ${rating === 'up' ? 'Thumbs up' : 'Thumbs down'}`;
    }
}

async function handleSend(query, options = {}) {
    if (!query || isSending) {
        return;
    }

    const { skipUserMessage = false } = options;

    if (!skipUserMessage) {
        addMessage(query, true);
    }

    input.value = '';
    setSendingState(true);
    addTypingIndicator();

    try {
        const data = await sendMessage(query);
        removeTypingIndicator();
        addMessage(data.answer, false, { logId: data.log_id });

        if (data.conversation_id) {
            conversationId = data.conversation_id;
            activeConversationKey = data.conversation_id;
        }

        setStatus('Assistant response received', 'success');
        await loadConversations();
    } catch (error) {
        removeTypingIndicator();
        addErrorMessage(getFriendlyErrorMessage(error), query);
        setStatus('Message failed, ready to retry', 'danger');
    } finally {
        setSendingState(false);
        input.focus();
    }
}

form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const query = input.value.trim();
    if (!query) {
        return;
    }

    await handleSend(query);
});

messagesContainer.addEventListener('click', async (event) => {
    const target = event.target;

    if (!(target instanceof HTMLElement)) {
        return;
    }

    if (target.classList.contains('suggested-chip')) {
        const prompt = target.dataset.prompt || '';
        await handleSend(prompt);
        return;
    }

    if (target.classList.contains('retry-btn')) {
        const prompt = decodeURIComponent(target.dataset.prompt || '');
        const errorCard = target.closest('[data-error-card="true"]');
        if (errorCard) {
            errorCard.remove();
        }
        await handleSend(prompt, { skipUserMessage: true });
        return;
    }

    if (target.classList.contains('feedback-btn')) {
        const logId = parseInt(target.dataset.logId || '0', 10);
        const rating = target.dataset.rating || '';

        if (!logId || submittedFeedback.has(logId)) {
            return;
        }

        try {
            target.textContent = 'Saving...';
            const result = await submitFeedback(logId, rating);
            lockFeedbackButtons(logId, result.rating);
            setStatus('Feedback captured for analytics', 'success');
        } catch (error) {
            target.textContent = rating === 'up' ? 'Thumbs up' : 'Thumbs down';
            setStatus('Feedback could not be saved right now', 'danger');
        }
    }
});

historyList.addEventListener('click', (event) => {
    const target = event.target;

    if (!(target instanceof HTMLElement)) {
        return;
    }

    const card = target.closest('[data-conversation-key]');
    if (!(card instanceof HTMLElement)) {
        return;
    }

    const selected = conversationsCache.find((conversation) => getConversationKey(conversation) === card.dataset.conversationKey);
    if (selected) {
        renderConversation(selected);
    } else {
        setStatus('Conversation history could not be loaded', 'danger');
    }
});

newChatBtn.addEventListener('click', resetChat);
sidebarToggle.addEventListener('click', openSidebar);
closeSidebarBtn.addEventListener('click', closeSidebar);
sidebarOverlay.addEventListener('click', closeSidebar);
aboutToggleBtn.addEventListener('click', toggleAboutPanel);
logoutBtn.addEventListener('click', handleLogout);

async function initializeApp() {
    if (!getSessionToken()) {
        redirectToLogin();
        return;
    }

    renderWelcomeState();

    try {
        await loadCurrentUser();
        await loadConversations();
    } catch (error) {
        setStatus(getFriendlyErrorMessage(error), 'danger');
    }
}

initializeApp();
