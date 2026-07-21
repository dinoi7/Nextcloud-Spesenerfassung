<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Service;

use OCA\Spesenerfassung\Db\Approval;
use OCA\Spesenerfassung\Db\ApprovalMapper;
use OCA\Spesenerfassung\Db\Expense;
use OCP\Files\IRootFolder;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;
use setasign\Fpdi\Tcpdf\Fpdi;

class BookingReceiptService {
	private const ACTION_LABELS = [
		Approval::ACTION_SUBMITTED => 'Eingereicht',
		Approval::ACTION_APPROVED => 'Genehmigt',
		Approval::ACTION_BOOKKEEPING => 'Buchhaltung',
		Approval::ACTION_REJECTED => 'Abgelehnt',
		Approval::ACTION_PAID => 'Ausbezahlt',
		Approval::ACTION_PAYSTACK => 'Zahlstapel',
		Approval::ACTION_DONE => 'Erledigt',
	];

	public function __construct(
		private IUserManager $userManager,
		private ReceiptService $receiptService,
		private UserSettingsService $userSettingsService,
		private IRootFolder $rootFolder,
		private ApprovalMapper $approvalMapper,
		private LoggerInterface $logger,
		private SettingsService $settingsService,
	) {
	}

	public function generate(Expense $expense, string $treasurerUserId): array {
		$receipts = $this->receiptService->findByExpenseId($expense->getId());
		$user = $this->userManager->get($expense->getUserId());
		$submitterName = $user ? $user->getDisplayName() : $expense->getUserId();
		$iban = $this->userSettingsService->getIban($expense->getUserId());
		$plz = $this->userSettingsService->getPlz($expense->getUserId());

		$pdf = new Fpdi();
		$pdf->SetPrintHeader(false);
		$pdf->SetPrintFooter(false);
		$pdf->SetCreator('Spesenerfassung');
		$pdf->SetAuthor('Makerspace Reinach');
		$pdf->SetTitle('Spesenbeleg ' . $expense->getId());
		$pdf->SetMargins(15, 15, 15);
		$pdf->SetAutoPageBreak(true, 20);

		$pdf->AddPage();
		$logoFile = __DIR__ . '/../../img/logo.png';
		$pdf->Image($logoFile, 15, 12, 40);
		$pdf->SetFont('helvetica', 'B', 20);
		$pdf->Cell(0, 14, 'Spesenbeleg Nr. ' . $expense->getId(), 0, 1, 'C');
		$pdf->SetFont('helvetica', '', 10);
		$pdf->Cell(0, 6, 'Erstellt: ' . date('d.m.Y H:i') . ' — Makerspace Reinach', 0, 1, 'C');
		$pdf->Ln(10);

		$labelW = 42;
		$rowH = 7;
		$pdf->SetFont('helvetica', 'B', 10);

		$pdf->Cell($labelW, $rowH, 'Spesennummer:', 0, 0);
		$pdf->SetFont('helvetica', '', 10);
		$pdf->Cell(0, $rowH, (string) $expense->getId(), 0, 1);

		$pdf->SetFont('helvetica', 'B', 10);
		$pdf->Cell($labelW, $rowH, 'Datum:', 0, 0);
		$pdf->SetFont('helvetica', '', 10);
		$pdf->Cell(0, $rowH, date('d.m.Y', strtotime($expense->getExpenseDate())), 0, 1);

		$pdf->SetFont('helvetica', 'B', 10);
		$pdf->Cell($labelW, $rowH, 'Erfasser:', 0, 0);
		$pdf->SetFont('helvetica', '', 10);
		$pdf->Cell(0, $rowH, $submitterName . ' (' . $expense->getUserId() . ')', 0, 1);

		$pdf->SetFont('helvetica', 'B', 10);
		$pdf->Cell($labelW, $rowH, 'Status:', 0, 0);
		$pdf->SetFont('helvetica', '', 10);
		$pdf->Cell(0, $rowH, 'Ausbezahlt', 0, 1);

		if ($expense->getPayoutMethod() === 'bank' && $iban) {
			$pdf->SetFont('helvetica', 'B', 10);
			$pdf->Cell($labelW, $rowH, 'IBAN:', 0, 0);
			$pdf->SetFont('helvetica', '', 10);
			$pdf->Cell(0, $rowH, $iban, 0, 1);
			if ($plz) {
				$pdf->SetFont('helvetica', 'B', 10);
				$pdf->Cell($labelW, $rowH, 'PLZ:', 0, 0);
				$pdf->SetFont('helvetica', '', 10);
				$pdf->Cell(0, $rowH, $plz, 0, 1);
			}
		}

		$pdf->SetFont('helvetica', 'B', 10);
		$pdf->Cell($labelW, $rowH, 'Titel:', 0, 0);
		$pdf->SetFont('helvetica', '', 10);
		$pdf->Cell(0, $rowH, $expense->getTitle(), 0, 1);

		if ($expense->getDescription()) {
			$y = $pdf->GetY();
			$pdf->SetFont('helvetica', 'B', 10);
			$pdf->Cell($labelW, $rowH, 'Beschreibung:', 0, 0);
			$pdf->SetFont('helvetica', '', 10);
			$pdf->SetXY($pdf->GetX(), $y);
			$pdf->MultiCell(0, $rowH, $expense->getDescription(), 0, 'L');
		}

		$pdf->SetFont('helvetica', 'B', 10);
		$pdf->Cell($labelW, $rowH, 'Betrag:', 0, 0);
		$pdf->SetFont('helvetica', '', 10);
		$pdf->Cell(0, $rowH, 'CHF ' . number_format((float) $expense->getAmount(), 2, '.', '\''), 0, 1);

		if ($expense->getForeignCurrency()) {
			$pdf->SetFont('helvetica', 'B', 10);
			$pdf->Cell($labelW, $rowH, 'Fremdwaehrung:', 0, 0);
			$pdf->SetFont('helvetica', '', 10);
			$fa = $expense->getForeignAmount() !== null ? number_format((float) $expense->getForeignAmount(), 2, '.', '\'') : '-';
			$pdf->Cell(0, $rowH, $expense->getForeignCurrency() . ' ' . $fa, 0, 1);
		}

		$pdf->SetFont('helvetica', 'B', 10);
		$pdf->Cell($labelW, $rowH, 'Kategorie:', 0, 0);
		$pdf->SetFont('helvetica', '', 10);
		$pdf->Cell(0, $rowH, $expense->getCategory(), 0, 1);

		$payoutLabel = $expense->getPayoutMethod() === 'bank' ? 'Bank' : 'Bar';
		$pdf->SetFont('helvetica', 'B', 10);
		$pdf->Cell($labelW, $rowH, 'Auszahlung:', 0, 0);
		$pdf->SetFont('helvetica', '', 10);
		$pdf->Cell(0, $rowH, $payoutLabel, 0, 1);

		$history = $this->approvalMapper->findByExpenseId($expense->getId());
		if (count($history) > 0) {
			$pdf->Ln(3);
			$pdf->SetFont('helvetica', 'B', 12);
			$pdf->Cell(0, 8, 'Verlauf', 0, 1, 'L');
			$pdf->Ln(1);
			$pdf->SetFont('helvetica', 'B', 8);
			$pdf->Cell(32, 5, 'Datum', 0, 0, 'C');
			$pdf->Cell(28, 5, 'Aktion', 0, 0, 'C');
			$pdf->Cell(40, 5, 'Benutzer', 0, 0, 'C');
			$pdf->Cell(0, 5, 'Bemerkung', 0, 1, 'C');
			$pdf->SetFont('helvetica', '', 8);
			foreach ($history as $entry) {
				$entryUser = $this->userManager->get($entry->getUserId());
				$entryName = $entryUser ? $entryUser->getDisplayName() : $entry->getUserId();
				$label = self::ACTION_LABELS[$entry->getAction()] ?? $entry->getAction();
				$comment = $entry->getComment() ?? '-';
				$date = date('d.m.Y H:i', strtotime($entry->getCreatedAt()));
				$pdf->Cell(32, 5, $date, 0, 0, 'C');
				$pdf->Cell(28, 5, $label, 0, 0, 'C');
				$pdf->Cell(40, 5, $entryName, 0, 0, 'C');
				$pdf->Cell(0, 5, $comment, 0, 1, 'L');
			}
		}

		if (count($receipts) > 0) {
			$pdf->SetFont('helvetica', 'B', 12);
			$pdf->Cell(0, 8, 'Anhänge (' . count($receipts) . ')', 0, 1, 'L');
			$pdf->Ln(2);

			$pdf->SetFont('helvetica', 'B', 9);
			$pdf->SetFillColor(240, 240, 240);
			$pdf->Cell(80, 6, 'Dateiname', 0, 0, 'L', true);
			$pdf->Cell(25, 6, 'Seiten', 0, 0, 'R', true);
			$pdf->Cell(25, 6, 'Grösse', 0, 1, 'R', true);

			$pdf->SetFont('helvetica', '', 9);
			foreach ($receipts as $receipt) {
				$pages = '-';
				if ($receipt->getMimeType() === 'application/pdf') {
					$pc = $this->receiptService->getPageCount($receipt);
					$pages = $pc !== null ? (string) $pc : '-';
				}
				$kb = round($receipt->getSize() / 1024, 1) . ' KB';
				$name = $receipt->getFileName();
				$pdf->Cell(80, 6, $name, 0, 0, 'L');
				$pdf->Cell(25, 6, $pages, 0, 0, 'R');
				$pdf->Cell(25, 6, $kb, 0, 1, 'R');
			}

			$pdf->Ln(4);

			foreach ($receipts as $receipt) {
				$content = $this->receiptService->getContent($receipt);
				if ($content === null) {
					continue;
				}

				$mime = $receipt->getMimeType();
				$tmpFile = tempnam(sys_get_temp_dir(), 'spes_bbr_');
				try {
					file_put_contents($tmpFile, $content);

					if ($mime === 'application/pdf') {
						try {
							$pageCount = $pdf->setSourceFile($tmpFile);
							$pdf->AddPage();
							$pdf->SetY(10);
							$pdf->SetFont('helvetica', 'B', 9);
							$pdf->Cell(0, 5, 'Anhang: ' . $receipt->getFileName() . ' (' . $pageCount . ' Seite' . ($pageCount > 1 ? 'n' : '') . ')', 0, 1, 'C');
							$pdf->Ln(2);

							for ($i = 1; $i <= $pageCount; $i++) {
								$tplId = $pdf->importPage($i);
								$size = $pdf->getTemplateSize($tplId);
								if ($i > 1) {
									$pdf->AddPage();
								}
								$pdf->useTemplate($tplId, null, null, $size['width'], $size['height'], true);
							}
						} catch (\Throwable $e) {
							$this->log($expense->getId(), 'PDF embedding failed for ' . $receipt->getFileName() . ': ' . $e->getMessage());
							$pdf->SetFont('helvetica', '', 9);
							$pdf->Cell(0, 7, 'PDF konnte nicht eingebettet werden: ' . $receipt->getFileName(), 0, 1);
						}
					} elseif (in_array($mime, ['image/jpeg', 'image/jpg', 'image/pjpeg', 'image/png'], true)) {
						try {
							$info = getimagesize($tmpFile);
							if ($info) {
								$pdf->AddPage();
								$pdf->SetY(10);
								$pdf->SetFont('helvetica', 'B', 9);
								$pdf->Cell(0, 5, 'Anhang: ' . $receipt->getFileName(), 0, 1, 'C');
								$pdf->Ln(2);

								$imgW = $info[0];
								$imgH = $info[1];
								$pageW = $pdf->getPageWidth() - 30;
								$pageH = $pdf->getPageHeight() - 30;
								$scale = min($pageW / $imgW, $pageH / $imgH, 1);
								$w = $imgW * $scale;
								$h = $imgH * $scale;
								$x = ($pdf->getPageWidth() - $w) / 2;
								$pdf->Image($tmpFile, $x, $pdf->GetY(), $w, $h);
							}
						} catch (\Throwable $e) {
							$pdf->SetFont('helvetica', '', 9);
							$pdf->Cell(0, 7, 'Bild konnte nicht eingebettet werden: ' . $receipt->getFileName(), 0, 1);
						}
					}
				} finally {
					if (file_exists($tmpFile)) {
						unlink($tmpFile);
					}
				}
			}
		}

		$pdfContent = $pdf->Output('', 'S');
		$fileName = 'Spesenbeleg_' . $expense->getId() . '.pdf';
		$folderPath = str_replace('\\', '/', $this->settingsService->getBookingFolder());

		try {
			$userFolder = $this->rootFolder->getUserFolder($treasurerUserId);
			$parts = explode('/', trim($folderPath, '/'));
			$current = $userFolder;
			foreach ($parts as $part) {
				if ($part === '') {
					continue;
				}
				if (!$current->nodeExists($part)) {
					$msg = 'Der Ordner "' . $folderPath . '" existiert nicht. Bitte vom Admin in den Einstellungen anlegen lassen.';
					$this->log($expense->getId(), $msg);
					return ['success' => false, 'message' => $msg];
				}
				$current = $current->get($part);
			}

			if ($current->nodeExists($fileName)) {
				$current->get($fileName)->delete();
			}
			$current->newFile($fileName, $pdfContent);

			$msg = 'Spesenbeleg erstellt: ' . $folderPath . '/' . $fileName;
			$this->log($expense->getId(), $msg);
			return ['success' => true, 'message' => $msg];
		} catch (\Throwable $e) {
			$this->log($expense->getId(), 'Fehler beim Speichern des Buchungsbelegs: ' . $e->getMessage());
			return ['success' => false, 'message' => 'Fehler beim Speichern des Buchungsbelegs.'];
		}
	}

	private function log(int $expenseId, string $message): void {
		$this->logger->info('[{id}] {message}', ['app' => 'spesenerfassung', 'id' => $expenseId, 'message' => $message]);
	}
}
