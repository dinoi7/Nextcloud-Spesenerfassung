<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Service;

use OCA\Spesenerfassung\Db\Approval;
use OCA\Spesenerfassung\Db\Expense;
use OCP\Mail\IMailer;
use OCP\IURLGenerator;
use OCP\IL10N;

class MailService {
	private IMailer $mailer;
	private IURLGenerator $urlGenerator;

	public function __construct(IMailer $mailer, IURLGenerator $urlGenerator) {
		$this->mailer = $mailer;
		$this->urlGenerator = $urlGenerator;
	}

	public function sendStatusNotification(Expense $expense, string $action, string $recipientUid): void {
		$subject = $this->getSubject($expense, $action);
		$bodyText = $this->getBodyText($expense, $action);
		$bodyHtml = $this->getBodyHtml($expense, $action);

		try {
			$message = $this->mailer->createMessage();
			$message->setTo([$recipientUid => $recipientUid]);
			$message->setFrom(['noreply@makerspace-reinach.ch' => 'Makerspace Reinach']);
			$message->setSubject($subject);
			$message->setPlainBody($bodyText);
			$message->setHtmlBody($bodyHtml);

			$this->mailer->send($message);
		} catch (\Throwable $e) {
			// Log failure but don't block the operation
		}
	}

	public function notifySubmitterSubmitted(Expense $expense): void {
		// Confirmation to submitter that expense was submitted
		try {
			$message = $this->mailer->createMessage();
			$message->setTo([$expense->getUserId() => $expense->getUserId()]);
			$message->setFrom(['noreply@makerspace-reinach.ch' => 'Makerspace Reinach']);
			$message->setSubject('Spese eingereicht: ' . $expense->getTitle());
			$subjectDe = 'Spese eingereicht: ' . $expense->getTitle();
			$subjectEn = 'Expense submitted: ' . $expense->getTitle();
			$message->setSubject($subjectDe);

			$amount = number_format((float) $expense->getAmount(), 2, '.', '\'');

			$bodyText = "Deine Spese wurde erfolgreich eingereicht.\n\n"
				. "Titel: {$expense->getTitle()}\n"
				. "Betrag: CHF {$amount}\n"
				. "Kategorie: {$expense->getCategory()}\n"
				. "Datum: {$expense->getExpenseDate()}\n\n"
				. "Du erhältst eine Benachrichtigung, sobald der Status aktualisiert wird.\n\n"
				. "\n---\n\n"
				. "Your expense has been successfully submitted.\n\n"
				. "Title: {$expense->getTitle()}\n"
				. "Amount: CHF {$amount}\n"
				. "Category: {$expense->getCategory()}\n"
				. "Date: {$expense->getExpenseDate()}\n\n"
				. "You will be notified when the status is updated.\n";

			$message->setPlainBody($bodyText);
			$this->mailer->send($message);
		} catch (\Throwable) {
		}
	}

	private function getSubject(Expense $expense, string $action): string {
		$title = $expense->getTitle();
		$amount = number_format((float) $expense->getAmount(), 2, '.', '\'');

		return match ($action) {
			Approval::ACTION_APPROVED => "Spese genehmigt: {$title} (CHF {$amount}) / Expense approved: {$title}",
			Approval::ACTION_REJECTED => "Spese zurückgewiesen: {$title} (CHF {$amount}) / Expense rejected: {$title}",
			Approval::ACTION_PAID => "Spese ausbezahlt: {$title} (CHF {$amount}) / Expense paid: {$title}",
			default => "Neue Spese: {$title} (CHF {$amount}) / New expense: {$title}",
		};
	}

	private function getBodyText(Expense $expense, string $action): string {
		$amount = number_format((float) $expense->getAmount(), 2, '.', '\'');
		$url = $this->urlGenerator->linkToRouteAbsolute('spesenerfassung.page.index');

		$de = match ($action) {
			Approval::ACTION_SUBMITTED => "Eine neue Spese zur Genehmigung wurde eingereicht:\n\n"
				. "Titel: {$expense->getTitle()}\n"
				. "Betrag: CHF {$amount}\n"
				. "Kategorie: {$expense->getCategory()}\n"
				. "Datum: {$expense->getExpenseDate()}\n"
				. "Von: {$expense->getUserId()}\n\n"
				. "Link: {$url}",
			Approval::ACTION_APPROVED => "Eine Spese wurde genehmigt und ist zur Auszahlung bereit:\n\n"
				. "Titel: {$expense->getTitle()}\n"
				. "Betrag: CHF {$amount}\n"
				. "Link: {$url}",
			Approval::ACTION_REJECTED => "Eine Spese wurde zurückgewiesen:\n\n"
				. "Titel: {$expense->getTitle()}\n"
				. "Betrag: CHF {$amount}\n"
				. "Begründung siehe Applikation.\n"
				. "Link: {$url}",
			Approval::ACTION_PAID => "Deine Spese wurde als ausbezahlt markiert:\n\n"
				. "Titel: {$expense->getTitle()}\n"
				. "Betrag: CHF {$amount}\n"
				. "Du kannst sie nun auf \"Erledigt\" setzen.\n"
				. "Link: {$url}",
			default => "Statusaktualisierung: {$expense->getTitle()}",
		};

		$en = match ($action) {
			Approval::ACTION_SUBMITTED => "\n\n---\n\nA new expense has been submitted for approval:\n\n"
				. "Title: {$expense->getTitle()}\n"
				. "Amount: CHF {$amount}\n"
				. "Category: {$expense->getCategory()}\n"
				. "Date: {$expense->getExpenseDate()}\n"
				. "By: {$expense->getUserId()}\n\n"
				. "Link: {$url}",
			Approval::ACTION_APPROVED => "\n\n---\n\nAn expense has been approved and is ready for payment:\n\n"
				. "Title: {$expense->getTitle()}\n"
				. "Amount: CHF {$amount}\n"
				. "Link: {$url}",
			Approval::ACTION_REJECTED => "\n\n---\n\nAn expense has been rejected:\n\n"
				. "Title: {$expense->getTitle()}\n"
				. "Amount: CHF {$amount}\n"
				. "See application for reason.\n"
				. "Link: {$url}",
			Approval::ACTION_PAID => "\n\n---\n\nYour expense has been marked as paid:\n\n"
				. "Title: {$expense->getTitle()}\n"
				. "Amount: CHF {$amount}\n"
				. "You can now set it to \"Done\".\n"
				. "Link: {$url}",
			default => "Status update: {$expense->getTitle()}",
		};

		return $de . $en;
	}

	private function getBodyHtml(Expense $expense, string $action): string {
		$amount = number_format((float) $expense->getAmount(), 2, '.', '\'');
		$url = $this->urlGenerator->linkToRouteAbsolute('spesenerfassung.page.index');

		$actionDe = match ($action) {
			Approval::ACTION_SUBMITTED => 'Neue Spese zur Genehmigung',
			Approval::ACTION_APPROVED => 'Spese genehmigt und zur Auszahlung bereit',
			Approval::ACTION_REJECTED => 'Spese zurückgewiesen',
			Approval::ACTION_PAID => 'Spese ausbezahlt',
			default => 'Statusaktualisierung',
		};

		return <<<HTML
<!DOCTYPE html>
<html>
<body>
<h2>{$actionDe}</h2>
<table>
<tr><td><strong>Titel / Title:</strong></td><td>{$expense->getTitle()}</td></tr>
<tr><td><strong>Betrag / Amount:</strong></td><td>CHF {$amount}</td></tr>
<tr><td><strong>Kategorie / Category:</strong></td><td>{$expense->getCategory()}</td></tr>
<tr><td><strong>Datum / Date:</strong></td><td>{$expense->getExpenseDate()}</td></tr>
<tr><td><strong>Von / From:</strong></td><td>{$expense->getUserId()}</td></tr>
</table>
<p><a href="{$url}">Applikation öffnen / Open application</a></p>
</body>
</html>
HTML;
	}
}
