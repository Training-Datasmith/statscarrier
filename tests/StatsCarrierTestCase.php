<?php

class StatsCarrierTestCase extends PHPUnit_Framework_TestCase
{
    /** @var PDO */
    protected $pdo;

    protected function setUp()
    {
        parent::setUp();
        PrestaShopStub::resetAll();
        $_SERVER['REQUEST_URI'] = '/admin/index.php?controller=AdminStats';
        $this->pdo = PrestaShopStub::getSharedPdo();
        PrestaShopStub::truncateTables($this->pdo);
    }

    protected function newModule()
    {
        return new statscarrier();
    }

    protected function nameToTotalMap($legends, $values)
    {
        $this->assertInternalType('array', $legends);
        $this->assertInternalType('array', $values);
        $this->assertSame(count($legends), count($values));
        $map = array();
        $count = count($legends);
        for ($i = 0; $i < $count; $i++) {
            $this->assertTrue(is_numeric($values[$i]));
            $map[$legends[$i]] = (int) $values[$i];
        }

        return $map;
    }
}
