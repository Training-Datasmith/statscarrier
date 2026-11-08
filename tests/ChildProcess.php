<?php

class ChildProcess
{
    private static $diagnosticPatterns = array(
        'Notice:',
        'Warning:',
        'Deprecated:',
        'Strict Standards:',
        'Parse error:',
        'Fatal error:',
    );

    public static function assertNoDiagnostics($stdout, $stderr)
    {
        $combined = $stdout . "\n" . $stderr;
        foreach (self::$diagnosticPatterns as $pattern) {
            if (strpos($combined, $pattern) !== false) {
                self::fail('Child PHP process emitted diagnostic: ' . $pattern . "\nstdout:\n" . $stdout . "\nstderr:\n" . $stderr);
            }
        }
    }

    public static function fail($message)
    {
        throw new RuntimeException($message);
    }

    public static function runPhpScript($scriptPath, $extraEnv = array(), $scriptArgs = array())
    {
        $php = defined('PHP_BINARY') ? PHP_BINARY : 'php';
        $cmd = escapeshellarg($php)
            . ' -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1 '
            . escapeshellarg($scriptPath);
        foreach ($scriptArgs as $arg) {
            $cmd .= ' ' . escapeshellarg($arg);
        }

        $descriptorSpec = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );
        $env = self::buildChildEnv($extraEnv);
        $process = proc_open($cmd, $descriptorSpec, $pipes, null, $env);
        if (!is_resource($process)) {
            self::fail('Failed to start child process');
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertNoDiagnostics($stdout, $stderr);

        return array(
            'exitCode' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
        );
    }

    public static function phpBinary()
    {
        return defined('PHP_BINARY') ? PHP_BINARY : 'php';
    }

    private static function buildChildEnv($extraEnv)
    {
        $env = array();
        $passthrough = array(
            'PATH', 'HOME', 'LANG', 'LC_ALL', 'TMPDIR', 'TEMP', 'TMP',
            'STATSCARRIER_DB_HOST', 'STATSCARRIER_DB_PORT', 'STATSCARRIER_DB_NAME',
            'STATSCARRIER_DB_USER', 'STATSCARRIER_DB_PASSWORD',
        );
        foreach ($passthrough as $key) {
            $value = getenv($key);
            if ($value !== false) {
                $env[$key] = $value;
            }
        }
        foreach ($extraEnv as $key => $value) {
            $env[$key] = $value;
        }

        return $env;
    }
}
