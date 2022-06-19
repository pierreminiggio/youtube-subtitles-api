<?php

$projectFolder = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;

require $projectFolder . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use App\App;
use PierreMiniggio\ConfigProvider\ConfigProvider;

/** @var string $requestUrl */
$requestUrl = $_SERVER['REQUEST_URI'];

/** @var string|null $queryParameters */
$queryParameters = ! empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : null;

/** @var string $calledEndPoint */
$calledEndPoint = $queryParameters
    ? str_replace($queryParameters, '', $requestUrl)
    : $requestUrl
;

if (strlen($calledEndPoint) > 1 && substr($calledEndPoint, -1) === '/') {
    /** @var string $calledEndPoint */
    $calledEndPoint = substr($calledEndPoint, 0, -1);
}

$protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
$host = $protocol . '://' . $_SERVER['HTTP_HOST'];

if (str_ends_with($host, '/')) {
    $host = substr($host, 0, -1);
}

$configProvider = new ConfigProvider($projectFolder);

(new App(
    $configProvider
))->run(
    $calledEndPoint,
    $_SERVER['REQUEST_METHOD'] ?? null,
    $queryParameters,
    $_SERVER['HTTP_AUTHORIZATION'] ?? null
);

exit;
