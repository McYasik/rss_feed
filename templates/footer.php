</div><!-- /.content -->

<button id="feed-status-btn" title="Statut des flux">?</button>
<div id="feed-status-panel">
    <h4>Statut des flux</h4>
    <ul id="feed-status-list"></ul>
</div>

<script>
(function () {
    const btn        = document.getElementById('feed-status-btn');
    const panel      = document.getElementById('feed-status-panel');
    const list       = document.getElementById('feed-status-list');
    const darkBtn    = document.getElementById('dark-toggle');
    const tsEl       = document.getElementById('last-loaded');
    const cards      = document.querySelectorAll('.feed[data-url]:not(.feed--off)');
    const statuses   = {};
    let errors       = 0;

    // --- Dark mode ---
    function applyTheme(dark) {
        document.documentElement.classList.toggle('dark', dark);
        darkBtn.textContent = dark ? 'Clair' : 'Sombre';
        localStorage.setItem('theme', dark ? 'dark' : 'light');
    }
    applyTheme(localStorage.getItem('theme') === 'dark');
    darkBtn.addEventListener('click', () =>
        applyTheme(!document.documentElement.classList.contains('dark'))
    );

    // --- Status panel ---
    btn.addEventListener('click', () => panel.classList.toggle('open'));
    document.addEventListener('click', e => {
        if (!panel.contains(e.target) && e.target !== btn)
            panel.classList.remove('open');
    });

    // --- Helpers ---
    function esc(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function isHttpUrl(url) {
        return /^https?:\/\//i.test(url);
    }

    function renderPanel() {
        list.innerHTML = Object.values(statuses).map(s => {
            const nameHtml = (s.link && isHttpUrl(s.link))
                ? `<a class="status-name" href="${esc(s.link)}" target="_blank" rel="noopener">${esc(s.name)}</a>`
                : `<span class="status-name">${esc(s.name)}</span>`;
            return s.ok
                ? `<li><span class="status-ok">&#10003;</span>${nameHtml}</li>`
                : `<li><span class="status-err">&#10007;</span>${nameHtml}<span class="status-reason">${esc(s.reason)}</span></li>`;
        }).join('');

        if (errors > 0) {
            btn.classList.add('has-errors');
            btn.textContent = errors;
            btn.title = `${errors} flux en erreur`;
        }
    }

    // --- Seen URLs (localStorage) ---
    const SEEN_KEY = 'rss_seen';

    function getSeenUrls(feedUrl) {
        try {
            return new Set((JSON.parse(localStorage.getItem(SEEN_KEY)) || {})[feedUrl] || []);
        } catch { return new Set(); }
    }

    function setSeenUrls(feedUrl, urls) {
        try {
            const data = JSON.parse(localStorage.getItem(SEEN_KEY)) || {};
            data[feedUrl] = [...new Set(urls)];
            localStorage.setItem(SEEN_KEY, JSON.stringify(data));
        } catch {}
    }

    // --- Auto-refresh ---
    setTimeout(() => location.reload(), 60 * 60 * 1000);

    // --- Load feeds ---
    // Une carte se charge seule : l'éditeur rappelle cette fonction pour un flux ajouté ou réactivé.
    function loadCard(card) {
        const url        = card.dataset.url;
        const titleEl    = card.querySelector('.feed-title');
        const titleRowEl = card.querySelector('.feed-title-row');
        const bodyEl     = card.querySelector('.feed-content');
        const shortName  = url.replace(/^https?:\/\//, '').split('/')[0];

        return fetch(`api/feed.php?url=${encodeURIComponent(url)}`)
            .then(r => {
                if (!r.ok) return r.json().then(d => { throw new Error(d.error || `HTTP ${r.status}`); });
                return r.json();
            })
            .then(data => {
                if (data.error) throw new Error(data.error);

                // Badges
                const seen     = getSeenUrls(url);
                const newCount = data.items.filter(i => i.link && !seen.has(i.link)).length;
                setSeenUrls(url, data.items.map(i => i.link).filter(Boolean));

                // Titre comme lien (sans style modifié)
                if (data.link && isHttpUrl(data.link)) {
                    const a       = document.createElement('a');
                    a.className   = 'feed-title';
                    a.href        = data.link;
                    a.target      = '_blank';
                    a.rel         = 'noopener';
                    a.textContent = data.title;
                    titleEl.replaceWith(a);
                } else {
                    titleEl.textContent = data.title;
                }

                if (newCount > 0) {
                    const badge       = document.createElement('span');
                    badge.className   = 'new-badge';
                    badge.textContent = `+${newCount}`;
                    titleRowEl.appendChild(badge);
                }

                // Items
                bodyEl.innerHTML = data.items.map(item => `
                    <div class="rss-item">
                        <a class="title" href="${esc(isHttpUrl(item.link) ? item.link : '#')}" target="_blank" rel="noopener">${esc(item.title)}</a>
                        <span>${esc(item.pubDate)}</span>
                    </div>`
                ).join('');

                card.classList.remove('feed--loading');
                statuses[url] = { ok: true, name: data.title, link: data.link };
                renderPanel();
            })
            .catch(err => {
                // Masquée par la CSS hors mode édition, où l'erreur reste visible pour corriger le flux.
                card.classList.add('feed--error');
                card.classList.remove('feed--loading');
                bodyEl.innerHTML = `<p class="feed-message">${esc(err.message)}</p>`;
                statuses[url] = { ok: false, name: shortName, reason: err.message };
                errors++;
                renderPanel();
            });
    }
    window.rssLoadCard = loadCard;

    const allFetches = [...cards].map(loadCard);

    // --- Timestamp ---
    Promise.allSettled(allFetches).then(() => {
        const now = new Date();
        const hh  = String(now.getHours()).padStart(2, '0');
        const mm  = String(now.getMinutes()).padStart(2, '0');
        tsEl.textContent = `chargé à ${hh}:${mm}`;
    });
}());
</script>

<aside id="config-panel" aria-labelledby="config-panel-title" hidden>
    <div class="config-head">
        <h2 id="config-panel-title">Configuration</h2>
        <button type="button" id="config-close">Fermer</button>
    </div>
    <p class="config-hint">Les changements s'appliquent en direct sur la page. Pour les garder, copiez l'export dans <code>src/config.php</code>.</p>

    <section aria-labelledby="cfg-general-title">
        <h3 id="cfg-general-title">Général</h3>
        <div id="cfg-general"></div>
    </section>

    <section aria-labelledby="cfg-daily-title">
        <h3 id="cfg-daily-title">Liens du jour</h3>
        <ul id="cfg-daily" class="cfg-rows"></ul>
        <button type="button" id="cfg-daily-add">Ajouter un lien</button>
    </section>

    <section aria-labelledby="cfg-cats-title">
        <h3 id="cfg-cats-title">Catégories</h3>
        <ul id="cfg-cats" class="cfg-rows"></ul>
        <button type="button" id="cfg-cat-add">Ajouter une catégorie</button>
    </section>

    <section aria-labelledby="cfg-feeds-title">
        <h3 id="cfg-feeds-title">Flux</h3>
        <p class="config-hint">Réorganisez les cartes par glisser-déposer, ou avec leurs boutons Avant / Après.</p>
        <form id="cfg-feed-add">
            <label for="cfg-feed-url">URL du flux (obligatoire)</label>
            <input type="url" id="cfg-feed-url" required>
            <label for="cfg-feed-cat">Catégorie</label>
            <select id="cfg-feed-cat"></select>
            <button type="submit">Ajouter le flux</button>
        </form>
    </section>

    <section aria-labelledby="cfg-export-title">
        <h3 id="cfg-export-title">Export</h3>
        <label for="cfg-export">Contenu de <code>src/config.php</code></label>
        <textarea id="cfg-export" rows="12" readonly spellcheck="false"></textarea>
        <button type="button" id="cfg-copy">Copier</button>
        <span id="cfg-copy-status" role="status"></span>
    </section>

    <div id="cfg-live" aria-live="polite"></div>
</aside>

<?php
// Données initiales de l'éditeur. Les catégories passent en paires pour garder leur ordre
// (un objet JS réordonne les clés numériques). Les flux, eux, sont lus dans les cartes.
$editor_data = [
    'config'     => $config,
    'daily'      => $daily,
    'categories' => array_map(fn($name, $color) => [(string) $name, $color], array_keys($colors), $colors),
];
?>
<script type="application/json" id="rss-config"><?= json_encode($editor_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script src="./editor.js"></script>
</body>
</html>
