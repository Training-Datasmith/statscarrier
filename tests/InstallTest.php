<?php

require_once dirname(__FILE__) . '/StatsCarrierTestCase.php';

class InstallTest extends StatsCarrierTestCase
{
    protected function setUp()
    {
        parent::setUp();
        Module::resetModuleHooks();
    }

    public function testInstallRegistersDisplayAdminStatsModules()
    {
        Module::setInstallReturn(true);
        Module::setRegisterHookReturn(true);
        $module = $this->newModule();
        $this->assertTrue($module->install());
        $this->assertSame(1, Module::getInstallCallCount());
        $this->assertSame(array('displayAdminStatsModules'), Module::getRegisteredHooks());
    }

    public function testInstallStopsWhenParentInstallFails()
    {
        Module::setInstallReturn(false);
        Module::setRegisterHookReturn(true);
        $module = $this->newModule();
        $this->assertFalse($module->install());
        $this->assertSame(1, Module::getInstallCallCount());
        $hooks = Module::getRegisteredHooks();
        $this->assertInternalType('array', $hooks);
        $this->assertSame(0, count($hooks));
    }

    public function testInstallReturnsFalseWhenHookRegistrationFails()
    {
        Module::setInstallReturn(true);
        Module::setRegisterHookReturn(false);
        $module = $this->newModule();
        $this->assertFalse($module->install());
        $this->assertSame(array('displayAdminStatsModules'), Module::getRegisteredHooks());
    }
}
