<?php
// Background job: convert one uploaded video. Started by the web app; can also be run by hand:
//   sudo -u www-data php bin/process-video.php <video id>
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/src/bootstrap.php';

$id = (int) ($argv[1] ?? 0);
if ($id <= 0) {
    fwrite(STDERR, "Usage: php bin/process-video.php <video id>\n");
    exit(1);
}
process_video($id);
