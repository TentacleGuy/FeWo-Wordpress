# Ferienwohnung Buchung

Eigenständiges WordPress-Plugin für **eine Ferienwohnung**, Version 1.5.2.

## Buchungsaktionen (1.5.2)

Stornieren verwendet ein orangefarbenes Kalendersymbol, Ablehnen ein rotes Kreuz. Über der Buchungstabelle erklärt eine Legende alle Symbole. In der Detailansicht tragen die Aktionen zusätzlich sichtbare Beschriftungen; das Mail-Häkchen ist mit „Status-Mail senden“ beschriftet und korrekt ausgerichtet.

## Installation

1. `dist/ferienwohnung-buchung-1.5.2.zip` in WordPress unter **Plugins → Installieren → Plugin hochladen** auswählen und aktivieren.
2. **Ferienwohnung → Einstellungen** öffnen. Nachtpreis, maximale Belegung, Unterkunftsname, Gastgeber-E-Mail und Datenschutz-Link ausfüllen.
3. Für Rechnungen zusätzlich vollständige Rechnungssteller-Adresse, Steuerkennung, passenden Steuer-/Rechnungshinweis und Bankverbindung hinterlegen. Umsatzsteuersatz passend zur eigenen Situation einstellen. Ohne diese Rechnungsangaben ist die Ausstellung gesperrt.
4. Auf einer Seite einen Shortcode-Block mit `[ferienwohnung_buchung]` einfügen. Für einen Kalender ohne Formular: `[ferienwohnung_kalender]`.
5. Eine eigene Testanfrage durchführen, bestätigen, die PDF-Vorschau prüfen und einen echten E-Mail-Zustelltest beim eigenen Hoster durchführen.

Voraussetzungen: WordPress ab 6.4, PHP ab 8.1 mit DOM und mbstring (GD empfohlen), MySQL/MariaDB mit `GET_LOCK`, HTTPS und moderner Browser mit Web Workers/Web Crypto. Die fertige ZIP enthält Dompdf; Composer ist auf dem Webserver nicht erforderlich. Getestet mit WordPress 7.1.2, PHP 8.3.35, MariaDB 11.4.5 und Dompdf 3.1.6. Ältere WordPress-/PHP-Versionen sind nicht separat getestet. SQLite wird nicht unterstützt. Multisite-Netzwerkaktivierung ist nicht implementiert; pro Website aktivieren.

## Englische Texte und vollständiger Baukasten (1.5.1)

Englische Gasttexte sind enthalten: Standardformular einschließlich Kalenderlegende, Preisdetails, „Gut zu wissen“, Hausregeln, Ortstaxenhinweis, System-/Fehlermeldungen sowie alle Standard-Mail- und PDF-Vorlagen. Beim Auswählen einer englischen Polylang-Sprache erscheinen sie direkt; eine manuelle Übertragung ist nicht erforderlich. Die Sprache wird über ihre Locale erkannt, auch etwa `en_US` statt `en_GB`. Allgemeine Signatur-Grüße werden ebenfalls übersetzt, Kontaktdaten und Platzhalter bleiben erhalten. Die Verwaltungsoberfläche bleibt deutsch; der Sprachschalter wählt die Inhaltssprache.

Die mitgelieferten Übersetzungen stehen im Formular- bzw. Vorlageneditor zum Anpassen bereit. Bereits gespeicherte eigene Übersetzungen haben Vorrang. Deutsche Inhalte, Aufbau und Design bleiben unverändert. Bekannte Standardtexte werden auch innerhalb abweichender HTML-Formatierung erkannt. Frei formulierte, unbekannte Texte werden nicht durch inhaltlich andere Standardbedingungen ersetzt; sie erscheinen in der Liste fehlender Übersetzungen. Namen, individuelle Kontakt-/Rechnungsangaben und Datenschutzseiten sind keine automatisch übersetzten Dokumente. Für ausschließlich auf der Website gespeicherte eigene Texte wird deren Inhalt bzw. Export benötigt.

Systemtexte werden beim ersten Backend-Aufruf durch einen Administrator in Polylangs Stringübersetzungen ergänzt, ohne bestehende Übersetzungen zu überschreiben. Ohne verfügbare Polylang-Speicheranbindung greift weiterhin die mitgelieferte englische Ausgabe. Grundlage: [Polylang-Funktionsreferenz](https://polylang.pro/documentation/support/developers/function-reference/).

Die linke Elementauswahl wird im Übersetzungsmodus wieder angezeigt. Ein Hinweis und **Aufbau bearbeiten** führen zur Ausgangssprache für gemeinsame Strukturänderungen; Hinzufügen/Verschieben bleibt dort geschützt. Kalender und „Gut zu wissen“ in der linken Formularspalte bleiben über den Stift übersetzbar. Auf schmalen Bildschirmen stehen die Formularspalten untereinander.

Update-ZIP installieren, vorhandenes Plugin ersetzen und anschließend YOOtheme-/Seiten-Cache leeren. Keine Buchungen oder bisherigen Vorlagen löschen.

Geprüft für 1.5.1: 363 neue Übersetzungsprüfungen plus 406 bestehende Prüfungen (insgesamt 769), einschließlich Platzhaltern, unveränderten Ausgangsdaten, bestehenden eigenen Übersetzungen, Polylang-Stringeditor und älteren englischen Buchungen. Zusätzlich wurde der englische Baukasten im Browser auf Desktopbreite samt Elementauswahl, beiden Formularspalten, Feld-Dialog sowie Speichern/Neuladen geprüft. Testmails blieben lokal.

## Polylang und weitere Sprachen (ab 1.5.0)

Polylang ist optional und wird nicht mitgeliefert. Die Integration wurde mit Polylang **3.8.10** und Deutsch, Englisch (en_GB) sowie Französisch getestet. Alle von Polylang angelegten Sprachen werden dynamisch angeboten; es gibt keine fest eingebaute Begrenzung auf Deutsch/Englisch.

1. Gewünschte Sprachen in **Polylang → Sprachen** anlegen. Auf übersetzten WordPress-Seiten denselben Plugin-Shortcode einsetzen und die Seiten in Polylang miteinander verknüpfen.
2. **Ferienwohnung → Formularbaukasten** oder **Vorlagen** öffnen. Sprache oben im Polylang-Schalter oder in der Sprachleiste des Plugins wählen. Die Anzeige **Du bearbeitest: …** zeigt die aktive Inhaltssprache. „Alle Sprachen“ verwendet die Ausgangssprache.
3. Feldbeschriftungen, Auswahloptionen (gleiche Anzahl/Reihenfolge), Hilfetexte, Eingabe-Platzhalter, Kalenderlegende, Preiszusammenfassung, Überschriften und freie Texte über den Stift übersetzen. WYSIWYG bleibt für freie Texte und „Gut zu wissen“ verfügbar. Vorhandene textliche Attribute wie `title`, `aria-label` und `uk-tooltip` lassen sich ebenfalls übersetzen; technische Attributnamen bleiben gemeinsam.
4. Mailbetreff, Mailinhalt, Signatur und alle drei PDF-Bereiche unter **Vorlagen** für jede Sprache gestalten. Die Platzhalternamen wie `{arrival}` bleiben unverändert. Vorschau und Testmail verwenden die ausgewählte Bearbeitungssprache. „Vorlagen speichern“ speichert nur deren Texte; PDF-Gestaltung bleibt gemeinsam.
5. In der Sprachleiste **Gemeinsame Textbausteine und Datenschutz-Link übersetzen** aufklappen: Unterkunftsname, Hausregeln, Ortstaxenhinweis, Rechnungshinweis und Datenschutz-URL pflegen. Diese Bausteine werden separat mit **Textbausteine speichern** gespeichert und von Platzhaltern verwendet. Den passenden Datenschutz-Link je Sprache ausdrücklich hinterlegen.
6. Kurze Systemmeldungen, dynamische Fehlertexte und feste PDF-Tabellenbeschriftungen unter **Polylang → Stringübersetzungen**, Gruppe **Ferienwohnung · Systemtexte**, anpassen oder für weitere Sprachen übersetzen. Englisch ist enthalten. Platzhalter wie `{nights}` oder `{field}` erhalten. Kalender-Monatsnamen und Wochentage folgen automatisch der Polylang-Locale.

**Eine zusätzliche Sprache:** In Polylang anlegen, dann in beiden Baukästen auswählen und die Inhalte sowie Systemtexte übersetzen. Kein Programmieren erforderlich. Englisch erhält ab 1.5.1 die mitgelieferten Standardübersetzungen. Andere Sprachen und unbekannte eigene Texte verwenden bei fehlender Übersetzung die Ausgangssprache. Der aufklappbare Hinweis listet fehlende Texte und geänderte Ausgangstexte. Nach dem Speichern und Neuladen wird er aktualisiert. Bereits gespeicherte Übersetzungen bleiben bei einer Änderung des Ausgangstextes zur Prüfung erhalten.

**Aufbau und Design:** Elemente hinzufügen/entfernen, Feldkennungen und Pflichtstatus, Reihenfolge, Klassen, Farben, Spalten und PDF-Stil in der Ausgangssprache bearbeiten. Übersetzungsansichten sperren diese Strukturänderungen. Textübersetzungen werden anhand der Elementkennung gespeichert und bleiben beim Verschieben erhalten. Lokale Entwürfe sind nach Website, Benutzer und Sprache getrennt. Der bestehende Designexport enthält die gemeinsame Gestaltung mit Ausgangstexten, keine komplette Übersetzungssicherung; diese gehört zur WordPress-Datenbanksicherung.

**Buchung und Versand:** Neue Anfragen speichern die Besuchersprache sowie die zum Anfragezeitpunkt gültigen Haus-/Ortstaxenhinweise. Bestätigung, Ablehnung, Stornierung, Rechnungsmail und neu ausgestellte Rechnung verwenden diese Sprache auch dann, wenn du später im deutschen Backend arbeitest. Bei telefonischen Buchungen gibt es eine Auswahl **Buchungssprache**. Die Sprache steht in den Buchungsdetails. Interne Gastgeberbenachrichtigungen bleiben deutsch. Bestehende Buchungen ohne Sprachkennung verwenden die Ausgangssprache; bereits ausgestellte PDFs bleiben unverändert.

**Bestehende Inhalte:** Die bisherigen Einstellungen bleiben Ausgangsdaten. Bei der ersten Polylang-Anbindung wird eine vorhandene deutsche Sprache als Ausgangssprache gespeichert; fehlt sie, wird Polylangs Standardsprache verwendet. Die Sprache deshalb vor dem ersten Bearbeiten korrekt in Polylang einrichten. Spätere Änderungen der Polylang-Standardsprache verschieben bestehende Übersetzungen nicht. Polylang-Sprachkennungen nachträglich nicht umbenennen; sie dienen als Zuordnung für Buchungen und Übersetzungen.

Für sprachabhängige Zahlen-/Datumsformate in PHP/PDF wird die PHP-Erweiterung **intl** empfohlen. Ohne sie werden fremdsprachige Datumswerte eindeutig als `YYYY-MM-DD` ausgegeben; Eurobeträge behalten das bisherige Zahlenformat. Browser-Kalender und dynamische Preisberechnung nutzen die jeweilige Locale. Für zusätzliche Schriftsysteme muss die verwendete PDF-Schrift die Zeichen unterstützen; die enthaltenen DejaVu-Schriften decken nicht jede Sprache ab.

Geprüft: **406 automatisierte Prüfungen**, davon 28 mit echtem Polylang einschließlich drei unabhängiger Sprachen, Schutz der Ausgangsdaten, gemeinsamer Gestaltung, Sprachübergabe an REST, öffentlicher Anfrage, späterem Mailversand, Signatur und PDF-Inhalt. Zusätzlich Browserprüfung für Speichern/Neuladen, Sprachwechsel, Mailvorschau und Kalender-Locale. Testmails wurden lokal abgefangen.

## YOOtheme / UIkit und eigenes CSS

Das Frontend nutzt UIkit 3 aus dem vorhandenen YOOtheme: Grid, Formulare, Buttons, Abstände und Typografie. Das Plugin lädt keine zweite UIkit-Version. Bei Themes ohne UIkit fehlt die entsprechende Gestaltung. Die Inhalte übernehmen den Farbkontext der umgebenden Theme-Sektion; die bisherigen fest vorgegebenen weißen Hintergründe entfallen.

Ab UIkit-Desktopbreite (Standard: 960 px) stehen Kalender und Formular nebeneinander. Unter 760 px tatsächlicher Plugin-Breite werden sie auch in einer schmalen Desktop-Builder-Spalte gestapelt. Unter 400 px Plugin-Breite stehen auch Datums-/Personenfelder einzeln untereinander. Container Queries benötigen einen aktuellen Browser. Im YOOtheme-Builder einen Shortcode in einem ausreichend breiten Element platzieren; für die Desktop-Zweispaltigkeit mindestens 760 px Inhaltsbreite vorsehen.

Den Shortcode als normalen Text bzw. im Shortcode-Block einfügen, nicht als formatierten Code. Version 1.0.2 entfernt versehentliche Code-/Preformatierungen um das aktive Plugin sowie darin und stabilisiert die Kalendernavigation und Wochentagszeile.

Freie Tage sind hellgrün, belegte Tage hellrot und ausgewählte Tage kräftig gelb mit dunkler Schrift. Anreise und Abreise sind zusätzlich umrandet; halbe Belegungen bleiben diagonal rot/grün dargestellt. Die Auswahl bleibt beim Monatswechsel erhalten und wird bei Änderungen der Datumsfelder aktualisiert. Bei Eingabe einer Anreise wechselt der Kalender zum entsprechenden Monat. Diese Markierung zeigt den gewünschten Zeitraum; Verfügbarkeit und Mindestaufenthalt werden weiterhin separat geprüft. Die Auswahl bestätigt keine Buchung.

Ab Version 1.0.3 verwenden die Plugin-Texte und Standardvorlagen die Du-Ansprache. Unverändert gespeicherte Standardvorlagen und die Standardbeschriftung „Ihre Nachricht“ werden beim Update angepasst. Individuell bearbeitete Mail-/PDF-Vorlagen und Feldbeschriftungen bleiben erhalten; falls sie noch siezen, kannst du sie unter Vorlagen bzw. Formular bearbeiten. Ausgestellte Rechnungs-PDFs bleiben unverändert. Der erklärende CAPTCHA-Absatz entfällt; der Spam-Schutz bleibt aktiv. Leere Code-Blöcke direkt vor/nach dem eingebundenen Formular werden ebenfalls entfernt.

Unter **Ferienwohnung → Design → Darstellung / eigenes CSS** eigenes CSS ohne HTML-/style-Tags einfügen. Das CSS wird nach dem Plugin-CSS im Frontend eingebunden, nicht im Backend oder in Rechnungen. Für Builder-Kompatibilität wird die kleine CSS-Datei einschließlich der Anpassungen auf öffentlichen Seiten geladen; deshalb Selektoren immer auf das Plugin begrenzen, beispielsweise:

~~~css
.fwb-widget .fwb-request { border: 1px solid currentColor; }
.fwb-widget .fwb-help { font-style: italic; }
~~~

Ein leeres Feld speichern entfernt die Anpassungen. CSS ist auf 50.000 Zeichen begrenzt. HTML wird abgewiesen. Das vorhandene Administrationsrecht und der WordPress-Nonce schützen die Speicherung.

**Update:** ZIP unter Plugins → Installieren → Hochladen auswählen und das vorhandene Plugin ersetzen. Daten, Vorlagen und Einstellungen bleiben erhalten. Anschließend YOOtheme-/Optimierungs-/Seiten-Cache leeren und den Browser neu laden, damit alte Styles nicht weiterverwendet werden.

Die neue Darstellung wurde lokal mit UIkit 3.25.25 in einem dunklen Farbkontext, einer schmalen Desktop-Spalte sowie mobilen Breiten geprüft. Die konkrete YOOtheme-Konfiguration der Zielwebsite war nicht verfügbar. UIkit-Referenzen: [Grid](https://getuikit.com/docs/grid) und [Formulare](https://getuikit.com/docs/form).

## Formularbaukasten mit zwei Spalten (1.1.0)

Unter **Ferienwohnung → Formularbaukasten** stehen links die verfügbaren Elemente und daneben die linke und rechte Spalte. Zusätzliche Bereiche oberhalb und unterhalb gehen über beide Spalten.

- Ein Element aus der Auswahl in eine Spalte ziehen. Vorhandene Elemente am Griff verschieben. Alternativ einen Zielbereich wählen und das Element anklicken; Pfeile und Bereichsauswahl ermöglichen die Bedienung ohne Ziehen.
- Das **Stiftsymbol** öffnet die Eigenschaften als Dialog mit **Inhalt**, **Stil** und **Erweitert**. **Fertig / schließen** oder Escape schließen den Dialog. Der Griff dient ausschließlich zum Verschieben. Änderungen mit **Baukasten speichern** übernehmen; Schließen allein veröffentlicht sie nicht.
- **Gut zu wissen** und freie Texte mit dem integrierten WordPress-WYSIWYG-Editor bearbeiten: Absätze, Überschriften, Fett/Kursiv, Listen und Links. Die Beschriftung kann leer bleiben, um die Überschrift auszublenden. HTML wird auf erlaubte WordPress-Inhalte begrenzt.
- Kalender, Überschriften, freie Texte, Anreise, Abreise, Gästezahl, ortstaxenpflichtige Gäste, Preisberechnung, persönliche Felder, Zustimmung und Absende-Button sind im Baukasten enthalten. Beschriftungen und ganze/halbe/Drittel-/Zweidrittel-Breiten sind anpassbar. Beim Kalender sind Hinweis und Legendenbeschriftungen editierbar. Bei der Preisberechnung sind die Einleitung und Ergebnisvorlagen bearbeitbar.
- Zusätzliche Text-, E-Mail-, Telefon-, Nachrichten-, Auswahl- und Checkbox-Felder sind beliebig ergänzbar (maximal 30 Kontaktfelder insgesamt). Auswahloptionen zeilenweise eingeben.
- Anreise, Abreise, Gästezahlen, Preisberechnung, Zustimmung, Absenden sowie Name, E-Mail und Rechnungsadresse bleiben notwendig. Sie können verschoben und umbenannt, aber nicht entfernt werden. Kalender und „Gut zu wissen“ lassen sich entfernen und aus der Elementauswahl wieder hinzufügen.
- Zum Abschluss **Baukasten speichern**. Alle Eingaben werden vor dem Speichern geprüft. Auf Mobilgeräten folgt die rechte Spalte auf die linke; bei sehr schmalen Bereichen werden auch halbe Felder gestapelt.

**Gut zu wissen:** Beim gleichnamigen Baustein in der linken Spalte das Stiftsymbol anklicken und den Text im visuellen Editor ändern. Platzhalter wie {night_price}, {min_nights}, {deposit_percent}, {rules} oder {property_name} übernehmen automatisch die aktuellen Werte aus den Einstellungen. Die verfügbaren Platzhalter werden beim Editor angezeigt. Eigener Text ändert keine Preisberechnung oder Buchungsregeln; diese bleiben unter Einstellungen. Der Datenschutzlink wird über {privacy_link} eingebunden.

Beim Update werden vorhandene Kontaktfelder und die bisher unter Einstellungen hinterlegten Einleitungs-/Formulartexte als Ausgangslayout übernommen. Sobald der Baukasten gespeichert wurde, werden sichtbare Texte und Anordnung dort gepflegt. Preise und Regeln bleiben weiterhin in den Einstellungen. Ausgestellte Rechnungen und vorhandene Buchungen werden nicht verändert.


## Erneut bestätigen (1.4.1)

Abgelehnte und stornierte Gästebuchungen können über **Annehmen** erneut bestätigt werden, einzeln oder per Sammelaktion. Der Zeitraum wird unter Datenbanksperre erneut auf Verfügbarkeit geprüft. Bei einem Konflikt bleiben Status und Mailversand unverändert. Die Mail-Checkbox steuert die erneute Bestätigungsmail. Entsperrte Sperrzeiten werden nicht als Gästebuchung bestätigt. Zehn zusätzliche automatisierte Prüfungen decken diese Abläufe einschließlich Symbolbuttons und Mail-Auswahl ab.

## Kompakte Aktionen und integrierte Signatur (1.4.0)

**Buchungen:** Kleine Symbolbuttons stehen nebeneinander. Mit Mauszeiger bzw. Screenreader ist die jeweilige Aktion beschriftet: Bearbeiten (Stift), Annehmen (Häkchen), Stornieren (Kreuz im Kreis), Ablehnen (Kreuz), Löschen (Papierkorb), Entsperren (offenes Schloss) und Wiederherstellen (Zurück-Pfeil). Angezeigt werden nur passende Aktionen für den aktuellen Status. Die Checkbox beim Briefsymbol steuert die Status-Mail dieser Zeile; beim Bestätigen ggf. auch die Rechnungsmail. Bearbeiten öffnet die bisherigen Buchungsdetails.

**Mehrfachauswahl:** Gewünschte Zeilen markieren oder alle Einträge der aktuellen Seite auswählen, Sammelaktion wählen und **Anwenden** anklicken. Maximal 30 Einträge pro Vorgang. Die Mail-Auswahl oberhalb gilt für die Sammelaktion und ist zunächst ausgeschaltet; die Mail-Häkchen einzelner Zeilen ändern sie nicht. Jede Buchung wird einzeln geprüft. Konflikte und nicht passende Status werden als Einzelergebnisse gemeldet; andere gültige Aktionen werden trotzdem ausgeführt. Filter und Seitenzahl bleiben erhalten.

**Löschen ist reversibel:** Einträge werden in den Papierkorb verschoben und aus „Alle“ ausgeblendet. Eine dadurch entfernte aktive Buchung/Sperre gibt den Zeitraum frei. Gästedaten, Zahlungen und bereits ausgestellte Rechnungen bleiben erhalten; es werden keine Mails versendet. Über **Papierkorb → Wiederherstellen** wird der ursprüngliche Status zurückgesetzt. Für bestätigte Buchungen und Sperren wird vorher geprüft, ob der Zeitraum noch verfügbar ist. Entsperren betrifft ausschließlich Sperrzeiten. Papierkorb, Wiederherstellen und Entsperren versenden auch bei angehaktem Mailfeld keine Mail. Es gibt keine endgültige Löschung in dieser Version.

**Mail-Signatur:** Der separate Editor entfällt. Links bei den Mail- und PDF-Vorlagen steht **Mail-Signatur** mit demselben WYSIWYG-Editor, anklickbaren Platzhaltern, Vorschau und Testversand. Mit **Vorlagen speichern** wird auch die Signatur gespeichert. Bereits vorhandene Signaturen werden übernommen. Ein leerer Signaturtext deaktiviert das Anhängen. Mailvorschauen verwenden den aktuellen Signaturentwurf, ohne ihn automatisch zu speichern; PDFs bleiben davon unabhängig.

Geprüft mit 367 automatisierten Prüfungen einschließlich Überschneidungen bei Sammelbestätigungen, gemischten Status, doppelten/ungültigen IDs, Papierkorb/Wiederherstellung und Signaturentwürfen. Zusätzlich lokale Browserprüfung der Symbolzeilen, Sammelauswahl, Papierkorb sowie Speicherung und Vorschau der Signatur. Keine externen Testmails versendet.

## Einfachere Verwaltung (1.3.0)

Alle Bereiche stehen unter **Ferienwohnung** als eigene WordPress-Untermenüs bereit: Dashboard, Buchungen, Kalender, Formularbaukasten, Vorlagen, Design und Einstellungen. Bestehende Links mit `tab=` funktionieren weiterhin.

- **Buchungen:** Bestätigen, Ablehnen und Stornieren direkt in der Tabellenzeile. Die sichtbare Mail-Auswahl gilt nur für diese Aktion; bei Bestätigung gegebenenfalls auch für die automatische Rechnungsmail. Stornieren/Ablehnen werden vor Ausführung nochmals bestätigt. Ohne gültige Gastadresse ist Mailversand nicht möglich. In **Details / bearbeiten** kannst du Name, Kontakt- und Rechnungsdaten ergänzen, Zahlungen erfassen und Rechnungen erstellen. Gästedatenänderungen versenden keine Mail und ändern keine bereits ausgestellte Rechnung.
- **Kalender → Reservierung anlegen:** Telefonische Buchung oder offene Anfrage mit Gästedaten, Zeitraum und Personenzahl erfassen. **Preis und Verfügbarkeit prüfen** zeigt den Preis vor dem Anlegen; beim Speichern wird die Verfügbarkeit erneut unter einer Datenbanksperre geprüft. Die geltenden Mindestnächte, Gästegrenzen und Preise gelten auch hier. Die Mail-Option ist zunächst ausgeschaltet, unabhängig von den allgemeinen automatischen Mails. Eine E-Mail-Adresse ist nur bei Mailversand erforderlich. Offene Anfragen blockieren noch keine Tage. Die Erfassung wird als manuell protokolliert und stellt keine Online-Datenschutz-Zustimmung dar.
- **Automatische Rechnung:** Ist die bisherige Option aktiviert, wird bei manueller Bestätigung ebenfalls eine Rechnung versucht. Fehlen Rechnungsangaben, bleibt die Buchung bestätigt; die Hinweismeldung erklärt, was noch zu ergänzen ist. Eine Rechnung erfordert eine Gast-Rechnungsadresse. Ohne Mail-Häkchen wird auch keine Rechnungsmail versendet.
- **Formularbaukasten:** Der Stift öffnet einen großen, auch mobil bedienbaren Dialog. Die Stiloptionen sind in Schrift, Textspalten, Abstände/Breiten, Hintergrund/Rahmen und feldabhängige Optionen gruppiert. Der Verschiebegriff öffnet keinen Editor mehr. Wiederholtes Speichern im Dialog wird unterstützt.
- **Design:** Vergangene Tage erhalten eigene Hintergrund- und Schriftfarben. Für nicht auswählbare belegte Tage sind zusätzliche Farben möglich; leere Werte übernehmen die Belegungsfarben. Nicht auswählbare Tage reagieren nicht auf Hover. **Eigenes CSS** befindet sich jetzt ebenfalls unter Design und wird dort mit einem eigenen Speichern-Button übernommen. Bestehendes CSS bleibt erhalten.
- **Vorlagen → Mail-Signatur** in der linken Vorlagenliste: Im gemeinsamen WYSIWYG-Editor gestalten und mit **Vorlagen speichern** übernehmen. Sie wird einmal an neue Gast-, Rechnungs-, Test- und Gastgebermails angehängt; Klartextmails erhalten eine Klartextversion. Die Mailvorschau nutzt seit 1.4.0 auch den aktuellen, noch ungespeicherten Signaturentwurf. Platzhalter werden wie in den Mailvorlagen ersetzt. Eine leere Signatur deaktiviert den Zusatz; bereits in einzelnen Vorlagen eingetragene Grußtexte werden nicht automatisch entfernt. PDFs erhalten keine Mail-Signatur.
- **Dashboard:** Vier Übersichtsboxen, nächste bestätigte Anreise und Monatsstatistik des aktuellen Kalenderjahrs mit Anreisen, gebuchten Nächten und prozentualer Belegung. Monatsübergreifende Aufenthalte werden aufgeteilt. Anfragen, Sperren und Stornierungen zählen nicht als gebuchte Nächte. Zusätzlich gibt es zwei Widgets im WordPress-Dashboard, nur für Administratoren.

Einzeilige Text-, Zahlen-, Auswahl-, Datums- und Zeitfelder im Plugin-Backend sind einheitlich 40 px hoch. Mehrzeilige Texte und Farbauswahl bleiben eigene Steuerelemente. Buchungen, Layout, Mail-/PDF-Vorlagen und Design-Einstellungen bleiben beim Update erhalten.

Geprüft: 343 automatische Prüfungen einschließlich neuer manueller Buchungen, Überschneidungen, optionaler Mails, Signaturen, Gästedaten und Monatsgrenzen; zusätzlich lokale Browserprüfungen der Tabellenaktionen, des Elementdialogs und der Feldhöhen. Es wurden keine externen Testmails verschickt. Die individuelle YOOtheme-Installation der Zielwebsite wurde nicht geprüft.

## Korrektur der Stileinstellungen (1.2.1)

Nach dem ersten Speichern bleiben die Eigenschaftenfelder mit dem aktuellen Bearbeitungsstand verbunden. Weitere Änderungen im geöffneten Element werden zuverlässig mitgespeichert. Änderungen während eines laufenden Speichervorgangs bleiben als ungespeicherter Entwurf erhalten.

Textstil, Textfarbe, Ausrichtung, Schriftgröße und Zeilenhöhe erreichen nun auch die Texte und Eingabefelder innerhalb der Elemente. Eine ausgewählte Überschriftengröße gilt nur für Überschriften. Spezifische Feld- und Buttonfarben haben Vorrang vor der allgemeinen Textfarbe. Die bisher wirkungslose Innenabstandsoption „Mittel“ wird auf den normalen UIkit-Innenabstand umgestellt. Kleine Überschriften (H5/H6) und vorformatierter Text bleiben in Mail- und PDF-Vorlagen erhalten.

Bereits gespeicherte Einstellungen bleiben erhalten. Werte, die wegen des bisherigen Speicherfehlers verloren gingen, bitte erneut auswählen und speichern. Anschließend den Website- und Browser-Cache leeren.

Geprüft mit 307 automatisierten Prüfungen einschließlich aller angebotenen Stil-Auswahlwerte sowie wiederholtem Speichern, erneutem Öffnen und tatsächlicher Schrift-Darstellung im lokalen UIkit-Browsertest. Die individuelle YOOtheme-Konfiguration der Zielwebsite wurde nicht geprüft.

## Gestaltung und Vorlagen-Designer (1.2.0)

**Formularbaukasten:** Die Elementauswahl bleibt links, die Anordnung daneben. Seit Version 1.3.0 öffnen sich die Eigenschaften über das Stiftsymbol in einem Dialog. Im Reiter **Stil** lassen sich – passend zum Element – Textstil, Textfarbe, Ausrichtung, Schriftgröße, Zeilenhöhe, Textspalten mit Breakpoint/Trenner, Initiale, Überschriftengröße, Abstände, Hintergrund, Rahmen, Rundung und Schatten einstellen. UIkit-Klassen übernehmen die YOOtheme-Stile. Eine leere Einstellung übernimmt das Theme bzw. die globale Vorgabe.

Bei Eingabefeldern kommen Feldgröße, Farben, Platzhalter, Hilfetext, automatische Vervollständigung, Bildschirmtastatur sowie bei Textfeldern Maximallänge und bei mehrzeiligen Feldern Zeilenzahl dazu. Die Maximallänge wird auch serverseitig geprüft. Absende-Buttons erhalten Stil, Größe, Breite und normale/Hover-Farben. Grundbreite und Breiten ab Small/Medium/Large steuern das responsive Layout; sehr schmale Plugin-Container stapeln weiterhin die Felder.

Unter **Erweitert** stehen eine eigene Container-ID, Container-Klassen, Klassen für die Eingabe bzw. den Button und getrennte Attributlisten. Erlaubt sind passende data-/aria-Attribute, title, uk-tooltip und uk-icon. ID-Doppelungen, Ereignisattribute wie onclick sowie Überschreiben interner Feldnamen/-typen sind gesperrt. Kontrollierte Eingabeeinstellungen wie autocomplete werden unter Stil gepflegt. Als HTML-Container sind div, section, aside, address und footer wählbar.

Optionale Elemente können ausgeblendet werden; Textblöcke, Überschriften und optionale persönliche Felder lassen sich duplizieren. Notwendige Buchungsfelder bleiben erhalten. **Rückgängig/Wiederholen** verwaltet bis zu 50 Bearbeitungsstände im geöffneten Editor. Ungespeicherte Änderungen werden als lokaler Entwurf im jeweiligen Browser gesichert und können beim nächsten Öffnen ausdrücklich geladen werden. Das ist keine serverseitige Versionshistorie. Beim Verlassen mit Änderungen erscheint die Browser-Rückfrage.

**Vorschau aktualisieren** zeigt das aktuelle Formular ohne vorheriges Speichern, mit Desktop-/Tablet-/Smartphone-Breite und hellem/dunklem Hintergrund. Die Vorschau lädt das installierte Theme und das Plugin. Die umgebende YOOtheme-Seitensektion wird dabei nicht nachgebaut; für den endgültigen Eindruck die eingebundene Website kontrollieren. In dieser Vorschau werden keine Buchungen abgeschickt.

**Ferienwohnung → Design:** Kalenderfarben für frei/belegt/ausgewählt, Zahlen, Rahmen, heutigen Tag und Hover; Formular-/Beschriftungs-/Hilfetextfarben; Buttonfarben samt Hover; Fokus-/Statusfarben; Rundungen und Abstände sind zentral einstellbar. Die halben Tage kombinieren die Farben für frei und belegt. **Vorgaben zurücksetzen** lädt zunächst nur einen Entwurf, der anschließend gespeichert werden muss.

**Exportieren/Importieren** überträgt die gespeicherte Gestaltung als JSON: globales Design, Formularlayout mit sichtbaren Texten/Feldern und PDF-Design. Preise, Buchungen, Mail-/PDF-Vorlagentexte und Mediendateien sind nicht enthalten. Vor einem Import wird der bisherige Gestaltungsstand gesichert; **Vor Import wiederherstellen** stellt ihn wieder her. Ein PDF-Logo muss auf der Zielwebsite als lokale Mediendatei vorhanden sein. Diese Sicherung ersetzt kein vollständiges WordPress-Backup.

**Ferienwohnung → Vorlagen:** Links die Mail-Art bzw. PDF-Kopfzeile, -Inhalt oder -Fußzeile wählen. In der Mitte mit dem WordPress-WYSIWYG-Editor schreiben; Betreffzeilen bleiben einfacher Text. Rechts einen Platzhalter suchen und anklicken: Er wird an der letzten Cursorposition im Betreff oder Text eingesetzt. Auch eigene Kontaktfelder stehen als Platzhalter bereit. {items} ist ausschließlich für den PDF-Inhalt vorgesehen und erzeugt die Rechnungstabelle.

**Vorschau aktualisieren** löst die Platzhalter mit Beispieldaten oder einer gewählten Buchung auf. Die PDF-Vorschau wird mit demselben PDF-Generator wie die Rechnung erstellt und stellt noch keine Rechnung aus. Rechts unter **PDF-Gestaltung** befinden sich Schrift, Schriftgröße, Text-/Akzent-/Tabellenfarben, Ausrichtung, Logo, Ränder, Kopf-/Fußhöhen und Seitenzahlen. Logo und PDF-Design gelten für alle drei PDF-Bereiche. Nach Änderungen die PDF-Vorschau erneut erzeugen.

**Vorlagen speichern** speichert alle Mail- und PDF-Vorlagen gemeinsam. Bestehende Text-Mails werden beim Öffnen in Absätze überführt, behalten aber bis zum ersten Speichern ihren bisherigen Versandmodus. Danach nutzt der Plugin-Versand HTML mit einer Klartextalternative über wp_mail(). Die vom Hoster bzw. Mail-Plugin konfigurierte Zustellung bleibt bestehen. Ein manueller **Testmail senden**-Klick verwendet die eingegebene Testadresse und den aktuellen Entwurf; Vorschau und Bearbeitung allein versenden nichts. Der Test für „Rechnungsversand“ prüft den Mailtext, ohne eine Rechnung auszustellen oder anzuhängen. Bereits ausgestellte Rechnungen bleiben unverändert gespeichert.

## Enthalten

- Öffentlicher Monatskalender ohne Namen oder Kontaktdaten. Halbe Tagesmarkierung für An-/Abreise; Gästewechsel am gleichen Datum möglich.
- Unverbindliche Anfragen mit serverseitiger Preis- und Verfügbarkeitsprüfung.
- Backend mit Anfragen, Bestätigen, Ablehnen, Stornieren und manuell gesperrten Zeiträumen.
- Datenbanksperre gegen gleichzeitig bestätigte Doppelbuchungen. Offene Anfragen blockieren nicht.
- Formularbaukasten mit Elementauswahl, zwei Spalten, Drag-and-drop, Pfeiltasten und WYSIWYG-Texteditor. Auch Kalender, Reisedaten und Preisberechnung sind verschiebbar. Notwendige Buchungsfelder bleiben erforderlich.
- Lokaler Proof-of-Work-Spam-Schutz: SHA-256 im Web Worker, signierte kurzlebige Herausforderung, Einmalverwendung in der Datenbank, Honeypot und IP-basierte Ratenbegrenzung. Keine externen CAPTCHA-Dienste.
- WordPress `wp_mail()` für Eingang, Bestätigung, Ablehnung, Stornierung und Rechnung. Automatik abschaltbar; manueller Neuversand und Protokoll.
- Visuell bearbeitbare HTML-E-Mail-Vorlagen mit Klartextalternative und PDF-Vorlagen mit anklickbaren Platzhaltern. PDF-Kopf/-Fußzeile wiederholen sich; Seitenzahlen automatisch. Vorschau vor Ausstellung.
- Fortlaufende Rechnungsnummern und unveränderlich gespeicherte ausgestellte PDF-Dokumente in der Datenbank. Geschützter Download nur im Backend. PDF als E-Mail-Anhang möglich.
- Manuell erfasster Zahlungseingang, Anzahlung, Restzahlung, Ortstaxe separat sowie optionale Endreinigung.

## Preisregeln

Voreingestellt: zwei Inklusivgäste, Aufschlag 20 €, 30 % Anzahlung, Restzahlung einen Tag vor Anreise, kostenfreie Stornierung bis 14 Tage vorher, mindestens **drei Nächte**, Ortstaxe 4,50 € pro abgabepflichtiger Person und Nacht. Nichtraucher- und Haustierregel werden angezeigt und in die Bestätigung übernommen.

Der Grundpreis bleibt absichtlich leer/0, bis ein tatsächlicher Preis eingetragen wurde. Die maximale Belegung ist zunächst **6**, Check-in **15:00**, Check-out **10:00**; diese Annahmen vor Nutzung ändern, falls nötig. Anfragen sind bis drei Jahre im Voraus und für maximal 90 Nächte möglich.

Der Aufschlag kombiniert zwei unabhängig wählbare Einstellungen:

| Zeitraum | Basis | Berechnung bei 4 Gästen, 2 Inklusivgästen und 3 Nächten |
| --- | --- | --- |
| Pro Nacht | Pro zusätzlicher Person | 20 € × 2 × 3 = 120 € |
| Pro Nacht | Pro Buchung | 20 € × 3 = 60 € |
| Pro Aufenthalt | Pro zusätzlicher Person | 20 € × 2 = 40 € |
| Pro Aufenthalt | Pro Buchung | 20 € |

„Pro Buchung“ wird nur berechnet, wenn die Zahl der Inklusivgäste überschritten wird. Alle Unterkunftspreise sind Endpreise in Euro; ein eingestellter Umsatzsteuersatz wird als darin enthalten ausgewiesen. Die Ortstaxe wird zusätzlich angezeigt, aber nicht in Unterkunftspreis, Anzahlung oder Restzahlung eingerechnet. Der Gast gibt die Zahl abgabepflichtiger Personen an; Alter oder Gesundheitsdaten werden nicht abgefragt. Der Befreiungstext ist editierbar.

## Abläufe und Grenzen

- **Anfrage → bestätigt → storniert**; eine offene Anfrage kann auch abgelehnt/storniert werden. Stornierte/abgelehnte Einträge werden nicht wieder geöffnet. Für geänderte Daten eine neue Anfrage verwenden.
- Halbe Tage modellieren die Belegung beim Gästewechsel. Keine frei buchbaren Stunden-/Tageshälften und keine halben Übernachtungspreise.
- Die kostenfreie Stornofrist wird berechnet und kommuniziert. Für spätere Stornierungen wurde kein Gebührensatz vorgegeben; deshalb keine automatische Gebühr oder Erstattung.
- Eine Buchungsstornierung ist **keine Rechnungskorrektur**. Rechnungen bleiben erhalten. Gutschriften/Stornorechnungen und Erstattungen müssen bisher separat erstellt werden.
- PDF-Vorlagen unterstützen sichere HTML-Formatierungen und ausgewählte Inline-Stile. Ein Logo wird ausschließlich aus einer lokalen PNG-/JPEG-Datei der Mediathek eingebunden (max. 3 MB; PHP-GD für PNG erforderlich). Externe Bilder, JavaScript und PHP sind gesperrt. Frei positionierbares PDF-Drag-and-drop ist nicht enthalten; Schrift, Farben, Ränder, Kopf-/Fußhöhen und Tabellen sind über Einstellungen steuerbar. Lange Kopf-/Fußtexte benötigen mehr Höhe; immer die echte PDF-Vorschau prüfen.
- Ein positiver `wp_mail()`-Rückgabewert bedeutet Übergabe an den Mailtransport, keine Zustellbestätigung. Fehler sind im Buchungsverlauf sichtbar und lassen sich manuell erneut versenden. Kein automatischer Mail-Wiederholungsdienst und keine Zahlungserinnerungen.
- HTTPS ist für den Spam-Schutz erforderlich. Content-Security-Policy muss Worker aus der eigenen Domain erlauben. REST-Routen `fwb/v1/*` vom Seiten-/CDN-Cache ausschließen. Bei Reverse Proxies muss der Hoster `REMOTE_ADDR` korrekt setzen; ungeprüfte Forwarding-Header werden absichtlich nicht vertraut.
- Die Speicherung umfasst Kontakt-/Formulardaten, Preis-/Regelsnapshot, Zustimmungszeitpunkt und Datenschutz-Link. Temporäre Anti-Spam-Token werden bereinigt. Buchungen und Rechnungen bleiben beim Deaktivieren/Löschen des Plugins erhalten. Es gibt noch keine automatische personenbezogene Löschfrist oder WordPress-Datenschutzexporter/-eraser; Aufbewahrung und Auskunft sind bisher administrativ zu organisieren. Datenbank-Backups sichern auch Rechnungen.
- Klassische PDF-Rechnungen, keine XRechnung/ZUGFeRD und keine behauptete länderspezifische Rechtssicherheit. Land und Steuerstatus wurden noch nicht angegeben. Pflichtangaben und steuerliche Behandlung vor echtem Einsatz passend einrichten.
- Keine Mehrwohnungsverwaltung, Saisonpreise, Onlinezahlung, iCal-Synchronisation, Gast-Selbststornierung oder automatischen Zahlungserinnerungen in dieser Version.

## Entwicklung und Prüfungen

Version 1.2.0 ergänzt 28 Prüfungen in tests/designer.php für Stil-/Attributvalidierung, Theme-Vererbung, Pflichtfelder, Vorlagenmigration, HTML-/Klartext-Mails, PDF-Parameter und sichere Platzhalter. Elf weitere Prüfungen in tests/designer-ajax.php prüfen Berechtigungen, Nonces, Import/Rücknahme, Vorlagen-Speicherung und Vorschauen ohne Speicherung. Lokale Browserprüfungen decken Eigenschaften-Speicherung, WYSIWYG-Platzhalter im Betreff und Text, Rückgängig, Testversand ohne echte Zustellung, Vorschauen und responsive Darstellung ab. PDF-Einzel- und Mehrseitenbeispiele sowie ein lokales PNG-Logo werden visuell geprüft. Die Tests erzeugen ausschließlich lokale Beispieldaten.


Zusätzlich prüft php tests/frontend.php mit 23 Prüfungen die CSS-Speicherung/-Entfernung, Inline-Einbindung, UIkit-Formularstruktur, bearbeitbare Texte mit sicherer Ausgabe und die Migration unveränderter Standardvorlagen in der lokalen WordPress-Testinstallation.

Zusätzlich prüft php tests/layout.php mit 14 Prüfungen die Übernahme bestehender Felder, Spaltenwechsel, sichere Rich-Text-Ausgabe, dynamische Preisplatzhalter und das Abweisen unvollständiger oder doppelter Buchungselemente. Im Browser wurden Ziehen aus der Elementauswahl, Kalender-Spaltenwechsel, WYSIWYG-Speicherung und die Formularfunktion mit umsortierten Reisedaten geprüft.

Quellcode im Ordner `ferienwohnung-buchung/`. Die ausgelieferte ZIP enthält nur diesen Ordner, keine lokale WordPress-Installation oder Testdaten.

`php tests/domain.php` prüft 44 Fälle für Preise, Nächte, Überschneidungen, Fristen, Eingabevalidierung und Proof of Work.

`tests/bootstrap-wordpress.php` / `tests/integration.php` sind für die **separate lokale Testinstallation unter tools/wordpress**. Integrationstests leeren ausschließlich deren `fwb_*`-Tabellen und erzeugen fiktive Buchungen. Nicht auf Produktionsdaten ausführen. 29 Integrationstests prüfen Anfrage, CAPTCHA-Replay, Bestätigung, Doppelbuchungsschutz, Stornierung, E-Mail-Übergabe, PDF und REST. E-Mails werden in den Tests abgefangen.

Für den Build Dompdf 3.1.6 aus dem offiziellen Paket nach `ferienwohnung-buchung/lib/dompdf/` legen; `autoload.inc.php` muss dort vorhanden sein. Anschließend `powershell -File scripts/build.ps1` ausführen. Drittanbieter-Lizenzen liegen im mitgelieferten Bibliotheksordner. Plugin-Code: GPL-2.0-or-later.

Technische Referenzen: [WordPress wp_mail](https://developer.wordpress.org/reference/functions/wp_mail/), [REST-Routen](https://developer.wordpress.org/reference/functions/register_rest_route/), [Dompdf](https://github.com/dompdf/dompdf).

Sinnvolle nächste Erweiterungen: Saisonpreise, iCal-Abgleich mit Buchungsportalen, Zahlungserinnerungen, Rechnungskorrekturen und Datenschutzexport/-löschung.
