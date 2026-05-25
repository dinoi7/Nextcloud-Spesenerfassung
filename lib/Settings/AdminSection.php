<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Settings;

use OCP\Settings\IIconSection;

class AdminSection implements IIconSection {
	public function getID(): string {
		return 'spesenerfassung';
	}

	public function getName(): string {
		return 'Spesenerfassung';
	}

	public function getPriority(): int {
		return 50;
	}

	public function getIcon(): string {
		return \OC::$server->getURLGenerator()->imagePath('spesenerfassung', 'app.svg');
	}
}
