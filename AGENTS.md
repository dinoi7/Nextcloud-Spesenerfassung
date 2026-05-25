# AGENTS.md — SpesenErfassung

## Autonomie-Level

**STUFE 3 — Mittel (Time-to-Market: entspannt)**

- Der AI darf Code schreiben, löschen, verschieben und refaktorisieren **ohne Nachfragen**.
- Bei Architektur-Änderungen, neuen Abhängigkeiten oder strukturellen Umbenennungen: **kurz ankündigen, dann machen**.
- Kein Git-Commit, kein Git-Push ohne expliziten Auftrag.
- `npm install`, `composer install` etc. nur mit Auftrag.

## Code-Style Konventionen

### PHP (Nextcloud App)
- `declare(strict_types=1);` in jeder PHP-Datei.
- PSR-4 Autoloading unter `lib/`, Namespace `OCA\Spesenerfassung\`.
- Controller erben von `OCP\AppFramework\Controller`, Methoden mit PHP-8-Attributen `#[NoAdminRequired]`, `#[NoCSRFRequired]`.
- Entities erben von `OCP\AppFramework\Db\Entity`, Mapper von `OCP\AppFramework\Db\QBMapper`.
- DB-Queries ausschließlich über den Nextcloud `IQueryBuilder` mit named Parameters.
- Keine direkten `$_GET`/`$_POST`/`$_SESSION` Zugriffe — alles über `IRequest`.
- Keine Kommentare, es sei denn explizit verlangt.
- String-Konkatenation: `.`, Templates in `templates/` als `.php` mit `<?php p(...);?>`.

### JavaScript / Vue 3
- Vue 3 Composition API (`<script setup>`).
- Pinia Stores (Composition API Style) unter `src/store/`.
- Vue Router mit Hash-History unter `src/router.js`.
- `fetch()`-basierter API-Client in `src/api.js` — CSRF-Token aus `<meta name="csrf-token">`.
- Keine externen UI-Libraries (kein `@nextcloud/vue`), reines Custom-CSS.
- CSS-Variablen von Nextcloud (`--color-main-background`, `--color-primary`, etc.) respektieren.

### Allgemein
- Dateinamen: PascalCase für PHP-Klassen, camelCase für JS/TS, kebab-case für Verzeichnisse.
- Fehlermeldungen auf Deutsch & Englisch im Frontend (i18n), Backend-Meldungen auf Englisch.
- Keine TODOs in Code-Kommentaren ablegen.

## Credential-Sicherheit

- **Niemals** `.env`, Passwörter, API-Keys oder Secrets committen.
- App-Konfiguration läuft über Nextcloud `IAppConfig` (in DB gespeichert).
- Mail-Passwörter werden NICHT in dieser App verwaltet — Nextcloud `IMailer` nutzt die systemweite Mail-Konfiguration.
- Falls Drittanbieter-APIs integriert werden: Keys via Nextcloud Admin-Settings speichern, nie im Code.

## MEMORY.md-Pflege

- `MEMORY.md` **nach jeder Session** aktualisieren.
- Neue Architekturentscheidungen in `_architecture.md` dokumentieren (mit Begründung, Datum).
- Wichtige Erkenntnisse, Fallstricke, Entscheidungen in die jeweilige Sektion schreiben.
- Keine redundanten Einträge — bestehende ergänzen oder verfeinern.

## Token-Effizienz

- Kein `cat`, `head`, `tail`, `sed`, `awk`, `echo` via Bash-Tool — immer `Read`/`Write`/`Edit`/`Grep`/`Glob` Tools verwenden.
- `bash` nur für `npm`, `composer`, `git`, `mkdir`, echte Shell-Befehle.
- Keine mehrzeiligen Befehle in `bash` — Abhängigkeiten mit `&&` verketten, unabhängige parallel als separate Tool-Calls.
- Antworten kurz halten (< 4 Zeilen), kein unnötiger Preamble/Postamble.

## Session-übergreifende Kontinuität

- Vor Code-Änderungen zuerst `MEMORY.md` und `_architecture.md` lesen.
- Jede Session beginnt mit einem kurzen Blick auf den aktuellen Branch und `git status`.
- Nach größeren Änderungen `AGENTS.md` prüfen, ob Konventionen noch aktuell sind.
