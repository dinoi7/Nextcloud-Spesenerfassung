# MEMORY.md — SpesenErfassung

## Projekt

**SpesenErfassung** ist eine Nextcloud 33 App zur Erfassung und Genehmigung von Spesen für den Makerspace Reinach.
- Spesen erfassen (Titel, Beschreibung, Betrag, Kategorie, Datum)
- Belege hochladen (PDF/JPG/PNG, max. 1 MB, Kamera-Support)
- Zwei-Stufen-Genehmigung: Präsident (> CHF-Grenze) → Kassier → Ausbezahlt → Erledigt
- SSO via Nextcloud (keine separate Authentifizierung)
- Status-Emails bei jedem Workflow-Schritt
- Zweisprachig: Deutsch & Englisch

## Initialer Techstack

| Schicht | Technologie |
|---|---|
| Backend | PHP 8.2+ / Nextcloud 33 Framework |
| Frontend | Vue 3 (Composition API), Pinia, Vue Router, Vite |
| Datenbank | MySQL/MariaDB/PostgreSQL (Nextcloud DB) |
| Mail | Nextcloud IMailer |
| Dateiablage | Nextcloud IAppData |
| i18n | Vue Composable (DE/EN) |
| CSS | Custom CSS mit Nextcloud CSS-Variablen |

## Wichtige Architekturentscheidungen

*(wird durch AI bei signifikanten Entscheidungen gefüllt)*

| Datum | Entscheidung | Begründung |
|---|---|---|
| 2026-05-25 | Nextcloud App statt Standalone | Existierende Nextcloud-Instanz, native SSO, kein zusätzlicher Hosting-Aufwand |
| 2026-05-25 | Kein `@nextcloud/vue` | Vermeidet Build-Komplexität; CSS respektiert dennoch Nextcloud-Theme-Variablen |
| 2026-05-25 | SettingsService als statische Utility | IAppConfig muss einmalig via BootContext gesetzt werden; einfacher als DI durch alle Services zu schleifen |
| 2026-05-28 | `getParsedBody()` → `getParams()` | NC 33 hat `getParsedBody()` aus IRequest entfernt; `getParams()` liefert alle Parameter inkl. JSON-Body |
| 2026-05-28 | `nodeExists()` → `fileExists()` | NC 33 SimpleFolder API-Änderung; Upload funktioniert jetzt |
| 2026-05-28 | Display Name via IUserManager-Map | UID → DisplayName Mapping im Controller, nicht im Frontend; alle Responses enthalten `displayName` |
| 2026-05-28 | Widget via IAPIWidget | Dashboard-Widget mit Counts für "Spesen zu bearbeiten" + "Spesen zu genehmigen" |

## Nächste Schritte

- [ ] Mail-Server in Nextcloud konfigurieren (für Benachrichtigungen)
- [ ] Nextcloud Notification-Integration (zusätzlich zu Mails)
- [ ] Export-Funktionalität (CSV/PDF)
