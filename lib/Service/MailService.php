<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Service;

use OCA\Spesenerfassung\Db\Approval;
use OCA\Spesenerfassung\Db\Expense;
use OCP\Mail\IMailer;
use OCP\IURLGenerator;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class MailService {
	private IMailer $mailer;
	private IURLGenerator $urlGenerator;
	private IUserManager $userManager;
	private LoggerInterface $logger;
	private SettingsService $settingsService;

	public function __construct(
		IMailer $mailer,
		IURLGenerator $urlGenerator,
		IUserManager $userManager,
		LoggerInterface $logger,
		SettingsService $settingsService,
	) {
		$this->mailer = $mailer;
		$this->urlGenerator = $urlGenerator;
		$this->userManager = $userManager;
		$this->logger = $logger;
		$this->settingsService = $settingsService;
	}

	private function resolveEmail(string $uid): ?string {
		$user = $this->userManager->get($uid);
		if ($user === null) {
			$this->logger->warning("Spesennerfassung: User '$uid' not found for email notification", ['app' => 'spesenerfassung']);
			return null;
		}
		$email = $user->getEMailAddress();
		if ($email === null || $email === '') {
			$this->logger->warning("Spesennerfassung: User '$uid' has no email address set", ['app' => 'spesenerfassung']);
			return null;
		}
		return $email;
	}

	public function sendStatusNotification(Expense $expense, string $action, string $recipientUid): void {
		$recipientEmail = $this->resolveEmail($recipientUid);
		if ($recipientEmail === null) {
			return;
		}

		$subject = $this->getSubject($expense, $action);
		$bodyText = $this->getBodyText($expense, $action);
		$bodyHtml = $this->getBodyHtml($expense, $action);

		try {
			$message = $this->mailer->createMessage();
			$message->setTo([$recipientEmail => $recipientUid]);
			$message->setFrom([$this->settingsService->getSenderEmail() => $this->settingsService->getSenderName()]);
			$message->setSubject($subject);
			$message->setPlainBody($bodyText);
			$message->setHtmlBody($bodyHtml);

			$failed = $this->mailer->send($message);
			if (!empty($failed)) {
				$this->logger->error("Spesennerfassung: Failed to send status notification to $recipientEmail (uid: $recipientUid)", ['app' => 'spesenerfassung', 'failed' => $failed]);
			}
		} catch (\Throwable $e) {
			$this->logger->error("Spesennerfassung: Error sending status notification to $recipientEmail (uid: $recipientUid): " . $e->getMessage(), ['app' => 'spesenerfassung', 'exception' => $e]);
		}
	}

	public function notifySubmitterSubmitted(Expense $expense): void {
		$submitterUid = $expense->getUserId();
		$submitterEmail = $this->resolveEmail($submitterUid);
		if ($submitterEmail === null) {
			return;
		}

		try {
			$message = $this->mailer->createMessage();
			$message->setTo([$submitterEmail => $submitterUid]);
			$message->setFrom([$this->settingsService->getSenderEmail() => $this->settingsService->getSenderName()]);

			$amount = number_format((float) $expense->getAmount(), 2, '.', '\'');
			$subject = 'Spesen eingereicht: ' . $expense->getTitle();
			$message->setSubject($subject);

			$bodyText = "Deine Spesen wurde erfolgreich eingereicht.\n\n"
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
			$failed = $this->mailer->send($message);
			if (!empty($failed)) {
				$this->logger->error("Spesennerfassung: Failed to send submission confirmation to $submitterEmail (uid: $submitterUid)", ['app' => 'spesenerfassung', 'failed' => $failed]);
			}
		} catch (\Throwable $e) {
			$this->logger->error("Spesennerfassung: Error sending submission confirmation to $submitterEmail (uid: $submitterUid): " . $e->getMessage(), ['app' => 'spesenerfassung', 'exception' => $e]);
		}
	}

	private function getSubject(Expense $expense, string $action): string {
		$title = $expense->getTitle();
		$amount = number_format((float) $expense->getAmount(), 2, '.', '\'');

		return match ($action) {
			Approval::ACTION_APPROVED => "Spesen genehmigt: {$title} (CHF {$amount}) / Expense approved: {$title}",
			Approval::ACTION_REJECTED => "Spesen zurückgewiesen: {$title} (CHF {$amount}) / Expense rejected: {$title}",
			Approval::ACTION_PAID => "Spesen ausbezahlt: {$title} (CHF {$amount}) / Expense paid: {$title}",
			default => "Neue Spesen: {$title} (CHF {$amount}) / New expense: {$title}",
		};
	}

	private function getBodyText(Expense $expense, string $action): string {
		$amount = number_format((float) $expense->getAmount(), 2, '.', '\'');
		$url = $this->urlGenerator->linkToRouteAbsolute('spesenerfassung.page.index');

		$de = match ($action) {
			Approval::ACTION_SUBMITTED => "Eine neue Spesen zur Genehmigung wurde eingereicht:\n\n"
				. "Titel: {$expense->getTitle()}\n"
				. "Betrag: CHF {$amount}\n"
				. "Kategorie: {$expense->getCategory()}\n"
				. "Datum: {$expense->getExpenseDate()}\n"
				. "Von: {$expense->getUserId()}\n\n"
				. "Link: {$url}",
			Approval::ACTION_APPROVED => "Eine Spesen wurde genehmigt und ist zur Auszahlung bereit:\n\n"
				. "Titel: {$expense->getTitle()}\n"
				. "Betrag: CHF {$amount}\n"
				. "Link: {$url}",
			Approval::ACTION_REJECTED => "Eine Spesen wurde zurückgewiesen:\n\n"
				. "Titel: {$expense->getTitle()}\n"
				. "Betrag: CHF {$amount}\n"
				. "Begründung siehe Applikation.\n"
				. "Link: {$url}",
			Approval::ACTION_PAID => "Deine Spesen wurde als ausbezahlt markiert:\n\n"
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

		$title = htmlspecialchars($expense->getTitle() ?? '', ENT_QUOTES, 'UTF-8');
		$category = htmlspecialchars($expense->getCategory() ?? '', ENT_QUOTES, 'UTF-8');
		$date = htmlspecialchars($expense->getExpenseDate() ?? '', ENT_QUOTES, 'UTF-8');
		$userId = htmlspecialchars($expense->getUserId() ?? '', ENT_QUOTES, 'UTF-8');

		$actionDe = match ($action) {
			Approval::ACTION_SUBMITTED => 'Neue Spesen zur Genehmigung',
			Approval::ACTION_APPROVED => 'Spesen genehmigt und zur Auszahlung bereit',
			Approval::ACTION_REJECTED => 'Spesen zurückgewiesen',
			Approval::ACTION_PAID => 'Spesen ausbezahlt',
			default => 'Statusaktualisierung',
		};

		return <<<HTML
<!DOCTYPE html>
<html>
<body>
<h2>{$actionDe}</h2>
<table>
<tr><td><strong>Titel / Title:</strong></td><td>{$title}</td></tr>
<tr><td><strong>Betrag / Amount:</strong></td><td>CHF {$amount}</td></tr>
<tr><td><strong>Kategorie / Category:</strong></td><td>{$category}</td></tr>
<tr><td><strong>Datum / Date:</strong></td><td>{$date}</td></tr>
<tr><td><strong>Von / From:</strong></td><td>{$userId}</td></tr>
</table>
<p><a href="{$url}">Applikation öffnen / Open application</a></p>
</body>
</html>
HTML;
	}
}
