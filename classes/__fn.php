<?php
// autocall functions 
class __fn
{
    private static $ths;
    private $create = false,
            $folder = __DIR__."/__functions",
            $fn_scema,
            $source  = [];

    static function get($dirs=null,$call=true,$fnscema = null){
        self::$ths = new \__fn();       
        self::$ths->fn_scema = $fnscema;
        if($dirs)
            self::$ths->folder = $dirs;
        
        is_dir(self::$ths->folder) || mkdir(self::$ths->folder,0755,true);
        self::$ths->source[self::$ths->folder] = $call; 
        return self::$ths;
    }
        
    function __call($var,$arg)
    {
        $this->get_source_function($var); 
        return call_user_func_array($var,$arg); 
    }

    private function get_source_function($var){
        foreach ($this->source as $dirs => $call) {
            if(!function_exists($var)){
                $file = preg_replace('/\\\+|\/+/s','/',"$dirs/$var.php");
                if ($call && !file_exists($file))
                {
                    file_put_contents($file,$this->preStructureFunc($var));
                    chmod($file,0644);
                }  
                if(file_exists($file)){
                    require($file);
                }                    
            } 
        }
    }   
    private function preStructureFunc($name){
        $dirs = preg_replace('/\\\+|\/+/s','/',$this->folder);
        // you can change this template function on $this->fn_scema
        $fnscema = "<?php function %s(...\$args){\n\tprint \"<br><b><i style=\\\"color:red\\\"> TODO: implement function <span style=\\\"color:blue\\\">%s</span> in folder :</i></b><br><small>[$dirs/%s.php]</small><br>\";\n\n}";
        if($this->fn_scema){
            $fnscema = $this->fn_scema;
        }
        return sprintf($fnscema,$name,$name,$name);
    }    	
}