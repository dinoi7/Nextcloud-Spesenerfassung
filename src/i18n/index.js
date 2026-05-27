import { ref, computed } from 'vue'

function getLocale() {
  try {
    const htmlLang = document.documentElement?.lang || ''
    if (htmlLang.startsWith('de')) return 'de'
  } catch {}
  try {
    if (navigator.language?.startsWith('de')) return 'de'
  } catch {}
  return 'en'
}

const locale = ref(getLocale())

const messages = {
  de: {
    dashboard: 'Dashboard',
    approvals: 'Genehmigungen',
    settings: 'Einstellungen',
    newExpense: 'Neue Spesen',
    editExpense: 'Spesen bearbeiten',
    title: 'Titel',
    description: 'Beschreibung',
    descriptionOptional: 'Beschreibung (optional)',
    amount: 'Betrag (CHF)',
    category: 'Kategorie',
    expenseDate: 'Datum',
    status: 'Status',
    saveDraft: 'Als Entwurf speichern',
    submit: 'Einreichen',
    save: 'Speichern',
    cancel: 'Abbrechen',
    delete: 'Löschen',
    edit: 'Bearbeiten',
    approve: 'Genehmigen',
    reject: 'Zurückweisen',
    pay: 'Ausbezahlt',
    done: 'Erledigt',
    reason: 'Begründung',
    reasonRequired: 'Begründung ist erforderlich bei Zurückweisung',
    uploadReceipt: 'Beleg hochladen',
    dropFiles: 'Dateien hier ablegen oder klicken',
    camera: 'Kamera',
    fileInfo: 'PDF, JPG, PNG (max. 1 MB)',
    maxReceipts: 'Maximal 5 Belege erreicht',
    noExpenses: 'Keine Spesen vorhanden',
    noApprovals: 'Keine ausstehenden Genehmigungen',
    history: 'Verlauf',
    byPrefix: 'von',
    onDate: 'am',
    confirmDelete: 'Wirklich löschen?',
    statusDraft: 'Entwurf',
    statusSubmitted: 'Eingereicht',
    statusApproved: 'Freigegeben',
    statusRejected: 'Zurückgewiesen',
    statusPaid: 'Ausbezahlt',
    statusDone: 'Erledigt',
    totalAmount: 'Gesamtbetrag',
    allExpenses: 'Alle Spesen',
    pending: 'Ausstehend',
    completed: 'Erledigt',
    rejectConfirmation: 'Begründung für Zurückweisung:',
    submitConfirmation: 'Spesen einreichen? Der Status kann danach nicht mehr geändert werden.',
    settingsSaved: 'Einstellungen gespeichert',
    presidentUid: 'Präsident (Nextcloud Benutzername)',
    treasurerUid: 'Kassier (Nextcloud Benutzername)',
    threshold: 'Genehmigungsgrenze (CHF)',
    categories: 'Kategorien',
    addCategory: 'Kategorie hinzufügen',
    categoryName: 'Kategoriename',
    loading: 'Laden...',
    error: 'Fehler',
    success: 'Erfolg',
    created: 'Spesen erstellt',
    updated: 'Spesen aktualisiert',
    submitted: 'Spesen eingereicht',
    approved: 'Spesen genehmigt',
    rejected: 'Spesen zurückgewiesen',
    paid: 'Spesen ausbezahlt',
    doneSet: 'Spesen als erledigt markiert',
    deleted: 'Spesen gelöscht',
  },
  en: {
    dashboard: 'Dashboard',
    approvals: 'Approvals',
    settings: 'Settings',
    newExpense: 'New Expense',
    editExpense: 'Edit Expense',
    title: 'Title',
    description: 'Description',
    descriptionOptional: 'Description (optional)',
    amount: 'Amount (CHF)',
    category: 'Category',
    expenseDate: 'Date',
    status: 'Status',
    saveDraft: 'Save as Draft',
    submit: 'Submit',
    save: 'Save',
    cancel: 'Cancel',
    delete: 'Delete',
    edit: 'Edit',
    approve: 'Approve',
    reject: 'Reject',
    pay: 'Mark as Paid',
    done: 'Mark as Done',
    reason: 'Reason',
    reasonRequired: 'Reason is required for rejection',
    uploadReceipt: 'Upload Receipt',
    dropFiles: 'Drop files here or click to upload',
    camera: 'Camera',
    fileInfo: 'PDF, JPG, PNG (max. 1 MB)',
    maxReceipts: 'Maximum 5 receipts reached',
    noExpenses: 'No expenses found',
    noApprovals: 'No pending approvals',
    history: 'History',
    byPrefix: 'by',
    onDate: 'on',
    confirmDelete: 'Really delete?',
    statusDraft: 'Draft',
    statusSubmitted: 'Submitted',
    statusApproved: 'Approved',
    statusRejected: 'Rejected',
    statusPaid: 'Paid',
    statusDone: 'Done',
    totalAmount: 'Total Amount',
    allExpenses: 'All Expenses',
    pending: 'Pending',
    completed: 'Completed',
    rejectConfirmation: 'Reason for rejection:',
    submitConfirmation: 'Submit expense? Status cannot be changed afterwards.',
    settingsSaved: 'Settings saved',
    presidentUid: 'President (Nextcloud username)',
    treasurerUid: 'Treasurer (Nextcloud username)',
    threshold: 'Approval threshold (CHF)',
    categories: 'Categories',
    addCategory: 'Add Category',
    categoryName: 'Category Name',
    loading: 'Loading...',
    error: 'Error',
    success: 'Success',
    created: 'Expense created',
    updated: 'Expense updated',
    submitted: 'Expense submitted',
    approved: 'Expense approved',
    rejected: 'Expense rejected',
    paid: 'Expense marked as paid',
    doneSet: 'Expense marked as done',
    deleted: 'Expense deleted',
  },
}

export function useI18n() {
  const t = (key) => {
    const msg = messages[locale.value]
    if (msg && msg[key]) {
      return msg[key]
    }
    return key
  }

  const lang = computed(() => locale.value)

  return { t, lang, locale: lang }
}

export const i18n = { useI18n }
