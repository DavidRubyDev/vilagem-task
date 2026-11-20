<?php

declare(strict_types=1);

// Router script for `php -S`: a stand-in for the ČÚZK VFR download server, serving the committed fixture.
// The state structure file (ST_UZSZ) is served from the same fixture directory.
// Municipality 500000 answers 500, 500001 answers 200 with an HTML body, anything else missing answers 404.

$uri = $_SERVER['REQUEST_URI'];
$path = is_string($uri) ? parse_url($uri, PHP_URL_PATH) : null;
$name = basename(is_string($path) ? $path : '');

$isStructureFile = preg_match('/^\d{8}_ST_UZSZ\.xml\.zip$/', $name) === 1;
if (!$isStructureFile && preg_match('/^\d{8}_OB_(\d+)_UKSH\.xml\.zip$/', $name, $matches) !== 1) {
    http_response_code(404);

    return true;
}

if (!$isStructureFile && $matches[1] === '500000') {
    http_response_code(500);
    echo 'boom';

    return true;
}

if (!$isStructureFile && $matches[1] === '500001') {
    header('Content-Type: text/html');
    echo '<html><body>Maintenance</body></html>';

    return true;
}

$file = dirname(__DIR__, 2) . '/Fixtures/ruian/' . $name;
if (!is_file($file)) {
    http_response_code(404);

    return true;
}

header('Content-Type: application/zip');
readfile($file);

return true;
