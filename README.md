# MF PriceList v1.1

Schlankes WordPress-Plugin für eine zweistufige, manuell sortierbare Preisliste. Keine zusätzlichen Plugins, Bibliotheken oder Build-Schritte erforderlich.

## Installation

Voraussetzungen: WordPress 6.5+, PHP 8.1+, HTTPS empfohlen, modernes JavaScript im Editor und auf der öffentlichen Seite. Preise werden in Euro ausgegeben.

1. Den Branch `feature/mf-pricelist-v1` herunterladen oder auschecken.
2. Den Ordner als `mf-pricelist` nach `wp-content/plugins/` kopieren. Darin muss unmittelbar `mf-pricelist.php` liegen. Alternativ einen ZIP mit diesem obersten Ordner erstellen und unter **Plugins → Installieren → Plugin hochladen** hochladen.
3. **MF PriceList** aktivieren. Der Menüpunkt ist für Administratoren mit `manage_options` sichtbar.
4. WordPress-Zeitzone unter **Einstellungen → Allgemein** prüfen; eine Ortszone wie `Europe/Berlin` berücksichtigt Sommerzeit.
5. Eine Seite mit dem Shortcode `[preisliste]` veröffentlichen.

## Kategorien und Leistungen

Unter **MF PriceList** zunächst Hauptkategorien (z.B. Damen) und dann Unterkategorien (z.B. Farbe, übergeordnete Kategorie Damen) anlegen. Genau zwei Ebenen sind möglich; Leistungen müssen einer Unterkategorie angehören. Hauptkategorien ohne Unterkategorien und leere Unterkategorien werden öffentlich ausgeblendet.

Leistungen haben einen Namen, eine optionale Kurzbeschreibung und eine Unterkategorie. Die Reihenfolge innerhalb derselben Elternebene oder Unterkategorie durch Ziehen am Griff oder über **Nach oben / Nach unten** ändern. Die Zuordnung über das Kategorieauswahlfeld ändern. Entfernen einer Kategorie ist erst nach Entfernen oder Umordnen ihrer Kinder/Leistungen möglich.

**Speichern** übernimmt das gesamte Dokument. Nicht gespeicherte Änderungen sind nur im Browser vorhanden. Fehler verhindern die gesamte Speicherung; über Zurück können Eingaben korrigiert werden. Maximal 200 Kategorien, 2000 Leistungen und 100 Preise pro Leistung; maximal 2 MB Dokumentgröße. Nur eine Person sollte gleichzeitig bearbeiten: Es gibt keine Bearbeitungssperre, der letzte Speichervorgang gewinnt.

## Preise und Zeitplanung

Jede Leistung unterstützt wahlweise **einen allgemeinen Preis** oder **bis zu drei Längenpreise: kurz, mittel, lang**. Bestehende Einzelpreise bleiben beim Update erhalten.

Im Editor unter **Preismodell** auf „Bis zu drei Preise“ wechseln. Vorhandene Preise inklusive geplanter Änderungen werden dabei „kurz“ zugeordnet. Über **Längenpreis / Preisänderung hinzufügen** weitere Einträge anlegen und deren **Haarlänge** auswählen. Nicht benötigte Längen einfach weglassen. Jede verwendete Länge braucht genau einen Grundpreis **ohne Datum**; weitere Einträge derselben Länge erhalten einen Gültigkeitszeitpunkt. Preisart und Hinweis sind je Länge frei wählbar. Geplante Preisänderungen zählen nicht als zusätzliche Längenvarianten.

Die Ausgabe beschriftet die Längenpreise in der Reihenfolge kurz, mittel, lang. Auf kleinen Bildschirmen stehen sie untereinander. Gleiche Hinweise werden auch über Längen hinweg je Unterkategorie zusammengefasst.

Für die Rückkehr zum allgemeinen Preis zuerst Einträge für mittel/lang entfernen (oder eine allein verbliebene Länge auf kurz umstellen); die kurz-Preise werden ohne Verlust ihrer Zeitplanung übernommen. Allgemeine Preise und Längenpreise können nicht innerhalb einer Leistung gemischt werden.

Je Preisvariante gibt es genau einen Grundpreis. Preisarten: Preisarten:

- **Festpreis:** z.B. `49,50` → 49,50 €.
- **ab-Preis:** z.B. `149` → ab 149,00 €.
- **Auf Anfrage:** kein numerischer Preis.

Über **Preisänderung hinzufügen** einen weiteren Preis mit **Gültig ab** eintragen. Es gilt immer der letzte bereits erreichte Zeitpunkt, einschließlich der angegebenen Minute. Vor dem ersten geplanten Wechsel gilt der Grundpreis. Vergangene Einträge bleiben erhalten; geplante Änderungen lassen sich durch Entfernen des betreffenden Preiseintrags zurücknehmen. Preis und optionaler Hinweis wechseln gemeinsam. Es gibt keinen Cron-Job.

Die Eingabe erfolgt in der beim Speichern aktuellen WordPress-Zeitzone und wird als Unix-Zeitpunkt gespeichert. Bei späterem Ändern der WordPress-Zeitzone bleiben gespeicherte Zeitpunkte zunächst erhalten; erneutes Speichern interpretiert die lokalen Eingaben in der neuen Zone. Vor einem Zeitzonenwechsel deshalb bestehende Termine prüfen. Nicht existente Uhrzeiten beim Sommerzeitwechsel werden abgewiesen. Die doppelte Stunde beim Herbstwechsel ist über das Eingabefeld nicht eindeutig auswählbar; Termine außerhalb dieser Stunde wählen.

Der optionale Preishinweis wird automatisch nummeriert. Identische, nach Bereinigung exakt gleiche Texte werden **je Unterkategorie** zusammengefasst; die Nummerierung startet dort jeweils bei 1. Hinweisnummern sind verlinkt. Keine frei wählbaren Fußnotenzeichen und kein HTML in Eingaben.

## Ausgabe und Caches

Der Shortcode liefert absichtlich nur einen Ladebereich. Eine öffentliche POST-Anfrage an WordPress `wp-admin/admin-ajax.php` lädt ausschließlich die aktuell gültige, serverseitig gerenderte Preisliste. Keine Nonce für diese rein lesende öffentliche Ausgabe; die Administration benötigt Berechtigung und Nonce.

Die Antwort setzt `no-store` und WordPress-No-Cache-Header; der Browser verwendet ebenfalls `cache: no-store`. Normale HTML-Seiten-Caches dürfen den Ladebereich cachen. **CDN, Firewall und Cache-Plugins müssen die POST-Anfrage `action=mfpl_public` an `admin-ajax.php` durchlassen und dürfen ihre Antwort nicht cachen.** Ein Proxy, der diese Regeln missachtet, lässt sich vom Plugin nicht verlässlich korrigieren.

Die Ausgabe aktualisiert sich spätestens nach 30 Sekunden und gezielt zum nächsten Preiszeitpunkt, außerdem beim erneuten Anzeigen/Fokussieren der Seite. Während der Prüfung werden bisherige Preise ausgeblendet. Bei Ladefehlern werden keine alten Preise weiter angezeigt, sondern eine Meldung mit Wiederholungsschaltfläche. Hintergrund-Tabs und unterbrochene Netzverbindungen können Aktualisierungen verzögern; beim Zurückkehren wird erneut geprüft. Die Genauigkeit hängt von Serverzeit und Browser-Timern ab.

**Öffentliche Preise benötigen JavaScript und die erreichbare AJAX-Schnittstelle.** Ohne JavaScript erscheint ein entsprechender Hinweis; Suchmaschinen ohne JavaScript erhalten keine Preisdaten. Das ist eine bewusste V1-Abwägung zugunsten cacheunabhängiger Preise. Keine Garantie minutengenauer Anzeige bei schlafenden Geräten. Seiten mit der Preisliste verursachen pro sichtbarer Instanz bis zu zwei regelmäßige Anfragen pro Minute sowie zusätzliche Fokus-/Seitenaufrufe.

Die Ausgabe verwendet Überschriften, Definitionslisten, verlinkte Fußnoten und Statusmeldungen. Auf schmalen Bildschirmen stehen Preise unter den Leistungen. Farben/Schriften erbt sie vom Theme; Kontrast und Überschriftenhierarchie im eingesetzten Theme prüfen.

## Daten und Deinstallation

Alle Daten liegen in der nicht automatisch geladenen WordPress-Option `mfpl_data`. Keine eigenen Tabellen, keine externen Dienste. Deaktivierung oder Löschen des Plugins entfernt absichtlich keine Preislisten-Daten. Für endgültiges Löschen nach einem Backup z.B. mit WP-CLI `wp option delete mfpl_data` ausführen. Kein eigener Import/Export und keine Datenmigration von Fremdplugins.

## Entwicklung und Prüfumfang

```sh
php tests/run.php
find . -name '*.php' -print0 | xargs -0 -n1 php -l
node --check assets/admin.js
node --check assets/public.js
```

Die eigenständigen Tests prüfen Domainregeln und Rendering mit kleinen WordPress-Funktionsdoubles: zwei Ebenen, ungültige Zuordnungen, Preisarten, Dezimalzahlen, inklusive Zeitgrenze, Zeitzone/Sommerzeitlücke, doppelte Zeitpunkte, Grundpreis, Fußnotendopplungen, HTML-Escaping und Leerzustand. Sie ersetzen keinen WordPress-Integrationstest. CI führt die Prüfungen für PHP 8.1, 8.3 und 8.4 aus.

Vor Produktiveinsatz die manuelle Checkliste in [docs/ai/v1.md](docs/ai/v1.md) in einer WordPress-Testinstallation durchgehen. Keine Zusage einer bereits erfolgten Geräte-, Screenreader- oder Theme-Abnahme.

Lizenz: [GPL-2.0-or-later](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html).
