<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Controller;

use OCA\Spesenerfassung\Service\ExpenseService;
use OCA\Spesenerfassung\Service\ReceiptService;
use OCA\Spesenerfassung\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;

class ApprovalController extends Controller {
	private ExpenseService $expenseService;
	private IUserSession $userSession;
	private IUserManager $userManager;
	private ReceiptService $receiptService;

	public function __construct(
		string $appName,
		IRequest $request,
		ExpenseService $expenseService,
		IUserSession $userSession,
		IUserManager $userManager,
		ReceiptService $receiptService,
	) {
		parent::__construct($appName, $request);
		$this->expenseService = $expenseService;
		$this->userSession = $userSession;
		$this->userManager = $userManager;
		$this->receiptService = $receiptService;
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

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function submit(int $id): DataResponse {
		$userId = $this->getUserId();
		$expense = $this->expenseService->submit($id, $userId);
		if ($expense === null) {
			return new DataResponse(['error' => 'Cannot submit'], Http::STATUS_FORBIDDEN);
		}
		return new DataResponse($expense->toArray());
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
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

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
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

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function pay(int $id): DataResponse {
		if (!$this->checkRole('treasurer')) {
			return new DataResponse(['error' => 'Only treasurer can pay'], Http::STATUS_FORBIDDEN);
		}
		$expense = $this->expenseService->pay($id, $this->getUserId());
		if ($expense === null) {
			return new DataResponse(['error' => 'Cannot pay'], Http::STATUS_FORBIDDEN);
		}
		return new DataResponse($expense->toArray());
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function done(int $id): DataResponse {
		$userId = $this->getUserId();
		$expense = $this->expenseService->done($id, $userId);
		if ($expense === null) {
			return new DataResponse(['error' => 'Cannot set done'], Http::STATUS_FORBIDDEN);
		}
		return new DataResponse($expense->toArray());
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
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
		return new DataResponse($result);
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
			$row['receiptCount'] = count($this->receiptService->findByExpenseId($e->getId()));
			return $row;
		}, $expenses);
		return new DataResponse($result);
	}
}
