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
	private const MAX_IMAGE_EDGE = 1600;
	private const JPEG_QUALITY = 80;

	private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

	private ReceiptMapper $receiptMapper;
	private IAppData $appData;
	private LoggerInterface $logger;

	public function __construct(ReceiptMapper $receiptMapper, IAppData $appData, LoggerInterface $logger) {
		$this->receiptMapper = $receiptMapper;
		$this->appData = $appData;
		$this->logger = $logger;
	}

	private function detectMime(string $fileName, string $tempPath): ?string {
		if ($tempPath === '' || !file_exists($tempPath) || !is_readable($tempPath)) {
			return null;
		}

		$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
		if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
			return null;
		}

		$detected = (new \finfo(FILEINFO_MIME_TYPE))->file($tempPath);
		$allowedMimes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/pjpeg'];
		if (!in_array($detected, $allowedMimes, true)) {
			return null;
		}

		return $detected;
	}

	private function resizeImage(string $content, string $mimeType): string {
		if (!function_exists('imagecreatefromstring') || !function_exists('getimagesizefromstring')) {
			return $content;
		}

		$info = @getimagesizefromstring($content);
		if ($info === false) {
			return $content;
		}
		$width = (int) $info[0];
		$height = (int) $info[1];
		if ($width <= 0 || $height <= 0) {
			return $content;
		}

		$longest = max($width, $height);
		if ($longest <= self::MAX_IMAGE_EDGE && strlen($content) <= self::MAX_SIZE) {
			return $content;
		}

		try {
			$image = @imagecreatefromstring($content);
			if ($image === false) {
				return $content;
			}

			$scale = min(1.0, self::MAX_IMAGE_EDGE / $longest);
			$newWidth = max(1, (int) round($width * $scale));
			$newHeight = max(1, (int) round($height * $scale));
			$resized = @imagescale($image, $newWidth, $newHeight, IMG_BICUBIC);
			imagedestroy($image);
			if ($resized === false) {
				return $content;
			}

			ob_start();
			if ($mimeType === 'image/png') {
				imagealphablending($resized, false);
				imagesavealpha($resized, true);
				imagepng($resized);
			} else {
				imagejpeg($resized, null, self::JPEG_QUALITY);
			}
			$output = ob_get_clean();
			imagedestroy($resized);

			if (!is_string($output) || $output === '') {
				return $content;
			}
			return $output;
		} catch (\Throwable) {
			if (ob_get_level() > 0) {
				ob_end_clean();
			}
			return $content;
		}
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

		$detectedMime = $this->detectMime($originalName, $tempPath);
		if ($detectedMime === null) {
			$this->logger->warning('Receipt upload validation failed', ['app' => 'spesenerfassung']);
			return null;
		}
		if ($size === 0) {
			$this->logger->warning('Receipt upload failed: empty file', ['app' => 'spesenerfassung']);
			return null;
		}

		$safeName = $this->sanitizeFileName($originalName);
		$now = (new DateTime())->format('Y-m-d H:i:s');

		$content = file_get_contents($tempPath);
		if ($content === false) {
			$this->logger->error('Receipt upload: file_get_contents failed for temp path', ['app' => 'spesenerfassung']);
			return null;
		}

		if (in_array($detectedMime, ['image/jpeg', 'image/jpg', 'image/pjpeg', 'image/png'], true)) {
			$content = $this->resizeImage($content, $detectedMime);
		}
		$size = strlen($content);

		if ($size > self::MAX_SIZE) {
			$this->logger->warning('Receipt upload failed: file too large after processing', ['app' => 'spesenerfassung', 'size' => $size]);
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
