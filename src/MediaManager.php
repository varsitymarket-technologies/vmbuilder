<?php
class MediaManager {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        if (!is_dir(UPLOADS_DIR)) {
            mkdir(UPLOADS_DIR, 0755, true);
        }
    }

    public function list(): array {
        return $this->pdo->query("SELECT * FROM media ORDER BY created_at DESC")->fetchAll();
    }

    public function upload(array $file): array {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed with error code: ' . $file['error']);
        }
        if ($file['size'] > MAX_UPLOAD_SIZE) {
            throw new RuntimeException('File exceeds maximum size of ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB');
        }
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mimeType, $allowed)) {
            throw new RuntimeException('File type not allowed: ' . $mimeType);
        }
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = bin2hex(random_bytes(16)) . '.' . strtolower($ext);
        $destPath = UPLOADS_DIR . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new RuntimeException('Failed to move uploaded file');
        }
        $stmt = $this->pdo->prepare(
            "INSERT INTO media (filename, original_name, mime_type, file_size) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$filename, $file['name'], $mimeType, $file['size']]);
        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'filename' => $filename,
            'original_name' => $file['name'],
            'mime_type' => $mimeType,
            'file_size' => $file['size'],
            'url' => UPLOADS_URL . $filename,
        ];
    }

    public function delete(int $id): bool {
        $stmt = $this->pdo->prepare("SELECT filename FROM media WHERE id = ?");
        $stmt->execute([$id]);
        $media = $stmt->fetch();
        if (!$media) return false;
        $path = UPLOADS_DIR . '/' . $media['filename'];
        if (file_exists($path)) {
            unlink($path);
        }
        $stmt = $this->pdo->prepare("DELETE FROM media WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
