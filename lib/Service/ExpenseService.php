<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Service;

use OCA\Spesenerfassung\Db\Approval;
use OCA\Spesenerfassung\Db\ApprovalMapper;
use OCA\Spesenerfassung\Db\Expense;
use OCA\Spesenerfassung\Db\ExpenseMapper;
use DateTime;
use OCP\AppFramework\Db\DoesNotExistException;

class ExpenseService {
	private ExpenseMapper $expenseMapper;
	private ApprovalMapper $approvalMapper;
	private WorkflowService $workflowService;
	private MailService $mailService;

	public function __construct(
		ExpenseMapper $expenseMapper,
		ApprovalMapper $approvalMapper,
		WorkflowService $workflowService,
		MailService $mailService,
	) {
		$this->expenseMapper = $expenseMapper;
		$this->approvalMapper = $approvalMapper;
		$this->workflowService = $workflowService;
		$this->mailService = $mailService;
	}

	public function findById(int $id): ?Expense {
		try {
			return $this->expenseMapper->findById($id);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function findAllForUser(string $userId): array {
		return $this->expenseMapper->findByUser($userId);
	}

	public function findAll(): array {
		return $this->expenseMapper->findAll();
	}

	/**
	 * @return Approval[]
	 */
	public function getHistory(int $expenseId): array {
		return $this->approvalMapper->findByExpenseId($expenseId);
	}

	public function create(string $userId, array $data): Expense {
		$now = (new DateTime())->format('Y-m-d H:i:s');

		$expense = new Expense();
		$expense->setUserId($userId);
		$expense->setTitle($data['title']);
		$expense->setDescription($data['description'] ?? null);
		$expense->setAmount(number_format((float) $data['amount'], 2, '.', ''));
		$expense->setCategory($data['category']);
		$expense->setExpenseDate($data['expenseDate']);
		$expense->setPayoutMethod($data['payoutMethod'] ?? '');
		$expense->setForeignCurrency($data['foreignCurrency'] ?? null);
		$expense->setForeignAmount(isset($data['foreignAmount']) ? number_format((float) $data['foreignAmount'], 2, '.', '') : null);
		$expense->setStatus($data['status'] ?? Expense::STATUS_DRAFT);
		$expense->setCreatedAt($now);
		$expense->setUpdatedAt($now);

		$expense = $this->expenseMapper->insert($expense);

		if ($expense->getStatus() === Expense::STATUS_SUBMITTED) {
			$this->logAction($expense->getId(), $userId, Approval::ACTION_SUBMITTED);
			$this->mailService->notifySubmitterSubmitted($expense);
			$this->notifyNextStep($expense);
		}

		return $expense;
	}

	public function update(int $id, string $userId, array $data): ?Expense {
		$expense = $this->findById($id);
		if ($expense === null || $expense->getUserId() !== $userId) {
			return null;
		}
		if (!in_array($expense->getStatus(), [Expense::STATUS_DRAFT, Expense::STATUS_REJECTED])) {
			return null;
		}

		$now = (new DateTime())->format('Y-m-d H:i:s');

		if (isset($data['title'])) {
			$expense->setTitle($data['title']);
		}
		if (array_key_exists('description', $data)) {
			$expense->setDescription($data['description']);
		}
		if (isset($data['amount'])) {
			$expense->setAmount(number_format((float) $data['amount'], 2, '.', ''));
		}
		if (isset($data['category'])) {
			$expense->setCategory($data['category']);
		}
		if (isset($data['expenseDate'])) {
			$expense->setExpenseDate($data['expenseDate']);
		}
		if (array_key_exists('payoutMethod', $data)) {
			$expense->setPayoutMethod($data['payoutMethod']);
		}
		if (array_key_exists('foreignCurrency', $data)) {
			$expense->setForeignCurrency($data['foreignCurrency']);
		}
		if (array_key_exists('foreignAmount', $data)) {
			$expense->setForeignAmount($data['foreignAmount'] !== null ? number_format((float) $data['foreignAmount'], 2, '.', '') : null);
		}
		if (isset($data['status'])) {
			$newStatus = $data['status'];
			if ($newStatus === Expense::STATUS_SUBMITTED) {
				if (!$this->workflowService->canTransition($expense->getStatus(), Expense::STATUS_SUBMITTED, $userId)) {
					return null;
				}
				$expense->setStatus(Expense::STATUS_SUBMITTED);
				$this->logAction($expense->getId(), $userId, Approval::ACTION_SUBMITTED);
				$this->mailService->notifySubmitterSubmitted($expense);
				$this->notifyNextStep($expense);
			} elseif ($newStatus === Expense::STATUS_DRAFT) {
				$expense->setStatus(Expense::STATUS_DRAFT);
			}
		}
		$expense->setUpdatedAt($now);

		return $this->expenseMapper->update($expense);
	}

	public function delete(int $id, string $userId): bool {
		$expense = $this->findById($id);
		if ($expense === null || $expense->getUserId() !== $userId) {
			return false;
		}
		if (!in_array($expense->getStatus(), [Expense::STATUS_DRAFT, Expense::STATUS_REJECTED])) {
			return false;
		}
		$this->expenseMapper->delete($expense);
		return true;
	}

	public function submit(int $id, string $userId): ?Expense {
		return $this->transition($id, $userId, Expense::STATUS_SUBMITTED, Approval::ACTION_SUBMITTED);
	}

	public function approve(int $id, string $userId): ?Expense {
		return $this->transition($id, $userId, Expense::STATUS_APPROVED, Approval::ACTION_APPROVED);
	}

	public function reject(int $id, string $userId, string $reason): ?Expense {
		return $this->transition($id, $userId, Expense::STATUS_REJECTED, Approval::ACTION_REJECTED, $reason);
	}

	public function pay(int $id, string $userId): ?Expense {
		return $this->transition($id, $userId, Expense::STATUS_PAID, Approval::ACTION_PAID);
	}

	public function done(int $id, string $userId): ?Expense {
		return $this->transition($id, $userId, Expense::STATUS_DONE, Approval::ACTION_DONE);
	}

	public function updateCategory(int $id, string $category, string $actorId): ?Expense {
		$expense = $this->findById($id);
		if ($expense === null) {
			return null;
		}

		$oldCategory = $expense->getCategory();
		$expense->setCategory($category);
		$this->expenseMapper->update($expense);

		$this->logAction($id, $actorId, 'category_changed', $oldCategory . ' → ' . $category);

		return $expense;
	}

	public function getPendingForPresident(): array {
		$submitted = $this->expenseMapper->findByStatus(Expense::STATUS_SUBMITTED);
		$threshold = SettingsService::getThreshold();
		return array_filter($submitted, fn(Expense $e) => (float) $e->getAmount() > $threshold);
	}

	public function getPendingForTreasurer(): array {
		$threshold = SettingsService::getThreshold();
		$fromSubmitted = $this->expenseMapper->findByStatusAndMaxAmount(Expense::STATUS_SUBMITTED, number_format($threshold, 2, '.', ''));
		$fromApproved = $this->expenseMapper->findByStatus(Expense::STATUS_APPROVED);
		return array_merge($fromSubmitted, $fromApproved);
	}

	private function transition(int $id, string $userId, string $targetStatus, string $action, ?string $comment = null): ?Expense {
		$expense = $this->findById($id);
		if ($expense === null) {
			return null;
		}

		if (!$this->workflowService->canTransition($expense->getStatus(), $targetStatus, $userId)) {
			return null;
		}

		if ($targetStatus === Expense::STATUS_REJECTED && empty($comment)) {
			return null;
		}

		$now = (new DateTime())->format('Y-m-d H:i:s');
		$expense->setStatus($targetStatus);
		$expense->setUpdatedAt($now);

		$expense = $this->expenseMapper->update($expense);
		$this->logAction($expense->getId(), $userId, $action, $comment);
		$this->sendNotificationMail($expense, $action);

		return $expense;
	}

	private function logAction(int $expenseId, string $userId, string $action, ?string $comment = null): void {
		$now = (new DateTime())->format('Y-m-d H:i:s');
		$approval = new Approval();
		$approval->setExpenseId($expenseId);
		$approval->setUserId($userId);
		$approval->setAction($action);
		$approval->setComment($comment);
		$approval->setCreatedAt($now);
		$this->approvalMapper->insert($approval);
	}

	private function sendNotificationMail(Expense $expense, string $action): void {
		$presidentUid = SettingsService::getPresidentUid();
		$treasurerUid = SettingsService::getTreasurerUid();
		$threshold = SettingsService::getThreshold();

		$recipientUid = match ($action) {
			Approval::ACTION_SUBMITTED => ((float) $expense->getAmount() > $threshold) ? $presidentUid : $treasurerUid,
			Approval::ACTION_APPROVED => $treasurerUid,
			Approval::ACTION_REJECTED => $expense->getUserId(),
			Approval::ACTION_PAID => $expense->getUserId(),
			default => null,
		};

		if ($recipientUid === null) {
			return;
		}

		$this->mailService->sendStatusNotification($expense, $action, $recipientUid);
	}

	private function notifyNextStep(Expense $expense): void {
		$threshold = SettingsService::getThreshold();
		$this->sendNotificationMail($expense, Approval::ACTION_SUBMITTED);
	}
}
