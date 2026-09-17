<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Command;

use OCA\Spesenerfassung\Service\BookingReceiptService;
use OCA\Spesenerfassung\Service\ExpenseService;
use OCA\Spesenerfassung\Service\SettingsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RegenerateBookingReceipt extends Command {
	public function __construct(
		private ExpenseService $expenseService,
		private BookingReceiptService $bookingReceiptService,
		private SettingsService $settingsService,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this->setName('spesenerfassung:regenerate-booking-receipt')
			->setDescription('Regenerates the Spesenbeleg PDF for one or more expense ids')
			->addArgument('ids', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'Expense id(s)');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$treasurerUid = $this->settingsService->getTreasurerUid();
		if ($treasurerUid === '') {
			$output->writeln('<error>Kein Kassier (treasurer_uid) konfiguriert.</error>');
			return self::FAILURE;
		}

		$exitCode = self::SUCCESS;
		foreach ($input->getArgument('ids') as $id) {
			$expense = $this->expenseService->findById((int) $id);
			if ($expense === null) {
				$output->writeln('<error>Spese ' . $id . ' nicht gefunden.</error>');
				$exitCode = self::FAILURE;
				continue;
			}

			$result = $this->bookingReceiptService->generate($expense, $treasurerUid);
			if ($result['success']) {
				$output->writeln('<info>OK: ' . $result['message'] . '</info>');
			} else {
				$output->writeln('<error>Fehler: ' . $result['message'] . '</error>');
				$exitCode = self::FAILURE;
			}
		}

		return $exitCode;
	}
}
