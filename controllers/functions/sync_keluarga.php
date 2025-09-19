<?php function sync_keluarga(...$args){	
  // MULAI DATA AYAH
$data_ayah = <<<SQL
CONCAT(
  '["',REPLACE(db1.nama_ayah,'"','\"'),
  '",',db1.id_pekerjaan_ayah,
  ',"',REPLACE(db1.alamat_ayah,'"','\"'),
  '"]'
)
SQL;
$data_ayah = <<<SQL
(
  CASE 
    WHEN REGEXP_REPLACE($data_ayah, '[^a-zA-Z]', '') = ''
    THEN '[]'
    ELSE $data_ayah
  END
)
SQL;
  // data ibu
$data_ibu = <<<SQL
CONCAT(
  '["',REPLACE(db1.nama_ibu,'"','\"'),
  '",',db1.id_pekerjaan_ibu,
  ',"',REPLACE(db1.alamat_ibu,'"','\"'),
  '"]'
)
SQL;
$data_ibu = <<<SQL
(
  CASE 
    WHEN REGEXP_REPLACE($data_ibu, '[^a-zA-Z]', '') = ''
    THEN '[]'
    ELSE $data_ibu
  END
)
SQL;
 $saudara_1 = <<<SQL
(
    CASE 
      WHEN REGEXP_REPLACE(db1.saudara, '[^a-zA-Z]', '') = ''
      THEN ''
      ELSE CONCAT('["',REPLACE(db1.saudara,'"','\"'),'"]')
    END
)
SQL;
 $saudara_2 = <<<SQL
(
    CASE 
      WHEN IFNULL(REGEXP_REPLACE(db2.data_sdr, '[^a-zA-Z]', ''),'') = ''
      THEN '[]'
      ELSE db2.data_sdr
    END
)
SQL;
// DATA PASANGAN DB2
  $pasangan = <<<SQL
CONCAT(
  '["',IFNULL(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(db2.data_kel,'\$.pasangan[0]')),'"','\"'),REPLACE(db1.nama_pasangan,'"','\"')),
  '","',IFNULL(JSON_UNQUOTE(JSON_EXTRACT(db2.data_kel,'\$.pasangan[1]')),db1.tanggal_lahir_pasangan),
  '",',IFNULL(JSON_UNQUOTE(JSON_EXTRACT(db2.data_kel,'\$.pasangan[2]')),db1.id_pekerjaan_pasangan),
  ',"',IFNULL(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(db2.data_kel,'\$.pasangan[3]')),'"','\"'),REPLACE(db1.alamat_pasangan,'"','\"')),
  '"]'
)
SQL;
  $pasangan = <<<SQL
(
  CASE
    WHEN  REGEXP_REPLACE($pasangan, '[^a-zA-Z]', '') = ''
    THEN '[]'
    ELSE $pasangan
  END
)
SQL;
// DATA ANAK
 $anak = <<<SQL
CONCAT(
  '["',
  CONCAT_WS(
    CHAR(13,10),
    db1.nama_anak_1,
    db1.nama_anak_2,
    db1.nama_anak_3,
    db1.nama_anak_4,
    db1.nama_anak_5
  ),
  '"]'
)
SQL;
$anak = <<<SQL
(
  CASE
    WHEN $anak = '["\r\n\r\n\r\n\r\n"]'
    THEN '[]'
    ELSE $anak
  END
)
SQL;
// CHECK DATA ANAK di DB2
 $anak = <<<SQL
IFNULL(CAST(JSON_EXTRACT(db2.data_kel,'\$.anak') AS CHAR),$anak)
SQL;
 $anak = <<<SQL
(
  CASE
    WHEN REGEXP_REPLACE($anak, '[^a-zA-Z]', '') = ''
    THEN '[]'
    ELSE $anak
  END
)
SQL;

// RETURN AKHIR DATA KELUARGA
$check = <<<SQL
CONCAT(
  '[',$data_ayah,',',$data_ibu,',',IFNULL($saudara_2, $saudara_1),',',$pasangan,',',$anak,']'
)
SQL;
// RETURN AKHIR DATA KELUARGA
return <<<SQL
(
    CASE 
    WHEN REGEXP_REPLACE($check, '[^a-zA-Z]', '') = ''
    THEN ''
    ELSE $check
    END 
)
SQL;
}