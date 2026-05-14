<?php
class FormHandler {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function submit(int $siteId, int $pageId, array $data): bool {
        $stmt = $this->pdo->prepare(
            "INSERT INTO form_submissions (site_id, page_id, data) VALUES (?, ?, ?)"
        );
        $stmt->execute([$siteId, $pageId, json_encode($data)]);

        $subject = "New contact form submission";
        $body = "New form submission from your website:\n\n";
        foreach ($data as $key => $value) {
            $body .= ucfirst(str_replace('_', ' ', $key)) . ": " . $value . "\n";
        }
        @mail(CONTACT_EMAIL, $subject, $body, "Content-Type: text/plain; charset=UTF-8");
        return true;
    }

    public function listSubmissions(int $siteId): array {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM form_submissions WHERE site_id = ? ORDER BY created_at DESC"
        );
        $stmt->execute([$siteId]);
        $results = $stmt->fetchAll();
        foreach ($results as &$row) {
            $row['data'] = json_decode($row['data'], true);
        }
        return $results;
    }
}
