<?php

require_once dirname(__FILE__) . '/StatsCarrierTestCase.php';
require_once dirname(__FILE__) . '/ChildProcess.php';

class ModuleMetadataTest extends StatsCarrierTestCase
{
    public function testConstructorPublishesIdentityFields()
    {
        $module = $this->newModule();
        $this->assertSame('statscarrier', $module->name);
        $this->assertSame('administration', $module->tab);
        $this->assertSame('2.1.0', $module->version);
        $this->assertSame('PrestaShop', $module->author);
        $this->assertSame(0, $module->need_instance);
    }

    public function testConstructorPublishesTranslatedCopyAndCompliancy()
    {
        $module = $this->newModule();
        $this->assertSame('Carrier distribution', $module->displayName);
        $expectedDescription = 'Enrich your stats, add a pie chart illustrating your carriers' . "\xE2\x80\x99" . ' distribution.';
        $this->assertSame($expectedDescription, $module->description);
        $this->assertSame('1.7.1.0', $module->ps_versions_compliancy['min']);
        $this->assertSame(_PS_VERSION_, $module->ps_versions_compliancy['max']);
    }

    public function testConfigManifestAgreesOnNameVersionAndNeedInstance()
    {
        $module = $this->newModule();
        $configPath = dirname(__DIR__) . '/config.xml';
        $xml = simplexml_load_file($configPath);
        $this->assertSame((string) $module->name, (string) $xml->name);
        $this->assertSame((string) $module->version, (string) $xml->version);
        $this->assertSame('0', (string) $xml->need_instance);
        $this->assertSame('0', (string) $xml->is_configurable);
    }

    public function testConfigManifestAuthorAgreesWithModuleAuthor()
    {
        $module = $this->newModule();
        $configPath = dirname(__DIR__) . '/config.xml';
        $xml = simplexml_load_file($configPath);
        $this->assertSame((string) $module->author, (string) $xml->author);
    }

    public function testModuleFileExitsWhenPsVersionIsUndefined()
    {
        $script = dirname(__DIR__) . '/tests/scripts/statscarrier-unguarded-child.php';
        $result = ChildProcess::runPhpScript($script);
        $this->assertSame(0, $result['exitCode']);
        $this->assertSame('', $result['stdout']);
        $this->assertNotContains('SENTINEL_AFTER', $result['stdout']);
    }
}
