<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Controller;

use OCA\Spesenerfassung\Db\Expense;
use OCA\Spesenerfassung\Service\ExpenseService;
use OCA\Spesenerfassung\Service\ReceiptService;
use OCA\Spesenerfassung\Service\SettingsService;
use OCA\Spesenerfassung\Service\UserSettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class ExpenseController extends Controller {
	private ExpenseService $expenseService;
	private ReceiptService $receiptService;
	private UserSettingsService $userSettingsService;
	private IUserSession $userSession;
	private IUserManager $userManager;
	private LoggerInterface $logger;

	public function __construct(
		string $appName,
		IRequest $request,
		ExpenseService $expenseService,
		ReceiptService $receiptService,
		UserSettingsService $userSettingsService,
		IUserSession $userSession,
		IUserManager $userManager,
		LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
		$this->expenseService = $expenseService;
		$this->receiptService = $receiptService;
		$this->userSettingsService = $userSettingsService;
		$this->userSession = $userSession;
		$this->userManager = $userManager;
		$this->logger = $logger;
	}

	private function getUserId(): string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return '';
		}
		return $user->getUID();
	}

	private function mapDisplayNames(array $userIds): array {
		$map = [];
		foreach (array_unique($userIds) as $uid) {
			$u = $this->userManager->get($uid);
			$map[$uid] = $u ? $u->getDisplayName() : $uid;
		}
		return $map;
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): DataResponse {
		$userId = $this->getUserId();
		$expenses = $this->expenseService->findAllForUser($userId);
		$userIds = array_map(fn(Expense $e) => $e->getUserId(), $expenses);
		$names = $this->mapDisplayNames($userIds);

		$result = array_map(function (Expense $e) use ($names) {
			$row = $e->toArray();
			$row['displayName'] = $names[$e->getUserId()] ?? $e->getUserId();
			$row['receiptCount'] = count($this->receiptService->findByExpenseId($e->getId()));
			return $row;
		}, $expenses);
		return new DataResponse($result);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function show(int $id): DataResponse {
		$expense = $this->expenseService->findById($id);
		if ($expense === null) {
			return new DataResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
		}

		$data = $expense->toArray();
		$receipts = $this->receiptService->findByExpenseId($id);
		$data['receipts'] = array_map(function ($r) {
			$row = $r->toArray();
			$row['pageCount'] = $this->receiptService->getPageCount($r);
			return $row;
		}, $receipts);
		$data['receiptCount'] = count($receipts);

		$history = $this->expenseService->getHistory($id);
		$historyUserIds = array_map(fn($a) => $a->getUserId(), $history);
		$names = $this->mapDisplayNames(array_merge($historyUserIds, [$expense->getUserId()]));
		$data['displayName'] = $names[$expense->getUserId()] ?? $expense->getUserId();
		$data['history'] = array_map(function ($a) use ($names) {
			$row = $a->toArray();
			$row['displayName'] = $names[$a->getUserId()] ?? $a->getUserId();
			return $row;
		}, $history);

		$treasurerUid = SettingsService::getTreasurerUid();
		if ($treasurerUid !== '' && $this->getUserId() === $treasurerUid && $expense->getPayoutMethod() === 'bank') {
			$iban = $this->userSettingsService->getIban($expense->getUserId());
			if ($iban !== '') {
				$data['iban'] = $iban;
				$data['submitterName'] = $data['displayName'];
			}
		}

		return new DataResponse($data);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function ping(): DataResponse {
		return new DataResponse(['ok' => true, 'time' => time()]);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function create(): DataResponse {
		$userId = $this->getUserId();
		$data = $this->request->getParams();

		if (empty($data['title']) || empty($data['amount']) || empty($data['category']) || empty($data['expenseDate'])) {
			return new DataResponse(['error' => 'Missing required fields'], Http::STATUS_BAD_REQUEST);
		}

		if (($data['payoutMethod'] ?? '') === 'bank') {
			$iban = $this->userSettingsService->getIban($userId);
			if ($iban === '') {
				return new DataResponse(['error' => 'IBAN ist erforderlich für Bank-Auszahlung. Bitte im Profil hinterlegen.'], Http::STATUS_BAD_REQUEST);
			}
		}

		try {
			$expense = $this->expenseService->create($userId, $data);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		if ($expense === null) {
			return new DataResponse(['error' => 'Failed to create expense'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse($expense->toArray(), Http::STATUS_CREATED);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function update(int $id): DataResponse {
		$userId = $this->getUserId();
		$data = $this->request->getParams();

		if (($data['payoutMethod'] ?? '') === 'bank') {
			$iban = $this->userSettingsService->getIban($userId);
			if ($iban === '') {
				return new DataResponse(['error' => 'IBAN ist erforderlich für Bank-Auszahlung. Bitte im Profil hinterlegen.'], Http::STATUS_BAD_REQUEST);
			}
		}

		$expense = $this->expenseService->update($id, $userId, $data);
		if ($expense === null) {
			return new DataResponse(['error' => 'Not found or not editable'], Http::STATUS_FORBIDDEN);
		}

		return new DataResponse($expense->toArray());
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function destroy(int $id): DataResponse {
		$userId = $this->getUserId();
		$deleted = $this->expenseService->delete($id, $userId);
		if (!$deleted) {
			return new DataResponse(['error' => 'Not found or cannot be deleted'], Http::STATUS_FORBIDDEN);
		}
		return new DataResponse(['success' => true]);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function uploadReceipt(int $id): DataResponse {
		$userId = $this->getUserId();
		$expense = $this->expenseService->findById($id);

		if ($expense === null || $expense->getUserId() !== $userId) {
			return new DataResponse(['error' => 'Not found'], Http::STATUS_FORBIDDEN);
		}
		if (!in_array($expense->getStatus(), [Expense::STATUS_DRAFT, Expense::STATUS_REJECTED])) {
			return new DataResponse(['error' => 'Expense is not editable'], Http::STATUS_FORBIDDEN);
		}

		$file = $this->request->getUploadedFile('receipt');
		if ($file === null) {
			return new DataResponse(['error' => 'No file uploaded'], Http::STATUS_BAD_REQUEST);
		}

		$receipt = $this->receiptService->upload(
			$id,
			$file['name'] ?? $file['tmp_name'] ?? 'receipt',
			$file['tmp_name'],
			$file['type'] ?? 'application/octet-stream',
			$file['size'] ?? 0
		);

		if ($receipt === null) {
			return new DataResponse(['error' => 'Upload failed. Check file type (PDF, JPG, PNG) and size (max 1MB).'], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse($receipt->toArray(), Http::STATUS_CREATED);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function deleteReceipt(int $id, int $receiptId): DataResponse {
		$userId = $this->getUserId();
		$expense = $this->expenseService->findById($id);

		if ($expense === null || $expense->getUserId() !== $userId) {
			return new DataResponse(['error' => 'Not found'], Http::STATUS_FORBIDDEN);
		}

		$receipt = $this->receiptService->findById($receiptId);
		if ($receipt === null || $receipt->getExpenseId() !== $id) {
			return new DataResponse(['error' => 'Receipt not found'], Http::STATUS_NOT_FOUND);
		}

		$this->receiptService->delete($receiptId);
		return new DataResponse(['success' => true]);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function updateCategory(int $id): DataResponse {
		$treasurerUid = SettingsService::getTreasurerUid();
		if ($treasurerUid === '' || $this->getUserId() !== $treasurerUid) {
			return new DataResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
		}

		$data = $this->request->getParams();
		$category = $data['category'] ?? '';
		if ($category === '') {
			return new DataResponse(['error' => 'Category required'], Http::STATUS_BAD_REQUEST);
		}

		$expense = $this->expenseService->updateCategory($id, $category, $this->getUserId());
		if ($expense === null) {
			return new DataResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
		}

		return new DataResponse($expense->toArray());
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function downloadReceipt(int $id, int $receiptId): DataDownloadResponse|DataResponse {
		$receipt = $this->receiptService->findById($receiptId);
		if ($receipt === null || $receipt->getExpenseId() !== $id) {
			return new DataResponse(['error' => 'Receipt not found'], Http::STATUS_NOT_FOUND);
		}

		$content = $this->receiptService->getContent($receipt);
		if ($content === null) {
			return new DataResponse(['error' => 'File not found'], Http::STATUS_NOT_FOUND);
		}

		return new DataDownloadResponse($content, $receipt->getFileName(), $receipt->getMimeType());
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function previewReceipt(int $id, int $receiptId): DataDisplayResponse|DataResponse {
		$receipt = $this->receiptService->findById($receiptId);
		if ($receipt === null || $receipt->getExpenseId() !== $id) {
			return new DataResponse(['error' => 'Receipt not found'], Http::STATUS_NOT_FOUND);
		}

		$content = $this->receiptService->getContent($receipt);
		if ($content === null) {
			return new DataResponse(['error' => 'File not found'], Http::STATUS_NOT_FOUND);
		}

		return new DataDisplayResponse($content, 200, ['Content-Type' => $receipt->getMimeType()]);
	}
}
