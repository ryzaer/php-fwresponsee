<?php function check_token(){
        if(!empty($_REQUEST['token']))
            return json_decode(\Crypto\ssl::decrypt($_REQUEST['token']),true);
        return false;
    
}