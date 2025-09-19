<?php function sql_sync_dataskck(...$args){
	return <<<SQL
START TRANSACTION;

INSERT INTO final_data (id, data)
SELECT id, data
FROM temp_data
WHERE verified = 1
ORDER BY created_at
LIMIT 5;

DELETE FROM temp_data
WHERE id IN (
  SELECT id FROM (
    SELECT id FROM temp_data
    WHERE verified = 1
    ORDER BY created_at
    LIMIT 5
  ) AS sub
);

COMMIT;
SQL;
}