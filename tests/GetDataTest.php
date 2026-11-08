<?php

require_once dirname(__FILE__) . '/StatsCarrierTestCase.php';

class StatscarrierDataProbe extends statscarrier
{
    public function exposeGetData($layers)
    {
        $this->getData($layers);
    }

    public function exposeValues()
    {
        return $this->_values;
    }

    public function exposeLegend()
    {
        return $this->_legend;
    }

    public function exposeTitles()
    {
        return $this->_titles;
    }

    public function reflectOption()
    {
        $ref = new ReflectionProperty('statscarrier', 'option');
        $ref->setAccessible(true);

        return $ref;
    }
}

class GetDataTest extends StatsCarrierTestCase
{
    private function probe()
    {
        return new StatscarrierDataProbe();
    }

    private function seedStandardAggregation()
    {
        PrestaShopStub::insertCarrier($this->pdo, 10, 10, 'Postal');
        PrestaShopStub::insertCarrier($this->pdo, 11, 10, 'Postal');
        PrestaShopStub::insertCarrier($this->pdo, 20, 20, 'Pickup');
        PrestaShopStub::insertOrder($this->pdo, 1, 10, 1, '2016-03-01 00:00:00');
        PrestaShopStub::insertOrder($this->pdo, 2, 11, 1, '2016-03-15 12:00:00');
        PrestaShopStub::insertOrder($this->pdo, 3, 20, 1, '2016-03-31 23:59:59');
        PrestaShopStub::insertOrder($this->pdo, 4, 20, 1, '2016-02-29 23:59:59');
        PrestaShopStub::insertOrder($this->pdo, 6, 20, 1, '2016-04-01 00:00:00');
        foreach (array(1, 2, 3, 4, 6) as $idOrder) {
            PrestaShopStub::insertOrderHistory($this->pdo, $idOrder, $idOrder, 4, '2016-03-10 10:00:00');
        }
    }

    private function seedLatestStatePair()
    {
        PrestaShopStub::insertCarrier($this->pdo, 10, 10, 'Postal');
        PrestaShopStub::insertCarrier($this->pdo, 20, 20, 'Pickup');
        PrestaShopStub::insertOrder($this->pdo, 1, 10, 1, '2016-03-10 12:00:00');
        PrestaShopStub::insertOrder($this->pdo, 3, 20, 1, '2016-03-12 09:00:00');
        PrestaShopStub::insertOrderHistory($this->pdo, 1, 1, 2, '2016-03-10 10:00:00');
        PrestaShopStub::insertOrderHistory($this->pdo, 2, 1, 4, '2016-03-10 11:00:00');
        PrestaShopStub::insertOrderHistory($this->pdo, 3, 3, 2, '2016-03-12 09:00:00');
    }

    public function testSetOptionCastsNumericStringAndIgnoresLayers()
    {
        $probe = $this->probe();
        $probe->setOption('12abc', 9);
        $ref = $probe->reflectOption();
        $stored = $ref->getValue($probe);
        $this->assertInternalType('integer', $stored);
        $this->assertSame(12, $stored);
    }

    public function testSetOptionCastsNullFalseAndZeroToIntegerZero()
    {
        $probe = $this->probe();
        $ref = $probe->reflectOption();
        $probe->setOption(null);
        $this->assertSame(0, $ref->getValue($probe));
        $probe->setOption(false);
        $this->assertSame(0, $ref->getValue($probe));
        $probe->setOption('0');
        $this->assertSame(0, $ref->getValue($probe));
    }

    public function testGetDataAggregatesCarrierReferenceInsideFixedDateWindow()
    {
        $this->seedStandardAggregation();
        $probe = $this->probe();
        $probe->setOption(0);
        $probe->exposeGetData(1);
        $map = $this->nameToTotalMap($probe->exposeLegend(), $probe->exposeValues());
        $this->assertSame(array('Postal' => 2, 'Pickup' => 1), $map);
        $sqlList = Db::getCapturedSql();
        $this->assertNotEmpty($sqlList);
        $sql = $sqlList[count($sqlList) - 1];
        $this->assertInternalType('string', $sql);
        $this->assertContains('ps_carrier', $sql);
        $this->assertContains('2016-03-01 00:00:00', $sql);
        $this->assertContains('2016-03-31 23:59:59', $sql);
        $this->assertSame(
            'Percentage of orders listed by carrier.',
            $probe->exposeTitles()['main']
        );
    }

    public function testGetDataExcludesOrdersOutsideShopRestriction()
    {
        $this->seedStandardAggregation();
        PrestaShopStub::insertOrder($this->pdo, 5, 10, 2, '2016-03-10 00:00:00');
        PrestaShopStub::insertOrderHistory($this->pdo, 5, 5, 4, '2016-03-10 10:00:00');
        $probe = $this->probe();
        $probe->setOption(0);
        $probe->exposeGetData(1);
        $map = $this->nameToTotalMap($probe->exposeLegend(), $probe->exposeValues());
        $this->assertSame(2, $map['Postal']);
        $sqlList = Db::getCapturedSql();
        $sql = $sqlList[count($sqlList) - 1];
        $this->assertContains('o.id_shop = 1', $sql);
    }

    public function testGetDataWithZeroOptionDoesNotFilterByState()
    {
        $this->seedLatestStatePair();
        $probe = $this->probe();
        $probe->setOption(0);
        $probe->exposeGetData(1);
        $map = $this->nameToTotalMap($probe->exposeLegend(), $probe->exposeValues());
        $this->assertArrayHasKey('Postal', $map);
        $this->assertArrayHasKey('Pickup', $map);
        $sqlList = Db::getCapturedSql();
        $sql = $sqlList[count($sqlList) - 1];
        $this->assertNotContains('id_order_state', $sql);
    }

    public function testGetDataKeepsOnlyLatestOrderState()
    {
        $this->seedLatestStatePair();
        $probe = $this->probe();
        $probe->setOption(4);
        $probe->exposeGetData(1);
        $map = $this->nameToTotalMap($probe->exposeLegend(), $probe->exposeValues());
        $this->assertSame(array('Postal' => 1), $map);
        $probe2 = $this->probe();
        $probe2->setOption(2);
        $probe2->exposeGetData(1);
        $map2 = $this->nameToTotalMap($probe2->exposeLegend(), $probe2->exposeValues());
        $this->assertSame(array('Pickup' => 1), $map2);
    }

    public function testGetDataBreaksStateTiesByHighestHistoryId()
    {
        PrestaShopStub::insertCarrier($this->pdo, 10, 10, 'Postal');
        PrestaShopStub::insertOrder($this->pdo, 1, 10, 1, '2016-03-20 12:00:00');
        PrestaShopStub::insertOrderHistory($this->pdo, 100, 1, 3, '2016-03-20 08:00:00');
        PrestaShopStub::insertOrderHistory($this->pdo, 101, 1, 8, '2016-03-20 08:00:00');
        $probe = $this->probe();
        $probe->setOption(8);
        $probe->exposeGetData(1);
        $map = $this->nameToTotalMap($probe->exposeLegend(), $probe->exposeValues());
        $this->assertSame(array('Postal' => 1), $map);
        $probe2 = $this->probe();
        $probe2->setOption(3);
        $probe2->exposeGetData(1);
        $map2 = $this->nameToTotalMap($probe2->exposeLegend(), $probe2->exposeValues());
        $this->assertSame(array(), $map2);
    }

    public function testGetDataCastsStoredOptionBeforeInterpolatingSql()
    {
        $this->seedLatestStatePair();
        $probe = $this->probe();
        $ref = $probe->reflectOption();
        $ref->setValue($probe, '4abc');
        $probe->exposeGetData(1);
        $sqlList = Db::getCapturedSql();
        $sql = $sqlList[count($sqlList) - 1];
        $this->assertInternalType('string', $sql);
        $this->assertContains('= 4', $sql);
        $this->assertNotContains('4abc', $sql);
    }

    public function testGetDataWithNoMatchingOrdersLeavesEmptySeriesAndSetsTitle()
    {
        $probe = $this->probe();
        $probe->exposeGetData(1);
        $values = $probe->exposeValues();
        $legend = $probe->exposeLegend();
        $this->assertInternalType('array', $values);
        $this->assertInternalType('array', $legend);
        $this->assertSame(0, count($values));
        $this->assertSame(0, count($legend));
        $sqlList = Db::getCapturedSql();
        $this->assertNotEmpty($sqlList);
        $sql = $sqlList[count($sqlList) - 1];
        $this->assertContains('ps_carrier', $sql);
        $this->assertSame(
            'Percentage of orders listed by carrier.',
            $probe->exposeTitles()['main']
        );
    }

    public function testGetDataIgnoresLayersArgument()
    {
        $this->seedStandardAggregation();
        $probe = $this->probe();
        $probe->setOption(0);
        $probe->exposeGetData(1);
        $map1 = $this->nameToTotalMap($probe->exposeLegend(), $probe->exposeValues());
        $this->assertNotEmpty($map1);
        $probe2 = $this->probe();
        $probe2->setOption(0);
        $probe2->exposeGetData(4);
        $map2 = $this->nameToTotalMap($probe2->exposeLegend(), $probe2->exposeValues());
        $this->assertSame($map1, $map2);
    }
}
