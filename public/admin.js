const STATS_URL = `${window.location.origin}/api/stats.php`;
const EXPORT_URL = `${window.location.origin}/api/export.php?format=csv`;
const SESSION_TOKEN_KEY = 'capstoneSessionToken';

const statusPanel = document.getElementById('stats-status');
const totalQuestionsEl = document.getElementById('total-questions');
const topTopicEl = document.getElementById('top-topic');
const conversationCountEl = document.getElementById('conversation-count');
const averageDepthEl = document.getElementById('average-depth');
const feedbackRateEl = document.getElementById('feedback-rate');
const thumbsUpCountEl = document.getElementById('thumbs-up-count');
const thumbsDownCountEl = document.getElementById('thumbs-down-count');
const latestActivityEl = document.getElementById('latest-activity');
const latestActiveTopicEl = document.getElementById('latest-active-topic');
const topicBarsEl = document.getElementById('topic-bars');
const dailyBarsEl = document.getElementById('daily-bars');
const recentPromptsEl = document.getElementById('recent-prompts');
const mostActiveCardEl = document.getElementById('most-active-card');
const feedbackSummaryEl = document.getElementById('feedback-summary');
const negativeFeedbackListEl = document.getElementById('negative-feedback-list');
const exportCsvBtn = document.getElementById('export-csv-btn');

function getSessionToken() {
    return localStorage.getItem(SESSION_TOKEN_KEY) || '';
}

async function apiFetch(url, options = {}) {
    const headers = new Headers(options.headers || {});
    const token = getSessionToken();

    if (token) {
        headers.set('Authorization', `Bearer ${token}`);
    }

    return fetch(url, {
        ...options,
        headers,
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

function formatTimestamp(timestamp) {
    if (!timestamp) {
        return 'No data';
    }

    return new Date(timestamp).toLocaleString([], {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function renderTopicBars(items) {
    if (!items.length) {
        topicBarsEl.innerHTML = '<p class="text-sm text-stone-500">No topic activity has been logged yet.</p>';
        return;
    }

    const maxValue = Math.max(...items.map((item) => item.question_count), 1);

    topicBarsEl.innerHTML = items.map((item) => {
        const width = Math.max((item.question_count / maxValue) * 100, 8);
        return `
            <div>
                <div class="mb-2 flex items-center justify-between gap-3 text-sm">
                    <span class="font-medium text-stone-800">${escapeHtml(item.topic_name)}</span>
                    <span class="text-stone-500">${item.question_count}</span>
                </div>
                <div class="h-3 rounded-full bg-stone-100">
                    <div class="h-3 rounded-full bg-[#8b2331]" style="width: ${width}%"></div>
                </div>
            </div>
        `;
    }).join('');
}

function renderDailyBars(items) {
    if (!items.length) {
        dailyBarsEl.innerHTML = '<p class="col-span-7 text-sm text-stone-500">No recent activity has been logged yet.</p>';
        return;
    }

    const maxValue = Math.max(...items.map((item) => item.question_count), 1);

    dailyBarsEl.innerHTML = items.map((item) => {
        const height = Math.max((item.question_count / maxValue) * 160, 18);
        const label = new Date(item.date).toLocaleDateString([], { weekday: 'short' });

        return `
            <div class="flex flex-col items-center justify-end gap-3">
                <span class="text-xs font-medium text-stone-500">${item.question_count}</span>
                <div class="flex h-44 w-full items-end rounded-t-[22px] bg-stone-100 p-2">
                    <div class="w-full rounded-[18px] bg-[#1f2937]" style="height: ${height}px"></div>
                </div>
                <span class="text-xs uppercase tracking-[0.24em] text-stone-400">${label}</span>
            </div>
        `;
    }).join('');
}

function renderRecentPrompts(items) {
    if (!items.length) {
        recentPromptsEl.innerHTML = '<p class="text-sm text-stone-500">No prompts have been recorded yet.</p>';
        return;
    }

    recentPromptsEl.innerHTML = items.map((item) => `
        <article class="rounded-[22px] border border-stone-200 bg-white px-4 py-4">
            <div class="flex items-center justify-between gap-3">
                <span class="rounded-full bg-stone-100 px-3 py-1 text-xs font-medium text-stone-600">${escapeHtml(item.topic_name)}</span>
                <span class="text-xs uppercase tracking-[0.2em] text-stone-400">${formatTimestamp(item.created_at)}</span>
            </div>
            <p class="mt-3 text-sm leading-7 text-stone-800">${escapeHtml(item.user_query)}</p>
            <p class="mt-3 text-xs uppercase tracking-[0.18em] ${item.feedback_rating === 'down' ? 'text-rose-600' : item.feedback_rating === 'up' ? 'text-emerald-700' : 'text-stone-400'}">
                ${item.feedback_rating === 'down' ? 'Recent feedback: Thumbs down' : item.feedback_rating === 'up' ? 'Recent feedback: Thumbs up' : 'No feedback captured yet'}
            </p>
        </article>
    `).join('');
}

function renderMostActiveConversation(item) {
    if (!item) {
        mostActiveCardEl.textContent = 'No conversation data available yet.';
        return;
    }

    mostActiveCardEl.innerHTML = `
        <p class="text-xs uppercase tracking-[0.28em] text-stone-500">Conversation preview</p>
        <p class="mt-3 text-base font-medium text-stone-900">${escapeHtml(item.preview)}</p>
        <p class="mt-4 text-sm text-stone-600">${item.question_count} questions, last active ${formatTimestamp(item.last_activity)}.</p>
    `;
}

function renderFeedbackSummary(summary) {
    if (!summary || !summary.total_feedback) {
        feedbackSummaryEl.innerHTML = `
            <p class="text-sm text-stone-700">No response ratings have been recorded yet.</p>
            <p class="mt-2 text-sm text-stone-500">Once students use the thumbs up/down controls, this panel will show adoption and quality signals.</p>
        `;
        return;
    }

    feedbackSummaryEl.innerHTML = `
        <p class="text-sm font-medium text-stone-900">${summary.total_feedback} total ratings captured</p>
        <p class="mt-2 text-sm text-stone-700">${summary.thumbs_up_count} thumbs up and ${summary.thumbs_down_count} thumbs down are currently stored for this account.</p>
        <p class="mt-2 text-sm text-stone-600">The current feedback rate is ${summary.feedback_rate}% of all logged prompts.</p>
    `;
}

function renderNegativeFeedback(items) {
    if (!items.length) {
        negativeFeedbackListEl.innerHTML = '<p class="text-sm text-stone-500">No low-rated prompts have been captured yet.</p>';
        return;
    }

    negativeFeedbackListEl.innerHTML = items.map((item) => `
        <article class="rounded-[22px] border border-rose-200 bg-rose-50 px-4 py-4">
            <div class="flex items-center justify-between gap-3">
                <span class="rounded-full bg-white px-3 py-1 text-xs font-medium text-rose-700">${escapeHtml(item.topic_name)}</span>
                <span class="text-xs uppercase tracking-[0.2em] text-rose-500">${formatTimestamp(item.feedback_created_at)}</span>
            </div>
            <p class="mt-3 text-sm font-medium text-rose-950">${escapeHtml(item.user_query)}</p>
            <p class="mt-2 text-sm leading-6 text-rose-800">${escapeHtml(item.ai_response)}</p>
        </article>
    `).join('');
}

async function loadStats() {
    try {
        const response = await apiFetch(STATS_URL);
        if (!response.ok) {
            throw new Error('Unable to load stats');
        }

        const data = await response.json();
        const topTopic = (data.questions_per_topic || [])[0];
        const feedbackSummary = data.feedback_summary || {
            thumbs_up_count: 0,
            thumbs_down_count: 0,
            total_feedback: 0,
            feedback_rate: 0,
        };

        totalQuestionsEl.textContent = data.total_questions ?? 0;
        topTopicEl.textContent = topTopic ? topTopic.topic_name : 'No data';
        conversationCountEl.textContent = data.conversation_count ?? 0;
        averageDepthEl.textContent = data.average_questions_per_conversation ?? 0;
        feedbackRateEl.textContent = `${feedbackSummary.feedback_rate ?? 0}%`;
        thumbsUpCountEl.textContent = feedbackSummary.thumbs_up_count ?? 0;
        thumbsDownCountEl.textContent = feedbackSummary.thumbs_down_count ?? 0;
        latestActiveTopicEl.textContent = data.latest_active_topic?.topic_name || 'No data';
        latestActivityEl.textContent = data.latest_active_topic?.created_at
            ? `Last activity ${formatTimestamp(data.latest_active_topic.created_at)}`
            : 'No recent activity';

        renderTopicBars(data.questions_per_topic || []);
        renderDailyBars(data.questions_per_day || []);
        renderRecentPrompts(data.recent_prompts || []);
        renderMostActiveConversation(data.most_active_conversation || null);
        renderFeedbackSummary(feedbackSummary);
        renderNegativeFeedback(data.recent_negative_feedback || []);

        statusPanel.className = 'rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-sm';
        statusPanel.textContent = 'Analytics loaded successfully from /api/stats.php.';
    } catch (error) {
        statusPanel.className = 'rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-sm';
        statusPanel.textContent = 'Analytics could not be loaded. Confirm the PHP server, MySQL database, and feedback migration are all available.';
    }
}

exportCsvBtn.href = EXPORT_URL;
exportCsvBtn.addEventListener('click', async (event) => {
    event.preventDefault();

    try {
        const response = await apiFetch(EXPORT_URL);
        if (!response.ok) {
            throw new Error('Unable to export CSV');
        }

        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `capstone-gpt-export-${new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-')}.csv`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    } catch (error) {
        statusPanel.className = 'rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-sm';
        statusPanel.textContent = 'CSV export could not be generated right now.';
    }
});
loadStats();
