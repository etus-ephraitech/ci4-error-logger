<?php

namespace Ephraitech\ErrorLogger\Handlers;

use CodeIgniter\Debug\Exceptions as CIExceptions;
use CodeIgniter\HTTP\Exceptions\HTTPExceptionInterface;
use Ephraitech\ErrorLogger\Libraries\ErrorLogger;
use Throwable;

class ExceptionHandler extends CIExceptions
{
    /**
     * Intercepts every uncaught exception CI4 would otherwise handle.
     * Logs it (unless it's an expected 4xx HTTP exception — those are
     * normal request outcomes, already handled per-endpoint), then hands
     * off to CI4's normal behavior (error page / API error response).
     */
    public function exceptionHandler(Throwable $exception): void
    {
        if (! $this->shouldSkip($exception)) {
            $this->logToErrorLogger($exception);
        }

        parent::exceptionHandler($exception);
    }

    /**
     * Intercepts native PHP errors/warnings/notices that CI4 converts
     * via set_error_handler (e.g. undefined array key, division by zero
     * warnings, trigger_error() calls).
     */
    public function errorHandler(int $severity, string $message, ?string $file = null, ?int $line = null)
    {
        $this->logPhpError($severity, $message, $file, $line);

        return parent::errorHandler($severity, $message, $file, $line);
    }

    /**
     * Catches fatal errors that bypass errorHandler/exceptionHandler
     * entirely (E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR), since
     * PHP terminates the script immediately for these and only the
     * shutdown function still runs.
     */
    public function shutdownHandler(): void
    {
        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR], true)) {
            (new ErrorLogger())->logMessage(
                $error['message'],
                'critical',
                'php',
                ['file' => $error['file'] ?? null, 'line' => $error['line'] ?? null]
            );
        }

        parent::shutdownHandler();
    }

    protected function logToErrorLogger(Throwable $exception): void
    {
        (new ErrorLogger())->logException($exception, $this->categorize($exception));
    }

    protected function logPhpError(int $severity, string $message, ?string $file, ?int $line): void
    {
        $config = config('ErrorLogger');

        if (! ($config->capture['php'] ?? false)) {
            return;
        }

        if (($severity & $config->minPhpErrorLevel) === 0) {
            return;
        }

        (new ErrorLogger())->logMessage(
            $message,
            $this->phpSeverityLabel($severity),
            'php',
            ['file' => $file, 'line' => $line]
        );
    }

    protected function phpSeverityLabel(int $severity): string
    {
        return match (true) {
            in_array($severity, [E_ERROR, E_USER_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR], true) => 'error',
            in_array($severity, [E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING], true) => 'warning',
            in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true) => 'deprecated',
            default => 'notice',
        };
    }

    /**
     * Rough classification by exception namespace. Not perfect, but
     * sufficient for the three categories that matter here.
     */
    protected function categorize(Throwable $exception): string
    {
        $class = get_class($exception);

        if (str_contains($class, '\\Database\\')) {
            return 'database';
        }

        if (str_starts_with($class, 'CodeIgniter\\')) {
            return 'framework';
        }

        return 'application';
    }

    /**
     * 4xx HTTP exceptions (404 Not Found, validation failures routed as
     * exceptions, etc.) are expected outcomes already handled at the
     * endpoint level — not "errors" worth persisting.
     */
    protected function shouldSkip(Throwable $exception): bool
    {
        if ($exception instanceof HTTPExceptionInterface) {
            $code = $exception->getCode();

            return $code >= 400 && $code < 500;
        }

        return false;
    }
}
