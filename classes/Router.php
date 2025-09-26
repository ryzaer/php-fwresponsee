<?php

class Router
{
    private $routes = [];
    protected $fn,$config,$http = [];
    protected static $inst ;
    public $basename;

    function __construct($configPath=null)
    {
        $this->basename = basename('.');
        if($configPath){
            $configFile = "{$this->basename}/$configPath";
            if(!file_exists($configFile)){
                echo "File {$configPath}, Not exist!";
                $configFile = null;
                exit;
            }else{
                $this->config = $this->loadConfig($configPath);       
                $this->loadRoutes($this->config['router'] ?? []);
            }
        }

        if(!empty($this->config['global']['cache_path']))
            $this->cachesPath = $this->config['global']['cache_path'];
        if(!empty($this->config['global']['controller_path']))
            $this->controllersPath = $this->config['global']['controller_path'];
        if(!empty($this->config['global']['template_path']))
            $this->templatesPath = $this->config['global']['template_path'];
        if(!empty($this->config['global']['allow_extension']))
            $this->extension = $this->config['global']['allow_extension'];
        
        $this->cachesPath = "{$this->basename}/{$this->cachesPath}";
        $this->controllersPath = "{$this->basename}/{$this->controllersPath}";
        $this->templatesPath = "{$this->basename}/{$this->templatesPath}";
    }

    static function instance(){
        return self::$inst;
    }
    static function dateFormatter($dateString, $locale = 'id_ID') {
        $stmt = self::instance();
        $locale = !empty($stmt->data['pwa']['lang']) ? $stmt->data['pwa']['lang'] : $locale;

        // $fmt = new \IntlDateFormatter('id_ID', \IntlDateFormatter::FULL, \IntlDateFormatter::SHORT);
        // pakai pattern kustom agar ada leading zero
        $fmt = new \IntlDateFormatter(
            $locale,
            \IntlDateFormatter::LONG,
            \IntlDateFormatter::NONE,
            null,
            null,
            'dd MMMM yyyy' // <- kustom pattern
        );        
        return $fmt->format(new \DateTime($dateString));
    }

    function getConfig()
    {
        // getconfig hanya bisa dibaca di cli mode
        return isset($this->config) ? $this->config : '';
    }
    // memanggil fn didalam maupun diluar class
    // pengganti $this->fn->custom_function()
    // karna nilai fn adalah protected hanya bisa diakses
    // didalam class dan class turunan (inheritance)
    function fn($func,...$args){
        if(is_string($func))
            return $this->fn->{$func}(...$args);       
    }
    function getAuthData()
    {
        $authKeys = !empty($this->data['global']['auth_data']) ? explode("|",$this->data['global']['auth_data']) : [] ;
        $authData = [];
        foreach ($authKeys as $key)
           $authData[$key] = !empty($_SESSION[$key]) ? $_SESSION[$key] : '';        
        return $authData;
    }

    function apiResponse(int $code,array $result,$custom=[],bool $arg=false)
    {
        $response = array_merge($custom,['result'=>$result]);
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        if(is_bool($arg) && $arg)
            $arg = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
        echo json_encode($response,$arg);
        exit;
    }

    private function setConfig():void
    {
        foreach ($this->config as $key => $value) {
            $this->data[$key] = $value;
        }
        // hilangkan variable data router
        unset($this->data['router']);
        // hilangkan variable config
        unset($this->config);
    }

    private function loadConfig($file)
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $section = [];
        $config = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === ';') continue;
            
            if (preg_match('/^\[([a-zA-Z0-9_:]+)\]$/', $line, $matches)) {
                $section = explode(":",preg_replace('/\s+/','_',strtolower($matches[1])));
                continue;
            }
            if ($section[0] === null) continue;

            if (strpos($line, '=') !== false) {
                // $line = preg_replace('/;.*(?:\r?\n)?/',"\n",$line);
                if ($section[0] == 'router') {
                    $config[$section[0]][] = $line;
                } else {
                    [$key, $value] = array_map('trim', explode('=', $line, 2));                    
                    $value = $this->convertValue($value);                    
                    $addsubs=true;
                    if ($section[0] === 'global' || $section[0] === 'pwa') {
                        $config[$section[0]][$key] = $value;
                        $addsubs=false;
                    } else {
                        if(count($section)>1){
                            $config[$section[0]][$section[1]][$key] = $value;
                        }else{
                            $addsubs=false;
                        }
                    }
                    if(!$addsubs){
                        if($section[0] === 'database'){
                            $config[$section[0]]['default'][$key] = $value;
                        }else{
                            $config[$section[0]][$key] = $value;
                        }
                    }
                }
            }else{
                if (!isset($config[$section[0]])) {
                    $config[$section[0]] = [];
                }
            }
        }
        
        return $config;
    }

    private function loadRoutes($routes)
    {
        $this->config['router'] = [];
        foreach ($routes as $line => $value) {
            preg_match('/\[(.*?)\]/', $value, $matches);
            $options = [];
            if ($matches) {
                $value = str_replace($matches[0], '', $value);
                parse_str(str_replace(',', '&', $matches[1]), $options);
                $optParse=[];
                foreach ($options as $k => $v) {
                    $optParse[$k] = $this->convertValue($v);
                }
                $options = $optParse;
            }
            [$key, $handler] = array_map('trim', explode('=', $value, 2));
            
            $this->config['router'][$key] = $handler;

            if (preg_match('/^([A-Z|]+)\s+([^\[]+)?$/', $key, $matches)) {
                $methods = explode('|', $matches[1]);
                $path = trim($matches[2]);
                $optionsLeft = [];

                foreach ($methods as $method) {
                    $this->routes[$method][] = [
                        'path' => $path,
                        'handler' => $handler,
                        'options' => $options,
                        'regex' => $this->compilePathToRegex($path),
                        'params' => $this->extractParams($path),
                    ];
                }
            }
        }
    }

    private function compilePathToRegex($path)
    {
        return '#^' . preg_replace('/\{[^\/]+\}/', '([^/]+)', $path) . '$#';
    }

    private function extractParams($path)
    {
        preg_match_all('/\{([^\/]+)\}/', $path, $matches);
        return $matches[1] ?? [];
    }

    private function includeHandler($route, $params, $http_code=200)
    {
        if (!strpos($route, '@')) return false;
        $params->code = $http_code;

        $this->http = (object) $params;

        [$controller, $action] = explode('@', $route, 2);
        $controllerFile = "{$this->controllersPath}/{$controller}.php";
        
        if (file_exists($controllerFile)) {
            require_once $controllerFile;
            if (class_exists($controller)) {
                // memanggil class handler
                $obj = new $controller();
                if (method_exists($obj, $action)) {
                    // start : menambahkan nilai2 yang sudah terpanggil oleh class Router pada class handler ini
                    $obj->fn = $this->fn;
                    $obj->data = $this->data;
                    $obj->routes = $this->routes;
                    $obj->sections = $this->sections;
                    $obj->parentLayout = $this->parentLayout;
                    $obj->enableCache = $this->enableCache;
                    $obj->includedFiles = $this->includedFiles;
                    $obj->extension = $this->extension;
                    $obj->cachesPath = $this->cachesPath;
                    $obj->controllersPath = $this->controllersPath;
                    $obj->http = $this->http;
                    // finish : selanjutnya memanggil fungsi yang ditentukan oleh handler
                    $obj->$action($this->http->data);
                    return true;
                }
            }
        }

        return false;
    }
    
    static function dispatch($configPath)
    {
        self::getCLI();
        if(!self::$inst)
            self::$inst = new self($configPath);
        self::$inst->setConfig();  
        if(!empty(self::$inst->data['global']['time_zone']))
            date_default_timezone_set(self::$inst->data['global']['time_zone']);
        
        if(isset($_SERVER['REQUEST_URI']) && isset($_SERVER['REQUEST_METHOD'])){
            
            $uri = $_SERVER['REQUEST_URI'];
            $path = str_replace(substr($_SERVER['SCRIPT_NAME'],0,-10),'',parse_url($uri, PHP_URL_PATH));
            $method = $_SERVER['REQUEST_METHOD'];
            $method = strtoupper($method);
            // $params = ['url_path'=>preg_replace('/[^0-9a-zA-Z]/','',$path)];
            $psplit = explode('/',$path);
            $pgname = array_values(array_filter($psplit));
            $pgbase = str_repeat('../',count($psplit)-2);
            // ini code awal adalah 200
            $params = ['code'=>200,'path'=> !empty($pgname[0])?$pgname[0]:'','base'=> $pgbase ?: "./",'data'=>[]];
            //memberikan nilai2 ini di fungsi instance dan route fungsi includeHandler
            self::$inst->fn = \__fn::get(self::$inst->controllersPath."/functions");
            self::$inst->http = (object) $params; 

            $errorHandler = self::$inst->get('global.error_handler');
            // conditional templating cache
            if(self::$inst->get('global.cache_enable') === true )
                self::$inst->enableCache = true;
            
            if (!isset(self::$inst->routes[$method])) {
                http_response_code(405);
                if ($errorHandler) {
                    self::$inst->includeHandler($errorHandler, $params, 405);
                } else {
                    echo "405 Method Not Allowed";
                }
                return;
            }
            
            foreach (self::$inst->routes[$method] as $route) {
                if (preg_match($route['regex'], $path, $matches)) {
                    array_shift($matches);
                    $chkprm = array_combine($route['params'],$matches);
                    //update nilai http data jika ada perubahan
                    self::$inst->http->data = !empty($chkprm) ? (object) $chkprm : []; 

                    if (!empty($route['options']['cors'])){
                        $origin = $route['options']['cors'] === true ? '*' : $route['options']['cors'];  
                        header("Access-Control-Allow-Origin: $origin");
                    }
                    
                    if (!empty($route['options']['auth']) && $route['options']['auth'] === true) {
                        session_start();
                        if(self::$inst->get('global.auth_data')){
                            $authKeys = explode('|', self::$inst->get('global.auth_data') ?? '');
                            $authKeys = array_map('trim', $authKeys);
                            $missing = array_filter($authKeys, function ($key) {
                                return !isset($_SESSION[$key]);
                            });
                            
                            if (!empty($missing)) {
                                http_response_code(403);
                                if ($errorHandler) {
                                    self::$inst->includeHandler($errorHandler, self::$inst->http, 403);
                                } else {
                                    echo "403 Forbidden (missing auth data)";
                                }
                                return;
                            }
                        }
                    }
                    
                    if (self::$inst->includeHandler($route['handler'], self::$inst->http)) return;
                    
                    http_response_code(500);
                    if ($errorHandler) {
                        self::$inst->includeHandler($errorHandler, self::$inst->http, 500);
                    } else {
                        echo "500 Controller not found.";
                    }
                    return;
                }
            }

            http_response_code(404);
            if ($errorHandler) {
                self::$inst->includeHandler($errorHandler, self::$inst->http, 404);
            } else {
                echo "404 Not Found";
            }
        }
    }
    
    // ini bagian layouting
    protected array $data = [];
    protected array $sections = [];
    protected ?string $parentLayout = null;
    protected bool $enableCache = false;
    protected array $includedFiles = [];

    protected function getCacheFilePath($layoutFile): string
    {
        $hash = md5($layoutFile . serialize($this->data));
        return $this->cachesPath . '/tpl_' . $hash . '.html';
    }

    protected function parseExtends(string &$content): void
    {
        if (preg_match('/\{\{@extends:([^\}]+)\}\}/', $content, $match)) {
            $this->parentLayout = trim($match[1]);
            $content = str_replace($match[0], '', $content);
        }
    }

    protected function parseSections(string &$content): void
    {
        preg_match_all('/\{\{@section:([^\}]+)\}\}(.*?)\{\{@endsection\}\}/s', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $sectionName = trim($match[1]);
            // Ini yang diperbaiki:
            $sectionContent = trim($this->parse($match[2])); // <-- HARUS di-parse di sini.
            $this->sections[$sectionName] = $sectionContent;

            // Hapus dari content agar tidak double render
            $content = str_replace($match[0], '', $content);
        }
    }

    protected function parseComponents(string $content): string
    {
        return preg_replace_callback('/\{\{@component:\s*[\'"](.+?)["\']\s+with\s+(.+?)\}\}/', function ($matches) {
            $path = $matches[1];
            $params = $matches[2];

            // parse key="value" pairs
            preg_match_all('/(\w+)\s*=\s*["\'](.*?)["\']/', $params, $pairs, PREG_SET_ORDER);
            $data = [];
            foreach ($pairs as $pair) 
                $data[$pair[1]] = $pair[2];

            if (!file_exists($path))
                return "<!-- Component not found: $path -->";

            $component = new self();
            foreach ($data as $key => $val) 
                $component->set($key, $val);
            
            return $component->render($path);
        }, $content);
    }

    protected function injectYields(string $content): string
    {
        // 1) Placeholder dengan konten default:
        //    {{@section:header}} ...default... {{@endsection}}
        $content = preg_replace_callback(
            '/\{\{@section:([^\}]+)\}\}(.*?)\{\{@endsection\}\}/s',
            function ($m) {
                $name = trim($m[1]);
                $default = $this->parse($m[2]);           // proses variabel, dll.
                return $this->sections[$name] ?? $default;
            },
            $content
        );

        // 2) Placeholder tunggal tanpa default:
        //    {{@section:header}}
        $content = preg_replace_callback(
            '/\{\{@section:([^\}]+)\}\}/',
            function ($m) {
                $name = trim($m[1]);
                return $this->sections[$name] ?? '';      // kosong kalau tak diisi
            },
            $content
        );

        return $content;
    }

    function set($key, $value = null): void
    {
        // Jika batch set (array)
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                if (!is_numeric($k)) {
                    $this->set($k, $v); // Rekursif
                }
            }
            return;
        }

        // Jika single key
        if (is_string($key)) {
            $segments = explode('.', $key);
            $data =& $this->data;

            foreach ($segments as $index => $segment) {
                // Jika segmen terakhir, assign value
                if ($index === count($segments) - 1) {
                    if (is_array($data)) {
                        $data[$segment] = $value;
                    } elseif (is_object($data)) {
                        $data->$segment = $value;
                    }
                    return;
                }

                // Jika belum ada, buatkan array atau object default
                if (is_array($data)) {
                    if (!isset($data[$segment])) {
                        $data[$segment] = [];
                    }
                    $data =& $data[$segment];
                } elseif (is_object($data)) {
                    if (!isset($data->$segment)) {
                        $data->$segment = new \stdClass();
                    }
                    $data =& $data->$segment;
                } else {
                    // Jika tipe data bukan array/object, override jadi array baru
                    $data = [];
                    $data =& $data[$segment];
                }
            }
        }
    }

    function get($path, $default = null)
    {
        $segments = explode('.', $path);
        $value = $this->data;

        foreach ($segments as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } elseif (is_object($value) && property_exists($value, $key)) {
                $value = $value->$key;
            } else {
                return $default;
            }
        }

        return $value;
    }

    protected function getDataValue(string $path)
    {
        $parts = explode('.', $path);
        $value = $this->data;

        foreach ($parts as $part) {
            if (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } elseif (is_object($value) && isset($value->$part)) {
                $value = $value->$part;
            } else {
                return '';
            }
        }
        return $value;
    }

    protected function parseHelpers(string $content): string
    {
        return preg_replace_callback('/\{\{\s*(upper|lower|date)\s+([\w\.]+)(?:\s+"(.*?)")?\s*\}\}/', function ($matches) {
            $func = $matches[1];
            $key = $matches[2];
            $format = $matches[3] ?? null;
            $value = $this->getDataValue($key);

            if ($func === 'upper') {
                return strtoupper((string)$value);
            } elseif ($func === 'lower') {
                return strtolower((string)$value);
            } elseif ($func === 'date') {
                $timestamp = strtotime((string)$value);
                if (!$timestamp) return '';
                return date($format ?: 'Y-m-d', $timestamp);
            }

            return '';
        }, $content);
    }

    protected function parseVariables(string $content): string
    {
        return preg_replace_callback('/\{\{@([\w\.]+)((?:\|[\w]+(?::[^|}]+)?)*)\}\}/', function ($matches) {
            $key = $matches[1];
            $filterString = $matches[2];

            $filters = [];
            if ($filterString) {
                preg_match_all('/\|([\w]+)(?::(["\'])(.*?)\2)?/', $filterString, $filterMatches, PREG_SET_ORDER);
                foreach ($filterMatches as $filterMatch) {
                    $filterName = $filterMatch[1];
                    $filterArg = $filterMatch[3] ?? null;
                    $filters[] = $filterArg !== null ? "$filterName:$filterArg" : $filterName;
                }
            }

            $value = $this->getDataValue($key);
            return $this->applyFilters((string)$value, $filters);
        }, $content);
    }

    protected function parseIncludes(string $content): string
    {
        // Pertama: parsing ekspresi dengan ~
        $content = preg_replace_callback('/\{\{\s*\'([^\']+)\'\s*~\s*(.*?)\s*~\s*\'([^\']+)\'\s*\}\}/', function ($matches) {
            $start = $matches[1];
            $var = $matches[2];
            $end = $matches[3];
            $middle = $this->getDataValue($var);
            $filePath = $start . $middle . $end;
            return $this->loadFile($filePath);
        }, $content);

        // Kedua: include biasa
        return preg_replace_callback('/\{\{\s*[\'"](.+?)["\']\s*\}\}/', function ($matches) {
            return $this->loadFile($matches[1]);
        }, $content);
    }

    protected function loadFile(string $filePath): string
    {
        if (file_exists($filePath)) {
            $this->includedFiles[] = $filePath;
            return $this->parse(file_get_contents($filePath));
        }
        return "<!-- File not found: $filePath -->";
    }

    protected function parseConditionals(string $content): string
    {
        return preg_replace_callback('/\{\{if (.+?)\}\}(.*?)\{\{endif\}\}/s', function ($matches) {
            $block = $matches[0];  // Semua isi dari {{if}} sampai {{endif}}
            $condition = trim($matches[1]);
            $body = $matches[2];

            // Pisahkan elseif dan else
            $parts = preg_split('/\{\{(elseif .+?|else)\}\}/', $body, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

            $conditions = [];
            $currentCondition = $condition;

            // Parsing semua blok if, elseif, else
            for ($i = 0; $i < count($parts); $i += 2) {
                $contentBlock = $parts[$i];
                $next = $parts[$i + 1] ?? null;

                if (is_string($next) && strpos($next, 'elseif') === 0) {
                    $conditions[] = ['condition' => $currentCondition, 'content' => $contentBlock];
                    $currentCondition = trim(substr($next, 7)); // Ambil kondisi elseif berikutnya
                } elseif ($next === 'else') {
                    $conditions[] = ['condition' => $currentCondition, 'content' => $contentBlock];
                    $conditions[] = ['condition' => 'else', 'content' => $parts[$i + 2] ?? ''];
                    break;
                } else {
                    $conditions[] = ['condition' => $currentCondition, 'content' => $contentBlock];
                    break;
                }
            }

            // Evaluasi kondisi satu per satu
            foreach ($conditions as $cond) {
                if ($cond['condition'] === 'else' || $this->evaluateCondition($cond['condition'])) {
                    return $this->parse($cond['content']);
                }
            }

            return '';
        }, $content);
    }

    protected function evaluateCondition(string $condition): bool
    {
        // Regex untuk ambil variabel, operator, dan value
        if (preg_match('/^([\w\.]+)\s*(===|!==|==|!=|>=|<=|>|<)\s*(.+)$/', $condition, $matches)) {
            $leftKey = trim($matches[1]);
            $operator = trim($matches[2]);
            $rightValue = trim($matches[3]);

            $leftValue = $this->getDataValue($leftKey);
            $rightValue = $this->convertValue($rightValue);

            switch ($operator) {
                case '===': return $leftValue === $rightValue;
                case '!==': return $leftValue !== $rightValue;
                case '==': return $leftValue == $rightValue;
                case '!=': return $leftValue != $rightValue;
                case '>=': return $leftValue >= $rightValue;
                case '<=': return $leftValue <= $rightValue;
                case '>': return $leftValue > $rightValue;
                case '<': return $leftValue < $rightValue;
            }
        }

        // Fallback: jika hanya variabel, cek apakah truthy
        $value = $this->getDataValue($condition);
        return !empty($value);
    }

    protected function convertValue(string $value)
    {
        // Tangani array kosong
        if ($value === '[]') 
            return [];
        // Tangani null string
        if (strtolower($value) === 'null')
            return null;
        // Tangani boolean (support case-insensitive)
        if (strtolower($value) === 'true' || strtolower($value) === 'false')
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        // Tangani string dengan kutip tunggal atau ganda
        if (preg_match('/^[\'"](.*)[\'"]$/', $value, $match))
            return $match[1];
        // Tangani angka (otomatis ke int/float)
        if (is_numeric($value))
            return $value + 0;
        // Jika tidak termasuk tipe di atas, kembalikan string as-is
        return $value;
    }
    
    protected function parseLoops(string $content): string
    {
        return preg_replace_callback('/\{\{foreach (.+?) in (.+?)\}\}(.*?)\{\{endforeach\}\}/s', function ($matches) {
            $itemName = trim($matches[1]);
            $listName = trim($matches[2]);
            $body = $matches[3];

            $list = $this->getDataValue($listName);

            if (!is_array($list)) {
                return '';
            }

            $output = [];
            // $num = 1;
            foreach ($list as $item) {
                $this->set($itemName, $item);
                // Hilangkan newline hanya di awal dan akhir blok item
                $parsed = preg_replace('/^[\r\n]+\s{4}|[\r\n]+$/', '', $this->parse($body));
                $output[] = $parsed;
                // $output[] = $num === count($list) ? preg_replace('/[\r\n]+/',"",$parsed) : $parsed;
                // $num++;
            }

            // Gabungkan semua item tanpa extra break line
            return implode('', $output); // << Perhatikan: Jangan pakai \n di sini
        }, $content);
    }

    protected function applyFilters($value, array $filters): string
    {
        foreach ($filters as $filter) {
            if (strpos($filter, ':') !== false) {
                [$name, $arg] = explode(':', $filter, 2);
                $arg = trim($arg, "\"'");
            } else {
                $name = $filter;
                $arg = null;
            }

            switch ($name) {
                case 'upper':
                    $value = strtoupper($value);
                    break;
                case 'lower':
                    $value = strtolower($value);
                    break;
                case 'ucwords':
                    $value = ucwords($value);
                    break;
                case 'date':
                    $timestamp = strtotime($value);
                    $value = $timestamp ? date($arg ?: 'Y-m-d', $timestamp) : '';
                    break;
                default:
                    // bisa tambahkan custom helper di sini
                    break;
            }
        }
        return $value;
    }

    protected function removeComments(string $content): string
    {
        return preg_replace('/\{\{\-\-.*?\-\-\}\}/s', '', $content);
    }

    protected function parse(string $content): string
    {
        $content = $this->removeComments($content);
        $content = $this->parseComponents($content);
        $content = $this->parseIncludes($content);
        $content = $this->parseConditionals($content);
        $content = $this->parseLoops($content);
        $content = $this->parseHelpers($content);
        $content = $this->parseVariables($content);
        
        return $content;
    }
    protected function addOnScripts($string):string{
        // deteksi language di initial <html> 
        if(!empty($this->data['pwa']['lang'])){
            $lang = $this->data['pwa']['lang'];
             // Jika sudah ada atribut lang (dengan atau tanpa quote)
            if (preg_match('/<html\b[^>]*\blang\s*=\s*([\'"])?([^\s>\'"]+)\1?/i', $string)) {
                // Ganti atribut lang yang sudah ada
                $string = preg_replace('/(<html\b[^>]*\blang\s*=\s*)([\'"])?([^\s>\'"]+)([\'"])?/i', "$1\"$lang\"", $string);
            } else {
                // Jika belum ada, tambahkan atribut lang
                $string = preg_replace('/(<html\b)([^>]*?)>/i', "$1 lang=\"$lang\"$2>", $string);
            }
        }
        // Mendeteksi script PWA
        $dir_mnfs = "{$this->basename}/manifest.json";
        $svworker = "{$this->basename}/service-worker.js";
        preg_match_all('/(\s*)<(title\b[^>]*|footer\b[^>]*)>|<\/(title|footer)>/', $string,$match,PREG_SET_ORDER);
        if(file_exists($dir_mnfs) && file_exists($svworker) && $match){
            
            $varss= array_map(function($item) {
                return preg_replace('/[\r\n]/is',"",$item);
            }, $match);
            $favicon = null ;
            if(isset($this->data['pwa']['icon_192']) && file_exists("{$this->basename}/{$this->data['pwa']['icon_192']}"))
                $favicon = "\n[~]<link rel=\"icon\" href=\"{$this->http->base}{$this->data['pwa']['icon_192']}\" sizes=\"192x192\">";
            $add_meta =null;
            if(!empty($this->data['pwa']['name']))
                $add_meta .= "\n[~]<meta name=\"application-name\" content=\"{$this->data['pwa']['name']}\"/>";
            if(!empty($this->data['pwa']['description']))
                $add_meta .= "\n[~]<meta name=\"description\" itemprop=\"description\" content=\"{$this->data['pwa']['description']}\"/>";
            if(!empty($this->data['pwa']['deindexed']) && $this->data['pwa']['deindexed'] === true){
                $add_meta .= "\n[~]<meta name=\"robots\" content=\"noindex, nofollow, noarchive, noodp\"/>";
                $add_meta .= "\n[~]<meta name=\"googlebot\" content=\"noindex, nofollow, noarchive, noodp\"/>";
                $add_meta .= "\n[~]<meta name=\"googlebot-news\" content=\"noindex, nosnippet, nofollow, noarchive\"/>";
                $add_meta .= "\n[~]<meta name=\"msnbot\" content=\"noindex, nofollow, noarchive, noodp\"/>";
                $add_meta .= "\n[~]<meta name=\"bingbot\" content=\"noindex, nofollow, noarchive, noodp\"/>";
            }
$color = $this->get('pwa.theme_color') ?? '#757575';
// meta x-http untuk inisiasi jQuery Page Modul
$meta = <<<HTML
</title>
[~]<link rel="manifest" href="{$this->http->base}manifest.json">$favicon
[~]<meta name="theme-color" content="$color">
[~]<meta name="x-http" content="path={$this->http->path}, code={$this->http->code}, base={$this->http->base}">$add_meta
HTML;
$script = <<<HTML
</footer>
[~]<script>
[~] if ('serviceWorker' in navigator) {
[~]   navigator.serviceWorker.register('{$this->http->base}service-worker.js')
[~]     .then(() => console.log('✅ Service Worker registered'))
[~]     .catch(err => console.error('⚠️ Fail register SW:', err));
[~] }
[~]</script>
HTML;
            if(count($varss)===4){
                foreach ($varss as $key => $value) {
                    if($key===0||$key==2){
                        $format = $meta;
                        if($key===2){
                            $format = $script;
                        }
                        $format = str_replace('[~]',$value[1],$format);
                    }
                    if($key===1||$key==3)
                        $string = str_replace(trim($value[0]),$format,$string);                    
                }
            }
        }
        return $string;
    }
    function render($layoutFile = null): string
    {
        if (!file_exists($layoutFile)) {
            return "<!-- Layout file not found: {$layoutFile} -->";
        }

        $cacheFile = $this->getCacheFilePath($layoutFile);
        $metaFile = $cacheFile . '.meta';

        if ($this->enableCache && file_exists($cacheFile) && file_exists($metaFile)) {
            $meta = json_decode(file_get_contents($metaFile), true);
            $expired = false;

            foreach ($meta['files'] as $file => $lastModified) {
                if (!file_exists($file) || filemtime($file) > $lastModified) {
                    $expired = true;
                    break;
                }
            }

            if (!$expired) {
                return file_get_contents($cacheFile);
            }
        }

        // --- Step 1: Ambil child layout ---
        $content = file_get_contents($layoutFile);

        // --- Step 2: Deteksi extends dan extract sections ---
        $this->parseExtends($content);
        $this->parseSections($content);

        // --- Step 3: Jika punya parent, load parent ---
        if ($this->parentLayout && file_exists($this->parentLayout)) {
            $layoutContent = file_get_contents($this->parentLayout);

            // --- Step 4: Inject section ke parent layout ---
            $layoutContent = $this->injectYields($layoutContent);

            // --- Step 5: Final parse parent layout ---
            $output = $this->parse($layoutContent);
        } else {
            // Jika tidak punya parent, parse child layout biasa
            $output = $this->parse($content);
        }

        // --- Step 6: Tambahkan script PWA jika ada ---
        $output = $this->addOnScripts($output);

        // --- Step 7: Simpan cache ---
        if ($this->enableCache) {
            $usedFiles = array_unique(array_merge(
                [$layoutFile],
                $this->parentLayout ? [$this->parentLayout] : [],
                $this->includedFiles
            ));

            $metaData = ['files' => []];
            foreach ($usedFiles as $file) {
                $metaData['files'][$file] = file_exists($file) ? filemtime($file) : 0;
            }

            file_put_contents($cacheFile, $output);
            file_put_contents($metaFile, json_encode($metaData));
        }

        return $output;
    }

    // DB Connection Mysql
    protected $extension;
    function dbConnect(...$prms)
    {
        $keys = !empty($prms[0]) ? $prms[0] : 'default';
        $data = !empty($this->data['database'][$keys]) ? $this->data['database'][$keys] : [] ; 
        if(!$data){
            if(!empty($prms[0]))
                $data['user'] = $prms[0];
            if(!empty($prms[1]))
                $data['pass'] = $prms[1];
            if(!empty($prms[2]))
                $data['name'] = $prms[2];
            if(!empty($prms[3]))
                $data['host'] = $prms[3];
            if(!empty($prms[4]))
                $data['port'] = $prms[4];
            if(!empty($prms[5]))
                $data['type'] = $prms[5];
        }

        $user = !empty($data['user'])?$data['user']:'';
        $pass = !empty($data['pass'])?$data['pass']:'';
        $type = !empty($data['type'])?$data['type']:'mysql';
        $host = !empty($data['host'])?$data['host']:'localhost';
        $port = !empty($data['port'])?$data['port']:'3306';
        $name = !empty($data['name'])?";dbname={$data['name']}":'';
        $char = $type === 'mysql' ? ';charset=utf8mb4' : '';
        
        try {
            $pdo = new \PDO(sprintf('%s:host=%s;port=%s%s%s',$type,$host,$port,$name,$char),$user,$pass);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);
            return new \dbHandler($pdo,$this->extension);
        } catch (\PDOException $e) {
            var_export("Connection Error: " . $e->getMessage());
        }
    }

    function getExtension($mimeType):string
    {
        return \dbHandler::getExtension($mimeType);
    }
    
    function getMimeFile($filePath,$isfile=true):string
    {
        return \dbHandler::getMimeFile($filePath,$isfile);
    }
    
    // CLI Command
    protected string $cachesPath = 'caches';
    protected string $controllersPath = 'controllers';
    protected string $templatesPath = 'templates';
    protected static function getCLI(){
        if (PHP_SAPI !== 'cli') return false;
        
        $prms = $_SERVER['argv'];
        // buat file script
        if($prms[1] === 'make:script'){
            $self = new self();
            echo "📌 Write script name : {$prms[2]}\n";
            if(!empty($prms[2]) && is_string($prms[2]) && preg_match('/\.js/', $prms[2])){
                $file = "{$self->basename}/{$prms[2]}";
                print_r(basename($file));
                // pastikan folder ada
                if(file_exists($file)){
                    echo "• Skipped, file already exists!\n";
                    exit;
                }
                $dir = dirname($file);
                $fnm = basename($file);
                
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true); // recursive mkdir
                }

                // buat file kosong
                if (touch($file)) {
                    echo "✔  File created!";
                } else {
                    echo "❌ Failed to create!";
                }
            }else{
                echo "❌ Script name must have extension .js!\n";
            }
            exit;
        }

        if (isset($prms[1]) && $prms[1] == 'clear:caches') {
            $self = new self();
            if (!is_dir($self->cachesPath)) {
                echo "Cache directory not found.\n";
                exit;
            }
            $files = glob($self->cachesPath . '/*.html*');
            if (empty($files)) {
                echo "No cache files to delete.\n";
                exit;
            }
            foreach ($files as $file) {
                if (unlink($file)) {
                    echo "Deleted: " . basename($file) . "\n";
                } else {
                    echo "Failed to delete: " . basename($file) . "\n";
                }
            }
            echo "Cache cleared.\n";
            exit;
        }

        if (isset($prms[2]) && $prms[1] === 'make:ini') {            
            $standard_ini = <<<INI
[global]
time_zone = Asia/Jakarta
;error_handler = ErrorController@handle
; Values separated by "|" .exp (username|password|....)
auth_data = 
;cache_enable = true
cache_path = caches
controller_path = controllers
template_path = templates
; The allow_extension option is optional for managing file extensions in the database.
; Values separated by "|" .exp (mp4|mp3|jpg|.....)
allow_extension = 

[router]
GET / = HomeController@method

[pwa]
name = PHP App iniStyle support
lang = id_ID
short_name = I-App
; description member is optional, and app stores may not use this
description = PHP application with .ini-based configuration
start_url = ./
; Deindexed option is optional, allow robots to index the page or not
deindexed = false
theme_color = #3367D6
background_color = #ffffff
; An icon with a size of 192×192 is required for PWA
icon_192 = icons/icon-192x192.png
icon_512 = icons/icon-512x512.png
; Screenshots are optional. Recommended narrow size: ≤ 640px
; Values separated by "|" .exp (sc_wide=image960.jpg|image2k.jpg)
sc_narrow = 
sc_wide = 
orientation = any
display = standalone
; version is optional
version =
INI;
            $filetoput = basename(".") . "/{$prms[2]}.ini";
            if(!file_exists($filetoput)){
                file_put_contents($filetoput,$standard_ini);
                echo "✔ {$prms[2]}.ini success created\n";
            }else{
                echo "• Skipped (already exists) : $filetoput\n";
            }
        }

        if (isset($prms[2]) && ($prms[1] === 'make:pwa' || $prms[1] === 'make:handlers')) {
            
            $self = new self("{$prms[2]}.ini");
            $routes = $self->getConfig()['router'] ?? [];
            $global = $self->getConfig()['global'] ?? [];
            $pwa = $self->getConfig()['pwa'] ?? [];
            $handlers = [];

            if(!empty($global['error_handler'])){
                $routes['error_handler'] = $global['error_handler'];
            }

            if($prms[1] == 'make:pwa'){
                if (empty($pwa)) {
                    echo "⚠️ [pwa] section's not set on {$prms[2]}.ini\n";
                    exit;
                }                

                $manifest["name"] = $pwa['name'] ?? 'PHP App iniStyle support';
                if(!empty($pwa['lang']))
                    $manifest["lang"] = $pwa['lang'];
                $manifest["short_name"] = $pwa['short_name'] ?? 'I-App';
                if(!empty($pwa['description']))
                    $manifest["description"] = $pwa['description'];
                $manifest["start_url"] = $pwa['start_url'] ?? './';
                $manifest["display"] = $pwa['display'] ?? 'standalone';
                $manifest["background_color"] = $pwa['background_color'] ?? '#ffffff';
                $manifest["theme_color"] = $pwa['theme_color'] ?? '#3367D6';
                $manifest["orientation"] = $pwa['orientation'] ?? 'any';
                $manifest["icons"] = [];

                if (!empty($pwa['icon_192']) && !file_exists($pwa['icon_192'])){
                    echo "⚠️  Please provide an icon with a minimum size of 192x192\n";
                    exit;
                }

                $read_img = getimagesize("{$self->basename}/{$pwa['icon_192']}");
                $manifest['icons'][] = [
                    "src" => $pwa['icon_192'],
                    "sizes" => "{$read_img[0]}x{$read_img[1]}",
                    "type" => $read_img['mime']
                ];
                
                if (!empty($pwa['icon_512']) && !file_exists($pwa['icon_192'])) {
                    $read_img = getimagesize("{$self->basename}/{$pwa['icon_512']}");
                    $manifest['icons'][] = [
                        "src" => $pwa['icon_512'],
                        "sizes" => "{$read_img[0]}x{$read_img[1]}",
                        "type" => $read_img['mime']
                    ];
                }
                foreach (['narrow'=>'≤ 640px','wide'=>'≥ 640px '] as $factory => $size_info) {
                    if (!empty($pwa["sc_$factory"])) {
                        foreach (explode("|",$pwa["sc_$factory"]) as $sc_file) {
                            $sc_file = trim($sc_file);
                            if(file_exists($sc_file)){
                                $read_img = getimagesize("{$self->basename}/$sc_file");
                                $fact_img = false;
                                if($factory === 'narrow' && $read_img[0] <= 640)
                                    $fact_img = true;
                                if($factory === 'wide' && $read_img[0] > 640)
                                    $fact_img = true;
                                if(!$fact_img){
                                    echo "⚠️  Recommended $factory : $size_info (cs_$factory ➞ $sc_file)\n";
                                }else{
                                    $manifest['screenshots'][] = [
                                        "src" => $sc_file,
                                        "sizes" => "{$read_img[0]}x{$read_img[1]}",
                                        "type" => $read_img['mime'],
                                        "form_factor" => $factory
                                    ];
                                    $fact_img = false;
                                }
                            }
                        }
                    }
                }
                if (!empty($pwa['version']))
                    $manifest["version"] = $pwa['version'];

                $dir_mnfs = "{$self->basename}/manifest.json";
                $svworker = "{$self->basename}/service-worker.js";
                $mnf_info = "successfully created based on {$prms[2]}.ini\n";
                $wrk_info = "successfully created!";
                $pwa_info = "active";
                if (file_exists($dir_mnfs) || file_exists($svworker)){
                    $mnf_info = "recreated!\n";
                    $wrk_info = "recreated!";
                    $pwa_info = "update";
                }

                file_put_contents("{$self->basename}/manifest.json", json_encode($manifest, JSON_UNESCAPED_SLASHES));
                echo "📌 manifest.json $mnf_info";

                // Service Worker
                $tm = hash('adler32',$manifest["name"]);
                $sw = <<<JS
self.addEventListener("install",function(e){
    e.waitUntil(caches.open("pwa-$tm").then(function(cache){
        return cache.addAll(["/"])
    }))
}); 
self.addEventListener("fetch",function(e){
    e.respondWith(caches.match(e.request).then(function(response){
        return response || fetch(e.request)
    }))
});
JS;

                file_put_contents("{$self->basename}/service-worker.js", $sw);
                echo "📌 service-worker.js $wrk_info\n";
                echo "✅ PWA is now $pwa_info!\n";
                exit;
            }

            if($prms[1] == 'make:handlers'){

                is_dir($self->cachesPath) || mkdir($self->cachesPath,0777);
                is_dir($self->controllersPath) || mkdir($self->controllersPath,0777);
                is_dir($self->templatesPath) || mkdir($self->templatesPath,0777);

                foreach ($routes as $key => $line) {
                    $handler = trim($line);
                    if (strpos($handler, '@') === false) continue;
                    [$controller, $method] = explode('@', $handler, 2);
                    $handlers[$controller][] = $method;
                }

                foreach ($handlers as $controller => $methods) {
                    $file = "{$self->controllersPath}/{$controller}.php";
                    $classDef = "<?php\n\nclass $controller extends \\Router\n{\n";

                    $uniqueMethods = array_unique($methods);

                    foreach ($uniqueMethods as $method) {
                        $classDef .= "    function $method(\$slug)\n    {\n        // TODO: implement $method\n    }\n\n";
                    }

                    $classDef .= "}\n";

                    if (!file_exists($file)) {
                        file_put_contents($file, $classDef);
                        echo "✔ Handler created : {$self->controllersPath}/$controller.php\n";
                    } else {
                        // Append method if not exists
                        $content = file_get_contents($file);
                        $updated = false;
                        foreach ($uniqueMethods as $method) {
                            if (!preg_match('/function\\s+' . preg_quote($method, '/') . '\\s*\\(/', $content)) {
                                $append = "\n    function $method(\$slug)\n    {\n        // TODO: implement $method\n    }\n";
                                $content = preg_replace('/\\}\\s*$/', $append . "\n}", $content);
                                $updated = true;
                            }
                        }
                        if ($updated) {
                            file_put_contents($file, $content);
                            echo "✔ Updated handler : {$self->controllersPath}/$controller.php\n";
                        } else {
                            echo "• Skipped (already exists) : {$self->controllersPath}/$controller.php\n";
                        }
                    }
                }
                exit;
            }
            
        }
        exit;
    }
}