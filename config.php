<?php
// Enable error logging
ini_set('log_errors', 1);

// Specify the error log file
ini_set('error_log', dirname(__FILE__).'/vmbuilder/error-file.log');

// Set the error reporting level
error_reporting(E_ALL);


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
