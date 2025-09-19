<?php

class ErrorController extends \Router
{
    function handle($slug)
    {
        $this->set('title',"Error {$this->http->code} ~ ".$this->get('pwa.name'));
        $this->set('time_script',strtotime('now'));
        $this->set('error_code' ,$this->http->code);
        $this->set('error_info','Halaman perlu dependensi!');
        $this->set('base_script',$this->http->base);
        
        // $this->fn->test();
        // var_dump($this);
        
        if($this->http->code == 404)
            $this->set('error_info',"Halaman {$this->http->base}{$this->http->path} tidak ditemukan!");
        if($this->http->code == 403)
            $this->set('error_info',"Halaman <b><i>{$this->http->path}</i></b> memerlukan akses<br>Silahkan login terlebih dahulu :)");
        print $this->render('templates/error.html');
    }

}
