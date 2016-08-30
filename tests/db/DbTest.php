<?php
namespace hapn\tests\db;

use hapn\db\Db;

/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/7/10 0:06
 * @copyright : 2016 huimang.com
 * @filesource: DbTest.php
 */
class DbTest extends \PHPUnit_Framework_TestCase
{
    private $conf = [
        'db_pool' => [
            'huimang' => [
                'ip' => '192.168.56.101',
                'port' => 3306,
                'user' => 'root',
                'pass' => 'huimang',
                'charset' => 'utf8',
            ],
        ],
        'dbs' => [
            'hm_blog' => 'huimang',
        ]
    ];

    /**
     * Test Add Record
     */
    public function testAdd()
    {
        Db::init($this->conf);

        $db = Db::get('hm_blog');
        $db->table('article')
           ->autoInc('article_id')
           ->saveBody([
               'title' => 'test article',
               'user_id' => 40,
               'create_time' => time(),
               'rv_content' => 1,
               'cate_id' => 22,
               'comment_num' => 1,
               'status' => 1,
           ])
           ->insert();
        var_dump($db->getLastInsertId());
    }

    /**
     * Test Db
     * @
     */
    public function testGet()
    {
        Db::init($this->conf);

        $rows = Db::get('hm_blog')
                  ->table('article')
                  ->where([
                      'status' => 1
                  ])->get();
        var_dump(count($rows));
    }

    public function testUpdate()
    {
        Db::init($this->conf);
        $db = Db::get('hm_blog');
        $db->table('article')
           ->where([
               'article_id' => 10000,
           ])
           ->saveBody([
               'status' => -1,
           ])
           ->update();
        $row = $db->table('article')
                  ->where(['article_id' => 10000])
                  ->getOne();
        $this->assertEquals($row['status'], -1);
    }

    public function testDelete()
    {
        Db::init($this->conf);
        $db = Db::get('hm_blog');
        $db->table('article')
            ->where([
                'article_id' => 10000,
            ])
            ->delete();

        $row = $db->table('article')
            ->where(['article_id' => 10000])
            ->getOne();
        $this->assertEquals($row, false);

        // delete records those article_id bigger than 10000
        $db->table('article')
            ->where(['article_id>' => 10000])
            ->delete();

        $num = $db->table('article')
            ->where('1=1')
            ->getCount();
        $this->assertEquals($num, 0);

        $db->table('article')
           ->autoInc('article_id')
           ->saveBody([
               'article_id' => 10000,
               'title' => 'test article',
               'user_id' => 40,
               'create_time' => time(),
               'rv_content' => 1,
               'cate_id' => 22,
               'comment_num' => 1,
               'status' => 1,
           ])
           ->insert();
    }

    public function testInsertOrIgnore()
    {
        Db::init($this->conf);
        $db = Db::get('hm_blog');
        $result = $db->table('article')
           ->autoInc('article_id')
           ->saveBody([
               'article_id' => 10000,
               'title' => 'test article',
               'user_id' => 40,
               'create_time' => time(),
               'rv_content' => 2,
               'cate_id' => 22,
               'comment_num' => 1,
               'status' => 1,
           ])
           ->insertIgnore();
        var_dump($result);
    }
}
