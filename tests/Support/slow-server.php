<?php

declare(strict_types=1);

/*
 * Router for PHP's built-in server, used by ConcurrencyTest to prove that
 * concurrent SDK calls really overlap on the wire: every request waits
 * `?ms=` milliseconds (default 400), then answers with a small JSON body
 * naming the path it was asked for.
 */

$ms = (int) ($_GET['ms'] ?? 400);
usleep($ms * 1000);

header('Content-Type: application/json');

echo json_encode(['data' => ['path' => parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)]]);
