<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method string|null getDescription()
 * @method void setDescription(?string $description)
 * @method string getAmount()
 * @method void setAmount(string $amount)
 * @method string getCategory()
 * @method void setCategory(string $category)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string getExpenseDate()
 * @method void setExpenseDate(string $expenseDate)
 * @method string|null getPayoutMethod()
 * @method void setPayoutMethod(?string $payoutMethod)
 * @method string|null getForeignCurrency()
 * @method void setForeignCurrency(?string $foreignCurrency)
 * @method string|null getForeignAmount()
 * @method void setForeignAmount(?string $foreignAmount)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 * @method string getUpdatedAt()
 * @method void setUpdatedAt(string $updatedAt)
 */
class Expense extends Entity {
	public const STATUS_DRAFT = 'draft';
	public const STATUS_SUBMITTED = 'submitted';
	public const STATUS_APPROVED = 'approved';
	public const STATUS_BOOKKEEPING = 'bookkeeping';
	public const STATUS_REJECTED = 'rejected';
	public const STATUS_PAID = 'paid';
	public const STATUS_PAYSTACK = 'paystack';
	public const STATUS_DONE = 'done';

	public const STATUSES = [
		self::STATUS_DRAFT,
		self::STATUS_SUBMITTED,
		self::STATUS_APPROVED,
		self::STATUS_BOOKKEEPING,
		self::STATUS_REJECTED,
		self::STATUS_PAID,
		self::STATUS_PAYSTACK,
		self::STATUS_DONE,
	];

	protected string $userId = '';
	protected string $title = '';
	protected ?string $description = null;
	protected string $amount = '0.00';
	protected string $category = '';
	protected string $status = self::STATUS_DRAFT;
	protected string $expenseDate = '';
	protected ?string $payoutMethod = null;
	protected ?string $foreignCurrency = null;
	protected ?string $foreignAmount = null;
	protected string $createdAt = '';
	protected string $updatedAt = '';

	public function __construct() {
		$this->addType('id', 'integer');
	}

	public function toArray(): array {
		return [
			'id' => $this->getId(),
			'userId' => $this->getUserId(),
			'title' => $this->getTitle(),
			'description' => $this->getDescription(),
			'amount' => (float) $this->getAmount(),
			'category' => $this->getCategory(),
			'status' => $this->getStatus(),
			'expenseDate' => $this->getExpenseDate(),
			'payoutMethod' => $this->getPayoutMethod(),
			'foreignCurrency' => $this->getForeignCurrency(),
			'foreignAmount' => $this->getForeignAmount() !== null ? (float) $this->getForeignAmount() : null,
			'createdAt' => $this->getCreatedAt(),
			'updatedAt' => $this->getUpdatedAt(),
		];
	}
}
