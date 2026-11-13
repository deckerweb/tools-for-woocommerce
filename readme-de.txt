=== Tools for WooCommerce ===
Contributors: deckerweb
Tags: woocommerce, catalog, cart, checkout, cleanup
Requires at least: 7.0
Tested up to: 7.1.4
Requires PHP: 8.3
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Werkzeuge für WooCommerce: aufgeräumter Shop, kostenlose Angebote, Katalog und Warenkorb-Regeln.

== Description ==

Plugin-Website
https://deckerwebplugins.com/de/plugins/tools-for-woocommerce/

Tools for WooCommerce ergänzt WooCommerce um eine ruhigere Arbeitsoberfläche und klar einstellbare Shop-Funktionen. Regulärer Verkauf, kostenlose Angebote, Präsentationskatalog und Bestellgrenzen lassen sich passend zum jeweiligen Shop konfigurieren.

* Werbung und Empfehlungen gezielt reduzieren; wichtige Warnungen erhalten.
* Kostenlose physische Produkte oder virtuelle Downloads sicher bestellen.
* Freiwillige Spenden-/Trinkgeldhinweise mit optionalem externem Link.
* Produkte als Katalog mit Kontaktweg präsentieren.
* Genau ein Artikel oder flexible Min-/Max-Regeln durchsetzen.
* Einstellungen je Website, Übernahme alter Konfiguration und JSON-Transfer.

== Installation ==

1. PHP ab 8.3, WordPress ab 7.0 und WooCommerce ab 11.1.2 verwenden.
2. Ein bisheriges Bereinigungs-Snippet oder die frühere Purify-WooCommerce-Testfassung deaktivieren.
3. Das ZIP unter Plugins → Installieren → Plugin hochladen installieren und aktivieren.
4. WooCommerce → Tools for WooCommerce öffnen. Ohne aktives WooCommerce liegt die Seite unter Einstellungen; die Shop-Funktionen pausieren, Einstellungen bleiben vorbereitbar.
5. Einen passenden Ausgangspunkt anwenden und die einzelnen Schalter anschließend speichern.

Beim manuellen Wechsel auf den neuen Pluginordner werden vorhandene alte Website-Einstellungen gelesen, solange noch keine neue Konfiguration gespeichert wurde. Es werden keine Produkte oder Bestellungen migriert. Alte und neue Implementierung nicht gleichzeitig betreiben.

== Frequently Asked Questions ==

= Kann der Shop weiterhin regulär verkaufen? =
Ja. „Aufgeräumter Shop“ erhält normalen Verkauf und Zahlungsarten.

= Wie erlaube ich genau ein Buch oder einen Artikel? =
Warenkorb-Regeln aktivieren und „Genau ein Artikel“ einschalten. Es ist ein Stück insgesamt zulässig.

= Werden Spenden im Checkout abgerechnet? =
Nein. Der Hinweis ist freiwillige Information; ein optionaler Link führt zu einem externen Unterstützungsangebot.

= Kann ich kostenlose Downloads und Versandprodukte mischen? =
Die kostenlosen Varianten sind getrennt. Im regulären Bestellbetrieb gelten weiterhin die WooCommerce-Funktionen.

= Gibt es ein Anfrageformular im Katalog? =
Der Katalog bietet einen konfigurierbaren Kontaktlink zu einer vorhandenen Seite oder einem Formular.

= Was passiert mit meinen bisherigen Einstellungen? =
Die neue Fassung liest die alte Konfiguration, bis neue Einstellungen gespeichert werden. Die alte Pluginfassung und das Snippet vorher deaktivieren.

= Was passiert bei Deinstallation? =
Einstellungen bleiben standardmäßig erhalten. Nur bei aktivierter Löschoption werden eigene Website-Einstellungen entfernt; Produkte und Bestellungen bleiben erhalten.

== Changelog ==

= 1.0.0 — 2026-11-13 =

- Dauerhafte Vorschauoptionen für Bestelldaten und externe Bilder. Fehlende deutsche Beschriftungen in der Einstellungsübersicht ergänzt.

- Shop-Einstellungsübersicht mit Direkteinstiegen, optionalem Menüeintrag und Prüfungen für Shop-Seiten und Versandzonen.
- E-Mail-Designer mit WooCommerce-Branding-Übernahme, Bestätigungsmail und Germanized-Vorlagen.
- Kaufbarkeits-, Gutschein- und Bestellstatusprüfung sowie kompaktere Admin-Navigation.

= 0.3.0 — 2026-11-10 =
* Neu: Katalogbetrieb mit optional ausgeblendeten Preisen, Erklärung und Kontaktlink.
* Neu: Warenkorb-Regeln für genau einen Artikel, Mindest-/Höchstmengen, Produktwerte und Mengenschritte.
* Neu: Optionale freiwillige Unterstützungshinweise für kostenlose physische Produkte und Downloads.
* Neu: Export/Import der Einstellungen und wahlweises Löschen der Website-Einstellungen.
* Verbessert: Umbenennung in Tools for WooCommerce mit neuem Pluginordner, neuer Textdomain und neuen Code-Präfixen; bisherige Einstellungen bleiben lesbar.
* Verbessert: Gemeinsame Update- und Plugin-Library-Komponenten aktualisiert; benötigt PHP 8.3.
* Behoben: Regulären Verkauf als Standard erhalten und Speicherbestätigung einmalig anzeigen.

= 0.2.0 — 2026-10-06 =
* Neu: Voreinstellungen Aufgeräumter Shop, Kostenlose Angebote und Individuell mit getrennten Versand- und Downloadvarianten.
* Verbessert: Werbung, Admin-Einstiege, Namen, Icons und Bestellboxen separat steuern; alte Konfiguration erhalten.
* Verbessert: Kostenlose Produkttypen, konfigurierte Downloads, Versand und Gebühren ungleich null prüfen.

= 0.1.0 — 2026-10-06 =
* Neu: Reversible WooCommerce-Bereinigung und geschützte kostenlose physische Bestellungen.
* Neu: Website-Einstellungen, optionale klassische Checkout-Texte und gemeinsame deckerweb-Anbindungen.
