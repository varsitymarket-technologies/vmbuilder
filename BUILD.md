# Website Builder Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a PHP website builder with drag-and-drop editing, pre-built templates, and static site publishing.

**Architecture:** SPA editor (Vue.js + Alpine.js + Tailwind CSS via CDN) backed by a PHP API with SQLite storage. Sites are composed of JSON component arrays, edited visually, and published as static HTML files.

**Tech Stack:** PHP 8+, SQLite3, Vue.js 3 (CDN), Alpine.js (CDN), Tailwind CSS (CDN), SortableJS (CDN for drag-and-drop)

**Prerequisites:** PHP 8+ with SQLite3 extension must be installed and available in PATH. Download from https://windows.php.net/download — extract, add to PATH, enable `extension=pdo_sqlite` and `extension=sqlite3` in php.ini.

---

## File Structure

```
vm.builder/
├── config.php                          # App configuration (paths, base URL)
├── public/
│   ├── index.php                       # SPA entry point - serves editor HTML
│   ├── api.php                         # API router - all backend endpoints
│   ├── assets/
│   │   ├── css/
│   │   │   └── editor.css              # Editor-specific styles
│   │   ├── js/
│   │   │   ├── app.js                  # Vue app initialization + state
│   │   │   ├── components/
│   │   │   │   ├── TopBar.js           # Top bar (site name, pages, publish)
│   │   │   │   ├── LeftSidebar.js      # Component panel + page list + templates
│   │   │   │   ├── Canvas.js           # Drag-drop canvas with component rendering
│   │   │   │   ├── RightSidebar.js     # Properties panel for selected component
│   │   │   │   ├── MediaLibrary.js     # Media upload & selection modal
│   │   │   │   ├── SeoModal.js         # SEO settings modal
│   │   │   │   └── SiteSelector.js     # Site list / create / template picker
│   │   │   ├── renderers/
│   │   │   │   └── ComponentRenderer.js # Renders each component type on canvas
│   │   │   └── api.js                  # API client (fetch wrapper)
│   │   └── img/                        # Static images/icons
│   └── published/                      # Output directory for published sites
├── src/
│   ├── Database.php                    # SQLite PDO connection + schema migration
│   ├── SiteManager.php                 # Sites & pages CRUD
│   ├── ComponentRegistry.php           # Component type definitions + defaults
│   ├── TemplateEngine.php              # Load & apply pre-built templates
│   ├── MediaManager.php                # File upload, list, delete
│   ├── Publisher.php                   # Render site JSON → static HTML
│   └── FormHandler.php                 # Contact form POST handler + email
├── templates/
│   ├── business-landing.json           # Business landing page template
│   ├── portfolio.json                  # Portfolio template
│   └── restaurant.json                 # Restaurant/local business template
├── storage/
│   ├── database.sqlite                 # Created at runtime
│   └── uploads/                        # User uploads directory
└── tests/
    └── test_backend.php                # CLI test script for PHP backend
```

---

### Task 1: Project Scaffold + Configuration + Database

**Files:**
- Create: `config.php`
- Create: `src/Database.php`
- Create: `tests/test_backend.php`

- [ ] **Step 1: Create directory structure**

```bash
mkdir -p public/assets/css public/assets/js/components public/assets/js/renderers public/assets/img public/published src templates storage/uploads tests
```

- [ ] **Step 2: Create config.php**

```php
<?php
define('ROOT_DIR', __DIR__);
define('PUBLIC_DIR', ROOT_DIR . '/public');
define('STORAGE_DIR', ROOT_DIR . '/storage');
define('UPLOADS_DIR', STORAGE_DIR . '/uploads');
define('PUBLISHED_DIR', PUBLIC_DIR . '/published');
define('TEMPLATES_DIR', ROOT_DIR . '/templates');
define('DB_PATH', STORAGE_DIR . '/database.sqlite');
define('BASE_URL', '/');
define('UPLOADS_URL', '/storage/uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('CONTACT_EMAIL', 'admin@example.com');
```

- [ ] **Step 3: Create src/Database.php**

```php
<?php
class Database {
    private static ?PDO $pdo = null;

    public static function connect(): PDO {
        if (self::$pdo === null) {
            self::$pdo = new PDO('sqlite:' . DB_PATH, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            self::$pdo->exec('PRAGMA journal_mode=WAL');
            self::$pdo->exec('PRAGMA foreign_keys=ON');
        }
        return self::$pdo;
    }

    public static function migrate(): void {
        $pdo = self::connect();

        $pdo->exec("CREATE TABLE IF NOT EXISTS sites (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            settings TEXT NOT NULL DEFAULT '{}',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS pages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            site_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            slug TEXT NOT NULL,
            components TEXT NOT NULL DEFAULT '[]',
            seo TEXT NOT NULL DEFAULT '{}',
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS media (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename TEXT NOT NULL,
            original_name TEXT NOT NULL,
            mime_type TEXT NOT NULL,
            file_size INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS form_submissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            site_id INTEGER NOT NULL,
            page_id INTEGER NOT NULL,
            data TEXT NOT NULL DEFAULT '{}',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
            FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
        )");
    }
}
```

- [ ] **Step 4: Create tests/test_backend.php**

```php
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';

define('TEST_DB', __DIR__ . '/test.sqlite');

function setup_test_db(): void {
    if (file_exists(TEST_DB)) unlink(TEST_DB);
    // Override DB_PATH for testing by reconnecting
    $ref = new ReflectionClass('Database');
    $prop = $ref->getProperty('pdo');
    $prop->setAccessible(true);
    $prop->setValue(null, null);
}

function assert_true(bool $condition, string $msg): void {
    if (!$condition) {
        echo "FAIL: $msg\n";
        exit(1);
    }
    echo "PASS: $msg\n";
}

// Override DB_PATH for tests
define('DB_PATH_BACKUP', DB_PATH);
// We redefine via a test-specific approach
putenv('DB_PATH=' . TEST_DB);

echo "=== Database Tests ===\n";

// Test 1: Migration creates tables
setup_test_db();
// Use test DB directly
$pdo = new PDO('sqlite:' . TEST_DB, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA foreign_keys=ON');

$pdo->exec("CREATE TABLE IF NOT EXISTS sites (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    settings TEXT NOT NULL DEFAULT '{}',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS pages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    site_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    slug TEXT NOT NULL,
    components TEXT NOT NULL DEFAULT '[]',
    seo TEXT NOT NULL DEFAULT '{}',
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
)");

$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
assert_true(in_array('sites', $tables), 'sites table exists');
assert_true(in_array('pages', $tables), 'pages table exists');

// Test 2: Can insert and retrieve a site
$pdo->exec("INSERT INTO sites (name, slug) VALUES ('Test Site', 'test-site')");
$site = $pdo->query("SELECT * FROM sites WHERE slug='test-site'")->fetch();
assert_true($site['name'] === 'Test Site', 'site inserted and retrieved');

// Test 3: Can insert a page linked to site
$siteId = $site['id'];
$pdo->prepare("INSERT INTO pages (site_id, name, slug, sort_order) VALUES (?, ?, ?, ?)")
    ->execute([$siteId, 'Home', 'home', 0]);
$page = $pdo->query("SELECT * FROM pages WHERE site_id=$siteId")->fetch();
assert_true($page['name'] === 'Home', 'page inserted and linked to site');

// Test 4: Cascade delete
$pdo->exec("DELETE FROM sites WHERE id=$siteId");
$pages = $pdo->query("SELECT * FROM pages WHERE site_id=$siteId")->fetchAll();
assert_true(count($pages) === 0, 'pages deleted on site cascade delete');

// Cleanup
unlink(TEST_DB);
echo "\nAll tests passed!\n";
```

- [ ] **Step 5: Run tests**

```bash
php tests/test_backend.php
```

Expected: All 4 tests PASS.

- [ ] **Step 6: Create .gitignore and commit**

Create `.gitignore`:
```
storage/database.sqlite
storage/uploads/*
!storage/uploads/.gitkeep
public/published/*
!public/published/.gitkeep
tests/test.sqlite
```

Create placeholder `.gitkeep` files:
```bash
touch storage/uploads/.gitkeep public/published/.gitkeep
```

```bash
git add -A
git commit -m "feat: project scaffold with config, database, and tests"
```

---

### Task 2: SiteManager — Sites & Pages CRUD

**Files:**
- Create: `src/SiteManager.php`
- Modify: `tests/test_backend.php`

- [ ] **Step 1: Create src/SiteManager.php**

```php
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
```

- [ ] **Step 2: Add SiteManager tests to tests/test_backend.php**

Append before the cleanup section:

```php
require_once __DIR__ . '/../src/SiteManager.php';

echo "\n=== SiteManager Tests ===\n";

// Re-create tables for SiteManager tests
$pdo2 = new PDO('sqlite:' . TEST_DB, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo2->exec('PRAGMA foreign_keys=ON');
// Create tables again
$pdo2->exec("CREATE TABLE IF NOT EXISTS sites (
    id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, slug TEXT NOT NULL UNIQUE,
    settings TEXT NOT NULL DEFAULT '{}', created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$pdo2->exec("CREATE TABLE IF NOT EXISTS pages (
    id INTEGER PRIMARY KEY AUTOINCREMENT, site_id INTEGER NOT NULL, name TEXT NOT NULL,
    slug TEXT NOT NULL, components TEXT NOT NULL DEFAULT '[]', seo TEXT NOT NULL DEFAULT '{}',
    sort_order INTEGER NOT NULL DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
)");

$sm = new SiteManager($pdo2);

// Test: Create site
$siteId = $sm->createSite('My Site', 'my-site', ['primaryColor' => '#3b82f6']);
assert_true($siteId > 0, 'createSite returns ID');

$site = $sm->getSite($siteId);
assert_true($site['name'] === 'My Site', 'getSite returns correct name');
assert_true($site['slug'] === 'my-site', 'getSite returns correct slug');

// Test: List sites
$sites = $sm->listSites();
assert_true(count($sites) === 1, 'listSites returns 1 site');

// Test: Update site
$sm->updateSite($siteId, ['name' => 'Updated Site']);
$site = $sm->getSite($siteId);
assert_true($site['name'] === 'Updated Site', 'updateSite changes name');

// Test: Duplicate slug gets suffix
$siteId2 = $sm->createSite('Another', 'my-site');
$site2 = $sm->getSite($siteId2);
assert_true($site2['slug'] === 'my-site-1', 'duplicate slug gets -1 suffix');

// Test: Create page
$pageId = $sm->savePage($siteId, [
    'name' => 'Home',
    'slug' => 'home',
    'components' => [['id' => 'c1', 'type' => 'heading', 'props' => ['text' => 'Hello']]],
    'seo' => ['title' => 'Home Page'],
    'sort_order' => 0,
]);
assert_true($pageId > 0, 'savePage creates page');

// Test: Get page with decoded JSON
$page = $sm->getPage($pageId);
assert_true($page['components'][0]['type'] === 'heading', 'getPage decodes components JSON');
assert_true($page['seo']['title'] === 'Home Page', 'getPage decodes seo JSON');

// Test: Update page
$sm->savePage($siteId, [
    'name' => 'Home Updated',
    'slug' => 'home',
    'components' => [],
    'sort_order' => 0,
], $pageId);
$page = $sm->getPage($pageId);
assert_true($page['name'] === 'Home Updated', 'savePage updates existing page');

// Test: List pages
$pages = $sm->listPages($siteId);
assert_true(count($pages) === 1, 'listPages returns 1 page');

// Test: Delete page
$sm->deletePage($pageId);
$pages = $sm->listPages($siteId);
assert_true(count($pages) === 0, 'deletePage removes page');

// Test: Delete site
$sm->deleteSite($siteId);
$sites = $sm->listSites();
assert_true(count($sites) === 1, 'deleteSite removes site (1 remaining)');
```

- [ ] **Step 3: Run tests**

```bash
php tests/test_backend.php
```

Expected: All tests PASS.

- [ ] **Step 4: Commit**

```bash
git add src/SiteManager.php tests/test_backend.php
git commit -m "feat: SiteManager with sites and pages CRUD"
```

---

### Task 3: ComponentRegistry — Component Definitions

**Files:**
- Create: `src/ComponentRegistry.php`

- [ ] **Step 1: Create src/ComponentRegistry.php**

```php
<?php
class ComponentRegistry {
    public static function getAll(): array {
        return [
            'layout' => [
                'section' => self::section(),
                'columns' => self::columns(),
                'spacer' => self::spacer(),
            ],
            'content' => [
                'heading' => self::heading(),
                'text' => self::text(),
                'image' => self::image(),
                'video' => self::video(),
                'button' => self::button(),
            ],
            'business' => [
                'hero' => self::hero(),
                'features' => self::features(),
                'testimonials' => self::testimonials(),
                'pricing' => self::pricing(),
                'contact_form' => self::contactForm(),
                'map' => self::map(),
                'gallery' => self::gallery(),
            ],
            'global' => [
                'navbar' => self::navbar(),
                'footer' => self::footer(),
            ],
        ];
    }

    public static function getDefaults(string $type): array {
        $all = self::getAll();
        foreach ($all as $components) {
            if (isset($components[$type])) {
                return $components[$type]['defaults'];
            }
        }
        return [];
    }

    public static function getSchema(string $type): array {
        $all = self::getAll();
        foreach ($all as $components) {
            if (isset($components[$type])) {
                return $components[$type]['schema'];
            }
        }
        return [];
    }

    public static function getFlat(): array {
        $flat = [];
        foreach (self::getAll() as $category => $components) {
            foreach ($components as $type => $def) {
                $flat[$type] = array_merge($def, ['category' => $category]);
            }
        }
        return $flat;
    }

    private static function section(): array {
        return [
            'label' => 'Section',
            'icon' => 'square',
            'defaults' => [
                'backgroundColor' => '#ffffff',
                'backgroundImage' => '',
                'paddingTop' => 60,
                'paddingBottom' => 60,
                'children' => [],
            ],
            'schema' => [
                ['key' => 'backgroundColor', 'type' => 'color', 'label' => 'Background Color'],
                ['key' => 'backgroundImage', 'type' => 'image', 'label' => 'Background Image'],
                ['key' => 'paddingTop', 'type' => 'number', 'label' => 'Padding Top (px)'],
                ['key' => 'paddingBottom', 'type' => 'number', 'label' => 'Padding Bottom (px)'],
            ],
        ];
    }

    private static function columns(): array {
        return [
            'label' => 'Columns',
            'icon' => 'columns',
            'defaults' => [
                'count' => 2,
                'gap' => 24,
                'children' => [],
            ],
            'schema' => [
                ['key' => 'count', 'type' => 'select', 'label' => 'Columns', 'options' => [2, 3, 4]],
                ['key' => 'gap', 'type' => 'number', 'label' => 'Gap (px)'],
            ],
        ];
    }

    private static function spacer(): array {
        return [
            'label' => 'Spacer',
            'icon' => 'minus',
            'defaults' => ['height' => 40],
            'schema' => [
                ['key' => 'height', 'type' => 'number', 'label' => 'Height (px)'],
            ],
        ];
    }

    private static function heading(): array {
        return [
            'label' => 'Heading',
            'icon' => 'type',
            'defaults' => [
                'text' => 'Heading Text',
                'level' => 'h2',
                'alignment' => 'left',
                'color' => '#111827',
            ],
            'schema' => [
                ['key' => 'text', 'type' => 'text', 'label' => 'Text'],
                ['key' => 'level', 'type' => 'select', 'label' => 'Level', 'options' => ['h1','h2','h3','h4','h5','h6']],
                ['key' => 'alignment', 'type' => 'select', 'label' => 'Alignment', 'options' => ['left','center','right']],
                ['key' => 'color', 'type' => 'color', 'label' => 'Color'],
            ],
        ];
    }

    private static function text(): array {
        return [
            'label' => 'Text',
            'icon' => 'align-left',
            'defaults' => [
                'content' => '<p>Enter your text here.</p>',
                'alignment' => 'left',
            ],
            'schema' => [
                ['key' => 'content', 'type' => 'richtext', 'label' => 'Content'],
                ['key' => 'alignment', 'type' => 'select', 'label' => 'Alignment', 'options' => ['left','center','right']],
            ],
        ];
    }

    private static function image(): array {
        return [
            'label' => 'Image',
            'icon' => 'image',
            'defaults' => [
                'src' => '',
                'alt' => '',
                'width' => 'full',
                'link' => '',
            ],
            'schema' => [
                ['key' => 'src', 'type' => 'image', 'label' => 'Image'],
                ['key' => 'alt', 'type' => 'text', 'label' => 'Alt Text'],
                ['key' => 'width', 'type' => 'select', 'label' => 'Width', 'options' => ['small','medium','large','full']],
                ['key' => 'link', 'type' => 'text', 'label' => 'Link URL'],
            ],
        ];
    }

    private static function video(): array {
        return [
            'label' => 'Video',
            'icon' => 'play',
            'defaults' => [
                'url' => '',
                'aspectRatio' => '16:9',
            ],
            'schema' => [
                ['key' => 'url', 'type' => 'text', 'label' => 'YouTube/Vimeo URL'],
                ['key' => 'aspectRatio', 'type' => 'select', 'label' => 'Aspect Ratio', 'options' => ['16:9','4:3','1:1']],
            ],
        ];
    }

    private static function button(): array {
        return [
            'label' => 'Button',
            'icon' => 'mouse-pointer',
            'defaults' => [
                'text' => 'Click Me',
                'link' => '#',
                'style' => 'solid',
                'color' => '#3b82f6',
                'alignment' => 'left',
            ],
            'schema' => [
                ['key' => 'text', 'type' => 'text', 'label' => 'Button Text'],
                ['key' => 'link', 'type' => 'text', 'label' => 'Link URL'],
                ['key' => 'style', 'type' => 'select', 'label' => 'Style', 'options' => ['solid','outline']],
                ['key' => 'color', 'type' => 'color', 'label' => 'Color'],
                ['key' => 'alignment', 'type' => 'select', 'label' => 'Alignment', 'options' => ['left','center','right']],
            ],
        ];
    }

    private static function hero(): array {
        return [
            'label' => 'Hero',
            'icon' => 'star',
            'defaults' => [
                'heading' => 'Welcome to Our Website',
                'subheading' => 'We help you build something amazing',
                'ctaText' => 'Get Started',
                'ctaUrl' => '#',
                'backgroundImage' => '',
                'backgroundColor' => '#1e3a5f',
                'textColor' => '#ffffff',
                'overlay' => true,
            ],
            'schema' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ['key' => 'subheading', 'type' => 'text', 'label' => 'Subheading'],
                ['key' => 'ctaText', 'type' => 'text', 'label' => 'Button Text'],
                ['key' => 'ctaUrl', 'type' => 'text', 'label' => 'Button URL'],
                ['key' => 'backgroundImage', 'type' => 'image', 'label' => 'Background Image'],
                ['key' => 'backgroundColor', 'type' => 'color', 'label' => 'Background Color'],
                ['key' => 'textColor', 'type' => 'color', 'label' => 'Text Color'],
                ['key' => 'overlay', 'type' => 'toggle', 'label' => 'Dark Overlay'],
            ],
        ];
    }

    private static function features(): array {
        return [
            'label' => 'Features',
            'icon' => 'grid',
            'defaults' => [
                'heading' => 'Our Features',
                'columns' => 3,
                'items' => [
                    ['icon' => '⚡', 'title' => 'Fast', 'description' => 'Lightning fast performance'],
                    ['icon' => '🔒', 'title' => 'Secure', 'description' => 'Built with security in mind'],
                    ['icon' => '📱', 'title' => 'Responsive', 'description' => 'Works on all devices'],
                ],
            ],
            'schema' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Section Heading'],
                ['key' => 'columns', 'type' => 'select', 'label' => 'Columns', 'options' => [3, 4]],
                ['key' => 'items', 'type' => 'repeater', 'label' => 'Features', 'fields' => [
                    ['key' => 'icon', 'type' => 'text', 'label' => 'Icon (emoji)'],
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    ['key' => 'description', 'type' => 'textarea', 'label' => 'Description'],
                ]],
            ],
        ];
    }

    private static function testimonials(): array {
        return [
            'label' => 'Testimonials',
            'icon' => 'message-circle',
            'defaults' => [
                'heading' => 'What Our Clients Say',
                'items' => [
                    ['quote' => 'Amazing service!', 'name' => 'John Doe', 'role' => 'CEO', 'photo' => ''],
                    ['quote' => 'Highly recommended.', 'name' => 'Jane Smith', 'role' => 'Designer', 'photo' => ''],
                ],
            ],
            'schema' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Section Heading'],
                ['key' => 'items', 'type' => 'repeater', 'label' => 'Testimonials', 'fields' => [
                    ['key' => 'quote', 'type' => 'textarea', 'label' => 'Quote'],
                    ['key' => 'name', 'type' => 'text', 'label' => 'Name'],
                    ['key' => 'role', 'type' => 'text', 'label' => 'Role'],
                    ['key' => 'photo', 'type' => 'image', 'label' => 'Photo'],
                ]],
            ],
        ];
    }

    private static function pricing(): array {
        return [
            'label' => 'Pricing',
            'icon' => 'dollar-sign',
            'defaults' => [
                'heading' => 'Pricing Plans',
                'plans' => [
                    ['name' => 'Basic', 'price' => '$9/mo', 'features' => ["5 Pages", "Basic Support", "1GB Storage"], 'highlighted' => false, 'ctaText' => 'Choose Plan', 'ctaUrl' => '#'],
                    ['name' => 'Pro', 'price' => '$29/mo', 'features' => ["Unlimited Pages", "Priority Support", "10GB Storage"], 'highlighted' => true, 'ctaText' => 'Choose Plan', 'ctaUrl' => '#'],
                    ['name' => 'Enterprise', 'price' => '$99/mo', 'features' => ["Everything in Pro", "Dedicated Support", "100GB Storage"], 'highlighted' => false, 'ctaText' => 'Contact Us', 'ctaUrl' => '#'],
                ],
            ],
            'schema' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Section Heading'],
                ['key' => 'plans', 'type' => 'repeater', 'label' => 'Plans', 'fields' => [
                    ['key' => 'name', 'type' => 'text', 'label' => 'Plan Name'],
                    ['key' => 'price', 'type' => 'text', 'label' => 'Price'],
                    ['key' => 'features', 'type' => 'textarea', 'label' => 'Features (one per line)'],
                    ['key' => 'highlighted', 'type' => 'toggle', 'label' => 'Highlight'],
                    ['key' => 'ctaText', 'type' => 'text', 'label' => 'Button Text'],
                    ['key' => 'ctaUrl', 'type' => 'text', 'label' => 'Button URL'],
                ]],
            ],
        ];
    }

    private static function contactForm(): array {
        return [
            'label' => 'Contact Form',
            'icon' => 'mail',
            'defaults' => [
                'heading' => 'Get In Touch',
                'fields' => [
                    ['name' => 'name', 'label' => 'Your Name', 'type' => 'text', 'required' => true],
                    ['name' => 'email', 'label' => 'Email Address', 'type' => 'email', 'required' => true],
                    ['name' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => false],
                    ['name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true],
                ],
                'submitText' => 'Send Message',
                'successMessage' => 'Thank you! We will get back to you soon.',
            ],
            'schema' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ['key' => 'fields', 'type' => 'repeater', 'label' => 'Form Fields', 'fields' => [
                    ['key' => 'name', 'type' => 'text', 'label' => 'Field Name'],
                    ['key' => 'label', 'type' => 'text', 'label' => 'Label'],
                    ['key' => 'type', 'type' => 'select', 'label' => 'Type', 'options' => ['text','email','tel','textarea']],
                    ['key' => 'required', 'type' => 'toggle', 'label' => 'Required'],
                ]],
                ['key' => 'submitText', 'type' => 'text', 'label' => 'Submit Button Text'],
                ['key' => 'successMessage', 'type' => 'text', 'label' => 'Success Message'],
            ],
        ];
    }

    private static function map(): array {
        return [
            'label' => 'Map',
            'icon' => 'map-pin',
            'defaults' => [
                'address' => '1600 Amphitheatre Parkway, Mountain View, CA',
                'height' => 400,
            ],
            'schema' => [
                ['key' => 'address', 'type' => 'text', 'label' => 'Address'],
                ['key' => 'height', 'type' => 'number', 'label' => 'Height (px)'],
            ],
        ];
    }

    private static function gallery(): array {
        return [
            'label' => 'Gallery',
            'icon' => 'layout',
            'defaults' => [
                'columns' => 3,
                'gap' => 8,
                'images' => [],
            ],
            'schema' => [
                ['key' => 'columns', 'type' => 'select', 'label' => 'Columns', 'options' => [2, 3, 4]],
                ['key' => 'gap', 'type' => 'number', 'label' => 'Gap (px)'],
                ['key' => 'images', 'type' => 'repeater', 'label' => 'Images', 'fields' => [
                    ['key' => 'src', 'type' => 'image', 'label' => 'Image'],
                    ['key' => 'alt', 'type' => 'text', 'label' => 'Alt Text'],
                ]],
            ],
        ];
    }

    private static function navbar(): array {
        return [
            'label' => 'Navbar',
            'icon' => 'menu',
            'defaults' => [
                'logo' => '',
                'logoText' => 'My Site',
                'backgroundColor' => '#ffffff',
                'textColor' => '#111827',
                'sticky' => true,
            ],
            'schema' => [
                ['key' => 'logo', 'type' => 'image', 'label' => 'Logo Image'],
                ['key' => 'logoText', 'type' => 'text', 'label' => 'Logo Text (fallback)'],
                ['key' => 'backgroundColor', 'type' => 'color', 'label' => 'Background Color'],
                ['key' => 'textColor', 'type' => 'color', 'label' => 'Text Color'],
                ['key' => 'sticky', 'type' => 'toggle', 'label' => 'Sticky Header'],
            ],
        ];
    }

    private static function footer(): array {
        return [
            'label' => 'Footer',
            'icon' => 'minus-square',
            'defaults' => [
                'text' => '© 2026 My Site. All rights reserved.',
                'backgroundColor' => '#111827',
                'textColor' => '#9ca3af',
                'links' => [
                    ['label' => 'Privacy Policy', 'url' => '#'],
                    ['label' => 'Terms of Service', 'url' => '#'],
                ],
                'socialLinks' => [
                    ['platform' => 'facebook', 'url' => '#'],
                    ['platform' => 'twitter', 'url' => '#'],
                    ['platform' => 'instagram', 'url' => '#'],
                ],
            ],
            'schema' => [
                ['key' => 'text', 'type' => 'text', 'label' => 'Copyright Text'],
                ['key' => 'backgroundColor', 'type' => 'color', 'label' => 'Background Color'],
                ['key' => 'textColor', 'type' => 'color', 'label' => 'Text Color'],
                ['key' => 'links', 'type' => 'repeater', 'label' => 'Links', 'fields' => [
                    ['key' => 'label', 'type' => 'text', 'label' => 'Label'],
                    ['key' => 'url', 'type' => 'text', 'label' => 'URL'],
                ]],
                ['key' => 'socialLinks', 'type' => 'repeater', 'label' => 'Social Links', 'fields' => [
                    ['key' => 'platform', 'type' => 'select', 'label' => 'Platform', 'options' => ['facebook','twitter','instagram','linkedin','youtube']],
                    ['key' => 'url', 'type' => 'text', 'label' => 'URL'],
                ]],
            ],
        ];
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/ComponentRegistry.php
git commit -m "feat: ComponentRegistry with all component type definitions"
```

---

### Task 4: MediaManager — File Upload & Management

**Files:**
- Create: `src/MediaManager.php`

- [ ] **Step 1: Create src/MediaManager.php**

```php
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
```

- [ ] **Step 2: Commit**

```bash
git add src/MediaManager.php
git commit -m "feat: MediaManager for file upload, listing, and deletion"
```

---

### Task 5: Publisher — Render JSON to Static HTML

**Files:**
- Create: `src/Publisher.php`

- [ ] **Step 1: Create src/Publisher.php**

```php
<?php
class Publisher {
    private PDO $pdo;
    private SiteManager $siteManager;

    public function __construct(PDO $pdo, SiteManager $siteManager) {
        $this->pdo = $pdo;
        $this->siteManager = $siteManager;
    }

    public function publish(int $siteId): string {
        $site = $this->siteManager->getSite($siteId);
        if (!$site) throw new RuntimeException('Site not found');

        $pages = $this->siteManager->listPages($siteId);
        if (empty($pages)) throw new RuntimeException('Site has no pages');

        $settings = json_decode($site['settings'], true) ?: [];
        $outputDir = PUBLISHED_DIR . '/' . $site['slug'];

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Build navigation from pages
        $navItems = [];
        foreach ($pages as $p) {
            $navItems[] = [
                'name' => $p['name'],
                'slug' => $p['slug'],
                'href' => $p['sort_order'] === 0 ? 'index.html' : $p['slug'] . '.html',
            ];
        }

        foreach ($pages as $index => $page) {
            $components = json_decode($page['components'], true) ?: [];
            $seo = json_decode($page['seo'], true) ?: [];
            $filename = $index === 0 ? 'index.html' : $page['slug'] . '.html';

            $bodyHtml = '';
            foreach ($components as $component) {
                $bodyHtml .= $this->renderComponent($component, $navItems, $settings) . "\n";
            }

            $html = $this->wrapHtml($bodyHtml, $seo, $settings, $site['name']);
            file_put_contents($outputDir . '/' . $filename, $html);
        }

        return $site['slug'];
    }

    private function wrapHtml(string $body, array $seo, array $settings, string $siteName): string {
        $title = htmlspecialchars($seo['title'] ?? $siteName, ENT_QUOTES);
        $description = htmlspecialchars($seo['description'] ?? '', ENT_QUOTES);
        $keywords = htmlspecialchars($seo['keywords'] ?? '', ENT_QUOTES);
        $favicon = htmlspecialchars($settings['favicon'] ?? '', ENT_QUOTES);
        $faviconTag = $favicon ? "<link rel=\"icon\" href=\"{$favicon}\">" : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <meta name="description" content="{$description}">
    <meta name="keywords" content="{$keywords}">
    {$faviconTag}
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; }
        .lightbox-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:9999; justify-content:center; align-items:center; cursor:pointer; }
        .lightbox-overlay.active { display:flex; }
        .lightbox-overlay img { max-width:90vw; max-height:90vh; object-fit:contain; }
    </style>
</head>
<body class="bg-white text-gray-900">
{$body}
<div class="lightbox-overlay" onclick="this.classList.remove('active')"><img src="" alt=""></div>
<script>
document.querySelectorAll('[data-lightbox]').forEach(function(img){
    img.style.cursor='pointer';
    img.addEventListener('click',function(){
        var overlay=document.querySelector('.lightbox-overlay');
        overlay.querySelector('img').src=this.src;
        overlay.classList.add('active');
    });
});
</script>
</body>
</html>
HTML;
    }

    public function renderComponent(array $component, array $navItems = [], array $siteSettings = []): string {
        $type = $component['type'];
        $props = $component['props'] ?? [];
        $method = 'render' . str_replace('_', '', ucwords($type, '_'));
        if (method_exists($this, $method)) {
            return $this->$method($props, $navItems, $siteSettings);
        }
        return "<!-- Unknown component: {$type} -->";
    }

    private function renderNavbar(array $p, array $navItems): string {
        $bg = htmlspecialchars($p['backgroundColor'] ?? '#ffffff');
        $tc = htmlspecialchars($p['textColor'] ?? '#111827');
        $logoText = htmlspecialchars($p['logoText'] ?? 'My Site');
        $logo = $p['logo'] ?? '';
        $sticky = ($p['sticky'] ?? true) ? 'sticky top-0 z-50' : '';
        $logoHtml = $logo
            ? '<img src="' . htmlspecialchars($logo) . '" alt="' . $logoText . '" class="h-8">'
            : '<span class="text-xl font-bold">' . $logoText . '</span>';
        $links = '';
        foreach ($navItems as $item) {
            $links .= '<a href="' . htmlspecialchars($item['href']) . '" class="hover:opacity-75">' . htmlspecialchars($item['name']) . '</a> ';
        }
        return <<<HTML
<nav class="{$sticky} shadow-sm" style="background-color:{$bg};color:{$tc}">
    <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
        <div>{$logoHtml}</div>
        <div class="flex gap-6 text-sm font-medium">{$links}</div>
    </div>
</nav>
HTML;
    }

    private function renderHero(array $p): string {
        $heading = htmlspecialchars($p['heading'] ?? '');
        $sub = htmlspecialchars($p['subheading'] ?? '');
        $ctaText = htmlspecialchars($p['ctaText'] ?? '');
        $ctaUrl = htmlspecialchars($p['ctaUrl'] ?? '#');
        $bg = htmlspecialchars($p['backgroundColor'] ?? '#1e3a5f');
        $tc = htmlspecialchars($p['textColor'] ?? '#ffffff');
        $bgImg = $p['backgroundImage'] ?? '';
        $overlay = ($p['overlay'] ?? true) ? '<div class="absolute inset-0 bg-black/50"></div>' : '';
        $bgStyle = $bgImg ? "background-image:url('" . htmlspecialchars($bgImg) . "');background-size:cover;background-position:center;" : "background-color:{$bg};";
        $cta = $ctaText ? '<a href="' . $ctaUrl . '" class="inline-block mt-6 px-8 py-3 bg-white text-gray-900 font-semibold rounded-lg hover:bg-gray-100 transition">' . $ctaText . '</a>' : '';
        return <<<HTML
<section class="relative min-h-[500px] flex items-center justify-center text-center" style="{$bgStyle}color:{$tc}">
    {$overlay}
    <div class="relative z-10 max-w-3xl mx-auto px-4">
        <h1 class="text-5xl font-bold mb-4">{$heading}</h1>
        <p class="text-xl opacity-90">{$sub}</p>
        {$cta}
    </div>
</section>
HTML;
    }

    private function renderHeading(array $p): string {
        $text = htmlspecialchars($p['text'] ?? '');
        $level = $p['level'] ?? 'h2';
        $align = $p['alignment'] ?? 'left';
        $color = htmlspecialchars($p['color'] ?? '#111827');
        $sizes = ['h1'=>'text-4xl','h2'=>'text-3xl','h3'=>'text-2xl','h4'=>'text-xl','h5'=>'text-lg','h6'=>'text-base'];
        $size = $sizes[$level] ?? 'text-3xl';
        return "<div class=\"max-w-6xl mx-auto px-4 py-4 text-{$align}\"><{$level} class=\"{$size} font-bold\" style=\"color:{$color}\">{$text}</{$level}></div>";
    }

    private function renderText(array $p): string {
        $content = $p['content'] ?? '';
        $align = $p['alignment'] ?? 'left';
        return "<div class=\"max-w-6xl mx-auto px-4 py-4 text-{$align} prose prose-lg\">{$content}</div>";
    }

    private function renderImage(array $p): string {
        $src = htmlspecialchars($p['src'] ?? '');
        $alt = htmlspecialchars($p['alt'] ?? '');
        $link = $p['link'] ?? '';
        $widths = ['small'=>'max-w-sm','medium'=>'max-w-lg','large'=>'max-w-4xl','full'=>'max-w-full'];
        $w = $widths[$p['width'] ?? 'full'] ?? 'max-w-full';
        $img = "<img src=\"{$src}\" alt=\"{$alt}\" class=\"w-full h-auto rounded-lg\">";
        if ($link) $img = '<a href="' . htmlspecialchars($link) . '">' . $img . '</a>';
        return "<div class=\"{$w} mx-auto px-4 py-4\">{$img}</div>";
    }

    private function renderVideo(array $p): string {
        $url = $p['url'] ?? '';
        $embedUrl = $this->getEmbedUrl($url);
        $ratios = ['16:9'=>'aspect-video','4:3'=>'aspect-[4/3]','1:1'=>'aspect-square'];
        $ratio = $ratios[$p['aspectRatio'] ?? '16:9'] ?? 'aspect-video';
        return "<div class=\"max-w-4xl mx-auto px-4 py-4\"><div class=\"{$ratio}\"><iframe src=\"{$embedUrl}\" class=\"w-full h-full rounded-lg\" frameborder=\"0\" allowfullscreen></iframe></div></div>";
    }

    private function getEmbedUrl(string $url): string {
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $m)) {
            return 'https://www.youtube.com/embed/' . htmlspecialchars($m[1]);
        }
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
            return 'https://player.vimeo.com/video/' . htmlspecialchars($m[1]);
        }
        return htmlspecialchars($url);
    }

    private function renderButton(array $p): string {
        $text = htmlspecialchars($p['text'] ?? 'Click');
        $link = htmlspecialchars($p['link'] ?? '#');
        $color = htmlspecialchars($p['color'] ?? '#3b82f6');
        $align = $p['alignment'] ?? 'left';
        $style = $p['style'] ?? 'solid';
        $btnClass = $style === 'outline'
            ? "border-2 bg-transparent hover:opacity-75"
            : "text-white hover:opacity-90";
        $btnStyle = $style === 'outline'
            ? "border-color:{$color};color:{$color}"
            : "background-color:{$color}";
        return "<div class=\"max-w-6xl mx-auto px-4 py-4 text-{$align}\"><a href=\"{$link}\" class=\"inline-block px-6 py-3 rounded-lg font-semibold transition {$btnClass}\" style=\"{$btnStyle}\">{$text}</a></div>";
    }

    private function renderSection(array $p): string {
        $bg = htmlspecialchars($p['backgroundColor'] ?? '#ffffff');
        $bgImg = $p['backgroundImage'] ?? '';
        $pt = (int)($p['paddingTop'] ?? 60);
        $pb = (int)($p['paddingBottom'] ?? 60);
        $style = "padding-top:{$pt}px;padding-bottom:{$pb}px;background-color:{$bg};";
        if ($bgImg) $style .= "background-image:url('" . htmlspecialchars($bgImg) . "');background-size:cover;background-position:center;";
        $childHtml = '';
        foreach (($p['children'] ?? []) as $child) {
            $childHtml .= $this->renderComponent($child);
        }
        return "<section style=\"{$style}\">{$childHtml}</section>";
    }

    private function renderColumns(array $p): string {
        $count = (int)($p['count'] ?? 2);
        $gap = (int)($p['gap'] ?? 24);
        $gridCols = ['2'=>'grid-cols-2','3'=>'grid-cols-3','4'=>'grid-cols-4'];
        $grid = $gridCols[(string)$count] ?? 'grid-cols-2';
        $childHtml = '';
        foreach (($p['children'] ?? []) as $child) {
            $childHtml .= '<div>' . $this->renderComponent($child) . '</div>';
        }
        return "<div class=\"max-w-6xl mx-auto px-4 py-4 grid {$grid}\" style=\"gap:{$gap}px\">{$childHtml}</div>";
    }

    private function renderSpacer(array $p): string {
        $h = (int)($p['height'] ?? 40);
        return "<div style=\"height:{$h}px\"></div>";
    }

    private function renderFeatures(array $p): string {
        $heading = htmlspecialchars($p['heading'] ?? '');
        $cols = (int)($p['columns'] ?? 3);
        $gridCols = ['3'=>'md:grid-cols-3','4'=>'md:grid-cols-4'];
        $grid = $gridCols[(string)$cols] ?? 'md:grid-cols-3';
        $items = '';
        foreach (($p['items'] ?? []) as $item) {
            $icon = htmlspecialchars($item['icon'] ?? '');
            $title = htmlspecialchars($item['title'] ?? '');
            $desc = htmlspecialchars($item['description'] ?? '');
            $items .= "<div class=\"text-center p-6\"><div class=\"text-4xl mb-4\">{$icon}</div><h3 class=\"text-xl font-semibold mb-2\">{$title}</h3><p class=\"text-gray-600\">{$desc}</p></div>";
        }
        return "<section class=\"py-16 bg-gray-50\"><div class=\"max-w-6xl mx-auto px-4\"><h2 class=\"text-3xl font-bold text-center mb-12\">{$heading}</h2><div class=\"grid {$grid} gap-8\">{$items}</div></div></section>";
    }

    private function renderTestimonials(array $p): string {
        $heading = htmlspecialchars($p['heading'] ?? '');
        $items = '';
        foreach (($p['items'] ?? []) as $item) {
            $quote = htmlspecialchars($item['quote'] ?? '');
            $name = htmlspecialchars($item['name'] ?? '');
            $role = htmlspecialchars($item['role'] ?? '');
            $photo = $item['photo'] ?? '';
            $photoHtml = $photo
                ? '<img src="' . htmlspecialchars($photo) . '" class="w-12 h-12 rounded-full object-cover" alt="' . $name . '">'
                : '<div class="w-12 h-12 rounded-full bg-gray-300 flex items-center justify-center text-gray-600 font-bold">' . mb_substr($name,0,1) . '</div>';
            $items .= "<div class=\"bg-white p-6 rounded-xl shadow-sm\"><p class=\"text-gray-600 italic mb-4\">\"{$quote}\"</p><div class=\"flex items-center gap-3\">{$photoHtml}<div><div class=\"font-semibold\">{$name}</div><div class=\"text-sm text-gray-500\">{$role}</div></div></div></div>";
        }
        return "<section class=\"py-16\"><div class=\"max-w-6xl mx-auto px-4\"><h2 class=\"text-3xl font-bold text-center mb-12\">{$heading}</h2><div class=\"grid md:grid-cols-2 gap-8\">{$items}</div></div></section>";
    }

    private function renderPricing(array $p): string {
        $heading = htmlspecialchars($p['heading'] ?? '');
        $plans = '';
        foreach (($p['plans'] ?? []) as $plan) {
            $name = htmlspecialchars($plan['name'] ?? '');
            $price = htmlspecialchars($plan['price'] ?? '');
            $ctaText = htmlspecialchars($plan['ctaText'] ?? 'Choose');
            $ctaUrl = htmlspecialchars($plan['ctaUrl'] ?? '#');
            $hl = ($plan['highlighted'] ?? false);
            $border = $hl ? 'border-blue-500 border-2 scale-105' : 'border-gray-200 border';
            $btn = $hl ? 'bg-blue-500 text-white' : 'bg-gray-900 text-white';
            $features = is_array($plan['features'] ?? null) ? $plan['features'] : explode("\n", $plan['features'] ?? '');
            $featureHtml = '';
            foreach ($features as $f) {
                $f = trim(htmlspecialchars($f));
                if ($f) $featureHtml .= "<li class=\"py-2 border-b border-gray-100\">{$f}</li>";
            }
            $plans .= "<div class=\"{$border} rounded-2xl p-8 bg-white\"><h3 class=\"text-xl font-bold mb-2\">{$name}</h3><div class=\"text-3xl font-bold mb-6\">{$price}</div><ul class=\"mb-8 text-gray-600\">{$featureHtml}</ul><a href=\"{$ctaUrl}\" class=\"block text-center py-3 rounded-lg font-semibold {$btn} hover:opacity-90 transition\">{$ctaText}</a></div>";
        }
        return "<section class=\"py-16 bg-gray-50\"><div class=\"max-w-5xl mx-auto px-4\"><h2 class=\"text-3xl font-bold text-center mb-12\">{$heading}</h2><div class=\"grid md:grid-cols-3 gap-8 items-start\">{$plans}</div></div></section>";
    }

    private function renderContactForm(array $p, array $navItems = [], array $siteSettings = []): string {
        $heading = htmlspecialchars($p['heading'] ?? '');
        $submitText = htmlspecialchars($p['submitText'] ?? 'Send');
        $successMsg = htmlspecialchars($p['successMessage'] ?? 'Thank you!');
        $fields = '';
        foreach (($p['fields'] ?? []) as $field) {
            $fname = htmlspecialchars($field['name'] ?? '');
            $flabel = htmlspecialchars($field['label'] ?? '');
            $ftype = $field['type'] ?? 'text';
            $req = ($field['required'] ?? false) ? 'required' : '';
            if ($ftype === 'textarea') {
                $fields .= "<div class=\"mb-4\"><label class=\"block text-sm font-medium mb-1\">{$flabel}</label><textarea name=\"{$fname}\" rows=\"4\" class=\"w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent\" {$req}></textarea></div>";
            } else {
                $fields .= "<div class=\"mb-4\"><label class=\"block text-sm font-medium mb-1\">{$flabel}</label><input type=\"{$ftype}\" name=\"{$fname}\" class=\"w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent\" {$req}></div>";
            }
        }
        return <<<HTML
<section class="py-16">
    <div class="max-w-xl mx-auto px-4">
        <h2 class="text-3xl font-bold text-center mb-8">{$heading}</h2>
        <form class="contact-form" onsubmit="return handleFormSubmit(this, '{$successMsg}')">
            {$fields}
            <button type="submit" class="w-full bg-blue-500 text-white py-3 rounded-lg font-semibold hover:bg-blue-600 transition">{$submitText}</button>
            <div class="form-success hidden mt-4 p-4 bg-green-50 text-green-700 rounded-lg text-center"></div>
        </form>
    </div>
</section>
<script>
function handleFormSubmit(form, msg) {
    var data = new FormData(form);
    fetch(form.action || window.location.origin + '/api.php?action=form_submit', {
        method: 'POST', body: data
    }).then(function(){
        var el = form.querySelector('.form-success');
        el.textContent = msg;
        el.classList.remove('hidden');
        form.reset();
    });
    return false;
}
</script>
HTML;
    }

    private function renderMap(array $p): string {
        $address = urlencode($p['address'] ?? '');
        $h = (int)($p['height'] ?? 400);
        return "<div class=\"max-w-6xl mx-auto px-4 py-4\"><iframe src=\"https://maps.google.com/maps?q={$address}&output=embed\" width=\"100%\" height=\"{$h}\" style=\"border:0;border-radius:0.75rem\" allowfullscreen loading=\"lazy\"></iframe></div>";
    }

    private function renderGallery(array $p): string {
        $cols = (int)($p['columns'] ?? 3);
        $gap = (int)($p['gap'] ?? 8);
        $gridCols = ['2'=>'grid-cols-2','3'=>'grid-cols-3','4'=>'grid-cols-4'];
        $grid = $gridCols[(string)$cols] ?? 'grid-cols-3';
        $images = '';
        foreach (($p['images'] ?? []) as $img) {
            $src = htmlspecialchars($img['src'] ?? '');
            $alt = htmlspecialchars($img['alt'] ?? '');
            $images .= "<img src=\"{$src}\" alt=\"{$alt}\" class=\"w-full h-64 object-cover rounded-lg\" data-lightbox>";
        }
        return "<div class=\"max-w-6xl mx-auto px-4 py-4 grid {$grid}\" style=\"gap:{$gap}px\">{$images}</div>";
    }

    private function renderFooter(array $p): string {
        $text = htmlspecialchars($p['text'] ?? '');
        $bg = htmlspecialchars($p['backgroundColor'] ?? '#111827');
        $tc = htmlspecialchars($p['textColor'] ?? '#9ca3af');
        $links = '';
        foreach (($p['links'] ?? []) as $link) {
            $links .= '<a href="' . htmlspecialchars($link['url'] ?? '#') . '" class="hover:underline">' . htmlspecialchars($link['label'] ?? '') . '</a> ';
        }
        $socials = '';
        $socialIcons = ['facebook'=>'FB','twitter'=>'X','instagram'=>'IG','linkedin'=>'LI','youtube'=>'YT'];
        foreach (($p['socialLinks'] ?? []) as $sl) {
            $platform = $sl['platform'] ?? '';
            $icon = $socialIcons[$platform] ?? strtoupper(substr($platform,0,2));
            $socials .= '<a href="' . htmlspecialchars($sl['url'] ?? '#') . '" class="hover:opacity-75">' . $icon . '</a> ';
        }
        return <<<HTML
<footer style="background-color:{$bg};color:{$tc}">
    <div class="max-w-6xl mx-auto px-4 py-12">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="text-sm">{$text}</div>
            <div class="flex gap-4 text-sm">{$links}</div>
            <div class="flex gap-3 font-bold">{$socials}</div>
        </div>
    </div>
</footer>
HTML;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Publisher.php
git commit -m "feat: Publisher renders site JSON to static HTML with all component types"
```

---

### Task 6: FormHandler + TemplateEngine

**Files:**
- Create: `src/FormHandler.php`
- Create: `src/TemplateEngine.php`

- [ ] **Step 1: Create src/FormHandler.php**

```php
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

        // Send email notification
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
```

- [ ] **Step 2: Create src/TemplateEngine.php**

```php
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
```

- [ ] **Step 3: Commit**

```bash
git add src/FormHandler.php src/TemplateEngine.php
git commit -m "feat: FormHandler for contact submissions and TemplateEngine for template loading"
```

---

### Task 7: API Router

**Files:**
- Create: `public/api.php`

- [ ] **Step 1: Create public/api.php**

```php
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
        // --- Sites ---
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
                // Create a default home page
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

        // --- Pages ---
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

        // --- Media ---
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

        // --- Publishing ---
        case 'publish':
            $siteId = (int)($_GET['site_id'] ?? 0);
            if (!$siteId) error('Missing site_id');
            $slug = $pub->publish($siteId);
            respond(['success' => true, 'url' => '/published/' . $slug . '/index.html']);

        // --- Forms ---
        case 'form_submit':
            $siteId = (int)($_POST['site_id'] ?? $_GET['site_id'] ?? 0);
            $pageId = (int)($_POST['page_id'] ?? $_GET['page_id'] ?? 0);
            $data = $_POST;
            unset($data['site_id'], $data['page_id']);
            $fh->submit($siteId, $pageId, $data);
            respond(['success' => true]);

        // --- Templates ---
        case 'templates_list':
            respond($te->listTemplates());

        // --- Components ---
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
```

- [ ] **Step 2: Commit**

```bash
git add public/api.php
git commit -m "feat: API router with all endpoints for sites, pages, media, publishing, forms, templates"
```

---

### Task 8: Pre-Built Templates (JSON)

**Files:**
- Create: `templates/business-landing.json`
- Create: `templates/portfolio.json`
- Create: `templates/restaurant.json`

- [ ] **Step 1: Create templates/business-landing.json**

```json
{
    "name": "Business Landing Page",
    "slug": "business-site",
    "description": "Professional landing page with hero, features, testimonials, pricing, and contact form.",
    "thumbnail": "",
    "settings": {
        "primaryColor": "#3b82f6",
        "fontFamily": "system-ui"
    },
    "pages": [
        {
            "name": "Home",
            "slug": "home",
            "seo": {
                "title": "Business Landing Page",
                "description": "Professional business website"
            },
            "components": [
                {
                    "id": "nav1",
                    "type": "navbar",
                    "props": {
                        "logo": "",
                        "logoText": "BusinessCo",
                        "backgroundColor": "#ffffff",
                        "textColor": "#111827",
                        "sticky": true
                    }
                },
                {
                    "id": "hero1",
                    "type": "hero",
                    "props": {
                        "heading": "Grow Your Business With Us",
                        "subheading": "We provide innovative solutions to help your business thrive in the digital age.",
                        "ctaText": "Get Started",
                        "ctaUrl": "#contact",
                        "backgroundImage": "",
                        "backgroundColor": "#1e40af",
                        "textColor": "#ffffff",
                        "overlay": false
                    }
                },
                {
                    "id": "feat1",
                    "type": "features",
                    "props": {
                        "heading": "Why Choose Us",
                        "columns": 3,
                        "items": [
                            {"icon": "⚡", "title": "Lightning Fast", "description": "Our solutions are optimized for speed and performance."},
                            {"icon": "🔒", "title": "Secure & Reliable", "description": "Enterprise-grade security to protect your data."},
                            {"icon": "📱", "title": "Mobile First", "description": "Fully responsive designs that work on any device."}
                        ]
                    }
                },
                {
                    "id": "test1",
                    "type": "testimonials",
                    "props": {
                        "heading": "What Our Clients Say",
                        "items": [
                            {"quote": "The best decision we made for our company. Results exceeded all expectations.", "name": "Sarah Johnson", "role": "CEO, TechStart", "photo": ""},
                            {"quote": "Professional, reliable, and innovative. Highly recommended for any business.", "name": "Michael Chen", "role": "Founder, DesignHub", "photo": ""}
                        ]
                    }
                },
                {
                    "id": "price1",
                    "type": "pricing",
                    "props": {
                        "heading": "Simple Pricing",
                        "plans": [
                            {"name": "Starter", "price": "$29/mo", "features": ["5 Projects", "Basic Analytics", "Email Support"], "highlighted": false, "ctaText": "Start Free", "ctaUrl": "#"},
                            {"name": "Professional", "price": "$79/mo", "features": ["Unlimited Projects", "Advanced Analytics", "Priority Support", "Custom Domain"], "highlighted": true, "ctaText": "Get Started", "ctaUrl": "#"},
                            {"name": "Enterprise", "price": "$199/mo", "features": ["Everything in Pro", "Dedicated Manager", "SLA Guarantee", "Custom Integration"], "highlighted": false, "ctaText": "Contact Us", "ctaUrl": "#contact"}
                        ]
                    }
                },
                {
                    "id": "form1",
                    "type": "contact_form",
                    "props": {
                        "heading": "Get In Touch",
                        "fields": [
                            {"name": "name", "label": "Full Name", "type": "text", "required": true},
                            {"name": "email", "label": "Email Address", "type": "email", "required": true},
                            {"name": "phone", "label": "Phone Number", "type": "tel", "required": false},
                            {"name": "message", "label": "How can we help?", "type": "textarea", "required": true}
                        ],
                        "submitText": "Send Message",
                        "successMessage": "Thanks! We'll get back to you within 24 hours."
                    }
                },
                {
                    "id": "foot1",
                    "type": "footer",
                    "props": {
                        "text": "\u00a9 2026 BusinessCo. All rights reserved.",
                        "backgroundColor": "#111827",
                        "textColor": "#9ca3af",
                        "links": [
                            {"label": "Privacy Policy", "url": "#"},
                            {"label": "Terms of Service", "url": "#"}
                        ],
                        "socialLinks": [
                            {"platform": "facebook", "url": "#"},
                            {"platform": "twitter", "url": "#"},
                            {"platform": "linkedin", "url": "#"}
                        ]
                    }
                }
            ]
        }
    ]
}
```

- [ ] **Step 2: Create templates/portfolio.json**

```json
{
    "name": "Portfolio",
    "slug": "portfolio",
    "description": "Minimal portfolio with gallery, about section, and contact form.",
    "thumbnail": "",
    "settings": {
        "primaryColor": "#000000",
        "accentColor": "#6366f1"
    },
    "pages": [
        {
            "name": "Home",
            "slug": "home",
            "seo": {
                "title": "Portfolio",
                "description": "Creative portfolio showcase"
            },
            "components": [
                {
                    "id": "nav1",
                    "type": "navbar",
                    "props": {
                        "logo": "",
                        "logoText": "Jane Doe",
                        "backgroundColor": "#000000",
                        "textColor": "#ffffff",
                        "sticky": true
                    }
                },
                {
                    "id": "hero1",
                    "type": "hero",
                    "props": {
                        "heading": "Creative Designer & Developer",
                        "subheading": "Crafting beautiful digital experiences",
                        "ctaText": "View My Work",
                        "ctaUrl": "#gallery",
                        "backgroundImage": "",
                        "backgroundColor": "#000000",
                        "textColor": "#ffffff",
                        "overlay": false
                    }
                },
                {
                    "id": "head1",
                    "type": "heading",
                    "props": {
                        "text": "Selected Work",
                        "level": "h2",
                        "alignment": "center",
                        "color": "#111827"
                    }
                },
                {
                    "id": "spacer1",
                    "type": "spacer",
                    "props": {"height": 20}
                },
                {
                    "id": "gal1",
                    "type": "gallery",
                    "props": {
                        "columns": 3,
                        "gap": 8,
                        "images": []
                    }
                },
                {
                    "id": "about1",
                    "type": "text",
                    "props": {
                        "content": "<h2>About Me</h2><p>I'm a creative designer and developer with over 10 years of experience building beautiful, functional websites and applications. I specialize in clean, modern design with a focus on user experience.</p><p>When I'm not designing, you can find me exploring new technologies, reading, or hiking in the mountains.</p>",
                        "alignment": "center"
                    }
                },
                {
                    "id": "form1",
                    "type": "contact_form",
                    "props": {
                        "heading": "Let's Work Together",
                        "fields": [
                            {"name": "name", "label": "Your Name", "type": "text", "required": true},
                            {"name": "email", "label": "Email", "type": "email", "required": true},
                            {"name": "message", "label": "Tell me about your project", "type": "textarea", "required": true}
                        ],
                        "submitText": "Send Message",
                        "successMessage": "Thanks for reaching out! I'll get back to you soon."
                    }
                },
                {
                    "id": "foot1",
                    "type": "footer",
                    "props": {
                        "text": "\u00a9 2026 Jane Doe. All rights reserved.",
                        "backgroundColor": "#000000",
                        "textColor": "#9ca3af",
                        "links": [],
                        "socialLinks": [
                            {"platform": "instagram", "url": "#"},
                            {"platform": "twitter", "url": "#"},
                            {"platform": "linkedin", "url": "#"}
                        ]
                    }
                }
            ]
        }
    ]
}
```

- [ ] **Step 3: Create templates/restaurant.json**

```json
{
    "name": "Restaurant & Local Business",
    "slug": "restaurant",
    "description": "Warm, inviting layout with menu, map, hours, and gallery.",
    "thumbnail": "",
    "settings": {
        "primaryColor": "#92400e",
        "fontFamily": "Georgia, serif"
    },
    "pages": [
        {
            "name": "Home",
            "slug": "home",
            "seo": {
                "title": "The Golden Table - Restaurant",
                "description": "Fine dining experience with locally sourced ingredients"
            },
            "components": [
                {
                    "id": "nav1",
                    "type": "navbar",
                    "props": {
                        "logo": "",
                        "logoText": "The Golden Table",
                        "backgroundColor": "#1c1917",
                        "textColor": "#fbbf24",
                        "sticky": true
                    }
                },
                {
                    "id": "hero1",
                    "type": "hero",
                    "props": {
                        "heading": "A Taste of Excellence",
                        "subheading": "Farm-to-table dining in the heart of the city",
                        "ctaText": "Reserve a Table",
                        "ctaUrl": "#contact",
                        "backgroundImage": "",
                        "backgroundColor": "#292524",
                        "textColor": "#fef3c7",
                        "overlay": true
                    }
                },
                {
                    "id": "feat1",
                    "type": "features",
                    "props": {
                        "heading": "Our Specialties",
                        "columns": 3,
                        "items": [
                            {"icon": "🥩", "title": "Prime Steaks", "description": "Dry-aged for 28 days, cooked to perfection over open flame."},
                            {"icon": "🐟", "title": "Fresh Seafood", "description": "Daily catch from local fishermen, prepared with care."},
                            {"icon": "🍷", "title": "Fine Wines", "description": "Curated selection of over 200 wines from around the world."}
                        ]
                    }
                },
                {
                    "id": "gal1",
                    "type": "gallery",
                    "props": {
                        "columns": 3,
                        "gap": 4,
                        "images": []
                    }
                },
                {
                    "id": "head1",
                    "type": "heading",
                    "props": {
                        "text": "Visit Us",
                        "level": "h2",
                        "alignment": "center",
                        "color": "#292524"
                    }
                },
                {
                    "id": "text1",
                    "type": "text",
                    "props": {
                        "content": "<p><strong>Hours:</strong></p><p>Monday - Thursday: 5:00 PM - 10:00 PM</p><p>Friday - Saturday: 5:00 PM - 11:00 PM</p><p>Sunday: 4:00 PM - 9:00 PM</p><br><p><strong>Address:</strong></p><p>123 Main Street, Downtown</p><p>Phone: (555) 123-4567</p>",
                        "alignment": "center"
                    }
                },
                {
                    "id": "map1",
                    "type": "map",
                    "props": {
                        "address": "123 Main Street, Downtown",
                        "height": 350
                    }
                },
                {
                    "id": "form1",
                    "type": "contact_form",
                    "props": {
                        "heading": "Make a Reservation",
                        "fields": [
                            {"name": "name", "label": "Name", "type": "text", "required": true},
                            {"name": "email", "label": "Email", "type": "email", "required": true},
                            {"name": "phone", "label": "Phone", "type": "tel", "required": true},
                            {"name": "message", "label": "Party size & preferred date/time", "type": "textarea", "required": true}
                        ],
                        "submitText": "Request Reservation",
                        "successMessage": "Thank you! We'll confirm your reservation shortly."
                    }
                },
                {
                    "id": "foot1",
                    "type": "footer",
                    "props": {
                        "text": "\u00a9 2026 The Golden Table. All rights reserved.",
                        "backgroundColor": "#1c1917",
                        "textColor": "#a8a29e",
                        "links": [
                            {"label": "Privacy Policy", "url": "#"}
                        ],
                        "socialLinks": [
                            {"platform": "facebook", "url": "#"},
                            {"platform": "instagram", "url": "#"}
                        ]
                    }
                }
            ]
        }
    ]
}
```

- [ ] **Step 4: Commit**

```bash
git add templates/
git commit -m "feat: add three pre-built templates (business, portfolio, restaurant)"
```

---

### Task 9: Editor SPA Shell + API Client

**Files:**
- Create: `public/index.php`
- Create: `public/assets/js/api.js`
- Create: `public/assets/css/editor.css`

- [ ] **Step 1: Create public/assets/js/api.js**

```javascript
const API = {
    base: '/api.php',

    async request(action, options = {}) {
        const params = new URLSearchParams({ action, ...options.params });
        const url = `${this.base}?${params}`;
        const fetchOptions = { method: options.method || 'GET', headers: {} };

        if (options.body) {
            fetchOptions.headers['Content-Type'] = 'application/json';
            fetchOptions.body = JSON.stringify(options.body);
        }
        if (options.formData) {
            fetchOptions.body = options.formData;
        }

        const res = await fetch(url, fetchOptions);
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Request failed');
        return data;
    },

    // Sites
    listSites() { return this.request('sites_list'); },
    createSite(data) { return this.request('sites_create', { method: 'POST', body: data }); },
    updateSite(id, data) { return this.request('sites_update', { method: 'PUT', params: { id }, body: data }); },
    deleteSite(id) { return this.request('sites_delete', { method: 'DELETE', params: { id } }); },

    // Pages
    listPages(siteId) { return this.request('pages_list', { params: { site_id: siteId } }); },
    getPage(id) { return this.request('pages_get', { params: { id } }); },
    savePage(siteId, data, pageId = null) {
        const params = { site_id: siteId };
        if (pageId) params.id = pageId;
        return this.request('pages_save', { method: 'POST', params, body: data });
    },
    deletePage(id) { return this.request('pages_delete', { method: 'DELETE', params: { id } }); },
    reorderPages(siteId, pageIds) { return this.request('pages_reorder', { method: 'PUT', params: { site_id: siteId }, body: { pageIds } }); },

    // Media
    listMedia() { return this.request('media_list'); },
    uploadMedia(file) {
        const fd = new FormData();
        fd.append('file', file);
        return this.request('media_upload', { method: 'POST', formData: fd });
    },
    deleteMedia(id) { return this.request('media_delete', { method: 'DELETE', params: { id } }); },

    // Publish
    publish(siteId) { return this.request('publish', { method: 'POST', params: { site_id: siteId } }); },

    // Templates
    listTemplates() { return this.request('templates_list'); },

    // Components
    listComponents() { return this.request('components_list'); },
};
```

- [ ] **Step 2: Create public/assets/css/editor.css**

```css
* { margin: 0; padding: 0; box-sizing: border-box; }

body.editor-body {
    overflow: hidden;
    height: 100vh;
    font-family: system-ui, -apple-system, sans-serif;
}

.editor-layout {
    display: grid;
    grid-template-rows: 56px 1fr;
    grid-template-columns: 280px 1fr 320px;
    height: 100vh;
}

.top-bar {
    grid-column: 1 / -1;
    background: #1e293b;
    color: white;
    display: flex;
    align-items: center;
    padding: 0 16px;
    gap: 16px;
    z-index: 100;
}

.left-sidebar {
    background: #f8fafc;
    border-right: 1px solid #e2e8f0;
    overflow-y: auto;
}

.canvas-area {
    background: #e2e8f0;
    overflow-y: auto;
    display: flex;
    justify-content: center;
    padding: 24px;
}

.canvas-frame {
    background: white;
    box-shadow: 0 4px 24px rgba(0,0,0,0.1);
    width: 100%;
    max-width: 1200px;
    min-height: 600px;
    transition: max-width 0.3s ease;
    position: relative;
}

.canvas-frame.tablet { max-width: 768px; }
.canvas-frame.mobile { max-width: 375px; }

.right-sidebar {
    background: #ffffff;
    border-left: 1px solid #e2e8f0;
    overflow-y: auto;
    padding: 16px;
}

/* Component on canvas */
.canvas-component {
    position: relative;
    transition: outline 0.15s;
    outline: 2px solid transparent;
    outline-offset: -2px;
}

.canvas-component:hover {
    outline-color: #93c5fd;
}

.canvas-component.selected {
    outline-color: #3b82f6;
}

.canvas-component .component-toolbar {
    display: none;
    position: absolute;
    top: -36px;
    right: 4px;
    background: #3b82f6;
    border-radius: 6px;
    padding: 4px;
    gap: 2px;
    z-index: 10;
}

.canvas-component.selected .component-toolbar {
    display: flex;
}

.component-toolbar button {
    background: transparent;
    border: none;
    color: white;
    width: 28px;
    height: 28px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.component-toolbar button:hover {
    background: rgba(255,255,255,0.2);
}

/* Drop zone indicator */
.drop-zone {
    height: 4px;
    background: transparent;
    transition: all 0.2s;
    margin: 0 16px;
}

.drop-zone.active {
    height: 4px;
    background: #3b82f6;
    border-radius: 2px;
}

/* Left sidebar component items */
.component-item {
    padding: 10px 16px;
    cursor: grab;
    display: flex;
    align-items: center;
    gap: 10px;
    border-radius: 6px;
    margin: 2px 8px;
    font-size: 14px;
    color: #334155;
}

.component-item:hover {
    background: #e2e8f0;
}

.component-item:active {
    cursor: grabbing;
}

.sidebar-section {
    padding: 12px 16px 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #94a3b8;
}

/* Tab buttons */
.tab-bar {
    display: flex;
    border-bottom: 1px solid #e2e8f0;
}

.tab-btn {
    flex: 1;
    padding: 10px;
    border: none;
    background: transparent;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    color: #64748b;
    border-bottom: 2px solid transparent;
}

.tab-btn.active {
    color: #3b82f6;
    border-bottom-color: #3b82f6;
}

/* Property fields */
.prop-field {
    margin-bottom: 16px;
}

.prop-field label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 4px;
}

.prop-field input[type="text"],
.prop-field input[type="number"],
.prop-field textarea,
.prop-field select {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 14px;
    outline: none;
}

.prop-field input:focus,
.prop-field textarea:focus,
.prop-field select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}

.prop-field input[type="color"] {
    width: 40px;
    height: 32px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    cursor: pointer;
    padding: 2px;
}

/* Buttons */
.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.15s;
}

.btn:hover { opacity: 0.9; }

.btn-primary { background: #3b82f6; color: white; }
.btn-success { background: #22c55e; color: white; }
.btn-danger { background: #ef4444; color: white; }
.btn-secondary { background: #e2e8f0; color: #334155; }

.btn-sm { padding: 5px 10px; font-size: 12px; }

/* Modal overlay */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}

.modal-overlay.active { display: flex; }

.modal-content {
    background: white;
    border-radius: 12px;
    padding: 24px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

/* Responsive preview buttons */
.preview-btns button {
    background: transparent;
    border: 1px solid #475569;
    color: #e2e8f0;
    padding: 4px 10px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
}

.preview-btns button.active {
    background: #3b82f6;
    border-color: #3b82f6;
}

/* Save status */
.save-status {
    font-size: 12px;
    color: #94a3b8;
}

.save-status.saving { color: #fbbf24; }
.save-status.saved { color: #22c55e; }

/* Site selector */
.site-selector {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100vh;
    background: #f1f5f9;
}

.site-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}

.site-card:hover {
    border-color: #3b82f6;
    box-shadow: 0 4px 12px rgba(59,130,246,0.15);
}

/* Repeater items */
.repeater-item {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 8px;
    background: #f8fafc;
    position: relative;
}

.repeater-item .remove-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    background: #fee2e2;
    color: #dc2626;
    border: none;
    border-radius: 4px;
    width: 24px;
    height: 24px;
    cursor: pointer;
    font-size: 14px;
}
```

- [ ] **Step 3: Create public/index.php**

```php
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';
Database::migrate();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Builder</title>
    <link rel="stylesheet" href="/assets/css/editor.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
</head>
<body class="editor-body">
    <div id="app">
        <!-- Site Selector Screen -->
        <div v-if="!currentSite" class="site-selector">
            <div style="max-width:800px;width:100%;padding:24px">
                <h1 style="font-size:28px;font-weight:700;text-align:center;margin-bottom:8px">Website Builder</h1>
                <p style="text-align:center;color:#64748b;margin-bottom:32px">Create and manage your websites</p>

                <div style="display:flex;gap:12px;margin-bottom:24px;justify-content:center">
                    <button class="btn btn-primary" @click="createBlankSite">+ New Blank Site</button>
                    <button class="btn btn-secondary" @click="showTemplates = true">From Template</button>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px">
                    <div class="site-card" v-for="site in sites" :key="site.id" @click="openSite(site)">
                        <div style="font-weight:600;margin-bottom:4px">{{ site.name }}</div>
                        <div style="font-size:12px;color:#94a3b8">/{{ site.slug }}</div>
                        <button class="btn btn-danger btn-sm" style="margin-top:12px" @click.stop="deleteSite(site.id)">Delete</button>
                    </div>
                </div>

                <!-- Template Modal -->
                <div class="modal-overlay" :class="{ active: showTemplates }">
                    <div class="modal-content">
                        <h2 style="font-size:20px;font-weight:700;margin-bottom:16px">Choose a Template</h2>
                        <div style="display:grid;gap:12px">
                            <div class="site-card" v-for="t in templates" :key="t.id" @click="createFromTemplate(t.id)">
                                <div style="font-weight:600">{{ t.name }}</div>
                                <div style="font-size:13px;color:#64748b;margin-top:4px">{{ t.description }}</div>
                            </div>
                        </div>
                        <button class="btn btn-secondary" style="margin-top:16px" @click="showTemplates = false">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Editor -->
        <div v-else class="editor-layout">
            <!-- Top Bar -->
            <div class="top-bar">
                <button class="btn btn-secondary btn-sm" @click="backToSites" title="Back to sites">&larr;</button>
                <input type="text" v-model="currentSite.name" @change="saveSiteSettings"
                    style="background:transparent;border:1px solid #475569;color:white;padding:6px 12px;border-radius:6px;font-size:14px;width:200px">
                <select v-model="currentPageId" @change="loadPage" style="background:#334155;color:white;border:1px solid #475569;padding:6px 10px;border-radius:6px;font-size:13px">
                    <option v-for="p in pages" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <button class="btn btn-secondary btn-sm" @click="addPage">+ Page</button>
                <button class="btn btn-secondary btn-sm" @click="showPageSettings = true" v-if="currentPage">Page Settings</button>

                <div style="flex:1"></div>

                <div class="preview-btns" style="display:flex;gap:4px">
                    <button :class="{ active: previewMode === 'desktop' }" @click="previewMode='desktop'">Desktop</button>
                    <button :class="{ active: previewMode === 'tablet' }" @click="previewMode='tablet'">Tablet</button>
                    <button :class="{ active: previewMode === 'mobile' }" @click="previewMode='mobile'">Mobile</button>
                </div>

                <button class="btn btn-secondary btn-sm" @click="openSeoModal">SEO</button>
                <button class="btn btn-secondary btn-sm" @click="previewSite">Preview</button>
                <button class="btn btn-success btn-sm" @click="publishSite">Publish</button>
                <span class="save-status" :class="saveStatus">{{ saveStatusText }}</span>
            </div>

            <!-- Left Sidebar -->
            <div class="left-sidebar">
                <div class="tab-bar">
                    <button class="tab-btn" :class="{ active: leftTab === 'components' }" @click="leftTab='components'">Components</button>
                    <button class="tab-btn" :class="{ active: leftTab === 'pages' }" @click="leftTab='pages'">Pages</button>
                    <button class="tab-btn" :class="{ active: leftTab === 'media' }" @click="leftTab='media'">Media</button>
                </div>

                <!-- Components tab -->
                <div v-if="leftTab === 'components'">
                    <template v-for="(items, category) in componentDefs" :key="category">
                        <div class="sidebar-section">{{ category }}</div>
                        <div v-for="(def, type) in items" :key="type"
                             class="component-item"
                             draggable="true"
                             @dragstart="onDragStartNew($event, type)">
                            <span>{{ def.label }}</span>
                        </div>
                    </template>
                </div>

                <!-- Pages tab -->
                <div v-if="leftTab === 'pages'" style="padding:12px">
                    <div v-for="p in pages" :key="p.id"
                         style="padding:10px;border-radius:6px;margin-bottom:4px;cursor:pointer;display:flex;justify-content:space-between;align-items:center"
                         :style="{ background: p.id === currentPageId ? '#e2e8f0' : 'transparent' }"
                         @click="currentPageId = p.id; loadPage()">
                        <span>{{ p.name }}</span>
                        <button v-if="pages.length > 1" class="btn btn-danger btn-sm" @click.stop="deletePageConfirm(p.id)" style="padding:2px 6px;font-size:11px">x</button>
                    </div>
                </div>

                <!-- Media tab -->
                <div v-if="leftTab === 'media'" style="padding:12px">
                    <div style="margin-bottom:12px">
                        <input type="file" accept="image/*" @change="uploadMedia" style="font-size:12px">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <div v-for="m in mediaItems" :key="m.id" style="position:relative">
                            <img :src="'/storage/uploads/' + m.filename" style="width:100%;height:80px;object-fit:cover;border-radius:6px;cursor:pointer" @click="copyMediaUrl(m)">
                            <button @click="deleteMedia(m.id)" style="position:absolute;top:2px;right:2px;background:#ef4444;color:white;border:none;border-radius:3px;width:18px;height:18px;font-size:10px;cursor:pointer">x</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Canvas -->
            <div class="canvas-area">
                <div class="canvas-frame" :class="previewMode" ref="canvasFrame"
                     @dragover.prevent="onCanvasDragOver"
                     @drop.prevent="onCanvasDrop">

                    <div v-if="!currentPage || currentPage.components.length === 0"
                         style="display:flex;align-items:center;justify-content:center;min-height:400px;color:#94a3b8;font-size:16px">
                        Drag components here to start building
                    </div>

                    <template v-for="(comp, index) in (currentPage ? currentPage.components : [])" :key="comp.id">
                        <div class="drop-zone" :class="{ active: dropIndex === index }"
                             @dragover.prevent="dropIndex = index"
                             @dragleave="dropIndex = -1"></div>
                        <div class="canvas-component" :class="{ selected: selectedComponentId === comp.id }"
                             @click.stop="selectComponent(comp.id)"
                             draggable="true"
                             @dragstart="onDragStartExisting($event, index)">
                            <div class="component-toolbar">
                                <button @click.stop="moveComponent(index, -1)" title="Move up">&#8593;</button>
                                <button @click.stop="moveComponent(index, 1)" title="Move down">&#8595;</button>
                                <button @click.stop="duplicateComponent(index)" title="Duplicate">&#9776;</button>
                                <button @click.stop="deleteComponent(index)" title="Delete">&#10005;</button>
                            </div>
                            <div v-html="renderComponentPreview(comp)"></div>
                        </div>
                    </template>
                    <div class="drop-zone" :class="{ active: dropIndex === (currentPage ? currentPage.components.length : 0) }"
                         @dragover.prevent="dropIndex = (currentPage ? currentPage.components.length : 0)"
                         @dragleave="dropIndex = -1"
                         style="min-height:40px"></div>
                </div>
            </div>

            <!-- Right Sidebar -->
            <div class="right-sidebar">
                <div v-if="!selectedComponent" style="color:#94a3b8;text-align:center;margin-top:40px;font-size:14px">
                    Select a component to edit its properties
                </div>
                <div v-else>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
                        <h3 style="font-weight:700;font-size:16px">{{ getComponentLabel(selectedComponent.type) }}</h3>
                        <button class="btn btn-secondary btn-sm" @click="selectedComponentId = null">Close</button>
                    </div>
                    <div v-for="field in getComponentSchema(selectedComponent.type)" :key="field.key" class="prop-field">
                        <label>{{ field.label }}</label>

                        <input v-if="field.type === 'text'" type="text"
                               :value="selectedComponent.props[field.key]"
                               @input="updateProp(field.key, $event.target.value)">

                        <textarea v-else-if="field.type === 'textarea'" rows="3"
                                  :value="selectedComponent.props[field.key]"
                                  @input="updateProp(field.key, $event.target.value)"></textarea>

                        <textarea v-else-if="field.type === 'richtext'" rows="6"
                                  :value="selectedComponent.props[field.key]"
                                  @input="updateProp(field.key, $event.target.value)"></textarea>

                        <input v-else-if="field.type === 'number'" type="number"
                               :value="selectedComponent.props[field.key]"
                               @input="updateProp(field.key, parseInt($event.target.value) || 0)">

                        <input v-else-if="field.type === 'color'" type="color"
                               :value="selectedComponent.props[field.key]"
                               @input="updateProp(field.key, $event.target.value)">

                        <select v-else-if="field.type === 'select'"
                                :value="selectedComponent.props[field.key]"
                                @change="updateProp(field.key, $event.target.value)">
                            <option v-for="opt in field.options" :key="opt" :value="opt">{{ opt }}</option>
                        </select>

                        <label v-else-if="field.type === 'toggle'" style="display:flex;align-items:center;gap:8px;cursor:pointer">
                            <input type="checkbox"
                                   :checked="selectedComponent.props[field.key]"
                                   @change="updateProp(field.key, $event.target.checked)">
                            <span style="font-weight:400">{{ selectedComponent.props[field.key] ? 'On' : 'Off' }}</span>
                        </label>

                        <div v-else-if="field.type === 'image'" style="display:flex;gap:8px;align-items:center">
                            <input type="text" :value="selectedComponent.props[field.key]"
                                   @input="updateProp(field.key, $event.target.value)" placeholder="Image URL or upload">
                            <button class="btn btn-secondary btn-sm" @click="openMediaPicker(field.key)">Browse</button>
                        </div>

                        <!-- Repeater -->
                        <div v-else-if="field.type === 'repeater'">
                            <div class="repeater-item" v-for="(item, ri) in (selectedComponent.props[field.key] || [])" :key="ri">
                                <button class="remove-btn" @click="removeRepeaterItem(field.key, ri)">x</button>
                                <div v-for="subfield in field.fields" :key="subfield.key" class="prop-field" style="margin-bottom:8px">
                                    <label style="font-size:11px">{{ subfield.label }}</label>
                                    <input v-if="subfield.type === 'text' || subfield.type === 'email' || subfield.type === 'tel'" type="text"
                                           :value="item[subfield.key]"
                                           @input="updateRepeaterItem(field.key, ri, subfield.key, $event.target.value)">
                                    <textarea v-else-if="subfield.type === 'textarea'" rows="2"
                                              :value="item[subfield.key]"
                                              @input="updateRepeaterItem(field.key, ri, subfield.key, $event.target.value)"></textarea>
                                    <select v-else-if="subfield.type === 'select'"
                                            :value="item[subfield.key]"
                                            @change="updateRepeaterItem(field.key, ri, subfield.key, $event.target.value)">
                                        <option v-for="o in subfield.options" :key="o" :value="o">{{ o }}</option>
                                    </select>
                                    <label v-else-if="subfield.type === 'toggle'" style="display:flex;align-items:center;gap:6px;cursor:pointer">
                                        <input type="checkbox" :checked="item[subfield.key]"
                                               @change="updateRepeaterItem(field.key, ri, subfield.key, $event.target.checked)">
                                        {{ item[subfield.key] ? 'Yes' : 'No' }}
                                    </label>
                                    <div v-else-if="subfield.type === 'image'" style="display:flex;gap:4px">
                                        <input type="text" :value="item[subfield.key]"
                                               @input="updateRepeaterItem(field.key, ri, subfield.key, $event.target.value)">
                                    </div>
                                </div>
                            </div>
                            <button class="btn btn-secondary btn-sm" @click="addRepeaterItem(field.key, field.fields)">+ Add</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SEO Modal -->
            <div class="modal-overlay" :class="{ active: showSeoModal }">
                <div class="modal-content">
                    <h2 style="font-size:20px;font-weight:700;margin-bottom:16px">SEO Settings</h2>
                    <div class="prop-field">
                        <label>Page Title</label>
                        <input type="text" v-model="seoData.title">
                    </div>
                    <div class="prop-field">
                        <label>Meta Description</label>
                        <textarea rows="3" v-model="seoData.description"></textarea>
                    </div>
                    <div class="prop-field">
                        <label>Keywords</label>
                        <input type="text" v-model="seoData.keywords" placeholder="keyword1, keyword2, ...">
                    </div>
                    <div style="display:flex;gap:8px;margin-top:16px">
                        <button class="btn btn-primary" @click="saveSeo">Save</button>
                        <button class="btn btn-secondary" @click="showSeoModal = false">Cancel</button>
                    </div>
                </div>
            </div>

            <!-- Page Settings Modal -->
            <div class="modal-overlay" :class="{ active: showPageSettings }">
                <div class="modal-content" v-if="currentPage">
                    <h2 style="font-size:20px;font-weight:700;margin-bottom:16px">Page Settings</h2>
                    <div class="prop-field">
                        <label>Page Name</label>
                        <input type="text" v-model="currentPage.name">
                    </div>
                    <div class="prop-field">
                        <label>URL Slug</label>
                        <input type="text" v-model="currentPage.slug">
                    </div>
                    <div style="display:flex;gap:8px;margin-top:16px">
                        <button class="btn btn-primary" @click="showPageSettings = false; triggerSave()">Save</button>
                        <button class="btn btn-secondary" @click="showPageSettings = false">Cancel</button>
                    </div>
                </div>
            </div>

            <!-- Media Picker Modal -->
            <div class="modal-overlay" :class="{ active: showMediaPicker }">
                <div class="modal-content">
                    <h2 style="font-size:20px;font-weight:700;margin-bottom:16px">Select Image</h2>
                    <div style="margin-bottom:12px">
                        <input type="file" accept="image/*" @change="uploadMediaForPicker">
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px">
                        <img v-for="m in mediaItems" :key="m.id"
                             :src="'/storage/uploads/' + m.filename"
                             style="width:100%;height:100px;object-fit:cover;border-radius:6px;cursor:pointer;border:2px solid transparent"
                             @click="pickMedia(m)">
                    </div>
                    <button class="btn btn-secondary" style="margin-top:16px" @click="showMediaPicker = false">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script src="/assets/js/api.js"></script>
    <script src="/assets/js/app.js"></script>
</body>
</html>
```

- [ ] **Step 4: Commit**

```bash
git add public/index.php public/assets/css/editor.css public/assets/js/api.js
git commit -m "feat: editor SPA shell with index.php, CSS, and API client"
```

---

### Task 10: Vue App — Editor Logic + Component Rendering

**Files:**
- Create: `public/assets/js/app.js`

- [ ] **Step 1: Create public/assets/js/app.js**

```javascript
const { createApp, ref, reactive, computed, watch, nextTick, onMounted } = Vue;

const app = createApp({
    setup() {
        // State
        const sites = ref([]);
        const templates = ref([]);
        const currentSite = ref(null);
        const pages = ref([]);
        const currentPageId = ref(null);
        const currentPage = ref(null);
        const componentDefs = ref({});
        const selectedComponentId = ref(null);
        const leftTab = ref('components');
        const previewMode = ref('desktop');
        const saveStatus = ref('saved');
        const saveStatusText = ref('Saved');
        const showTemplates = ref(false);
        const showSeoModal = ref(false);
        const showPageSettings = ref(false);
        const showMediaPicker = ref(false);
        const mediaItems = ref([]);
        const mediaPickerTarget = ref(null);
        const seoData = reactive({ title: '', description: '', keywords: '' });
        const dropIndex = ref(-1);
        const dragType = ref(null); // 'new' or 'existing'
        const dragData = ref(null);
        let saveTimer = null;

        // Computed
        const selectedComponent = computed(() => {
            if (!currentPage.value || !selectedComponentId.value) return null;
            return currentPage.value.components.find(c => c.id === selectedComponentId.value) || null;
        });

        // Init
        onMounted(async () => {
            await loadSites();
            componentDefs.value = await API.listComponents();
            await loadMedia();
            templates.value = await API.listTemplates();
        });

        // Sites
        async function loadSites() {
            sites.value = await API.listSites();
        }

        async function createBlankSite() {
            const name = prompt('Site name:', 'My Website');
            if (!name) return;
            const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
            const site = await API.createSite({ name, slug });
            await loadSites();
            openSite(site);
        }

        async function createFromTemplate(templateId) {
            showTemplates.value = false;
            const site = await API.createSite({ template: templateId });
            await loadSites();
            openSite(site);
        }

        async function openSite(site) {
            currentSite.value = site;
            pages.value = await API.listPages(site.id);
            if (pages.value.length > 0) {
                currentPageId.value = pages.value[0].id;
                await loadPage();
            }
        }

        async function deleteSite(id) {
            if (!confirm('Delete this site?')) return;
            await API.deleteSite(id);
            await loadSites();
        }

        function backToSites() {
            currentSite.value = null;
            currentPage.value = null;
            selectedComponentId.value = null;
            loadSites();
        }

        async function saveSiteSettings() {
            if (!currentSite.value) return;
            await API.updateSite(currentSite.value.id, { name: currentSite.value.name });
        }

        // Pages
        async function loadPage() {
            if (!currentPageId.value) return;
            const page = await API.getPage(currentPageId.value);
            currentPage.value = page;
            selectedComponentId.value = null;
        }

        async function addPage() {
            const name = prompt('Page name:', 'New Page');
            if (!name) return;
            const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
            const page = await API.savePage(currentSite.value.id, {
                name, slug, components: [], seo: { title: name }, sort_order: pages.value.length
            });
            pages.value = await API.listPages(currentSite.value.id);
            currentPageId.value = page.id;
            await loadPage();
        }

        async function deletePageConfirm(id) {
            if (!confirm('Delete this page?')) return;
            await API.deletePage(id);
            pages.value = await API.listPages(currentSite.value.id);
            if (pages.value.length > 0) {
                currentPageId.value = pages.value[0].id;
                await loadPage();
            } else {
                currentPage.value = null;
            }
        }

        // Save
        function triggerSave() {
            saveStatus.value = 'saving';
            saveStatusText.value = 'Saving...';
            clearTimeout(saveTimer);
            saveTimer = setTimeout(doSave, 1500);
        }

        async function doSave() {
            if (!currentPage.value || !currentSite.value) return;
            try {
                await API.savePage(currentSite.value.id, {
                    name: currentPage.value.name,
                    slug: currentPage.value.slug,
                    components: currentPage.value.components,
                    seo: currentPage.value.seo || {},
                    sort_order: currentPage.value.sort_order || 0,
                }, currentPage.value.id);
                saveStatus.value = 'saved';
                saveStatusText.value = 'Saved';
            } catch (e) {
                saveStatus.value = '';
                saveStatusText.value = 'Error saving';
            }
        }

        // Components
        function generateId() {
            return 'c' + Math.random().toString(36).substr(2, 9);
        }

        function getDefaultProps(type) {
            const allDefs = componentDefs.value;
            for (const cat of Object.values(allDefs)) {
                if (cat[type]) return JSON.parse(JSON.stringify(cat[type].defaults));
            }
            return {};
        }

        function getComponentLabel(type) {
            const allDefs = componentDefs.value;
            for (const cat of Object.values(allDefs)) {
                if (cat[type]) return cat[type].label;
            }
            return type;
        }

        function getComponentSchema(type) {
            const allDefs = componentDefs.value;
            for (const cat of Object.values(allDefs)) {
                if (cat[type]) return cat[type].schema;
            }
            return [];
        }

        function selectComponent(id) {
            selectedComponentId.value = id;
        }

        function updateProp(key, value) {
            if (!selectedComponent.value) return;
            selectedComponent.value.props[key] = value;
            triggerSave();
        }

        function moveComponent(index, direction) {
            const comps = currentPage.value.components;
            const newIndex = index + direction;
            if (newIndex < 0 || newIndex >= comps.length) return;
            const item = comps.splice(index, 1)[0];
            comps.splice(newIndex, 0, item);
            triggerSave();
        }

        function duplicateComponent(index) {
            const comp = currentPage.value.components[index];
            const clone = JSON.parse(JSON.stringify(comp));
            clone.id = generateId();
            currentPage.value.components.splice(index + 1, 0, clone);
            triggerSave();
        }

        function deleteComponent(index) {
            const comp = currentPage.value.components[index];
            if (selectedComponentId.value === comp.id) selectedComponentId.value = null;
            currentPage.value.components.splice(index, 1);
            triggerSave();
        }

        // Drag and Drop
        function onDragStartNew(event, type) {
            dragType.value = 'new';
            dragData.value = type;
            event.dataTransfer.effectAllowed = 'copy';
        }

        function onDragStartExisting(event, index) {
            dragType.value = 'existing';
            dragData.value = index;
            event.dataTransfer.effectAllowed = 'move';
        }

        function onCanvasDragOver(event) {
            event.dataTransfer.dropEffect = dragType.value === 'new' ? 'copy' : 'move';
        }

        function onCanvasDrop(event) {
            if (!currentPage.value) return;
            const targetIndex = dropIndex.value >= 0 ? dropIndex.value : currentPage.value.components.length;

            if (dragType.value === 'new') {
                const type = dragData.value;
                const newComp = {
                    id: generateId(),
                    type: type,
                    props: getDefaultProps(type),
                };
                currentPage.value.components.splice(targetIndex, 0, newComp);
                selectedComponentId.value = newComp.id;
            } else if (dragType.value === 'existing') {
                const fromIndex = dragData.value;
                const item = currentPage.value.components.splice(fromIndex, 1)[0];
                const adjustedIndex = targetIndex > fromIndex ? targetIndex - 1 : targetIndex;
                currentPage.value.components.splice(adjustedIndex, 0, item);
            }

            dropIndex.value = -1;
            dragType.value = null;
            dragData.value = null;
            triggerSave();
        }

        // Repeater
        function addRepeaterItem(key, fields) {
            if (!selectedComponent.value) return;
            const item = {};
            fields.forEach(f => { item[f.key] = f.type === 'toggle' ? false : ''; });
            if (!selectedComponent.value.props[key]) selectedComponent.value.props[key] = [];
            selectedComponent.value.props[key].push(item);
            triggerSave();
        }

        function removeRepeaterItem(key, index) {
            selectedComponent.value.props[key].splice(index, 1);
            triggerSave();
        }

        function updateRepeaterItem(key, index, subKey, value) {
            selectedComponent.value.props[key][index][subKey] = value;
            triggerSave();
        }

        // Media
        async function loadMedia() {
            try { mediaItems.value = await API.listMedia(); } catch(e) {}
        }

        async function uploadMedia(event) {
            const file = event.target.files[0];
            if (!file) return;
            await API.uploadMedia(file);
            await loadMedia();
            event.target.value = '';
        }

        async function deleteMedia(id) {
            if (!confirm('Delete this image?')) return;
            await API.deleteMedia(id);
            await loadMedia();
        }

        function copyMediaUrl(m) {
            const url = '/storage/uploads/' + m.filename;
            navigator.clipboard.writeText(url);
            alert('URL copied: ' + url);
        }

        function openMediaPicker(targetKey) {
            mediaPickerTarget.value = targetKey;
            showMediaPicker.value = true;
            loadMedia();
        }

        async function uploadMediaForPicker(event) {
            const file = event.target.files[0];
            if (!file) return;
            await API.uploadMedia(file);
            await loadMedia();
            event.target.value = '';
        }

        function pickMedia(m) {
            if (mediaPickerTarget.value && selectedComponent.value) {
                selectedComponent.value.props[mediaPickerTarget.value] = '/storage/uploads/' + m.filename;
                triggerSave();
            }
            showMediaPicker.value = false;
        }

        // SEO
        function openSeoModal() {
            if (!currentPage.value) return;
            const seo = currentPage.value.seo || {};
            seoData.title = seo.title || '';
            seoData.description = seo.description || '';
            seoData.keywords = seo.keywords || '';
            showSeoModal.value = true;
        }

        function saveSeo() {
            if (!currentPage.value) return;
            currentPage.value.seo = { ...seoData };
            showSeoModal.value = false;
            triggerSave();
        }

        // Publish & Preview
        async function publishSite() {
            if (!currentSite.value) return;
            try {
                const result = await API.publish(currentSite.value.id);
                alert('Published! URL: ' + result.url);
                window.open(result.url, '_blank');
            } catch (e) {
                alert('Publish failed: ' + e.message);
            }
        }

        function previewSite() {
            if (!currentSite.value) return;
            window.open('/published/' + currentSite.value.slug + '/index.html', '_blank');
        }

        // Component Preview Rendering
        function renderComponentPreview(comp) {
            const p = comp.props || {};
            const renderers = {
                navbar: () => {
                    const bg = p.backgroundColor || '#fff';
                    const tc = p.textColor || '#111';
                    const logo = p.logoText || 'Site';
                    return `<div style="background:${bg};color:${tc};padding:12px 20px;display:flex;justify-content:space-between;align-items:center"><strong>${esc(logo)}</strong><span style="font-size:12px;color:#999">nav links</span></div>`;
                },
                hero: () => {
                    const bg = p.backgroundImage ? `url('${p.backgroundImage}')` : p.backgroundColor || '#1e3a5f';
                    const bgStyle = p.backgroundImage ? `background:linear-gradient(rgba(0,0,0,0.5),rgba(0,0,0,0.5)),${bg};background-size:cover;background-position:center` : `background:${bg}`;
                    return `<div style="${bgStyle};color:${p.textColor||'#fff'};padding:60px 20px;text-align:center;min-height:300px;display:flex;flex-direction:column;justify-content:center"><h1 style="font-size:28px;font-weight:bold;margin-bottom:8px">${esc(p.heading||'')}</h1><p style="opacity:0.9">${esc(p.subheading||'')}</p>${p.ctaText ? `<div style="margin-top:16px"><span style="background:white;color:#333;padding:8px 20px;border-radius:6px;font-weight:600">${esc(p.ctaText)}</span></div>` : ''}</div>`;
                },
                heading: () => {
                    const sizes = {h1:28,h2:24,h3:20,h4:18,h5:16,h6:14};
                    const size = sizes[p.level||'h2']||24;
                    return `<div style="padding:16px 20px;text-align:${p.alignment||'left'}"><div style="font-size:${size}px;font-weight:bold;color:${p.color||'#111'}">${esc(p.text||'')}</div></div>`;
                },
                text: () => `<div style="padding:16px 20px;text-align:${p.alignment||'left'}">${p.content||'<p>Text block</p>'}</div>`,
                image: () => {
                    if (!p.src) return `<div style="padding:20px;text-align:center;color:#999;background:#f1f5f9;margin:16px 20px;border-radius:8px;height:150px;display:flex;align-items:center;justify-content:center">Image placeholder</div>`;
                    return `<div style="padding:16px 20px;text-align:center"><img src="${esc(p.src)}" alt="${esc(p.alt||'')}" style="max-width:100%;height:auto;border-radius:8px"></div>`;
                },
                video: () => `<div style="padding:16px 20px;text-align:center;background:#0f172a;color:#94a3b8;height:200px;display:flex;align-items:center;justify-content:center;border-radius:8px;margin:0 20px">Video: ${esc(p.url||'No URL')}</div>`,
                button: () => {
                    const st = p.style === 'outline' ? `border:2px solid ${p.color||'#3b82f6'};color:${p.color||'#3b82f6'};background:transparent` : `background:${p.color||'#3b82f6'};color:white`;
                    return `<div style="padding:16px 20px;text-align:${p.alignment||'left'}"><span style="${st};padding:10px 24px;border-radius:6px;font-weight:600;display:inline-block">${esc(p.text||'Button')}</span></div>`;
                },
                section: () => {
                    const bgStyle = p.backgroundImage ? `background:url('${p.backgroundImage}');background-size:cover` : `background:${p.backgroundColor||'#fff'}`;
                    return `<div style="${bgStyle};padding:${p.paddingTop||60}px 0 ${p.paddingBottom||60}px"><div style="text-align:center;color:#999;font-size:13px">Section container</div></div>`;
                },
                columns: () => {
                    const cols = p.count || 2;
                    let colHtml = '';
                    for (let i = 0; i < cols; i++) colHtml += `<div style="background:#f1f5f9;padding:20px;border-radius:6px;text-align:center;color:#999;font-size:13px">Column ${i+1}</div>`;
                    return `<div style="display:grid;grid-template-columns:repeat(${cols},1fr);gap:${p.gap||24}px;padding:16px 20px">${colHtml}</div>`;
                },
                spacer: () => `<div style="height:${p.height||40}px;background:repeating-linear-gradient(45deg,transparent,transparent 5px,#f1f5f9 5px,#f1f5f9 10px)"></div>`,
                features: () => {
                    const items = (p.items||[]).map(i => `<div style="text-align:center;padding:16px"><div style="font-size:32px;margin-bottom:8px">${i.icon||''}</div><div style="font-weight:600;margin-bottom:4px">${esc(i.title||'')}</div><div style="font-size:13px;color:#666">${esc(i.description||'')}</div></div>`).join('');
                    return `<div style="background:#f8fafc;padding:40px 20px"><h2 style="text-align:center;font-size:24px;font-weight:bold;margin-bottom:24px">${esc(p.heading||'')}</h2><div style="display:grid;grid-template-columns:repeat(${p.columns||3},1fr);gap:16px">${items}</div></div>`;
                },
                testimonials: () => {
                    const items = (p.items||[]).map(i => `<div style="background:white;padding:20px;border-radius:8px;border:1px solid #e2e8f0"><p style="font-style:italic;color:#555;margin-bottom:12px">"${esc(i.quote||'')}"</p><div style="font-weight:600">${esc(i.name||'')}</div><div style="font-size:12px;color:#999">${esc(i.role||'')}</div></div>`).join('');
                    return `<div style="padding:40px 20px"><h2 style="text-align:center;font-size:24px;font-weight:bold;margin-bottom:24px">${esc(p.heading||'')}</h2><div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px">${items}</div></div>`;
                },
                pricing: () => {
                    const plans = (p.plans||[]).map(pl => {
                        const features = Array.isArray(pl.features) ? pl.features : (pl.features||'').split('\n');
                        const fl = features.filter(f=>f.trim()).map(f => `<div style="padding:6px 0;border-bottom:1px solid #f1f5f9;font-size:13px">${esc(f.trim())}</div>`).join('');
                        const hl = pl.highlighted ? 'border:2px solid #3b82f6' : 'border:1px solid #e2e8f0';
                        return `<div style="${hl};border-radius:12px;padding:24px;background:white"><h3 style="font-weight:600">${esc(pl.name||'')}</h3><div style="font-size:28px;font-weight:bold;margin:8px 0 16px">${esc(pl.price||'')}</div>${fl}<div style="margin-top:16px;text-align:center"><span style="background:#1e293b;color:white;padding:8px 20px;border-radius:6px;font-size:13px;font-weight:600">${esc(pl.ctaText||'Choose')}</span></div></div>`;
                    }).join('');
                    return `<div style="background:#f8fafc;padding:40px 20px"><h2 style="text-align:center;font-size:24px;font-weight:bold;margin-bottom:24px">${esc(p.heading||'')}</h2><div style="display:grid;grid-template-columns:repeat(${Math.min((p.plans||[]).length,3)},1fr);gap:16px">${plans}</div></div>`;
                },
                contact_form: () => {
                    const fields = (p.fields||[]).map(f => `<div style="margin-bottom:12px"><label style="font-size:13px;font-weight:500;display:block;margin-bottom:4px">${esc(f.label||'')}</label>${f.type==='textarea' ? '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;height:60px"></div>' : '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;height:36px"></div>'}</div>`).join('');
                    return `<div style="padding:40px 20px;max-width:500px;margin:0 auto"><h2 style="text-align:center;font-size:24px;font-weight:bold;margin-bottom:24px">${esc(p.heading||'')}</h2>${fields}<div style="text-align:center;margin-top:16px"><span style="background:#3b82f6;color:white;padding:10px 28px;border-radius:6px;font-weight:600">${esc(p.submitText||'Send')}</span></div></div>`;
                },
                map: () => `<div style="padding:16px 20px"><div style="background:#e2e8f0;height:${p.height||300}px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#64748b">Map: ${esc(p.address||'')}</div></div>`,
                gallery: () => {
                    const images = (p.images||[]);
                    if (images.length === 0) return `<div style="padding:20px;text-align:center;color:#999">Gallery (no images yet)</div>`;
                    const imgs = images.map(i => `<img src="${esc(i.src||'')}" alt="${esc(i.alt||'')}" style="width:100%;height:120px;object-fit:cover;border-radius:6px">`).join('');
                    return `<div style="display:grid;grid-template-columns:repeat(${p.columns||3},1fr);gap:${p.gap||8}px;padding:16px 20px">${imgs}</div>`;
                },
                footer: () => {
                    const bg = p.backgroundColor || '#111827';
                    const tc = p.textColor || '#9ca3af';
                    return `<div style="background:${bg};color:${tc};padding:24px 20px;text-align:center;font-size:13px">${esc(p.text||'Footer')}</div>`;
                },
            };

            const renderer = renderers[comp.type];
            return renderer ? renderer() : `<div style="padding:20px;color:#999;text-align:center">Unknown: ${esc(comp.type)}</div>`;
        }

        function esc(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        return {
            sites, templates, currentSite, pages, currentPageId, currentPage,
            componentDefs, selectedComponentId, selectedComponent,
            leftTab, previewMode, saveStatus, saveStatusText,
            showTemplates, showSeoModal, showPageSettings, showMediaPicker,
            mediaItems, seoData, dropIndex,
            loadSites, createBlankSite, createFromTemplate, openSite, deleteSite, backToSites, saveSiteSettings,
            loadPage, addPage, deletePageConfirm,
            triggerSave,
            selectComponent, updateProp, moveComponent, duplicateComponent, deleteComponent,
            getComponentLabel, getComponentSchema,
            onDragStartNew, onDragStartExisting, onCanvasDragOver, onCanvasDrop,
            addRepeaterItem, removeRepeaterItem, updateRepeaterItem,
            loadMedia, uploadMedia, deleteMedia, copyMediaUrl,
            openMediaPicker, uploadMediaForPicker, pickMedia,
            openSeoModal, saveSeo,
            publishSite, previewSite,
            renderComponentPreview,
        };
    },
});

app.mount('#app');
```

- [ ] **Step 2: Commit**

```bash
git add public/assets/js/app.js
git commit -m "feat: Vue app with editor logic, drag-drop, component rendering, auto-save"
```

---

### Task 11: Final Setup + Test Run

**Files:**
- Modify: `config.php` (add .htaccess or PHP server routing)

- [ ] **Step 1: Create a PHP router script for the built-in server**

Create `router.php` in the project root:

```php
<?php
// Router for PHP built-in development server
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve static files directly
if ($uri !== '/' && file_exists(__DIR__ . '/public' . $uri)) {
    // Check if it's a real file (not directory)
    if (is_file(__DIR__ . '/public' . $uri)) {
        return false;
    }
}

// Serve uploaded files
if (strpos($uri, '/storage/uploads/') === 0 && file_exists(__DIR__ . $uri)) {
    $mime = mime_content_type(__DIR__ . $uri);
    header('Content-Type: ' . $mime);
    readfile(__DIR__ . $uri);
    return true;
}

// Route everything else to index.php or api.php
if (strpos($uri, '/api.php') === 0) {
    require __DIR__ . '/public/api.php';
} else {
    require __DIR__ . '/public/index.php';
}
```

- [ ] **Step 2: Verify directory structure**

```bash
ls -la public/assets/js/
ls -la public/assets/css/
ls -la src/
ls -la templates/
```

- [ ] **Step 3: Start the development server**

```bash
php -S localhost:8080 router.php
```

Open http://localhost:8080 in a browser. Verify:
1. Site selector screen loads
2. "New Blank Site" creates a site
3. "From Template" shows 3 templates
4. Opening a site shows the editor with 3-panel layout
5. Dragging components onto canvas works
6. Properties panel shows when selecting a component
7. Auto-save works (status shows "Saving..." then "Saved")
8. Publish generates static HTML

- [ ] **Step 4: Commit router**

```bash
git add router.php
git commit -m "feat: PHP dev server router for local development"
```