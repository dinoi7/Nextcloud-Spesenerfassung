<?php
declare(strict_types=1);

namespace OCA\Spesenerfassung\Service;

use OCA\Spesenerfassung\Db\Receipt;
use OCA\Spesenerfassung\Db\ReceiptMapper;
use DateTime;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\Files\NotPermittedException;
use Psr\Log\LoggerInterface;

class ReceiptService {
	private const MAX_SIZE = 1048576;
	private const MAX_FILES = 5;

	private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

	private ReceiptMapper $receiptMapper;
	private IAppData $appData;
	private LoggerInterface $logger;

	public function __construct(ReceiptMapper $receiptMapper, IAppData $appData, LoggerInterface $logger) {
		$this->receiptMapper = $receiptMapper;
		$this->appData = $appData;
		$this->logger = $logger;
	}

	public function validateFile(string $fileName, string $tempPath, int $size): ?string {
		if ($size > self::MAX_SIZE) {
			return 'File too large. Maximum size is 1MB.';
		}
		if ($size === 0) {
			return 'File is empty.';
		}
		if ($tempPath === '' || !file_exists($tempPath) || !is_readable($tempPath)) {
			return 'File not accessible.';
		}

		$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
		if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
			return 'Invalid file type. Allowed: PDF, JPG, PNG.';
		}

		$detected = (new \finfo(FILEINFO_MIME_TYPE))->file($tempPath);
		$allowedMimes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/pjpeg'];
		if (!in_array($detected, $allowedMimes, true)) {
			return 'Invalid file type. Allowed: PDF, JPG, PNG.';
		}

		return $detected;
	}

	public function findByExpenseId(int $expenseId): array {
		return $this->receiptMapper->findByExpenseId($expenseId);
	}

	public function upload(int $expenseId, string $originalName, string $tempPath, string $mimeType, int $size): ?Receipt {
		$existing = $this->receiptMapper->findByExpenseId($expenseId);
		if (count($existing) >= self::MAX_FILES) {
			$this->logger->warning('Receipt upload failed: too many files for expense {id}', ['app' => 'spesenerfassung', 'id' => $expenseId]);
			return null;
		}

		$detectedMime = $this->validateFile($originalName, $tempPath, $size);
		if ($detectedMime === null) {
			$this->logger->warning('Receipt upload validation failed', ['app' => 'spesenerfassung']);
			return null;
		}

		$safeName = $this->sanitizeFileName($originalName);
		$now = (new DateTime())->format('Y-m-d H:i:s');

		$content = file_get_contents($tempPath);
		if ($content === false) {
			$this->logger->error('Receipt upload: file_get_contents failed for temp path', ['app' => 'spesenerfassung']);
			return null;
		}

		try {
			try {
				$receiptsFolder = $this->appData->getFolder('receipts');
			} catch (FilesNotFoundException) {
				$receiptsFolder = $this->appData->newFolder('receipts');
			}

			try {
				$expenseFolder = $receiptsFolder->getFolder((string) $expenseId);
			} catch (FilesNotFoundException) {
				$expenseFolder = $receiptsFolder->newFolder((string) $expenseId);
			}

			$counter = 1;
			$finalName = $safeName;
			$ext = pathinfo($safeName, PATHINFO_EXTENSION);
			$base = pathinfo($safeName, PATHINFO_FILENAME);
			while ($expenseFolder->fileExists($finalName)) {
				$finalName = $base . '_' . $counter . ($ext !== '' && $ext !== $base ? '.' . $ext : '');
				$counter++;
			}

			$expenseFolder->newFile($finalName, $content);

			$receipt = new Receipt();
			$receipt->setExpenseId($expenseId);
			$receipt->setFileName($finalName);
			$receipt->setFilePath('receipts/' . $expenseId . '/' . $finalName);
			$receipt->setMimeType($detectedMime);
			$receipt->setSize($size);
			$receipt->setCreatedAt($now);

			$result = $this->receiptMapper->insert($receipt);
			return $result;
		} catch (\Throwable $e) {
			$this->logger->error('Receipt upload exception: {message}', ['app' => 'spesenerfassung', 'message' => $e->getMessage(), 'exception' => $e]);
			return null;
		}
	}

	public function delete(int $receiptId): bool {
		try {
			$receipt = $this->receiptMapper->findById($receiptId);
		} catch (DoesNotExistException) {
			return false;
		}

		try {
			$receiptsFolder = $this->appData->getFolder('receipts');
			$expenseFolder = $receiptsFolder->getFolder((string) $receipt->getExpenseId());
			$file = $expenseFolder->getFile($receipt->getFileName());
			$file->delete();
		} catch (FilesNotFoundException) {
		}

		$this->receiptMapper->delete($receipt);
		return true;
	}

	public function findById(int $receiptId): ?Receipt {
		try {
			return $this->receiptMapper->findById($receiptId);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function getContent(Receipt $receipt): ?string {
		try {
			$receiptsFolder = $this->appData->getFolder('receipts');
			$expenseFolder = $receiptsFolder->getFolder((string) $receipt->getExpenseId());
			$file = $expenseFolder->getFile($receipt->getFileName());
			return $file->getContent();
		} catch (\Throwable) {
			return null;
		}
	}

	public function getPageCount(Receipt $receipt): ?int {
		if ($receipt->getMimeType() !== 'application/pdf') {
			return null;
		}
		$content = $this->getContent($receipt);
		if ($content === null) {
			return null;
		}
		$matches = [];
		preg_match_all('/\/Type\s*\/Page[^s]/', $content, $matches);
		$count = count($matches[0]);
		return $count > 0 ? $count : 1;
	}

	private function sanitizeFileName(string $name): string {
		$name = basename($name);
		$name = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $name);
		return $name ?: 'receipt';
	}
}
