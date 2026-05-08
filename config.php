<?php
// Portal configuration. Override per environment.

// Paths. Storage location can be overridden via PORTAL_STORAGE env var
// (useful when the source tree is on a filesystem that doesn't support SQLite — e.g., WSL/9p mounts).
define('PORTAL_ROOT', __DIR__);
define('PORTAL_STORAGE', getenv('PORTAL_STORAGE') ?: PORTAL_ROOT . '/storage');
define('PORTAL_UPLOADS', PORTAL_STORAGE . '/uploads');
define('PORTAL_VIEWS', PORTAL_ROOT . '/views');
define('PORTAL_DB_PATH', PORTAL_STORAGE . '/portal.sqlite');
define('PORTAL_SCHEMA_PATH', PORTAL_ROOT . '/db/schema.sql');
define('PORTAL_SEED_PATH', PORTAL_ROOT . '/db/seed.php');

// App
define('PORTAL_NAME', 'AmplifyCan Customer Portal');
define('PORTAL_BASE_URL', getenv('PORTAL_BASE_URL') ?: 'http://localhost:8000');
define('PORTAL_TAX_RATE', 0.055);

// Sessions
define('PORTAL_SESSION_NAME', 'amp_portal');
define('PORTAL_SESSION_LIFETIME', 60 * 60 * 8);

// Tools (assumed available on PATH)
define('PORTAL_PDFINFO',     getenv('PORTAL_PDFINFO')     ?: 'pdfinfo');
define('PORTAL_PDFIMAGES',   getenv('PORTAL_PDFIMAGES')   ?: 'pdfimages');
define('PORTAL_GHOSTSCRIPT', getenv('PORTAL_GHOSTSCRIPT') ?: 'gs');

// Monday.com (Slice 5)
define('PORTAL_MONDAY_API_KEY',  getenv('PORTAL_MONDAY_API_KEY')  ?: '');
define('PORTAL_MONDAY_BOARD_ID', getenv('PORTAL_MONDAY_BOARD_ID') ?: '');
define('PORTAL_MONDAY_ESTIMATES_BOARD_ID', getenv('PORTAL_MONDAY_ESTIMATES_BOARD_ID') ?: '8483187264');
define('PORTAL_MONDAY_SUBITEMS_BOARD_ID', getenv('PORTAL_MONDAY_SUBITEMS_BOARD_ID') ?: '8483469691');

foreach ([PORTAL_STORAGE, PORTAL_UPLOADS] as $dir) {
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
}
