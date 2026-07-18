<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Service;

use OCP\IAppConfig;

class SettingsService {
	private static ?IAppConfig $appConfig = null;

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

	public static function setConfig(IAppConfig $config): void {
		self::$appConfig = $config;
	}

	private static function getConfig(): IAppConfig {
		if (self::$appConfig === null) {
			throw new \RuntimeException('IAppConfig not set. Call SettingsService::setConfig() first.');
		}
		return self::$appConfig;
	}

	private static function getString(string $key): string {
		$value = self::getConfig()->getValueString('spesenerfassung', $key);
		return $value !== '' ? $value : (self::DEFAULTS[$key] ?? '');
	}

	public static function getPresidentUid(): string {
		$cfg = self::getString(self::KEY_PRESIDENT_UID);
		return $cfg;
	}

	public static function setPresidentUid(string $uid): void {
		self::getConfig()->setValueString('spesenerfassung', self::KEY_PRESIDENT_UID, $uid);
	}

	public static function getTreasurerUid(): string {
		return self::getString(self::KEY_TREASURER_UID);
	}

	public static function setTreasurerUid(string $uid): void {
		self::getConfig()->setValueString('spesenerfassung', self::KEY_TREASURER_UID, $uid);
	}

	public static function getThreshold(): float {
		$value = self::getString(self::KEY_THRESHOLD);
		return is_numeric($value) ? (float) $value : 250.0;
	}

	public static function setThreshold(float $threshold): void {
		self::getConfig()->setValueString('spesenerfassung', self::KEY_THRESHOLD, (string) $threshold);
	}

	/**
	 * @return string[]
	 */
	public static function getCategories(): array {
		$raw = self::getString(self::KEY_CATEGORIES);
		$categories = json_decode($raw, true);
		return is_array($categories) ? $categories : json_decode(self::DEFAULTS[self::KEY_CATEGORIES], true);
	}

	/**
	 * @param string[] $categories
	 */
	public static function setCategories(array $categories): void {
		self::getConfig()->setValueString('spesenerfassung', self::KEY_CATEGORIES, json_encode($categories));
	}

	public static function addCategory(string $category): array {
		$categories = self::getCategories();
		$categories[] = $category;
		self::setCategories($categories);
		return $categories;
	}

	public static function updateCategory(int $index, string $name): array {
		$categories = self::getCategories();
		if (isset($categories[$index])) {
			$categories[$index] = $name;
			self::setCategories($categories);
		}
		return $categories;
	}

	public static function deleteCategory(int $index): array {
		$categories = self::getCategories();
		if (isset($categories[$index])) {
			array_splice($categories, $index, 1);
			self::setCategories($categories);
		}
		return $categories;
	}

	public static function getDefaultPayoutMethod(): string {
		return self::getString(self::KEY_DEFAULT_PAYOUT_METHOD);
	}

	public static function setDefaultPayoutMethod(string $method): void {
		self::getConfig()->setValueString('spesenerfassung', self::KEY_DEFAULT_PAYOUT_METHOD, $method);
	}

	public static function getExportAccounts(): array {
		$raw = self::getString(self::KEY_EXPORT_ACCOUNTS);
		$accounts = json_decode($raw, true);
		return is_array($accounts) ? $accounts : [];
	}

	public static function setExportAccounts(array $accounts): void {
		self::getConfig()->setValueString('spesenerfassung', self::KEY_EXPORT_ACCOUNTS, json_encode($accounts));
	}

	public static function getBookingFolder(): string {
		return self::getString(self::KEY_BOOKING_FOLDER);
	}

	public static function setBookingFolder(string $folder): void {
		self::getConfig()->setValueString('spesenerfassung', self::KEY_BOOKING_FOLDER, $folder);
	}

	public static function getAll(): array {
		return [
			'presidentUid' => self::getPresidentUid(),
			'treasurerUid' => self::getTreasurerUid(),
			'threshold' => self::getThreshold(),
			'categories' => self::getCategories(),
			'defaultPayoutMethod' => self::getDefaultPayoutMethod(),
			'exportAccounts' => self::getExportAccounts(),
			'bookingFolder' => self::getBookingFolder(),
		];
	}

	public static function updateAll(array $data): array {
		if (isset($data['presidentUid'])) {
			self::setPresidentUid($data['presidentUid']);
		}
		if (isset($data['treasurerUid'])) {
			self::setTreasurerUid($data['treasurerUid']);
		}
		if (isset($data['threshold'])) {
			self::setThreshold((float) $data['threshold']);
		}
		if (isset($data['categories']) && is_array($data['categories'])) {
			self::setCategories($data['categories']);
		}
		if (isset($data['defaultPayoutMethod'])) {
			self::setDefaultPayoutMethod($data['defaultPayoutMethod']);
		}
		if (isset($data['exportAccounts']) && is_array($data['exportAccounts'])) {
			self::setExportAccounts($data['exportAccounts']);
		}
		if (isset($data['bookingFolder'])) {
			self::setBookingFolder(trim($data['bookingFolder']));
		}
		return self::getAll();
	}
}
