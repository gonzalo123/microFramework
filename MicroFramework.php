<?php
class MicroFramework
{
    public function decodeUri()
    {
        $uri = $this->getUri();
        $conf = $params = array();
        $functionName = $format = null;

        $parsedUrl = parse_url($uri);
        $path = $parsedUrl['path'];
        if (isset($parsedUrl['query'])) {
            $query = $parsedUrl['query'];
            $params = array();
            $pairs = explode('&', $query);
            foreach ($pairs as $pair) {
                if (trim($pair) == '') {
                    continue;
                }
                list($key, $value) = explode('=', $pair);
                $params[$key] = urldecode($value);
            }
        }
        $arr = explode('/', $path);

        for ($i = 0; $i < count($arr); $i++) {
            $elem = $arr[$i];
            if (strpos($elem, '.') !== false) {
                list($functionName, $format) = explode(".", $elem);
                continue;
            } else {
                if ($elem != '') $conf[] = ucfirst($elem);
            }
        }

        $className = implode('\\', $conf);

        $this->className    = $className;
        $this->functionName = $functionName;
        $this->format       = $format;
        $this->realParams   = $this->getRealParams($params);
        return $this;
    }

    private $className, $functionName, $format, $realParams;

    private function format($format, $out)
    {
        switch ($format) {
            case 'json':
                header('Cache-Control: no-cache, must-revalidate');
                header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
                header('Content-type: application/json');
                return json_encode($out);
            case 'html':
            case 'htm':
                header('Cache-Control: no-cache, must-revalidate');
                header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
                header('Content-type: Content-Type: text/html');
                return (string)$out;
            case 'txt':
            case 'ajax':
                header('Content-type: text/plain; charset=utf-8');
                return (string)$out;
            case 'css':
                header('Content-type: text/css');
                return (string)$out;
            case 'js':
                header('Content-type: application/javascript');
                return (string)$out;
            case 'jsonp':
                $cbk = filter_input(INPUT_GET, '_cbk', FILTER_SANITIZE_STRING);
                if ($cbk == '') {
                    $cbk = 'cbk';
                }

                header('Content-type: text/javascript; charset=utf-8');
                return "{$cbk}(" . json_encode($out) . ");";
            default:
                throw new Exception("Undefined format");
        }
    }

    private function getRealParams($params)
    {
        $realParams = array();
        $class = new ReflectionClass(new $this->className);
        $reflect = $class->getMethod($this->functionName);

        foreach ($reflect->getParameters() as $i => $param) {
            $pname = $param->getName();
            if ($param->isPassedByReference()) {
                /// @todo shall we raise some warning?
            }
            if (array_key_exists($pname, $params)) {
                $realParams[] = $params[$pname];
            } else if ($param->isDefaultValueAvailable()) {
                $realParams[] = $param->getDefaultValue();
            } else {
                throw new Exception("{$this->className}::{$this->functionName}() param: missing param: {$pname}");
            }
        }
        return $realParams;
    }

    private function registerAutoload()
    {
        spl_autoload_register(function ($class)
            {
                $class = str_replace('\\', '/', $class) . '.php';
                if (is_file($class)) {
                    require_once($class);
                } else {
                    throw new Exception("{$class} does not exists");
                }
            }
        );
        return $this;
    }

    private function getUri()
    {
        $requestUri = $_SERVER['REQUEST_URI'];
        $scriptName = $_SERVER['SCRIPT_NAME'];

        if (dirname($scriptName) == '/') {
            $uri = $requestUri;
            return $uri;
        } else {
            $uri = str_replace(dirname($scriptName), null, $requestUri);
            return $uri;
        }
    }

    static function factory()
    {
        return new self;
    }

    public function __construct()
    {
        $this->registerAutoload();
    }

    public function run()
    {
        return $this->format($this->format, call_user_func_array(array($this->className, $this->functionName), $this->realParams));
    }

    private $isCli = false;

    public function cli()
    {
        $options = $this->getCliOptions();

        $this->shift($options);

        $param_arr = $this->getCliParams();

        $className    = !array_key_exists('c', $options) ?: $options['c'];
        $functionName = !array_key_exists('f', $options) ?: $options['f'];

        if (array_key_exists('h', $options)) {
             $usage = <<<USAGE
Usage: cli [options] [-c] <class> [-f] <function> [args...]

Options:
  -h Print this help
  -v verbose mode
\n
USAGE;
            echo $usage;
            exit;
        }

        $this->isCli = true;
        $this->className    = $className;
        $this->functionName = $functionName;
        $this->realParams   = $this->getRealParams($param_arr);
        return call_user_func_array(array($className, $functionName), $this->realParams);
    }

    private function shift($options)
    {
        foreach( $options as $o => $a ) {
            while($k=array_search("-". $o. $a, $GLOBALS['argv'])) {
                if($k) {
                    unset($GLOBALS['argv'][$k]);
                }
            }
            while($k=array_search("-" . $o, $GLOBALS['argv'])) {
                if($k) {
                    unset($GLOBALS['argv'][$k]);
                    unset($GLOBALS['argv'][$k+1]);
                }
            }
        }
        $GLOBALS['argv'] = array_merge($GLOBALS['argv']);
    }

    private function getCliParams()
    {
        $param_arr = array();
        $lenght = count((array)$GLOBALS['argv']);
        if ($lenght > 0) {
            for ($i = 1; $i < $lenght; $i++) {
                if (isset($GLOBALS['argv'][$i])) {
                    list($paramName, $paramValue) = explode("=", $GLOBALS['argv'][$i], 2);
                    $param_arr[$paramName] = $paramValue;
                }
            }
            return $param_arr;
        }
        return $param_arr;
    }

    private function getCliOptions()
    {
        $sortOptions = "";
        $sortOptions .= "c:";
        $sortOptions .= "f:";
        $sortOptions .= "v::";
        $sortOptions .= "h::";

        $options = getopt($sortOptions);
        return $options;
    }
}