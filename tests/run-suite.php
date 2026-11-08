<?php
error_reporting(-1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

$repoRoot = dirname(__DIR__);
$expectedTestCount = 31;
$expectedFiles = array(
    $repoRoot . '/tests/DirectAccessTest.php',
    $repoRoot . '/tests/GetDataTest.php',
    $repoRoot . '/tests/HookDisplayTest.php',
    $repoRoot . '/tests/InstallTest.php',
    $repoRoot . '/tests/ModuleMetadataTest.php',
    $repoRoot . '/tests/UpgradeTest.php',
);
$pharName = 'phpunit-4.8.36.phar';
$pharChecksum = 'ab8bc3017a64cf75a2112f5e089a1d5b0c4cc2116556d60c1af5e3f584e85c5e';
$gpgFingerprint = 'D8406D0D82947747293778314AA394086372C20A';
$diagnosticPatterns = array(
    'Notice:', 'Warning:', 'Deprecated:', 'Strict Standards:', 'Parse error:', 'Fatal error:',
);

if (!preg_match('/^5\.6\.40/', PHP_VERSION)) {
    fwrite(STDERR, "Refusing to run: PHP version must be 5.6.40, got " . PHP_VERSION . "\n");
    exit(1);
}

function failRunner($message)
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

function assertNoDiagnostics($text)
{
    global $diagnosticPatterns;
    foreach ($diagnosticPatterns as $pattern) {
        if (strpos($text, $pattern) !== false) {
            failRunner('PHPUnit output contained diagnostic: ' . $pattern);
        }
    }
}

function runCommand($command, &$stdout, &$stderr, &$exitCode)
{
    $descriptorSpec = array(
        0 => array('pipe', 'r'),
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
    );
    $process = proc_open($command, $descriptorSpec, $pipes);
    if (!is_resource($process)) {
        failRunner('Failed to execute: ' . $command);
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
}

function lintPhpFiles($repoRoot)
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($repoRoot, FilesystemIterator::SKIP_DOTS)
    );
    $phpBinary = PHP_BINARY;
    foreach ($iterator as $fileInfo) {
        if (substr($fileInfo->getFilename(), -4) !== '.php') {
            continue;
        }
        $path = $fileInfo->getPathname();
        $stdout = '';
        $stderr = '';
        $exitCode = 0;
        $cmd = escapeshellarg($phpBinary)
            . ' -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1 -l '
            . escapeshellarg($path);
        runCommand($cmd, $stdout, $stderr, $exitCode);
        if ($exitCode !== 0) {
            failRunner('Lint failed for ' . $path . "\n" . $stderr . $stdout);
        }
        assertNoDiagnostics($stdout . $stderr);
    }
}

function waitForMariaDb()
{
    $host = getenv('STATSCARRIER_DB_HOST');
    if ($host === false || $host === '') {
        $host = '127.0.0.1';
    }
    $user = getenv('STATSCARRIER_DB_USER');
    if ($user === false || $user === '') {
        $user = 'statscarrier';
    }
    $pass = getenv('STATSCARRIER_DB_PASSWORD');
    if ($pass === false) {
        failRunner('STATSCARRIER_DB_PASSWORD is required');
    }
    $deadline = time() + 60;
    while (time() < $deadline) {
        $cmd = 'mysqladmin --protocol=TCP -h ' . escapeshellarg($host)
            . ' -u ' . escapeshellarg($user)
            . ' -p' . escapeshellarg($pass)
            . ' ping 2>/dev/null';
        $stdout = '';
        $stderr = '';
        $exitCode = 0;
        runCommand($cmd, $stdout, $stderr, $exitCode);
        if ($exitCode === 0 && strpos($stdout . $stderr, 'mysqld is alive') !== false) {
            return;
        }
        usleep(500000);
    }
    failRunner('MariaDB did not become ready within 60 seconds');
}

function verifyPhpunitPhar($workDir)
{
    global $pharName, $pharChecksum, $gpgFingerprint;
    $pharPath = $workDir . '/' . $pharName;
    $ascPath = $workDir . '/' . $pharName . '.asc';
    if (!file_exists($pharPath)) {
        $cmds = array(
            'curl --fail -sS -o ' . escapeshellarg($pharPath) . ' https://phar.phpunit.de/' . $pharName,
            'curl --fail -sS -o ' . escapeshellarg($ascPath) . ' https://phar.phpunit.de/' . $pharName . '.asc',
        );
        foreach ($cmds as $cmd) {
            $stdout = '';
            $stderr = '';
            $exitCode = 0;
            runCommand($cmd, $stdout, $stderr, $exitCode);
            if ($exitCode !== 0) {
                failRunner('Failed to download PHPUnit PHAR');
            }
        }
    }
    $checksumOut = '';
    $checksumErr = '';
    $checksumExit = 0;
    runCommand('sha256sum ' . escapeshellarg($pharPath), $checksumOut, $checksumErr, $checksumExit);
    if ($checksumExit !== 0) {
        failRunner('sha256sum failed');
    }
    $parts = preg_split('/\s+/', trim($checksumOut));
    if (!isset($parts[0]) || $parts[0] !== $pharChecksum) {
        failRunner('PHPUnit PHAR checksum mismatch');
    }
    $gpgHome = $workDir . '/gnupg';
    if (!is_dir($gpgHome)) {
        mkdir($gpgHome, 0700, true);
    }
    $gpgEnv = 'GNUPGHOME=' . escapeshellarg($gpgHome);
    $importCmd = 'curl --fail -sS https://sebastian-bergmann.de/gpg.asc | ' . $gpgEnv . ' gpg --batch --import';
    $stdout = '';
    $stderr = '';
    $exitCode = 0;
    runCommand($importCmd, $stdout, $stderr, $exitCode);
    if ($exitCode !== 0) {
        failRunner('GPG key import failed');
    }
    $fpOut = '';
    $fpErr = '';
    $fpExit = 0;
    runCommand($gpgEnv . ' gpg --batch --with-colons --fingerprint', $fpOut, $fpErr, $fpExit);
    if ($fpExit !== 0 || strpos($fpOut, $gpgFingerprint) === false) {
        failRunner('Imported GPG fingerprint mismatch');
    }
    $verifyOut = '';
    $verifyErr = '';
    $verifyExit = 0;
    runCommand(
        $gpgEnv . ' gpg --batch --status-fd 1 --verify '
        . escapeshellarg($ascPath) . ' ' . escapeshellarg($pharPath),
        $verifyOut,
        $verifyErr,
        $verifyExit
    );
    if ($verifyExit !== 0) {
        failRunner('GPG verify failed: ' . $verifyOut . $verifyErr);
    }
  if (strpos($verifyOut, 'VALIDSIG') === false) {
        failRunner('GPG verify missing VALIDSIG');
    }
    if (strpos($verifyOut, 'BADSIG') !== false || strpos($verifyOut, 'ERRSIG') !== false
        || strpos($verifyOut, 'NO_PUBKEY') !== false || strpos($verifyOut, 'NODATA') !== false) {
        failRunner('GPG verify reported error status');
    }
    if (strpos($verifyOut, 'VALIDSIG ' . $gpgFingerprint) === false
        && strpos($verifyOut, 'VALIDSIG') === false) {
        failRunner('GPG verify fingerprint mismatch in VALIDSIG');
    }
    if (strpos($verifyOut, $gpgFingerprint) === false) {
        failRunner('GPG verify output missing expected primary fingerprint');
    }

    return $pharPath;
}

function writeSuiteXml($path, $bootstrap, $files)
{
    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $xml .= "<phpunit bootstrap=\"" . htmlspecialchars($bootstrap, ENT_QUOTES, 'UTF-8') . "\"\n";
    $xml .= "         colors=\"true\">\n";
    $xml .= "  <testsuites>\n";
    $xml .= "    <testsuite name=\"module-tests\">\n";
    foreach ($files as $file) {
        $xml .= '      <file>' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . "</file>\n";
    }
    $xml .= "    </testsuite>\n";
    $xml .= "  </testsuites>\n";
    $xml .= "</phpunit>\n";
    file_put_contents($path, $xml);
}

function runPhpunit($pharPath, $suitePath, $junitPath)
{
    global $expectedTestCount, $diagnosticPatterns;
    $phpBinary = PHP_BINARY;
    $cmd = escapeshellarg($phpBinary)
        . ' -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1 '
        . escapeshellarg($pharPath)
        . ' --log-junit ' . escapeshellarg($junitPath)
        . ' --configuration ' . escapeshellarg($suitePath);
    $stdout = '';
    $stderr = '';
    $exitCode = 0;
    runCommand($cmd, $stdout, $stderr, $exitCode);
    assertNoDiagnostics($stdout . $stderr);
    if ($exitCode !== 0) {
        failRunner("PHPUnit failed:\n" . $stdout . $stderr);
    }
    if (!preg_match('/OK \\(' . $expectedTestCount . ' tests, [0-9]+ assertions\\)/', $stdout)) {
        failRunner('Unexpected PHPUnit summary: ' . trim($stdout));
    }
    if (!file_exists($junitPath)) {
        failRunner('Missing JUnit output');
    }
    $junit = simplexml_load_file($junitPath);
    if (!$junit) {
        failRunner('Invalid JUnit XML');
    }
    $tests = 0;
    $failures = 0;
    $errors = 0;
    $skipped = 0;
    foreach ($junit->testsuite as $suite) {
        if (isset($suite['tests'])) {
            $tests += (int) $suite['tests'];
        }
        if (isset($suite['failures'])) {
            $failures += (int) $suite['failures'];
        }
        if (isset($suite['errors'])) {
            $errors += (int) $suite['errors'];
        }
        if (isset($suite['skipped'])) {
            $skipped += (int) $suite['skipped'];
        }
    }
    if ($tests !== $expectedTestCount || $failures !== 0 || $errors !== 0 || $skipped !== 0) {
        failRunner('JUnit summary mismatch');
    }
    echo trim($stdout) . "\n";
}

lintPhpFiles($repoRoot);
waitForMariaDb();

$discovered = glob($repoRoot . '/tests/*Test.php');
sort($discovered);
if ($discovered !== $expectedFiles) {
    failRunner('Test file list drift');
}

$workDir = sys_get_temp_dir() . '/statscarrier-suite-' . getmypid() . '-' . bin2hex(openssl_random_pseudo_bytes(4));
if (!mkdir($workDir, 0700, true)) {
    failRunner('Failed to create work directory');
}
register_shutdown_function(function () use ($workDir) {
    if (is_dir($workDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($workDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());
            } else {
                unlink($fileInfo->getPathname());
            }
        }
        rmdir($workDir);
    }
});

$pharPath = verifyPhpunitPhar($workDir);
$bootstrap = $repoRoot . '/tests/bootstrap.php';

$sortedSuite = $workDir . '/sorted.xml';
$shuffledSuite = $workDir . '/shuffled.xml';
$junitSorted = $workDir . '/junit-sorted.xml';
$junitShuffled = $workDir . '/junit-shuffled.xml';

writeSuiteXml($sortedSuite, $bootstrap, $expectedFiles);

$shuffledFiles = $expectedFiles;
srand(20261108);
shuffle($shuffledFiles);
writeSuiteXml($shuffledSuite, $bootstrap, $shuffledFiles);

echo "Running sorted suite...\n";
runPhpunit($pharPath, $sortedSuite, $junitSorted);

echo "Running shuffled suite (seed 20261108)...\n";
runPhpunit($pharPath, $shuffledSuite, $junitShuffled);

echo "Suite gate passed.\n";
