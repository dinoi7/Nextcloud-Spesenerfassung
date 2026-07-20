# _architecture.md — SpesenErfassung

## System-Übersicht

```
┌──────────────────────────────────────────────────────────┐
│                    Nextcloud 34                          │
│  ┌────────────────────────────────────────────────────┐  │
│  │           SpesenErfassung App                      │  │
│  │                                                    │  │
│  │  ┌──────────┐   ┌──────────────────────────────┐   │  │
│  │  │  Vue 3   │   │        PHP Backend           │   │  │
│  │  │  SPA     │◄──┤  Controller  ──►  Service  ──┤   │  │
│  │  │ (Vite)   │   │     │             │          │   │  │
│  │  └──────────┘   │     │          ┌──┴──────┐   │   │  │
│  │                  │     │          │         │   │   │  │
│  │  Browser        │  REST API   Mapper   Mailer   │   │  │
│  │  + Mobile        │     │          │         │   │   │  │
│  │                  │     ▼          ▼         ▼   │   │  │
│  │                  │  ┌─────┐  ┌───────┐ ┌──────┐ │   │  │
│  │  Kamera          │  │ DB  │  │IAppData│ │IMailer│ │   │  │
│  │  (capture)       │  └─────┘  └───────┘ └──────┘ │   │  │
│  └──────────────────┴────────────────────────────────┘   │  │
│       ▲                                                  │  │
│       │ SSO (Nextcloud Auth)                             │  │
│       │                                                  │  │
│  ┌────┴─────────────────────────────────────────────┐    │  │
│  │  Nextcloud Core: IUserSession, IAppConfig,        │    │  │
│  │  IURLGenerator, IGroupManager, Logger            │    │  │
│  └──────────────────────────────────────────────────┘    │  │
└──────────────────────────────────────────────────────────┘

     Benutzer          Präsident          Kassier
  (Erfasser)         (>250 Freigabe)   (≤250 Auszahlung
                        (>250 weiterleiten)   >250 Auszahlung
                                              Zurückweisung)
```

## Ordnerstruktur

```
spesenerfassung/
├── appinfo/
│   ├── info.xml                        # Nextcloud App Meta
│   └── routes.php                      # Route-Definitionen
├── lib/
│   ├── AppInfo/Application.php         # IBootstrap: Boot + Admin-Registrierung
│   ├── Controller/
│   │   ├── ExpenseController.php       # CRUD Spesen + Beleg-Upload
│   │   ├── ApprovalController.php      # Workflow-Endpunkte
│   │   ├── SettingsController.php      # Admin Einstellungen & Kategorien
│   │   └── PageController.php          # SPA-Auslieferung mit InitialData
│   ├── Db/
│   │   ├── Expense.php / ExpenseMapper.php
│   │   ├── Receipt.php / ReceiptMapper.php
│   │   └── Approval.php / ApprovalMapper.php
│   ├── Migration/
│   │   └── Version000001Date20260525.php
│   ├── Service/
│   │   ├── ExpenseService.php          # Business-Logik + Status-Transitions
│   │   ├── WorkflowService.php         # State Machine (TRANSITIONS Matrix)
│   │   ├── ReceiptService.php          # Datei-Upload via IAppData
│   │   ├── SettingsService.php         # AppConfig-Getter/Setter
│   │   ├── MailService.php             # DE/EN Mail-Versand via IMailer
│   │   └── BookingReceiptService.php   # PDF-Spesenbeleg (TCPDF+FPDI) in kassier-Ordner
│   └── Settings/
│       ├── AdminSettings.php           # Admin-Form
│       └── AdminSection.php            # Admin-Navigations-Abschnitt
├── src/
│   ├── main.js                         # Vue App Entry
│   ├── App.vue                         # Root Component (Header + Router-View)
│   ├── api.js                          # fetch()-basierter API-Client
│   ├── router.js                       # Hash-Router
│   ├── store/
│   │   ├── expenses.js                 # Pinia: Spesen-State + Rollen-Erkennung
│   │   └── settings.js                 # Pinia: Admin-Einstellungen
│   ├── views/
│   │   ├── Dashboard.vue               # Übersicht + Summary-Cards
│   │   ├── ExpenseForm.vue             # Neu/Bearbeiten
│   │   ├── ExpenseDetail.vue           # Detail + History + Workflow-Aktionen + Beleg-Vorschau
│   │   ├── ApprovalList.vue            # Genehmigungs-Liste (Präsident/Kassier)
│   │   ├── BookkeepingView.vue         # Buchhaltung (Kassier) mit CSV-Export
│   │   ├── PaystackView.vue            # Zahlstapel (Kassier) mit QR-Bill
│   │   ├── EvaluationView.vue          # Auswertung mit Filtern + CSV-Export
│   │   ├── ProfileView.vue             # Benutzerprofil (IBAN, Sprache)
│   │   └── SettingsView.vue            # Admin-Einstellungen
│   ├── components/
│   │   ├── ExpenseCard.vue
│   │   ├── StatusBadge.vue
│   │   ├── ReceiptUpload.vue           # Drag&Drop + Kamera
│   │   └── HistoryTimeline.vue
│   └── i18n/
│       └── index.js                    # DE/EN Composable
├── css/
│   └── style.css                       # Responsive, Nextcloud-Theme-Variablen
├── templates/
│   ├── index.php                       # SPA Mountpoint
│   └── admin.php                       # Admin-Seite
├── img/
│   ├── app.svg
│   └── logo.png                        # Makerspace-Logo für PDF-Spesenbeleg
├── composer.json
├── package.json
├── vite.config.js
├── AGENTS.md
├── MEMORY.md
└── _architecture.md
```

## Datenmodell

### `spesenerfassung_expenses`

| Feld | Typ | Beschreibung |
|---|---|---|
| `id` | BIGINT PK | Auto-ID |
| `user_id` | VARCHAR(64) | Nextcloud UID des Erfassers |
| `title` | VARCHAR(255) | Titel der Spese |
| `description` | TEXT nullable | Beschreibung |
| `amount` | DECIMAL(10,2) | Betrag in CHF |
| `category` | VARCHAR(128) | Kategorie (aus configurierbarer Liste) |
| `status` | VARCHAR(32) | `draft`, `submitted`, `approved`, `rejected`, `bookkeeping`, `paystack`, `paid`, `done` |
| `expense_date` | DATE | Datum der Ausgabe |
| `created_at` | DATETIME | Erstellungszeitpunkt |
| `updated_at` | DATETIME | Letzte Änderung |

Indizes: `(user_id)`, `(status)`

### `spesenerfassung_receipts`

| Feld | Typ | Beschreibung |
|---|---|---|
| `id` | BIGINT PK | Auto-ID |
| `expense_id` | BIGINT FK → expenses.id ON DELETE CASCADE | Zugehörige Spese |
| `file_name` | VARCHAR(255) | Dateiname |
| `file_path` | VARCHAR(512) | Pfad in IAppData |
| `mime_type` | VARCHAR(64) | MIME-Type |
| `size` | BIGINT | Dateigrösse in Bytes |
| `created_at` | DATETIME | Upload-Zeitpunkt |

FK-Constraint: `expense_id` → `spesenerfassung_expenses.id` ON DELETE CASCADE

### `spesenerfassung_approvals` (Audit-Log)

| Feld | Typ | Beschreibung |
|---|---|---|
| `id` | BIGINT PK | Auto-ID |
| `expense_id` | BIGINT FK → expenses.id ON DELETE CASCADE | Zugehörige Spese |
| `user_id` | VARCHAR(64) | Wer hat gehandelt |
| `action` | VARCHAR(32) | `submitted`, `approved`, `rejected`, `bookkeeping`, `paystack`, `paid`, `done` |
| `comment` | TEXT nullable | Begründung (zwingend bei `rejected`) |
| `created_at` | DATETIME | Zeitpunkt der Aktion |

FK-Constraint: `expense_id` → `spesenerfassung_expenses.id` ON DELETE CASCADE

## State Machine

```
                    ┌──────────────┐
                    │    DRAFT     │◄──────────┐
                    └──────┬───────┘           │
                           │ submit (Erfasser)  │ edit & save
                           ▼                    │
                    ┌──────────────┐           │
              ┌─────┤  SUBMITTED   ├─────┐     │
              │     └──────┬───────┘     │     │
              │     ≤CHF   │   >CHF      │     │
              │            │             │     │
              ▼            ▼             │     │
       ┌──────────┐  ┌──────────┐       │     │
       │  Kassier │  │Präsident │       │     │
       └────┬─────┘  └────┬─────┘       │     │
            │             │             │     │
   bookkeep │    approve  │    reject   │     │
            │             │   (Grund)   │     │
            ▼             ▼             │     │
       ┌───────────┐ ┌──────────┐      │     │
       │BOOKKEEPING│ │ APPROVED │      │     │
       └─────┬─────┘ └────┬─────┘      │     │
             │            │            │     │
   paystack  │    bookkeep│   reject   │     │
   (bank)    │            │   (Kassier,│     │
             │            │    Grund)  │     │
             ▼            ▼            │     │
       ┌──────────┐ ┌──────────┐      │     │
       │ PAYSTACK │ │ REJECTED ├──────┘     │
       └────┬─────┘ └──────────┘            │
            │                               │
            │ pay                           │
            ▼                               │
       ┌──────────┐                         │
       │  PAID    │                         │
       └────┬─────┘                         │
            │ done (Erfasser)               │
            ▼                               │
       ┌──────────┐                         │
       │  DONE    │   (Endzustand)          │
       └──────────┘                         │
                                            │
  ┌─── Löschen nur durch Erfasser ──────────┘
  │    und nur in DRAFT / REJECTED

  BAR-Auszahlung: BOOKKEEPING → pay → PAID
  Bank-Auszahlung: BOOKKEEPING → paystack → PAYSTACK → pay → PAID
```

## API-Routen

### Spesen CRUD
```
GET    /api/expenses                        → ExpenseController#index
POST   /api/expenses                        → ExpenseController#create
GET    /api/expenses/{id}                   → ExpenseController#show
PUT    /api/expenses/{id}                   → ExpenseController#update
DELETE /api/expenses/{id}                   → ExpenseController#destroy
```

### Belege
```
POST   /api/expenses/{id}/receipts             → ExpenseController#uploadReceipt
GET    /api/expenses/{id}/receipts/{rid}/download → ExpenseController#downloadReceipt
GET    /api/expenses/{id}/receipts/{rid}/preview  → ExpenseController#previewReceipt
DELETE /api/expenses/{id}/receipts/{rid}       → ExpenseController#deleteReceipt
```

### Workflow
```
POST   /api/expenses/{id}/submit            → ApprovalController#submit
POST   /api/expenses/{id}/approve           → ApprovalController#approve
POST   /api/expenses/{id}/reject            → ApprovalController#reject
POST   /api/expenses/{id}/bookkeeping       → ApprovalController#bookkeeping
POST   /api/expenses/{id}/paystack          → ApprovalController#paystack
POST   /api/expenses/{id}/pay               → ApprovalController#pay
POST   /api/expenses/{id}/done              → ApprovalController#done
GET    /api/approvals/pending               → ApprovalController#pending
GET    /api/approvals/bookkeeping           → ApprovalController#bookkeepingList
GET    /api/approvals/bookkeeping/export    → ApprovalController#bookkeepingExport
GET    /api/approvals/bookkeeping/export/{id}→ ApprovalController#bookkeepingExportSingle
GET    /api/approvals/paystack              → ApprovalController#paystackList
POST   /api/approvals/paystack/pay-all      → ApprovalController#paystackPayAll
GET    /api/approvals/paystack/export       → ApprovalController#paystackExport
GET    /api/approvals/paystack/export/{id}  → ApprovalController#paystackExportSingle
GET    /api/evaluation                      → ApprovalController#evaluation
GET    /api/evaluation/export               → ApprovalController#evaluationExport
```

### Einstellungen
```
GET    /api/settings                        → SettingsController#get
PUT    /api/settings                        → SettingsController#update
GET    /api/settings/user                   → SettingsController#getUserSettings
PUT    /api/settings/user                   → SettingsController#updateUserSettings
GET    /api/categories                      → SettingsController#getCategories
POST   /api/categories                      → SettingsController#createCategory
PUT    /api/categories/{id}                 → SettingsController#updateCategory
DELETE /api/categories/{id}                 → SettingsController#deleteCategory
GET    /api/users                           → SettingsController#getUsers
```

### Page
```
GET    /                                    → PageController#index
```

## Wichtige Entscheidungen mit Begründung

### 1. Nextcloud App statt Standalone Web-App (2026-05-25)

**Begründung:** Existierende Nextcloud-Instanz bietet native SSO, Dateiablage, Mail-Versand und Notification-System. Eine eigene App spart Hosting-Kosten und Entwicklungszeit für Auth/Storage. PHP/MySQL läuft auf jedem Shared-Hosting.

### 2. Vue 3 SPA mit Hash-Router (2026-05-25)

**Begründung:** Vue 3 Composition API mit Pinia ist der Nextcloud-32-Standard für Frontend. Hash-History vermeidet Routing-Konflikte mit Nextcloud's eigenem Router. Vite-Build erzeugt JS-Bundles im `js/`-Verzeichnis, die über `script()`-Helper geladen werden.

### 3. Kein `@nextcloud/vue` (2026-05-25)

**Begründung:** Vermeidet Build-Komplexität und Abhängigkeitskonflikte. Custom CSS mit Nextcloud CSS-Variablen (`--color-main-background`, `--color-primary`, etc.) stellt Theme-Kompatibilität (inkl. Dark Mode) sicher, ohne an die Nextcloud-Komponentenbibliothek gebunden zu sein.

### 4. Statischer WorkflowService mit TRANSITIONS-Matrix (2026-05-25)

**Begründung:** Alle erlaubten Status-Übergänge sind in einer deklarativen Matrix definiert. `canTransition(from, to)` checkt in O(1). Neue Status-Übergänge benötigen nur einen Array-Eintrag — keine Business-Logik-Änderung.

### 5. SettingsService als statische Utility (2026-05-25)

**Begründung:** `IAppConfig` muss einmalig via `BootContext::boot()` gesetzt werden. Statischer Zugriff spart DI-Boilerplate durch alle Service-Klassen. In Tests kann `setConfig()` mit einem Mock aufgerufen werden.

### 6. Audit-Log als eigene Tabelle (2026-05-25)

**Begründung:** Trennung von Entität und Historie. Der Approval-Status ist nur eine Momentaufnahme; die `approvals`-Tabelle speichert lückenlos wer/wann/was/warum. Kein Logik-Verlust bei Status-Änderungen.

### 7. Beleg-Ablage via IAppData (2026-05-25)

**Begründung:** `IAppData` stellt app-weiten, vom Benutzer unabhängigen Speicher bereit. Belege bleiben erhalten, auch wenn der Erfasser gelöscht wird. Keine Permission-Komplexität wie bei User-Foldern.

### 8. Kamera via HTML5 `<input capture>` (2026-05-25)

**Begründung:** `<input type="file" accept="image/*" capture="environment">` öffnet auf Smartphones nativ die Kamera. Keine zusätzliche JS-Bibliothek oder Berechtigungs-API nötig. Funktioniert in allen modernen Browsern.

### 9. Mail-Templates bilingual (DE/EN) (2026-05-25)

**Begründung:** Makerspace Reinach hat deutsch- und englischsprachige Mitglieder. Mails enthalten beide Sprachen (DE-Block + `---` + EN-Block), damit der Empfänger die präferierte Sprache lesen kann, ohne dass die App die Empfänger-Sprache kennen muss.

### 10. Docblock-Annotationen vs. PHP-8-Attribute (2026-07-18 / revidiert 2026-07-19)

**Historie:** Nextcloud 33 ignoriert `#[NoAdminRequired]`/`#[NoCSRFRequired]` ohne korrekten Import (`OCP\AppFramework\Http\Attribute\*`) — CSRF-412-Fehler an Auswertung war Folge. Workaround: Docblock-Annotationen (`@NoAdminRequired`/`@NoCSRFRequired`).

**Kehrtwende für NC 34 (2026-07-19):** Mit dem Upgrade auf Nextcloud 34 wurden alle Controller auf PHP-8-Attribute (`#[NoAdminRequired]`, `#[NoCSRFRequired]`) umgestellt. NC 34 verarbeitet beide Formate; Attribute sind der moderne Standard und weniger fehleranfällig (kein stillschweigendes Ignorieren bei fehlendem Import).

### 11. PDF-Spesenbeleg mit TCPDF + FPDI (2026-07-18)

**Begründung:** Beim Bezahlen einer Spese wird automatisch ein "Spesenbeleg"-PDF generiert und im kassier-User-Ordner abgelegt. TCPDF (6.11.3) erstellt das Grundgerüst (Layout, Tabellen, Text), FPDI (2.6.8) bettet PDF-Anhänge als weitere Seiten ein. Die Ablage erfolgt im kassier-Ordner (`IRootFolder->getUserFolder('kassier')`), nicht in IAppData, damit der Kassier direkten Zugriff über die Nextcloud-UI hat. Wichtige Fixes: `getTemplateSize()` für korrekte Anhang-Dimensionen, `SetPrintHeader(false)`/`SetPrintFooter(false)` gegen TCPDF-Standard-Rahmenlinien, `str_replace('\\', '/')` gegen Backslash im Ordnerpfad.

### 12. Security: Ownership-Check via `canAccessExpense()` (2026-07-19)

**Begründung:** Vor dem Security-Audit gab es keine Zugriffskontrolle auf einzelne Spesen — jeder authentifizierte User konnte jede Spese lesen, Belege herunterladen und Status-Übergänge anderer User auslösen. Der neue `canAccessExpense($expense, $userId)`-Helper in `ExpenseController.php` prüft: Eigentümer der Spese ODER Präsident ODER Kassier. Wird in `show()`, `downloadReceipt()` und `previewReceipt()` verwendet. Analog prüft `ExpenseService::transition()` den Eigentümer bei `submit`/`done`.

### 13. CSRF Token-Quelle im Frontend (2026-07-20)

**Begründung:** `src/api.js` las das CSRF-Token aus `document.querySelector('head meta[name="csrf-token"]')` — dieses `<meta>`-Tag existiert in Nextcloud nicht (es steht als `data-requesttoken` am `<head>`-Element). Folge: alle POST/PUT/DELETE-Requests scheiterten mit 412, sobald `#[NoCSRFRequired]` entfernt wurde. Fix: `document.head?.dataset?.requesttoken` — identisch mit Nextclouds eigener `core-common.js`.

### 14. Email UID → Email-Adress-Auflösung (2026-07-19)

**Begründung:** `MailService::notify()` setzte `setTo([$uid => $uid])` — die Nextcloud-UID wurde als Email-Adresse verwendet, was nicht der echten E-Mail des Users entspricht. Fix: `IUserManager::getEMailAddress($uid)` liefert die im Nextcloud-Profil hinterlegte E-Mail-Adresse. Zusätzlich `LoggerInterface` statt stillem `catch (\Throwable)` und Korrektur aller Mail-Texte von "Spese" zu "Spesen".
