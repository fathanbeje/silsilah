<?php
declare(strict_types=1);

final class SilsilahWorkerRuntime
{
    private static bool $autoloadRegistered = false;
    private static int $handledRequests = 0;
    private static float $startedAt = 0.0;
    private static string $basePath = '';
    private static string $publicPath = '';
    private static string $healthPath = '/_franken/health';

    public static function bootstrap(string $basePath, string $publicPath): void
    {
        self::$basePath = rtrim(str_replace('\\', '/', $basePath), '/');
        self::$publicPath = rtrim(str_replace('\\', '/', $publicPath), '/');
        self::$healthPath = self::normalizePath((string) (getenv('APP_WORKER_HEALTH_PATH') ?: '/_franken/health'));

        if (self::$startedAt <= 0.0) {
            self::$startedAt = microtime(true);
        }

        if (!self::$autoloadRegistered) {
            require_once self::$basePath . '/bootstrap/autoload.php';
            self::$autoloadRegistered = true;
        }
    }

    public static function beforeRequest(): void
    {
        self::$handledRequests++;
        self::drainOutputBuffers();
        self::resetResponseState();
        self::primeServerGlobals();
    }

    public static function bootstrapLaravelApplication()
    {
        /** @var \Illuminate\Foundation\Application $app */
        $app = require self::$basePath . '/bootstrap/app.php';
        return $app;
    }

    public static function afterRequest($app = null): void
    {
        try {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        } catch (Throwable $e) {
            // Ignore cleanup failures to preserve worker availability.
        }

        try {
            if ($app && method_exists($app, 'bound') && $app->bound('db')) {
                $app->make('db')->disconnect();
            }
        } catch (Throwable $e) {
            // Ignore DB disconnect failures during teardown.
        }

        try {
            if ($app && method_exists($app, 'flush')) {
                $app->flush();
            }
        } catch (Throwable $e) {
            // Ignore container flush failures during teardown.
        }

        self::resetLaravelStatics();
        self::resetMutableGlobals();
        self::drainOutputBuffers();

        $gcEvery = (int) (getenv('APP_WORKER_GC_EVERY') ?: 1);
        if ($gcEvery > 0 && self::$handledRequests % $gcEvery === 0) {
            gc_collect_cycles();
        }
    }

    public static function shouldRecycleWorker(): bool
    {
        $maxRequests = (int) (getenv('APP_WORKER_MAX_REQUESTS') ?: 0);
        return $maxRequests > 0 && self::$handledRequests >= $maxRequests;
    }

    public static function isHealthRequest(): bool
    {
        $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        return self::normalizePath((string) $requestPath) === self::$healthPath;
    }

    public static function healthPayload(): array
    {
        $uptime = max(0.0, microtime(true) - self::$startedAt);
        $maxRequests = (int) (getenv('APP_WORKER_MAX_REQUESTS') ?: 0);

        return [
            'ok' => true,
            'mode' => 'frankenphp-worker',
            'worker_enabled' => self::boolEnv('APP_ENABLE_WORKER', false),
            'handled_requests' => self::$handledRequests,
            'max_requests' => $maxRequests,
            'should_recycle' => self::shouldRecycleWorker(),
            'memory_usage_bytes' => memory_get_usage(true),
            'memory_peak_bytes' => memory_get_peak_usage(true),
            'uptime_seconds' => round($uptime, 3),
            'request_uri' => (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            'public_path' => self::$publicPath,
            'health_path' => self::$healthPath,
            'php_version' => PHP_VERSION,
            'pid' => getmypid(),
            'timestamp' => date(DATE_ATOM),
        ];
    }

    public static function respondHealth(): void
    {
        http_response_code(200);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(self::healthPayload(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function reportThrowable(Throwable $e, $app = null): void
    {
        try {
            if ($app && method_exists($app, 'bound') && $app->bound(\Illuminate\Contracts\Debug\ExceptionHandler::class)) {
                $app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)->report($e);
                return;
            }
        } catch (Throwable $reportError) {
            error_log('[silsilah-worker] Failed reporting exception via Laravel: ' . $reportError->getMessage());
        }

        error_log('[silsilah-worker] ' . $e->getMessage());
        error_log($e->getTraceAsString());
    }

    public static function sendFatalResponse(Throwable $e): void
    {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
        }

        if (self::boolEnv('APP_DEBUG', false)) {
            echo $e;
            return;
        }

        echo 'Internal Server Error';
    }

    private static function primeServerGlobals(): void
    {
        $_SERVER['DOCUMENT_ROOT'] = self::$publicPath;
        $_SERVER['SCRIPT_FILENAME'] = self::$publicPath . '/index.php';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['PHP_SELF'] = '/index.php';
        $_SERVER['APP_RUNNING_IN_WORKER'] = '1';
    }

    private static function resetResponseState(): void
    {
        if (!headers_sent()) {
            header_remove();
        }

        http_response_code(200);
    }

    private static function resetLaravelStatics(): void
    {
        try {
            if (class_exists(\Illuminate\Support\Facades\Facade::class)) {
                \Illuminate\Support\Facades\Facade::clearResolvedInstances();
            }
        } catch (Throwable $e) {
            // Ignore facade reset failures during teardown.
        }

        try {
            if (class_exists(\Illuminate\Container\Container::class)) {
                \Illuminate\Container\Container::setInstance(null);
            }
        } catch (Throwable $e) {
            // Ignore container reset failures during teardown.
        }

        try {
            if (class_exists(\Illuminate\Database\Eloquent\Model::class)) {
                \Illuminate\Database\Eloquent\Model::unsetConnectionResolver();
                \Illuminate\Database\Eloquent\Model::unsetEventDispatcher();
            }
        } catch (Throwable $e) {
            // Ignore Eloquent reset failures during teardown.
        }
    }

    private static function resetMutableGlobals(): void
    {
        if (isset($GLOBALS['_SESSION']) && is_array($GLOBALS['_SESSION'])) {
            $GLOBALS['_SESSION'] = [];
        }

        foreach (['_FILES', '_POST'] as $key) {
            if (isset($GLOBALS[$key]) && is_array($GLOBALS[$key])) {
                $GLOBALS[$key] = [];
            }
        }
    }

    private static function normalizePath(string $path): string
    {
        $normalized = '/' . ltrim($path, '/');
        return rtrim($normalized, '/') ?: '/';
    }

    private static function boolEnv(string $key, bool $default): bool
    {
        $value = getenv($key);
        if (!is_string($value) || trim($value) === '') {
            return $default;
        }

        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    private static function drainOutputBuffers(): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    }
}
