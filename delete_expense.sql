-- ============================================================
-- Spesenerfassung: Eine einzelne Spesen aus der DB löschen
-- Tabellen-Präfix: oc_  (bei Bedarf anpassen)
-- Ausführen auf nextcloud3:
--   mysql -u nextcloud -p YOUR_PASSWORD -D nextcloud < delete_expense.sql
-- ============================================================
-- Vorher <SPESEN_ID> durch die tatsächliche ID ersetzen!
-- ============================================================

SET @expense_id = <SPESEN_ID>;

SELECT '=== Vorschau: Diese Spesen wird gelöscht ===' AS hinweis;

SELECT e.id, e.title, e.user_id, e.amount, e.status, e.expense_date,
       (SELECT COUNT(*) FROM oc_sp_receipts WHERE expense_id = e.id) AS belege,
       (SELECT COUNT(*) FROM oc_sp_approvals WHERE expense_id = e.id) AS verlauf_eintraege
FROM oc_sp_expenses e
WHERE e.id = @expense_id;

SELECT '=== Belege, die mitgelöscht werden ===' AS hinweis;

SELECT id, file_name, mime_type, ROUND(size/1024, 1) AS size_kb
FROM oc_sp_receipts
WHERE expense_id = @expense_id;

SELECT '=== Verlaufseinträge, die mitgelöscht werden ===' AS hinweis;

SELECT id, user_id, action, comment, created_at
FROM oc_sp_approvals
WHERE expense_id = @expense_id;

SELECT '=== LÖSCHVORGANG ===' AS hinweis;

DELETE FROM oc_sp_expenses WHERE id = @expense_id;

SELECT CONCAT('Spesen-ID ', @expense_id, ' gelöscht.') AS ergebnis;

SELECT CONCAT('Verbleibende Spesen: ', COUNT(*)) AS status FROM oc_sp_expenses;
