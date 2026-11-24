/**
 * Our own confirmation window for every `wire:confirm`, instead of the browser's
 * "127.0.0.1:8000 says" pop-up.
 *
 * A click on an element with wire:confirm is stopped before Livewire sees it and the
 * window opens. On "Delete"/"Remove"/… the click is replayed with the browser's
 * confirm() answering yes, so Livewire runs the action exactly as before.
 */
const DANGER = ['delete', 'remove', 'clear', 'cancel', 'deactivate', 'reset'];

const css = `
.ajc-back{position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(17,24,39,.55);opacity:0;transition:opacity .15s}
.ajc-back.ajc-on{opacity:1}
.ajc-box{width:100%;max-width:420px;background:#fff;border-radius:16px;box-shadow:0 20px 50px rgba(0,0,0,.25);padding:24px;font-family:inherit;transform:translateY(8px) scale(.98);transition:transform .15s}
.ajc-on .ajc-box{transform:none}
.ajc-head{display:flex;gap:14px;align-items:flex-start}
.ajc-icon{flex:none;width:40px;height:40px;border-radius:999px;display:flex;align-items:center;justify-content:center}
.ajc-icon.ajc-red{background:#fef2f2;color:#dc2626}
.ajc-icon.ajc-blue{background:#eff6ff;color:#2563eb}
.ajc-title{margin:0;font-size:16px;font-weight:700;color:#111827;line-height:1.35}
.ajc-text{margin:6px 0 0;font-size:14px;color:#4b5563;line-height:1.5}
.ajc-btns{display:flex;justify-content:flex-end;gap:10px;margin-top:22px}
.ajc-btn{border:0;border-radius:10px;padding:10px 18px;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit}
.ajc-btn:focus-visible{outline:2px solid #111827;outline-offset:2px}
.ajc-no{background:#f3f4f6;color:#374151}.ajc-no:hover{background:#e5e7eb}
.ajc-yes.ajc-red{background:#dc2626;color:#fff}.ajc-yes.ajc-red:hover{background:#b91c1c}
.ajc-yes.ajc-blue{background:#111827;color:#fff}.ajc-yes.ajc-blue:hover{background:#374151}
@media (prefers-reduced-motion:reduce){.ajc-back,.ajc-box{transition:none}}
`;

const ICON_WARN = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>';
const ICON_ASK = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01"/></svg>';

function esc(s) {
    return s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

/** "Remove "Card" from the list? Records … changed." → title + text + button words */
function parse(message) {
    const m = message.replaceAll('\\n', '\n').trim() || 'Are you sure?';
    const cut = m.indexOf('?');
    const title = cut > 0 ? m.slice(0, cut + 1) : m;
    const text = cut > 0 ? m.slice(cut + 1).trim() : '';
    const verb = (title.match(/^[A-Za-z]+/) || [''])[0];
    const danger = DANGER.includes(verb.toLowerCase());
    let yes = verb && verb.length < 14 ? verb : 'Continue';
    let no = 'Cancel';
    if (verb.toLowerCase() === 'cancel') { yes = 'Yes, cancel'; no = 'Keep it'; }
    if (verb.toLowerCase() === 'mark') yes = 'Yes, mark it';
    // Kiswahili website: plain yes / no, danger from the Swahili verbs
    if ((document.documentElement.lang || '').startsWith('sw')) {
        const sw = ['futa', 'ondoa', 'sitisha', 'ghairi', 'safisha'].includes(verb.toLowerCase());
        return { title, text, danger: danger || sw, yes: 'Ndiyo', no: 'Hapana' };
    }
    return { title, text, danger, yes, no };
}

function ask(message) {
    return new Promise((resolve) => {
        const { title, text, danger, yes, no } = parse(message);
        const tone = danger ? 'ajc-red' : 'ajc-blue';
        const back = document.createElement('div');
        back.className = 'ajc-back';
        back.innerHTML = `
            <div class="ajc-box" role="alertdialog" aria-modal="true" aria-labelledby="ajc-t" ${text ? 'aria-describedby="ajc-d"' : ''}>
                <div class="ajc-head">
                    <div class="ajc-icon ${tone}">${danger ? ICON_WARN : ICON_ASK}</div>
                    <div><p class="ajc-title" id="ajc-t">${esc(title)}</p>${text ? `<p class="ajc-text" id="ajc-d">${esc(text)}</p>` : ''}</div>
                </div>
                <div class="ajc-btns">
                    <button type="button" class="ajc-btn ajc-no">${esc(no)}</button>
                    <button type="button" class="ajc-btn ajc-yes ${tone}">${esc(yes)}</button>
                </div>
            </div>`;
        const before = document.activeElement;
        const done = (answer) => {
            document.removeEventListener('keydown', onKey, true);
            back.classList.remove('ajc-on');
            setTimeout(() => back.remove(), 150);
            if (before && before.focus) before.focus({ preventScroll: true });
            resolve(answer);
        };
        const onKey = (e) => {
            if (e.key === 'Escape') { e.preventDefault(); done(false); }
            if (e.key === 'Tab') { // keep focus inside the window
                const b = back.querySelectorAll('button');
                if (e.shiftKey && document.activeElement === b[0]) { e.preventDefault(); b[1].focus(); }
                else if (! e.shiftKey && document.activeElement === b[1]) { e.preventDefault(); b[0].focus(); }
            }
        };
        back.querySelector('.ajc-no').onclick = () => done(false);
        back.querySelector('.ajc-yes').onclick = () => done(true);
        back.addEventListener('mousedown', (e) => { if (e.target === back) done(false); });
        document.addEventListener('keydown', onKey, true);
        document.body.appendChild(back);
        requestAnimationFrame(() => back.classList.add('ajc-on'));
        // safe choice first for dangerous actions
        (danger ? back.querySelector('.ajc-no') : back.querySelector('.ajc-yes')).focus();
    });
}

const style = document.createElement('style');
style.textContent = css;
document.head.appendChild(style);

document.addEventListener('click', (e) => {
    const el = e.target.closest && e.target.closest('[wire\\:confirm]');
    if (! el || el.__ajcPass || el.hasAttribute('wire:confirm.prompt')) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    ask(el.getAttribute('wire:confirm') || '').then((ok) => {
        if (! ok || ! el.isConnected) return;
        const browserConfirm = window.confirm;
        window.confirm = () => true;   // Livewire's own check now says yes
        el.__ajcPass = true;
        try { el.click(); } finally { el.__ajcPass = false; window.confirm = browserConfirm; }
    });
}, true);

window.ajConfirm = ask;
