<?php
declare(strict_types=1);

require_once __DIR__ . '/request-reset.php';

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

SilsilahWorkerRuntime::bootstrap(dirname(__DIR__), dirname(__DIR__) . '/public');

$requestHandler = static function (): void {
    $app = null;

    SilsilahWorkerRuntime::beforeRequest();

    try {
        if (SilsilahWorkerRuntime::isHealthRequest()) {
            SilsilahWorkerRuntime::respondHealth();
            return;
        }

        $app = SilsilahWorkerRuntime::bootstrapLaravelApplication();
        /** @var Kernel $kernel */
        $kernel = $app->make(Kernel::class);

        $request = Request::capture();
        $response = $kernel->handle($request);
        $response->send();
        $kernel->terminate($request, $response);
    } catch (Throwable $e) {
        SilsilahWorkerRuntime::reportThrowable($e, $app);
        SilsilahWorkerRuntime::sendFatalResponse($e);
    } finally {
        SilsilahWorkerRuntime::afterRequest($app);
    }
};

if (function_exists('frankenphp_handle_request')) {
    while (frankenphp_handle_request($requestHandler)) {
        if (SilsilahWorkerRuntime::shouldRecycleWorker()) {
            break;
        }
    }
    return;
}

// Fallback for direct CLI/local invocation.
$requestHandler();
