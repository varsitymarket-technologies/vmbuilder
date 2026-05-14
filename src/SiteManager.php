<?php
class SiteManager {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // --- Sites ---

    public function listSites(): array {
        return $this->pdo->query("SELECT * FROM sites ORDER BY updated_at DESC")->fetchAll();
    }

    public function getSite(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM sites WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function createSite(string $name, string $slug, array $settings = []): int {
        $slug = $this->generateUniqueSlug($slug);
        $stmt = $this->pdo->prepare("INSERT INTO sites (name, slug, settings) VALUES (?, ?, ?)");
        $stmt->execute([$name, $slug, json_encode($settings)]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateSite(int $id, array $data): bool {
        $fields = [];
        $values = [];
        foreach (['name', 'slug', 'settings'] as $key) {
            if (isset($data[$key])) {
                $fields[] = "$key = ?";
                $values[] = $key === 'settings' ? json_encode($data[$key]) : $data[$key];
            }
        }
        if (empty($fields)) return false;
        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $values[] = $id;
        $stmt = $this->pdo->prepare("UPDATE sites SET " . implode(', ', $fields) . " WHERE id = ?");
        return $stmt->execute($values);
    }

    public function deleteSite(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM sites WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // --- Pages ---

    public function listPages(int $siteId): array {
        $stmt = $this->pdo->prepare("SELECT * FROM pages WHERE site_id = ? ORDER BY sort_order ASC");
        $stmt->execute([$siteId]);
        return $stmt->fetchAll();
    }

    public function getPage(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$id]);
        $page = $stmt->fetch() ?: null;
        if ($page) {
            $page['components'] = json_decode($page['components'], true);
            $page['seo'] = json_decode($page['seo'], true);
        }
        return $page;
    }

    public function savePage(int $siteId, array $data, ?int $pageId = null): int {
        $components = isset($data['components']) ? json_encode($data['components']) : '[]';
        $seo = isset($data['seo']) ? json_encode($data['seo']) : '{}';

        if ($pageId) {
            $stmt = $this->pdo->prepare(
                "UPDATE pages SET name = ?, slug = ?, components = ?, seo = ?, sort_order = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND site_id = ?"
            );
            $stmt->execute([
                $data['name'], $data['slug'], $components, $seo,
                $data['sort_order'] ?? 0, $pageId, $siteId
            ]);
            return $pageId;
        } else {
            $stmt = $this->pdo->prepare(
                "INSERT INTO pages (site_id, name, slug, components, seo, sort_order) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $siteId, $data['name'], $data['slug'], $components, $seo,
                $data['sort_order'] ?? 0
            ]);
            return (int) $this->pdo->lastInsertId();
        }
    }

    public function deletePage(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM pages WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function reorderPages(int $siteId, array $pageIds): bool {
        $stmt = $this->pdo->prepare("UPDATE pages SET sort_order = ? WHERE id = ? AND site_id = ?");
        foreach ($pageIds as $order => $pageId) {
            $stmt->execute([$order, $pageId, $siteId]);
        }
        return true;
    }

    private function generateUniqueSlug(string $slug): string {
        $slug = preg_replace('/[^a-z0-9-]/', '-', strtolower(trim($slug)));
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        if (empty($slug)) $slug = 'site';

        $original = $slug;
        $counter = 1;
        while (true) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM sites WHERE slug = ?");
            $stmt->execute([$slug]);
            if ($stmt->fetchColumn() == 0) break;
            $slug = $original . '-' . $counter++;
        }
        return $slug;
    }
}
