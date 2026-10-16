(() => {
    'use strict';
    const data = window.mfplData;
    const form = document.getElementById('mfpl-form');
    if (!form || !data) return;
    let dirty = false;
    const savedPrices = new Map(data.services.map(row => [row.id, JSON.stringify(row.prices)]));
    const uid = () => 'i' + crypto.randomUUID().replaceAll('-', '');
    const status = text => { document.getElementById('mfpl-status').textContent = text; };
    const changed = () => { dirty = true; status('Ungespeicherte Änderungen.'); };
    const element = (tag, text, className) => {
        const node = document.createElement(tag);
        if (text) node.textContent = text;
        if (className) node.className = className;
        return node;
    };
    function button(text, action) {
        const node = element('button', text, 'button'); node.type = 'button';
        node.addEventListener('click', action); return node;
    }
    function field(host, labelText, value, update, options, type = 'text') {
        const label = element('label', labelText);
        const input = element(options ? 'select' : type === 'textarea' ? 'textarea' : 'input');
        if (!options && type !== 'textarea') input.type = type;
        if (options) options.forEach(([value, name]) => { const o = element('option', name); o.value = value; input.append(o); });
        input.value = value;
        input.addEventListener('change', () => { update(input.value); changed(); });
        label.append(input); host.append(label); return input;
    }
    let selected = '';
    let editor = null;
    const workspace = document.getElementById('mfpl-workspace');
    function locationCategory() {
        const match = location.hash.match(/^#category=([a-zA-Z0-9_-]{1,64})$/);
        return match && data.categories.some(c => c.id === match[1]) ? match[1] : '';
    }
    function navigate(id) {
        editor = null; selected = id;
        location.hash = id ? 'category=' + id : '';
        render();
    }
    window.addEventListener('hashchange', () => { selected = locationCategory(); editor = null; render(); });
    function toolbar(title) {
        const bar = element('div', null, 'mfpl-toolbar');
        bar.append(element('h2', title)); workspace.append(bar); return bar;
    }
    function description(host, text) { if (text) host.append(element('p', text, 'mfpl-admin-description')); }
    function table(headers) {
        const scroll = element('div', null, 'mfpl-table-scroll');
        const t = element('table', null, 'widefat striped');
        const head = element('thead'), tr = element('tr');
        headers.forEach(text => { const th = element('th', text); th.scope = 'col'; tr.append(th); });
        head.append(tr); const body = element('tbody'); t.append(head, body); scroll.append(t); workspace.append(scroll); return body;
    }
    function cell(row, content) { const td = element('td'); if (typeof content === 'string') td.textContent = content; else td.append(content); row.append(td); return td; }
    function sortControls(list, row, tr, group) {
        tr.id = 'row-' + row.id; tr.tabIndex = -1;
        const host = element('div', null, 'mfpl-controls');
        const siblings = list.filter(item => group(item) === group(row));
        function move(target) {
            if (!target) return;
            const at = list.indexOf(row), to = list.indexOf(target);
            list.splice(at, 1); list.splice(to, 0, row); changed(); render();
            document.getElementById('row-' + row.id)?.focus();
        }
        [-1, 1].forEach(dir => {
            const target = siblings[siblings.indexOf(row) + dir];
            const b = button(dir < 0 ? '↑' : '↓', () => move(target));
            b.setAttribute('aria-label', row.name + (dir < 0 ? ' nach oben' : ' nach unten'));
            b.disabled = !target; host.append(b);
        });
        const grip = element('span', '↕', 'mfpl-grip'); grip.draggable = true; grip.title = 'Zum Sortieren ziehen';
        grip.addEventListener('dragstart', e => { e.dataTransfer.setData('text/plain', row.id); }); host.prepend(grip);
        tr.addEventListener('dragover', e => e.preventDefault());
        tr.addEventListener('drop', e => {
            e.preventDefault(); const source = list.find(item => item.id === e.dataTransfer.getData('text/plain'));
            if (!source || source === row || group(source) !== group(row)) return;
            const at = list.indexOf(source), to = list.indexOf(row);
            list.splice(at, 1); list.splice(to, 0, source); changed(); render();
        });
        return host;
    }
    function edit(kind, row) { editor = {kind, row}; renderEditor(); }
    function addCategory(parent) {
        const row = {id: uid(), name: '', description: '', parent};
        data.categories.push(row); changed(); edit('category', row);
    }
    function addService() {
        const row = {id: uid(), name: '', description: '', category: selected, length_layout: 'columns', prices: [{type: 'fixed', amount: '', valid_from: '', note: ''}]};
        data.services.push(row); changed(); edit('service', row);
    }
    function categoriesTable(rows, nested = false) {
        const body = table(['Sortierung', 'Kategorie', 'Aktionen']);
        rows.forEach(row => {
            const tr = element('tr'); cell(tr, sortControls(data.categories, row, tr, c => c.parent));
            const title = element('div', null, row.parent && nested ? 'mfpl-indent' : '');
            title.append(element('strong', row.name || '(Ohne Namen)')); description(title, row.description);
            cell(tr, title); const actions = element('div', null, 'mfpl-controls');
            actions.append(button('Anzeigen', () => navigate(row.id)), button('Bearbeiten', () => edit('category', row)));
            cell(tr, actions); body.append(tr);
        });
        if (!rows.length) { const tr = element('tr'); const td = cell(tr, 'Noch keine Kategorien angelegt.'); td.colSpan = 3; body.append(tr); }
    }
    function priceSummary(row) {
        if (savedPrices.get(row.id) !== JSON.stringify(row.prices)) return 'Preis geändert – bitte speichern';
        const variants = ['', 'short', 'medium', 'long'];
        const labels = {'': '', short: 'kurz', medium: 'mittel', long: 'lang'};
        const now = Number(window.mfplNow || 0) + Math.floor((Date.now() - startedAt) / 1000);
        return variants.map(variant => {
            const candidates = row.prices.filter(p => (p.variant || '') === variant && Number(p.timestamp || 0) <= now);
            const p = candidates.reduce((best, p) => !best || Number(p.timestamp || 0) > Number(best.timestamp || 0) ? p : best, null);
            if (!p) return '';
            const amount = p.type === 'request' ? 'Auf Anfrage' : (p.type === 'from' ? 'ab ' : '') + (p.amount || '—') + ' €';
            return (labels[variant] ? labels[variant] + ': ' : '') + amount;
        }).filter(Boolean).join(' | ');
    }
    const startedAt = Date.now();
    function render() {
        workspace.replaceChildren(); document.getElementById('mfpl-context').value = selected;
        if (editor) { renderEditor(); return; }
        if (!selected) {
            toolbar('Kategorienübersicht').append(button('Neue Kategorie', () => addCategory('')));
            const rows = data.categories.filter(c => !c.parent).flatMap(c => [c, ...data.categories.filter(child => child.parent === c.id)]);
            categoriesTable(rows, true); return;
        }
        const category = data.categories.find(c => c.id === selected);
        if (!category) { selected = ''; render(); return; }
        const nav = element('nav'); nav.setAttribute('aria-label', 'Kategoriennavigation');
        nav.append(button('Kategorienübersicht', () => navigate('')));
        if (category.parent) nav.append(button(data.categories.find(c => c.id === category.parent)?.name || 'Hauptkategorie', () => navigate(category.parent)));
        workspace.append(nav);
        const bar = toolbar(category.name); description(workspace, category.description);
        const actions = element('div', null, 'mfpl-controls');
        actions.append(button('Kategorie bearbeiten', () => edit('category', category)));
        if (!category.parent) actions.append(button('Neue Unter-Kategorie', () => addCategory(category.id)));
        actions.append(button('Neue Dienstleistung', addService)); bar.append(actions);
        if (!category.parent) { workspace.append(element('h3', 'Unterkategorien')); categoriesTable(data.categories.filter(c => c.parent === selected)); }
        workspace.append(element('h3', 'Dienstleistungen'));
        const body = table(['Sortierung', 'Name', 'Preisschema', 'Preis', 'Aktionen']);
        const services = data.services.filter(s => s.category === selected);
        services.forEach(row => {
            const tr = element('tr'); cell(tr, sortControls(data.services, row, tr, s => s.category));
            const title = element('div'); title.append(element('strong', row.name || '(Ohne Namen)')); description(title, row.description); cell(tr, title);
            cell(tr, row.prices.some(p => p.variant) ? 'Nach Längen' : 'Einzelpreis'); cell(tr, priceSummary(row));
            cell(tr, button('Bearbeiten', () => edit('service', row))); body.append(tr);
        });
        if (!services.length) { const tr = element('tr'); const td = cell(tr, 'Noch keine Dienstleistungen in dieser Kategorie.'); td.colSpan = 5; body.append(tr); }
    }
    function renderEditor() {
        workspace.replaceChildren(); const {kind, row} = editor;
        toolbar(kind === 'category' ? 'Kategorie bearbeiten' : 'Dienstleistung bearbeiten').append(button('Zurück zur Übersicht', () => { editor = null; render(); }));
        const box = element('section', null, 'mfpl-card'); workspace.append(box);
        field(box, 'Name', row.name, value => { row.name = value; });
        field(box, 'Beschreibung', row.description || '', value => { row.description = value; }, null, 'textarea');
        if (kind === 'category') {
            const parents = data.categories.filter(c => !c.parent && c.id !== row.id);
            field(box, 'Übergeordnete Kategorie', row.parent, value => {
                if (value && data.categories.some(c => c.parent === row.id)) { status('Zuerst Unterkategorien umordnen.'); renderEditor(); return; }
                row.parent = value;
            }, [['', '— Hauptkategorie —'], ...parents.map(c => [c.id, c.name])]);
        } else {
            field(box, 'Kategorie', row.category, value => { row.category = value; }, data.categories.map(c => [c.id, (c.parent ? (data.categories.find(p => p.id === c.parent)?.name || '') + ' > ' : '') + c.name]));
            const lengths = row.prices.some(price => price.variant);
            field(box, 'Preismodell', lengths ? 'lengths' : 'single', value => {
                if (value === 'single' && row.prices.some(price => price.variant && price.variant !== 'short')) {
                    status('Vor dem Wechsel zum Einzelpreis zuerst die Preise für mittel und lang entfernen.'); renderEditor(); return;
                }
                row.prices.forEach(price => { price.variant = value === 'lengths' ? 'short' : ''; });
                renderEditor();
            }, [['single', 'Ein allgemeiner Preis'], ['lengths', 'Bis zu drei Preise: kurz / mittel / lang']]);
            row.prices.forEach((price, index) => {
                const p = element('fieldset'); p.append(element('legend', price.valid_from ? 'Geplanter Preis' : 'Grundpreis / ohne Datum'));
                if (lengths) field(p, 'Haarlänge', price.variant || 'short', value => { price.variant = value; }, [['short', 'kurz'], ['medium', 'mittel'], ['long', 'lang']]);
                field(p, 'Preisart', price.type, value => { price.type = value; renderEditor(); }, [['fixed', 'Festpreis'], ['from', 'ab-Preis'], ['request', 'Auf Anfrage']]);
                if (price.type !== 'request') field(p, 'Preis in Euro (z.B. 49,50)', price.amount, value => { price.amount = value; });
                field(p, 'Gültig ab (leer = Grundpreis)', price.valid_from, value => { price.valid_from = value; }, null, 'datetime-local');
                field(p, 'Preishinweis / Fußnote (optional)', price.note, value => { price.note = value; });
                p.append(button('Preis entfernen', () => { row.prices.splice(index, 1); changed(); renderEditor(); })); box.append(p);
            });
            box.append(button(lengths ? 'Längenpreis / Preisänderung hinzufügen' : 'Preisänderung hinzufügen', () => { row.prices.push({variant: lengths ? 'short' : '', type: 'fixed', amount: '', valid_from: '', note: ''}); changed(); renderEditor(); }));
            if (row.prices.some(p => p.variant)) field(box, 'Öffentliche Darstellung der Längenpreise', row.length_layout || 'columns', value => { row.length_layout = value; }, [['columns', 'Neben dem Titel'], ['stacked', 'Untereinander, eingerückt unter dem Titel']]);
        }
        box.append(button('Fertig – zurück zur Übersicht', () => { editor = null; render(); }));
        box.append(button('Entfernen', () => {
            if (kind === 'category' && (data.categories.some(c => c.parent === row.id) || data.services.some(s => s.category === row.id))) { status('Zuerst zugeordnete Unterkategorien und Dienstleistungen entfernen oder umordnen.'); return; }
            if (!window.confirm('Eintrag entfernen? Erst Speichern übernimmt die Änderung.')) return;
            const list = kind === 'category' ? data.categories : data.services;
            list.splice(list.indexOf(row), 1); if (kind === 'category' && selected === row.id) selected = row.parent;
            editor = null; changed(); render();
        }));
    }
    form.addEventListener('submit', () => { document.getElementById('mfpl-document').value = JSON.stringify(data); dirty = false; });
    window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    selected = locationCategory(); render(); document.getElementById('mfpl-save').disabled = false;
})();
