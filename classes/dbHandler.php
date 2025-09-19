<?php
class dbHandler
{
    protected $handler;
    protected $allowBlob=false;
    protected $extension='mp4|mp3|jpg|png|gif|webp|pdf|doc|xls|txt|csv|zip|tar|7z';
    protected $format;
    protected $paginationState = false;
    protected $paginationResult = [];
    function __construct($handler,$extension=null) {
        $this->handler = $handler;
        if($extension)
            $this->extension = $extension;

        $this->format = $this->extension;
        return $this;
    }

    function prepare(...$query)
    {
        return $this->handler->prepare(...$query);
    }
    function query(...$query)
    {
        return $this->handler->query(...$query);
    }
    
    function quote(...$query)
    {
        return $this->handler->quote(...$query);
    }

    function exec(...$query)
    {
        return $this->handler->exec(...$query);
    }

    function blob($extension=null) {
        if($extension)
            $this->format = $extension;
        $this->allowBlob = true;
        return $this;
    }
    
    function insert(string $table, array $data): int {
        $keys = array_keys($data);
        $placeholders = array_map(fn($k) => ":$k", $keys);
        $sql = "INSERT INTO `$table` (" . implode(',', $keys) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $this->handler->prepare($sql);

        foreach ($data as $key => $value) {
            $isBlob = $this->allowBlob && $this->checkFile($value);
            $vals = $isBlob ? $this->isAllowFile($value, $this->format) : $value;
            $type = $isBlob ? PDO::PARAM_LOB : ( is_numeric($vals) ? PDO::PARAM_INT : PDO::PARAM_STR );
            $stmt->bindValue(":$key", $vals, $type);
        }
        $this->format = $this->extension;
        $this->allowBlob = false;
        $stmt->execute();
        return (int) $this->handler->lastInsertId();
    } 

    public function update(string $table, array $data, array $where, bool $useLike = false, array $orWhere = []): bool {
        
        $setParts = [];
        foreach (array_keys($data) as $col) {
            $setParts[] = "$col = :set_$col";
        }

        $sql = "UPDATE `$table` SET " . implode(", ", $setParts);

        $params = [];
        $conditions = [];

        foreach ($where as $key => $value) {
            $conditions[] = $useLike ? "$key LIKE :$key" : "$key = :$key";
            $params[":$key"] = $useLike ? "%$value%" : $value;
        }

        if (!empty($orWhere)) {
            $orConditions = [];
            foreach ($orWhere as $key => $value) {
                $orConditions[] = $useLike ? "$key LIKE :or_$key" : "$key = :or_$key";
                $params[":or_$key"] = $useLike ? "%$value%" : $value;
            }
            if (!empty($orConditions))
                $conditions[] = '( ' . implode(' OR ', $orConditions) . ' )';            
        }

        if (!empty($conditions))
            $sql .= ' WHERE ' . implode(' AND ', $conditions);        

        $stmt = $this->handler->prepare($sql);

        foreach ($data as $key => $value) {
            $isBlob = $this->allowBlob && $this->checkFile($value);
            $vals = $isBlob ? $this->isAllowFile($value, $this->format) : $value;
            $type = $isBlob ? PDO::PARAM_LOB : ( is_numeric($vals) ? PDO::PARAM_INT : PDO::PARAM_STR );
            $stmt->bindValue(":set_$key", $vals, $type);
        }

        foreach ($params as $paramKey => $paramValue)
            $stmt->bindValue($paramKey, $paramValue);

        $this->format = $this->extension;
        $this->allowBlob = false;

        return $stmt->execute();
    }

    public function delete(string $table, array $where): bool {
        $parts = [];
        foreach ($where as $key => $_)
            $parts[] = "$key = :$key";
        
        $sql = "DELETE FROM `$table` WHERE " . implode(' AND ', $parts);
        $stmt = $this->handler->prepare($sql);

        foreach ($where as $key => $value) 
            $stmt->bindValue(":$key", $value);
        
        return $stmt->execute();
    }

    public function pagination(int $perPage, int $currentPage = 1, int $range = 5): self {
        $this->paginationState = [
            'perPage' => $perPage,
            'currentPage' => $currentPage,
            'range' => $range
        ];
        return $this;
    }

    private function buildCondition(string &$sql, array &$params, array $where, bool $useLike, bool $useRegex, array $orWhere, string $groupBy, string $having, string $order): void {
        $conditions = [];

        if (!empty($where)) {
            foreach ($where as $key => $value) {
                if ($useRegex) {
                    $conditions[] = "$key REGEXP :$key";
                    $params[":$key"] = $value;
                } else {
                    $conditions[] = $useLike ? "$key LIKE :$key" : "$key = :$key";
                    $params[":$key"] = $useLike ? "%$value%" : $value;
                }
            }
        }

        if (!empty($orWhere)) {
            $orConditions = [];
            foreach ($orWhere as $key => $value) {
                if ($useRegex) {
                    $orConditions[] = "$key REGEXP :or_$key";
                    $params[":or_$key"] = $value;
                } else {
                    $orConditions[] = $useLike ? "$key LIKE :or_$key" : "$key = :or_$key";
                    $params[":or_$key"] = $useLike ? "%$value%" : $value;
                }
            }
            if (!empty($orConditions)) {
                $conditions[] = '( ' . implode(' OR ', $orConditions) . ' )';
            }
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        if (!empty($groupBy)) {
            $sql .= " GROUP BY $groupBy";
        }

        if (!empty($having)) {
            $sql .= " HAVING $having";
        }

        if (!empty($order)) {
            $sql .= " ORDER BY $order";
        }
    }

    public function select(string $table, array $where = [], bool $useLike = false, bool $useRegex = false, array $orWhere = []): array {
        $columns = '*';
        $order = '';
        $limit = '';
        $groupBy = '';
        $having = '';

        if (preg_match_all('/\[~(.*?)~\]|\(~(.*?)~\)|\{~(.*?)~\}|<~(.*?)~>|:~(.*?)~:/', $table, $matches, PREG_SET_ORDER)) {
            array_walk_recursive($matches, function (&$value) {
                $value = trim($value);
            });
            foreach ($matches as $match) {
                $table = trim(str_replace($match[0], '', $table));
                if (!empty($match[1])) $columns = $match[1] ?? $columns;
                if (!empty($match[2])) $order = $match[2];
                if (!empty($match[3])) $limit = $match[3];
                if (!empty($match[4])) $groupBy = $match[4];
                if (!empty($match[5])) $having = $match[5];
            }
        }

        $sql = "SELECT $columns FROM `$table`";
        $params = [];

        $this->buildCondition($sql, $params, $where, $useLike, $useRegex, $orWhere, $groupBy, $having, $order);

        if (!empty($limit)) {
            if (preg_match('/^(\d+)\s*,\s*(\d+)$/', $limit, $pages)) {
                $page = (int)$pages[1];
                $perPage = (int)$pages[2];
                $offset = ($page - 1) * $perPage;
                $sql .= " LIMIT $offset, $perPage";
            } else {
                $sql .= " LIMIT $limit";
            }
        }

        if ($this->paginationState) {
            $countSql = "SELECT COUNT(*) FROM `$table`";
            $this->buildCondition($countSql, $params, $where, $useLike, $useRegex, $orWhere, $groupBy, $having, $order);

            $stmt = $this->handler->prepare($countSql);
            $stmt->execute($params);
            $totalRows = (int) $stmt->fetchColumn();

            $perPage = $this->paginationState['perPage'];
            $currentPage = $this->paginationState['currentPage'];
            $range = $this->paginationState['range'];

            $totalPages = (int) ceil($totalRows / $perPage);

            $startPage = max(1, $currentPage - floor($range / 2));
            $endPage = min($totalPages, $startPage + $range - 1);

            if ($endPage - $startPage + 1 < $range) {
                $startPage = max(1, $endPage - $range + 1);
            }

            $pageList = range($startPage, $endPage);
            $lastThreePages = $totalPages >= 3 ? range($totalPages - 2, $totalPages) : range(1, $totalPages);

            $this->paginationResult = [
                'pages' => [
                    'total' => $totalPages,
                    'current' => $currentPage,
                    'first' => 1,
                    'range' => $pageList,
                    'Last3' => $lastThreePages
                ]
            ];
        }

        $stmt = $this->handler->prepare($sql);
        $stmt->execute($params);

        $resultData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($this->paginationState) {
            $this->paginationResult['data'] = $resultData;
            $this->paginationState = false;
            return $this->paginationResult;
        }

        return $resultData;
    }

    function create(string $table, array $columns, string $engine = 'InnoDB', string $charset = 'utf8mb4'): bool
    {
        $fields = [];
        foreach ($columns as $name => $definition)
            $fields[] = "`$name` $definition";

        $sql = "CREATE TABLE IF NOT EXISTS `$table` (" . implode(',', $fields) . ") ENGINE=$engine DEFAULT CHARSET=$charset;";
        return $this->handler->exec($sql) !== false;
    }

    // untuk membuat parse regex permutasi kata
    function buildFlexibleRegex($keyword) {
        // Bersihkan dan pisahkan kata
        $words = preg_split('/\s+/', trim($keyword));
        $words = array_filter($words); // hapus yang kosong
        $words = array_map(function($w) {
            return preg_quote($w, '/'); // escape karakter khusus regex
        }, $words);

        // Jika cuma satu kata, cukup pakai langsung
        if (count($words) === 1) {
            return $words[0];
        }

        // Buat semua kombinasi urutan kata
        $permutations = $this->permute($words);

        // Gabungkan tiap kombinasi dengan ".*" antar katanya
        $regexParts = array();
        foreach ($permutations as $perm) {
            $regexParts[] = implode('.*', $perm);
        }

        // Gabungkan jadi satu regex dengan OR "|"
        return implode('|', $regexParts);
    }

    // Fungsi bantu untuk menghasilkan semua permutasi kata
    private function permute($items, $perms = array()) {
        if (empty($items)) {
            return array($perms);
        } else {
            $result = array();
            for ($i = 0; $i < count($items); $i++) {
                $newItems = $items;
                $newPerms = $perms;
                list($item) = array_splice($newItems, $i, 1);
                array_push($newPerms, $item);
                $result = array_merge($result, $this->permute($newItems, $newPerms));
            }
            return $result;
        }
    }

    private function checkFile(string $path):bool {
        return  is_readable($path) ? true : false;
    }

    private function isAllowFile(string $filename, string $formatList):string {
        // Buka fileinfo
        $mime_type = $this->getMimeFile($filename);
        $extension = $this->getExtension($mime_type);
        $allowed = explode('|', $formatList);
        if(in_array($extension,$allowed)){
           return file_get_contents($filename);
        }else{
           return '';
        }
    }  

    // mime parse map
    private static function parseMimeFile($mimeFile)
    {
        $lines = file($mimeFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $mimeMap = [];
        foreach ($lines as $line) {
            // Abai comment
            if (strpos($line, '#') === 0) continue;
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) > 1) {
                // Ambil tipe mime
                $mimeType = array_shift($parts); 
                foreach ($parts as $ext) 
                    // Tambahkan ke peta ekstensi
                    $mimeMap[$mimeType][] = $ext;
            }
        }
        return $mimeMap;
    }

    static function getMimeFile($filePath,$isfile=true):string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $fmime = '';
        if($isfile){
            $fmime = finfo_file($finfo,$filePath);
        }else{
            $fmime = finfo_buffer($finfo,$filePath);
        }
        finfo_close($finfo);
        return $fmime;
    }

    static function getExtension($mimeType):string
    {
        
        $mimeFile = __DIR__.'/mime.types';
        $mimeMap = [];

        if (!file_exists($mimeFile))
            throw new Exception("mime.types file is missing!: {$mimeFile}");

        $mimeMap = self::parseMimeFile($mimeFile);

        if(is_bool($mimeType)){
            if($mimeType)
                // Ambil ekstensi jika true
                return $mimeMap[$mimeType] ?? [];
            else
                // Ambil mimetype dan ekstensi jika false
                return $mimeMap;            
        }else{
            if (isset($mimeMap[$mimeType]))
                // Ambil ekstensi pertama yang ditemukan
                return $mimeMap[$mimeType][0];
        }
        
        return 'unknown';
    }
}