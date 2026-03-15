# MovieVault

Serverseitig gerenderte Medienverwaltung fuer Filme und Serien auf DVD und Blu-ray, basierend auf PHP 8.5, Smarty, Bootstrap und SQLite.

## Voraussetzungen

- Apache mit `mod_rewrite`
- PHP 8.5+
- Pflicht-Extensions: `pdo_sqlite`, `sqlite3`
- Empfohlen: `openssl`, `curl`, `fileinfo`, `mbstring`

## Einrichtung

### Shared Hosting: Uploaden und starten

1. Das komplette Projekt in das Webverzeichnis des Hosters hochladen.
2. Dokument-Root darf auf dem Projekt-Root bleiben; die Root-[index.php](H:/Projekte/MovieVault/index.php) und Root-[.htaccess](H:/Projekte/MovieVault/.htaccess) leiten sicher in die `public`-Dateien weiter.
3. Im Browser die Projekt-URL aufrufen.
4. Wenn `pdo_sqlite` und `sqlite3` aktiv sind, erscheint automatisch der Web-Installer.
5. Admin-E-Mail eintragen, Installation starten und den erzeugten Einladungslink oeffnen.
6. Bestehende Installationen ziehen beim ersten App-Start fehlende Migrationen automatisch nach.

### Klassisch mit CLI

1. Optional `config/app.local.php` aus [`config/app.local.example.php`](config/app.local.example.php) ableiten und Basis-URL oder API-Keys hinterlegen.
2. Datenbank und Start-Einladung erzeugen:

   ```bash
   php bin/install.php admin@example.local
   ```

3. Das ausgegebene Einladungs-URL im Browser oeffnen und das erste Admin-Konto aktivieren.
4. `public/` als Webroot konfigurieren.

### Update bestehender Installationen

Normalerweise zieht MovieVault neue Migrationen beim App-Start automatisch nach. Falls du das Update manuell anstossen willst oder per CLI kontrollieren moechtest:

```bash
php bin/migrate.php
```

## Wichtige Funktionen

- Benutzer, Einladungen, Rollen und Rechte
- Filme und Staffel-Eintraege mit getrennten Exemplaren
- Gemeinsame Wunsch- und Einkaufslisten mit Bulk-Aktionen und Uebernahme in die Sammlung
- CSV-Import mit Mapping und Vorschau
- Metadaten-Suche ueber TMDb und Wikidata
- Lokaler Poster-Cache
- Watched-List, Rewatches, Vorschlaege und Statistik-Dashboard

## Hinweise

- Ohne konfigurierte SQLite-Erweiterungen zeigt die App bewusst nur die Requirements-Seite.
- TMDb braucht einen API-Key in `config/app.local.php`.
- Auf Shared Hosting schreibt der Web-Installer nach Moeglichkeit automatisch `config/app.local.php`.
