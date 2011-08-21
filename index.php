<?php
function call_user_func_named($className, $obj, $function, $params)
{
    $class = new ReflectionClass($obj);
    $reflect = $class->getMethod($function);

    $realParams = array();
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
            throw new Exception("{$className}::{$function}() param: missing param: {$pname}");
        }
    }

    return call_user_func_array(array(new $obj, $function), $realParams);
}

function decodeUri($uri)
{
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
    return array($className, $functionName, $format, $params);
}

function format($format, $out)
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

function getRealParams($className, $functionName, $params)
{
    $realParams = array();
    $class = new \ReflectionClass(new $className);
    $reflect = $class->getMethod($functionName);

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
            throw new Exception("{$className}::{$functionName}() param: missing param: {$pname}");
        }
    }
    return $realParams;
}

function setUpAutoload()
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
}

function getUri()
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

setUpAutoload();
list($className, $functionName, $format, $params) = decodeUri(getUri());
$realParams = getRealParams($className, $functionName, $params);
echo format($format, call_user_func_array(array($className, $functionName), $realParams));