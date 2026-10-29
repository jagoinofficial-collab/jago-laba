(() => {
    let busy = false;
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-expense-detail]');
        if (button) {
            const detail = document.getElementById(button.dataset.expenseDetail);
            detail.hidden = !detail.hidden;
            button.setAttribute('aria-expanded', String(!detail.hidden));
        }
        if (event.target.closest('#refresh-expenses')) refresh();
    });
    async function refresh() {
        if (busy || document.hidden) return;
        busy = true;
        const status = document.getElementById('expense-refresh-status');
        status.textContent = 'Memperbarui…';
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 15000);
        try {
            const response = await fetch(location.href, {credentials: 'same-origin', cache: 'no-store', signal: controller.signal});
            if (!response.ok || response.redirected) throw new Error('Sesi atau koneksi bermasalah');
            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const next = doc.getElementById('global-expenses');
            if (!next) throw new Error('Data tidak tersedia');
            const current = document.getElementById('global-expenses');
            current.querySelectorAll('[data-expense-detail][aria-expanded="true"]').forEach(button => {
                const id = button.dataset.expenseDetail;
                const detail = next.querySelector('#' + id);
                if (detail) {
                    detail.hidden = false;
                    next.querySelector('[data-expense-detail="' + id + '"]').setAttribute('aria-expanded', 'true');
                }
            });
            // Keep keyboard focus stable while the table is refreshed.
            const active = current.contains(document.activeElement) ? document.activeElement : null;
            const focusedDetail = active?.dataset.expenseDetail;
            const focusedRefresh = active?.id === 'refresh-expenses';
            current.replaceWith(next);
            if (focusedDetail) next.querySelector('[data-expense-detail="' + focusedDetail + '"]')?.focus();
            else if (focusedRefresh) next.querySelector('#refresh-expenses').focus();
            next.querySelector('#expense-refresh-status').textContent = 'Database diperbarui ' + new Intl.DateTimeFormat('id-ID', {timeZone: 'Asia/Jakarta', hour: '2-digit', minute: '2-digit', second: '2-digit'}).format(new Date()) + ' WIB';
        } catch (error) {
            status.textContent = 'Pembaruan gagal. Data terakhir tetap ditampilkan.';
        } finally {
            clearTimeout(timeout);
            busy = false;
        }
    }
    setInterval(refresh, 30000);
})();
