<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Service;

use OCP\IAppConfig;

class SettingsService {
	private const KEY_PRESIDENT_UID = 'president_uid';
	private const KEY_TREASURER_UID = 'treasurer_uid';
	private const KEY_THRESHOLD = 'threshold';
	private const KEY_CATEGORIES = 'categories';
	private const KEY_DEFAULT_PAYOUT_METHOD = 'default_payout_method';
	private const KEY_EXPORT_ACCOUNTS = 'export_accounts';
	private const KEY_BOOKING_FOLDER = 'booking_folder';

	private const DEFAULTS = [
		self::KEY_PRESIDENT_UID => '',
		self::KEY_TREASURER_UID => '',
		self::KEY_THRESHOLD => '250',
		self::KEY_CATEGORIES => '["Material","Verpflegung","Reise","Büro","Sonstiges"]',
		self::KEY_DEFAULT_PAYOUT_METHOD => 'bank',
		self::KEY_EXPORT_ACCOUNTS => '{}',
		self::KEY_BOOKING_FOLDER => 'Buchungsbelege',
	];

	public function __construct(
		private IAppConfig $appConfig
	) {
	}

	private function getString(string $key): string {
		$value = $this->appConfig->getValueString('spesenerfassung', $key);
		return $value !== '' ? $value : (self::DEFAULTS[$key] ?? '');
	}

	public function getPresidentUid(): string {
		return $this->getString(self::KEY_PRESIDENT_UID);
	}

	public function setPresidentUid(string $uid): void {
		$this->appConfig->setValueString('spesenerfassung', self::KEY_PRESIDENT_UID, $uid);
	}

	public function getTreasurerUid(): string {
		return $this->getString(self::KEY_TREASURER_UID);
	}

	public function setTreasurerUid(string $uid): void {
		$this->appConfig->setValueString('spesenerfassung', self::KEY_TREASURER_UID, $uid);
	}

	public function getThreshold(): float {
		$value = $this->getString(self::KEY_THRESHOLD);
		return is_numeric($value) ? (float) $value : 250.0;
	}

	public function setThreshold(float $threshold): void {
		$this->appConfig->setValueString('spesenerfassung', self::KEY_THRESHOLD, (string) $threshold);
	}

	/**
	 * @return string[]
	 */
	public function getCategories(): array {
		$raw = $this->getString(self::KEY_CATEGORIES);
		$categories = json_decode($raw, true);
		return is_array($categories) ? $categories : json_decode(self::DEFAULTS[self::KEY_CATEGORIES], true);
	}

	/**
	 * @param string[] $categories
	 */
	public function setCategories(array $categories): void {
		$this->appConfig->setValueString('spesenerfassung', self::KEY_CATEGORIES, json_encode($categories));
	}

	public function addCategory(string $category): array {
		$categories = $this->getCategories();
		$categories[] = $category;
		$this->setCategories($categories);
		return $categories;
	}

	public function updateCategory(int $index, string $name): array {
		$categories = $this->getCategories();
		if (isset($categories[$index])) {
			$categories[$index] = $name;
			$this->setCategories($categories);
		}
		return $categories;
	}

	public function deleteCategory(int $index): array {
		$categories = $this->getCategories();
		if (isset($categories[$index])) {
			array_splice($categories, $index, 1);
			$this->setCategories($categories);
		}
		return $categories;
	}

	public function getDefaultPayoutMethod(): string {
		return $this->getString(self::KEY_DEFAULT_PAYOUT_METHOD);
	}

	public function setDefaultPayoutMethod(string $method): void {
		$this->appConfig->setValueString('spesenerfassung', self::KEY_DEFAULT_PAYOUT_METHOD, $method);
	}

	public function getExportAccounts(): array {
		$raw = $this->getString(self::KEY_EXPORT_ACCOUNTS);
		$accounts = json_decode($raw, true);
		return is_array($accounts) ? $accounts : [];
	}

	public function setExportAccounts(array $accounts): void {
		$this->appConfig->setValueString('spesenerfassung', self::KEY_EXPORT_ACCOUNTS, json_encode($accounts));
	}

	public function getBookingFolder(): string {
		return $this->getString(self::KEY_BOOKING_FOLDER);
	}

	public function setBookingFolder(string $folder): void {
		$this->appConfig->setValueString('spesenerfassung', self::KEY_BOOKING_FOLDER, $folder);
	}

	public function getAll(): array {
		return [
			'presidentUid' => $this->getPresidentUid(),
			'treasurerUid' => $this->getTreasurerUid(),
			'threshold' => $this->getThreshold(),
			'categories' => $this->getCategories(),
			'defaultPayoutMethod' => $this->getDefaultPayoutMethod(),
			'exportAccounts' => $this->getExportAccounts(),
			'bookingFolder' => $this->getBookingFolder(),
		];
	}

	public function updateAll(array $data): array {
		if (isset($data['presidentUid'])) {
			$this->setPresidentUid($data['presidentUid']);
		}
		if (isset($data['treasurerUid'])) {
			$this->setTreasurerUid($data['treasurerUid']);
		}
		if (isset($data['threshold'])) {
			$this->setThreshold((float) $data['threshold']);
		}
		if (isset($data['categories']) && is_array($data['categories'])) {
			$this->setCategories($data['categories']);
		}
		if (isset($data['defaultPayoutMethod'])) {
			$this->setDefaultPayoutMethod($data['defaultPayoutMethod']);
		}
		if (isset($data['exportAccounts']) && is_array($data['exportAccounts'])) {
			$this->setExportAccounts($data['exportAccounts']);
		}
		if (isset($data['bookingFolder'])) {
			$this->setBookingFolder(trim($data['bookingFolder']));
		}
		return $this->getAll();
	}
}
