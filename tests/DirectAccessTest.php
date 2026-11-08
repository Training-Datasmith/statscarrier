<?php

require_once dirname(__FILE__) . '/StatsCarrierTestCase.php';
require_once dirname(__FILE__) . '/ChildProcess.php';

class DirectAccessTest extends StatsCarrierTestCase
{
    private function runGuardChild($relativePath)
    {
        $repoRoot = dirname(__DIR__);
        $target = $repoRoot . '/' . $relativePath;
        $script = $repoRoot . '/tests/scripts/guard-include-child.php';
        $result = ChildProcess::runPhpScript($script, array(), array($target));
        $this->assertSame(0, $result['exitCode']);
        $this->assertNotContains('SENTINEL_AFTER', $result['stdout']);
        $this->assertSame('', $result['stdout']);

        return $result;
    }

    public function testRootIndexExitsBeforeSentinel()
    {
        $this->runGuardChild('index.php');
    }

    public function testTranslationsIndexExitsBeforeSentinel()
    {
        $this->runGuardChild('translations/index.php');
    }

    public function testUpgradeIndexExitsBeforeSentinel()
    {
        $this->runGuardChild('upgrade/index.php');
    }
}
