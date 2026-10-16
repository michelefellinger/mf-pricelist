(() => {
    'use strict';
    document.querySelectorAll('.mfpl[data-endpoint]').forEach((root, index) => {
        let timer, busy = false, controller;
        // A cached page contains only a shell, never a stale price snapshot.
        const notice = document.createElement('p'); notice.setAttribute('role', 'status');
        const content = document.createElement('div');
        root.replaceChildren(notice, content);
        async function refresh() {
            if (busy) return;
            clearTimeout(timer); busy = true; content.hidden = true; root.setAttribute('aria-busy', 'true');
            controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 15000);
            try {
                const response = await fetch(root.dataset.endpoint, {
                    method: 'POST', credentials: 'omit', cache: 'no-store', signal: controller.signal,
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'action=mfpl_public'
                });
                if (!response.ok) throw new Error('HTTP');
                const result = await response.json();
                if (!result.success || typeof result.data.html !== 'string') throw new Error('Data');
                // Unique IDs for multiple shortcode instances; avoid replacing focused links unnecessarily.
                const template = document.createElement('template');
                template.innerHTML = result.data.html;
                template.content.querySelectorAll('[id], a[href^="#mfpl-"]').forEach(node => {
                    if (node.id) node.id = node.id.replace(/^mfpl-\d+-/, 'mfpl-view-' + index + '-');
                    if (node.hasAttribute('href')) node.setAttribute('href', node.getAttribute('href').replace(/^#mfpl-\d+-/, '#mfpl-view-' + index + '-'));
                });
                const html = template.innerHTML;
                if (content.innerHTML !== html) content.innerHTML = html;
                notice.textContent = ''; root.setAttribute('aria-busy', 'false');
                timer = setTimeout(refresh, Math.max(1, Math.min(30, Number(result.data.refresh_after) || 30)) * 1000);
            } catch (_) {
                content.replaceChildren(); root.setAttribute('aria-busy', 'false');
                notice.textContent = 'Preise konnten nicht aktuell geladen werden. ';
                const retry = document.createElement('button'); retry.type = 'button'; retry.textContent = 'Erneut versuchen';
                retry.addEventListener('click', refresh); notice.append(retry);
                timer = setTimeout(refresh, 30000);
            } finally { clearTimeout(timeout); content.hidden = false; busy = false; }
        }
        notice.textContent = 'Preisliste wird geladen …';
        document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
        window.addEventListener('pageshow', refresh);
        window.addEventListener('focus', refresh);
        refresh();
    });
})();
