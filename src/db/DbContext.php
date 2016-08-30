<?php
namespace hapn\db;

/**
 * Db's Context
 *
 * @author    : ronnie
 * @since     : 2016/7/8 23:23
 * @copyright : 2016 jiehun.com.cn
 * @filesource: DbContext.php
 */
class DbContext
{
    public static $db_pool;
    public static $dbconf;
    // setting of table split, and the format is:
    // array(table=>array(field,array(method=>arg)));
    //
    public static $splits;
    public static $guidDB;
    public static $guidTable;
    public static $readOnly = false;
    public static $logFunc;
    public static $testMode = false;
    public static $defaultDB;
    // Long query's minimal time
    public static $longQueryTime = 0;

    // Splited by ID's mod
    const MOD_SPLIT = 1;
    // Splited by id's division
    const DIV_SPLIT = 2;
    // Splited by month
    const MONTH_SPLIT = 3;
    // Splited b year
    const YEAR_SPLIT = 4;
    // Splited by day
    const DAY_SPLIT = 5;
}
