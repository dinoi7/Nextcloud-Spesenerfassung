<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000003Date20260529 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if ($schema->hasTable('sp_expenses')) {
			$table = $schema->getTable('sp_expenses');
			if (!$table->hasColumn('foreign_currency')) {
				$table->addColumn('foreign_currency', Types::STRING, [
					'notnull' => false,
					'length' => 32,
				]);
			}
			if (!$table->hasColumn('foreign_amount')) {
				$table->addColumn('foreign_amount', Types::DECIMAL, [
					'notnull' => false,
					'precision' => 10,
					'scale' => 2,
				]);
			}
		}

		return $schema;
	}
}
