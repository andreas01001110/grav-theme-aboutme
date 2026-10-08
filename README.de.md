# AboutME – Grav-Theme für persönliche Profilseiten

AboutME ist ein modernes One-Page-Theme für [Grav CMS](https://getgrav.org), gemacht für persönliche Profil-, Lebenslauf- und „Über mich“-Seiten. Es ist von Haus aus zweisprachig (Deutsch und Englisch), hat einen Hell- und Dunkelmodus und lädt keine externen Dienste: Schriften, Symbole und Skripte liegen alle im Theme.

## Funktionen

- **Modulare Profilseite** mit Kopfbereich (Foto, Statuszeile, Buttons, Infokarten, Kennzahlen), Rubriken „Über mich“, Kompetenzen, Projekten und Kontaktbereich
- **Weitere Seitentypen:** Zeitstrahl („Stationen“), Seite mit Einleitung, Galerie und Videos, Kontaktformular mit Danke-Seite, Artikel und News mit Übersichtsseiten
- **Optionale vorgelagerte Startseite:** Bild-Slider über die volle Breite, Kurzprofil, neueste Artikel, News-Slider und Kontakt, jeder Bereich einzeln schaltbar
- **Darstellung:** 12 Farbschemata, 4 Hintergründe (Farbverlauf, Aurora, Punktraster, einfarbig), Hell-/Dunkelmodus mit optionalem Umschalter, je 5 Schriften für Titel und Texte
- **Menü:** automatisch oder als Liste im Admin (die Liste ist das Menü und wird mit den aktuellen Seiten vorbelegt)
- **Social Media:** 19 Netzwerke mit Symbolen, sortierbar, je Profil wählbar, ob es auch oben im Menü erscheint
- **Mehrsprachig:** Deutsch und Englisch sind enthalten, weitere Sprachen lassen sich über `languages.yaml` ergänzen. Listen wie Zeitstrahl, Kompetenzen, Projekte, Galerie oder Slider werden automatisch abgeglichen: Einen Eintrag in der Hauptsprache anlegen, die anderen Sprachen bekommen ihn zum Übersetzen.
- **Galerie:** Vorschau im Quer- oder Hochformat (auch je Bild), animierte GIFs, Großansicht in voller Auflösung
- **Datenschutzfreundlich:** Schriften lokal, kein Tracking, Videos verlinkt statt eingebettet, E-Mail-Adressen gegen Adress-Sammler verschleiert
- **Zweisprachiger Admin:** Alle Theme-Optionen und Seitenfelder sind auf Deutsch und Englisch beschriftet (je nach Admin-Sprache).

## Voraussetzungen

- Grav 2.1 oder neuer (getestet mit Grav 2.2.4), PHP 8.3 oder neuer
- Empfohlen: Admin 2 (Admin Next) mit dem API-Plugin
- Für das Kontaktformular: die Plugins Form und Email

## Installation

1. Den Ordner `aboutme` nach `user/themes/` der Grav-Installation kopieren.
2. In `user/config/system.yaml` `pages: theme: aboutme` eintragen oder im Admin unter *Themes* auswählen.
3. Cache leeren.

### Beispielinhalte (empfohlen)

Das Theme enthält eine vollständige Beispielseite des fiktiven Künstlers **Jon Doe** auf Deutsch und Englisch: Profil, Stationen, Galerie, Artikel, News, Startseite, Kontaktformular sowie Impressum und Datenschutz. So sieht man alle Seitentypen sofort und hat einen Ausgangspunkt für die eigene Seite.

- **Mit dem Grav-Paketmanager:** Bei der Installation über `bin/gpm install` fragt Grav, ob die Beispielinhalte installiert werden sollen.
- **Von Hand:** den Inhalt von `aboutme/_demo/` in den Ordner `user/` kopieren.

**Wichtig:** Die Beispielinhalte ersetzen `user/pages` sowie die Dateien `user/config/site.yaml`, `user/config/system.yaml` und `user/config/themes/aboutme.yaml`. Vorher die eigenen Seiten und Einstellungen sichern. Impressum und Datenschutzerklärung enthalten nur Platzhalter und müssen durch eigene, rechtlich passende Texte ersetzt werden.

## Einstellungen

Alle Optionen stehen im Admin unter *Themes → AboutME*: Name und Monogramm (leer gelassen werden die Anfangsbuchstaben verwendet, auch für das Browser-Symbol), Darstellung (Farbmodus, Farbschema, Hintergrund, Schriften), Sprache, vorgelagerte Startseite, Menü, Social Media und Fußzeile.

### Mehrere Sprachen

Die Sprachdateien folgen dem Grav-Schema (`seite.de.md`, `seite.en.md`). Die Standardsprache ist die Hauptsprache, dort werden Listen gepflegt. Beim Speichern in der Hauptsprache gleicht das Theme die anderen Sprachen an: Neue Einträge erscheinen mit dem Text der Hauptsprache zum Übersetzen, Reihenfolge und Löschungen werden übernommen, gemeinsame Felder wie Bilder, Links oder Kategorien ebenfalls. Vorhandene Übersetzungen bleiben erhalten. Dafür trägt jeder Listeneintrag eine unsichtbare Kennung (`uid`).

## Schriften und Lizenzen

Mitgelieferte Schriften (SIL Open Font License 1.1): Bricolage Grotesque, Fraunces, Playfair Display, Sora, Space Grotesk, Figtree, Inter, Roboto, Open Sans, Source Sans 3, JetBrains Mono.

Die Demo-Person Jon Doe und alle Beispielinhalte sind frei erfunden. Die Beispielbilder sind abstrakte Grafiken, die für dieses Theme erstellt wurden, und dürfen zusammen mit ihm verwendet werden.

## Lizenz

MIT, siehe [LICENSE](LICENSE). © 2026 Andreas G. Neuhauser
