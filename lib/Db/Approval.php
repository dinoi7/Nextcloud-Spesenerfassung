<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method int getExpenseId()
 * @method void setExpenseId(int $expenseId)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getAction()
 * @method void setAction(string $action)
 * @method string|null getComment()
 * @method void setComment(?string $comment)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 */
class Approval extends Entity {
	public const ACTION_SUBMITTED = 'submitted';
	public const ACTION_APPROVED = 'approved';
	public const ACTION_REJECTED = 'rejected';
	public const ACTION_PAID = 'paid';
	public const ACTION_PAYSTACK = 'paystack';
	public const ACTION_DONE = 'done';

	protected int $expenseId = 0;
	protected string $userId = '';
	protected string $action = '';
	protected ?string $comment = null;
	protected string $createdAt = '';

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('expenseId', 'integer');
	}

	public function toArray(): array {
		return [
			'id' => $this->getId(),
			'expenseId' => $this->getExpenseId(),
			'userId' => $this->getUserId(),
			'action' => $this->getAction(),
			'comment' => $this->getComment(),
			'createdAt' => $this->getCreatedAt(),
		];
	}
}
