<?php

namespace Ephraitech\ErrorLogger\Config;

use CodeIgniter\Config\BaseConfig;

class ErrorLogger extends BaseConfig
{
    /**
     * Master switch. If false, the handler still catches errors
     * (so the app doesn't crash) but writes nothing anywhere.
     */
    public bool $enabled = true;

    /**
     * Table used to persist error records. Overridable per-app in
     * case of naming conflicts or multi-tenant table prefixes.
     */
    public string $table = 'error_logs';

    /**
     * Which categories of error this package should capture.
     * Lets a host app disable a category without touching handler code.
     *
     * - 'application' : uncaught exceptions thrown by app code
     * - 'framework'   : CI4 framework-level errors (routing, views, etc.)
     * - 'database'    : query/connection errors from the DB layer
     * - 'php'         : native PHP errors/warnings/notices (E_WARNING, etc.)
     */
    public array $capture = [
        'application' => true,
        'framework'   => true,
        'database'    => true,
        'php'         => true,
    ];

    /**
     * Minimum PHP error severity to capture when 'php' capture is enabled.
     * Uses PHP's native E_* constants. Default skips E_NOTICE/E_DEPRECATED
     * noise and captures warnings and above.
     */
    // public int $minPhpErrorLevel = E_WARNING;
    /**
     * Bitmask of PHP error levels to capture when 'php' capture is enabled —
     * same convention as PHP's native error_reporting() setting. Default
     * captures errors and warnings, excludes notices and deprecation noise.
     */
    public int $minPhpErrorLevel = E_ALL & ~E_NOTICE & ~E_USER_NOTICE & ~E_DEPRECATED & ~E_USER_DEPRECATED;

    /**
     * If writing to the database fails (e.g. DB itself is down), fall back
     * to CodeIgniter's normal log file so the error isn't lost entirely.
     */
    public bool $fallbackToFileLogOnDbFailure = true;

    /**
     * How many days of error_logs rows to keep. Used later by a cleanup
     * CLI command (not built yet — noting the setting now so the schema
     * in Step 3 already has what it needs).
     */
    public int $retentionDays = 30;

    /**
     * Fields considered sensitive and stripped from any captured
     * request payload/context before it's persisted (case-insensitive).
     */
    public array $redactFields = [
        'password',
        'password_confirm',
        'token',
        'authorization',
        'secret',
    ];
}
