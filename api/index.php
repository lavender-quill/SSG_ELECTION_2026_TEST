<?php
// Enable gzip compression to reduce payload size
if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
    ob_start('ob_gzhandler');
}

// Bridge to main application
require_once __DIR__ . '/../api-version1/index.php';
