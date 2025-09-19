<?php function exec_biodata() {    
    $form['nama'] = $_REQUEST['nama'];
    $form['nik'] = $_REQUEST['nik'];
    $form['tpt_lahir'] = $_REQUEST['tpt_lahir'];
    $date = \DateTime::createFromFormat('d/m/Y', $_REQUEST['tgl_lahir']);
    $form['tgl_lahir'] = $date->format('Y-m-d');
    $form['agama'] = $_REQUEST['agama'];
    $form['pekerjaan'] = $_REQUEST['pekerjaan'];
    $form['pendidikan'] = $_REQUEST['pendidikan'];
    $form['gender'] = $_REQUEST['gender'];
    $form['negara'] = $_REQUEST['negara'];
    $alamat = json_encode([
        $_REQUEST['alamat'],
        $_REQUEST['rt'],
        $_REQUEST['rw'],
        $_REQUEST['kelurahan']
    ]);
    $form['alamat'] = preg_replace('/[^0-9a-zA-Z]/','', $alamat) ? $alamat : '';
    
    $self = \Router::instance();
    $db = $self->dbConnect();
    // check catatan
    $st = $db->prepare("SELECT 
    IFNULL(JSON_UNQUOTE(JSON_EXTRACT(catatan,'\$[0]')),'') as alias,
    IFNULL(JSON_UNQUOTE(JSON_EXTRACT(catatan,'\$[1]')),'') as hp,
    IFNULL(JSON_UNQUOTE(JSON_EXTRACT(catatan,'\$[2]')),'') as passport,
    IFNULL(JSON_UNQUOTE(JSON_EXTRACT(catatan,'\$[3]')),'') as sponsor,
    IFNULL(JSON_UNQUOTE(JSON_EXTRACT(catatan,'\$[4]')),'') as kriminal
    FROM tbbiodata WHERE id = :id LIMIT 1");
    $st->execute(['id' => $_REQUEST['id']]);
    $ch = $st->fetch(\PDO::FETCH_ASSOC);
    $alias = '';
    $hp = '';
    $passport = '';
    $sponsor = '';
    $kriminal = '';
    if($ch){
        $alias = $ch['alias'];
        $hp = $ch['hp'];
        $passport = $ch['passport'];
        $sponsor = $ch['sponsor'];
        $kriminal = $ch['kriminal'];
    }
    // untuk alias tidak ada form maka default database
    // jika tidak ada maka memecahkan dari nama
    $catatan = json_encode([
        $alias ? $alias : explode(' ', preg_replace('/[^0-9a-zA-Z\s]/','',$_REQUEST['nama']))[0],
        $_REQUEST['hp'] ? $_REQUEST['hp'] : $hp,
        $_REQUEST['passport'] ? $_REQUEST['passport'] : $passport,
        $_REQUEST['sponsor'] ? $_REQUEST['sponsor'] : $sponsor,
        $_REQUEST['kriminal'] ? $_REQUEST['kriminal'] : $kriminal,
    ]);
    $form['catatan'] = preg_replace('/[^0-9a-zA-Z]/','', $catatan) ? $catatan : ''; 
    
    if(!empty($_REQUEST['id'])){
        $db->update('tbbiodata', $form,['id' => $_REQUEST['id']]);
        return 0;
    }else{
        $form['tgl_input'] = date('Y-m-d H:i:s');
        return $db->insert('tbbiodata', $form);
    }    
    return strtoupper(trim($parse));
}