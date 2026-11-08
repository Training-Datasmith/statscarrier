<?php

require_once dirname(__FILE__) . '/StatsCarrierTestCase.php';

class UpgradeHookSpy
{
    public $unregisterLog = array();
    public $registerLog = array();

    public function unregisterHook($name)
    {
        $this->unregisterLog[] = $name;

        return true;
    }

    public function registerHook($name)
    {
        $this->registerLog[] = $name;

        return true;
    }
}

class UpgradeTest extends StatsCarrierTestCase
{
    public function testUpgrade201UnregistersLegacyHookAndRegistersDisplayHook()
    {
        $repoRoot = dirname(__DIR__);
        require_once $repoRoot . '/upgrade/upgrade-2.0.1.php';
        $spy = new UpgradeHookSpy();
        $result = upgrade_module_2_0_1($spy);
        $this->assertTrue($result);
        $this->assertSame(array('AdminStatsModules'), $spy->unregisterLog);
        $this->assertSame(array('displayAdminStatsModules'), $spy->registerLog);
    }
}
