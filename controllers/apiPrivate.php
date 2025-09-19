<?php

class apiPrivate extends \Router
{
    function biodata($slug)
    {
      session_start();  
      // untuk format database baru, pendidikan terakhir dihilangkan
      // alamat fleksibel jika ada kode wilayah maka data            
      $result = [];
      $regist = [];
      $infopg = 'Biodata Pemohon';
      $form = !empty($_REQUEST['form']) ? $_REQUEST['form'] : null;
      switch ($form) {
        case 'biodata':
          $exec = $this->fn->exec_biodata();
          if($exec){
            $infopg = 'Biodata baru di tambahkan!';
          }else{
            $infopg = 'Biodata telah di Update!';
          }
          $result = ['id' => $exec ];          
          break;
        default:
          $db = $this->dbConnect(); 
          // pencarian nik atau id
          $gid = strlen($_REQUEST['id']) === 16 ? 'nik = :id' : 'id = :id';
          $sql = <<<SQL
SELECT 
  id,
  nik,
  nama,
  DATE_FORMAT(tgl_lahir, '%d/%m/%Y') AS tgl_lahir,
  tpt_lahir,
  JSON_UNQUOTE(JSON_EXTRACT(alamat, '\$[0]')) AS alamat,
  JSON_UNQUOTE(JSON_EXTRACT(alamat, '\$[1]')) AS rt,
  JSON_UNQUOTE(JSON_EXTRACT(alamat, '\$[2]')) AS rw,
  JSON_UNQUOTE(JSON_EXTRACT(alamat, '\$[3]')) AS area,
  /** disini data input select area
  IFNULL(LEFT(JSON_UNQUOTE(JSON_EXTRACT(alamat, '\$[3]')),5),'') AS kabupaten,
  IFNULL(LEFT(JSON_UNQUOTE(JSON_EXTRACT(alamat, '\$[3]')),8),'') AS kecamatan,
  IFNULL(JSON_UNQUOTE(JSON_EXTRACT(alamat, '\$[3]')),'') AS kelurahan,
  */
  JSON_UNQUOTE(JSON_EXTRACT(catatan, '\$[1]')) AS hp,
  JSON_UNQUOTE(JSON_EXTRACT(catatan, '\$[2]')) AS passport,
  JSON_UNQUOTE(JSON_EXTRACT(catatan, '\$[3]')) AS sponsor,
  JSON_UNQUOTE(JSON_EXTRACT(catatan, '\$[4]')) AS kriminal,
  /** disini data input select */
  gender,
  agama,
  pekerjaan,
  pendidikan,
  negara
FROM tbbiodata 
WHERE $gid
SQL;
          $st = $db->prepare($sql);
          $st->execute(['id' => $_REQUEST['id']]);
          $result = $st->fetch(PDO::FETCH_ASSOC);
          if(!empty($result['id'])){
            $regist = $this->fn->sync_register($result['id']);
          }else{
            $result = [];
          }
          break;
      }
      $this->apiResponse(200, $result , [
        'messeges' => $infopg,
        'register' => $regist
      ]);
      
    }

    function sync_pemohon($slug)
    {
      // INI UNTUK SYNC DB BARU & DB LAMA Ke DB Terbaru 'dbskck'
      // var_dump(json_decode( "[\"18 L 1 U oio 12\",\"S 1 A -io \",[0,0,2,2,1,1,2,1,2,1,0,2,1,1,1,2,\"\",\"\"]]",true));die;
      $db = $this->dbConnect();
      // parse kode wilayah
      $alamat = $this->fn->sync_alamat();
      // parse rumus jari & sinyalemen
      $sinyalemen = $this->fn->sync_sinyalemen();
      // parse riwayat pendidikan dll
      $riwayat = $this->fn->sync_riwayat();
      // parse data keluarga
      $keluarga = $this->fn->sync_keluarga();
      // parse data catatan
      $catatan = $this->fn->sync_catatan();
      $cm = <<<SQL
SELECT  
  db1.id_biodata AS id,
  CONVERT(db1.nama USING utf8mb4) AS nama,
  db1.tanggal_lahir AS tgl_lahir,
  db1.tempat_lahir AS tpt_lahir,
  $alamat AS alamat,
  db1.nomor_identitas AS nik,
  db1.id_sex AS gender,
  db1.id_agama AS agama,
  db1.id_pekerjaan AS pekerjaan,
  db1.id_pendidikan AS pendidikan,
  CAST(db1.id_negara AS SIGNED) AS negara,
  $sinyalemen AS inafis,
  $riwayat AS riwayat,
  $keluarga AS keluarga,
  $catatan AS catatan,
  IFNULL(db2.tgl_update,DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s')) AS tgl_input
FROM dbident.tbbiodata db1
LEFT JOIN dbserver_skck.base_skck_data db2 
  ON db1.nomor_identitas = db2.nik
WHERE  db1.id_biodata > 114348
ORDER BY db1.id_biodata ASC
LIMIT 1000
SQL;
      $st = $db->prepare($cm);
      $st->execute();
      $rs = $st->fetchAll(PDO::FETCH_ASSOC);
      foreach ($rs as $prms) {
        // $db->insert('tbbiodata',$prms);
      }
      $this->apiResponse(200,$rs,[
          'message' => 'Daftar Propinsi'
      ]);
    }

    function dataskck($slug)
    {
      session_start();
      $hasil = [];
      // batasi hasil pencarian data untuk efisiensi server dan
      // pastikan jumlah data dalam database sama atau lebih
      // dengan 1000 akan menghemat memori server
      $limit = 1000;
      // total default adalah 0
      $total = 0;
      // pencarian per halaman
      $page = isset($_REQUEST['page']) ? (int) $_REQUEST['page'] : 1;
      // batas tampilan data
      $perPage = 5; 
      $offset = ($page - 1) * $perPage;
      
      $session = !empty($_SESSION['token']) ? $_SESSION['token'] : (!empty($_REQUEST['token']) ? $_REQUEST['token'] : null);
      $valid = \Crypto\ssl::decrypt($session);
      // eksekusi data pencarian
      $keyword = !empty($_REQUEST['cari']) ? $_REQUEST['cari'] : null;
      if($valid){     
        $db = $this->dbConnect();
        $where = null;
        $param = [];
        $total = $limit;
        if($keyword){
          // ini pencarian berdasarkan kata kunci
          // meliputi nama, ayah, ibu, hp & alamat
          $where = "WHERE 
          nama REGEXP :regex OR 
          # cari nama ayah
          IFNULL(JSON_UNQUOTE(JSON_EXTRACT(keluarga, '\$[0][0]')),'') REGEXP :regex OR 
          # cari nama ibu
          IFNULL(JSON_UNQUOTE(JSON_EXTRACT(keluarga, '\$[0][1]')),'') REGEXP :regex OR
          # cari nomor hp
          IFNULL(JSON_UNQUOTE(JSON_EXTRACT(catatan, '\$[1]')),'') REGEXP :regex OR
          # cari alamat
          IFNULL(JSON_UNQUOTE(JSON_EXTRACT(alamat, '\$[0]')),'') REGEXP :regex";
          $param = ['regex',$db->buildFlexibleRegex($keyword),PDO::PARAM_STR];
          // mencari seluruh data di database jika ada param & tidak melebihi batas limit
          $st = $db->prepare("SELECT COUNT(*) FROM tbbiodata $where LIMIT $limit");
          if($param)
            $st->execute([$param[0]=>$param[1]]);
          $total = $st->fetchColumn();
        }

        // Cegah offset + perPage tidak melebihi batas total
        if ($offset + $perPage > $total) 
            $perPage = max(0, $total - $offset);        

        // hasil pencarian data
        $sql = "SELECT id, upper(nama) as nama, alamat, concat(upper(tpt_lahir),', ',upper(date_format(tgl_lahir, '%e %M %Y'))) as ttl FROM tbbiodata $where ORDER BY id DESC LIMIT :offset, :limit";
        $db->query("SET lc_time_names = '{$this->data['pwa']['lang']}'");
        $stmt = $db->prepare($sql);
        if($param)
          $stmt->bindValue($param[0],$param[1],$param[2]);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $hasil = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $k => $v){          
          foreach ($v as $var => $val) {
            if($var === 'alamat'){
              $hasil[$k][$var] = $this->fn->parse_alamat($val);
            }else{
              $hasil[$k][$var] = $val;
            } 
          }
        }
        if(!$hasil)
          $total = 0;
      }
      
      $this->apiResponse(200, $hasil, array(
          'messeges' => 'Pencarian Data SKCK : '.$keyword.' '.$this->dateFormatter('2021-02-01'),
          "total" => $total
      ));

    }

    function history(){
      $rs = [];
      $ms = 'History memerlukan akses';
      if(!empty($_REQUEST['token'])){
        $ms = 'Akses history tidak dikenali';
        $tk = json_decode(\Crypto\ssl::decrypt($_REQUEST['token']),true);
        if($tk){
          $db = $this->dbConnect();
          $tb = "tbskck_reg_".date('Y');
          $st = $db->prepare("SELECT 
          (SELECT id FROM tbbiodata WHERE id=id_biodata LIMIT 1) as id,
          (SELECT upper(nama) as nama FROM tbbiodata WHERE id=id_biodata LIMIT 1) as nama,
          (SELECT agama FROM tbbiodata WHERE id=id_biodata LIMIT 1) as agama,
          (SELECT gender FROM tbbiodata WHERE id=id_biodata LIMIT 1) as gender,
          (SELECT keperluan FROM tbskck_keperluan WHERE id=id_kep LIMIT 1) as keperluan,
          date_format(tgl_terbit, '%d/%m/%Y') as tanggal
          FROM $tb WHERE id_user='{$tk['id']}' ORDER BY tgl_terbit DESC LIMIT 10");
          $st->execute();
          $rs = $st->fetchAll(PDO::FETCH_ASSOC);
          if(!$rs)
            $ms = 'History tidak ditemukan';
        }        
      }
      $this->apiResponse(200,$rs,[
        'message' => $ms
      ]);
    }

}