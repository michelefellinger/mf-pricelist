(() => {
    'use strict';
    const data = window.mfplData;
    const form = document.getElementById('mfpl-form');
    if (!form || !data) return;
    let dirty = false;
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
    function move(list, row, target) {
        const index = list.indexOf(row), other = list.indexOf(target);
        if (index < 0 || other < 0 || index === other) return;
        list.splice(index, 1); list.splice(other, 0, row); changed(); render();
        const focus = document.getElementById('row-' + row.id);
        if (focus) focus.focus();
    }
    function card(list, row, host, title, group) {
        const box = element('section', null, 'mfpl-card'); box.id = 'row-' + row.id; box.tabIndex = -1;
        box.append(element('h3', title));
        const controls = element('div', null, 'mfpl-controls');
        const grip = element('span', '↕ Ziehen', 'mfpl-grip'); grip.draggable = true;
        grip.addEventListener('dragstart', event => { event.dataTransfer.setData('text/plain', row.id); event.dataTransfer.effectAllowed = 'move'; });
        box.addEventListener('dragover', event => { event.preventDefault(); });
        box.addEventListener('drop', event => {
            event.preventDefault(); event.stopPropagation();
            const source = list.find(item => item.id === event.dataTransfer.getData('text/plain'));
            if (source && group(source) === group(row)) move(list, source, row);
            else status('Sortieren ist nur innerhalb derselben Kategorieebene bzw. Unterkategorie möglich.');
        });
        const siblings = list.filter(item => group(item) === group(row));
        const index = siblings.indexOf(row);
        controls.append(grip);
        [-1, 1].forEach(direction => {
            const b = button(direction < 0 ? '↑ Nach oben' : '↓ Nach unten', () => move(list, row, siblings[index + direction]));
            b.disabled = !siblings[index + direction]; controls.append(b);
        });
        controls.append(button('Entfernen', () => {
            if (list === data.categories && (data.categories.some(c => c.parent === row.id) || data.services.some(s => s.category === row.id))) {
                status('Zuerst zugeordnete Unterkategorien und Leistungen entfernen oder umordnen.'); return;
            }
            if (!window.confirm('„' + title + '“ entfernen? Erst Speichern übernimmt die Änderung.')) return;
            list.splice(list.indexOf(row), 1); changed(); render();
        }));
        box.append(controls); host.append(box); return box;
    }
    function render() {
        const categories = document.getElementById('mfpl-categories'); categories.replaceChildren();
        data.categories.forEach(row => {
            const box = card(data.categories, row, categories, row.name || 'Neue Kategorie', item => item.parent);
            field(box, 'Name', row.name, value => { row.name = value; render(); });
            const parents = data.categories.filter(c => !c.parent && c.id !== row.id);
            field(box, 'Übergeordnete Kategorie', row.parent, value => {
                if (data.categories.some(c => c.parent === row.id) && value) { status('Hauptkategorie hat noch Unterkategorien.'); render(); return; }
                if (data.services.some(s => s.category === row.id) && !value) { status('Unterkategorie hat noch Leistungen.'); render(); return; }
                row.parent = value; render();
            }, [['', '— Hauptkategorie —'], ...parents.map(c => [c.id, c.name])]);
        });
        const services = document.getElementById('mfpl-services'); services.replaceChildren();
        data.services.forEach(row => {
            const box = card(data.services, row, services, row.name || 'Neue Leistung', item => item.category);
            field(box, 'Name', row.name, value => { row.name = value; });
            field(box, 'Kurzbeschreibung', row.description, value => { row.description = value; }, null, 'textarea');
            field(box, 'Unterkategorie', row.category, value => { row.category = value; render(); }, [['', '— Bitte wählen —'], ...data.categories.filter(c => c.parent).map(c => [c.id, (data.categories.find(p => p.id === c.parent)?.name || '') + ' > ' + c.name])]);
            row.prices.forEach((price, index) => {
                const p = element('fieldset'); p.append(element('legend', price.valid_from ? 'Geplanter Preis' : 'Grundpreis / ohne Datum'));
                field(p, 'Preisart', price.type, value => { price.type = value; render(); }, [['fixed', 'Festpreis'], ['from', 'ab-Preis'], ['request', 'Auf Anfrage']]);
                if (price.type !== 'request') field(p, 'Preis in Euro (z.B. 49,50)', price.amount, value => { price.amount = value; });
                field(p, 'Gültig ab (leer = Grundpreis)', price.valid_from, value => { price.valid_from = value; }, null, 'datetime-local');
                field(p, 'Preishinweis / Fußnote (optional)', price.note, value => { price.note = value; });
                p.append(button('Preis entfernen', () => { row.prices.splice(index, 1); changed(); render(); })); box.append(p);
            });
            box.append(button('Preisänderung hinzufügen', () => { row.prices.push({type: 'fixed', amount: '', valid_from: '', note: ''}); changed(); render(); }));
        });
    }
    document.getElementById('mfpl-add-category').addEventListener('click', () => { data.categories.push({id: uid(), name: '', parent: ''}); changed(); render(); });
    document.getElementById('mfpl-add-service').addEventListener('click', () => { data.services.push({id: uid(), name: '', description: '', category: '', prices: [{type: 'fixed', amount: '', valid_from: '', note: ''}]}); changed(); render(); });
    form.addEventListener('submit', () => { document.getElementById('mfpl-document').value = JSON.stringify(data); dirty = false; });
    window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    render(); document.getElementById('mfpl-save').disabled = false;
})();
