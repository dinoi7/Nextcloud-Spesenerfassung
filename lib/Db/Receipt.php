<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method int getExpenseId()
 * @method void setExpenseId(int $expenseId)
 * @method string getFileName()
 * @method void setFileName(string $fileName)
 * @method string getFilePath()
 * @method void setFilePath(string $filePath)
 * @method string getMimeType()
 * @method void setMimeType(string $mimeType)
 * @method int getSize()
 * @method void setSize(int $size)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 */
class Receipt extends Entity {
	protected int $expenseId = 0;
	protected string $fileName = '';
	protected string $filePath = '';
	protected string $mimeType = '';
	protected int $size = 0;
	protected string $createdAt = '';

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('expenseId', 'integer');
		$this->addType('size', 'integer');
	}

	public function toArray(): array {
		return [
			'id' => $this->getId(),
			'expenseId' => $this->getExpenseId(),
			'fileName' => $this->getFileName(),
			'mimeType' => $this->getMimeType(),
			'size' => $this->getSize(),
			'createdAt' => $this->getCreatedAt(),
		];
	}
}
