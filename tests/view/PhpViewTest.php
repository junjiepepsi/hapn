<?php
namespace hapn\tests\view;

use hapn\web\view\PhpView;

/**
 *
 *
 * @author    : ronnie
 * @since     : 2016/7/8 2:14
 * @copyright : 2016 huimang.com
 * @filesource: PhpViewTest.php
 */
class PhpViewTest extends \PHPUnit_Framework_TestCase
{
    public function testView()
    {
        $content = <<<HTML
<!doctype html>
<html lang="zh-cn">
<head>
<meta charset="UTF-8">
<title>Document</title>
</head>
<body>
  <h1><?=\$this->v['title']?></h1>
</body>
</html>
HTML;
        $tpl = __DIR__ . '/test.phtml';
        file_put_contents($tpl, $content);

        $view = new \hapn\web\view\PhpView();
        $title = "中国人的标题";
        $view->set('title', $title);
        $content = $view->build($tpl);

        $tplContent = <<<HTML
<!doctype html>
<html lang="zh-cn">
<head>
<meta charset="UTF-8">
<title>Document</title>
</head>
<body>
  <h1>{$title}</h1>
</body>
</html>
HTML;

        $this->assertEquals($content, $tplContent);
    }

    private $vars = [
        'title' => 'test title',
        'list' => [
            [
                'name' => 'foo',
                'url' => '/arti/foo',
            ],
            [
                'name' => 'bar',
                'url' => '/arti/bar',
            ],
        ]
    ];

    /**
     * Test setLayout
     */
    public function testLayout()
    {
        $viewRoot = __DIR__ . '/tpl/';

        $view = new PhpView();
        $view->init(
            [
                'tplDir' => $viewRoot,
            ]
        );
        $view->setLayout($viewRoot . 'layout.phtml');
        $view->setArray($this->vars);
        $view->display($viewRoot . 'body.phtml');
    }

    /**
     * Test set multi layouts
     */
    public function testMultiLayouts()
    {
        $viewRoot = __DIR__ . '/tpl/';

        $view = new PhpView();
        $view->init(
            [
                'tplDir' => $viewRoot,
            ]
        );
        $view->setLayout($viewRoot . 'layout.phtml', $viewRoot . 'layout.sub.phtml');
        $view->setArray($this->vars);
        $view->display($viewRoot . 'body.phtml');
    }
}
