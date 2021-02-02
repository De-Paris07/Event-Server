<?php

use App\Kernel;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

$loader = require __DIR__.'/../vendor/autoload.php';
// auto-load annotations
AnnotationRegistry::registerLoader([$loader, 'loadClass']);

if (!class_exists(Dotenv::class)) {
    throw new \RuntimeException('You need to define environment variables for configuration or add "symfony/dotenv" as a Composer dependency to load variables from a .env file.');
}
(new Dotenv())->load(__DIR__.'/../.env');

$kernel = new Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
