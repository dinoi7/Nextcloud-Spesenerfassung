<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Service;

use OCA\Spesenerfassung\Db\Expense;

class WorkflowService {

	private const STATE_DRAFT = Expense::STATUS_DRAFT;
	private const STATE_SUBMITTED = Expense::STATUS_SUBMITTED;
	private const STATE_APPROVED = Expense::STATUS_APPROVED;
	private const STATE_REJECTED = Expense::STATUS_REJECTED;
	private const STATE_PAID = Expense::STATUS_PAID;
	private const STATE_DONE = Expense::STATUS_DONE;

	/**
	 * Allowed transitions: from => [to]
	 */
	private const TRANSITIONS = [
		self::STATE_DRAFT => [
			self::STATE_SUBMITTED,
		],
		self::STATE_SUBMITTED => [
			self::STATE_APPROVED,
			self::STATE_REJECTED,
			self::STATE_PAID,
		],
		self::STATE_APPROVED => [
			self::STATE_PAID,
			self::STATE_REJECTED,
		],
		self::STATE_REJECTED => [
			self::STATE_DRAFT,
		],
		self::STATE_PAID => [
			self::STATE_DONE,
		],
		self::STATE_DONE => [],
	];

	public function canTransition(string $from, string $to, ?string $userId = null): bool {
		$allowedTargets = self::TRANSITIONS[$from] ?? [];
		return in_array($to, $allowedTargets, true);
	}

	public function isEditable(string $status): bool {
		return in_array($status, [self::STATE_DRAFT, self::STATE_REJECTED], true);
	}

	public function isDeletable(string $status): bool {
		return in_array($status, [self::STATE_DRAFT, self::STATE_REJECTED], true);
	}
}
