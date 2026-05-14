<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/SiteManager.php';
require_once __DIR__ . '/../src/ComponentRegistry.php';
require_once __DIR__ . '/../src/TemplateEngine.php';
require_once __DIR__ . '/../src/MediaManager.php';
require_once __DIR__ . '/../src/Publisher.php';
require_once __DIR__ . '/../src/FormHandler.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

Database::migrate();
$pdo = Database::connect();

$sm = new SiteManager($pdo);
$mm = new MediaManager($pdo);
$te = new TemplateEngine();
$pub = new Publisher($pdo, $sm);
$fh = new FormHandler($pdo);

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

function jsonInput(): array {
    $raw = file_get_contents('php://input');
    return $raw ? (json_decode($raw, true) ?: []) : [];
}

function respond(mixed $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function error(string $message, int $code = 400): never {
    respond(['error' => $message], $code);
}

try {
    switch ($action) {
        case 'sites_list':
            respond($sm->listSites());

        case 'sites_create':
            $input = jsonInput();
            if (!empty($input['template'])) {
                $siteId = $te->applyTemplate($input['template'], $sm);
            } else {
                $name = $input['name'] ?? 'Untitled Site';
                $slug = $input['slug'] ?? 'untitled';
                $siteId = $sm->createSite($name, $slug, $input['settings'] ?? []);
                $sm->savePage($siteId, [
                    'name' => 'Home',
                    'slug' => 'home',
                    'components' => [],
                    'seo' => ['title' => $name],
                    'sort_order' => 0,
                ]);
            }
            respond($sm->getSite($siteId), 201);

        case 'sites_update':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) error('Missing site ID');
            $sm->updateSite($id, jsonInput());
            respond($sm->getSite($id));

        case 'sites_delete':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) error('Missing site ID');
            $sm->deleteSite($id);
            respond(['success' => true]);

        case 'pages_list':
            $siteId = (int)($_GET['site_id'] ?? 0);
            if (!$siteId) error('Missing site_id');
            respond($sm->listPages($siteId));

        case 'pages_get':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) error('Missing page ID');
            $page = $sm->getPage($id);
            if (!$page) error('Page not found', 404);
            respond($page);

        case 'pages_save':
            $siteId = (int)($_GET['site_id'] ?? 0);
            if (!$siteId) error('Missing site_id');
            $input = jsonInput();
            $pageId = !empty($_GET['id']) ? (int)$_GET['id'] : null;
            $id = $sm->savePage($siteId, $input, $pageId);
            respond($sm->getPage($id), $pageId ? 200 : 201);

        case 'pages_delete':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) error('Missing page ID');
            $sm->deletePage($id);
            respond(['success' => true]);

        case 'pages_reorder':
            $siteId = (int)($_GET['site_id'] ?? 0);
            if (!$siteId) error('Missing site_id');
            $input = jsonInput();
            $sm->reorderPages($siteId, $input['pageIds'] ?? []);
            respond(['success' => true]);

        case 'media_list':
            respond($mm->list());

        case 'media_upload':
            if (empty($_FILES['file'])) error('No file uploaded');
            respond($mm->upload($_FILES['file']), 201);

        case 'media_delete':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) error('Missing media ID');
            $mm->delete($id);
            respond(['success' => true]);

        case 'publish':
            $siteId = (int)($_GET['site_id'] ?? 0);
            if (!$siteId) error('Missing site_id');
            $slug = $pub->publish($siteId);
            respond(['success' => true, 'url' => '/published/' . $slug . '/index.html']);

        case 'form_submit':
            $siteId = (int)($_POST['site_id'] ?? $_GET['site_id'] ?? 0);
            $pageId = (int)($_POST['page_id'] ?? $_GET['page_id'] ?? 0);
            $data = $_POST;
            unset($data['site_id'], $data['page_id']);
            $fh->submit($siteId, $pageId, $data);
            respond(['success' => true]);

        case 'extensions_list':
            $url = 'https://raw.githubusercontent.com/varsitymarket-technologies/embedded-themes/main/collection/records.json';
            $json = @file_get_contents($url);
            if (!$json) error('Failed to fetch extensions list', 500);
            respond(json_decode($json, true));

        case 'extensions_install':
            $input = jsonInput();
            $themeId = $input['theme_id'] ?? '';
            if (!$themeId) error('Missing theme_id');
            
            $baseUrl = "https://raw.githubusercontent.com/varsitymarket-technologies/embedded-themes/main/collection/{$themeId}";
            $interfaceHtml = @file_get_contents("{$baseUrl}/interface");
            $autofillContent = @file_get_contents("{$baseUrl}/autofill.json") ?: '{}';
            
            if (!$interfaceHtml) error('Failed to fetch theme interface', 500);
            
            $autofill = json_decode($autofillContent, true) ?: [];
            
            // Prepare components array
            $components = [];
            
            // 1. Extract and add CSS/Styles
            preg_match_all('/(<style\b[^>]*>.*?<\/style>|<link\b[^>]*>)/is', $interfaceHtml, $styleMatches);
            $themeStyleHtml = implode("\n", $styleMatches[0]);
            if (trim($themeStyleHtml)) {
                $props = $autofill;
                $props['_tpl_id'] = 'global_styles';
                $props['_html'] = $themeStyleHtml;
                $props['_schema'] = $autofillContent;
                $components[] = [
                    'id' => 'c' . substr(md5($themeId . 'style' . time()), 0, 9),
                    'type' => 'theme_section',
                    'props' => $props
                ];
            }

            // Extract Body to find Header/Footer shells
            $bodyHtml = preg_match('/<body[^>]*>(.*?)<\/body>/is', $interfaceHtml, $m) ? $m[1] : $interfaceHtml;
            $bodyBlocks = preg_split('/<template\b[^>]*>.*?<\/template>/is', $bodyHtml);
            
            // 2. Add Top Shell (e.g. Header)
            $topShell = isset($bodyBlocks[0]) ? trim($bodyBlocks[0]) : '';
            if ($topShell) {
                $topShell = preg_replace('/<main\b[^>]*>\s*<\/main>/is', '', $topShell); // Remove empty main if present
                if (trim($topShell)) {
                    $props = $autofill;
                    $props['_tpl_id'] = 'header_shell';
                    $props['_html'] = $topShell;
                    $props['_schema'] = $autofillContent;
                    $components[] = [
                        'id' => 'c' . substr(md5($themeId . 'head' . time()), 0, 9),
                        'type' => 'theme_section',
                        'props' => $props
                    ];
                }
            }

            // 3. Extract Templates
            preg_match_all('/<template id="tpl-([^"]+)">(.*?)<\/template>/is', $interfaceHtml, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $tplId = $match[1];
                $tplHtml = trim($match[2]);
                
                $props = $autofill;
                $props['_tpl_id'] = $tplId;
                $props['_html'] = $tplHtml;
                $props['_schema'] = $autofillContent;
                
                $components[] = [
                    'id' => 'c' . substr(md5($themeId . $tplId . time()), 0, 9),
                    'type' => 'theme_section',
                    'props' => $props
                ];
            }

            // 4. Add Bottom Shell (e.g. Footer, Scripts)
            $bottomShell = end($bodyBlocks);
            if (count($bodyBlocks) > 1 && trim($bottomShell)) {
                $props = $autofill;
                $props['_tpl_id'] = 'footer_shell';
                $props['_html'] = trim($bottomShell);
                $props['_schema'] = $autofillContent;
                $components[] = [
                    'id' => 'c' . substr(md5($themeId . 'foot' . time()), 0, 9),
                    'type' => 'theme_section',
                    'props' => $props
                ];
            }

            $name = $input['name'] ?? 'Theme: ' . $themeId;
            $slug = 'theme-' . preg_replace('/[^a-z0-9]/', '-', strtolower($themeId)) . '-' . time();
            
            $siteId = $sm->createSite($name, $slug, []);
            
            $sm->savePage($siteId, [
                'name' => 'Home',
                'slug' => 'home',
                'components' => $components,
                'seo' => ['title' => $name],
                'sort_order' => 0,
            ]);
            
            respond($sm->getSite($siteId), 201);

        case 'templates_list':
            respond($te->listTemplates());

        case 'components_list':
            respond(ComponentRegistry::getAll());

        default:
            error('Unknown action: ' . $action, 404);
    }
} catch (RuntimeException $e) {
    error($e->getMessage());
} catch (Exception $e) {
    error('Internal server error', 500);
}
