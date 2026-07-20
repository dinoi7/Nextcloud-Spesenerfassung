# MEMORY.md — SpesenErfassung

## Projekt

**SpesenErfassung** ist eine Nextcloud 34 App zur Erfassung und Genehmigung von Spesen für den Makerspace Reinach.
- Spesen erfassen (Titel, Beschreibung, Betrag, Kategorie, Datum)
- Belege hochladen (PDF/JPG/PNG, max. 1 MB, Kamera-Support)
- Zwei-Stufen-Genehmigung: Präsident (> CHF-Grenze) → Kassier → Ausbezahlt → Erledigt
- SSO via Nextcloud (keine separate Authentifizierung)
- Status-Emails bei jedem Workflow-Schritt
- Zweisprachig: Deutsch & Englisch

## Initialer Techstack

| Schicht | Technologie |
|---|---|
| Backend | PHP 8.2+ / Nextcloud 34 Framework |
| Frontend | Vue 3 (Composition API), Pinia, Vue Router, Vite |
| Datenbank | MySQL/MariaDB/PostgreSQL (Nextcloud DB) |
| Mail | Nextcloud IMailer |
| Dateiablage | Nextcloud IAppData |
| i18n | Vue Composable (DE/EN) |
| CSS | Custom CSS mit Nextcloud CSS-Variablen |

## Wichtige Architekturentscheidungen

| Datum | Entscheidung | Begründung |
|---|---|---|
| 2026-05-25 | Nextcloud App statt Standalone | Existierende Nextcloud-Instanz, native SSO, kein zusätzlicher Hosting-Aufwand |
| 2026-05-25 | Kein `@nextcloud/vue` | Vermeidet Build-Komplexität; CSS respektiert dennoch Nextcloud-Theme-Variablen |
| 2026-05-25 | SettingsService als statische Utility | IAppConfig muss einmalig via BootContext gesetzt werden; einfacher als DI durch alle Services zu schleifen |
| 2026-05-28 | `getParsedBody()` → `getParams()` | NC 33 hat `getParsedBody()` aus IRequest entfernt; `getParams()` liefert alle Parameter inkl. JSON-Body |
| 2026-05-28 | `nodeExists()` → `fileExists()` | NC 33 SimpleFolder API-Änderung; Upload funktioniert jetzt |
| 2026-05-28 | Display Name via IUserManager-Map | UID → DisplayName Mapping im Controller, nicht im Frontend; alle Responses enthalten `displayName` |
| 2026-05-28 | Widget via IAPIWidget | Dashboard-Widget mit Counts für "Spesen zu bearbeiten" + "Spesen zu genehmigen" |
| 2026-07-18 | Docblock-Annotationen statt PHP-8-Attribute (NC 33) | Nextcloud 33 ignoriert `#[NoAdminRequired]`/`#[NoCSRFRequired]` Attribute ohne korrekten Import — CSRF-412-Fehler an Auswertung war Folge. Workaround: Docblock `@NoAdminRequired`/`@NoCSRFRequired`. |
| 2026-07-19 | PHP-8-Attribute für NC 34 | Nach Upgrade auf Nextcloud 34 wieder zurück zu `#[NoAdminRequired]`/`#[NoCSRFRequired]` Attributen. NC 34 verarbeitet beide Formate, Attribute sind der moderne Standard und weniger fehleranfällig. |
| 2026-07-19 | NC 34 Compatibility | `info.xml` max-version auf 34 erhöht. `OC::$server`-Calls in PageController, SettingsController, AdminSection durch DI ersetzt (`IGroupManager`, `IConfig`, `IURLGenerator`). |
| 2026-07-19 | Security Audit — 6 Critical Fixes | Status-Bypass (C-1: `create()` hardcodiert DRAFT), Ownership-Checks für `show()`/`downloadReceipt()`/`previewReceipt()`/`submit()`/`done()` (C-2–C-5), CSRF auf allen POST/PUT/DELETE-Endpoints reaktiviert (C-6). Neuer `canAccessExpense()`-Helper prüft Eigentümer/Präsident/Kassier. |
| 2026-07-20 | CSRF Token-Quelle gefixt | `api.js` las Token aus nicht-existentem `<meta name="csrf-token">` → jetzt `document.head?.dataset?.requesttoken` (Nextclouds kanonische Quelle, identisch mit `core-common.js`). |
| 2026-07-19 | Email UID → Email-Resolution | `MailService` nutzt `IUserManager::getEMailAddress()` um UIDs in echte E-Mail-Adressen aufzulösen. Deutsche Texte: "Spese" → "Spesen". LoggerInterface für Fehler statt stillem `catch (\Throwable)`. |
| 2026-07-19 | Auswertung "Spesennummer"-Filter | `EvaluationView.vue`: "Nr."-Spalte (sortierbar nach ID) + Textfilter für Suche nach Spesennummer. CSV-Export enthält Spesennummer als erste Spalte. |
| 2026-07-18 | Workflow: SUBMITTED → BOOKKEEPING | "Zum Zahlstapel"/"Auszahlen" aus ApprovalList entfernt; Eingereichte unterhalb der Schwelle gehen direkt zur Buchhaltung |
| 2026-07-18 | Nav-Erweiterung: Buchhaltung + Zahlstapel + Badges | Neues Menü: Erfassung → Genehmigungen → Buchhaltung → Zahlstapel → Auswertung → Profil → Einstellungen; Pending-Counts auf Buchhaltung/Zahlstapel |
| 2026-07-18 | Beleg-Vorschau (Hover) im Detail | Image-Preview als `<img>`, PDF als `<iframe>`, Download-Button; unterhalb des Receipt-Items positioniert |
| 2026-07-18 | Detail/Form-Ansicht verbreitert | `max-width: none` ab 900px für `.spes-detail` und `.spes-form` |
| 2026-07-18 | PDF-Spesenbeleg via TCPDF + FPDI | `BookingReceiptService` generiert PDF in kassier-Ordner: Logo im Header, "Spesenbeleg"-Titel, Verlauf-Tabelle, Anhang-Embedding (FPDI `getTemplateSize`-Fix), keine TCPDF-Header/Footer-Linien |
| 2026-07-18 | Spesenbeleg in kassier-User-Ordner | Ablage via `IRootFolder->getUserFolder('kassier')` statt IAppData — nur kassier hat Zugriff |
| 2026-07-18 | Backslash-Fix in Buchungsordner-Pfad | `str_replace('\\', '/', $folderPath)` in BookingReceiptService + SettingsController |

## Nächste Schritte

- [x] Mail-Server in Nextcloud konfigurieren (getestet: admin→reto@wff.ch, kassier→reto.probst@wff.ch)
- [ ] Nextcloud Notification-Integration (zusätzlich zu Mails)
- [x] Export-Funktionalität (CSV für Buchhaltung, Zahlstapel, Auswertung)
- [x] Buchhaltung-Ansicht mit Soll-Konto-Zuordnung
- [x] Zahlstapel-Ansicht mit QR-Bill / IBAN
- [ ] **HIGH H-1:** `SettingsService::getAll()` exponiert Admin-UIDs (Präsident/Kassier) an alle User
- [ ] **HIGH H-2:** `getUsers()` API erlaubt vollständige Nextcloud-User-Enumeration
- [ ] **HIGH H-3:** Hardcodierte Debug-Logfiles (`/var/www/nextcloud-data/spes_*.log`) entfernen
- [ ] **HIGH H-4:** `updateCategory()` validiert nicht gegen konfigurierte Kategorienliste
