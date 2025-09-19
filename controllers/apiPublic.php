<?php

class apiPublic extends \Router
{
    function wilayah($slug)
    {
        // wilayah/kec/61.71 kecamatan
        // wilayah/kel/61.71.01
        $db = $this->dbConnect(); 
        $data = [];
        $sql = null;
        $msg = "Daftar Wilayah {$slug->id}";
        $sid = ['kode'=>$slug->id];
        if($slug->item === 'kec'){
            $msg = "Daftar Kecamatan ".$db->select("tbarea[~nama~]",$sid)[0]['nama'];
            $sql = "SELECT * FROM tbarea WHERE CHAR_LENGTH(kode)=8 && LEFT(kode,5)=:kode";
        }
        if($slug->item === 'kel'){
            $msg = "Daftar Kelurahan ".$db->select("tbarea[~nama~]",$sid)[0]['nama'];
            $sql = "SELECT * FROM tbarea WHERE CHAR_LENGTH(kode)=13 && LEFT(kode,8)=:kode";
        }
        if($slug->item === 'nama'){
            $msg = "Nama Wilayah";
            $row = "*";
            if(strlen($slug->id) == 13){
                $row = [];
                $prp = substr($slug->id,0,2);
                $row[] = "(SELECT nama FROM tbarea WHERE kode='$prp' LIMIT 1) AS prov";
                $kab = substr($slug->id,0,5);
                $row[] = "(SELECT nama FROM tbarea WHERE kode='$kab' LIMIT 1) AS kab";
                $kec = substr($slug->id,0,8);
                $row[] = "(SELECT nama FROM tbarea WHERE kode='$kec' LIMIT 1) AS kec";
                $row[] = "nama AS kel";
                $row = implode(',',$row);
                $msg = "Info wilayah : {$slug->id}";
            }
            $sql = "SELECT $row FROM tbarea WHERE kode=:kode";
        }
        if($sql){
            $st = $db->prepare($sql);
            $st->execute($sid);
            $data = $st->fetchAll(PDO::FETCH_ASSOC);
        }
        // $rs = [\Crypto\ssl::hash('haval5')->encrypt('test')];
        $this->apiResponse(200,$data,[
            'message' => $msg
        ]);
    }

    function propinsi($slug)
    {
        $db = $this->dbConnect(); // LEMOT 3x Select :D
        $rs = [];
        // $query1 = "(SELECT nama FROM tbarea_lama WHERE kode=JSON_UNQUOTE(JSON_EXTRACT(alamat, '\$.skrg[5]')) LIMIT 1)";
        // $st = $db->prepare("SELECT nama, nik, JSON_UNQUOTE(JSON_EXTRACT(alamat, '\$.skrg[0]')) as alamat, (SELECT kode FROM tbarea WHERE CHAR_LENGTH(kode)=13 AND REPLACE(LOWER(nama),' ','')=REPLACE(LOWER($query1),' ','') LIMIT 1) AS Kode FROM dbserver_skck.base_skck_data LIMIT 200");
        // $st->execute();
        // $rs = $st->fetchAll(PDO::FETCH_ASSOC);
        // $rs = [\Crypto\ssl::hash('haval5')->encrypt('test')];
        $this->apiResponse(200,$rs,[
            'message' => 'Daftar Propinsi'
        ]);
    }
    
        
    function keperluan($slug)
    {
        $rs = [];
        $db = $this->dbConnect();
        $ms = 'Keperluan Tidak Lengkap';
        $mt = 'warning';
        $ex = !empty($_REQUEST['exec']) ? $_REQUEST['exec'] : null;
        switch ($ex) {
            case 'buat':
                $tk = json_decode(\Crypto\ssl::decrypt($_REQUEST['token'])??'[]',true);
                if($tk){
                    $th = date('Y');
                    $dt = date('Y-m-d');
                    if($db->select('tbskck_reg_'.$th.'[~id_reg~]',[
                        'id_biodata' => $_REQUEST['id_bio'],
                        'tgl_terbit' => $dt
                    ])){
                        $ms = 'Register sudah dibuat hari ini!';
                    }else{
                        $st = $db->prepare("INSERT INTO tbskck_reg_$th (id_biodata,tgl_terbit,id_kep,id_user) VALUES(:id_biodata,:tgl_terbit,:id_kep,:id_user)");
                        $st->bindValue('id_biodata', $_REQUEST['id_bio']);
                        $st->bindValue('tgl_terbit', $dt);
                        $st->bindValue('id_kep', $_REQUEST['id_kep']);
                        $st->bindValue('id_user', $tk['id']);
                        if($st->execute()){
                            $ms = 'Keperluan Berhasil Ditambahkan';
                            $mt = 'success';     
                            $rs = $this->fn->sync_register($_REQUEST['id_bio']);
                        }
                    }
                }
                break;
            case 'ubah':
                $tk = json_decode(\Crypto\ssl::decrypt($_REQUEST['token'])??'[]',true);
                $ms = 'Keperluan Gagal Diubah';
                if($tk){
                    $ex = explode('-',$_REQUEST['id_reg']);
                    $th = (int) $ex[0];
                    $id = (int) $ex[1];
                    if($db->update("tbskck_reg_$th",[
                        'id_kep' => $_REQUEST['id_kep'],
                        'id_user' => $tk['id']
                    ],[
                        'id_reg' => $id
                    ])){                        
                        $ms = 'Keperluan Berhasil Diubah';
                        $mt = 'success';    
                        $rs = $this->fn->sync_register($_REQUEST['id_bio']);                
                    }
                }

                break;
            case 'cetak':
                $ms = 'Proses cetak register gagal!';
                $rs = $this->fn->cetak_register();
                if($rs){
                    $ms = 'Informasi register';
                    $mt = 'success';
                }
                break;
            default:
                // jika tidak ada exec, maka tampilkan list keperluan
                if(!$ex){
                    $ms = 'Daftar Keperluan';            
                    $st = $db->prepare("SELECT id, keperluan as label FROM tbskck_keperluan");
                    $st->execute();
                    $rs = $st->fetchAll(PDO::FETCH_ASSOC);
                }
                break; 
        }
        
        $this->apiResponse(200,$rs,[
            'message' => $ms,
            'type' => $mt
        ]);
    }


    function pemohon($slug)
    {
        $data = [
            [ "no" => 1, "nama" => "Andi", "alamat" => "Jakarta" ],
            [ "no" => 2, "nama" => "Budi", "alamat" => "Bandung" ],
            [ "no" => 3, "nama" => "Citra", "alamat" => "Surabaya" ],
            [ "no" => 4, "nama" => "Dedi", "alamat" => "Medan" ],
            [ "no" => 5, "nama" => "Eka", "alamat" => "Yogyakarta" ],
            [ "no" => 6, "nama" => "Fajar", "alamat" => "Semarang" ],
            [ "no" => 7, "nama" => "Gina", "alamat" => "Makassar" ],
            [ "no" => 8, "nama" => "Hadi", "alamat" => "Balikpapan" ],
            [ "no" => 9, "nama" => "Ika", "alamat" => "Denpasar" ],
            [ "no" => 10, "nama" => "Joko", "alamat" => "Palembang" ],
            [ "no" => 11, "nama" => "Kiki", "alamat" => "Pontianak" ],
            [ "no" => 12, "nama" => "Lina", "alamat" => "Manado" ]
        ];
        $this->apiResponse(200,$data,[
            'messeges' => 'Register Pemohon'
        ]);
    }


    function kategori($slug)
    {
        $db = $this->dbConnect();
        $op = null;
        $order = null;
        if ($slug->opt==="opt_pekerjaan")
            $order = " ORDER BY item";
        $sql = <<<SQL
SELECT gid,item FROM tbkategori WHERE groups=:opt $order
SQL;
        $st = $db->prepare($sql);
        $st->execute(['opt' => $slug->opt]);
        $data = $st->fetchAll(PDO::FETCH_ASSOC);
        $this->apiResponse(200,$data,[
            'messeges' => 'Kategori'
        ]);
    }

}