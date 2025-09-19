<?php function parse_alamat($alamat) {
    $check = json_decode($alamat, true);
    $parse = "{$check[0]} ";
    $parse .= $check[1] ? "RT.{$check[1]}/" : '';
    $parse .= $check[2] ? "RW.{$check[2]}" : '';
    if($check[3]){
        $self = \Router::instance();
        $db = $self->dbConnect();
        $st = $db->prepare("SELECT 
        kode as area,
        (SELECT nama FROM tbarea WHERE kode=LEFT(area,2) LIMIT 1) AS prov,
        REPLACE((SELECT nama FROM tbarea WHERE kode=LEFT(area,5) LIMIT 1),'Kabupaten','Kab.') AS kab,
        (SELECT nama FROM tbarea WHERE kode=LEFT(area,8) LIMIT 1) AS kec,
        nama AS kel
        FROM tbarea WHERE kode=:kode LIMIT 1");
        $st->execute(['kode'=>$check[3]]);
        $prov = $st->fetch(\PDO::FETCH_ASSOC);

        if($prov){
            $parse .= " KEL. {$prov['kel']} KEC. {$prov['kec']}, {$prov['kab']} PROV. {$prov['prov']}";
        }
    }
    
    return strtoupper(trim($parse));
}