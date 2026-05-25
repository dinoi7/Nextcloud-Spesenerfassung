# MEMORY.md — SpesenErfassung

## Projekt

**SpesenErfassung** ist eine Nextcloud 32 App zur Erfassung und Genehmigung von Spesen für den Makerspace Reinach.
- Spesen erfassen (Titel, Beschreibung, Betrag, Kategorie, Datum)
- Belege hochladen (PDF/JPG/PNG, max. 1 MB, Kamera-Support)
- Zwei-Stufen-Genehmigung: Präsident (> CHF-Grenze) → Kassier → Ausbezahlt → Erledigt
- SSO via Nextcloud (keine separate Authentifizierung)
- Status-Emails bei jedem Workflow-Schritt
- Zweisprachig: Deutsch & Englisch

## Initialer Techstack

| Schicht | Technologie |
|---|---|
| Backend | PHP 8.2+ / Nextcloud 32 Framework |
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

## Nächste Schritte

- [ ] Nextcloud-Testumgebung einrichten
- [ ] `npm install && npm run build` ausführen
- [ ] App in Nextcloud aktivieren und Admin-Seite konfigurieren
- [ ] Mail-Server in Nextcloud konfigurieren (für Benachrichtigungen)
- [ ] Workflow mit realen Benutzern testen
- [ ] Präsident-UID und Kassier-UID in Admin-Settings eintragen
- [ ] Beleg-Download-Endpunkt implementieren
- [ ] Export-Funktionalität (CSV/PDF)
- [ ] Nextcloud Notification-Integration (zusätzlich zu Mails)
