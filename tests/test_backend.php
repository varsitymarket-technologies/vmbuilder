<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';

define('TEST_DB', __DIR__ . '/test.sqlite');

function setup_test_db(): void {
    if (file_exists(TEST_DB)) unlink(TEST_DB);
}

function assert_true(bool $condition, string $msg): void {
    if (!$condition) {
        echo "FAIL: $msg\n";
        exit(1);
    }
    echo "PASS: $msg\n";
}

echo "=== Database Tests ===\n";

setup_test_db();
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

require_once __DIR__ . '/../src/SiteManager.php';

echo "\n=== SiteManager Tests ===\n";

// Re-create tables for SiteManager tests
$pdo2 = new PDO('sqlite:' . TEST_DB, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo2->exec('PRAGMA foreign_keys=ON');
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

// Cleanup
unlink(TEST_DB);
echo "\nAll tests passed!\n";
