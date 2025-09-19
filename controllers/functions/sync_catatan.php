<?php function sync_catatan(...$args){
// check gelar (tidak lagi dipakai)
// $gelar = <<<SQL
// IFNULL((
//   CASE
//   WHEN REGEXP_REPLACE(db2.gelar, '[^a-zA-Z]', '') = ''
//   THEN '[]'
//   ELSE db2.gelar
//   END
// ),'[]')
// SQL;
$imigrasi = <<<SQL
IFNULL((
  CASE
  WHEN REGEXP_REPLACE(db1.nomor_pass, '[^0-9a-zA-Z]', '') = ''
  THEN ''
  ELSE TRIM(db1.nomor_pass)
  END
),'[]')
SQL;
$catatan = <<<SQL
CONCAT(
    '["',
    REPLACE(db1.alias,'"','\"'),'","',
    db1.nomor_telp,'","',
    $imigrasi,'","","',
    REPLACE(db1.kode_pasal,'"','\"'),
    '"]'
)
SQL;
return <<<SQL
(
    CASE
    WHEN REGEXP_REPLACE($catatan, '[^0-9a-zA-Z]', '') = ''
    THEN ''
    ELSE $catatan
    END
)
SQL;
}