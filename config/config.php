<?php

// Base URL (no trailing slash)
define('BASE_URL', 'http://localhost/web');

// Project root path
define('ROOT_PATH', dirname(__DIR__));

// Max image size for base64 upload (5MB)
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024);

// Allowed image MIME types
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

// All available collections
define('COLLECTIONS', [
    'Smartphones',
    'Audio',
    'Smartwatches',
    'Laptops',
    'Accessories',
    'Premium Tech',
    'Deals',
    'Trending',
    'New Arrivals',
]);
