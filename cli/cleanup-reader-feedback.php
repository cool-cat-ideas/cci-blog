<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__, 3) . '/config/config.inc.php';
require_once dirname(__DIR__) . '/classes/CciBlogReaderFeedback.php';
CciBlogReaderFeedback::cleanup();
echo "Reader feedback retention cleanup completed.\n";
