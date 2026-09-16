<?php

namespace Ephraitech\ErrorLogger\Handlers;

use CodeIgniter\Debug\Exceptions as CIExceptions;
use CodeIgniter\Exceptions\HTTPExceptionInterface;
use Ephraitech\ErrorLogger\Libraries\ErrorLogger;
use ErrorException;
use Throwable;

class ExceptionHandler extends CIExceptions
{
    public function exceptionHandler(Throwable $exception): void
    {
        if (! $this->shouldSkip($exception)) {
            $this->logToErrorLogger($exception);
        }

        parent::exceptionHandler($exception);
    }

    public function errorHandler(int $severity, string $message, ?string $file = null, ?int $line = null)
    {
        $this->logPhpError($severity, $message, $file, $line);

        return parent::errorHandler($severity, $message, $file, $line);
    }

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

    protected function shouldSkip(Throwable $exception): bool
    {
        if ($exception instanceof HTTPExceptionInterface) {
            $code = $exception->getCode();

            return $code >= 400 && $code < 500;
        }

        // Already logged in errorHandler() before CI4 escalates the
        // warning/notice into a thrown ErrorException for display —
        // skip here to avoid logging the same underlying event twice.
        if ($exception instanceof ErrorException) {
            return true;
        }

        return false;
    }
}