<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Dashboard;

use OCA\Spesenerfassung\Db\Expense;
use OCA\Spesenerfassung\Db\ExpenseMapper;
use OCA\Spesenerfassung\Service\SettingsService;
use OCP\Dashboard\IAPIWidget;
use OCP\Dashboard\Model\WidgetItem;
use OCP\IURLGenerator;
use OCP\IUserSession;

class SpesenWidget implements IAPIWidget {
	private ExpenseMapper $expenseMapper;
	private IUserSession $userSession;
	private IURLGenerator $urlGenerator;

	public function __construct(
		ExpenseMapper $expenseMapper,
		IUserSession $userSession,
		IURLGenerator $urlGenerator,
	) {
		$this->expenseMapper = $expenseMapper;
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
		return 'icon-files';
	}

	public function getUrl(): ?string {
		return $this->urlGenerator->linkToRoute('spesenerfassung.page.index');
	}

	public function load(): void {
	}

	public function getItems(string $userId, ?string $since = null, int $limit = 7): array {
		$items = [];
		$draftCount = 0;
		$rejectedCount = 0;
		$paidCount = 0;

		$userExpenses = $this->expenseMapper->findByUser($userId);
		foreach ($userExpenses as $e) {
			if ($e->getStatus() === Expense::STATUS_DRAFT) {
				$draftCount++;
			} elseif ($e->getStatus() === Expense::STATUS_REJECTED) {
				$rejectedCount++;
			} elseif ($e->getStatus() === Expense::STATUS_PAID) {
				$paidCount++;
			}
		}

		$actionCount = $draftCount + $rejectedCount + $paidCount;

		$presidentUid = SettingsService::getPresidentUid();
		$treasurerUid = SettingsService::getTreasurerUid();
		$threshold = SettingsService::getThreshold();
		$approvalCount = 0;

		if ($userId === $presidentUid || $userId === $treasurerUid) {
			$allSubmitted = $this->expenseMapper->findByStatus(Expense::STATUS_SUBMITTED);
			foreach ($allSubmitted as $e) {
				$amount = (float) $e->getAmount();
				if ($userId === $presidentUid && $amount > $threshold) {
					$approvalCount++;
				} elseif ($userId === $treasurerUid && $amount <= $threshold) {
					$approvalCount++;
				}
			}

			if ($userId === $treasurerUid) {
				$approved = $this->expenseMapper->findByStatus(Expense::STATUS_APPROVED);
				$approvalCount += count($approved);
			}
		}

		if ($actionCount > 0) {
			$items[] = new WidgetItem(
				(string) $actionCount,
				'Spesen zu bearbeiten',
				$this->urlGenerator->linkToRoute('spesenerfassung.page.index'),
			);
		}

		if ($approvalCount > 0) {
			$items[] = new WidgetItem(
				(string) $approvalCount,
				'Spesen zu genehmigen',
				$this->urlGenerator->linkToRoute('spesenerfassung.page.index'),
			);
		}

		return array_slice($items, 0, $limit);
	}
}
