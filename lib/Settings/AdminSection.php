<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Settings;

use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class AdminSection implements IIconSection {
	private IURLGenerator $urlGenerator;

	public function __construct(IURLGenerator $urlGenerator) {
		$this->urlGenerator = $urlGenerator;
	}

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
		return $this->urlGenerator->imagePath('spesenerfassung', 'app.svg');
	}
}
