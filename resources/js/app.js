import './bootstrap';

const normalizePortfolioText = (value) => {
    const lines = value
        .replace(/\r\n?/g, '\n')
        .replace(/\u00a0/g, ' ')
        .replace(/[\u200b-\u200d\ufeff]/g, '')
        .split('\n');
    const normalized = [];

    for (const sourceLine of lines) {
        let line = sourceLine
            .replace(/\t/g, ' ')
            .trim()
            .replace(/ {2,}/g, ' ')
            .replace(/\s+([,.;:!?])/g, '$1')
            .replace(/^[•●▪◦‣⁃*–—-]\s*/, '- ')
            .replace(/^\(?(\d+)[.)]\s*/, '$1. ');

        if (line === '' && normalized.at(-1) === '') {
            continue;
        }

        normalized.push(line);
    }

    return normalized.join('\n').trim();
};

const formatSelectedLines = (textarea, type) => {
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const lineStart = textarea.value.lastIndexOf('\n', Math.max(0, start - 1)) + 1;
    const nextBreak = textarea.value.indexOf('\n', end);
    const lineEnd = nextBreak === -1 ? textarea.value.length : nextBreak;
    const selected = textarea.value.slice(lineStart, lineEnd);
    const lines = selected.split('\n').map((line, index) => {
        const content = line.replace(/^\s*(?:[-•●▪◦‣⁃*–—]|\(?\d+[.)])\s*/, '').trim();
        if (content === '') return '';
        return type === 'number' ? `${index + 1}. ${content}` : `- ${content}`;
    });
    const replacement = lines.join('\n');

    textarea.setRangeText(replacement, lineStart, lineEnd, 'select');
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    textarea.focus();
};

const enhancePortfolioWriting = () => {
    document.querySelectorAll('[data-portfolio-writing-assistant] textarea').forEach((textarea) => {
        if (textarea.dataset.writingAssistantReady) return;
        textarea.dataset.writingAssistantReady = 'true';
        textarea.value = normalizePortfolioText(textarea.value);

        const toolbar = document.createElement('div');
        toolbar.className = 'flex flex-wrap items-center gap-1 rounded-t-xl border border-b-0 border-slate-200 bg-slate-50 px-2 py-1.5';
        toolbar.setAttribute('role', 'toolbar');
        toolbar.setAttribute('aria-label', 'Alat format penulisan');

        const actions = [
            { label: '•', title: 'Jadikan daftar bullet', action: () => formatSelectedLines(textarea, 'bullet') },
            { label: '1.', title: 'Jadikan daftar bernomor', action: () => formatSelectedLines(textarea, 'number') },
            {
                label: 'Rapikan',
                title: 'Rapikan spasi, baris kosong, bullet, dan penomoran',
                action: () => {
                    textarea.value = normalizePortfolioText(textarea.value);
                    textarea.dispatchEvent(new Event('input', { bubbles: true }));
                    textarea.focus();
                },
            },
        ];

        actions.forEach(({ label, title, action }) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'inline-flex min-h-8 min-w-8 items-center justify-center rounded-md px-2 text-xs font-bold text-slate-700 hover:bg-white hover:text-cyan-800 focus:outline-none focus:ring-2 focus:ring-cyan-500';
            button.textContent = label;
            button.title = title;
            button.setAttribute('aria-label', title);
            button.addEventListener('click', action);
            toolbar.appendChild(button);
        });

        textarea.before(toolbar);
        textarea.classList.add('rounded-t-none');
        textarea.addEventListener('paste', () => {
            window.setTimeout(() => {
                textarea.value = normalizePortfolioText(textarea.value);
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
            }, 0);
        });
    });

    document.querySelectorAll('[data-portfolio-writing-assistant] form').forEach((form) => {
        form.addEventListener('submit', () => {
            form.querySelectorAll('textarea').forEach((textarea) => {
                textarea.value = normalizePortfolioText(textarea.value);
            });
        });
    });
};

document.addEventListener('DOMContentLoaded', enhancePortfolioWriting);
