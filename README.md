# SpesenErfassung

Expense tracking app with multi-step approval workflow for Nextcloud. Built for Makerspace Reinach.

## Features

- Submit expenses (title, description, amount in CHF, category, date, foreign currency support)
- Upload receipts (PDF, JPG, PNG, drag & drop, camera capture on mobile, max 1 MB each, max 5 per expense)
- Two-step approval: Präsident (above threshold) + Kassier (at/below threshold and post-Präsident)
- Payment stack (Zahlstapel) with QR-IBAN info and bulk "pay all"
- Bookkeeping export (Buchhaltung) with Soll-Konto mapping per category and CSV export
- PDF booking receipt generation (Spesenbeleg) with logo, history table, and embedded attachments
- Evaluation (Auswertung) with filters and CSV export
- Bilingual email notifications (DE/EN) on every workflow status change
- Nextcloud Dashboard widget showing actionable counts
- Full audit trail (who did what and when)
- Responsive UI with Nextcloud theme support (light/dark)
- German / English interface, auto-detected from Nextcloud locale

## Requirements

| Requirement | Version |
|---|---|
| Nextcloud | 28 – 34 |
| PHP | 8.2+ |
| MySQL / MariaDB / PostgreSQL | 8+ / 10+ / 14+ |
| Composer | 2 |

Node.js 20+ is only required for frontend development. Pre-built JavaScript is included in the repository.

### PHP Dependencies

Installed via Composer, declared in `composer.json`:

| Package | Purpose |
|---|---|
| `tecnickcom/tcpdf` ^6.3 | PDF generation for booking receipts (Spesenbeleg) |
| `setasign/fpdi` ^2.6 | Import and embed existing PDFs as attachment pages |
| `setasign/fpdi-tcpdf` ^2.3 | FPDI–TCPDF bridge |

## Installation

### 1. Clone the app

```bash
cd /var/www/nextcloud/apps/
git clone https://github.com/dinoi7/Nextcloud-Spesenerfassung
```

### 2. Install PHP dependencies

```bash
cd spesenerfassung
composer install --no-dev
```

### 3. Enable the app

```bash
su www-data -s /bin/sh -c "php /var/www/nextcloud/occ app:enable spesenerfassung"
```

### 4. Frontend build (only for development)

The compiled JavaScript (`js/spesenerfassung-main.js`) is already checked into the repository.
To modify and rebuild the frontend:

```bash
npm install
npm run build       # one-time build
npm run dev         # watch mode (auto-rebuild on changes)
```

## Post-Installation Setup

After enabling the app, configure the following under **Nextcloud Settings → Administration → SpesenErfassung**:

### Required Settings

| Setting | Description |
|---|---|
| **Präsident (UID)** | Nextcloud username of the president. Approves expenses above the threshold. |
| **Kassier (UID)** | Nextcloud username of the treasurer. Approves expenses at/below the threshold, manages payouts and bookkeeping. |
| **Schwellwert (CHF)** | Amount in CHF above which the Präsident must approve. Expenses at or below this go directly to the Kassier. Default: 250. |

### Optional Settings

| Setting | Description |
|---|---|
| **Auszahlungsmethode** | Default payout method for new expenses: Banküberweisung (bank) or Bar (cash). |
| **Buchungsordner** | Relative path within the Kassier's Nextcloud files where PDF booking receipts (Spesenbeleg) are stored. Default: `Buchungsbelege`. |
| **Kategorien & Soll-Konten** | Expense categories with optional debit account numbers. Used for the bookkeeping export CSV. |

### User Settings

Each user can set their **CH IBAN** under **Profil** in the app. Only IBANs starting with "CH" are accepted.

## Workflow

```
 DRAFT ──submit──▶ SUBMITTED ──▶ Präsident (>Schwellwert) ──▶ APPROVED
                     │                    │                        │
                     │                    ▼ reject                 │
                     │               REJECTED ◀────────────────────┘
                     │                    │
                     │                    └── edit & resubmit ──▶ DRAFT
                     │
                     └── Kassier (≤Schwellwert) ──▶ BOOKKEEPING ──▶ PAYSTACK (Bank)
                                                         │               │
                                                         ▼               ▼
                                                        PAY ◀────────────┘
                                                         │
                                                         ▼
                                                       DONE
```

See `MEMORY.md` and `_architecture.md` for the complete state machine and architecture documentation.

## Development

```
spesenerfassung/
├── appinfo/                 Nextcloud app metadata & routes
├── lib/                     PHP backend
│   ├── AppInfo/             Bootstrap & DI container
│   ├── Controller/          REST API controllers
│   ├── Db/                  Entities & mappers
│   ├── Migration/           Database migrations
│   ├── Service/             Business logic, workflow, mail, PDF generation
│   ├── Settings/            Admin settings forms
│   └── Dashboard/           Nextcloud Dashboard widget
├── src/                     Vue 3 frontend
│   ├── views/               Page components
│   ├── components/          Reusable UI components
│   ├── store/               Pinia stores
│   ├── i18n/                German/English translations
│   ├── router.js            Hash-based routing
│   └── api.js               fetch()-based API client
├── templates/               PHP page templates
├── img/                     App icon & Makerspace logo
├── js/                      Pre-built JavaScript bundle
├── css/                     Custom stylesheet
├── composer.json            PHP dependencies
└── package.json             Frontend build dependencies
```

### Commands

```bash
composer lint      # PHP syntax check
npm run build      # Build frontend
npm run dev        # Watch mode for frontend development
```

## License

AGPL-3.0-or-later
