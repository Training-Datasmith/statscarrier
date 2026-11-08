<?php

class Context
{
    public $language;
    public $employee;
    public $shop;

    private static $instance;

    public static function getContext()
    {
        if (!self::$instance) {
            self::$instance = new Context();
            self::$instance->language = new stdClass();
            self::$instance->language->id = 1;
            self::$instance->employee = new stdClass();
            self::$instance->employee->id = 1;
            self::$instance->shop = new stdClass();
            self::$instance->shop->id = 1;
        }

        return self::$instance;
    }
}

class Module
{
 	public $name;
    public $tab;
    public $version;
    public $author;
    public $need_instance;
    public $displayName;
    public $description;
    public $ps_versions_compliancy = array();
    public $context;

    protected static $installReturn = true;
    protected static $registerHookReturn = true;
    protected static $installCallCount = 0;
    protected static $registeredHooks = array();

    public function __construct()
    {
        $this->context = Context::getContext();
    }

    public static function resetModuleHooks()
    {
        self::$installReturn = true;
        self::$registerHookReturn = true;
        self::$installCallCount = 0;
        self::$registeredHooks = array();
    }

    public static function setInstallReturn($value)
    {
        self::$installReturn = (bool) $value;
    }

    public static function setRegisterHookReturn($value)
    {
        self::$registerHookReturn = (bool) $value;
    }

    public static function getInstallCallCount()
    {
        return self::$installCallCount;
    }

    public static function getRegisteredHooks()
    {
        return self::$registeredHooks;
    }

    public function install()
    {
        self::$installCallCount++;

        return self::$installReturn;
    }

    public function registerHook($name)
    {
        self::$registeredHooks[] = $name;

        return self::$registerHookReturn;
    }

    public function unregisterHook($name)
    {
        if (!isset($this->unregisterLog)) {
            $this->unregisterLog = array();
        }
        $this->unregisterLog[] = $name;

        return true;
    }

    public function trans($key, $params = array(), $domain = null)
    {
        return $key;
    }
}

abstract class ModuleGraph extends Module
{
    protected $_values = array();
    protected $_legend = array();
    protected $_titles = array('main' => null, 'x' => null, 'y' => null);
    protected $_employee;

    protected static $engineCalls = array();

    public static function resetGraphState()
    {
        self::$engineCalls = array();
    }

    public static function getEngineCalls()
    {
        return self::$engineCalls;
    }

    public static function getDateBetween($employee = null)
    {
        return " '2016-03-01 00:00:00' AND '2016-03-31 23:59:59' ";
    }

    public function engine($params)
    {
        self::$engineCalls[] = $params;

        return 'ENGINE_SENTINEL';
    }

    protected function csvExport($datas)
    {
        $capture = getenv('STATSCARRIER_CSV_CAPTURE');
        if ($capture !== false && $capture !== '') {
            file_put_contents($capture, json_encode($datas));
            exit(0);
        }
        throw new RuntimeException('csvExport reached in-process without capture env');
    }
}

class Shop
{
    const SHARE_ORDER = 'share_order';

    public static function addSqlRestriction($share = false, $alias = null)
    {
        if ($share === self::SHARE_ORDER && $alias === 'o') {
            return ' AND o.id_shop = 1 ';
        }

        return '';
    }
}

class Tools
{
    private static $values = array();

    public static function resetRequest()
    {
        self::$values = array();
    }

    public static function setValue($key, $value)
    {
        self::$values[$key] = $value;
    }

    public static function getValue($key)
    {
        if (!array_key_exists($key, self::$values)) {
            return false;
        }

        return self::$values[$key];
    }

    public static function safeOutput($string)
    {
        $string = strip_tags($string);

        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

class OrderState
{
    private static $lastLangId = null;

    public static function getOrderStates($idLang)
    {
        self::$lastLangId = $idLang;

        return array(
            array('id_order_state' => 2, 'name' => 'Payment accepted'),
            array('id_order_state' => 4, 'name' => 'Shipped'),
            array('id_order_state' => 8, 'name' => 'Delivered'),
        );
    }

    public static function getLastLangId()
    {
        return self::$lastLangId;
    }
}

class Db
{
    private static $pdo;
    private static $instances = array();
    private static $capturedSql = array();

    public static function setPdo(PDO $pdo)
    {
        self::$pdo = $pdo;
        self::$instances = array();
    }

    public static function resetCapturedSql()
    {
        self::$capturedSql = array();
    }

    public static function getCapturedSql()
    {
        return self::$capturedSql;
    }

    public static function getInstance($slave = false)
    {
        $key = $slave ? 'slave' : 'master';
        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new Db();
        }

        return self::$instances[$key];
    }

    public function getRow($sql)
    {
        self::$capturedSql[] = $sql;
        if (!self::$pdo) {
            return false;
        }
        $stmt = self::$pdo->query($sql);
        if ($stmt === false) {
            return false;
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return false;
        }

        return $row;
    }

    public function executeS($sql)
    {
        self::$capturedSql[] = $sql;
        if (!self::$pdo) {
            return false;
        }
        $stmt = self::$pdo->query($sql);
        if ($stmt === false) {
            return false;
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

class PrestaShopStub
{
    private static $sharedPdo;

    public static function setSharedPdo(PDO $pdo)
    {
        self::$sharedPdo = $pdo;
    }

    public static function getSharedPdo()
    {
        return self::$sharedPdo;
    }

    public static function resetAll()
    {
        Module::resetModuleHooks();
        ModuleGraph::resetGraphState();
        Tools::resetRequest();
        Db::resetCapturedSql();
        OrderState::getOrderStates(1);
    }

    public static function setPdo(PDO $pdo)
    {
        Db::setPdo($pdo);
    }

    public static function truncateTables(PDO $pdo)
    {
        $pdo->exec('DELETE FROM `' . _DB_PREFIX_ . 'order_history`');
        $pdo->exec('DELETE FROM `' . _DB_PREFIX_ . 'orders`');
        $pdo->exec('DELETE FROM `' . _DB_PREFIX_ . 'carrier`');
    }

    public static function insertCarrier(PDO $pdo, $idCarrier, $idReference, $name)
    {
        $stmt = $pdo->prepare(
            'INSERT INTO `' . _DB_PREFIX_ . 'carrier` (`id_carrier`, `id_reference`, `name`) VALUES (?, ?, ?)'
        );
        $stmt->execute(array($idCarrier, $idReference, $name));
    }

    public static function insertOrder(PDO $pdo, $idOrder, $idCarrier, $idShop, $dateAdd)
    {
        $stmt = $pdo->prepare(
            'INSERT INTO `' . _DB_PREFIX_ . 'orders` (`id_order`, `id_carrier`, `id_shop`, `date_add`) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute(array($idOrder, $idCarrier, $idShop, $dateAdd));
    }

    public static function insertOrderHistory(PDO $pdo, $idHistory, $idOrder, $idState, $dateAdd)
    {
        $stmt = $pdo->prepare(
            'INSERT INTO `' . _DB_PREFIX_ . 'order_history` (`id_order_history`, `id_order`, `id_order_state`, `date_add`) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute(array($idHistory, $idOrder, $idState, $dateAdd));
    }
}
