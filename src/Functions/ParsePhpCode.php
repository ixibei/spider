<?php
namespace Ixibei\Spider\Functions;

use Log;

class ParsePhpCode{

    /**
     * @param $code 自定义代码
     * @param $fileName 采集ID
     */
    public function code($code,$fileName)
    {
        $dir = str_replace('Functions','',__DIR__).'Cache/';
        if(!is_dir($dir)){
            mkdir($dir,0777,true);
        }

        $file = $dir.$fileName.'.php';

        if(!is_file($file)){
            $mycode = "<?php \r\n";
            //$mycode .= "namespace Ixibei\Spider\Cache; \r\n";
            $mycode .= "use Ixibei\Spider\Functions\simple_html_dom; \r\n\r\n";
            $mycode .= "use Ixibei\Spider\Html; \r\n\r\n";

            $mycode .= "class {$fileName} extends Html{ \r\n\r\n";

            $mycode .= "    public \$data = []; \r\n\r\n";

            $mycode .= "    public function __construct(\$data){\r\n";

            $mycode .= "        \$this->data = \$data;\r\n";

            $mycode .= "    }\r\n\r\n";

            $mycode .= "    public function regular(\$html){\r\n";

            $mycode .= "        ".$code."\r\n";

            $mycode .= "    }\r\n";

            $mycode .= "}\r\n";

            file_put_contents($file,$mycode);
        }
    }

}