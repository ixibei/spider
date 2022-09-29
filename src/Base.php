<?php

namespace Ixibei\Spider;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Ixibei\Spider\Functions\Curl;
use HTMLPurifier_Config,HTMLPurifier;
use DB;
use Log;
use JonnyW\PhantomJs\Client;
use Cache;

class Base
{
    public $request_retry_times = 2;//网络错误重连次数
    public $test = false;//是否测试模式
    public $regular = '';//提取网页的规则
    public $url = '';//要链接的url
    public $errorTimes = 0;

    /**
     * 初始化采集必须加载此方法传入参数
     * 将要测试的数据生成对象
     * @param array $param
     */
    public function addCondition(array $param)
    {
        $this->test = isset($param['test']) ? $param['test'] : false;
        $this->json_test = isset($param['json_test']) ? $param['json_test'] : false;
        $this->url = $param['url'];
        $this->regular = (object)$param['regular'];
        $this->regular->load_js = isset($this->regular->load_js) ? $this->regular->load_js : false;
        $this->regular->user_agent = isset($this->regular->user_agent) ? $this->regular->user_agent : false;
    }

    public function _parseError($msg)
    {
        $this->errorTimes++;
        if(!$this->regular){
            $str = $msg;
        } else {
            $str = 'ERROR-'.$this->errorTimes.' -- <b style="color: red;">[ MES ]：</b> '.$msg.' <b style="color: red;">[ URL ]：</b> '.$this->url.'<br>';
        }

        if($this->test){
            if(!$this->json_test){
                echo ($str);
            }
        }
        //如果是laravel框架引用，则将错误信息保存在数据库中
        if(class_exists('DB')){
            if($this->regular){
                $str = strip_tags($str);
                $str = addslashes(htmlspecialchars($str));
                DB::table('spider_log')->insert(['content'=>$str,'status'=>2,'type'=>1,'spider_id'=>$this->regular->id]);
                DB::statement("update spider set failure_times=failure_times+1,all_failure_times=all_failure_times+1,failure_reason='".$str."' where id=".$this->regular->id);
            } else {
                $path = storage_path().'/logs/Spider/SpiderError.log';
                Log::useDailyFiles($path);
                Log::info($str);
            }
        } else{
            $log = new logger('ERROR');
            $path = __DIR__.'/../logs/';
            if(!is_dir($path)){
                mkdir($path,0777,true);
            }
            $saveTo = $path.'SpiderError-'.date('Y-m-d',time()).'.log';
            $log->pushHandler(new StreamHandler($saveTo, Logger::WARNING));
            $log->warning($str);
        }
        return false;
    }

    /**
     * @param $dirtyHtml html代码
     * @param array $stripTags 要去除的标签
     * @param bool $isMerge 是否合并默认标签
     * @return mixed
     */
    public function htmlpurifier($dirtyHtml,$stripTags = [],$isMerge = true)
    {
        $defaultDom = ['a','div','span','hr','b'];
        $stripTags = $isMerge ? array_merge($defaultDom,$stripTags) : $stripTags;

        $config = HTMLPurifier_Config::createDefault();
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('AutoFormat.RemoveEmpty.RemoveNbsp', true);
        $config->set('HTML.ForbiddenElements', $stripTags);
        $config->set('HTML.SafeObject',true);
        $config->set('HTML.SafeEmbed',true);
        $config->set('HTML.SafeIframe',true);
        $config->set('URI.SafeIframeRegexp','%^(http://|https://|//)%');
        $config->set('AutoFormat.AutoParagraph', $isMerge);
        $config->set('HTML.ForbiddenAttributes', ['id', 'width', 'height','style','alt', 'on*','class','title','border','vspace','frameborder','align','valign']);
        $purifier = new HTMLPurifier($config);
        return $purifier->purify($dirtyHtml);
    }

    /**
     * 获取网页内容
     * @param $url 网站url 可以让其他程序自由调取该函数
     * @param string $encoding 网站编码
     * @param bool $urlAddParam 是否在访问url得时候加上参数
     * @param bool $isProxy 是否代理
     * @param bool $httpRefer 是否指定httpRefer
     * @param bool $loadJs 是否加载js效果
     * @param bool $userAgent 指定用户请求头
     * @return string
     */
    public function getContent($url, $encoding = 'utf-8',$urlAddParam = true,$isProxy = false,$httpRefer = false,$loadJs = false,$userAgent = false)
    {
        $url = html_entity_decode($url);
        if($urlAddParam){
            if(strpos($url,'?') !== false){
                $url .= '&cmt='.mt_rand(0,1000);
            } else {
                $url .= '?cmt='.mt_rand(0,1000);
            }
        }
        $this->url = $url;//为了输出打印，URL而赋值

        $cip = '220.181.108.'.mt_rand(0,254);
        $xip = '220.181.32.'.mt_rand(0,254);

        if($loadJs){
            return $this->getContentLoadJs($url,$encoding,$httpRefer,$cip,$xip);
        }

        $curl = new Curl();
        $try_times = 0;

        do {
            try {
                if($isProxy && class_exists('DB')){
                    $proxyIp = $this->getProxyIp();
                    if($proxyIp){
                        $proxyIp = json_decode($proxyIp);
                        $curl->proxy($proxyIp->ip,$proxyIp->port,$proxyIp->socket,$proxyIp->username,$proxyIp->password);
                    }
                }
                if($httpRefer){
                    $curl->setHeader('CURLOPT_REFERER',$httpRefer);
                }
                $userAgent = $userAgent ?  'Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html)' : $userAgent;
                $response = $curl
                    ->cookies(array('JSESSIONID' => 'constant-session-1'))
                    ->setHeader('User-Agent', $userAgent)
                    ->setHeader('CLIENT-IP',$cip)//模拟请求ip
                    ->setHeader('X-FORWARDED-FOR',$xip)//模拟请求ip
                    ->get($url);
            } catch (Exception $e) {
                sleep(1);
            }
        } while ((!isset($response) || !$response) && ++$try_times < $this->request_retry_times);

        if (!isset($response) || !$response) {
            if (isset($e) && $e) {
                $str = $e->getMessage();
                $this->_parseError($str);
                return false;
            } else {
                $str = 'curl unknown error  URL：'.$url.' 无返回值';
                $this->_parseError($str);
                return false;
            }
        }

        if ($curl->getStatus() >= 300 || $curl->getStatus() < 200) {
            $str = 'curl return code error ：'.$curl->getStatus().' URL：'.$url;
            $this->_parseError($str);
            return false;
        }

        if ($encoding == false) {
            $headers = $curl->getHeader();

            if (isset($headers['content-type'])) {
                if (preg_match('~charset=([^"]+)~', $headers['content-type'], $encoding)) {
                    $encoding = $encoding[1];
                } else {
                    $encoding = 'utf-8';
                }
            } else {
                if (preg_match('~charset=([^"]+)"~', $response, $encoding)) {
                    $encoding = $encoding[1];
                } else {
                    $encoding = 'utf-8';
                }
            }

        }
        if ($encoding != 'utf-8') {
            $response = mb_convert_encoding($response, 'utf-8', $encoding);
        }
        return $response;
    }

    public function getContentLoadJs($url,$encoding,$httpRefer,$cip,$xip)
    {
        if(PHP_OS == 'WINNT'){
            $file = __DIR__.'/../phantomjs/phantomjs.exe';
        } else{
            $var = shell_exec('getconf LONG_BIT');
            if($var == 64){
                $file = __DIR__.'/../phantomjs/phantomjs-64';
            } else {
                $file = __DIR__.'/../phantomjs/phantomjs-32';
            }
        }
        $client = Client::getInstance();
        $client->getEngine()->addOption('--load-images=false');
        $client->getEngine()->addOption('--ignore-ssl-errors=true');

        $client->getEngine()->setPath($file);
        $request = $client->getMessageFactory()->createRequest($url, 'GET');
        $request->setTimeout(10000);//10s超时
        $request->setDelay(5);//5s加载时间
        $request->addSetting('userAgent','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_2) AppleWebKit/600.4.10 (KHTML, like Gecko) Version/8.0.4 Safari/600.4.10');
        $request->addHeader('Referer',$httpRefer);
        $request->addHeader('CLIENT-IP',$cip);
        $request->addHeader('X-FORWARDED-FOR',$xip);

        $response = $client->getMessageFactory()->createResponse();
        $client->send($request, $response);
        if($response->getStatus() === 200) {
            $content = $response->getContent();
            if ($encoding != 'utf-8') {
                $content = mb_convert_encoding($content, 'utf-8', $encoding);
            }
            return $content;
        } else {
            $str = '没有获取到内容，状态码'.$response->getStatus();
            $this->_parseError($str);
            return false;
        }
    }

    public function getProxyIp()
    {
        $proxyIp = Cache::get('spider:proxy:ip');
        if($proxyIp){
            return $proxyIp;
        } else {
            $interface = DB::table('system')->where('key','PROXY_IP')->first();
            $interface = $interface->value;
            if($interface){
                $interfaces = explode("\r\n",$interface);
                $ips = file_get_contents($interfaces[0]);
                $username = isset($interfaces[1]) ? $interfaces[1] : false;
                $password = isset($interfaces[2]) ? $interfaces[2] : false;
                $deadline = isset($interfaces[3]) ? $interfaces[3] : 60;
                $arr = explode("\r\n",$ips);
                foreach ($arr as $key=>$val){
                    $val = trim($val);
                    if($val && strpos($val,':') !== false){ //必须为xxx.xxx.xxx.xxx:port
                        $vals = explode(":",$val);
                        $jsonArr = ['ip'=>$vals[0],'port'=>$vals[1],'socket'=>false,'username'=>$username,'password'=>$password];
                        $proxyIp = json_encode($jsonArr);
                        Cache::set('spider:proxy:ip',$proxyIp,$deadline);
                        $filePath = __DIR__.'/Cache/proxy.txt';
                        file_put_contents($filePath,date('Y-m-d H:i:s').' : '.$proxyIp."\r\n",FILE_APPEND);
                        break;
                    }
                }
                return $proxyIp;
            }
            return false;
        }
    }

    /**
     * 替换空格
     * @param $str
     * @return mixed
     */
    public function TB($str)
    {
        $arr = [" ", "　", "\t", "\n", "\r", "\r\n"];
        $str = str_replace($arr,'',$str);
        return $str;
    }
}