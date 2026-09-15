<?php

namespace Ephraitech\ErrorLogger\Libraries;

use Config\Services;
use Throwable;

class ErrorLogger
{
    protected \Ephraitech\ErrorLogger\Config\ErrorLogger $config;

    public function __construct()
    {
        $this->config = config('ErrorLogger');
    }

    /**
     * Log a caught (or otherwise available) exception/throwable.
     *
     * @param Throwable   $exception      The exception being recorded.
     * @param string      $category       One of: application, framework, database, php.
     * @param string      $severity       Free-text severity label (e.g. error, critical).
     * @param array       $extraContext   Extra request/app data to store alongside the error.
     *                                    Any key matching config()->redactFields is masked.
     * @param string|null $userIdentifier Caller-supplied identifier (user id, email, API key id).
     *                                    Deliberately a plain string — this package has no
     *                                    knowledge of any host app's user table.
     */
    public function logException(
        Throwable $exception,
        string $category = 'application',
        string $severity = 'error',
        array $extraContext = [],
        ?string $userIdentifier = null
    ): bool {
        if (! $this->shouldCapture($category)) {
            return false;
        }

        return $this->write([
            'category'        => $category,
            'severity'        => $severity,
            'message'         => $exception->getMessage(),
            'exception_class' => get_class($exception),
            'file'            => $exception->getFile(),
            'line'            => $exception->getLine(),
            'trace'           => $exception->getTraceAsString(),
            'context'         => $extraContext,
            'user_identifier' => $userIdentifier,
        ]);
    }

    /**
     * Log a plain message with no underlying Throwable — used for native
     * PHP errors/warnings/notices, which PHP's error handler receives as
     * a level + string, not an exception object.
     */
    public function logMessage(
        string $message,
        string $severity = 'error',
        string $category = 'application',
        array $extraContext = [],
        ?string $userIdentifier = null
    ): bool {
        if (! $this->shouldCapture($category)) {
            return false;
        }

        return $this->write([
            'category'        => $category,
            'severity'        => $severity,
            'message'         => $message,
            'exception_class' => null,
            'file'            => null,
            'line'            => null,
            'trace'           => null,
            'context'         => $extraContext,
            'user_identifier' => $userIdentifier,
        ]);
    }

    /**
     * Whether this category is currently enabled for capture, per the
     * master switch and the per-category toggles in config.
     */
    protected function shouldCapture(string $category): bool
    {
        if (! $this->config->enabled) {
            return false;
        }

        return $this->config->capture[$category] ?? false;
    }

    /**
     * Build the final row and attempt to persist it. Falls back to
     * CodeIgniter's normal file log if the DB write itself fails, so a
     * DB outage never means the error is lost entirely.
     */
    protected function write(array $data): bool
    {
        $row = [
            'category'        => $data['category'],
            'severity'        => $data['severity'],
            'message'         => $data['message'],
            'exception_class' => $data['exception_class'],
            'file'            => $data['file'],
            'line'            => $data['line'],
            'trace'           => $data['trace'],
            'context'         => $this->buildContext($data['context']),
            'url'             => $this->currentUrl(),
            'method'          => $this->currentMethod(),
            'ip_address'      => $this->currentIp(),
            'user_identifier' => $data['user_identifier'],
            'environment'     => defined('ENVIRONMENT') ? ENVIRONMENT : null,
            'resolved'        => 0,
            'resolved_at'     => null,
            'created_at'      => date('Y-m-d H:i:s'),
        ];

        try {
            db_connect()->table($this->config->table)->insert($row);

            return true;
        } catch (Throwable $dbException) {
            if ($this->config->fallbackToFileLogOnDbFailure) {
                log_message(
                    'critical',
                    'ErrorLogger DB write failed: {dbError}. Original error: {original}',
                    [
                        'dbError'  => $dbException->getMessage(),
                        'original' => $data['message'],
                    ]
                );
            }

            return false;
        }
    }

    /**
     * JSON-encode context after redacting sensitive keys. Returns null
     * for an empty array so we store SQL NULL rather than the string "[]".
     */
    protected function buildContext(array $context): ?string
    {
        if ($context === []) {
            return null;
        }

        return json_encode($this->redact($context), JSON_UNESCAPED_SLASHES);
    }

    /**
     * Recursively mask any key listed in config()->redactFields,
     * case-insensitively, at any depth of the context array.
     */
    protected function redact(array $data): array
    {
        $redactFields = array_map('strtolower', $this->config->redactFields);

        array_walk($data, function (&$value, $key) use ($redactFields) {
            if (is_array($value)) {
                $value = $this->redact($value);

                return;
            }

            if (in_array(strtolower((string) $key), $redactFields, true)) {
                $value = '[REDACTED]';
            }
        });

        return $data;
    }

    /**
     * Returns the current IncomingRequest, or null when running in a CLI
     * context (spark commands, cron jobs) where there is no HTTP request.
     */
    protected function getRequest(): ?\CodeIgniter\HTTP\IncomingRequest
    {
        if (is_cli()) {
            return null;
        }

        $request = Services::request();

        return $request instanceof \CodeIgniter\HTTP\IncomingRequest ? $request : null;
    }

    protected function currentUrl(): ?string
    {
        $request = $this->getRequest();

        return $request ? (string) $request->getUri() : null;
    }

    protected function currentMethod(): ?string
    {
        $request = $this->getRequest();

        return $request ? $request->getMethod() : null;
    }

    protected function currentIp(): ?string
    {
        $request = $this->getRequest();

        return $request ? $request->getIPAddress() : null;
    }
}
