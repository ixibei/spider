<?php

namespace Ixibei\Spider;
use Ixibei\Spider\Functions\ParsePhpCode;
use Ixibei\Spider\Functions\simple_html_dom;

class Html extends Base
{
    public $dom = '';
    public $data = [];//采集的网页信息
    public $content = '';//url内容
    public $baseNum = '';//如果存在分页，则此为第一原始页数字
    public $forbidStop = false;//停止检测分页

    /**
     * 抓取列表内容
     * @return array|bool
     */
    public function getHtmlList()
    {
        $this->regular->load_js = isset($this->regular->load_js) ? $this->regular->load_js : false;
        $this->content = $this->getContent($this->url,$this->regular->encode,$this->regular->url_add_param,$this->regular->is_proxy,$this->url,$this->regular->load_js);
        if(!$this->content){
            return $this->_parseError('can\'t connect this url');
        }

        if($this->regular->html_replace){
            $this->content = $this->_replace($this->content,$this->regular->html_replace);
        }

        $return = [];

        if(isset($this->regular->list_ruku) && $this->regular->list_ruku && $this->regular->detail_replace){ //自定义程序采集
            $parsePhpCode = new ParsePhpCode();
            $fileName = 'SelfCode'.$this->regular->id;
            $parsePhpCode->code($this->regular->detail_replace,$fileName);
            $filePath = __DIR__.'/Cache/'.$fileName.'.php';
            require_once($filePath);
            $mod = new $fileName($this->data);
            $return = $mod->regular($this->content);
            if($this->test){
                dd($return);
            }
        } else { //标准采集
            $this->dom = new simple_html_dom($this->content);
            $listObj = $this->analyticRule($this->regular->list,$this->dom);
            if(!$listObj){
                return $listObj;
            }

            $circle = $listObj->find($this->regular->list_cricle);
            if(!$circle){
                return $this->_parseError('列表页循环标识未找到 '.$this->regular->list_cricle);
            }

            foreach($circle as $key=>$val){
                if(isset($this->regular->list_cricle_skip) && strlen($this->regular->list_cricle_skip) > 0 && $this->regular->list_cricle_skip == $key){
                    continue;
                }
                $tmpArr = [];

                //详情页内容url 有些变态的网站就是循环的a标签 所以加此判断
                if($val->href){
                    $detailLinkObj = $val;
                } else {
                    $detailLinkObj = $this->analyticRule($this->regular->list_url,$val);
                    if(!$detailLinkObj){
                        continue;
                    }
                }

                //详情页url
                $tmpArr['url'] = strpos($detailLinkObj->href,'http') === false ? $this->regular->url.'/'.ltrim($detailLinkObj->href,'/') : $detailLinkObj->href;//防止使用相对路径

                //多字段采集
                if($this->regular->mult_list_field){
                    $this->dom = $val;
                    $this->_parseMultField($this->regular->mult_list_field,'list');
                    if(isset($this->data['multContent']) && $this->data['multContent']){
                        $tmpArr = array_merge($tmpArr,$this->data['multContent']);
                    }
                }

                //测试模式则判断是否有指定连接测试
                if($this->test){
                    if($this->regular->assign_url){
                        $tmpArr['url'] = $this->regular->assign_url;
                    }
                    return $this->getHtmlDetail($tmpArr);
                } else{
                    $return[] = $tmpArr;
                }
            }
        }

        return $return;
    }

    /**
     * 抓取详情页的内容
     * @param $arr 条件数组
     * @return array|bool
     */
    public function getHtmlDetail($arr)
    {
        $this->data['url'] = $arr['url'];
        $this->url = trim($arr['url']);
        //直接入库模式
        if(!isset($this->regular->list_ruku) || !$this->regular->list_ruku){
            $this->regular->load_js = isset($this->regular->load_js) ? $this->regular->load_js : false;
            $content = $this->getContent($this->url,$this->regular->encode,$this->regular->url_add_param,$this->regular->is_proxy,$this->url,$this->regular->load_js);
            if(!$content){
                return false;
            }

            if($this->regular->html_replace){
                $content = $this->_replace($content,$this->regular->html_replace);
            }

            $this->dom = new simple_html_dom($content);

            $detailObj = $this->_parseMultMark($this->dom,$this->regular->detail);//处理内容详情文字
            if(!$detailObj) {
                return $this->_parseError('找不到内容详情，或内容详情字段为空！');
            }

            //截取开始和结束的制定标识
            $this->data['content'] = $this->_cutEndStartPos($detailObj->innertext,$this->regular->end_pos,$this->regular->start_pos);

            //提前切割好需要替换的字符
            $this->regular->detail_replace = is_array($this->regular->detail_replace) ? $this->regular->detail_replace : array_filter(explode("\r\n",$this->regular->detail_replace));

            //如果文章存在分页则 寻找下一页内容
            if($this->regular->detail_page){
                $pagesObj = $this->analyticRule($this->regular->detail_page,$this->dom);
                if($pagesObj){
                    $pageHtml = $pagesObj->innertext;
                    $this->baseNum = self::_extractNum($this->url);//获取第一页原始数字
                    $nextUrl = $this->_haveNextPage($pageHtml,$this->url);
                    if($nextUrl){
                        $this->_parsePage($nextUrl);
                    }
                }
            }

            //替换内容中不需要的词语 第一次替换
            $this->data['content'] = $this->_replace($this->data['content']);

            //需要禁止标签里的标签里的内容 或者 需要禁止内容中的class 或者是id
            $forbidElement = array_filter(explode('&&',$this->regular->detail_forbid_tag));
            $this->data['content'] = $this->forbidClassAndTag($forbidElement,$this->data['content']);

            if($this->regular->strip_tags == 'all'){
                $this->data['content'] = strip_tags($this->data['content']);
            } else {
                $stripTags = [];
                if($this->regular->strip_tags){
                    $stripTags = explode('&&',$this->regular->strip_tags);
                }
                $this->data['content'] = $this->htmlpurifier($this->data['content'],$stripTags);//去除乱七八糟的标签 链接
            }

            //将分页符替换成 本站需要的分页符
            $this->data['content'] = str_replace('$$$$','<hr/>',$this->data['content']);
            //去除空段落
            $this->data['content'] = $this->_parseContent($this->data['content']);
            //处理指定标签相关属性
            $this->data['content'] = $this->_addTagsAttribute($this->data['content']);
            //替换内容中不需要的词语 第二次替换
            $this->data['content'] = $this->_replace($this->data['content']);

            //自定义采集字段
            if($this->regular->mult_detail_field){
                $this->_parseMultField($this->regular->mult_detail_field,'detail');
            }

            //解析PHP代码
            if($this->regular->mult_content){
                $this->_parseMultContent();
            }
        }

        if($this->test){
            if($this->json_test){
                echo json_encode($this->data,JSON_UNESCAPED_UNICODE );
            } else{
                echo  '<title>'.$this->regular->name.'</title><pre>';
                $this->data['url'] = '<span style="color:blue;cursor: pointer;" class="copyUrl" data-clipboard-text="'.$this->data['url'].'" onclick="copyFromUrl(\'copyUrl\')" >'.$this->data['url'].'</span>';
                print_r($this->data);
                echo '<script>';
                echo file_get_contents(__DIR__.'/js/jquery.js');
                echo file_get_contents(__DIR__.'/js/clipboard.min.js');
                echo file_get_contents(__DIR__.'/js/function.js');
                echo '</script>';
            }
            exit;
        } else {
            return $this->data;
        }
    }

    /**
     * 处理多种可能的标示（有些网站可能的样式定位可能有很多种，比如说有些是div.content 而有些则是div.news 处理方式则是多种 div.content|div.news ...）
     * @dom obj simple_html_dom 加载的内容
     * @$element string|int 网站定位元素
     * @return bool
     */
    private function _parseMultMark($dom,$element)
    {
        $mark = explode('||',$element);
        $obj = false;//返回的对象
        if($mark){
            foreach ($mark as $key=>$val){
                $obj = $this->analyticRule($val,$dom);
                if($obj) break;//如果正确找到标示，则退出
            }
        }
        return $obj;
    }

    /**
     * 解析PHP代码 寻找第一页中需要的另外一下内容信息
     */
    private function _parseMultContent()
    {
        $mult_contents = json_decode($this->regular->mult_content,true);
        $parsePhpCode = new ParsePhpCode();
        $this->data['multContent'] = isset($this->data['multContent']) ? $this->data['multContent'] : [];
        foreach($mult_contents as $key=>$val){
            $obj = $this->analyticRule($val['name'],$this->dom);
            if(!$obj) {
                $this->data['content'.$key] = '';
                continue;
            }
            $attr = $val['val'];
            $this->data['content'.$key] = trim($obj->$attr);
            if(isset($val['code']) && $val['code']){
                $fileName = 'Code'.$this->regular->id.$key;
                $parsePhpCode->code($val['code'],$fileName);
                $filePath = __DIR__.'/Cache/'.$fileName.'.php';
                require_once($filePath);
                $mod = new $fileName($this->data);
                $contents = $mod->regular($this->data['content'.$key]);
                $this->data['multContent'] = array_merge($this->data['multContent'],$contents);
            }
        }
    }


    /**
     * 将规则传递到此进行解析，寻找结果
     * @param $rule 解析规则
     * @param $dom 文档对象
     * @return bool 返回对象
     */
    public function analyticRule($rule,$dom)
    {
        $rules = array_filter(explode('/',$rule));
        $return = false;
        foreach ($rules as $key=>$val){
            $val = trim($val);
            if(!$val) {
                continue;
            }
            $tmpArr = $this->explodeAnalyticRule($val);
            if(!$return){ //第一次赋值，确保返回值不是 $dom
                $return = $dom;
            }
            $return = $return->find($tmpArr[0],$tmpArr[1]);
            if(!$return){
                $return = $this->_parseError('没有找到该规则：'.$rule);
                break;
            }
        }
        return $return;
    }

    /**
     * 返回数组，切割规则
     * @param $str 待切割的字符串
     * @return array
     */
    public function explodeAnalyticRule($str)
    {
        $return = [];
        $first = preg_replace('/\[\d+\]/','',$str);
        $return[0] = $first;

        preg_match_all('/\[(.*?)\]/',$str,$tmpArr);
        if(isset($tmpArr[1])){
            $last = array_pop($tmpArr[1]);
            if(is_numeric($last)){
                $return[1] = $last;
            }
        }

        $return[1] = isset($return[1]) ? $return[1] : 0;
        return $return;
    }

    /**
     * 自定义字段采集
     */
    private function _parseMultField($fields,$type)
    {
        $mult_fields = json_decode($fields);
        foreach($mult_fields as $key=>$val){
            $obj = $this->analyticRule($val->tags,$this->dom);
            if(!$obj) {
                $this->data['multContent'][$val->name] = '';
                continue;
            }
            $attr = $val->val;
            $this->data['multContent'][$val->name] = trim($obj->$attr);
            //前后位置截取
            if($val->end_pos || $val->start_pos){
                $this->data['multContent'][$val->name] = $this->_cutEndStartPos($this->data['multContent'][$val->name],$val->end_pos,$val->start_pos);
            }
            //替换内容
            if($val->replace){
                $this->regular->detail_replace = array_filter(explode('&&',$val->replace));
                $this->data['multContent'][$val->name] = $this->_replace($this->data['multContent'][$val->name]);
            }
            //去除指定的标签内容
            if($val->forbid_tags){
                $forbidElement = array_filter(explode('&&',$val->forbid_tags));
                $this->data['multContent'][$val->name] = $this->forbidClassAndTag($forbidElement,$this->data['multContent'][$val->name]);
            }
            //去除标签
            if($val->strip_tags){
                if($val->strip_tags == 'all'){
                    $this->data['multContent'][$val->name] = strip_tags($this->data['multContent'][$val->name]);
                } else {
                    $stripTags = explode('&&',$val->strip_tags);
                    $this->data['multContent'][$val->name] = $this->htmlpurifier($this->data['multContent'][$val->name],$stripTags,false);
                }
            }
            if(isset($val->trim) && $val->trim){
                $this->data['multContent'][$val->name] = preg_replace('/(^(　| |\xC2\xA0|&nbsp;)+|(　| |\xC2\xA0|&nbsp;)+$)/','',$this->data['multContent'][$val->name]);
            }
            if(isset($val->prefix) && $val->prefix){
                $this->data['multContent'][$val->name] = $val->prefix.$this->data['multContent'][$val->name];
            }
            if(isset($val->suffix) && $val->suffix){
                $this->data['multContent'][$val->name] .= $val->suffix;
            }
            if(isset($val->cover) && $val->cover){
                $this->data['multContent'][$val->name] = $val->cover;
            }
            //解析PHP代码
            if(isset($val->code) && $val->code){
                $parsePhpCode = new ParsePhpCode();
                $fileName = 'Field'.ucfirst($type).$this->regular->id.$key;
                $parsePhpCode->code($val->code,$fileName);
                $filePath = __DIR__.'/Cache/'.$fileName.'.php';
                require_once($filePath);
                $mod = new $fileName($this->data);
                $contents = $mod->regular($this->data['multContent'][$val->name]);
                $this->data['multContent'][$val->name] = $contents;
            }
        }
    }

    /**
     * 循环抓取解析下一个分页的内容
     * @param $nextUrl 下一页的url
     * @return bool|string
     */
    private function _parsePage($nextUrl)
    {
        $this->regular->load_js = isset($this->regular->load_js) ? $this->regular->load_js : false;
        $content = $this->getContent($nextUrl,$this->regular->encode,$this->regular->url_add_param,$this->regular->is_proxy,$nextUrl,$this->regular->load_js);
        if(!$content){
            return '';
        }
        $dom = new simple_html_dom(str_replace('\\','',$content));

        $detailObj = $this->_parseMultMark($dom,$this->regular->detail);//处理内容详情文字
        if(!$detailObj) return false;//如果发生错误，则从页也开始往下一页不在采集

        //如果被采集站点含有分页，则加上分页标识，在后续程序中替换$$$$ 为<hr/>标签(此步防止采集网站中存在<hr/> 标签，扰乱本网站的分页情况)
        $this->data['content'] .= '$$$$'.$this->_cutEndStartPos($detailObj->innertext,$this->regular->end_pos,$this->regular->start_pos);
        $pages = $this->analyticRule($this->regular->detail_page,$dom);
        if($pages){
            $pageHtml = $pages->innertext;
            $nextUrl = $this->_haveNextPage($pageHtml,$nextUrl);
            if($nextUrl){
                $this->_parsePage($nextUrl);
            }
        }
    }

    /**
     * 判断是否还有下一页 取出分页html 提取分页链接
     * @param $html 分页html
     * @param $currentPage 当前是第几页
     * @return bool|mixed|string
     */
    private function _haveNextPage($html,$currentPage)
    {
        if(!$html) return false;
        $currentNum = self::_extractNum($currentPage);//当前的页码
        $dom = new simple_html_dom($html);
        $nextUrl = false;
        foreach($dom->find('a') as $val){
            //找不到连接则跳过
            $href = $val->href;
            if(!$href) {
                continue;
            }
            //如果链接中不是数字跳过
            $num = self::_extractNum($href);
            if(!$num){
                continue;
            }
            if($currentNum<$num){
                //如果存在禁止首个链接采集（第一页是原来的页面 类似于 http://www.yuexw.com/ent/42/1470490.htm 跳过）
                if($this->regular->forbid_first_page && !$this->forbidStop){
                    $this->forbidStop = true;
                    continue;
                }
                //判断此链接是否是这篇文章了
                if($this->regular->is_self_news){
                    $href = $this->isSelfNews($href);
                    //如果不是这篇文章，则跳过改链接采集
                    if(!$href){
                        continue;
                    }
                }
                $nextUrl = $href;//获取到下一页分页链接则停止循环搜索
                break;
            }
        }
        if($nextUrl){
            if(count(explode('/',$nextUrl)) == 1){ //针对此种url解析为正常路径 http://www.qulishi.com/news/201610/129119.html 以免其他网站使用的相对路径
                $arrs = array_filter(explode('/',$currentPage));
                array_pop($arrs);
                $baseUrl = implode('/',$arrs);

                $nextUrls = array_filter(explode('/',$nextUrl));
                $nextUrl = array_pop($nextUrls);
                $nextUrl = str_replace(':',':/',$baseUrl.'/'.$nextUrl);
            } else {
                if(strpos($nextUrl,'http') === false){
                    $nextUrl = $this->regular->url.$nextUrl;//针对没有http情况下的路径 例如 http://yule.youbian.com/news65166/
                }
            }
        }
        return $nextUrl;
    }

    /**
     * 验证当前链接是否属于本条新闻  防止分页中插入其他新闻链接而导致的死循环
     * @param $href 当前需要验证的链接
     * @return bool|正确的网址
     */
    private function isSelfNews($href)
    {
        preg_match("/\d+/i",$href,$match);
        if(isset($match[0]) && $match[0] == $this->baseNum){
            return $href;
        }
        return false;
    }

    /**
     * 把字符串中的数字抽取出来
     * @param $string 要抽取的字符串
     * @return string
     */
    private static function _extractNum($string)
    {
        $strings = explode('/',$string);//取这个链接最后部分用于比较大小，会避免一些错误
        $string = array_pop($strings);
        $num = 0;
        for($i=0;$i<strlen($string);$i++) {
            if (is_numeric($string[$i])) {
                $num .= $string[$i];
            }
        }
        return intval($num);
    }

    /**
     * 需要禁止内容中的class 或者是id 或者禁止的标签
     * @param array $forbids 要禁止的标签或者是 class
     * @param $content 内容
     * @return string
     */
    public function forbidClassAndTag($forbids = [],$content)
    {
        $forbids = array_merge(['style','script'],$forbids);//默认去除style  和script 这两个标签
        $contentObj = new simple_html_dom($content);
        foreach ($forbids as $val){
            foreach($contentObj->find($val) as $v){
                $v->innertext = '';
            }
        }
        return $contentObj->innertext;
    }


    /**
     * 替换内容中不需要的词语
     * @param $content 要替换的内容
     * @return mixed
     */
    public function _replace($content,$replace = false)
    {
        if($replace){
            $replace = explode('&&',$replace);
        } else {
            $replace = $this->regular->detail_replace;
        }
        if($replace){
            foreach($replace as $key=>$val){
                $val = trim($val);
                if(!$val){
                    continue;
                }
                $replace = array_filter(explode('##',$val));
                $replace[1] = isset($replace[1]) ? $replace[1] : '';
                //如果是正则替换
                if(strpos($replace[0],'.*?') !== false || strpos($replace[0],'/is') !== false){
                    $content = preg_replace($replace[0],$replace[1],$content);
                } else {
                    $content = str_ireplace($replace[0],$replace[1],$content);
                }
            }
        }
        $content = str_replace('<p></p>','',$content);//去除空p标签对
        return $content;
    }

    /**
     * 去除空段落
     * 将两个p标签包含的情况替换未单个标签
     * @param $content 要处理的内容
     * @return string
     */
    public function _parseContent($content)
    {
        $contentObj = new simple_html_dom($content);
        foreach ($contentObj->find('p') as $key=>$val) {
            $val->innertext = str_replace(['　','&nbsp;'],['  ',' '],$val->innertext);//中文空格替换为两个英文空格，使用trim则会替换某些特殊字符如《 【
            $val->innertext = trim($val->innertext);//去除段前空格

            //去除所有空段落
            if(isset($this->regular->remove_empty_p)
                && $this->regular->remove_empty_p
                && strpos($val->innertext,'iframe') === false
                && strpos($val->innertext,'img') === false
                && strpos($val->innertext,'embed') === false
                && !$this->TB(strip_tags($val->innertext)))
            {
                $val->outertext = '';
                continue;
            }
        }
        //将两个p标签包含的情况替换未单个标签
        $content = str_replace(['<p><p','</p></p>'],['<p','</p>'],$contentObj->innertext);
        return $content;
    }

    /**
     * 截取内容前后位置
     * @param $content 内容
     * @return string
     */
    public function _cutEndStartPos($content,$endPos,$startPos)
    {
        //开始的截取位置 舍去此位置前内容
        $startPos = array_filter(explode('||',$startPos));
        if(is_array($startPos) && $startPos){
            foreach ($startPos as $key=>$val){
                $pos = mb_stripos($content,$val);
                if($pos !== false){//如果循环中找到要分割的字符串，则截取并停止循环
                    $valLen = mb_strlen($val);
                    $content = mb_substr($content,$pos+$valLen);
                    break;
                }
            }
        }
        //最后的截取位置 舍去此位置后内容
        $endPos = array_filter(explode('||',$endPos));
        if(is_array($endPos) && $endPos){
            foreach ($endPos as $key=>$val){
                $pos = mb_stripos($content,$val);
                if($pos !== false){//如果循环中找到要分割的字符串，则截取并停止循环
                    $content = mb_substr($content,0,$pos);
                    break;
                }
            }
        }
        return $content;
    }

    /**
     * 替换alt标签内容
     * 给iframe embed 增加相关属性 将src="http:// 更改为// 避免https网站无法加载内容
     * @param $content
     * @return mixed|null|string|string[]
     */
    public function _addTagsAttribute($content)
    {
        $content = preg_replace('/alt="(.*?)"/','',$content);
        $content = preg_replace('/<iframe(.*?)src="https{0,1}:\/\//is','<iframe$1src="//',$content);
        $content = preg_replace('/<embed(.*?)src="https{0,1}:\/\//is','<embed$1src="//',$content);
        $content = str_replace(['<iframe ','<embed '],['<iframe width="100%" height="100%" frameborder="0" ','<embed width="100%" height="100%" '],$content);
        return $content;
    }

    /**
     * 返回最终的URL地址
     * @param $url
     * @return mixed
     */
    public function traceUrl($url,$refer = '')
    {
        $url = htmlspecialchars_decode($url);
        if(strpos($url,'http') === false){
            return $url;
        }

        //检测后缀是否是 .apk 结尾 若是 .apk 结尾，则直接返回下载地址
        $urls = explode('.',$url);
        $last = array_pop($urls);
        if($last == 'apk'){
            return $url;
        }

        //检测是否是苹果官网的地址
        if(strpos($url,'apple.com') !== false){
            return $url;
        }

        $refer = $refer ? $refer : (isset($this->data['url']) ? $this->data['url'] : '');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_REFERER, $refer);//模拟来路
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.26 Safari/537.36 Core/1.63.5967.400 LBBROWSER/10.1.3622.400');
        curl_setopt($ch, CURLOPT_NOBODY, true);// 不需要页面内容
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);// 不直接输出
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);// 返回最后的 Location
        curl_exec($ch);
        $info = curl_getinfo($ch,CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        return $info;
    }
}