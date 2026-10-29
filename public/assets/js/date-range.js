(() => {
    const dayMs = 86400000;
    const iso = date => date.toISOString().slice(0, 10);
    const parse = value => {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return null;
        const date = new Date(value + 'T00:00:00Z');
        return Number.isFinite(+date) && iso(date) === value ? date : null;
    };
    const shift = (date, days) => new Date(date.getTime() + days * dayMs);
    const month = (date, offset = 0) => new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + offset, 1));
    const fmt = date => new Intl.DateTimeFormat('id-ID', {timeZone:'UTC', day:'numeric', month:'short', year:'numeric'}).format(date);
    const parts = new Intl.DateTimeFormat('en', {timeZone:'Asia/Jakarta', year:'numeric', month:'2-digit', day:'2-digit'}).formatToParts(new Date());
    const part = name => parts.find(p => p.type === name).value;
    const today = parse(`${part('year')}-${part('month')}-${part('day')}`);
    document.querySelectorAll('[data-date-range]').forEach(root => {
        if (root.dataset.ready) return;
        root.dataset.ready = 'true';
        const startInput = root.querySelector('[name=date_start]');
        const endInput = root.querySelector('[name=date_end]');
        const trigger = root.querySelector('.jl-range-trigger');
        const popover = root.querySelector('.jl-range-popover');
        const summary = root.querySelector('[data-range-summary]');
        let start = parse(startInput.value), end = parse(endInput.value);
        let left = month(end || start || today, -1);
        let choosingEnd = false;
        const label = () => { root.querySelector('[data-range-label]').textContent = start && end ? `${fmt(start)} - ${fmt(end)}` : 'Pilih tanggal'; };
        const close = () => { popover.hidden = true; trigger.setAttribute('aria-expanded', 'false'); };
        const apply = () => {
            startInput.value = start ? iso(start) : '';
            endInput.value = end ? iso(end) : '';
            label(); close();
            // Preserve the other filters and reset pagination for a new period.
            const page = root.closest('form')?.querySelector('[name=page]');
            if (page) page.value = '1';
            root.closest('form').requestSubmit();
        };
        function render() {
            const calendars = root.querySelector('[data-calendars]');
            const focusedDay = calendars.contains(document.activeElement) ? document.activeElement.getAttribute('aria-label') : null;
            calendars.replaceChildren();
            for (let panel = 0; panel < 2; panel++) {
                const date = month(left, panel);
                const box = document.createElement('div');
                const header = document.createElement('div'); header.className = 'jl-range-month-header';
                for (const [text, offset, title] of [['«',-12,'Tahun sebelumnya'],['‹',-1,'Bulan sebelumnya'],['',0,''],['›',1,'Bulan berikutnya'],['»',12,'Tahun berikutnya']]) {
                    if (!offset) { const heading = document.createElement('strong'); heading.textContent = new Intl.DateTimeFormat('id-ID', {timeZone:'UTC',month:'long',year:'numeric'}).format(date); header.append(heading); continue; }
                    const nav = document.createElement('button'); nav.type = 'button'; nav.textContent = text; nav.setAttribute('aria-label', title);
                    nav.addEventListener('click', () => { left = month(left, offset); render(); }); header.append(nav);
                }
                box.append(header);
                const grid = document.createElement('div'); grid.className = 'jl-range-grid';
                ['S','S','R','K','J','S','M'].forEach(name => { const cell = document.createElement('span'); cell.textContent = name; grid.append(cell); });
                const offset = (date.getUTCDay() + 6) % 7;
                const days = month(date, 1); days.setUTCDate(0);
                for (let index = 0; index < offset; index++) grid.append(document.createElement('span'));
                for (let day = 1; day <= days.getUTCDate(); day++) {
                    const value = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), day));
                    const button = document.createElement('button'); button.type = 'button'; button.textContent = day; button.setAttribute('aria-label', fmt(value));
                    button.disabled = value > today;
                    if (start && end && value >= start && value <= end) button.classList.add('in-range');
                    const selected = Boolean((start && +value === +start) || (end && +value === +end));
                    if (selected) button.classList.add('endpoint');
                    button.setAttribute('aria-pressed', String(selected));
                    if (+value === +today) button.classList.add('today');
                    button.addEventListener('click', () => {
                        if (!choosingEnd) { start = value; end = null; choosingEnd = true; render(); return; }
                        let from = start, to = value;
                        if (from > to) [from, to] = [to, from];
                        if (Math.round((to - from) / dayMs) + 1 > 45) { summary.textContent = 'Rentang maksimal 45 hari. Pilih tanggal akhir lainnya.'; return; }
                        start = from; end = to; choosingEnd = false; apply();
                    });
                    grid.append(button);
                }
                box.append(grid); calendars.append(box);
            }
            summary.textContent = start && end ? `${fmt(start)} – ${fmt(end)} (${Math.round((end-start)/dayMs)+1} hari)` : start ? `${fmt(start)} – Pilih tanggal akhir` : 'Pilih tanggal mulai dan akhir';
            if (focusedDay) Array.from(calendars.querySelectorAll('button')).find(button => button.getAttribute('aria-label') === focusedDay)?.focus();
        }
        trigger.addEventListener('click', () => {
            if (!popover.hidden) { close(); return; }
            start = parse(startInput.value); end = parse(endInput.value); choosingEnd = false;
            left = month(end || start || today, -1);
            popover.hidden = false; trigger.setAttribute('aria-expanded','true'); render();
            popover.querySelector('button').focus();
        });
        root.querySelectorAll('[data-preset]').forEach(button => button.addEventListener('click', () => {
            const key = button.dataset.preset;
            end = today;
            if (key === 'today') start = today;
            else if (key === 'yesterday') start = end = shift(today, -1);
            else if (key === 'week') start = shift(today, -((today.getUTCDay()+6)%7));
            else if (key === 'month') start = month(today);
            else if (key === 'lastmonth') { start = month(today, -1); end = shift(month(today), -1); }
            else start = shift(today, -(Number(key)-1));
            apply();
        }));
        root.querySelector('[data-range-clear]').addEventListener('click', () => { start = end = null; apply(); });
        root.querySelector('[data-range-close]').addEventListener('click', () => { close(); trigger.focus(); });
        document.addEventListener('click', event => { if (!root.contains(event.target)) close(); });
        root.addEventListener('keydown', event => { if (event.key === 'Escape') { close(); trigger.focus(); } });
        label();
    });
})();
