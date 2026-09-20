/** Inquiry chat: polling every 3 s, optimistic append, attachments. One instance per .chat element. */

interface Msg { id: number; sender: string; body: string | null; attachment: { name: string; url: string } | null; at: string }

const esc = (s: string) => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c] as string));

function bubble(m: Msg, side: string): string {
    if (m.sender === 'system') return `<div class="text-center text-xs text-slate-400">${esc(m.body ?? '')}</div>`;
    const mine = m.sender === side;
    const att = m.attachment ? `<a href="${m.attachment.url}" class="mt-1 block text-xs underline">📎 ${esc(m.attachment.name)}</a>` : '';
    const time = new Date(m.at).toLocaleTimeString(document.documentElement.lang === 'en' ? 'en-GB' : 'cs-CZ', { hour: '2-digit', minute: '2-digit' });
    return `<div class="flex ${mine ? 'justify-end' : 'justify-start'}"><div class="max-w-[80%] rounded-2xl px-3 py-2 ${mine ? 'bg-teal-600 text-white' : 'bg-slate-100 text-slate-800'}">${m.body ? esc(m.body).replace(/\n/g, '<br>') : ''}${att}<div class="mt-0.5 text-[10px] opacity-70">${time}</div></div></div>`;
}

class Chat {
    private last = 0;
    private log: HTMLElement;
    private side: string;
    private url: string;
    private post: string;
    private inquiry: string;
    private timer = 0;

    constructor(private root: HTMLElement) {
        this.log = root.querySelector('.chat-log') as HTMLElement;
        this.side = root.dataset.side ?? 'customer';
        this.url = root.dataset.url ?? '';
        this.post = root.dataset.post ?? '';
        this.inquiry = root.dataset.inquiry ?? '';
        const form = root.querySelector('.chat-form') as HTMLFormElement;
        const input = root.querySelector('.chat-input') as HTMLInputElement;
        const file = root.querySelector('.chat-file') as HTMLInputElement;
        form.onsubmit = (e) => { e.preventDefault(); this.send(input.value, file.files?.[0] ?? null).then(() => { input.value = ''; file.value = ''; }); };
        file.onchange = () => { if (file.files?.[0]) this.send(input.value, file.files[0]).then(() => { input.value = ''; file.value = ''; }); };
        this.poll();
        document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') this.poll(); });
    }

    private qs(): string { return this.inquiry ? `inquiry=${encodeURIComponent(this.inquiry)}&` : ''; }

    private async poll(): Promise<void> {
        window.clearTimeout(this.timer);
        try {
            const res = await fetch(`${this.url}?${this.qs()}since=${this.last}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (res.ok) {
                const body = await res.json();
                for (const m of body.messages as Msg[]) this.append(m);
            }
        } catch { /* retry on next tick */ }
        this.timer = window.setTimeout(() => this.poll(), document.visibilityState === 'visible' ? 3000 : 15000);
    }

    private append(m: Msg): void {
        if (m.id <= this.last) return;
        this.last = m.id;
        const atBottom = this.log.scrollHeight - this.log.scrollTop - this.log.clientHeight < 40;
        this.log.insertAdjacentHTML('beforeend', bubble(m, this.side));
        if (atBottom || m.sender === this.side) this.log.scrollTop = this.log.scrollHeight;
    }

    private async send(text: string, file: File | null): Promise<void> {
        text = text.trim();
        if (!text && !file) return;
        const form = new FormData();
        form.append('body', text);
        if (file) form.append('file', file, file.name);
        if (this.inquiry) form.append('inquiry', this.inquiry);
        const res = await fetch(this.post, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' }, body: form });
        if (res.ok) { const b = await res.json(); this.append(b.message as Msg); }
        else alert((await res.json().catch(() => ({}))).message ?? 'error');
    }
}

export function bootChat(): void {
    document.querySelectorAll<HTMLElement>('.chat').forEach((el) => new Chat(el));
}
