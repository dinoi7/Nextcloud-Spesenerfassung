<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Dashboard;

use OCP\Dashboard\IAPIWidgetV2;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\Model\WidgetItem;
use OCP\Dashboard\Model\WidgetItems;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\IURLGenerator;
use OCP\IUserSession;

class SpesenWidget implements IAPIWidgetV2, IIconWidget {
	private IDBConnection $db;
	private IAppConfig $appConfig;
	private IUserSession $userSession;
	private IURLGenerator $urlGenerator;

	public function __construct(
		IDBConnection $db,
		IAppConfig $appConfig,
		IUserSession $userSession,
		IURLGenerator $urlGenerator,
	) {
		$this->db = $db;
		$this->appConfig = $appConfig;
		$this->userSession = $userSession;
		$this->urlGenerator = $urlGenerator;
	}

	public function getId(): string {
		return 'spesenerfassung_tasks';
	}

	public function getTitle(): string {
		return 'Spesenerfassung';
	}

	public function getOrder(): int {
		return 10;
	}

	public function getIconClass(): string {
		return '';
	}

	public function getIconUrl(): string {
		return $this->urlGenerator->imagePath('spesenerfassung', 'app.svg');
	}

	public function getUrl(): ?string {
		return $this->urlGenerator->linkToRoute('spesenerfassung.page.index');
	}

	public function load(): void {
	}

	public function getItemsV2(string $userId, ?string $since = null, int $limit = 7): WidgetItems {
		$items = [];

		// Count: draft + rejected + paid
		$qb = $this->db->getQueryBuilder();
		$qb->select('status')
			->from('sp_expenses')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->in('status', $qb->createNamedParameter(['draft', 'rejected', 'paid'], IQueryBuilder::PARAM_STR_ARRAY)));
		$result = $qb->executeQuery();
		$actionCount = 0;
		while ($row = $result->fetch()) {
			$actionCount++;
		}
		$result->closeCursor();

		// Approval count
		$presidentUid = $this->appConfig->getValueString('spesenerfassung', 'president_uid', '');
		$treasurerUid = $this->appConfig->getValueString('spesenerfassung', 'treasurer_uid', '');
		$threshold = (float) $this->appConfig->getValueString('spesenerfassung', 'threshold', '250');
		$approvalCount = 0;

		if ($userId === $presidentUid) {
			$qb = $this->db->getQueryBuilder();
			$qb->select($qb->func()->count('*', 'cnt'))
				->from('sp_expenses')
				->where($qb->expr()->eq('status', $qb->createNamedParameter('submitted')))
				->andWhere($qb->expr()->gt('amount', $qb->createNamedParameter($threshold)));
			$approvalCount = (int) $qb->executeQuery()->fetchOne();
		} elseif ($userId === $treasurerUid) {
			// Submitted <= threshold
			$qb = $this->db->getQueryBuilder();
			$qb->select($qb->func()->count('*', 'cnt'))
				->from('sp_expenses')
				->where($qb->expr()->eq('status', $qb->createNamedParameter('submitted')))
				->andWhere($qb->expr()->lte('amount', $qb->createNamedParameter($threshold)));
			$approvalCount = (int) $qb->executeQuery()->fetchOne();

			// Plus approved
			$qb = $this->db->getQueryBuilder();
			$qb->select($qb->func()->count('*', 'cnt'))
				->from('sp_expenses')
				->where($qb->expr()->eq('status', $qb->createNamedParameter('approved')));
			$approvalCount += (int) $qb->executeQuery()->fetchOne();
		}

		if ($actionCount > 0) {
			$items[] = new WidgetItem(
				(string) $actionCount,
				'Spesen zu bearbeiten',
				$this->urlGenerator->linkToRoute('spesenerfassung.page.index'),
				$this->urlGenerator->imagePath('spesenerfassung', 'expense.svg'),
			);
		}

		if ($approvalCount > 0) {
			$items[] = new WidgetItem(
				(string) $approvalCount,
				'Spesen zu genehmigen',
				$this->urlGenerator->linkToRoute('spesenerfassung.page.index') . '#/approvals',
				$this->urlGenerator->imagePath('spesenerfassung', 'approval.svg'),
			);
		}

		return new WidgetItems(array_slice($items, 0, $limit));
	}
}
