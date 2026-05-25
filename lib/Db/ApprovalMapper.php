<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ApprovalMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'spesenerfassung_approvals', Approval::class);
	}

	/**
	 * @return Approval[]
	 */
	public function findByExpenseId(int $expenseId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('expense_id', $qb->createNamedParameter($expenseId, IQueryBuilder::PARAM_INT)))
			->orderBy('created_at', 'ASC');
		return $this->findEntities($qb);
	}
}
