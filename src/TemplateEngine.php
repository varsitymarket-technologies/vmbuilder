<?php
class TemplateEngine {
    public function listTemplates(): array {
        $templates = [];
        $files = glob(TEMPLATES_DIR . '/*.json');
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $templates[] = [
                    'id' => pathinfo($file, PATHINFO_FILENAME),
                    'name' => $data['name'] ?? pathinfo($file, PATHINFO_FILENAME),
                    'description' => $data['description'] ?? '',
                    'thumbnail' => $data['thumbnail'] ?? '',
                ];
            }
        }
        return $templates;
    }

    public function loadTemplate(string $templateId): ?array {
        $file = TEMPLATES_DIR . '/' . basename($templateId) . '.json';
        if (!file_exists($file)) return null;
        return json_decode(file_get_contents($file), true);
    }

    public function applyTemplate(string $templateId, SiteManager $sm): int {
        $template = $this->loadTemplate($templateId);
        if (!$template) throw new RuntimeException('Template not found');

        $siteId = $sm->createSite(
            $template['name'] ?? 'New Site',
            $template['slug'] ?? 'new-site',
            $template['settings'] ?? []
        );

        foreach (($template['pages'] ?? []) as $index => $page) {
            $sm->savePage($siteId, [
                'name' => $page['name'],
                'slug' => $page['slug'],
                'components' => $page['components'] ?? [],
                'seo' => $page['seo'] ?? [],
                'sort_order' => $index,
            ]);
        }

        return $siteId;
    }
}
