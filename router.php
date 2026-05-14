<?php
// Router for PHP built-in development server
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve static files directly
if ($uri !== '/' && file_exists(__DIR__ . '/public' . $uri)) {
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
