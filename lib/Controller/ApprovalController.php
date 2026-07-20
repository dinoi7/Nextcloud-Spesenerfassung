<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Controller;

use OCA\Spesenerfassung\Service\ExpenseService;
use OCA\Spesenerfassung\Service\ReceiptService;
use OCA\Spesenerfassung\Service\SettingsService;
use OCA\Spesenerfassung\Service\UserSettingsService;
use OCA\Spesenerfassung\Service\BookingReceiptService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;

class ApprovalController extends Controller {
	private ExpenseService $expenseService;
	private IUserSession $userSession;
	private IUserManager $userManager;
	private ReceiptService $receiptService;
	private UserSettingsService $userSettingsService;
	private BookingReceiptService $bookingReceiptService;

	public function __construct(
		string $appName,
		IRequest $request,
		ExpenseService $expenseService,
		IUserSession $userSession,
		IUserManager $userManager,
		ReceiptService $receiptService,
		UserSettingsService $userSettingsService,
		BookingReceiptService $bookingReceiptService,
	) {
		parent::__construct($appName, $request);
		$this->expenseService = $expenseService;
		$this->userSession = $userSession;
		$this->userManager = $userManager;
		$this->receiptService = $receiptService;
		$this->userSettingsService = $userSettingsService;
		$this->bookingReceiptService = $bookingReceiptService;
	}

	private function getUserId(): string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return '';
		}
		return $user->getUID();
	}

	private function checkRole(string $requiredRole): bool {
		$userId = $this->getUserId();
		$presidentUid = SettingsService::getPresidentUid();
		$treasurerUid = SettingsService::getTreasurerUid();

		return match ($requiredRole) {
			'president' => $userId === $presidentUid,
			'treasurer' => $userId === $treasurerUid,
			default => false,
		};
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function submit(int $id): DataResponse {
		$userId = $this->getUserId();
		$expense = $this->expenseService->submit($id, $userId);
		if ($expense === null) {
			return new DataResponse(['error' => 'Cannot submit'], Http::STATUS_FORBIDDEN);
		}
		return new DataResponse($expense->toArray());
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function approve(int $id): DataResponse {
		if (!$this->checkRole('president')) {
			return new DataResponse(['error' => 'Only president can approve'], Http::STATUS_FORBIDDEN);
		}
		$expense = $this->expenseService->approve($id, $this->getUserId());
		if ($expense === null) {
			return new DataResponse(['error' => 'Cannot approve'], Http::STATUS_FORBIDDEN);
		}
		return new DataResponse($expense->toArray());
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function reject(int $id): DataResponse {
		$userId = $this->getUserId();
		$isPresident = $this->checkRole('president');
		$isTreasurer = $this->checkRole('treasurer');

		if (!$isPresident && !$isTreasurer) {
			return new DataResponse(['error' => 'Not authorized'], Http::STATUS_FORBIDDEN);
		}

		$data = $this->request->getParams();
		$reason = trim($data['reason'] ?? '');

		if ($reason === '') {
			return new DataResponse(['error' => 'Reason is required for rejection'], Http::STATUS_BAD_REQUEST);
		}

		$expense = $this->expenseService->reject($id, $userId, $reason);
		if ($expense === null) {
			return new DataResponse(['error' => 'Cannot reject'], Http::STATUS_FORBIDDEN);
		}
		return new DataResponse($expense->toArray());
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function pay(int $id): DataResponse {
		if (!$this->checkRole('treasurer')) {
			return new DataResponse(['error' => 'Only treasurer can pay'], Http::STATUS_FORBIDDEN);
		}
		$expense = $this->expenseService->findById($id);
		if ($expense === null) {
			return new DataResponse(['error' => 'Expense not found'], Http::STATUS_NOT_FOUND);
		}
		try {
			$booking = $this->bookingReceiptService->generate($expense, $this->getUserId());
		} catch (\Throwable $e) {
			$booking = ['success' => false, 'message' => 'Fehler beim Erstellen des Buchungsbelegs: ' . $e->getMessage()];
		}
		if ($booking['success']) {
			$result = $this->expenseService->pay($id, $this->getUserId());
			if ($result === null) {
				return new DataResponse(['error' => 'Cannot pay'], Http::STATUS_FORBIDDEN);
			}
			$data = $result->toArray();
		} else {
			$data = $expense->toArray();
		}
		$data['bookingReceipt'] = $booking;
		return new DataResponse($data);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function done(int $id): DataResponse {
		$userId = $this->getUserId();
		$expense = $this->expenseService->done($id, $userId);
		if ($expense === null) {
			return new DataResponse(['error' => 'Cannot set done'], Http::STATUS_FORBIDDEN);
		}
		return new DataResponse($expense->toArray());
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function pending(): DataResponse {
		$userId = $this->getUserId();
		$presidentUid = SettingsService::getPresidentUid();
		$treasurerUid = SettingsService::getTreasurerUid();

		$pending = [];

		if ($userId === $presidentUid) {
			$pending = $this->expenseService->getPendingForPresident();
		} elseif ($userId === $treasurerUid) {
			$pending = $this->expenseService->getPendingForTreasurer();
		} else {
			return new DataResponse(['error' => 'No pending approvals'], Http::STATUS_FORBIDDEN);
		}

		$userIds = array_map(fn($e) => $e->getUserId(), $pending);
		$names = [];
		foreach (array_unique($userIds) as $uid) {
			$u = $this->userManager->get($uid);
			$names[$uid] = $u ? $u->getDisplayName() : $uid;
		}

		$result = array_map(function ($e) use ($names) {
			$row = $e->toArray();
			$row['displayName'] = $names[$e->getUserId()] ?? $e->getUserId();
			$row['receiptCount'] = count($this->receiptService->findByExpenseId($e->getId()));
			return $row;
		}, $pending);
		return new DataResponse(array_values($result));
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function paystack(int $id): DataResponse {
		if (!$this->checkRole('treasurer')) {
			return new DataResponse(['error' => 'Only treasurer can set paystack'], Http::STATUS_FORBIDDEN);
		}
		$expense = $this->expenseService->addToPaystack($id, $this->getUserId());
		if ($expense === null) {
			return new DataResponse(['error' => 'Cannot transition to paystack'], Http::STATUS_FORBIDDEN);
		}
		return new DataResponse($expense->toArray());
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function paystackList(): DataResponse {
		if (!$this->checkRole('treasurer')) {
			return new DataResponse(['error' => 'Only treasurer can view paystack'], Http::STATUS_FORBIDDEN);
		}
		$expenses = $this->expenseService->getPaystackExpenses();
		$userIds = array_map(fn($e) => $e->getUserId(), $expenses);
		$names = [];
		foreach (array_unique($userIds) as $uid) {
			$u = $this->userManager->get($uid);
			$names[$uid] = $u ? $u->getDisplayName() : $uid;
		}
		$accounts = SettingsService::getExportAccounts();
		$result = array_map(function ($e) use ($names, $accounts) {
			$row = $e->toArray();
			$row['displayName'] = $names[$e->getUserId()] ?? $e->getUserId();
			$row['receiptCount'] = count($this->receiptService->findByExpenseId($e->getId()));
			$row['sollKonto'] = $accounts[$e->getCategory()] ?? '';
			if ($e->getPayoutMethod() === 'bank') {
				$row['submitterName'] = $row['displayName'];
				$row['iban'] = $this->userSettingsService->getIban($e->getUserId());
			}
			return $row;
		}, $expenses);
		return new DataResponse($result);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function paystackExport() {
		if (!$this->checkRole('treasurer')) {
			return new DataResponse(['error' => 'Only treasurer can export'], Http::STATUS_FORBIDDEN);
		}
		$expenses = $this->expenseService->getPaystackExpenses();
		$accounts = SettingsService::getExportAccounts();
		$csv = "\xEF\xBB\xBF";
		$csv .= "\"Datum\";\"Text\";\"Soll\";\"Betrag (CHF)\"\n";
		foreach ($expenses as $e) {
			$id = $e->getId();
			$date = date('d.m.Y', strtotime($e->getExpenseDate()));
			$name = $this->sani($this->resolveDisplayName($e->getUserId()));
			$title = $this->sani($e->getTitle() ?? '');
			$desc = $this->sani($e->getDescription() ?? '');
			$text = 'Spesen: ' . $id . ', ' . $name . ', ' . $title . ', ' . $desc;
			$cat = $e->getCategory();
			$soll = $this->sani($accounts[$cat] ?? '');
			$amount = '-' . number_format((float) $e->getAmount(), 2, '.', '');
			$csv .= "\"$date\";\"$text\";\"$soll\";\"$amount\"\n";
		}

		return new DataDownloadResponse($csv, 'zahlstapel.csv', 'text/csv; charset=utf-8');
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function paystackExportSingle(int $id) {
		if (!$this->checkRole('treasurer')) {
			return new DataResponse(['error' => 'Only treasurer can export'], Http::STATUS_FORBIDDEN);
		}
		$expense = $this->expenseService->findById($id);
		if ($expense === null || $expense->getStatus() !== \OCA\Spesenerfassung\Db\Expense::STATUS_PAYSTACK) {
			return new DataResponse(['error' => 'Expense not in paystack'], Http::STATUS_NOT_FOUND);
		}
		$accounts = SettingsService::getExportAccounts();
		$csv = "\xEF\xBB\xBF";
		$csv .= "\"Datum\";\"Text\";\"Soll\";\"Betrag (CHF)\"\n";
		$date = date('d.m.Y', strtotime($expense->getExpenseDate()));
		$name = $this->sani($this->resolveDisplayName($expense->getUserId()));
		$title = $this->sani($expense->getTitle() ?? '');
		$desc = $this->sani($expense->getDescription() ?? '');
		$text = 'Spesen: ' . $expense->getId() . ', ' . $name . ', ' . $title . ', ' . $desc;
		$cat = $expense->getCategory();
		$soll = $this->sani($accounts[$cat] ?? '');
		$amount = '-' . number_format((float) $expense->getAmount(), 2, '.', '');
		$csv .= "\"$date\";\"$text\";\"$soll\";\"$amount\"\n";
		return new DataDownloadResponse($csv, 'zahlstapel-' . $id . '.csv', 'text/csv; charset=utf-8');
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function paystackPayAll(): DataResponse {
		if (!$this->checkRole('treasurer')) {
			return new DataResponse(['error' => 'Only treasurer can pay all'], Http::STATUS_FORBIDDEN);
		}
		$expenses = $this->expenseService->getPaystackExpenses();
		$results = [];
		$paidCount = 0;
		$messages = [];

		foreach ($expenses as $expense) {
			try {
				$booking = $this->bookingReceiptService->generate($expense, $this->getUserId());
			} catch (\Throwable $e) {
				$booking = ['success' => false, 'message' => 'Fehler: ' . $e->getMessage()];
			}

			if ($booking['success']) {
				$result = $this->expenseService->pay($expense->getId(), $this->getUserId());
				if ($result !== null) {
					$row = $result->toArray();
					$row['bookingReceipt'] = $booking;
					$results[] = $row;
					$paidCount++;
					$messages[] = $booking['message'];
				}
			} else {
				$row = $expense->toArray();
				$row['bookingReceipt'] = $booking;
				$results[] = $row;
				$messages[] = 'Spesen ' . $expense->getId() . ': ' . $booking['message'];
			}
		}

		$summaryMsg = implode("\n", $messages);
		return new DataResponse([
			'paid' => $paidCount,
			'total' => count($expenses),
			'expenses' => $results,
			'bookingReceipt' => ['success' => $paidCount === count($expenses), 'message' => $summaryMsg],
		]);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function bookkeeping(int $id): DataResponse {
		if (!$this->checkRole('treasurer')) {
			return new DataResponse(['error' => 'Only treasurer can set bookkeeping'], Http::STATUS_FORBIDDEN);
		}
		$expense = $this->expenseService->addToBookkeeping($id, $this->getUserId());
		if ($expense === null) {
			return new DataResponse(['error' => 'Cannot transition to bookkeeping'], Http::STATUS_FORBIDDEN);
		}
		return new DataResponse($expense->toArray());
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function bookkeepingList(): DataResponse {
		if (!$this->checkRole('treasurer')) {
			return new DataResponse(['error' => 'Only treasurer can view bookkeeping'], Http::STATUS_FORBIDDEN);
		}
		$expenses = $this->expenseService->getBookkeepingExpenses();
		$userIds = array_map(fn($e) => $e->getUserId(), $expenses);
		$names = [];
		foreach (array_unique($userIds) as $uid) {
			$u = $this->userManager->get($uid);
			$names[$uid] = $u ? $u->getDisplayName() : $uid;
		}
		$accounts = SettingsService::getExportAccounts();
		$result = array_map(function ($e) use ($names, $accounts) {
			$row = $e->toArray();
			$row['displayName'] = $names[$e->getUserId()] ?? $e->getUserId();
			$row['receiptCount'] = count($this->receiptService->findByExpenseId($e->getId()));
			$row['sollKonto'] = $accounts[$e->getCategory()] ?? '';
			return $row;
		}, $expenses);
		return new DataResponse($result);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function bookkeepingExport() {
		if (!$this->checkRole('treasurer')) {
			return new DataResponse(['error' => 'Only treasurer can export'], Http::STATUS_FORBIDDEN);
		}
		$expenses = $this->expenseService->getBookkeepingExpenses();
		$accounts = SettingsService::getExportAccounts();
		$csv = "\xEF\xBB\xBF";
		$csv .= "\"Datum\";\"Text\";\"Soll\";\"Betrag (CHF)\"\n";
		foreach ($expenses as $e) {
			$id = $e->getId();
			$date = date('d.m.Y', strtotime($e->getExpenseDate()));
			$name = $this->sani($this->resolveDisplayName($e->getUserId()));
			$title = $this->sani($e->getTitle() ?? '');
			$desc = $this->sani($e->getDescription() ?? '');
			$text = 'Spesen: ' . $id . ', ' . $name . ', ' . $title . ', ' . $desc;
			$cat = $e->getCategory();
			$soll = $this->sani($accounts[$cat] ?? '');
			$amount = '-' . number_format((float) $e->getAmount(), 2, '.', '');
			$csv .= "\"$date\";\"$text\";\"$soll\";\"$amount\"\n";
		}
		return new DataDownloadResponse($csv, 'buchhaltung.csv', 'text/csv; charset=utf-8');
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function bookkeepingExportSingle(int $id) {
		if (!$this->checkRole('treasurer')) {
			return new DataResponse(['error' => 'Only treasurer can export'], Http::STATUS_FORBIDDEN);
		}
		$expense = $this->expenseService->findById($id);
		if ($expense === null || $expense->getStatus() !== \OCA\Spesenerfassung\Db\Expense::STATUS_BOOKKEEPING) {
			return new DataResponse(['error' => 'Expense not in bookkeeping'], Http::STATUS_NOT_FOUND);
		}
		$accounts = SettingsService::getExportAccounts();
		$csv = "\xEF\xBB\xBF";
		$csv .= "\"Datum\";\"Text\";\"Soll\";\"Betrag (CHF)\"\n";
		$date = date('d.m.Y', strtotime($expense->getExpenseDate()));
		$name = $this->sani($this->resolveDisplayName($expense->getUserId()));
		$title = $this->sani($expense->getTitle() ?? '');
		$desc = $this->sani($expense->getDescription() ?? '');
		$text = 'Spesen: ' . $expense->getId() . ', ' . $name . ', ' . $title . ', ' . $desc;
		$cat = $expense->getCategory();
		$soll = $this->sani($accounts[$cat] ?? '');
		$amount = '-' . number_format((float) $expense->getAmount(), 2, '.', '');
		$csv .= "\"$date\";\"$text\";\"$soll\";\"$amount\"\n";
		return new DataDownloadResponse($csv, 'buchhaltung-' . $id . '.csv', 'text/csv; charset=utf-8');
	}

	private function sani(string $field): string {
		return str_replace(['"', ';'], '_', $field);
	}

	private function resolveDisplayName(string $userId): string {
		$u = $this->userManager->get($userId);
		return $u ? $u->getDisplayName() : $userId;
	}

	private function getUserIban(string $userId): string {
		return $this->userSettingsService->getIban($userId);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function evaluation(): DataResponse {
		if (!$this->checkRole('president') && !$this->checkRole('treasurer')) {
			return new DataResponse(['error' => 'Not authorized'], Http::STATUS_FORBIDDEN);
		}
		$expenses = $this->expenseService->findAll();
		$userIds = array_map(fn($e) => $e->getUserId(), $expenses);
		$names = [];
		foreach (array_unique($userIds) as $uid) {
			$u = $this->userManager->get($uid);
			$names[$uid] = $u ? $u->getDisplayName() : $uid;
		}
		$result = array_map(function ($e) use ($names) {
			$row = $e->toArray();
			$row['displayName'] = $names[$e->getUserId()] ?? $e->getUserId();
			$receipts = $this->receiptService->findByExpenseId($e->getId());
			$row['receipts'] = array_map(fn($r) => $r->toArray(), $receipts);
			$row['receiptCount'] = count($receipts);
			return $row;
		}, $expenses);
		return new DataResponse(array_values($result));
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function evaluationExport() {
		if (!$this->checkRole('president') && !$this->checkRole('treasurer')) {
			return new DataResponse(['error' => 'Not authorized'], Http::STATUS_FORBIDDEN);
		}
		$expenses = $this->expenseService->findAll();
		$userIds = array_map(fn($e) => $e->getUserId(), $expenses);
		$names = [];
		foreach (array_unique($userIds) as $uid) {
			$u = $this->userManager->get($uid);
			$names[$uid] = $u ? $u->getDisplayName() : $uid;
		}
		$csv = "\xEF\xBB\xBF";
		$csv .= "\"Spesennummer\";\"Status\";\"Datum\";\"Erfasser\";\"Titel\";\"Kategorie\";\"Betrag (CHF)\";\"Fremdwährung\";\"Fremdbetrag\";\"Auszahlungsart\"\n";
		foreach ($expenses as $e) {
			$id = (string) $e->getId();
			$status = $e->getStatus();
			$date = date('d.m.Y', strtotime($e->getExpenseDate()));
			$name = $this->sani($names[$e->getUserId()] ?? $e->getUserId());
			$title = $this->sani($e->getTitle() ?? '');
			$category = $this->sani($e->getCategory() ?? '');
			$amount = number_format((float) $e->getAmount(), 2, '.', '');
			$fc = $this->sani($e->getForeignCurrency() ?? '');
			$fa = $e->getForeignAmount() !== null ? number_format((float) $e->getForeignAmount(), 2, '.', '') : '';
			$payout = $e->getPayoutMethod() === 'bank' ? 'Bank' : ($e->getPayoutMethod() ? 'Bar' : '');
			$csv .= "\"$id\";\"$status\";\"$date\";\"$name\";\"$title\";\"$category\";\"$amount\";\"$fc\";\"$fa\";\"$payout\"\n";
		}
		return new DataDownloadResponse($csv, 'auswertung.csv', 'text/csv; charset=utf-8');
	}
}
