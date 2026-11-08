<?php

require_once dirname(__FILE__) . '/StatsCarrierTestCase.php';
require_once dirname(__FILE__) . '/ChildProcess.php';

class HookDisplayTest extends StatsCarrierTestCase
{
    private function seedOneInRangeOrder($idOrder = 1, $idCarrier = 10, $state = 4)
    {
        PrestaShopStub::insertCarrier($this->pdo, $idCarrier, $idCarrier, 'Postal');
        PrestaShopStub::insertOrder($this->pdo, $idOrder, $idCarrier, 1, '2016-03-10 12:00:00');
        PrestaShopStub::insertOrderHistory($this->pdo, $idOrder, $idOrder, $state, '2016-03-10 11:00:00');
    }

    public function testHookRendersFilterFormAndSelectsAllByDefault()
    {
        Tools::resetRequest();
        $html = $this->newModule()->hookDisplayAdminStatsModules(array());
        $this->assertContains('name="id_order_state"', $html);
        $this->assertContains('name="submitState"', $html);
        $this->assertContains('value="Filter"', $html);
        $this->assertContains('>All</option>', $html);
        $this->assertContains('<option value="0" selected="selected">', $html);
        $this->assertContains('>Payment accepted</option>', $html);
        $this->assertContains('>Shipped</option>', $html);
        $this->assertContains('>Delivered</option>', $html);
        $this->assertContains('Carrier distribution', $html);
        $this->assertContains(
            'This graph represents the carrier distribution for your orders. You can also narrow the focus of the graph to display distribution for a particular order status.',
            $html
        );
        $this->assertNotContains('value="2" selected="selected"', $html);
        $sqlList = Db::getCapturedSql();
        $this->assertNotEmpty($sqlList);
        $sql = $sqlList[0];
        $this->assertInternalType('string', $sql);
        $this->assertContains('ps_orders', $sql);
        $this->assertContains('2016-03-01 00:00:00', $sql);
        $this->assertContains('2016-03-31 23:59:59', $sql);
        $this->assertNotContains('id_order_state', $sql);
    }

    public function testHookSelectsTheRequestedOrderState()
    {
        Tools::setValue('id_order_state', '4');
        $html = $this->newModule()->hookDisplayAdminStatsModules(array());
        $this->assertContains('<option value="4" selected="selected">', $html);
        $this->assertNotContains('<option value="0" selected="selected">', $html);
    }

    public function testHookShowsEmptyPeriodCopyWithoutGraphOrExport()
    {
        $html = $this->newModule()->hookDisplayAdminStatsModules(array());
        $this->assertContains('No valid orders have been received for this period.', $html);
        $this->assertSame(0, count(ModuleGraph::getEngineCalls()));
        $this->assertNotContains('ENGINE_SENTINEL', $html);
        $this->assertNotContains('CSV Export', $html);
        $this->assertContains(
            'This graph represents the carrier distribution for your orders.',
            $html
        );
    }

    public function testHookRendersGraphSentinelAndExportLinkWhenOrdersExist()
    {
        $this->seedOneInRangeOrder(1, 10, 4);
        Tools::setValue('id_order_state', '4');
        $html = $this->newModule()->hookDisplayAdminStatsModules(array());
        $calls = ModuleGraph::getEngineCalls();
        $this->assertSame(1, count($calls));
        $this->assertSame(array('type' => 'pie', 'option' => '4'), $calls[0]);
        $this->assertContains('ENGINE_SENTINEL', $html);
        $this->assertContains('CSV Export', $html);
        $this->assertContains('&amp;export=1', $html);
        $this->assertNotContains('No valid orders have been received for this period.', $html);
    }

    public function testHookLoadsOrderStatesForTheContextLanguage()
    {
        $ctx = Context::getContext();
        $ctx->language->id = 7;
        $html = $this->newModule()->hookDisplayAdminStatsModules(array());
        $langId = OrderState::getLastLangId();
        $this->assertInternalType('integer', $langId);
        $this->assertSame(7, $langId);
        $this->assertContains('Shipped', $html);
    }

    public function testHookEscapesRequestUriInFormActionAndExportHref()
    {
        $_SERVER['REQUEST_URI'] = '/admin/index.php?controller=AdminStats&token=a"b';
        $this->seedOneInRangeOrder();
        $html = $this->newModule()->hookDisplayAdminStatsModules(array());
        $this->assertContains('/admin/index.php?controller=AdminStats&amp;token=a&quot;b', $html);
        $this->assertNotContains('token=a"b', $html);
    }

    public function testHookCastsOrderStateFilterInTheCountQuery()
    {
        PrestaShopStub::insertCarrier($this->pdo, 10, 10, 'Postal');
        PrestaShopStub::insertCarrier($this->pdo, 20, 20, 'Pickup');
        PrestaShopStub::insertOrder($this->pdo, 1, 10, 1, '2016-03-10 12:00:00');
        PrestaShopStub::insertOrder($this->pdo, 2, 20, 1, '2016-03-11 12:00:00');
        PrestaShopStub::insertOrderHistory($this->pdo, 1, 1, 2, '2016-03-10 10:00:00');
        PrestaShopStub::insertOrderHistory($this->pdo, 2, 2, 4, '2016-03-10 10:00:00');
        Tools::setValue('id_order_state', '2 OR 1=1');
        $html = $this->newModule()->hookDisplayAdminStatsModules(array());
        $sqlList = Db::getCapturedSql();
        $sql = $sqlList[0];
        $this->assertInternalType('string', $sql);
        $this->assertContains('= 2', $sql);
        $this->assertNotContains('OR 1=1', $sql);
        $this->assertNotContains('No valid orders have been received for this period.', $html);
        $this->assertContains('ENGINE_SENTINEL', $html);
    }

    public function testExportRequestsPieCsvAndDoesNotEmitHtml()
    {
        $this->seedOneInRangeOrder();
        $capture = tempnam(sys_get_temp_dir(), 'csvcap');
        $this->assertNotSame(false, $capture);
        $script = dirname(__DIR__) . '/tests/scripts/hook-export-child.php';
        $payload = json_encode(array('export' => '1', 'id_order_state' => '9'));
        $result = ChildProcess::runPhpScript(
            $script,
            array('STATSCARRIER_CSV_CAPTURE' => $capture),
            array($payload)
        );
        $this->assertSame(0, $result['exitCode']);
        $this->assertFileExists($capture);
        $decoded = json_decode(file_get_contents($capture), true);
        $this->assertSame('pie', $decoded['type']);
        $this->assertInternalType('string', $decoded['option']);
        $this->assertSame('9', $decoded['option']);
        $this->assertSame('', $result['stdout']);
        $this->assertNotContains('SENTINEL_AFTER', $result['stdout']);
        $this->assertNotContains('Carrier distribution', $result['stdout']);
        @unlink($capture);
    }

    public function testExportRequestsPieCsvEvenWhenThePeriodIsEmpty()
    {
        $capture = tempnam(sys_get_temp_dir(), 'csvcap');
        $this->assertNotSame(false, $capture);
        $script = dirname(__DIR__) . '/tests/scripts/hook-export-child.php';
        $payload = json_encode(array('export' => '1'));
        $result = ChildProcess::runPhpScript(
            $script,
            array('STATSCARRIER_CSV_CAPTURE' => $capture),
            array($payload)
        );
        $this->assertSame(0, $result['exitCode']);
        $this->assertFileExists($capture);
        $decoded = json_decode(file_get_contents($capture), true);
        $this->assertSame('pie', $decoded['type']);
        $this->assertFalse($decoded['option']);
        $this->assertSame('', $result['stdout']);
        @unlink($capture);
    }
}
