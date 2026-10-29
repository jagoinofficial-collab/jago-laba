(() => {
    if (window.jlAnalyticsRefreshReady) return;
    window.jlAnalyticsRefreshReady = true;
    let busy = false;
    async function refresh() {
        const current = document.getElementById('global-analytics');
        if (!current || busy || document.hidden) return;
        // Avoid replacing a user's active controls or selected text during background refresh.
        if (current.contains(document.activeElement) && !document.activeElement.matches('[data-refresh-analytics]')) return;
        const selection = window.getSelection();
        if (selection && !selection.isCollapsed && current.contains(selection.anchorNode)) return;
        busy = true;
        const status = current.querySelector('#analytics-refresh-status');
        const button = current.querySelector('[data-refresh-analytics]');
        status.textContent = 'Memperbarui analitik…'; button.disabled = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 20000);
        try {
            const response = await fetch(current.dataset.analyticsUrl, {credentials: 'same-origin', cache: 'no-store', signal: controller.signal});
            if (!response.ok || response.redirected) throw new Error('Koneksi atau sesi bermasalah.');
            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const next = doc.getElementById('global-analytics');
            if (!next) throw new Error('Respons analitik tidak tersedia.');
            current.querySelectorAll('[data-analytics-details]').forEach(detail => {
                Array.from(next.querySelectorAll('[data-analytics-details]')).find(node => node.dataset.analyticsDetails === detail.dataset.analyticsDetails)?.toggleAttribute('open', detail.open);
            });
            const positions = Array.from(current.querySelectorAll('.overflow-auto,.overflow-x-auto')).map(node => [node.scrollLeft,node.scrollTop]);
            const focused = document.activeElement === button;
            current.replaceWith(next);
            Array.from(next.querySelectorAll('.overflow-auto,.overflow-x-auto')).forEach((node,i) => { if(positions[i]) {node.scrollLeft=positions[i][0];node.scrollTop=positions[i][1];} });
            if (focused) next.querySelector('[data-refresh-analytics]').focus({preventScroll:true});
        } catch (error) {
            status.textContent = 'Pembaruan gagal; data terakhir tetap ditampilkan. Klik Refresh untuk mencoba lagi.';
        } finally { clearTimeout(timeout); busy = false; button.disabled = false; }
    }
    document.addEventListener('click', event => { if(event.target.closest('[data-refresh-analytics]'))refresh(); });
    document.addEventListener('visibilitychange', () => { if(!document.hidden)refresh(); });
    setInterval(refresh,30000);
})();
