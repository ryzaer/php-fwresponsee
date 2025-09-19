<?php function sync_alamat(...$args){
	$query1 = <<<SQL
(
	SELECT nama 
	FROM tbarea_lama 
	WHERE kode=JSON_UNQUOTE(JSON_EXTRACT(db2.alamat, '\$.skrg[5]')) 
	LIMIT 1
) 
SQL;
    $kode_wilayah = <<<SQL
IFNULL((	
	SELECT kode 
		FROM tbarea 
		WHERE CHAR_LENGTH(kode)=13 AND REPLACE(LOWER(nama),' ','')=REPLACE(LOWER($query1),' ','') 
		LIMIT 1
),'')
SQL;   
return  <<<SQL
CONCAT(
'[',
'"',CONVERT(REPLACE(IFNULL(JSON_UNQUOTE(JSON_EXTRACT(db2.alamat,'\$.skrg[0]')),db1.alamat),'"','\"') USING utf8mb4),'"',
',"',IFNULL(JSON_UNQUOTE(JSON_EXTRACT(db2.alamat,'\$.skrg[1]')),''),'"',
',"',IFNULL(JSON_UNQUOTE(JSON_EXTRACT(db2.alamat,'\$.skrg[2]')),''),'"',
',"',$kode_wilayah,'"',
']'
)
SQL;
}