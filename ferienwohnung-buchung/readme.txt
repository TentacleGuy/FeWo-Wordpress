=== Ferienwohnung Buchung ===
Requires at least: 6.4
Requires PHP: 8.1
Stable tag: 1.5.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Belegungskalender, Anfragenverwaltung, Formularbaukasten, E-Mails und PDF-Rechnungen für eine Ferienwohnung.

== Installation ==
1. ZIP hochladen und aktivieren.
2. Ferienwohnung > Einstellungen: Nachtpreis, Belegung, Datenschutz-Link und Gastgeberdaten eintragen.
3. [ferienwohnung_buchung] auf einer Seite einfügen.
4. Eigene Testbuchung, E-Mail-Zustellung und PDF prüfen.

Die vollständige Anleitung und Funktionsgrenzen stehen in ANLEITUNG.md.

== Changelog ==
= 1.5.0 =
Optionale Polylang-Anbindung mit dynamischer Sprachliste und Sprachwechsel im Formular-/Vorlagenbaukasten. Separate Übersetzungen bei gemeinsamem Aufbau und Design; globale Textbausteine und Datenschutz-Link pro Sprache. Systemtexte in Polylangs Stringübersetzungen. Gespeicherte Buchungssprache für späteren Mail-/Rechnungsversand, lokalisierte Kalenderdaten und sprachgebundene REST-Anfragen. Bestehende Inhalte und ausgestellte Rechnungen bleiben erhalten.
= 1.4.1 =
Abgelehnte und stornierte Buchungen erneut bestätigen, einzeln oder per Sammelaktion, mit erneuter Verfügbarkeitsprüfung und optionaler Mail. Entsperrte Sperrzeiten bleiben von Gästebestätigungen ausgeschlossen.
= 1.4.0 =
Mail-Signatur in der linken Vorlagenliste mit gemeinsamem Editor, Platzhaltern und Entwurfsvorschau. Kompakte Symbolaktionen für Buchungen, statusabhängige Aktionen und Mehrfachauswahl. Sammelaktionen mit Einzelprüfung und Ergebnis je Eintrag. Reversibler Papierkorb mit Verfügbarkeitsprüfung beim Wiederherstellen; Rechnungen bleiben erhalten.
= 1.3.0 =
Eigene Sidebar-Untermenüs und Dashboard mit Kennzahlen, nächster Anreise und Monatsstatistik. Direkte Buchungsaktionen mit Mail-Auswahl in der Tabelle. Telefonische Reservierungen im Kalender mit Preisprüfung, optionaler Mail und nachträglich bearbeitbaren Gästedaten. Elementeditor als Modal über Stiftsymbol, getrennte Verschiebegriffe und gruppierte Stiloptionen. Einheitliche Feldhöhen im Backend. Einstellbare Farben für vergangene/nicht auswählbare Tage ohne Hover. Zentrale WYSIWYG-Mail-Signatur. Eigenes CSS unter Design.
= 1.2.1 =
Korrektur wiederholter Speicherung im geöffneten Eigenschaftenbereich. Textstile, Schriftgrößen, Zeilenhöhen, Farben und Ausrichtung werden direkt auf die Texte und Eingabefelder angewendet. Überschriftengrößen bleiben auf Überschriften begrenzt. Unterstützte UIkit-Innenabstände und vollständige kleine Überschriften in Mail-/PDF-Vorlagen.
= 1.2.0 =
Erweiterter Formularbaukasten mit Inhalt/Stil/Erweitert, UIkit-Stilen, Klassen und sicheren Attributen. Globale Design-Einstellungen, Vorschauen, lokale Entwürfe, Rückgängig und Design-Export/Import. WYSIWYG-Mail-/PDF-Designer mit anklickbaren Platzhaltern, HTML-Mails mit Klartextalternative, PDF-Gestaltung und lokalem Mediathek-Logo. Bestehende Buchungen und ausgestellte Rechnungen bleiben erhalten.
= 1.1.0 =
Formularbaukasten mit Elementauswahl, zwei Spalten und Bereichen über/unter beiden Spalten. Kalender, Reisedaten, Preisberechnung und persönliche Felder verschiebbar. WYSIWYG-Editor für Gut zu wissen und freie Texte mit dynamischen Platzhaltern. Bestehende Texte und Felder werden übernommen.
= 1.0.3 =
Du-Ansprache, grüne freie/rote belegte/gelbe ausgewählte Tage, bearbeitbare Einleitungs- und Formulartexte, Entfernung leerer Code-Blöcke und des sichtbaren CAPTCHA-Hinweises. Spam-Schutz bleibt aktiv.
= 1.0.2 =
Sichtbare Datumsauswahl mit An-/Abreise und Zwischenzeitraum; Synchronisierung mit Datumsfeldern und Monatswechsel. Korrektur versehentlicher Code-Formatierung und stabilere Kalender-/Formulardarstellung.
= 1.0.1 =
UIkit/YOOtheme-Layout, responsive Spalten mit Container Queries, kleinere CSS-Datei und eigenes CSS in den Einstellungen.
= 1.0.0 =
Erste Version für eine Ferienwohnung.
