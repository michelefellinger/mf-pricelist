// Editor interaction tests with a minimal DOM double; no browser/WordPress bootstrap.
'use strict';
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
class Node {
    constructor(tag) { this.tag = tag; this.children = []; this.events = {}; this.attributes = {}; this.textContent = ''; this.value = ''; }
    append(...nodes) { this.children.push(...nodes); }
    prepend(...nodes) { this.children.unshift(...nodes); }
    replaceChildren(...nodes) { this.children = nodes; }
    addEventListener(name, handler) { (this.events[name] ||= []).push(handler); }
    setAttribute(name, value) { this.attributes[name] = value; }
    focus() {}
    fire(name) { for (const fn of this.events[name] || []) fn({preventDefault() {}, dataTransfer: {}}); }
}
const ids = Object.fromEntries(['mfpl-form', 'mfpl-workspace', 'mfpl-context', 'mfpl-document', 'mfpl-status', 'mfpl-save'].map(id => [id, new Node('div')]));
const data = {
    categories: [{id:'a', name:'Damen', description:'Für Damen', parent:''}, {id:'b', name:'Farbe', description:'Farbberatung', parent:'a'}],
    services: [{id:'s', name:'Föhnen', description:'Mit Pflege', category:'a', prices:[{type:'fixed', amount:'25', timestamp:0, valid_from:'', note:''}]}]
};
const window = {mfplData: data, mfplNow: 1792483200, addEventListener() {}, confirm: () => true};
vm.runInNewContext(fs.readFileSync(__dirname + '/../assets/admin.js', 'utf8'), {
    window, document:{getElementById:id => ids[id] || null, createElement:tag => new Node(tag)},
    crypto:{randomUUID:()=> 'new-id'}, location:{hash:''}, Date, Map, JSON, Number
});
function all(node = ids['mfpl-workspace']) { return [node, ...node.children.flatMap(c => all(c))]; }
function button(text) { const b = all().find(n => n.tag === 'button' && n.textContent === text); assert.ok(b, 'Button ' + text); return b; }
function field(label) { const n = all().find(n => n.tag === 'label' && n.textContent === label); assert.ok(n, 'Field ' + label); return n.children[0]; }
function change(label, value) { const input = field(label); input.value = value; input.fire('change'); }
assert.ok(all().some(n => n.textContent === 'Kategorienübersicht'));
assert.ok(all().some(n => n.textContent === 'Für Damen'));
button('Anzeigen').fire('click');
assert.equal(ids['mfpl-context'].value, 'a');
assert.ok(all().some(n => n.textContent === 'Föhnen'));
assert.ok(button('Neue Unter-Kategorie'));
button('Neue Dienstleistung').fire('click');
assert.equal(field('Kategorie').value, 'a', 'Context preselects main category');
change('Name', 'Schnitt'); change('Beschreibung', 'Inklusive Beratung');
button('Fertig – zurück zur Übersicht').fire('click');
assert.ok(all().some(n => n.textContent === 'Schnitt'));
const editButtons = all().filter(n => n.tag === 'button' && n.textContent === 'Bearbeiten');
editButtons.at(-1).fire('click');
assert.equal(field('Name').value, 'Schnitt');
change('Preismodell', 'lengths');
change('Öffentliche Darstellung der Längenpreise', 'stacked');
button('Fertig – zurück zur Übersicht').fire('click');
assert.equal(data.services[1].length_layout, 'stacked');
assert.equal(data.services[1].prices[0].variant, 'short');
button('Anzeigen').fire('click');
assert.equal(ids['mfpl-context'].value, 'b');
assert.ok(!all().some(n => n.tag === 'button' && n.textContent === 'Neue Unter-Kategorie'), 'No third level action');
button('Neue Dienstleistung').fire('click');
assert.equal(field('Kategorie').value, 'b', 'Context preselects subcategory');
change('Name', 'Tönen');
button('Fertig – zurück zur Übersicht').fire('click');
ids['mfpl-form'].fire('submit');
assert.equal(JSON.parse(ids['mfpl-document'].value).services[2].category, 'b');
console.log('OK: admin navigation, descriptions, editing, context selection and stacked option');
