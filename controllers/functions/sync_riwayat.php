<?php function sync_riwayat(...$args){
// check pendidikan db1
$pendidikan = <<<SQL
CONCAT(
    '["',
    REPLACE(db1.riwayat_pendidikan,'"','\"'),
    '"]'
)
SQL;
$pendidikan = <<<SQL
(
    CASE
    WHEN REGEXP_REPLACE($pendidikan, '[^a-zA-Z]', '') = ''
    THEN '[]'
    ELSE $pendidikan
    END
)
SQL;
$pendidikan = <<<SQL
CONCAT(
    '[',
    IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT(db2.pendidikan, '\$.riwayat')) AS CHAR),$pendidikan),
    ',[],[],[]]'
)
SQL;
return <<<SQL
(
    CASE
    WHEN REGEXP_REPLACE($pendidikan, '[^a-zA-Z]', '') = ''
    THEN ''
    ELSE $pendidikan
    END
)
SQL;
}