<?php function cetak_register(){
    $tk = json_decode(\Crypto\ssl::decrypt($_REQUEST['token'])??'[]',true);
    if($tk){
        $self = \Router::instance();
        $lang = $self->get('pwa.lang');
        $ex = explode('-',$_REQUEST['id_reg']);
        $th = (int) $ex[0];
        $id = (int) $ex[1];
        $db = $self->dbConnect();
        // set format tanggal indonesia di database
        $db->query("SET lc_time_names = '$lang'");
        $st = $db->prepare("SELECT id_biodata, (SELECT keperluan FROM tbskck_keperluan WHERE id=id_kep LIMIT 1) as keperluan, tgl_terbit FROM tbskck_reg_$th WHERE id_reg=:id LIMIT 1");
        $st->execute(['id' => $id]);
        $pr = $st->fetch(\PDO::FETCH_ASSOC);
        $rs = [];
        if($pr){
            $id_bio = $pr['id_biodata'];
            unset($pr['id_biodata']);
            foreach ($pr as $k => $v) {
                if($k == 'tgl_terbit'){   
                    // Buat format nomor surat atau register                 
                    $rmw = $self->fn('bulanKeRomawi',date('m',strtotime($v)));
                    // YAN.2.3 format penomoran skck 
                    // tambahkan nol didepan jika 1 digit
                    $nmr = sprintf("%02d", $id) . PHP_EOL;
                    $rs['register'] = "$nmr/YAN.2.3/$rmw/".date('Y',strtotime($v));
                    $rs[$k] = strtoupper($self->dateFormatter($v));                    
                    $rs['tgl_expire'] = strtoupper($self->dateFormatter(date('Y-m-d', strtotime('+6 month', strtotime($v)))));
                }else{
                    $rs[$k] = strtoupper($v);
                }
            }
            $pr = $db->prepare("SELECT 
            upper(nama) as nama,gender,agama,negara,nik,inafis,upper(tpt_lahir) as tpt_lahir,alamat,
            upper(date_format(tgl_lahir,'%e %M %Y')) as tgl_lahir,
            ifnull(JSON_UNQUOTE(JSON_EXTRACT(inafis, '\$[1]')),'') as rumus_1,
            ifnull(JSON_UNQUOTE(JSON_EXTRACT(inafis, '\$[2]')),'') as rumus_2,
            ifnull(JSON_UNQUOTE(JSON_EXTRACT(catatan, '\$[2]')),'') as pass,
            ifnull(JSON_UNQUOTE(JSON_EXTRACT(catatan, '\$[4]')),'') as catatan,
            (SELECT upper(item) FROM tbkategori WHERE groups='opt_pekerjaan' AND gid=pekerjaan LIMIT 1) as pekerjaan
                FROM tbbiodata 
                WHERE id='$id_bio'
                LIMIT 1");
            $pr->execute();
            foreach ($pr->fetch(\PDO::FETCH_ASSOC) as $k => $v) {
                if($k == 'alamat'){
                    $rs[$k] = $self->fn('parse_alamat',$v);
                }else{
                    // disini nanti sidik jari
                    $rs[$k] = $v;
                }
            }
        }
        return $rs;
    }else{
        return [];
    }
}