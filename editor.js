// Panneau de configuration : modifie la page en direct et produit le contenu de src/config.php.
// Rien n'est enregistré côté serveur : l'export est à copier-coller dans le fichier.
(function () {
    'use strict';

    const data          = JSON.parse(document.getElementById('rss-config').textContent);
    const panel         = document.getElementById('config-panel');
    const toggleBtn     = document.getElementById('config-toggle');
    const closeBtn      = document.getElementById('config-close');
    const grid          = document.querySelector('.content');
    const dailyNav      = document.querySelector('.daily-links');
    const generalEl     = document.getElementById('cfg-general');
    const dailyList     = document.getElementById('cfg-daily');
    const catList       = document.getElementById('cfg-cats');
    const addFeedForm   = document.getElementById('cfg-feed-add');
    const addFeedUrl    = document.getElementById('cfg-feed-url');
    const addFeedCat    = document.getElementById('cfg-feed-cat');
    const exportEl      = document.getElementById('cfg-export');
    const copyStatus    = document.getElementById('cfg-copy-status');
    const liveEl        = document.getElementById('cfg-live');
    const DEFAULT_COLOR = '#e67e22';

    // État hors cartes. L'ordre et les réglages des flux, eux, sont lus directement dans le DOM :
    // c'est la grille qui fait foi, ce qui évite de synchroniser deux listes.
    const config     = data.config;
    const daily      = data.daily.map(l => ({ name: l.name, url: l.url }));
    const categories = data.categories.map(([name, color]) => ({ name, color }));

    // Cartes déjà chargées ou en cours : réactiver un flux ne relance un chargement que s'il n'en a jamais eu.
    const loadedCards = new WeakSet(cards().filter(c => !c.classList.contains('feed--off')));

    let dragged = null;

    // --- Helpers ---
    function cards() {
        return [...grid.querySelectorAll('.feed[data-url]')];
    }

    function findCat(name) {
        return categories.find(c => c.name === name);
    }

    function esc(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function faviconUrl(url) {
        let host = '';
        try { host = new URL(url).hostname; } catch {}
        return 'https://www.google.com/s2/favicons?sz=32&domain=' + encodeURIComponent(host);
    }

    // Nom lisible d'une carte pour les libellés accessibles : le titre du flux s'il est chargé, sinon l'URL.
    function cardName(card) {
        const title = card.querySelector('.feed-title').textContent.trim();
        return title && title !== '…' ? title : card.dataset.url;
    }

    function announce(msg) {
        liveEl.textContent = '';
        setTimeout(() => { liveEl.textContent = msg; }, 50);
    }

    // Une catégorie référencée par un flux mais sans couleur est ajoutée, pour être éditable et exportée.
    cards().forEach(card => {
        if (!findCat(card.dataset.category)) {
            categories.push({ name: card.dataset.category, color: DEFAULT_COLOR });
        }
    });

    // --- Ouverture du panneau ---
    function setOpen(open) {
        panel.hidden = !open;
        toggleBtn.setAttribute('aria-expanded', String(open));
        document.body.classList.toggle('editing', open);
        cards().forEach(card => open ? addCardControls(card) : removeCardControls(card));
        if (open) {
            refreshExport();
            closeBtn.focus();
        } else {
            toggleBtn.focus();
        }
    }

    toggleBtn.addEventListener('click', () => setOpen(panel.hidden));
    closeBtn.addEventListener('click', () => setOpen(false));
    // Échap ferme aussi depuis les contrôles des cartes, qui sont hors du panneau.
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && !panel.hidden && (panel.contains(e.target) || grid.contains(e.target))) setOpen(false);
    });

    // --- Contrôles posés sur chaque carte en mode édition ---
    function addCardControls(card) {
        if (card.querySelector('.feed-edit')) return;
        const name = esc(cardName(card));
        const bar  = document.createElement('div');
        bar.className = 'feed-edit';
        bar.innerHTML = `
            <button type="button" data-move="-1" aria-label="Avant : ${name}">Avant</button>
            <button type="button" data-move="1" aria-label="Après : ${name}">Après</button>
            <select aria-label="Catégorie : ${name}"></select>
            <label><input type="checkbox" data-active${card.classList.contains('feed--off') ? '' : ' checked'}> Actif<span class="visually-hidden"> : ${name}</span></label>
            <button type="button" data-remove aria-label="Supprimer : ${name}">Supprimer</button>`;
        fillCatSelect(bar.querySelector('select'), card.dataset.category);
        card.querySelector('h5').after(bar);
        card.draggable = true;
    }

    function removeCardControls(card) {
        card.querySelector('.feed-edit')?.remove();
        card.draggable = false;
    }

    function fillCatSelect(select, value) {
        select.innerHTML = categories
            .map(c => `<option${c.name === value ? ' selected' : ''}>${esc(c.name)}</option>`)
            .join('');
    }

    function refreshCatSelects() {
        cards().forEach(card => {
            const select = card.querySelector('.feed-edit select');
            if (select) fillCatSelect(select, card.dataset.category);
        });
        fillCatSelect(addFeedCat, addFeedCat.value || categories[0]?.name);
    }

    function setCardCategory(card, name) {
        card.dataset.category = name;
        card.querySelector('.feed-category').textContent = name;
        card.style.setProperty('--card-color', findCat(name)?.color ?? DEFAULT_COLOR);
    }

    function setCardActive(card, active) {
        card.classList.toggle('feed--off', !active);
        if (active && !loadedCards.has(card)) {
            loadedCards.add(card);
            card.classList.add('feed--loading');
            card.querySelector('.feed-content').innerHTML = '<div class="spinner"></div>';
            window.rssLoadCard(card);
        }
    }

    // Déplacement au clavier : alternative au glisser-déposer, le focus reste sur le bouton actionné.
    function moveCard(card, dir, button) {
        const list   = cards();
        const target = list[list.indexOf(card) + dir];
        if (!target) {
            announce(dir < 0 ? 'Déjà en première position' : 'Déjà en dernière position');
            return;
        }
        target[dir < 0 ? 'before' : 'after'](card);
        button.focus();
        announce(`Position ${cards().indexOf(card) + 1} sur ${list.length}`);
        refreshExport();
    }

    function removeCard(card) {
        const list = cards();
        const next = list[list.indexOf(card) + 1] ?? list[list.indexOf(card) - 1];
        card.remove();
        // Le focus suit sur la carte voisine pour ne pas retomber en haut de page.
        (next?.querySelector('[data-remove]') ?? toggleBtn).focus();
        announce('Flux supprimé');
        renderCats();
        refreshExport();
    }

    grid.addEventListener('click', e => {
        const card = e.target.closest('.feed');
        if (!card || !document.body.classList.contains('editing')) return;
        const move = e.target.closest('[data-move]');
        if (move) moveCard(card, Number(move.dataset.move), move);
        if (e.target.closest('[data-remove]')) removeCard(card);
    });

    grid.addEventListener('change', e => {
        const card = e.target.closest('.feed');
        if (!card) return;
        if (e.target.matches('.feed-edit select')) {
            setCardCategory(card, e.target.value);
            renderCats();
        }
        if (e.target.matches('[data-active]')) setCardActive(card, e.target.checked);
        refreshExport();
    });

    // --- Glisser-déposer : la carte survolée cède sa place selon le côté où passe le pointeur ---
    grid.addEventListener('dragstart', e => {
        const card = e.target.closest?.('.feed');
        if (!card || !card.draggable) return;
        dragged = card;
        card.classList.add('feed--dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', card.dataset.url);
    });

    grid.addEventListener('dragover', e => {
        if (!dragged) return;
        e.preventDefault();
        const over = e.target.closest('.feed');
        if (!over || over === dragged) return;
        const r = over.getBoundingClientRect();
        over[e.clientX > r.left + r.width / 2 ? 'after' : 'before'](dragged);
    });

    grid.addEventListener('drop', e => {
        if (dragged) e.preventDefault();
    });

    grid.addEventListener('dragend', () => {
        if (!dragged) return;
        dragged.classList.remove('feed--dragging');
        dragged = null;
        refreshExport();
    });

    // --- Général ---
    // Description des réglages connus. nullable : vide (ou case Défaut) vaut null dans l'export.
    // Une clé absente d'ici est quand même éditable, en texte ou nombre selon sa valeur.
    const FIELDS = {
        pagetitle:      { group: 'Bandeau', label: 'Titre', type: 'text' },
        header_color:   { group: 'Bandeau', label: 'Couleur du bandeau', type: 'color', nullable: true, comment: 'null = couleur du thème' },
        feeds_per_row:  { group: 'Affichage', label: 'Flux par ligne', type: 'number', min: 1, step: 1 },
        items_per_feed: { group: 'Affichage', label: 'Items max par flux (vide = illimité)', type: 'number', min: 1, step: 1, nullable: true, comment: 'null = illimité' },
        font_size:      { group: 'Affichage', label: 'Taille du texte (rem)', type: 'number', min: 0.5, step: 0.025, comment: 'rem' },
        title_size:     { group: 'Affichage', label: 'Taille des titres (rem)', type: 'number', min: 0.5, step: 0.025, comment: 'rem' },
        cache_ttl:      { group: 'Technique', label: 'Cache des flux (secondes)', type: 'number', min: 0, step: 1, comment: 'secondes' },
    };

    function fieldOf(key) {
        return FIELDS[key] ?? { group: 'Autres', label: key, type: typeof config[key] === 'number' ? 'number' : 'text' };
    }

    // Même règle que contrast_text() en PHP.
    function contrastText(hex) {
        const [r, g, b] = [1, 3, 5].map(i => parseInt(hex.slice(i, i + 2), 16));
        return 0.299 * r + 0.587 * g + 0.114 * b > 150 ? '#000000' : '#ffffff';
    }

    // Reconstruit le bloc de settings_css() en PHP, pour voir les réglages s'appliquer en direct.
    function applySettings() {
        let css = `:root { --per-row: ${Math.max(1, parseInt(config.feeds_per_row, 10) || 1)};`
            + ` --item-size: ${Number(config.font_size)}rem; --title-size: ${Number(config.title_size)}rem; }\n`;
        if (/^#[0-9a-f]{6}$/i.test(config.header_color ?? '')) {
            const fg = contrastText(config.header_color);
            css += `.page-title { --header-bg: ${config.header_color}; --text: ${fg}; --text-muted: ${fg}; }\n`;
        }
        if (config.items_per_feed) {
            css += `.rss-item:nth-child(n + ${parseInt(config.items_per_feed, 10) + 1}) { display: none; }\n`;
        }
        document.getElementById('rss-settings').textContent = css;
        document.title = config.pagetitle;
        document.querySelector('.page-title h1').textContent = config.pagetitle;
    }

    function setSetting(key, value) {
        config[key] = value;
        applySettings();
        refreshExport();
    }

    function renderField(key) {
        const f   = fieldOf(key);
        const id  = 'cfg-general-' + key;
        const val = config[key];
        const row = document.createElement('div');
        row.className = 'cfg-field';

        if (f.type === 'color') {
            // Un champ couleur ne peut pas être vide : la case Défaut porte la valeur null.
            row.innerHTML = `<label for="${id}">${esc(f.label)}</label>
                <span class="cfg-color">
                    <input id="${id}" type="color" value="${esc(val ?? '#ffffff')}"${val ? '' : ' disabled'}>
                    <label><input type="checkbox"${val ? '' : ' checked'}> Défaut<span class="visually-hidden"> : ${esc(f.label)}</span></label>
                </span>`;
            const [colorIn, defaultIn] = row.querySelectorAll('input');
            colorIn.addEventListener('input', () => setSetting(key, colorIn.value));
            defaultIn.addEventListener('change', () => {
                colorIn.disabled = defaultIn.checked;
                setSetting(key, defaultIn.checked ? null : colorIn.value);
            });
            return row;
        }

        const attrs = f.type === 'number' ? ` min="${f.min}" step="${f.step}"` : '';
        row.innerHTML = `<label for="${id}">${esc(f.label)}</label>
            <input id="${id}" type="${f.type}"${attrs} value="${esc(val ?? '')}">`;
        const input = row.querySelector('input');
        input.addEventListener('input', () => {
            if (f.type !== 'number') return setSetting(key, input.value);
            if (input.value === '' && f.nullable) return setSetting(key, null);
            // Saisie incomplète ou hors bornes : on garde la dernière valeur valide.
            const n = Number(input.value);
            if (input.value !== '' && !Number.isNaN(n) && n >= f.min) setSetting(key, n);
        });
        return row;
    }

    const groups = {};
    Object.keys(config).forEach(key => {
        const name = fieldOf(key).group;
        if (!groups[name]) {
            groups[name] = document.createElement('div');
            groups[name].innerHTML = `<h4>${esc(name)}</h4>`;
            generalEl.appendChild(groups[name]);
        }
        groups[name].appendChild(renderField(key));
    });

    // --- Liens du jour ---
    // Les lignes ne sont reconstruites qu'à l'ajout ou au retrait, jamais à la frappe, pour garder le focus.
    function renderDaily() {
        dailyList.innerHTML = '';
        daily.forEach((link, i) => {
            const li = document.createElement('li');
            li.innerHTML = `
                <input type="text" aria-label="Nom du lien ${i + 1}" value="${esc(link.name)}" placeholder="Nom">
                <input type="url" aria-label="URL du lien ${i + 1}" value="${esc(link.url)}" placeholder="https://">
                <button type="button" aria-label="Retirer le lien ${i + 1}">Retirer</button>`;
            const [nameIn, urlIn] = li.querySelectorAll('input');
            nameIn.addEventListener('input', () => { link.name = nameIn.value; renderDailyBar(); refreshExport(); });
            urlIn.addEventListener('input', () => { link.url = urlIn.value; renderDailyBar(); refreshExport(); });
            li.querySelector('button').addEventListener('click', () => {
                daily.splice(daily.indexOf(link), 1);
                renderDaily();
                renderDailyBar();
                refreshExport();
                document.getElementById('cfg-daily-add').focus();
                announce('Lien retiré');
            });
            dailyList.appendChild(li);
        });
    }

    function renderDailyBar() {
        const links = daily.filter(l => l.name.trim() && /^https?:\/\//i.test(l.url));
        dailyNav.hidden = links.length === 0;
        dailyNav.querySelector('ul').innerHTML = links.map(l => `
            <li>
                <a href="${esc(l.url)}" target="_blank" rel="noopener">
                    <img src="${esc(faviconUrl(l.url))}" alt="" width="16" height="16">
                    ${esc(l.name)}
                    <span class="visually-hidden">(nouvel onglet)</span>
                </a>
            </li>`).join('');
    }

    document.getElementById('cfg-daily-add').addEventListener('click', () => {
        daily.push({ name: '', url: '' });
        renderDaily();
        dailyList.lastElementChild.querySelector('input').focus();
    });

    // --- Catégories ---
    function renderCats() {
        catList.innerHTML = '';
        categories.forEach(cat => {
            const count = cards().filter(c => c.dataset.category === cat.name).length;
            const li    = document.createElement('li');
            li.innerHTML = `
                <input type="color" value="${esc(cat.color)}">
                <input type="text" value="${esc(cat.name)}">
                <span class="cfg-count">${count} flux</span>
                <button type="button">Retirer</button>`;
            const [colorIn, nameIn] = li.querySelectorAll('input');
            const removeBtn = li.querySelector('button');
            const label = () => {
                colorIn.setAttribute('aria-label', `Couleur : ${cat.name}`);
                nameIn.setAttribute('aria-label', `Nom de la catégorie ${cat.name}`);
                removeBtn.setAttribute('aria-label', `Retirer la catégorie ${cat.name}`);
            };
            label();

            colorIn.addEventListener('input', () => {
                cat.color = colorIn.value;
                cards().filter(c => c.dataset.category === cat.name)
                    .forEach(c => c.style.setProperty('--card-color', cat.color));
                refreshExport();
            });

            // Renommage à la validation et non à la frappe : un nom intermédiaire égal à une autre
            // catégorie fusionnerait les deux sans retour possible.
            nameIn.addEventListener('change', () => {
                const name = nameIn.value.trim();
                if (!name || (name !== cat.name && findCat(name))) {
                    announce(name ? `La catégorie ${name} existe déjà` : 'Le nom ne peut pas être vide');
                    nameIn.value = cat.name;
                    return;
                }
                cards().filter(c => c.dataset.category === cat.name).forEach(c => setCardCategory(c, name));
                const wasSelected = addFeedCat.value === cat.name;
                cat.name = name;
                label();
                refreshCatSelects();
                if (wasSelected) addFeedCat.value = name;
                refreshExport();
            });

            removeBtn.addEventListener('click', () => {
                const used = cards().filter(c => c.dataset.category === cat.name).length;
                if (used) {
                    announce(`Catégorie utilisée par ${used} flux : changez-les de catégorie d'abord`);
                    return;
                }
                categories.splice(categories.indexOf(cat), 1);
                renderCats();
                refreshCatSelects();
                refreshExport();
                document.getElementById('cfg-cat-add').focus();
                announce('Catégorie retirée');
            });
            catList.appendChild(li);
        });
    }

    document.getElementById('cfg-cat-add').addEventListener('click', () => {
        let name = 'Nouvelle catégorie';
        for (let i = 2; findCat(name); i++) name = `Nouvelle catégorie ${i}`;
        categories.push({ name, color: '#7f8c8d' });
        renderCats();
        refreshCatSelects();
        refreshExport();
        catList.lastElementChild.querySelector('input[type="text"]').select();
    });

    // --- Ajout d'un flux : nouvelle carte en fin de grille, chargée tout de suite pour valider l'URL ---
    addFeedForm.addEventListener('submit', e => {
        e.preventDefault();
        const url = addFeedUrl.value.trim();
        if (cards().some(c => c.dataset.url === url)) {
            announce('Ce flux est déjà présent');
            return;
        }
        const cat  = addFeedCat.value;
        const card = document.createElement('div');
        card.className = 'feed feed--loading';
        card.dataset.url = url;
        card.dataset.category = cat;
        card.style.setProperty('--card-color', findCat(cat)?.color ?? DEFAULT_COLOR);
        card.innerHTML = `
            <h5>
                <span class="feed-category">${esc(cat)}</span>
                <span class="feed-title-row"><span class="feed-title">…</span></span>
            </h5>
            <div class="feed-content"><div class="spinner"></div></div>`;
        grid.appendChild(card);
        loadedCards.add(card);
        window.rssLoadCard(card);
        addCardControls(card);
        const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
        card.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'nearest' });
        addFeedUrl.value = '';
        announce('Flux ajouté en fin de page');
        renderCats();
        refreshExport();
    });

    // --- Export PHP ---
    function php(s) {
        return "'" + String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
    }

    function phpValue(v) {
        if (v === null || v === undefined) return 'null';
        if (typeof v === 'number') return String(v);
        if (typeof v === 'boolean') return v ? 'true' : 'false';
        return php(v);
    }

    // Tableau associatif aligné sur la plus longue clé, comme dans le fichier d'origine.
    // Troisième élément optionnel : commentaire de fin de ligne.
    function aligned(pairs) {
        const width = Math.max(0, ...pairs.map(([k]) => php(k).length));
        return pairs
            .map(([k, v, comment]) => `        ${php(k).padEnd(width)} => ${v},` + (comment ? ` // ${comment}` : ''))
            .join('\n');
    }

    function buildConfigFile() {
        const dailyLines = daily
            .filter(l => l.url.trim())
            .map(l => `        ['name' => ${php(l.name)}, 'url' => ${php(l.url)}],`)
            .join('\n');
        const feedLines = cards()
            .map(c => `        ['url' => ${php(c.dataset.url)}, 'category' => ${php(c.dataset.category)}`
                + (c.classList.contains('feed--off') ? ", 'off' => true" : '') + '],')
            .join('\n');

        return `<?php

// Ce fichier peut être régénéré en entier depuis le panneau de configuration (rouage).

function get_config(): object {
    return (object) [
${aligned(Object.entries(config).map(([k, v]) => [k, phpValue(v), FIELDS[k]?.comment]))}
    ];
}

// Sites à visiter chaque jour, affichés sous le titre.
function daily_links(): array {
    return [
${dailyLines}
    ];
}

// Liste plate : l'ordre d'affichage des cartes est celui de la liste, indépendant des catégories.
// 'off' => true garde un flux en réserve sans le charger.
function feeds(): array {
    return [
${feedLines}
    ];
}

// L'ordre de ce tableau est celui des catégories dans le panneau de configuration.
function category_colors(): array {
    return [
${aligned(categories.map(c => [c.name, php(c.color)]))}
    ];
}
`;
    }

    function refreshExport() {
        exportEl.value = buildConfigFile();
        copyStatus.textContent = '';
    }

    // Le presse-papiers moderne exige un contexte sécurisé : repli sur execCommand sinon.
    document.getElementById('cfg-copy').addEventListener('click', () => {
        const done = () => {
            copyStatus.textContent = '';
            setTimeout(() => { copyStatus.textContent = 'Copié'; }, 50);
        };
        navigator.clipboard?.writeText(exportEl.value).then(done).catch(fallback) ?? fallback();
        function fallback() {
            exportEl.select();
            document.execCommand('copy');
            done();
        }
    });

    renderDaily();
    renderCats();
    refreshCatSelects();
}());
