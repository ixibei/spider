<?php
namespace Ixibei\Spider\Tests;
use Ixibei\Spider\Html;

include '../autoload.php';
/*class HtmlListTest extends \PHPUnit_Framework_TestCase {

    public function testGetList(){
        $this->assertEquals(1,HtmlList::getList());
    }

}*/
$collectConfig = [
    'url'       =>  'http://www.qulishi.com/fengyun/',//要测试的地址
    'test'      =>  true,//是否测试模式
    'json_test' =>  false,//测试模式 是否json输出
    'regular'   =>  [
        'id'                =>  '1',//如果该测试存放在数据库，则为该规则的id
        'name'              =>  '趣历史采集',//改规则的名字
        'encode'            =>  'utf-8',//采集网站的编码
        'list'              =>  'div.j31List[0]',//列表页位置
        'list_cricle'       =>  'dl',//列表页循环标识
        'list_url'          =>  'dt[0]/a[0]',//详情页url
        'url'               =>  'http://www.qulishi.com',//采集地址
        'load_js'           =>  1,//是否渲染页面
        'print_list_html'   =>  0,//是否打印列表页面，用于测试
        'assign_url'        =>  'http://www.qulishi.com/article/202011/460690.html',//指定链接测试
        'url_add_param'     =>   true,//采集url是否添加参数
        'is_proxy'          =>   false,//是否使用代理
        'html_replace'      =>   '白起##spider',//多内容替换
        'mult_list_field'   =>  '[{"name":"name","tags":"dt/a","val":"innertext","replace":"","end_pos":"","start_pos":"","forbid_tags":"","strip_tags":""},{"name":"img","tags":"dd/a/img","val":"src","replace":"责任编辑：","end_pos":"","start_pos":"","forbid_tags":"","strip_tags":""},{"name":"time","tags":"dd[1]/span","val":"innertext","replace":"","end_pos":"","start_pos":"","forbid_tags":"","strip_tags":""}]',//自定义采集字段

        //详情页面
        'detail'            =>  'div.n18_art_con[0]',//详情标识 支持多个查找 用 | 分割
        'detail_page'       =>  'div.page1[0]',//详情页分页标识
        'detail_forbid_tag' =>  'div',//禁止的标签 以多个空格分割
        'strip_tags'        =>  '',//需要移除的标签 以多个空格分割
        'no_strip_tags'     =>  0,//不去除标签样式
        'print_detail_html' =>  0,//是否打印列表页面，用于测试
        'review'            =>  '0',//是否需要审核 0 无需审核直接发布 1 审核发布
        'detail_replace'    =>  '免责声明：以上内容源自网络，版权归原作者所有，如有侵犯您的原创版权请告知，我们将尽快删除相关内容。',//要替换的关键词
        'start_pos'         =>  '网络配图',//一篇文章最开始的截取位置
        'end_pos'           =>  '长平之战，千古罪证',//一篇文章最后的截取位置 支持多个截取位置 用 | 分割
        'forbid_first_page' =>  0,//禁止首页采集 1 开启
        'remove_empty_p'    =>  1,//去除空段落
        'is_self_news'      =>  1,//检测分页是否存在不是本新闻的链接 防止出现死链情况
        'mult_content'      =>  '[
                                    {"name":"div.zl07ListParent[0]/ul[0]","val":"innertext","code":"$name = \'\';\r\n    $dom = new simple_html_dom($html);\r\n     foreach ($dom->find(\'li\') as $key=>$val){\r\n            $name .= $val->find(\'h4 a\',0)->innertext.\'|\';\r\n        }\r\n        $return[\'name1\'] = $name;\r\n        return $return;" },
                                    {"name":"ul.n18_imglist[0]","val":"innertext","code":"$name = \'\';\r\n       $dom = new simple_html_dom($html);\r\n   foreach ($dom->find(\'li\') as $key=>$val){\r\n            $name .= $val->find(\'span.imgtt\',0)->innertext.\'|\';\r\n        }\r\n        $return[\'name2\'] = $name;\r\n        return $return;"}
        ]',//支持详情页返回多个内容 限定于第一页中的内容
        'mult_detail_field'   =>  '[{"name":"from","tags":"div.n18_art_info[0]/a[0]","val":"innertext","replace":"","end_pos":"","start_pos":"","forbid_tags":"","strip_tags":""},{"name":"add_user","tags":"div.n18_art_info[0]/span[0]","val":"innertext","replace":"责任编辑：","end_pos":"","start_pos":"","forbid_tags":"","strip_tags":""},{"name":"download","tags":"div.shartFixed[0]/script[0]","val":"innertext","replace":"","end_pos":"?","start_pos":".src=\'","forbid_tags":"","strip_tags":""}]',//自定义采集字段
    ]
];
$htmlObj = new Html();
$htmlObj->addCondition($collectConfig);
$htmlObj->getHtmlList();