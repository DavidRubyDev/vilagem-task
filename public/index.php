<?php

declare(strict_types=1);

use ParcelMap\Bootstrap\Container;
use ParcelMap\Handler\Http\Request;
use ParcelMap\Handler\Http\ResponseEmitter;

require dirname(__DIR__) . '/vendor/autoload.php';

$response = new Container()->httpKernel()->handle(Request::fromServer($_SERVER));

new ResponseEmitter()->emit($response);
