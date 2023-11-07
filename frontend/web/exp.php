<?php

class Pager
{

    public $messages = array();
    public $errors = array();

    public function addMessage($message, $error = '', $sql = '')
    {
        $this->messages []= array($message, $error, $sql);
    }

    public function addError($message)
    {
        $this->errors []= $message;
    }

    /**
     * Доп пункты меню
     */
    public function printMenus()
    {
        if (Bitrix::has()) {
            echo '<a href="?action=bitrix">Bitrix</a>';
        }
    }

    /**
     * Выполняется на старте, перед запуском сессии
     */
    public function onStart() {
        Bitrix::onStart();
        Utils::onStart();
    }

    public static function actionsExec($className)
    {
        $page = GET('page');
        if (GET('action') != mb_strtolower($className)) {
            return ;
        }
        if (!$page) {
            $page = 'index';
        }
        $utils = new $className;
        if (method_exists($utils, $page)) {
            ob_start();
            try {
                $utils->$page();
            } catch (\Exception $e) {
                error($e->getMessage());
            }
            $utils::$output = ob_get_contents();
            ob_end_clean();
        } else {
            throw new \Exception('Метод "'.$page.'" не существует');
        }
    }

    /**
     * В блоке действий перед html кодом
     */
    public function actions()
    {
        self::actionsExec('Bitrix');
        self::actionsExec('Utils');
    }

    public function printSubMenu($menu)
    {
        ?>
        <hr>
        <ul class="nav nav-pills small">
            <?php
            foreach ($menu as $k => $page) {
                $add = '';
                if ($_GET['page'] == $page) {
                    $add = ' active';
                }
                $link = '?action='.$_GET['action'];
                if ($page) {
                    $link .= '&page='.$page;
                    if ($page == 'auth') {
                        $link .= '&id=1';
                    }
                    if ($page == 'cashclear') {
                        $link .= '&stat=1';
                    }
                }
                ?>
                  <li class="nav-item">
                    <a class="nav-link<?=$add?>" href="<?=$link?>"><?=$k?></a>
                  </li>
                <?php
            }
            ?>
        </ul>
        <?php
    }
}
// Библиотека общих функций по экспорту таблиц БД block
class Exporter
{
    public $db;
    public $table;
    public $data;
    public $tableStructure = array();
    public $comments = true;
    public $fields   = array();

    public $addIfNot  = false;
    public $addAuto   = true;
    public $addKav    = true;

    public $insFull   = false;
    public $insExpand = false;
    public $insZapazd = false;
    public $insIgnor  = false;

    /**
    * Позволяет сразу установить опции экспорта
    */
    public function __construct($db = null, $table = null, $header = null)
    {
        $this->table  = $this->tableb = $table;
        $this->db     = $db;
        $this->data   = $header;
        return $this;
    }

    public static function exportInit($server, $database)
    {
        $dumpHeader =
        '-- '.MS_APP_NAME.' '.MS_APP_VERSION.' SQL Экспорт'.
        "\n".'--'.
        "\n".'-- Хост: '.$server.
        "\n".'-- Время создания: '.date('j.m.Y, H-i').
        "\n".'-- Версия сервера: '.getServerVersion().
        "\n".'-- Версия PHP: '.phpversion().
        "\n".'--'.
        "\n".'-- БД: `'.$database.'`'.
        "\n".'--'.
        "\n\n".'--';

        $exp = new Exporter();
        $exp->setComments(1);
        $exp->setHeader($dumpHeader);
        $exp->setOptionsStruct($addIfNot = true, $addAuto = true, $addKav = 1);
        $exp->setOptionsData(
            POST('query_list_fields', 0),
            POST('one_query', 0),
            POST('ins_zadazd', 0),
            POST('ins_ignore', 0)
        );
        // Экспорт БД
        $exp->setDatabase($database);

        return $exp;
    }

    public function exportTables($tables, $full = 0)
    {
        $exp = new Exporter('komimu2');
        $exp->setComments(0);
        $exp->setOptionsStruct($addIfNot = true, $addAuto = true, $addKav = 1);
        $exp->setOptionsData($full, $one_query = 0, $ins_zadazd = 0, $ins_ignore = 0);


        foreach ($tables as $table) {
            if (!$table) {
                continue;
            }

            $exp->setTable($table);
            $exp->exportStructure($addDelim = true, $addDrop = false);

            $what = $_GET['what'][$table];
            if (!$what) {
                $what = '*';
            }
            $max = $_GET['max'][$table];
            $limit = $_GET['limit'][$table];
            if (!$limit) {
                $limit = 1000;
            }

            $i = 0;
            while (true) {
                $where = 1;
                if ($_GET['where'][$table]) {
                    $where = $_GET['where'][$table];
                }
                $where = ' '.$where.' LIMIT '.$i.','.$limit.'';
                $resultsCount = $exp->exportData('INSERT', $where, $skipAi = 0, $what);
                if (!$resultsCount) {
                    break;
                }
                if ($max && $i > $max) {
                    break;
                }
                $i += 1000;
            }
        }

        return $exp->data;
    }

    /**
    * Запускает комплексный процесс экспорта
    *
    * $isStruct
    * $isData
    * $addDelim
    * $addDrop
    * $type
    * $where
    */
    public function startFull(
        $isStruct = true,
        $isData = true,
        $addDelim = true,
        $addDrop = false,
        $type = 'INSERT',
        $where = null
    ) {
        if ($isStruct) {
            $this -> exportStructure($addDelim, $addDrop);
        }
        if ($isData) {
            $this -> exportData($type, $where);
        }
        return $this->get();
    }

    // Установить текущую базу данных
    public function setDatabase($a)
    {
        global $mysqli;
        if ($this->db != $a) {
            $this->db = $a;
            $mysqli->select_db($this->db);
        }
    }

    // Установить текущую таблицу
    public function setTable($a)
    {
        $this->table     = $a;
        if ($this->addKav) {
            $this->tableb = "`$a`";
        } else {
            $this->tableb = $a;
        }
        return $this;
    }

    // Установить шапку к дампу
    public function setHeader($a)
    {
        if ($this->comments) {
            $this->data .= $a;
        }
    }

    // Добавлять или нет комментарии
    public function setComments($a)
    {
        $this->comments = (bool)$a;
    }

    /**
    * Установить некоторые опции экспорта структуры
    */
    public function setOptionsStruct($addIfNot, $addAuto, $addKav)
    {
        $this->addIfNot = $addIfNot;
        $this->addAuto = $addAuto;
        $this->addKav = $addKav;
    }

    /**
    * УСтавноить некоорые опции экспорта данных
    */
    public function setOptionsData($insFull, $insExpand, $insZapazd, $insIgnor)
    {
        $this->insFull = $insFull;
        $this->insExpand = $insExpand;
        $this->insZapazd = $insZapazd;
        $this->insIgnor = $insIgnor;
    }

    /**
    * Получить полный текст дампа
    * @ $clear - очистить объект (экономия памяти)
    */
    public function get()
    {
        return $this->data;
    }

    /**
    * Заворачивает дамп в нужный вид и отправляет
    *
    * @ $type - тип отправки, значения:
    *   'textarea' - создаёт форму
    *   'zip' - создаёт архив и отправляет
    * @ $file - имя файла дампа для типа 'zip'
    */
    public function send($type = 'textarea', $file = null, $saveTo = '')
    {
        return $this->sendSqlDamp($type, $file, $saveTo);
    }

    function addComments($title, $wr) {
        if ($this->comments) {
            return $wr.'--'.$wr.'-- '.$title.$wr.'--'.$wr.$wr;
        }
        return '';
    }

    // ниже - внутренние функции, реализация

    /**
    * Возврвщает дамп структуры таблицы (sql запрос создания таблицы)
    * @$this->table - имя таблицы
    * @$addDrop - добавить к запросу удаление таблицы + форматировать через ;
    * @$this->comments - добавить комментарий
    */
    public function exportStructure($addDelim = true, $addDrop = false)
    {
        if (!$this->tableb) {
            return ;
        }

        $delim = ";\r\n";
        $wr = "\r\n";
        $tab = '  ';
        $dump = $this->addComments('Структура таблицы '.$this->table, $wr);

        $ife = null;
        if ($addDrop) {
            $if = null;
            if ($this->addIfNot) {
                $if = 'IF EXISTS ';
            }
            $dump .= 'DROP TABLE '.$if.$this->tableb.$delim;
        }
        if ($this->addIfNot) {
            $ife = 'IF NOT EXISTS ';
        }

        $dump .= 'CREATE TABLE '.$ife. $this->tableb . ' ('.$wr.$tab;

        // дамп полей
        $sql = 'SHOW FIELDS FROM '.$this->tableb;
        $result = query($sql);
        if (!$result) {
            return ;
        }
        $fields = array();
        $this->fields = array();
        $extraData = array();
        while ($row = $result->fetch_assoc()) {
            if (is_array($row['value'])) {
                $row = $row['value'];
            }
            $this->fields []= $row;
            if ($this->addKav) {
                $field_info  = '`' . $row['Field'] . '` ' . $row['Type'];
            } else {
                $field_info  = '`'.$row['Field'] . '` ' . $row['Type'];
            }
            if ($row['Null'] != 'YES') {
                $field_info .=  ' NOT NULL';
            }

            if ($row['Type'] == 'timestamp') {
                if ($row['Default'] != '') {
                    $row['Default'] = $row['Default'] == 'CURRENT_TIMESTAMP' ? $row['Default'] : '\''.
                        $row['Default'].'\'';
                    $field_info  .=  ' default '.$row['Default'];
                }
            } elseif ($row['Default'] != null || ($row['Null'] != 'YES' && !strchr($row['Type'], 'text'))) {
                if (!stristr($row['Extra'], 'auto')) {
                    if ($row['Null'] == 'YES' || $row['Default'] != '') {
                        $field_info .=  ' default \''.$row['Default'].'\'';
                    }
                }
            } elseif (!strchr($row['Type'], 'text')) {
                $field_info .=  ' default NULL';
            }
            if ($row['Extra'] != '') {
                $extraData [$row['Extra']][]= $field_info.' '.$row['Extra'];
            }
            $fields []= $field_info;
        }
        $dump .=  implode(','.$wr.$tab, $fields);
        // кодировка, тип, автоинкремент
        $ai = null;
        $comment = null;
        $charset = 'utf8';
        $engine = 'MyISAM';
        $pack = null;
        if (!isset($this->tableStructure[$this->db])) {
            $sql = "SHOW TABLE STATUS FROM $this->db";
            $result = query($sql);
            $this->tableStructure[$this->db] = array();
            if ($result) {
                while ($row = $result->fetch_object()) {
                    if (isset($row->value) && is_array($row->value)) {
                        $row = $row->value;
                    }
                    $this->tableStructure [$this->db][]= $row;
                }
            }
        }
        foreach ($this->tableStructure[$this->db] as $row) {
            if ($row->Name == $this->table) {
                $ai = $row->Auto_increment;
                $charset = $row->Collation;
                $comment = $row->Comment;
                $engine = $row->Engine;
                $pack = $row->Create_options;
                break;
            }
        }
        if (!$this->addAuto || $ai == null) {
            $ai = null;
        } else {
            $ai = ' AUTO_INCREMENT='.$ai.' ';
        }
        if (strlen($pack) > 0) {
            $pack = ' '.$pack;
        }
        if ($comment != null) {
            $comment = ' COMMENT="'.$comment.'"';
        }
        if (strchr($charset, '_')) {
            $charset = str_replace(strchr($charset, '_'), '', $charset);
        }
        $dump .= $wr.") ENGINE=$engine DEFAULT CHARSET=$charset$pack$ai$comment";

        // Alter keys

        // ключи
        $keys = array();
        $keys['PRI'] = $keys['UNI'] = $keys['MUL'] = $keys['FULL'] = array();
        $parts = array();
        $sql = 'SHOW KEYS FROM '.$this->tableb;
        $result = query($sql);
        $x = $this->addKav ? '`' : '';
        while ($row = $result->fetch_assoc()) {
            if (is_array($row['value'])) {
                $row = $row['value'];
            }
            $row['Column_name'] = $x.$row['Column_name'].$x;
            if ($row['Sub_part'] > 0) {
                $row['Column_name'] .= '('.$row['Sub_part'].')';
            }
            if ($row['Key_name'] == 'PRIMARY') {
                $keys['PRI'][] = $row['Column_name'];
            } elseif ($row['Index_type'] == 'FULLTEXT') {
                $keys['FULL'][$row['Key_name']][] = $row['Column_name'];
            } elseif ($row['Non_unique'] == '0') {
                $keys['UNI'][$row['Key_name']][] = $row['Column_name'];
            } else {
                $keys['MUL'][$row['Key_name']][] = $row['Column_name'];
            }
        }
        // обработка ключей
        $keysText = array();
        if (count($keys['PRI']) > 0) {
            $keysText [] = "ADD PRIMARY KEY  (" . implode(",", $keys['PRI']) . ")";
        }
        if (count($keys['UNI']) > 0) {
            foreach ($keys['UNI'] as $k => $c) {
                $keysText [] = "ADD UNIQUE KEY $x".$k."$x (" .implode(",", $c). ")";
            }
        }
        if (count($keys['MUL']) > 0) {
            foreach ($keys['MUL'] as $k => $c) {
                $keysText [] = "ADD KEY $x".$k."$x (" .implode(",", $c). ")";
            }
        }
        if (count($keys['FULL']) > 0) {
            foreach ($keys['FULL'] as $k => $c) {
                $keysText [] = "ADD FULLTEXT KEY $x".$k."$x (" .implode(",", $c). ")";
            }
        }
        if (count($keysText) > 0) {
            $dump .= $delim;
            $dump .= $this->addComments('Индексы таблицы '.$this->table, $wr);
            $dump .= 'ALTER TABLE '.$this->tableb.''.$wr.$tab;
            $dump .= implode(','.$wr.$tab, $keysText);
        }
        // AUTO_INCREMENT для сохранённых таблиц
        if ($extraData) {
            foreach ($extraData as $type => $fields) {
                foreach ($fields as $field) {
                    $dump .= $delim;
                    $dump .= $this->addComments($type.' для таблицы '.$this->table, $wr);
                    $dump .= 'ALTER TABLE '.$this->tableb.''.$wr.$tab;
                    $dump .= 'MODIFY '.$field;
                }
            }
        }

        if ($addDelim) {
            $dump .= $delim;
        } else {
            $dump .= $wr;
        }

        return $this->data .= $dump;
    }


    /**
    * Возврвщает дамп данных таблицы (sql запрос )
    * @param string   тип экспорта (INSERT-REPLACE-UPDATE)
    * @param string   SQL условие
    * @param boolean  пропускать ли поля с auto_increment
    */
    public function exportData($type = 'INSERT', $where = null, $skipAi = false, $what = '*')
    {
        global $memory_limit, $mysqli;
        if (!$memory_limit) {
            $memory_limit = (intval(ini_get('memory_limit')) * 1024 * 1024) / 2;
        }
        $dump = '';
        $delim = ";\r\n";
        $wr   = "\r\n";
        $tab = '    ';
        if (is_null($where) || strlen(trim($where)) < 2) {
            $sql = "SELECT $what FROM $this->tableb";
        } else {
            if (stristr($where, 'WHERE ')) {
                $sql = "SELECT $what FROM $this->tableb $where";
            } else {
                $sql = "SELECT $what FROM $this->tableb WHERE $where";
            }
        }

        $q_result = query($sql);
        if (!$q_result) {
            return ;
        }

        // поля
        $f = getFields($this->table);
        $fnames = array();
        foreach ($f as $i => $v) {
            if ($skipAi && $v->Extra != '') {
                continue;
            }
            if ($what && $what != '*' && strpos($what, $v->Field) === false) {
                unset($f[$i]);
                continue;
            }
            $fnames[] = $v->Field;
        }
        if ($what && $what != '*') {
            $f = array_values($f);
        }
       // echo '<pre>'; print_r($f); echo '</pre>';
        // подготовка для INSERT
        $typeName = substr($type, 0, 6);
        if ($this->insZapazd && $typeName != 'UPDATE') {
            $type = $type.' DELAYED';
        }
        if ($this->insIgnor && $typeName != 'REPLAC') {
            $type = $type.' IGNORE';
        }
        if ($this->insFull) {
            $start = $type.' INTO '.$this->tableb.' (`'.implode('`,`', $fnames).'`) VALUES (';
        } else {
            $start = $type.' INTO '.$this->tableb.' VALUES (';
        }
        if ($this->insExpand && $typeName != 'UPDATE') {
            $dump .= $type.' INTO '.$this->tableb.' (`'.implode('`,`', $fnames).'`) VALUES ';
        }
        $count = 0;
        $isFullDump = true;
        while ($row = $q_result->fetch_assoc()) {
            if ($func == 'each') {
                $row = $row[1];
            }
            //echo '<pre>'; print_r($row); echo '</pre>';
            if (memory_get_usage() > $memory_limit) {
                $isFullDump = $count;
                break;
            }
            // UPDATE
            if ($typeName == 'UPDATE') {
                $a = array();
                $primary = array();
                foreach ($f as $i => $v) {
                    if (isset($row[$v->Field])) {
                        if (stristr($v->Type, 'int')) {
                            $val =  $row[$v->Field];
                        } else {
                            $val = $mysqli->real_escape_string($row[$v->Field]);
                            $val = str_replace("\r\n", '\r\n', $val);
                            $val =  '\'' . $val . '\'';
                        }
                    } else {
                        $val = 'NULL';
                    }
                    $b = $v->Field;
                    if ($this->addKav) {
                        $b = '`'.$b.'`';
                    }
                    $a[] = $b . '=' . $val;
                    if ($v->Key == 'PRI') {
                        $primary []= $b.'='.$val;
                    }
                }
                $dump .= 'UPDATE '.$this->tableb.' SET '.implode(', ', $a).
                    ' WHERE '.implode(' AND ', $primary) . $delim;
            // INSERT - REPLACE
            } elseif ($typeName == 'INSERT' || $typeName == 'REPLAC') {
                $values = array();
                //echo '<pre>'; print_r($row); echo '</pre>';
                foreach ($f as $i => $v) {
                    if ($skipAi && $v->Extra != '') {
                        continue;
                    }
                    //echo '<br />'.$v->Field;
                    if (isset($row[$v->Field])) {
                        if (stristr($v->Type, 'int')) {
                            $val =  $row[$v->Field];
                        } else {
                            $val = $mysqli->real_escape_string($row[$v->Field]);
                            $val = str_replace("\r\n", '\r\n', $val);
                            $val =  '\'' . $val . '\'';
                        }
                    } else {
                        $val = $v->Null == 'YES' ? 'NULL' : '""';
                    }
                    $values []= $val;
                }
                if ($this->insExpand) {
                    if ($count == 50) {
                        $count = 0;
                        $dump  = substr($dump, 0, strlen($dump) - 3) . $delim;
                        $dump .= $start . implode(',', $values) . ')' . $delim;
                    } else {
                        $dump .= '('.implode(',', $values) . ')'.",\r\n";
                    }
                } else {
                    $dump .= $start . implode(',', $values) . ')' . $delim;
                }
            }
            $count ++;
            if ($count % 1000 == 0 && function_exists('addLog')) {
                addLog('экспортировано строк: '.$count.'');
            }
        }
        if ($this->insExpand) {
            $dump = substr($dump, 0, strlen($dump) - 3) . $delim;
        }
        if ($dump != null) {
            $dump = $dump . $wr;
            if ($this->comments) {
                $dump = $wr.'--'.$wr.'-- Дамп данных таблицы '.$this->table.$wr.'--'.$wr.$wr.$dump;
            }
        }
        $this->data .= $dump;
        return $isFullDump;
    }

    public function sendSqlDamp($type = 'textarea', $file = null, $saveTo = '')
    {
        if (is_null($file)) {
            $file = $this->db != null ? $this->db : $this->table;
        }
        // текстовое поле
        if ($type == 'textarea') {
            return '<textarea name="sql" style="width:1050px; height:500px;; font-size:11px;" wrap="OFF">'.
            htmlspecialchars($this->get()).
            '</textarea>';
            // zip архив
        } elseif ($type == 'zip') {
            if ($saveTo) {
                Zip::create($file.'.sql', $saveTo, $this->get());
                return ;
            }
            echo 'Не реализовано';
            exit;
            /*if (headers_sent()) {
                return '<h3>headers_sent...</h3>';
            }
            header("Content-type: application/zip");
            header("Content-Disposition: attachment; filename=$file.zip");
            Zip::show($file.'.sql', $this->get());
            exit;*/
        }
    }


    public function exportByPart($table, $resultsCount = '', $folder = '')
    {
        $limit = $_POST['partSize'];
        if (!$limit) {
            $limit = 10000;
        }
        $this->data = '';
        $i = 0;
        while (true) {
            $filename = $folder.'/'.$table.'.'.($i + 1).'.zip';
            if (file_exists($filename)) {
                addLog('File '.$filename.' exist');
                $i ++;
                continue;
            }
            $start = $i * $limit;
            elog("------- Export $table FROM $start TO $limit by part", 1);
            $resultsCount = $this->exportData('INSERT', $where = "1 LIMIT $start, $limit");
            if (empty($this->data)) {
                break;
            }
            if (is_numeric($resultsCount)) {
                elog(' .... Exported only '.$resultsCount.' rec - memory limit '.memory_get_usage(), 0);
            }
            $i++;
            saveData2file($table.'.'.$i, $this->data, 'zip', $folder);
            $this->data = '';
        }
    }
}
class DbConnect
{

    public $connect_error;

    public static function connect($server, $user, $pass, $database = '')
    {
        global $mysqli;
        if (class_exists('mysqli')) {
            $mysqli = new mysqli($server, $user, $pass);
        } else {
            $mysqli = new PdoEx($server, $user, $pass, $database);
            /*if ($pdo->connection) {
                $mysqli = $pdo->connection;
            } else {
                $this->connect_error = $pdo->connect_error;
            }*/
        }
        return $mysqli;
    }

    public static function query($sql)
    {
        global $mysqli;
        if (class_exists('mysqli')) {
            $mysqli->query($sql);
        } else {
            $mysqli->exec($sql);
        }

    }

    public static function close()
    {
    }
}


class PdoEx
{

    public $connect_error;
    public $connection;

    public function onError()
    {
        $this->error = $this->connection->errorInfo();
        $this->error = $this->error[2];
        if ($this->error) {
            //echo '<p style="color:red">'.$this->connection->errorInfo()[2].'</p>';
        }
    }

    public function __construct($server, $user, $password, $db)
    {
        $options = array(
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"
        );

        try {
            if (!$db) {
                $db = 'mysql';
            }
            $dsn = 'mysql:host='.$server.';dbname='.$db.'';
            $this->connection = new PDO($dsn, $user, $password, $options);
        } catch (PDOException $e) {
            $this->connect_error = 'Подключение не удалось: ' . $e->getMessage();
        }
    }

    public function selectDb($database)
    {
        return $this->connection->query('use `'.$database.'`');
    }

    // Выполнения

    public function exec($sql)
    {
        $res = $this->connection->exec($sql);
        if (!$res) {
            $this->onError();
        }
        return $res;
    }

    public function query($sql)
    {
        $res = $this->connection->query($sql);
        if (!$res) {
            $this->onError();
        }
        return $res;
    }

    public function execPrepared($sql, $data)
    {
        $sth = $this->connection->prepare($sql);
        if (!$sth) {
            $this->onError();
            return false;
        }
        if (!$res = $sth->execute($data)) {
            $this->onError();
            return false;
        }
        return $res;
    }



    // Выборка данных

    public function fetchAll($sql)
    {
        $st = $this->connection->query($sql, PDO::FETCH_ASSOC);
        if (!$st) {
            $this->onError();
            return array();
        }
        return $st->fetchAll($mode);
    }

    public function fetchOne($sql)
    {
        $st = $this->connection->query($sql, PDO::FETCH_ASSOC);
        if (!$st) {
            $this->onError();
            return array();
        }
        return $st->fetch($mode);
    }

    public function fetchColumn($sql, $index = 0)
    {
        $st = $this->connection->query($sql, PDO::FETCH_COLUMN, $index);
        if (!$st) {
            $this->onError();
            return array();
        }
        return $st->fetchAll($mode);
    }

    /*
    $sql = 'SELECT * FROM clients WHERE name = ?';
    $red = $db->fetchPrepared($sql, array('Andrew'));
    */
    public function fetchPrepared($sql, $data)
    {
        $res = $this->execPrepared($sql, $data);
        if ($res) {
            return $res->fetchAll($sql, $data);
        }
        return array();
    }



    // Вставка

    public function insert($table, $data)
    {
        if (!$data) {
            $this->error = 'Пустые данные для insert';
            return false;
        }
        $q = array();
        for ($i = 0; $i < count($data); $i ++) {
            $q []= '?';
        }
        $sql = 'INSERT INTO `'.$table.'` (`'.implode('`, `', array_keys($data)).'`) VALUES ('.implode(', ', $q).')';
        return $this->execPrepared($sql, array_values($data));
    }

    // Удаление

    public function deleteById($table, $id)
    {
        $id = (int)$id;
        $this->exec('delete from '.$table.' where id='.$id);
    }


    // Обновление

    public function update($table, $data, $where = '')
    {
        if (!$data) {
            $this->error = 'Пустые данные для update';
            return false;
        }
        $q = array();
        foreach ($data as $field => $v) {
            $q []= '`'.$field.'`=?';
        }
        $sql = 'UPDATE `'.$table.'` SET '.implode(', ', $q);
        if ($where) {
            $sql .= ' WHERE '.$where;
        }
        return $this->execPrepared($sql, array_values($data));
    }
}
class Bitrix
{
    public static $menu = array(
        'Копирование шаблона' => '',
        'Авторизация' => 'auth',
        'Кеш' => 'cashclear'
    );

    public static $output;

    public static function has()
    {
        return file_exists(getRoot().'/bitrix');
    }

    public static function onStart()
    {
        if (GET('action') != 'bitrix') {
            return ;
        }
        try {
            self::prolog();
        } catch (\Exception $e) {
            error($e->getMessage());
        }
    }

    public static function prolog()
    {
        $prolog = $_SERVER['DOCUMENT_ROOT'] . "/bitrix/modules/main/include/prolog_before.php";
        if (!file_exists($prolog)) {
            throw new Exception('Файл пролога не найден ('.$prolog.')');
        }
        require_once $prolog;
    }

    public function index()
    {
        self::componentCopy();
    }

    public function auth()
    {
        global $USER;
        $res = $USER->Authorize($_GET['id']);
        if ($res) {
            header('Location: /bitrix/admin/');
        } else {
            echo 'Ошибка авторизации';
        }
    }

    public function cashclear()
    {

        $obCache = new CPHPCache();
        $obCache->CleanDir();

        $static_html_cache = \Bitrix\Main\Data\StaticHtmlCache::getInstance();
        $static_html_cache->deleteAll();

        BXClearCache(true, '/');

        $dirs = array('cache', 'managed_cache/MYSQL', 'html_pages/'.$_SERVER['HTTP_HOST']);
        foreach ($dirs as $dir) {
            $root = $_SERVER['DOCUMENT_ROOT'];
            if (!$dir || strpos($dir, '.')) {
                continue;
            }
            $dir = $root.'/bitrix/'.$dir;
            if (!file_exists($dir)) {
                error('Папка не найдена "'.$dir.'"');
                continue;
            }
            list($dels, $size) = self::cashclearDropfiles($dir);
            $stat = ' ('.str_replace($root, '', $dir).'): <b>'.$dels.'</b> size: '.formatSize($size);
            if ($_GET['stat']) {
                echo '<br />Статистика кеша в папке'.$stat;
            } else {
                echo '<br />Deleted files'.$stat;
            }
        }
        if ($_GET['stat']) {
            echo '<hr /> <a href="?action=bitrix&page=cashclear" class="btn btn-primary">Выполнить очистку</a>';
        }
    }

    public static function cashclearDropfiles($dir, $level = 0)
    {
        $a = scandir($dir);
        $dels = 0;
        $size = 0;
        foreach ($a as $k => $v) {
            if ($v == '.' || $v == '..') {
                continue;
            }
            $path = $dir.'/'.$v;
            if (is_dir($path)) {
                list($d, $s) = self::cashclearDropfiles($path, $level+1);
                $dels += $d;
                $size += $s;
            } else {
                $size += filesize($path);
                $dels ++;
                if (!$_GET['stat']) {
                    unlink($path);
                }
            }
        }
        if ($level != 0) {
            if (!$_GET['stat']) {
                rmdir($dir);
            }
        }
        return array($dels, $size);
    }

    public static function componentCopy()
    {

        $c = $_POST['component'];
        if (strpos($c, '/') === false) {
            $c = 'bitrix/'.$c;
        }

        // Получение списка шаблонов компонента
        if ($_POST['bxaction'] == 'ct-list') {
            $a = self::scandirx(getRoot().'/bitrix/components/'.$c.'/templates');
            foreach ($a as $k => $v) {
                echo '<option>'.$v.'</option>';
            }
            exit;
        }

        // Получение списка шаблонов компонента
        if ($_POST['bxaction'] == 'ct-files') {
            $from = 'bitrix/components/'.$c.'/templates/'.$_POST['component-template'].'/';
            $a = self::scandirx($from);
            foreach ($a as $k => $v) {
                $path = $from.'/'.$v;
                if (is_dir($path)) {
                    $v = '<span style="font-weight:bold;">'.$v.'</span>';
                }
                echo '<div>'.$v.'</div>';
            }
            exit;
        }

        // Непосредственно копирование компонента
        if ($_POST['component']) {
            $from = getRoot().'/bitrix/components/'.$c.'/templates/'.$_POST['component-template'];
            $to = getRoot().'/local/templates/'.$_POST['site-template'].'/components/'.$c;
            if (!file_exists($from)) {
                throw new Exception('Нет папки from ('.$from.')');
            }
            if (!file_exists($to)) {
                if (class_exists('Bitrix\Main\IO\Directory')) {
                    Bitrix\Main\IO\Directory::createDirectory($to);
                } else {
                    if (!mkdir($to)) {
                        throw new Exception('Ошибка создания папки "'.$to.'"');
                    };
                }
            }
            $to .= '/'.$_POST['component-template'];
            if (file_exists($to)) {
                error('Папка уже существует "'.$to.'"');
            } else {
                mkdir($to);
                copyFolder($from, $to, explode(',', $_POST['skip']));
                msg('Скопировал "'.$to);
            }
        }
        // $c = self::components(); echo '<pre>'.print_r($c, 1).'</pre>'; return $c;

        self::componentCopyForm();
    }

    public static function componentCopyForm()
    {

        ?>

        <form method="post">
            <div class="row gy-2 gx-3 align-items-center mt-2">
                <div class="col-auto">
                <?php
                echo selector(
                    self::components(),
                    'Выберите компонент',
                    ' required name="component" class="form-select input-sm"',
                    $_POST['component']
                );
                ?>
                </div>
                <div class="col-auto">
                <select required name="component-template" class="form-select input-sm">
                    <option value="">Выберите шаблон компонента</option>
                </select>
                </div>
                <div class="col-auto">
                <?php
                echo selector(
                    self::templates(),
                    'Выберите шаблон сайта',
                    ' required name="site-template" class="form-select input-sm"',
                    $_POST['site-template'] ?: (defined('SITE_TEMPLATE_ID') ? SITE_TEMPLATE_ID : '')
                );
                ?>
                </div>
                <div class="col-auto">
                <input type="text" name="skip" placeholder="Пропустить папки" value="" class="form-control input-sm">
                </div>
                <div class="col-auto">
                <input type="submit" value="Копировать" class="btn btn-primary" />
                </div>
            </div>

            <div id="ctpl-list" class="mt-2"></div>
        </form>
        <hr />
        <script type="text/javascript">
        function loadExtraData()
        {
            var c = $('select[name="component"]').val();
            if (!c) {
                return ;
            }
            $.post('?action=bitrix', 'bxaction=ct-list&component='+c, function(data) {
                $('select[name="component-template"]').html(data)
                var ctpl = $('select[name="component-template"]').val();
                $.post('?action=bitrix', 'bxaction=ct-files&component='+c+'&component-template='+ctpl, function(data) {
                    $('#ctpl-list').show().html(data)
                });
            });
        }
        $(document).ready(function(){
            $('select[name="component"]').change(loadExtraData)
            loadExtraData()
        });
        </script>

        <?php
    }

    /**
     * Массив шаблонов сайта
     */
    public static function templates()
    {
        $dirs = [getRoot().'/local/templates', getRoot().'/bitrix/templates'];
        if (defined('DIR_TEMPLATES')) {
            $dirs []= getRoot().'/'.DIR_TEMPLATES;
        }
        return self::scandirx($dirs);
    }

    /**
     * Массив названий субпапок из массива папок
     */
    public static function scandirx($dirs)
    {
        if (!is_array($dirs)) {
            $dirs = array($dirs);
        }
        $a = $dirs;
        while ($dir = array_shift($a)) {
            if (file_exists($dir)) {
                break;
            }
        }
        if (!is_dir($dir)) {
            return [];
        }
        if (!file_exists($dir)) {
            echo 'Папка не найдена "'.implode(', ', $dirs).'"';
            return array();
        }
        $components = array();
        $a = scandir($dir);
        foreach ($a as $k => $v) {
            if ($v == '.' || $v == '..') {
                continue;
            }
            $components []= $v;
        }
        return $components;
    }

    /**
     * Массив компонентов для формы копирования
     */
    public static function components()
    {

        $dir = getRoot().'/bitrix/components';

        $list = array();

        $popular = array(
            'breadcrumb',
            'catalog',
            'catalog.element',
            'catalog.section',
            'catalog.section.list',
            'catalog.smart.filter',
            'catalog.top',
            'form.result.new',
            'menu',
            'news',
            'news.line',
            'sale.basket.basket',
            'sale.basket.basket.line',
            'sale.order.ajax',
            'search.title',
            'search.page',
            'system.pagenavigation'
        );
        foreach ($popular as $k => $v) {
            if (!file_exists($dir.'/bitrix/'.$v)) {
                continue;
            }
            $list []= $v;
        }
        array_push($list, '-----------');


        $groups = self::scandirx($dir);
        foreach ($groups as $group) {
            $folder = $dir.'/'.$group;
            $components = self::scandirx($folder);
            foreach ($components as $component) {
                $list []= $group.'/'.$component;
            }
        }

        return $list;
    }
}
class Utils
{
    public static $menu = array(
        //'Копирование шаблона' => '',
        //'Авторизация' => 'auth',
        //'Кеш' => 'cashclear'
    );

    public static $output;

    public static function has()
    {
        return true;
    }

    public static function onStart()
    {
        if (GET('action') != 'utils') {
            return ;
        }
        /*try {
            self::prolog();
        } catch (\Exception $e) {
            error($e->getMessage());
        }*/
    }

    public function index()
    {
        if ($_POST['extract-links']) {
            preg_match_all('~href\s*=\s*["\'](http://[^"\']+)["\']~i', $_POST['content'], $a);
            echo implode('<br />', $a[1]);
        }

        if ($_POST['generate']) {
            $this->generate();
        }

        ?>
        <h2>Генерация тегов</h2>
        <style type="text/css">
            .big-inputs input {min-width: 100px;}
        </style>
        <form method="post">
            <input type="hidden" name="generate" value="1">
            <textarea name="content" id="generateTxt" class="form-control mb-3" style="height: 200px"></textarea>
            <div class="big-inputs">
            <input type="submit" name="ul-gen" value="ul" class="btn btn-success" />
            <input type="submit" name="ol-gen" value="ol" class="btn btn-success" />
            <input type="submit" name="p-gen" value="p" class="btn btn-warning"  />
            <input type="submit" name="ph2-gen" value="h2+p" class="btn btn-warning" />
            <input type="submit" name="td" value="td" class="btn btn-danger"/>
            <input type="submit" name="spec" value="spec" class="btn btn-primary" />
            <input type="submit" name="full" value="full" class="btn btn-primary" />
            <input type="submit" name="table" value="table" class="btn btn-primary" />
            <input type="submit" name="extract-links" value="extract-links" class="btn btn-primary" title="Извлечь ссылки из текста" />
            </div>
        </form>
        <?php
    }

    protected static function splitAndClear($rx, $content, $opts=[])
    {
        $lines = preg_split('~'.$rx.'~u', $content);
        foreach ($lines as $k => $v) {
            if (empty($v)) {
                unset($lines[$k]);
            }
        }
        $lines = array_map('trim', $lines);
        if ($opts['s']) {
            foreach ($lines as $k => $v) {
                $lines [$k]= preg_replace('~\s+~i', ' ', $v);
            }
        }
        return $lines;
    }

    protected static function tagImplode($lines, $tag, $tab='')
    {
        return "$tab<$tag>".implode("</$tag>\n$tab<$tag>", $lines)."</$tag>";
    }

    protected function generate()
    {

        $content = trim($_POST['content']);

        if ($_POST['table']) {
            $content = preg_replace('~\r\n\t~i', "\t", $content);
            echo '<pre>'.$content.'</pre>';
        }

        $rx = '[\r\n]+';
        if ($_POST['td']) {
            $rx = '[\t]+';
        }
        $lines = self::splitAndClear($rx, $content);

        if ($_POST['table']) {

            $total = 0;
            foreach ($lines as $key => $vals) {
                $vals = explode("\t", $vals);
                $cnt = count($vals);
                if ($cnt > $total) {
                    $total = $cnt;
                }
            }




            $th = 1;
            $result = '<table><thead>'."\n";
            $total = 0;
            foreach ($lines as $key => $vals) {
                $vals = explode("\t", $vals);
                $cnt = count($vals);
                $colspan = 0;
                    if ($total != $cnt) {
                        $colspan = 1 + $total - $cnt;
                    }
                $result .=  '<tr>';
                foreach ($vals as $k => $v) {
                    $tag = 'td';

                    if ($th == 2) {
                        if (!$k) {
                            $tag = 'th';
                        }
                    } else {
                    if (!$key) {
                        $tag = 'th';
                    }
                    }
                    $add = '';
                    if ($colspan && $k + 1 == $cnt) {
                        $add = ' colspan="'.$colspan.'"';
                    }
                    $result .=  '<'.$tag.$add.'>'.$v.'</'.$tag.'>';
                }
                $result .=  '</tr>'."\n";
                if (!$key) {
                    $result .=  '</thead><tbody>'."\n";
                }
            }
            $result .= '</tbody></table>';

            echo '<style type="text/css">
            table {empty-cells:show; border-collapse:collapse; font-size:12px; font-family:Arial;}
            table td {border:1px solid #eee; padding: 3px; vertical-align: top;}
            table tr:nth-child(odd) {background-color:#eee; }
            </style>';
            echo $result;

            //echo '<pre>'.$content.'</pre>';
            //exit;
        }


        if ($_POST['ol-gen'] || $_POST['ul-gen']) {
            $point = mb_substr($content, 0, 1);
            if (in_array($point, array('•', '-', '‒'))) {
                $lines = self::splitAndClear($point, $content, ['s' => 1]);
            } else {
                // Если с большой буквы, то сплитим по переносам, после которых идет большая буква
                $pointLower = mb_strtolower($point);
                if ($pointLower != $point) {
                    $lines = self::splitAndClear('[\r\n]+(?=[А-Я])', $content, ['s' => 1]);
                }
            }

            foreach ($lines as $k => &$v) {
                $v = preg_replace('~^[-\.\s•]+~i', '', $v);
                if ($_POST['ol-gen']) {
                    $v = preg_replace('~^[\d\.\s]+~i', '', $v);
                }
            }

            $tag = $_POST['ol-gen'] ? 'ol' : 'ul';
            $result = '<'.$tag.'>'."\n".self::tagImplode($lines, 'li', '    ')."\n".'</'.$tag.'>';
        }
        if ($_POST['ph2-gen']) {
            $h2 = array_shift($lines);
            $result = '<h2>'.$h2.'</h2>'."\n".self::tagImplode($lines, 'p');
        }
        if ($_POST['p-gen']) {
            $result = self::tagImplode($lines, 'p');
        }
        if ($_POST['td']) {
            $result = self::tagImplode($lines, 'td');
        }

        if ($_POST['spec']) {
            $result = "\n".'        <ul class="in">'."\n";
            foreach ($lines as $k => $v) {
                if (!$v) {
                    continue;
                }
                list($a, $b) = explode("    ", $v);
                if ($b == 'бесплатно') {
                    $b = '<span class="free">'.$b.'</span>';
                } else {
                    $b = '<span>'.$b.'</span>';
                }
                $result .= '            <li><span>'.$a.'</span>'.$b.'</li>'."\n";
            }
            $result .= '        </ul>'."\n";
        }

        if ($_POST['full']) {
            function callAfterUl(&$result, &$lis)
            {
                if (count($lis)) {
                    $result []= '<ul>';
                    foreach ($lis as $v) {
                        $result []= $v;
                    }
                    $result []= '</ul>';
                    $lis = [];
                }
            }
            function callAfterOl(&$result, &$ols)
            {
                if (count($ols)) {
                    $result []= '<ol>';
                    foreach ($ols as $v) {
                        $result []= $v;
                    }
                    $result []= '</ol>';
                    $ols = [];
                }
            }
            //echo '<pre>'; print_r($lines); echo '</pre>';
            $result = [];
            $lis = [];
            $ols = [];
            foreach ($lines as $line) {
                if (strpos($line, '•') === 0) {
                    callAfterOl($result, $ols);
                    $line = preg_replace('~•\s*~i', '', $line);
                    $lis []= '<li>'.$line.'</li>';
                } elseif (strpos($line, '') === 0) {
                    callAfterUl($result, $ols);
                    $line = preg_replace('~\s*~i', '', $line);
                    $lis []= '<li>'.$line.'</li>';
                } else {
                    callAfterUl($result, $lis);
                    if (preg_match('~^\d+~i', $line)) {
                        $line = preg_replace('~\d+\.?\s*~i', '', $line);
                        $ols []= '<li>'.$line.'</li>';
                    } else {
                        callAfterOl($result, $ols);
                        if (strpos($line, '<') === 0) {
                            $result []= $line;
                        } else {
                            $result []= '<p>'.$line.'</p>';
                        }
                    }
                }
            }
            callAfterUl($result, $lis);
            callAfterOl($result, $ols);
            $result = implode("\n", $result);
        }

        ?>
        <textarea oncopy="setTimeout(function() {document.getElementById('generateTxt').focus(); document.getElementById('results').remove();}, 100); " class="form-control small" style="height:200px;" id="results"><?=htmlspecialchars($result)?></textarea>
        <script type="text/javascript">
        document.getElementById('results').select()
        </script>
        <?php
    }
}
class Zip
{

    public static function zipError($code)
    {
        switch ($code) {
            case 0:
                return 'No error';
            case 1:
                return 'Multi-disk zip archives not supported';
            case 2:
                return 'Renaming temporary file failed';
            case 3:
                return 'Closing zip archive failed';
            case 4:
                return 'Seek error';
            case 5:
                return 'Read error';
            case 6:
                return 'Write error';
            case 7:
                return 'CRC error';
            case 8:
                return 'Containing zip archive was closed';
            case 9:
                return 'No such file';
            case 10:
                return 'File already exists';
            case 11:
                return 'Can\'t open file';
            case 12:
                return 'Failure to create temporary file';
            case 13:
                return 'Zlib error';
            case 14:
                return 'Malloc failure';
            case 15:
                return 'Entry has been changed';
            case 16:
                return 'Compression method not supported';
            case 17:
                return 'Premature EOF';
            case 18:
                return 'Invalid argument';
            case 19:
                return 'Not a zip archive';
            case 20:
                return 'Internal error';
            case 21:
                return 'Zip archive inconsistent';
            case 22:
                return 'Can\'t remove file';
            case 23:
                return 'Entry has been deleted';
            default:
                return 'An unknown error has occurred('.intval($code).')';
        }
    }

    public static function createFile()
    {
        $zip = new ZipArchive();
        $zip->open('new.zip', ZipArchive::CREATE);
        $zip->addFile('somefile.sql');
        $zip->close();
    }

    public static function addFiles($zip, $files, $baseDir = '', $excludePathArray = array())
    {
        if (!is_array($files)) {
            $files = array($files);
        }
        $added = $errors = 0;
        foreach ($files as $path) {
            if ($path == '.' || $path == '..') {
                continue;
            }
            if ($excludePathArray && in_array($path, $excludePathArray)) {
                continue;
            }
            if ($_POST['archive-log']) {
                $a = fopen(dirname(__FILE__).'/exp-log.txt', 'a+');
                fwrite($a, "\n".$path);
                fclose($a);
            }
            /*if ($fromFolder) {
                $path = $fromFolder.'/'.$v;
            }*/
            if (is_dir($path)) {
                //echo '<br />'.$path.' ('.$fromFolder.')';
                $zip->addEmptyDir($path);
                $subs = scandirex($path, 'path');
                //echo '<pre>'; print_r($subs); echo '</pre>'; exit;
                if (count($subs)) {
                    list($a, $e) = Zip::addFiles($zip, $subs, $baseDir, $excludePathArray);
                    $added += $a;
                    $errors += $e;
                }
            } else {
                if (!is_readable($path)) {
                    error('File "'.$path.'" не читаем, пропущен');
                    continue;
                }
                //echo '<br />'.$path.' ('.$fromFolder.')';
                if (!$zip->addFile($path)) {
                    $errors ++;
                } else {
                    $added ++;
                }
            }
        }
        return array($added, $errors);
    }

    public static function addFilesDir($zip, $dir, $baseDir = '', &$added = 0, &$errors = 0)
    {
        if (is_dir($dir)) {
            $a = scandir($dir);
        } else {
            $a = array($dir);
        }
        foreach ($a as $k => $v) {
            if ($v == '.' || $v == '..') {
                continue;
            }
            $path = $dir.'/'.$v;
            if (is_dir($path)) {
                Zip::addFilesDir($zip, $path, $baseDir, $added, $errors);
            } else {
                if ($baseDir) {
                    $pathTo = str_replace($baseDir, '', $path);
                }
                if (!is_readable($path)) {
                    error('File "'.$path.'" не читаем, пропущен');
                    continue;
                }
                if (!$zip->addFile($path, $pathTo)) {
                    $errors ++;
                } else {
                    $added ++;
                }
            }
        }
    }

    public static function create($file, $saveTo, $content = '')
    {
        if (file_exists($saveTo)) {
            unlink($saveTo);
        }
        $zip = new ZipArchive();
        $code = $zip->open($saveTo, ZipArchive::CREATE);
        if ($code !== true) {
            addlog("Невозможно открыть '$saveTo' ошибка - ".$this->zipError($code)."\n");
            exit;
        }
        $zip->addFromString(basename($file), $content);
        $zip->close();
    }

    public static function unpack($v, $folder, &$error = '')
    {
        addLog($v.'... ');
        $removeArchivedFiles = array();

        $zip = new ZipArchive;
        if ($zip->open($v) === true) {
            if ($zip->extractTo($folder)) {
                for ($i = 0; $i < $zip->numFiles; $i ++) {
                    $stat = $zip->statIndex($i);
                    $removeArchivedFiles []= $folder.'/'.$stat['name'];
                }
            } else {
                addLog($error = 'ошибка распаковки');
            }
            $zip->close();
        } else {
            addLog($error = 'ошибка открытия архива');
        }
        return $removeArchivedFiles;
    }

    public static function readFiles($file)
    {
        $files = array();
        $zip = new ZipArchive();
        $code = $zip->open($file, ZipArchive::CREATE);
        for ($i = 0; $i < $zip->numFiles; $i ++) {
            $stat = $zip->statIndex($i);
            $files []= $stat['name'];
        }
        return $files;
    }
}

function archiveFolders($includeArray, $excludeArray)
{
    $files = array();
    $a = scandir('.');
    foreach ($a as $k => $v) {
        if (count($includeArray)) {
            if (!in_array($v, $includeArray)) {
                continue;
            }
        } else {
            if ($v == '.' || $v == '..' || ($excludeArray && in_array($v, $excludeArray))) {
                continue;
            }
        }
        $files []= $v;
    }
    return $files;
}

function archiveFile($folder, $delete = true)
{
    $file = $folder.'/all.zip';
    if (file_exists($file) && $delete) {
        unlink($file);
    }
    return $file;
}

function createArchive($opts)
{

    extract($opts);

    if ($_POST['filesList']) {
        $list = array_map('trim', explode("\n", $_POST['filesList']));
        $file = TMP_DIR.'/fileslist.zip';
        if (file_exists($file)) {
            unlink($file);
        }
        $zip = new ZipArchive();
        $zip->open($file, ZipArchive::CREATE);
        foreach ($list as $k => $v) {
            $zip->addFile($v);
        }
        $zip->close();
        echo '<p><a href="'.$file.'">Скачать архив</a></p>';
        exit;
    }

    $includeArray   = $include ? explode(',', $include) : array();
    $extensionArray = explode(',', $extension);
    $excludeArray   = explode(',', $exclude);
    $excludePathArray   = explode(',', $exclude_path);

    if ($_POST['chunked']) {
        echo 'Создание архива по частям..';
        $index = 0;
        $files = array();
        zipFiles('.', $files, $max, $count, $extensionArray, $excludeArray, $includeArray, $index, $level = 0);
        if ($files) {
            zipFilesAdd($files, $index);
        }
    } else {
        echo 'Создание одного архива...';

        $a = scandir('.');
        echo '  всего папок: '.(count($a)-2).' ';
        $files = archiveFolders($includeArray, $excludeArray);
        echo '<div>Беру: '.implode(', ', $files).'</div>';

        $file = archiveFile($opts['archiveFolder']);
        $zip = new ZipArchive();
        $code = $zip->open($file, ZipArchive::CREATE);
        if ($code !== true) {
            addlog("Невозможно открыть '$saveTo' ошибка - ".$this->zipError($code)."\n");
            exit;
        }

        list($added, $errors) = Zip::addFiles($zip, $files, '', $excludePathArray);
        echo '<div>Добавлено файлов: '.$added.'. Не удачно: '.$errors.'</div>';

        $zip->close();
    }
    echo '<div><b>Вроде бы всё</b></div> <div><a href="?fileDownload='.urlencode($file).'">Скачать файл</a></div>';
}


// Используется в общем создании архива
function zipFiles($cdir, &$files, $max, $count, $extension, $exclude, $include, &$index, $level = 0)
{
    $dirs = scandir($cdir);
    $break = false;
    foreach ($dirs as $k => $v) {
        if ($v == '.' || $v == '..') {
            continue;
        }
        if ($cdir != '.') {
            $dir = $cdir .'/'. $v;
        } else {
            if ($include && !in_array($v, $include)) {
                continue;
            }
            $dir = $v;
        }
        if (in_array($dir, $exclude)) {
            continue;
        }
        if ($index % $count == 0 && count($files) > 0) {
            zipFilesAdd($files, $index);
            $files = array();
        }
        if (is_dir($dir)) {
            $break = zipFiles($dir, $files, $max, $count, $extension, $exclude, $include, $index, $level + 1);
        } else {
            $index ++;
            $files []= $dir;
        }
    }
    return $break;
}

function zipFilesAdd($files, $index)
{
    $file = TMP_DIR."/files-$index.zip";
    echo '<hr />zipFilesAdd';
    echo '<br />'.$file;
    if (file_exists($file)) {
        echo ' ... file exists SKIP!';
    } else {
        $zip = new ZipArchive();
        $code = $zip->open($file, ZipArchive::CREATE);
        list($added, $errors) = Zip::addFiles($zip, $files);
        $zip->close();
    }
}
function rowCopy($fields, $table, $where)
{
    $f = array();
    foreach ($fields as $k => $v) {
        if (!strchr($v->Key, 'PRI')) {
            $f []= $v->Field;
        }
    }
    $fields = '`'.implode('`,`', $f).'`';
    $where = stripslashes(urldecode($where));
    $sql = "INSERT INTO $table ($fields) SELECT $fields FROM $table WHERE $where";
    $res = query($sql, $e);
    if ($res) {
        echo 'Ряд успешно скопирован';
    } else {
        echo 'Ошибка копирования '.$e;
    }
}

function getData($sql)
{
    global $mysqli;
    $result = query($sql);
    if (!$result) {
        return array();
    }
    $data = array();
    while ($row = $result->fetch_assoc()) {
        $data []= $row;
    }
    return $data;
}

function getOne($sql)
{
    global $mysqli;
    $result = query($sql);
    if (!$result) {
        return array();
    }
    $data = $result->fetch_assoc();
    return $data;
}

function query($sql, &$e = '')
{
    global $mysqli;
    if (!is_scalar($sql)) {
        echo 'Запрос не строка';
        return false;
    }
    $result = $mysqli->query($sql);
    $e = $mysqli->error;
    $error = $e != null && substr($e, 0, 15) != 'Duplicate entry' ? $e : '';
    // var_dump($sql); var_dump($error);
    $type = substr(strtolower(trim($sql)), 0, 6);
    if (!$result) {
        return $result;
    }
    if ($type == 'insert') {
        return $mysqli->insert_id;
    } elseif ($type == 'update' || $type == 'delete') {
        return $mysqli->affected_rows;
    } else {
        return $result;
    }
}

function mysqlUpdate($table, $data, $where = '', $fields = '', &$sql = '')
{
    global $mysqli;
    $values = array();
    $nulled = $data['nulled'];
    unset($data['nulled']);
    foreach ($data as $k => $v) {
        if ($fields && $fields[$k]->Null == 'YES' && ($v === '' || $v === null) && $nulled[$k] == 1) {
            $v = 'null';
        } elseif (!is_numeric($v)) {
            $v = '"'.$mysqli->real_escape_string($v).'"';
        }
        $values []= '`'.$k.'`='.$v;
    }
    $sql = ' UPDATE `'.$table.'` SET '.implode(', ', $values).' WHERE '.$where;
    return query($sql);
}

function mysqlInsert($table, $data, $m = 'INSERT', $fields = '', &$sql = '', &$e = '')
{
    global $mysqli;
    $values = array();
    foreach ($data as $k => $v) {
        if (is_array($v)) {
            unset($data[$k]);
            continue;
        }
        if ($fields && $fields[$k]->Null == 'YES' && ($v === '' || $v === null)) {
            $v = 'null';
        } elseif (!is_numeric($v)) {
            $v = '"'.$mysqli->real_escape_string($v).'"';
        }
        $values []= $v;
    }
    $sql = $m.' INTO `'.$table.'` (`'.implode('`, `', array_keys($data)).'`) VALUES ('.implode(',', $values).')';
    return query($sql, $e);
}

function getServerVersion()
{
    global $mysqli;
    if (!$mysqli) {
        return false;
    }
    $vs = $mysqli->server_version;
    return $vs;
}

function execSql($content, $type = '', $max_query = null, $exitOnError = false)
{
    global $mysqli;
    $errors = array();
    $content = str_replace("\r", '', $content);
    $array = explode(";\n", $content);
    $timeStart = time();
    $count = 0;
    foreach ($array as $k => $sql) {
        if (empty($sql)) {
            continue;
        }
        $count ++;
        $mysqli->query($sql);
        $e = $mysqli->error;
        if ($e) {
            $errors []= $e;
        }
        $time = time() - $timeStart;
        if ($time && $time % 5 === 0) {
            $s = ', ошибок нет';
            if ($errors) {
                $s = ', ошибок '.count($errors);
            }
            addLog('выполняется... запросов '.$count.$s);
            $timeStart = time();
        }
    }
    $c = count($array);
    //$mysqli->multi_query($content);
    return array($errors, $c, $mysqli->affected_rows);
}

function getFields($table, $onlyNames = false, $what = 'FIELDS')
{
    global $mysqli;
    if (empty($table)) {
        return array();
    }
    $a = array();
    $table = str_replace('`', '``', $table);
    $result = $mysqli->query('SHOW '.$what.' FROM `'.$table.'`');
    if (!$result) {
        return false;
    }
    while ($row = $result->fetch_object()) {
        $name = $what == 'FIELDS' ? $row->Field : $row->Key_name;
        if ($onlyNames) {
            $a []= $name;
        } else {
            $a [$name]= $row;
        }
    }
    return $a;
}

function getKeys($table, $onlyNames = false)
{
    return getFields($table, $onlyNames, 'KEYS');
}

function fieldKeys($table, $field)
{
    $fieldKeys = array();
    $keys = getKeys($table);
    foreach ($keys as $k => $v) {
        if ($v->Column_name == $field) {
            if ($v->Key_name == 'PRIMARY') {
                $fieldKeys []= 'primary';
            } elseif ($v->Non_unique) {
                $fieldKeys []= 'index';
            } else {
                $fieldKeys []= 'unique';
            }
        }
    }
    return $fieldKeys;
}

function getAllTables()
{
    global $mysqli;
    if (!$mysqli) {
        return array();
    }
    static $tables;
    if (!isset($tables)) {
        $result = $mysqli->query('SHOW TABLE STATUS');
        $tables = array();
        if ($result) {
            while ($row = $result->fetch_object()) {
                $tables [$row->Name]= $row;
            }
        }
    }
    return $tables;
}

function getTableExport($database, $t, $withData = false, $opts = '')
{
    $exp = new Exporter($database);
    $exp->setComments(0);
    $exp->setOptionsStruct($addIfNot = true, $addAuto = true, $addKav = 1);
    $exp->setOptionsData($full = 1, $one_query = 0, $ins_zadazd = 0, $ins_ignore = 0);
    $exp->setTable($t);
    $exp->exportStructure($addDelim = true, $addDrop = false);
    if ($withData) {
        $type = 'INSERT';
        if ($opts['METHOD'] == 'REPLACE') {
            $type = 'REPLACE';
        }
        $skipAi = false;
        if ($opts['AI']) {
            $skipAi = true;
        }
        $exp->exportData($type, '', $skipAi);
    }
    return $exp->data;
}
// Распечатка запроса в таблицу
function printSqlTable($sql, $data = null)
{
    global $mysqli;
    if ($data == null) {
        $result = query($sql, $e);
        if (!$result) {
            return $e;
        }
        $data = array();
        while ($row = $result->fetch_assoc()) {
            $data []= $row;
        }
    }
    if (count($data) == 0) {
        addLog('No data in  '.$sql);
        return ;
    }
    $content =  '<table class="optionstable">';
    $max = 250;
    foreach ($data as $k => $v) {
        if (!isset($headersPrinted)) {
            $headersPrinted = 1;
            $content .= '<tr>';
            foreach ($v as $k1 => $v1) {
                $content .= '<th>'.$k1.'</th>';
            }
            $content .= '</tr>';
        }
        $content .= '<tr>';
        foreach ($v as $k2 => $v2) {
            $v2 = strip_tags($v2);
            $v2 = htmlspecialchars($v2);
            if (mb_strlen($v2) > $max) {
                $more = '<span style="display:none;">' . mb_substr($v2, $max).'</span>';
                $v2 = mb_substr($v2, 0, $max) . ' <a href="#" class="nsh">еще</a>'.$more;
            }
            if (strpos($v2, 'http') === 0) {
                $v2 = '<a href="'.$v2.'" target="_blank">'.$v2.'</a>';
            }
            if ($k2 == 'id167000') {
                $v2 = '<a href="http://167000.ru/o/'.$v2.'" target="_blank">'.$v2.'</a>';
            }
            $content .= '<td>'.$v2.'</td>';
        }
        $content .= '</tr>';
    }
    $content .= '</table>';
    unset($headersPrinted);
    return $content;
}

// Генерирует where условие на поиск запроса по полям
function fieldsSearchWhere($fields, $filter, $onlyField = '')
{
    if (!$filter) {
        return ;
    }
    $isRus = preg_match('~[а-я]~i', $filter, $reg);
    $isNum = is_numeric($filter);
    $where = array();
    foreach ($fields as $k => $v) {
        if ($onlyField && $v->Field != $onlyField) {
            continue;
        }
        $isInt = strpos($v->Type, 'int') !== false;
        // Для поиска на русском языке, исключаем даты
        if ($isRus && (strpos($v->Type, 'date') !== false || $isInt)) {
              continue;
        }
        if ($isInt && $isNum) {
            $where []= '`'.$v->Field.'`="'.addslashes($filter).'"';
        } else {
            $where []= '`'.$v->Field.'` LIKE "%'.addslashes($filter).'%"';
        }
    }
    $where = ' WHERE ('.implode(' OR ', $where).')';
    return $where;
}



// Операции с полями block

function ch($key, $array = -1)
{
    if ($array === -1) {
        $array = $_POST;
    }
    return $array && array_key_exists($key, $array) && $array[$key] ? ' checked' : '';
}

function fieldForm($defaults = '', $fields = '', $key = '')
{
    if (!$defaults) {
        $defaults = array();
    }
    $columnTypes = array(
        'Тип', 'VARCHAR', 'TINYINT', 'TEXT', 'DATE',
        'SMALLINT', 'MEDIUMINT', 'INT', 'BIGINT',
        'FLOAT', 'DOUBLE', 'DECIMAL',
        'DATETIME', 'TIMESTAMP', 'TIME', 'YEAR',
        'CHAR', 'TINYBLOB', 'TINYTEXT', 'BLOB', 'MEDIUMBLOB', 'MEDIUMTEXT', 'LONGBLOB', 'LONGTEXT',
        'ENUM', 'SET', 'BOOLEAN', 'SERIAL'
    );
    $a = '';
    if ($_GET['tmode'] == 'createTable') {
        $a = '['.$key.']';
    }
    ?>


  <div class="row mb-2">
    <div class="col-sm-3">
      <input type="text" name="name<?=$a?>" placeholder="Поле" value="<?=@$defaults['name']?>" class="form-control form-control-sm" />
    </div>
    <div class="col-sm-3">
      <select name="type<?=$a?>" class="form-select form-select-sm">
        <?php
        foreach ($columnTypes as $k => $v) {
            $add = '';
            if ($defaults['type'] == $v) {
                $add = ' selected';
            }
            if ($k == 0) {
                $add .= ' value=""';
            }
            echo '<option'.$add.'>'.$v.'</option>';
        }
        ?></select>
    </div>
    <div class="col-sm-3">
      <input type="text" name="length<?=$a?>" placeholder="Длина / значения" value="<?=@$defaults['length']?>"
        class="form-control form-control-sm" />
    </div>
    <div class="col-sm-3">
      <input type="text" name="default<?=$a?>" placeholder="По умолчанию" value="<?=@$defaults['default']?>"
        class="form-control form-control-sm" />
    </div>
  </div>


  <div class="row mb-2">
    <div class="col-sm-12 small" style="text-align: right; ">
    <?php
    if ($_GET['tmode'] == 'createTable') {
        $chbx = array(
            'null' => 'NULL',
            'ai' => 'autoincrement',
            'pk' => 'pk',
            'unique' => 'unique',
            'index' => 'index'
        );
    } else {
        $chbx = array(
            'null' => 'NULL',
            'ai' => 'autoincrement'
        );
    }
    foreach ($chbx as $k => $v) {
        ?>
        <input type="hidden" name="<?=$k.$a?>" value="">
        <div class="form-check form-check-inline">
              <label class="form-check-label"><input class="form-check-input" type="checkbox" <?=ch($k, $defaults)?> name="<?=$k.$a?>" value="1">
              <?=$v?>
            </label>
        </div>
        <?php
    }
    ?>
    </div>
  </div>

    <?php
    if ($_GET['table']) {
        ?>

  <div class="row mb-2">
    <label class="col-sm-2 col-form-label">After</label>
    <div class="col-sm-10">
      <select class="form-select form-select-sm" name="after<?=$a?>">
                <option value=""></option>
        <?php
        foreach ($fields as $v) {
            $add = '';
            if ($defaults['after'] == $v->Field) {
                $add = ' selected';
            }
            echo '<option'.$add.'>'.$v->Field.'</option>';
        }
        ?>
            </select>
    </div>
  </div>

        <?php
    }
}

function getFieldDefinition($type = null, $null = null, $default = null, $extra = null, $length = null)
{
    if (is_object($type)) {
        foreach ($type as $param => $value) {
            $param = strtolower($param);
            $$param = $value;
        }
        if (stristr($type, 'UNSIGNED')) {
            $extra .= ' UNSIGNED';
            $type   = str_ireplace('UNSIGNED', '', $type);
        }
        if (stristr($type, 'ZEROFILL')) {
            $extra .= ' ZEROFILL';
            $type = str_ireplace('ZEROFILL', '', $type);
        }
        if (preg_match('~\((.*)\)~U', $type, $length)) {
            $length = $length[1];
            $type = trim(str_replace('('.$length.')', '', $type));
        }
    }
    $type = strtoupper($type);
    // особый тип, без доп. параметров
    if ($type == 'SERIAL') {
        return 'SERIAL';
    }
    if ($type == 'VARCHAR') {
        if (!is_numeric($length) || $length > 255 || $length < 1) {
            $length = 255;
        }
        $type .= "($length)";
    } elseif ($type == 'SET' || $type == 'ENUM') {
        if (empty($length)) {
            return false;
        }
        $type .= "($length)";
    } elseif ($type == 'FLOAT' || $type == 'DOUBLE') {
        if (empty($length)) {
            return false;
        } else {
            $length = str_replace('.', ',', $length);
        }
        $type .= "($length)";
    } elseif (is_numeric($length) && !stristr($type, 'text')) {
        $type .= "($length)";
    }
    $field_info  = $type;
    if (stristr($extra, 'UNSIGNED')) {
        // это алиас
        if ($type != 'BOOLEAN') {
            $field_info .= ' UNSIGNED';
        }
        $extra = str_replace('UNSIGNED', '', $extra); // UNSIGNED - после типа поля
    }
    if (stristr($extra, 'ZEROFILL')) {
        $field_info .= ' ZEROFILL';
        $extra = str_replace('ZEROFILL', '', $extra);
    }
    if ($null != 'YES') {
        $field_info .=  ' NOT NULL';
    }
    if (trim($extra) != null) {
        $field_info .= ' '.$extra;
    }
    if ($default != null) {
        if (is_numeric($default)) {
            $field_info .=  ' DEFAULT '.intval($default);
        } else {
            $field_info .=  ' DEFAULT "'.$default.'"';
        }
    }
    //$field_info .= ' DEFAULT NULL';
    $field_info = str_ireplace('auto_increment', 'AUTO_INCREMENT', $field_info);
    return $field_info;
}

function getFieldDefinitionByData($data)
{
    $extra = '';
    if ($data['ai']) {
        $extra = 'AUTO_INCREMENT';
    }
    $def = getFieldDefinition($data['type'], $data['null'] ? 'YES' : 'NO', $data['default'], $extra, $data['length']);
    return ' `'.$data['name'].'` '.$def;
}

function processEdit($fields)
{
    $data = array();
    if (GET('where')) {
        $data = getOne('SELECT * FROM '.$_GET['table'].' WHERE '.GET('where').' LIMIT 1');
    }

    $isCopy = $_GET['where'] && $_GET['tmode'] == 'add';

    if (count($_POST)) {
        $isSave = $_POST['action-submit'] == 'Сохранить';
        unset($_POST['action-submit']);
        if (GET('where') && !$isCopy) {
            $res = mysqlUpdate($_GET['table'], $_POST, GET('where'), $fields, $sql, $e);
        } else {
            $res = mysqlInsert($_GET['table'], $_POST, 'INSERT', $fields, $sql, $e);
        }
        if (is_numeric($res)) {
            msg('Обновлено! <div style="font-size:10px; color:#aaa">'.htmlspecialchars($sql).'</div>', $error = '');
            if ($isSave) {
                redirect(EXP.'?action=tables&table='.GET('table'), 1);
                return ;
            }
        } else {
            $msg = 'Ошибка <div style="font-size:10px; color:#aaa">'.htmlspecialchars($sql).'</div>';
            msg($msg, $mysqli->error .'<br />'.$e);
        }
    }


    $formFields = '';
    foreach ($fields as $v) {
        $isLong = (isset($data[$v->Field]) && mb_strlen($data[$v->Field]) > 100);
        $typeStr = '';
        if (strchr($v->Type, 'int') !== false) {
            $typeStr = 'integer';
        } elseif (strchr($v->Type, 'text') !== false || $isLong) {
            $typeStr = 'text';
        } elseif (strchr($v->Type, 'varchar') !== false) {
            $typeStr = '';
        }

        if ($v->Null != 'YES') {
            $title = '<b style="color:red">'.$v->Field.':</b>';
        } else {
            $title = '<b>'.$v->Field.':</b>';
        }
        $dataField = GET('where') && ($v->Key != 'PRI' || !$isCopy) ? $data : '';
        $html = getField($v->Field, $title, $typeStr, $v->Null != 'YES', $dataField);

        // Что за бред?
        ob_start();
        $Default = ob_get_contents();
        ob_end_clean();

        $null = ($v->Null == 'YES' ? 'Null' : 'Required');
        $key = ($v->Key != '' ? $v->Key.' Key' : '');
        $t = trim($v->Type.' '.$null.' '.$key.' '.$v->Extra.' '.($Default ? 'Default='.$Default : ''));

        $nulled = '';
        if ($data[$v->Field] === null) {
            $nulled = ' checked';
        }

        $formFields .= '
        <div class="row mb-2">
        <div class="col-sm-2">
            '.$title.'
        </div>
        <div class="col-sm-9" title="'.$t.'">
            '.$html.'
        </div>
        <div class="col-sm-1">
            <label><input type="checkbox"'.$nulled.' name="nulled['.$v->Field.']" value="1"> null</label>
        </div>
        </div>';
    }
    ?>
    <form method="post" class="myform edit-form" id="edit-fields-form">
        <?php echo $formFields?>
        <br /><br /><br /><br /><br />
        <div class="fixed-bottom-block">
            <input type="submit" class="btn btn-primary btn-lg" name="action-submit" value="Сохранить" />
            <input type="submit" class="btn btn-primary btn-lg" name="action-submit" value="Применить" />
        </div>
    </form>
    <?php
}

// Возвращает наиболее подходящий код инпут-формы для редактирования параметра
function getField($name, $title, $type, $req, $values)
{
    $defaultValue = isset($_POST[$name]) ? $_POST[$name] : (isset($values[$name]) ? $values[$name] : '');
    $defaultValue = htmlspecialchars($defaultValue);
    $add = 'id="f-'.$name.'"';

    if ($type == 'select') {
        $a = explode('|', $values);
        $html = '';
        foreach ($a as $key => $val) {
            if ($key == count($a)-1) {
                $add .= ' checked="checked"';
            }
            $html .= '<label><input type="radio" name="'.$name.'" value="'.$val.'"'. $add.'> '.$val.'</label>';
        }
    } elseif ($type == 'boolean') {
        if (intval($defaultValue) != 0 /*&& (!$isAdd || $param['default_value'] != 1)*/) {
            $add .= ' checked="checked"';
        }
        $html = '<div class="text"><input type="checkbox" name="'.$name.'" value="1"'.$add.' /></div>';
    } elseif ($type == 'integer') {
        $html = '<input type="number" name="'.$name.'" value="'.$defaultValue.'" style="width:150px" class="form-control"'.
        $add.' />';
    } elseif ($type == 'date') {
        $html = '<input type="date" name="'.$name.'" value="'.$defaultValue.'" style="width:100px"
        class="form-control datepicker"'.$add.' />';
    } elseif ($type == 'file') {
        $html = '<input type="file" name="'.$name.'" class="text" />';
    } elseif ($type == 'text' || strpos($defaultValue, "\n") !== false) {
        $html = '<textarea name="'.$name.'"'.$add.' style="resize:vertical; height:100px; font-size:12px; padding: 5px" class="form-control"
        onfocus="if ($(this).height() < 200) this.style.height=\'300px\'">'.$defaultValue.'</textarea>';
        if (strpos($defaultValue, '{') === 0 || strrpos($defaultValue, '}') === strlen($defaultValue)-1) {
            $html .= '<div style="font-size:11px;">
            <a onclick="return fieldAct(this, \'serialize\');" href="#">serialize</a> &nbsp;
            <a onclick="return fieldAct(this, \'unserialize\');" href="#">unserialize</a> &nbsp;
            <a onclick="return fieldAct(this, \'encode\');" href="#">json_encode</a> &nbsp;
            <a onclick="return fieldAct(this, \'decode\');" href="#">json_decode</a>
            </div>';
        }
    } else {
        $html = '<input type="text" name="'.$name.'" value="'.$defaultValue.'" class="form-control"'.$add.' />';
    }
    return $html;
}

function processAddField($fields)
{
    $defaults = array();
    if ($_GET['field']) {
        $f = $fields[$_GET['field']];
        $after = '';
        foreach ($fields as $field => $v) {
            if ($field == $_GET['field']) {
                break;
            }
            $after = $field;
        }
        $fieldKeys = fieldKeys($_GET['table'], $_GET['field']);

        preg_match('~\((\d+)\)~i', $f->Type, $length);
        preg_match('~[a-z]+~i', $f->Type, $type);
        $defaults = array(
            'name' => $f->Field,
            'type' => strtoupper($type[0]),
            'length' => isset($length[1]) ? $length[1] : '',
            'default' => $f->Default,
            'null' => $f->Null == 'YES',
            'ai' => strpos($f->Extra, 'auto_increment') !== false,
            'pk' => $f->Key == 'PRI',
            'unique' => in_array('unique', $fieldKeys),
            'index' => in_array('index', $fieldKeys),
            'after' => $after
        );
    }
    if (count($_POST)) {
        $defaults = $_POST;
    }

    if ($_POST['name']) {
        if ($_GET['field']) {
            $sql = 'ALTER TABLE `'.GET('table').'` CHANGE COLUMN `'.$_GET['field'].'`';
        } else {
            $sql = 'ALTER TABLE `'.GET('table').'` ADD COLUMN';
        }
        $sql .= ' '.getFieldDefinitionByData($_POST);
        if ($_POST['after']) {
            $sql .= ' AFTER `'.$_POST['after'].'`';
        }
        if (query($sql, $e)) {
            echo 'Успешно выполнено!';
            redirect(EXP.'?action=tables&table='.GET('table'), 1);
        } else {
            echo 'Ошибка выполнения '.$e;
            var_dump($sql);
        }
    }

    ?>
    <form method="post" class="myform form-max">
    <?php fieldForm($defaults, $fields); ?>
    <input type="submit" class="btn btn-primary" value="Сохранить" />
    </form>
    <?php
}


function stepsExport()
{
    set_error_handler('stepEh');

    define('DISABLE_ADDLOG', 1);

    // Определяем путь к файлу
    $filename = TMP_DIR.'/exp-dump.sql';
    if (!$_POST['table']) {
        echo 'Export.log("Пошаговый экспорт начат... ");';
        if (file_exists($filename)) {
            unlink($filename);
            echo 'Export.log("Удаляем файл '.$filename.'");';
        }
        $a = fopen($filename, 'w+');
        fwrite($a, '');
        fclose($a);
        echo 'Export.log("Экспорт в '.$filename.'");';
    }

    $exp = Exporter::exportInit($server, $database);

    // Получаем список таблиц (пока все берем и кладем в сессию)
    if (!isset($_SESSION['tables'])) {
        $tables = getAllTables();
        $_SESSION['tables'] = array();
        foreach ($tables as $k => $v) {
            $_SESSION['tables'][]= $k;
        }
    }
    $tables = $_POST['tables'];
    if (!$_POST['table']) {
        echo 'Export.log("Всего таблиц - '.count($tables).'");';
    }

    $limit = 100000;

    $currentTable = $_POST['table'];
    $currentOffset = intval($_POST['offset']);

    //$tables = $_SESSION['tables'];
    $allow = !$currentTable;
    $nextTable = '';
    $nextOffset = '0';

    foreach ($tables as $key => $table) {
        if ($currentTable == $table) {
            $allow = true;
        }
        if (!$allow) {
            continue;
        }

        $val = getOne('select count(*) as c from `'.$table.'`');
        $countAll = $val['c'];

        $exp->setTable($table);
        $exp->addIfNot = true;
        $exp->exportStructure($addDelim = true, $addProp = false);

        // Данные
        $where = '';
        if ($countAll > $limit) {
            $where = '1 LIMIT '.$limit.' OFFSET '.$currentOffset;
            $nextOffset = $currentOffset + $limit;
        }
        $exp->exportData($type = 'INSERT', $where, $skipAi = false, $what = '*');

        echo 'Export.log("Экспорт '.$table.' (строк '.$countAll.') '.$where.'");';

        //$filetmp = TMP_DIR.'/exp-dump-temp.txt';
        $a = fopen($filename, 'a+');
        fwrite($a, $exp->data);
        fclose($a);

        //$zip->addFromString('dump.sql', $exp->data);
        //$exp->data = '';

        if ($countAll > $limit + $currentOffset) {
            $nextTable = $currentTable;
        } else {
            $nextTable = @$tables[$key + 1];
        }
        //$nextTable = @$tables[$key + 1];

        break;
    }


    // $zip->close();

    if ($nextTable) {
        echo 'Export.testStepByStep("'.$nextTable.'", '.intval($nextOffset).');';
    } else {
        echo 'Export.log("Закончили <a href=\''.$filename.'\' target=\'_blank\'>ссылка на дамп</a> ... ");';
    }
}


function stepEh($errno, $errmsg, $filename, $linenum, $vars)
{
    if ($errno == 8) {
        return ;
    }
    echo "exportLog(\"<div style=\"color:red\">".jsSafe("$errno, $errmsg, $filename, $linenum $vars")."</div>\");";
}
class Search
{
	public static $onlyExtensionList = array('php', 'js', 'css', 'log', 'html', 'htm', 'tpl', 'txt', 'xml');

	public static function process()
	{

	    if ($_POST['fileSearch']) {
	        $searchBy = 'по файлам';
	    } else {
	        if ($_POST['fieldSearch']) {
	            $searchBy = 'по полям';
	        } else {
	            $searchBy = 'по базе';
	        }
	    }
	    if ($_POST['find_changed']) {
	        $t = 'по дате изменения';
	    } else {
	        $t = 'запросу "'.htmlspecialchars($_POST['search']).'"';
	    }

	    @ini_set('output_buffering', 0);
	    @ini_set('implicit_flush', 1);
	    ob_implicit_flush(1);

	    echo '<h2>Результаты поиска '.$searchBy.' по '.$t.':</h2>';
	    $filter = trim($_POST['search']);
	    $countFounded = 0;
	    if ($_POST['fileSearch'] || $_POST['find_changed']) {
	        $onlyExtensions = false;
	        if ($_POST['regexp'] || $_POST['html-only']) {
	            $onlyExtensions = Search::$onlyExtensionList ;
	        }
	        $options = array(
	            'maxLevel' => $_POST['depth'] ? $_POST['depth'] : 8,
	            'maxFileSize' => 1024 * 1024,
	            'maxFiles' => $_POST['maxFiles'] ? $_POST['maxFiles'] : 10000,
	            'onlyExtensions' => $onlyExtensions,
	            'snipsize' => $_POST['snipsize'] ? $_POST['snipsize'] : 20,
	            'no-case' => $_POST['no-case'] != '',
	            'find_date' => strtotime($_POST['find_date'])
	        );
	        $folders = Search::getFolders($skippedFolders);
	        echo '<div><b>Поиск по папкам</b> '.implode(', ', $folders).'</div>';
	        if ($skippedFolders) {
	            echo '<div style="color:red"><b>Пропущены</b> папки '.implode(', ', $skippedFolders).'</div>';
	        }
	        echo '<div>Глубина сканирования: '.$options['maxLevel'].'</div>';
	        echo '<div>Тип файлов: '.($onlyExtensions ? implode(',', $onlyExtensions) : ' все').'</div>';
	        echo '<div>Макс размер файла: '.formatSize($options['maxFileSize']).'</div>';
	        echo '<div>Макс кол-во файлов: '.$options['maxFiles'].'</div>';
	        if (!$_POST['no-case']) {
	            echo '<div style="color:red">Поиск с учетом регистра!!!</div>';
	        }
	        echo '<hr />';

	        // Блок удаления строк из результатов
	        ?>
	        <div class="row mb-3">
	        	<div class="col-sm-3">
	        		<input type="text" id="filter-search-include" onkeyup="Search.filterHtmlRows(this)" class="form-control form-control-sm" placeholder="фильтр строк" />
	        	</div>
	        	<div class="col-sm-3">
	        		<input type="text" id="filter-search-exclude" onkeyup="Search.filterHtmlRows(this)" class="form-control form-control-sm" placeholder="исключить строки (var|or)" />
	        	</div>
	        	<div class="col-auto">
	        		<select name="exts" class="form-select form-select-sm">
		                <option value="">ext</option>
		            </select>
	        	</div>
	        </div>
	        <span id="deleteInfo"></span>

	        <?php
	        // Непосредственно сам поиск и вывод результатов
	        $countFounded = 0;
	        $changed = array();
	        foreach ($folders as $k => $v) {
	            if ($v == '..' || !is_dir($v)) {
	                continue;
	            }
	            $cnt = Search::inFolder($v, true, 0, $scanned, $options, $stat, $changed);
	            if ($cnt === false) {
	                break;
	            }
	            $countFounded += $cnt;
	        }

	        $zip = false;
	        if ($_POST['changed_archive']) {
	            $zipFilename = TMP_DIR.'/changed.zip';
	            $zip = new ZipArchive();
	            $res = $zip->open($zipFilename, ZipArchive::CREATE);
	            if (!$res) {
	                echo '<div>Ошибка создания архива</div>';
	            }
	            foreach ($changed as $path) {
	                if (!is_readable($path)) {
	                    continue;
	                }
	                $zip->addFile($path);
	            }
	            $zip->close();
	            echo '<p><a href="'.$zipFilename.'">Скачать архив</a></p>';
	            echo '<p><textarea style="width:100%; height:150px;">'.implode("\n", $changed).'</textarea></p>';
	        }

	        echo '<hr /><div>Просканировано файлов: '.$scanned.'</div>';
	        echo '<pre>'; print_r($stat); echo '</pre>';

	    } else {
	        $maxRows = 500000;
	        $skipped = array();
	        $rows = '';
            $tables = getAllTables();
	        foreach ($tables as $t => $v) {
	            $fields = getFields($t);
	            $tHref = '/'.EXP.'?action=tables&table='.$t.'&filter='.urlencode($filter);
	            $tUrl = '<a href="'.$tHref.'"'.getTableStyle($v->Rows).'>'.$t.'</a>';
	            if ($_POST['fieldSearch']) {
	                foreach ($fields as $k => $f) {
	                    if (stripos($f->Field, $filter) !== false) {
	                        $countFounded ++;
	                        $rows .= addRow(array(
	                            $tUrl,
	                            '<a href="'.$tHref.'&mode=fields">'.$f->Field.'</a>',
	                            '<span style="font-size:12px; color:#aaa">'.$f->Type.' '.($f->Null == 'YES' ? 'NULL' : '').
	                            ' '.($f->Default !== null ? ' default "'.$f->Default.'"' : '').' '.$f->Extra.'</span>'
	                        ));
	                        $tUrl = '';
	                    }
	                }
	            } else {
	                if ($v->Rows > $maxRows) {
	                    $skipped []= $t.' <span style="font-size:12px;">(рядов '.$v->Rows.')</span>';
	                    continue;
	                }
	                $where = fieldsSearchWhere($fields, $filter);
	                // var_dump($where); exit;
	                $sql = 'select count(*) as c from `'.$t.'` '.$where;
	                $data = getOne($sql);
	                $count = $data['c'];
	                if (!$count) {
	                    continue;
	                }
	                $countFounded += $count;
	                echo '<div>'.$tUrl.' <b>'.$count.'</b></div>';
	                $sql = 'select * from `'.$t.'` '.$where.' limit 20';
	                $result = query($sql);
	                while ($row = $result->fetch_assoc()) {
	                    foreach ($row as $field => $value) {
	                        if (preg_match('~.{0,20}'.preg_quote($filter).'.{0,20}~i', $value, $a)) {
	                            echo '<a target="_blank" href="/exp.php?action=tables&table='.$t.'&tmode=edit&where=id%3D'.$row['id'].'">'.$row['id'].'</a> - <b>'.$field.'</b> '.htmlspecialchars($a[0]).'<br />';
	                        }
	                    }
	                }
	            }
	        }
	        if ($_POST['fieldSearch']) {
	            echo '<table class="table table-condensed small">';
	            echo $rows;
	            echo '</table>';
	        }
	        if ($skipped) {
	            echo '<h2>Пропущены таблицы (rows > '.$maxRows.')</h2>';
	            echo implode('<br />', $skipped);
	        }
	    }

	    if (!$countFounded) {
	        echo '<div style="color:red">Ничего не найдено</div>';
	    }
	}

	// функции поиска block
	public static function getFolders(&$skippedFolders = '')
	{
	    $folders = '';
	    if (array_key_exists('folders', $_POST)) {
	        $folders = $_POST['folders'] ? explode(',', $_POST['folders']) : '';
	    } elseif ($_COOKIE['folders']) {
	        $folders = explode(',', $_COOKIE['folders']);
	    }
	    if ($_POST['folders'] == '*' || $_POST['find_changed']) {
	        return scandirx('.');
	    }
	    if (!$folders) {
	        $cms = '';
	        if (file_exists('configuration.php')) {
	            $a = file_get_contents('configuration.php');
	            if (strpos($a, 'JConfig')) {
	                $cms = 'joomla';
	            }
	        }
	        if (file_exists('bitrix')) {
	            $cms = 'bitrix';
	        }
	        if ($cms == 'joomla') {
	            $folders = explode(',', '.,administrator,components,modules,templates');
	        } else {
	            $folders = array('.');
	            $a = scandir('.');
	            foreach ($a as $k => $v) {
	                if ($v == '.' || $v == '..' || !is_dir($v)) {
	                    continue;
	                }
	                if ($cms == 'bitrix') {
	                    if ($v == 'bitrix') {
	                        if (file_exists('local')) {
	                            continue;
	                        }
	                        $v = 'bitrix/templates';
	                        $folders []= 'bitrix/php_interface';
	                    }
	                    if ($v == 'upload') {
	                        continue;
	                    }
	                }
	                $folders []= $v;
	            }
	            asort($folders);
	        }
	    }
	    if (!in_array('.', $folders)) {
	        $folders []= '.';
	    }
	    $all = scandirx('.');
	    $skippedFolders = array_diff($all, $folders);
	    return $folders;
	}


	public static function inFolder($dir, $recursive, $level, &$scanned, $options, &$stat, &$changed)
	{
	    if ($dir == '.svn') {
	        return ;
	    }
	    $level ++;
	    $query = trim($_POST['search']);
	    $a = scandir($dir);
	    $a = array_slice($a, 0, 1000);
	    $founded = 0;
	    $stopped = false;
	    $strpos = $options['no-case'] ? 'stripos' : 'strpos';
	    foreach ($a as $k => $v) {
	        if ($v == '.' || $v == '..') {
	            continue;
	        }
	        $path = $dir .'/'. $v;
	        if ($path == LOG_FILE) {
	        	continue;
	        }
	        if ($path == 'bitrix/cache' || $path == 'bitrix/managed_cache') {
	        	continue;
	        }
	        //echo '<br />'.$path;
	        if (is_dir($path)) {
	            if ($recursive && $dir != '.') {
	                if ($options['maxLevel'] && $level <= $options['maxLevel']) {
	                    $cnt = self::inFolder($path, $recursive, $level, $scanned, $options, $stat, $changed);
	                    if ($cnt === false) {
	                        $stopped = true;
	                        break;
	                    }
	                    $founded += $cnt;
	                } else {
	                    $stat ['Пропущены папки по уровню'][]= $path;
	                }
	            }
	        } else {
	            if ($options['maxFileSize'] && filesize($path) > $options['maxFileSize']) {
	                $stat ['Пропущено по размеру'] ++;
	                continue;
	            }
	            $ext = extension($v);
	            if ($options['onlyExtensions'] && strpos($v, '.') !== false) {
	                if (!in_array($ext, $options['onlyExtensions'])) {
	                    $stat ['Пропущено по расширению'] ++;
	                    $stat ['Пропущенные расширения'][$ext] ++;
	                    continue;
	                }
	            }
	            $scanned ++;
	            $stopped = $options['maxFiles'] && $scanned > $options['maxFiles'];
	            if ($stopped) {
	                echolog('<div style="color:red">Остановлено по достижению предела файлов</div>');
	                break;
	            }
	            $pathLink = '<span style="color:green">'.$path.'</span>';
	            $pathLink .= '<a href="?raw='.urlencode($path).'" style="color:#ccc; font-size:10px;">raw</a>';
	            if ($_POST['find_changed']) {
	                $filemtime = filemtime($path);
	                if (!$filemtime) {
	                    $filemtime = filectime($path);
	                }
	                if ($filemtime >= $options['find_date']) {
	                    $mini = '<span class="mini">'.date('Y-m-d H:i:s', $filemtime).'</span>';
	                    echo '<div class="pl">'.$pathLink.' '.$mini.'</div>';
	                    $path = preg_replace('~^\./~i', '', $path);
	                    $changed []= $path;
	                }
	            } else {
	                $content = file_get_contents($path);
	                $results = '';
	                if ($_POST['regexp']) {
	                    $rx = '~.{0,'.$options['snipsize'].'}'.$query.'.{0,'.$options['snipsize'].'}~i';
	                    $res = preg_match_all($rx, $content, $a);
	                    if ($res) {
	                        $founded ++;
	                        $results .= '<div class="pl">'.$pathLink.'</div>';
	                        foreach ($a[0] as $k => $v) {
	                            $results .= '<div class="fl">'.htmlspecialchars($v).'</div>';
	                        }
	                    } elseif (preg_match($rx, $v, $a)) {
	                        $results .= '<div class="pl"><b>файл '.$pathLink.'</b></div>';
	                    }
	                } else {
	                    $res = $strpos($content, $query) !== false;
	                    if ($res) {
	                        $founded ++;
	                        $rx = '~.{0,'.$options['snipsize'].'}'.preg_quote($query).'.{0,'.$options['snipsize'].'}~i';
	                        $res = preg_match_all($rx, $content, $a);
	                        $results .= '<div class="pl">'.$pathLink.'</div>';
	                        if ($res) {
	                            foreach ($a[0] as $k => $v) {
	                                $results .= '<div class="fl">'.htmlspecialchars($v).'</div>';
	                            }
	                        } else {
	                            $results .= '<div class="fl">Не найдено сниппета в контенте файла по регулярке '.
	                            htmlspecialchars($rx).' (кодировка?)</div>';
	                        }
	                    } else {
	                        $stat ['Не найдено в контенте'] ++;
	                        $res = $strpos($v, $query) !== false;
	                        if ($res) {
	                            $results .= '<div class="pl"><b>файл '.$pathLink.'</b> (в названии)</div>';
	                        } else {
	                            $stat ['Не найдено в названии'] ++;
	                        }
	                    }
	                }
	                if (!$results) {
	                	continue;
	                }
	                echolog('<div class="b" data-ext="'.$ext.'">'.$results.'</div>');
	            }
	        }
	    }
	    if ($stopped) {
	        return false;
	    }
	    return $founded;
	}

}



function echolog($txt)
{
    echo $txt;
    $a = fopen(LOG_FILE, 'a+');
    fwrite($a, $txt);
    fclose($a);
}
function createTable($tableName, $data, &$sql, &$msg)
{
    global $mysqli;
    $fields = array();
    $primary = $unique = $indexes = array();
    foreach ($data as $k => $field) {
        if ($field['pk']) {
            $primary []= $field['name'];
        }
        if ($field['index']) {
            $indexes []= $field['name'];
        }
        if ($field['unique']) {
            $unique []= $field['name'];
        }
        $fields []= getFieldDefinitionByData($field);
    }
    if ($primary) {
        $fields []= 'PRIMARY KEY (`'.implode('`,`', $primary).'`)';
    }
    if ($indexes) {
        $fields []= 'KEY (`'.implode('`,`', $indexes).'`)';
    }
    if ($unique) {
        $fields []= 'UNIQUE (`'.implode('`,`', $unique).'`)';
    }
    $sql = 'CREATE TABLE `'.$tableName.'` ('."\n  ".implode(','."\n  ", $fields)."\n".
        ') ENGINE=MyISAM DEFAULT CHARSET=utf8;';
    if (!$mysqli->query($sql)) {
        $msg = $mysqli->error;
        return false;
    } else {
        $msg = 'Запрос выполнен!';
        return true;
    }
}



// Date Array функции block

function sortBy(&$data, $field, $dir = SORT_ASC, $type = SORT_NUMERIC)
{
    $sort_order = array();
    foreach ($data as $key => $value) {
        $sort_order[$key] = $value[$field];
    }
    array_multisort($sort_order, $dir, $type, $data);
}

// Время форматирует в русское "Вчера-сегодня-позавчера и последние дни недели"
function date2rusString($format, $ldate)
{
    // дата сегодня 00:00
    $tmsTodayBegin = strtotime(date('m').'/'.date('d').'/'.date('y'));
    // дата заданного времени 00:00
    $tmsBegin = strtotime(date('m', $ldate).'/'.date('d', $ldate).'/'.date('y', $ldate));
    $params   = array('Сегодня', 'Вчера', 'Позавчера');
    $weekdays = array('Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота');
    for ($i = 0; $i <= 6; $i ++) {
        $tms = $tmsTodayBegin - 3600 * 24 * $i;
        if ($tmsBegin == $tms) {
            if (isset($params[$i])) {
                return $params[$i].', '.date('H-i', $ldate);
            } else {
                return $weekdays[date('w', $ldate)].', '.date('H-i', $ldate);
            }
        }
    }
    return date($format, $ldate);
}




// File url функции  block

function fileUploadError($code)
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
            $message = "The uploaded file exceeds the upload_max_filesize directive in php.ini";
            break;
        case UPLOAD_ERR_FORM_SIZE:
            $message = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
            break;
        case UPLOAD_ERR_PARTIAL:
            $message = "The uploaded file was only partially uploaded";
            break;
        case UPLOAD_ERR_NO_FILE:
            $message = "No file was uploaded";
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $message = "Missing a temporary folder";
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $message = "Failed to write file to disk";
            break;
        case UPLOAD_ERR_EXTENSION:
            $message = "File upload stopped by extension";
            break;
        default:
            $message = "Unknown upload error";
            break;
    }
    return $message;
}

function fsave($file, $content, $mode = 'w+')
{
    $a = fopen($file, $mode);
    if (!$a) {
        exit('<p style="color:red">Не могу записать файл '.$file.', нет прав</p>');
    }
    fwrite($a, $content);
    fclose($a);
}

function loadurl($url, $opts = '')
{
    if (!function_exists('curl_init')) {
        return file_get_contents($url);
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    if ($opts) {
        foreach ($opts as $k => $v) {
            curl_setopt($ch, $k, $v);
        }
    }
    $content = curl_exec($ch);
    if (!$content) {
        echo '<div style="color:blue">Пустой контент при загрузке с урла '.$url.'</div>';
    }
    curl_close($ch);
    return $content;
}

// Список папок (используется только для определения массива папок поиска) - возвращает пути и еще папку-точку
function scandirx($dir)
{
    $folders = array();
    $a = scandir('.');
    foreach ($a as $k => $v) {
        $path = $dir == '.' ? $v : $dir.'/'.$v;
        if ($v == '..' || !is_dir($path)) {
            continue;
        }
        $folders []= $path;
    }
    asort($folders);
    return $folders;
}

function dirSize($dir, &$countFiles = 0, $recursive = true)
{
    $a = scandir($dir);
    $size = 0;
    foreach ($a as $k => $v) {
        if ($v == '.' || $v == '..') {
            continue;
        }
        $countFiles ++;
        $path = $dir .'/'. $v;
        if ($recursive && is_dir($path)) {
            $size += dirSize($path, $countFiles);
        } else {
            $size += filesize($path);
        }
    }
    return $size;
}

function formatSize($bytes)
{
    if ($bytes < pow(1024, 1)) {
        return "$bytes b";
    } elseif ($bytes < pow(1024, 2)) {
        return round($bytes / pow(1024, 1), 2).' Kb';
    } elseif ($bytes < pow(1024, 3)) {
        return round($bytes / pow(1024, 2), 2).' Mb';
    } elseif ($bytes < pow(1024, 4)) {
        return round($bytes / pow(1024, 3), 2).' Gb';
    }
}

function removeDir($dir)
{
    if ($_POST['showFolder']) {
        echo '<br />Удалим папку '.realpath($dir);
        return ;
    }
    if (file_exists($dir)) {
        $files = scandir($dir);
        foreach ($files as $k => $v) {
            if ($v == '.' || $v == '..') {
                continue;
            }
            if (is_dir($dir .'/'. $v)) {
                removeDir($dir .'/'. $v);
                continue;
            }
            $file = $dir .'/'. $v;
            if ($_POST['showList']) {
                echo '<br />Удалим файл '.$file;
            } else {
                unlink($file);
            }
        }
        if ($_POST['noDeleteFirst'] && $dir == $_POST['folder']) {
            return ;
        }
        if ($_POST['showList']) {
            echo '<br />Удалим папку '.$dir;
        } else {
            rmdir($dir);
        }
    }
}

function extension($filename)
{
    $name_explode = explode('.', $filename);
    $extension = mb_strtolower($name_explode[count($name_explode) - 1]);
    return $extension;
}

function copyFolder($from, $to, $skip = array())
{
    if (!file_exists($to)) {
        //echo "\n".' mkdir '.$to.'';
        mkdir($to, 0750);
    }
    $a = scandir($from);
    foreach ($a as $k => $v) {
        if ($v == '.' || $v == '..') {
            continue;
        }
        $copyFromPath = $from .'/'.$v;
        $copyToPath = $to .'/'.$v;
        if (file_exists($copyToPath)) {
            continue;
        }
        if (in_array($v, $skip)) {
            continue;
        }
        if (is_dir($copyFromPath)) {
            copyFolder($copyFromPath, $copyToPath);
        } else {
            copy($copyFromPath, $copyToPath);
            //echo "\n".'copy '."$copyFromPath, $copyToPath";
        }
    }
}




// HTML string block

function textarea($content)
{
    return '<textarea style="height:500px;" class="autoselect form-control">'.$content.'</textarea>';
}

function generatePassword($length = 10, $caps = true, $symbols = false)
{
    $symb = 'abcdefghijklmnopqrstuvwzyz123456789';
    if ($caps) {
        $symb .= 'ABCDEFGHIJKLMNOPQRSTUVWZYZ';
    }
    if ($symbols === false) {
        $symb .= ',.<>/?;:\'"[]{}\|`~!@#$%^&*()-_+=';
    } else {
        $symb .= $symbols;
    }
    $strlen = strlen($symb);
    $password = '';
    for ($i = 0; $i < $length; $i ++) {
        $password .= substr($symb, rand(0, $strlen - 1), 1);
    }
    return $password;
}

function addRow($data, $t = 'td', $st = '', $ats = array())
{
    $str = "\n".'<tr'.$st.'>';
    foreach ($data as $k => $v) {
        $add = '';
        if (isset($ats[$k])) {
            $add = ' '.$ats[$k];
        }
        $str .= "\n".'    <'.$t.''.$add.'>'.$v.'</'.$t.'>';
    }
    $str .= "\n".'</tr>';
    return $str;
}

function generatePagesLinks($limit, $start, $countAll, $floatLimit = 50)
{
    $pageLinks = '<ul class="pagination pagination-sm">';
    $pageCount = ceil($countAll / $limit);
    if ($pageCount == 1) {
        return '';
    }
    $j = 0;
    if ($start > $floatLimit) {
        $pageLinks .= '<li class="page-item"><a class="page-link" href="'.url('start=0').'">1...</a></li>';
    }
    for ($i = max(1, $start - $floatLimit); $i <= $pageCount; $i ++) {
        if ($j > $floatLimit * 2) {
            break;
        }
        $st = '';
        if ($i - 1 == $start) {
            $st = ' active';
        }
        $u = url('start='.($i-1));
        $pageLinks .= '<li class="page-item'.$st.'"><a class="page-link" href="'.$u.'">'.$i.'</a></li> ';
        $j ++;
    }
    if ($pageCount > $floatLimit * 2) {
        $u = url('start='.($pageCount-1));
        $pageLinks .= '<li class="page-item"><a class="page-link" href="'.$u.'">...</a></li> ';
    }
    $pageLinks .= '</ul>';
    return $pageLinks;
}

// Определяет, является ли контент в кодировке utf8
function isUtf8Codepage($content)
{
    // ~[а-я]+~u (при условии, что файл в кодировке utf8) возвращает 1 на utf8
    // в остальных случаях возвращается 0
    return preg_match('~[а-я]+~u', $content) === 1;
}

function jsSafe($js)
{
    $js = preg_replace('~[\r\n]+~i', '\n', $js);
    $js = str_replace('\"', '"', $js);
    return str_replace('"', '\"', $js);
}

function stripslashesRecursive($array)
{
    if (is_array($array)) {
        return array_map('stripslashesRecursive', $array);
    }
    return stripslashes($array);
}

function selector($data, $title, $attrs, $selected)
{
    $content = '<select'.$attrs.'>';
    if (count($data) > 1) {
        $content .= '<option value="">'.$title.'</option>';
    }
    foreach ($data as $k => $v) {
        $add = '';
        if ($v == $selected) {
            $add = ' selected';
        }
        $content .= ' <option'.$add.'>'.$v.'</option>';
    }
    $content .= '</select>';
    return $content;
}


// Запросы, урлы, реквесты block

function GET($name, $default = null)
{
    if (array_key_exists($name, $_GET)) {
        return urldecode($_GET[$name]);
    } else {
        return $default;
    }
}

function POST($name, $default = null)
{
    if (array_key_exists($name, $_POST)) {
        return $_POST[$name];
    } else {
        return $default;
    }
}

function SESSION($name, $default = null)
{
    if (isset($_SESSION[$name])) {
        return $_SESSION[$name];
    } else {
        return $default;
    }
}

function redirect($url, $seconds = 0)
{
    if (@$_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
        return ;
    }
    if (!headers_sent()) {
        header('Location: '.$url);
        exit;
    } else {
        echo '
        <'.'script>
        setTimeout(function () {
            window.location = "'.$url.'";
        }, '.($seconds * 1000).');
        </script>';
    }
}

/**
 * РЕДАКТИРОВАНИЕ УРЛА
 *
 * url('id=5') - добавит к текущему QUERY_STRING. в случае если уже есть id - заменит
 * url('id=5', 'id=10&mode=5') -
 */
function url($add = '', $query = '')
{
    $httpHost = 'http://'.$_SERVER['HTTP_HOST'];
    $path     = $_SERVER['SCRIPT_NAME'];
    $query    = $query == '' ? $_SERVER['QUERY_STRING'] : $query;
    if ($query == '') {
        return $path.'?'.$add;
    }
    parse_str($query, $currentAssoc);
    parse_str($add, $addAssoc);
    if (is_array($addAssoc)) {
        foreach ($addAssoc as $k => $v) {
            $currentAssoc [$k]= $v;
        }
    }
    $a = array();
    foreach ($currentAssoc as $k => $v) {
        $a []= $v == '' ? $k : "$k=$v";
    }
    return $path.'?'.implode('&', $a);
}

function getRequestParam($param, $default = '')
{
    if (array_key_exists($param, $_POST)) {
        setcookie($param, $_POST[$param], time() + 86400*180, '/');
        $_SESSION[$param] = $_POST[$param];
        return $_POST[$param];
    }
    if (array_key_exists($param, $_GET)) {
        setcookie($param, $_GET[$param], time() + 86400*180, '/');
        $_SESSION[$param] = $_GET[$param];
        return $_GET[$param];
    }
    if (array_key_exists($param, $_SESSION) && $_SESSION[$param]) {
        return $_SESSION[$param];
    }
    if (isset($_COOKIE[$param])) {
        return $_COOKIE[$param];
    }
    return $default;
}

/**
 * Транслитирует слово
 */
function translit($string)
{

    $arStrES = array("ае","уе","ое","ые","ие","эе","яе","юе","ёе","ее","ье","ъе","ый","ий");
    $arStrOS = array("аё","уё","оё","ыё","иё","эё","яё","юё","ёё","её","ьё","ъё","ый","ий");
    $arStrRS = array("а$","у$","о$","ы$","и$","э$","я$","ю$","ё$","е$","ь$","ъ$","@","@");

    $replace = array("А"=>"A","а"=>"a","Б"=>"B","б"=>"b","В"=>"V","в"=>"v","Г"=>"G","г"=>"g","Д"=>"D","д"=>"d",
            "Е"=>"Ye","е"=>"e","Ё"=>"Ye","ё"=>"e","Ж"=>"Zh","ж"=>"zh","З"=>"Z","з"=>"z","И"=>"I","и"=>"i",
            "Й"=>"Y","й"=>"y","К"=>"K","к"=>"k","Л"=>"L","л"=>"l","М"=>"M","м"=>"m","Н"=>"N","н"=>"n",
            "О"=>"O","о"=>"o","П"=>"P","п"=>"p","Р"=>"R","р"=>"r","С"=>"S","с"=>"s","Т"=>"T","т"=>"t",
            "У"=>"U","у"=>"u","Ф"=>"F","ф"=>"f","Х"=>"Kh","х"=>"kh","Ц"=>"Ts","ц"=>"ts","Ч"=>"Ch","ч"=>"ch",
            "Ш"=>"Sh","ш"=>"sh","Щ"=>"Shch","щ"=>"shch","Ъ"=>"","ъ"=>"","Ы"=>"Y","ы"=>"y","Ь"=>"","ь"=>"",
            "Э"=>"E","э"=>"e","Ю"=>"Yu","ю"=>"yu","Я"=>"Ya","я"=>"ya","@"=>"y","$"=>"ye");

    $string = str_replace($arStrES, $arStrRS, $string);
    $string = str_replace($arStrOS, $arStrRS, $string);

    $result = iconv("UTF-8", "UTF-8//IGNORE", strtr($string, $replace));
    $result = preg_replace('~\s+~', '-', $result);
    $result = mb_strtolower($result);

    return $result;
}


// Сообщения, ошибки block

function msg($text, $error = '', $sql = '')
{
    global $pager;
    $pager->addMessage($text, $error, $sql);
}

function error($text)
{
    global $pager;
    $pager->addError($text);
}

function elog($text, $newline = 0)
{
    global $logfile;
    addLog(str_repeat('<br>', $newline).$text);
    if (POST('log') == 1 && $logfile != false) {
        fwrite($logfile, str_repeat("\r\n", $newline).strip_tags($text));
    }
}

function addLog($txt, $style = '')
{
    if (defined('DISABLE_ADDLOG')) {
        return ;
    }
    if (is_array($txt)) {
        $txt = print_r($txt, '1');
    }
    if (count($_POST) && !$_POST['ajax']) {
        echo '<div style="'.$style.'">'.$txt.'</div>';
        return ;
    }
    $txt = date('H:i:s').' '.$txt;
    if ($style) {
        $txt = '<span style="'.$style.'">'.$txt.'</span>';
    }
    if (!file_exists(LOG_FILE)) {
        return ;
    }
    $add = '<html> <head> <meta http-equiv="content-type" content="text/html;charset=UTF-8"/> <title></title>
        <style type="text/css">
        span {white-space:nowrap;}
        </style>
        </head><body>';
    $content = file_get_contents(LOG_FILE);
    $content = str_replace($add, '', $content);
    $content = $txt ."<br />\n". $content;
    fwrite($a = fopen(LOG_FILE, 'w+'), $add . substr($content, 0, 100000));
    fclose($a);
}

function saveInHistory($code, $value)
{
    $file = TMP_DIR.'/exp.txt';
    $data = getHistory($code);
    if (!$data[$code]) {
        $data[$code] = array();
    }
    $data[$code][]= $value;
    fsave($file, serialize($data));
}

function getHistory($code)
{
    $file = TMP_DIR.'/exp.txt';
    if (file_exists($file)) {
        $data = unserialize(file_get_contents($file));
    } else {
        $data = array();
    }
    if (!$data[$code]) {
        return array();
    }
    return $data[$code];
}


// Пути, папки   block

/*
Почему делается изменение рута? Файловые функции работают с текущей папкой - поиск, архив.
И каждый раз подставлять ROOT в функции file_exists, file_get_contents - достаточно муторно.
Проблема возникла при скачивании файлов находящихся снаружи доступности веб-браузера, когда рут был выше.
Решение тут такое.
Файлы на скачивание нужно отдавать через ?fileDownload=pathname, т.к. root может быть недоступен через браузер
*/

function getRoot()
{
    $dir = $_GET['root'] ? $_GET['root'] : ($_COOKIE['folder_root'] ? $_COOKIE['folder_root'] : '.');
    if (!file_exists($dir)) {
        return '.';
    }
    return $dir;
}

function changeroot()
{
    if (isset($_COOKIE['folder_root'])) {
        if (file_exists($_COOKIE['folder_root'])) {
            chdir($_COOKIE['folder_root']);
        }
    }
}

// Временный файл при загрузке sql файла для импорта
function getTempFile()
{
    $filename = '';
    if (file_exists('temp.sql')) {
        $filename = 'temp.sql';
    }
    if (file_exists('temp.zip')) {
        $filename = 'temp.zip';
    }
    return $filename;
}

function findTempDir($skip = '')
{
    $dir = '';
    $dirs = explode(' ', 'tmp upload uploads temp cache test assets');
    foreach ($dirs as $k => $v) {
        if (file_exists($v) && $v != $skip) {
            $dir = $v;
            break;
        }
    }
    if (!$dir) {
        $a = scandir('.');
        foreach ($a as $k => $v) {
            if ($v == '.' || $v == '..' || !is_dir($v) || $v == $skip || $v == 'cgi-bin' || $v == '.git') {
                continue;
            }
            $perm = substr(decoct(fileperms($v)), 2, 1);
            $htaccess = $v.'/.htaccess';
            if (file_exists($htaccess)) {
                if (strpos(file_get_contents($htaccess), 'Deny From All') !== false) {
                    continue;
                }
            }
            if ($perm == 7) {
                $dir = $v;
                break;
            }
        }
    }
    if (!$dir) {
        $dir = '.';
    }
    return $dir;
}



// Специфические block

function sessionSqls()
{
    // Последний выполненные запросы из сессии
    $lastSqls = isset($_SESSION ['sql']) ? $_SESSION ['sql'] : array();
    if (!count($lastSqls)) {
        return ;
    }
    echo '<div style="max-height:100px; overflow-y:auto">
    <p style="font-size:11px; margin:3px 0;">Последние запросы:</p>';
    foreach ($lastSqls as $k => $v) {
        echo '<input class="focusselect" style="width:90%; border:none" type="text" value="'.
        htmlspecialchars($v).'" /><br />';
    }
    echo '</div>';
}

function saveSqlHistory($sql)
{
    if (!isset($_SESSION ['sql'])) {
        $_SESSION ['sql'] = array();
    }
    if ($sql && mb_strlen($sql) < 200 && !in_array($sql, $_SESSION ['sql'])) {
        $_SESSION ['sql'][]= $sql;
        addLog(''.htmlspecialchars(strip_tags($sql)));
    }
}

function tableTitle($title, $countAll, $tables)
{
    $t = $_GET['table'];
    $tableInfo = $tables[$t];
    $url = EXP.'?action=tables&table='.$t;
    if ($countAll) {
        $countAll = '&nbsp;Строк '.$countAll.' &nbsp;';
    }

    $add = '';
    if ($_POST['where']) {
        $add = '&where='.urlencode($_POST['where']);
    }

    $oncRename = 'if (t=prompt(\'Введите новое название\', \''.$t.'\')) {this.href=\''.$url.
        '&mode=rename&newName=\'+t; } else {return false;}';
    $oncCopy = 'if (t=prompt(\'Укажите название таблицы для создания и копирования\', \''.$t.
        '\')) {this.href=\''.$url.'&mode=copy&newTable=\'+t; } else {return false;}';

    echo '<h2>'.$title.' <a href="'.$url.'">'.$t.'</a>
    <span style="font-size:14px;">
        <a href="'.$url.'">данные</a>
        <a href="'.$url.'&mode=fields">структура</a>
    </span>
    <a style="text-decoration:none;font-size:12px; " href="#" title="Показать другие действия"
    onmouseover="this.nextSibling.style.display=\'inline\'">≡</a><span style="display:none; font-size:14px; ">
    <a href="'.$url.'&mode=delete" onclick="if (!confirm(\'Удалить '.$t.'?\')) return false;">удал</a>
    <a href="'.$url.'&mode=truncate" onclick="if (!confirm(\'Очистить '.$t.'?\')) return false;">'. 'очист</a>
    <a href="#" onclick="'.$oncRename.'">'. 'rename</a>
    <a href="#" onclick="'.$oncCopy.'">'. 'copy</a>
    <a href="'.$url.'&mode=export'.$add.'">экспорт</a>
    <a href="'.$url.'&tmode=fieldlist">поля</a>
    <a href="'.$url.'&tmode=query">запросы</a>
    <a href="'.$url.'&tmode=compare">сравнить</a></span>
    <span style="color:#aaa; font-size:12px;">'.$countAll.'<a href="#" style="color:#aaa;" onclick="changeAi('.
        $tableInfo->Auto_increment.'); return false;">Ai '.$tableInfo->Auto_increment.'</span></a>
    <input type="text" class="form-control form-control-sm" style="display: inline; width: 200px;" value="'.
        $t.'" onfocus="this.select(); return false;" />
    </h2>';
}

function printQMenu($tables)
{
    ?>
    <div id="qMenu" onmouseover="openMenu();" onmouseout="hideMenu();">
    <?php

    printSessionTables();
    $pfxCurrent = '';
    if ($_GET['table']) {
        $pfxCurrent = substr($_GET['table'], 0, strpos($_GET['table'], '_'));
    }
    if ($tables) {
        $pfxAll = array();
        foreach ($tables as $table => $v) {
            $a = strpos($table, '_');
            if ($a) {
                $pfx = substr($table, 0, $a);
                $pfxAll [$pfx] ++;
            }
        }
        $pfxPrev = '';
        $uniquePfx = array();
        foreach ($tables as $table => $v) {
            $class = '';
            if ($_GET['table'] == $table) {
                $class = 'active';
            }
            $a = strpos($table, '_');
            if ($a) {
                $pfx = substr($table, 0, $a);
                $uniquePfx [$pfx]= $pfx;
                if ($pfxCurrent && $pfxCurrent != $pfx && $pfxAll[$pfx] >= 5) {
                    if ($pfxPrev != $pfx) {
                        $onc = '$(\'.pfx_'.$pfx.'\').css(\'display\', \'block\'); $(this).remove(); return false;';
                        $st = 'color:red; font-weight:bold; font-size:15px; border-bottom:1px dotted red';
                        echo '<a href="#" onclick="'.$onc.'" style="'.$st.'">'.$pfx.'</a>';
                    }
                    $class .= ' pfx_'.$pfx;
                }
                $pfxPrev = $pfx;
            }
            $add = '';
            if ($class) {
                $add = ' class="'.$class.'"';
            }
            echo '<a href="/'.EXP.'?action=tables&table='.$table.'"'.$add.getTableStyle($v->Rows).'>'.$table.'</a>';
        }
        if ($uniquePfx) {
            echo '<style type="text/css">';
            foreach ($uniquePfx as $k => $v) {
                echo '#qMenu a.pfx_'.$v.' {display:none;}';
            }
            echo '</style>';
        }
    }
    ?>
    </div>
    <?php
}

function getTableStyle($rows)
{
    $add = '';
    if ($rows == 0) {
        $add = 'color:#aaa';
    } elseif ($rows > 200000) {
        $add = 'font-weight:bold; color:red';
    } elseif ($rows > 100000) {
        $add = 'color:red';
    } elseif ($rows > 50000) {
        $add = 'font-weight:bold;';
    }
    if ($add) {
        $add = ' style="'.$add.'"';
    }
    return $add;
}

function printSessionTables()
{
    if (isset($_SESSION['tables'])) {
        $tables = getAllTables();
        if (!$tables) {
            return ;
        }
        echo '<b>Таблицы сессии:</b> <br />';
        asort($_SESSION['tables']);
        foreach ($_SESSION['tables'] as $table) {
            if (!array_key_exists($table, $tables)) {
                continue;
            }
            echo '<a href="/'.EXP.'?action=tables&table='.$table.'">'.$table.'</a>';
        }
        echo '<hr />';
    }
}

function getAppTitle()
{
    $acts = array(
        'tables' => $_SESSION['db_name'],
        'zip' => 'Архив',
        'upload' => 'Upload',
    );
    $title = 'exp';
    if ($_GET['table']) {
        $title = $_GET['table'];
        if ($_GET['mode'] == 'fields') {
            $title .= ' структура';
        }
    } elseif ($_GET['folder']) {
        $title = basename($_GET['folder']);
    } elseif (array_key_exists($_GET['action'], $acts)) {
        $title = $acts[$_GET['action']];
    } elseif ($_GET['action']) {
        $title = ucfirst($_GET['action']);
    }
    return $title;
}

function getTmpInfo()
{
    $tmpInfo = 'Указана темп-папка: '.TMP_DIR;
    if (file_exists(TMP_DIR) && is_dir(TMP_DIR)) {
        $tmpInfo .= ' - существует!';
        $tmpInfo .= ' Права: '.substr(decoct(fileperms(TMP_DIR)), 2, 4);
        $perm = substr(decoct(fileperms(TMP_DIR)), 2, 1);
        if (in_array($perm, array(2, 3, 7))) {
            $tmpInfo .= ' запись разрешена';
        } else {
            $tmpInfo .= ' запись запрещена!';
        }
    } else {
        $tmpInfo .= ' - отсутствует!';
    }
    return $tmpInfo;
}


// функции архивирования block

// Используется в архивировании папки. В архиве только сама папка, без родительских папок
function zipFilesFolder($zip, $dir, $files, &$addedAll, &$errorAll, $fromRoot = false)
{
    static $dirBase;
    if (!isset($dirBase)) {
        $dirBase = $dir;
    }
    $a = $files ? $files : scandir($dir);
    foreach ($a as $k => $v) {
        if ($v == '.' || $v == '..') {
            continue;
        }
        $path = $dir.'/'.$v;
        if (is_dir($path)) {
            zipFilesFolder($zip, $path, '', $addedAll, $errorAll, $fromRoot);
        } else {
            if ($fromRoot) {
                $local = $path;
            } else {
                $local = str_replace($dirBase.'/', '', $path);
            }
            $res = $zip->addFile($path, $local);
            if ($res) {
                $addedAll ++;
            } else {
                $errorAll ++;
            }
        }
    }
}

function scandirex($dir, $onlyField=false)
{
    $a = scandir($dir);
    $list = array();
    $sort_order = array();
    foreach ($a as $k => $v) {
        if ($v == '.' || $v == '..') {
            continue;
        }
        $isdir = is_dir($dir.'/'.$v);
        $list []= array(
            'name' => $v,
            'path' => $dir.'/'.$v,
            'dir' => $isdir,
            'size' => $isdir ? '' : filesize($dir.'/'.$v)
        );
        $sort_order[$k] = (!$isdir).'-'.$v;
    }
    array_multisort($sort_order, SORT_ASC, SORT_STRING, $list);
    if ($onlyField) {
        return array_column($list, $onlyField);
    }
    return $list;
}

// Сравнение таблиц
function compareDisplay(&$msg, $fields)
{

    $table1 = $_GET['table'];
    if (!$table1) {
        $msg = 'Нет table в $_GET запросе';
        return ;
    }
    $table2 = $_GET['table2'];
    if (!$table2) {
        $msg = 'Нет table2 в $_GET запросе';
        return ;
    }
    foreach ($fields as $k => $v) {
        if ($v->Key == 'PRI') {
            $primaryKey = array($v->Field);
        }
    }
    // $primaryKey = array('code', 'key');

    $data1 = getData('select * from '.$table1);
    $data1Grouped = array();
    $pks1 = array();
    foreach ($data1 as $k => $v) {
        $pkv = array();
        foreach ($primaryKey as $pk) {
            $pkv []= $v[$pk];
        }
        $pkv = implode(',', $pkv);
        $pks1 []= $pkv;
        $data1Grouped [$pkv] = $v;
    }

    $data2 = getData('select * from '.$table2);
    $data2Grouped = array();
    $pks2 = array();
    foreach ($data2 as $k => $v) {
        $pkv = array();
        foreach ($primaryKey as $pk) {
            $pkv []= $v[$pk];
        }
        $pkv = implode(',', $pkv);
        $pks2 []= $pkv;
        $data2Grouped [$pkv] = $v;
    }
    $pks = array_values(array_unique(array_merge($pks1, $pks2)));
    asort($pks);
    foreach ($fields as $k => $v) {
        if (!in_array($k, array('id', 'introtext', 'fulltext'))) {
            unset($fields[$k]);
        }
    }

    $rows = '<tr>';
    foreach ($fields as $f) {
        $rows .= '<th>'.$f->Field.'</th>';
    }
    $rows .= '</tr>';
    $trsDiff = 0;
    foreach ($pks as $pk) {
        $tds = '';
        $diffs = 0;
        foreach ($fields as $f) {
            if ($f->Field == $primaryKey) {
                $rows .= '<td>'.$pk.'</td>';
                continue;
            }
            $value1 = $data1Grouped[$pk][$f->Field];
            $value2 = $data2Grouped[$pk][$f->Field];
            if (!isset($data1Grouped[$pk][$f->Field])) {
                $t = $table2;
                $value = ' <span style="color:red" title="'.$t.'">'.$value2.'</span>
                <div style="color:#ccc; font-size:10px;">'.$t.'</div>';
                $diffs ++;
            } elseif (!isset($data2Grouped[$pk][$f->Field])) {
                $t = $table1;
                $value = ' <span style="color:red" title="'.$t.'">'.$value1.'</span>
                <div style="color:#ccc; font-size:10px;">'.$t.'</div>';
                $diffs ++;
            } else {
                if ($value1 == $value2) {
                    $value = '<span style="color:green">'.$value1.'</span>';
                } else {
                    $diffs ++;
                    $value = '
                    <span style="color:red; font-weight:bold;">
                        <span title="'.$table1.'">'.$value1.'</span><br />
                        <span title="'.$table2.'">'.$value2.'</span>
                    </span>';
                }
            }
            $tds .= '<td>'.$value.'</td>';
        }
        if (!$diffs) {
            continue;
        }
        $trsDiff ++;
        $rows .= '<tr>'.$tds.'</tr>';
    }
    if (!$trsDiff) {
        $msg = 'Все строки одинаковые!';
    }
    return $rows;
}

function compareData($t1, $t2, $field, $cut, &$msg)
{
    $data1 = getData('select * from `'.$t1.'`');
    $data2 = getData('select * from `'.$t2.'`');
    if (!$data1) {
        $msg = 'Ничего не найдено в '.$t1;
        return ;
    }
    if (!$data2) {
        $msg = 'Ничего не найдено в '.$t2;
        return ;
    }

    $dataAll = array();
    foreach ($data1 as $row) {
        foreach ($row as $k => $v) {
            if (!in_array($k, array($field))) {
                continue;
            }
            $dataAll [$row['id']][$k][$t1] = $v;
        }
    }
    foreach ($data2 as $row) {
        foreach ($row as $k => $v) {
            if (!in_array($k, array($field))) {
                continue;
            }
            $dataAll [$row['id']][$k][$t2] = $v;
        }
    }

    foreach ($dataAll as $id => $vals) {
        foreach ($vals as $field => $rows) {
            if (count(array_unique($rows)) == 1) {
                unset($dataAll[$id][$field]);
            }
        }
        if (!count($dataAll[$id])) {
            unset($dataAll[$id]);
        }
    }


    $output = '';
    foreach ($dataAll as $id => $vals) {
        $output .= '<h2 style="margin:0px;">'.$id.'</h2>';
        foreach ($vals as $field => $rows) {
            // $output .= '<br />'.$field;
            foreach ($rows as $table => $val) {
                $val = htmlspecialchars($val);
                if ($cut) {
                    $val = substr($val, 0, $cut).'...';
                }
                $output .= '<div>'.$table.' => '.$val.'</div>';
            }
        }
    }

    return $output;
}

function ajaxErrorHandler($errno, $errmsg, $filename, $linenum, $vars)
{
    if ($errno == 8) {
        return ;
    }
    addLog("$errno, $errmsg, $filename, $linenum $vars");
}

function ajaxShutdown()
{
    addLog('Финиш');
}

function templateLayout($tables, $database, $pageContent='') {
    global $pager, $mysqli;
    ?><!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo getAppTitle() ?></title>

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-giJF6kkoqNQ00vy+HMDP7azOuL0xtbfIcaT9wjKHr8RbDVddVHyTfAAsrekwKmP1" crossorigin="anonymous">

<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.4/jquery.min.js"></script>
<?php ?><script type="text/javascript">
window.onerror = function (text, file, line) {
    if (document.getElementById('errorBlock') == null) {
	    console.error(text+' '+file+':'+line);
        return ;
    }
    document.getElementById('errorBlock').innerHTML += text+' '+file+':'+line + '<br />';
}

// Общие функции
function formatSize($bytes) {
    if ($bytes < 1024) {
        return $bytes+" b";
    } else if ($bytes < Math.pow(1024, 2)) {
        return ($bytes / 1024).toFixed(2)+' Kb';
    } else if ($bytes < Math.pow(1024, 3)) {
        return ($bytes / Math.pow(1024, 2)).toFixed(2)+' Mb';
    } else if ($bytes < Math.pow(1024, 4)) {
        return ($bytes / Math.pow(1024, 3)).toFixed(2)+' Gb';
    }
}
// cook.set('folders', n, 365, '/')
cook = {
    //
	set:function(name, value, expires, path, domain, secure) {
		expl=new Date();
        if (expires == undefined) {
        	expires = 30;
        }
		expires=expl.getTime() + (expires*24*60*60*1000);
		expl.setTime(expires);
		expires=expl.toGMTString();
		var curCookie = name + "=" + escape(value) +
		((expires) ? "; expires=" + expires: "") +
		((path) ? "; path=" + path : "") +
		((domain) ? "; domain=" + domain : "") +
		((secure) ? "; secure" : "")
		document.cookie = curCookie;
        return cook.get(name) == value;
	},
	get:function(name) {
		var prefix = name + "=";
		var cookieStartIndex = document.cookie.indexOf(prefix);
		if (cookieStartIndex == -1)
				return false
		var cookieEndIndex = document.cookie.indexOf(";", cookieStartIndex + prefix.length);
		if (cookieEndIndex == -1)
				cookieEndIndex = document.cookie.length;
		return unescape(document.cookie.substring(cookieStartIndex + prefix.length, cookieEndIndex))
	}
}
// dev переключатель
function devRefresh()
{
    var dev = cook.get('dev')
    if (typeof(dev) != 'undefined' && dev) {
        $('#dev').addClass('active')
    } else {
        $('#dev').removeClass('active')
    }
}
function devToggle()
{
    var dev = cook.get('dev')
    if (typeof(dev) != 'undefined' && dev) {
        cook.set('dev', 0, 0, '/');
    } else {
        cook.set('dev', 1, 365, '/');
    }
    devRefresh()
}

// Топ меню
function ns(obj)
{
    obj.nextElementSibling.style.display = 'block';
    return false;
}
function openMenu()
{
    document.getElementById('qMenu').style.display='block';
}
function hideMenu()
{
    document.getElementById('qMenu').style.display='none';
}
function switchSql(force)
{
    if (typeof(force) == 'undefined') {
    	force = ''
    }
    var s = document.getElementById('sql-quick');
    if (s.style.display == 'block' || force == 'hide') {
        s.style.display = 'none';
        document.getElementById('overlay').style.display = 'none'
    } else {
        s.style.display = 'block';
        s.getElementsByTagName('TEXTAREA')[0].focus()
        document.getElementById('overlay').style.display = 'block'
    }
}
function checkPsevdo(chbx, hidden)
{
    hidden.value = chbx.checked ? 1 : '';
}
var ctrlKey;
$(document).ready(function(){
    $(document).ajaxError(function(event, jqxhr, settings, thrownError) {
        document.getElementById('errorBlock').innerHTML += 'Ajax error '+settings.url + '<br />';
    });
    $(document).ajaxStart(function() {
        $('#loader').show();
    });
    $(document).ajaxStop(function() {
        $('#loader').hide();
    });
    /*$('.autofocus').focus(function() {
        $(this).select()
    })*/
    $('.focusselect').focus(function() {
        $(this).select()
    })
    $('.autoselect').each(function() {
        $(this).select()
    })
    $('.nsh').click(function() {
        $(this).next().toggle();
        if ($(this).hasClass('ns-hide')) {
        	$(this).hide()
        }
        return false;
    })
    $(window).on('keydown', function(event) {
        ctrlKey = event.ctrlKey;
        if (ctrlKey) {
        	$('body').addClass('ctrlKey')
        }
        if (event.which == 27) {
        	switchSql('hide')
            $('#overlay, #edit-file').hide();
        }
    })
    $(window).on('keyup', function(event) {
        ctrlKey = event.ctrlKey;
        if (!ctrlKey) {
            $('body').removeClass('ctrlKey')
        }
    })
    $('#sql-quick [type=submit]').click(function(e) {
        $(this).next().show()
        $('#value-row, #value-row-alt').attr('name', '')
        var id = $('#value-row').val() ? '#value-row' : '#value-row-alt';
        $(id).attr('name', this.name != '' ? this.name : this.value)
        setTimeout(function(x) {
            x.disabled = true;
        }, 100, this);
        setTimeout(function(x) {
            x.disabled = false;
        }, 1000, this);
    })
});
function changeAi(ai)
{
    var ai = prompt('Введите новое значение AI', ai);
    if (!ai) {
        return false;
    }
    location = '/<?=EXP?>?action=tables&table=<?=$_GET['table']?>&ai='+ai
}
// аяксоквый сабмит формы с алертом результата
function ajaxSubmit(form)
{
    if ($('textarea[name=sql-exec]').val()) {
        return true;
    }
    $.post('', $(form).serialize(), function(data) {
        if (data != '') {
            $('#ajaxResults').show().html(data)
        }
    });
    return false;
}
// просто аякс запрос по урлу
// obj элемент который надо спрятать после выполнения
/*
- в декоде / сериализации в форме редактирования
- dropkey в ключах. удаление строки в таблице
*/
function jsquery(q, opts)
{
    if (opts.table) {
    	var table = opts.table;
    } else {
        if (window.location.search) {
        	var table = new URLSearchParams(window.location.search).get('table');
        }
    }
    $.ajax({
        url : '/<?=EXP?>?table='+table,
        type : 'POST',
        data : q,
        success : function(data) {
            if (data.indexOf('eval:') === 0) {
                eval(data.substr(5))
                return ;
            }
            if (data != '' && data.indexOf('<') !== 0) {
                alert(data)
            }
            if (typeof(opts.hide) != 'undefined') {
                $(opts.hide).hide()
            }
        }
    });
}

// Поиск
function chooseFolders()
{
    var folders = '<?=$_COOKIE['folders']?>';
    var n = prompt('Список папок для поиска через ,', folders);
    if (n !== false && n != null) {
        cook.set('folders', n, 365, '/');
        document.getElementById('foldersInput').value = n
    }
}
function changeRoot()
{
    let folders = '<?=$_COOKIE['folder_root'] ? $_COOKIE['folder_root'] : getcwd()?>';
    let n = prompt('Выбрать корень поиска', folders);
    if (n !== false && n != null) {
        cook.set('folder_root', n, 365, '/')
    }
}

// Создание таблицы
function addNewField()
{
    var el = $('#createTable').append($('#firstField').html())
    $('#createTable').children().last().find('input').val('')
}

// Страница поиска
Search = {
    filterHtmlRows(input) {
        let include = document.getElementById('filter-search-include').value;
        let exclude = document.getElementById('filter-search-exclude').value;
        if (include.length < 3) {
            include = '';
        }
        if (exclude.length < 3) {
            exclude = '';
        }
        if (!include && !exclude) {
            $('.fl, [data-ext').prop('hidden', false)
            return ;
        }
        $('.fl').each(function() {
            if (include && !this.innerHTML.match(new RegExp('('+include+')', 'gi'))) {
                this.hidden = true
            }
            if (exclude && this.innerHTML.match(new RegExp('('+exclude+')', 'gi'))) {
                this.hidden = true
            }
        })
        $('[data-ext]').each(function() {
            if (!$(this).find('.fl:not([hidden])').length) {
                this.hidden = true;
            } else {
                this.hidden = false;
            }
        })
    },
    init() {
        // Заполнить селект выбора расширений
        let exts = []
        $('[data-ext]').each(function() {
            let ext = this.dataset.ext;
            if (ext && exts.indexOf(ext) < 0) {
                exts.push(ext)
                $('select[name="exts"]').append('<option>'+ext+'</option>')
            }
        })
        $('select[name="exts"]').change(function() {
            let ext = $(this).val()
            if (ext) {
                $('[data-ext]').hide()
                $('[data-ext="'+ext+'"]').show()
            } else {
                $('[data-ext').show()
            }
        })
    }
}

// Размеры папок
class SizeFolders {

    constructor(root) {
        this.root = root
        let max = 20;
        this.formatTotalSize()
        $('.calcSize').each(function(index, obj) {
            if (max > 0) {
                var folder = $(obj).addClass('calced').attr('data-folder');
                this.calcSize(folder)
            }
            max --;
        }.bind(this))
    }

    nextCalc() {
        let el = $('.calcSize:not(.calced)').first();
        if (el.length) {
            let folder = el.attr('data-folder');
            el.addClass('calced');
            this.calcSize(folder)
        }
    }

    formatTotalSize(add)
    {
        let total = document.getElementById('total')
        if (typeof(add) != 'undefined') {
            //console.log(`${Number(total.title)} + ${add}`)
            total.title = add + Number(total.title)
        }
        total.innerHTML = formatSize(total.title);
    }

    colorSize(size='#ccc') {
        if (size > 1024*1024*10) {
            var color = 'red';
        } else if (size > 1024*1024*1) {
            var color = 'blue';
        }
        return color;
    }

    calcSize(folder)
    {
        $.get('/<?=EXP?>?action=sizeFolders&root='+this.root+'&folder='+folder, function(data) {
            this.nextCalc()
            let span = $('span[data-folder="'+folder+'"]');
            if (!data.startsWith('{')) {
                var r = 2 + Number((Math.random() * 8).toFixed());
                span.html('error, reload '+r+' sec')
                setTimeout(this.calcSize, r * 1000, folder);
                return ;
            }
            data = JSON.parse(data);
            let color = this.colorSize(data.size);
            this.formatTotalSize(data.size)
            span.html(formatSize(data.size)).css('color', color).attr('data-size', data.size)
            span.parent().after('<td style="text-align: right">'+data.count+'</td>')
        }.bind(this));
    }

    bigFolders()
    {
        let files = [];
        let sizeLimit = parseInt($('#sizeLimit').val() * 1024 * 1024)
        $('[data-size]').each(function() {
            let size = this.dataset.size;
            if (size > sizeLimit) {
                files.push($.trim($(this).closest('tr').find('td').first().text()))
            }
        })
        $('#bigFolders').val(files.join(','));
    }
}

// Страница просмотр данных
class TableData {
    constructor() {
        $('#filter-form .act').click(function() {
            $('#filter-form').attr('action', this.href).submit()
        })
        $('.optionstable').delegate('td', 'click', function() {
            $(this).css('white-space', 'normal')
        })
        $('.optionstable').delegate('[data-delete]', 'click', function() {
            if (document.confirmed || confirm('Подтвердите удаление')) {
                jsquery('tmode=delete&where='+this.dataset.delete, {hide: this.parentNode.parentNode});
                document.confirmed = 1
            };
            return false;
        })
        if ($('[name="where"]').val()) {
            this.openWhere();
        }
        if ($('#filter_id_id').val()) {
            this.openId();
        }
        $('#whereField').on('focus', this.openWhere)
        $('#filter_id_id').on('focus', this.openId)
        console.log('init');
    }
    openWhere() {
        $('#whereField').addClass('wideBlock')
        $('#whereField').parent().css('display', 'block').show()
    }
    openId() {
        $('#filter_id_id').css('width', 100)
    }
}

// Страница Экспорта
class Export {
    constructor() {
        $('[name="action"]').change(function() {
            $('.export-col, .import-col').addClass('visually-hidden')
            $('.'+$(this).val()+'-col').removeClass('visually-hidden')
        })
        $('#execButton').click(this.startLoading)
    }
    log(t)
    {
        var now = new Date();
        $('#exportLogInfo').prepend(`<div><span style="color:#aaa">${now.toLocaleTimeString()}</span> ${t}</div>`)
    }
    testStepByStep(table, offset)
    {
        var q = $('#loadingForm').serialize();
        q += '&steps-export=1';
        if (typeof(table) != 'undefined') {
            q += '&table='+table
        }
        if (typeof(offset) != 'undefined') {
            q += '&offset='+offset
        }

        $.post('', q, function(data) {
            data = data.split(');');
            for (let s of data) {
                if (s.startsWith('<')) {
                    Export.log('<div style="color:red">'+s+'</div>')
                } else if (s != '') {
                    e = s+');'; // т.к. сверху split );
                    eval(e);
                    // console.log(`eval(${e})`)
                }
            }
            return false;
        });
    }
    startLoading()
    {
        let btn = document.getElementById('execButton')
        if ($('select[name="ex_type"]').val() == 'steps') {
            this.testStepByStep();
            return ;
        }

        $('#loggerBlock').show()
        $('#resultsError, #results').hide()

        btn.disabled = true;

        var q = $('#loadingForm').serialize();
        if (q == '') {
            alert('Пустой запрос из формы');
            return ;
        }

        $.post('', q + '&ajax=1', (data) => {
            $('#resultSuccess').show().html(`<h2>Запрос выполнен!</h2>`)
            if (data.startsWith('<!>')) {
                eval(data.substr(3));
            } else {
                if (data.length < 500 && data.match(/on line/)) {
                    $('#resultsError').show().html(data)
                } else {
                    $('#results').show().html(data)
                }
            }
            setTimeout(() => {
                clearInterval(this.intervalLoading);
                btn.disabled = false;
                $('#logEvent').html('disabled')
            }, 3000);
            return false;
        });

        this.stopUpdate = false;
        this.intervalLoading = setInterval(() => {
            if (this.stopUpdate) {
                $('#logEvent').html('stopUpdate')
                return ;
            }
            if (document.getElementById('logger') == null) {
                clearInterval(this.intervalLoading);
                $('#logEvent').html('cleared')
                return ;
            }
            document.getElementById('logger').src = '/<?=LOG_FILE?>?' + Math.random();
        }, 3000);
    }
}
// Нижние функции аплоада - все для экспорта
// uploadFile({file: '#fileField', show: '#fileFieldLoader', disable: '#execButton', callback: uploadFileAfter})
function uploadFile(opts)
{
    if (typeof(opts.disable) != 'undefined') {
        $(opts.disable).attr('disabled', true)
    }
    if ($(opts.file)[0].size == 0) {
        alert('Файл '+$(opts.file)[0].name+' пустой');
    }
    let formData = new FormData();
    formData.append($(opts.file)[0].name, $(opts.file)[0].files[0]);
    if (typeof(opts.show) != 'undefined') {
        $(opts.show).show()
    }
    let query = '?';
    if (typeof(opts.deleteExist) != 'undefined' && opts.deleteExist) {
        query += '&deleteExist=1'
    }
    if (typeof(opts.filename) != 'undefined') {
        query += '&filename='+opts.filename
    }
    $.ajax({
        url : '/<?=EXP?>'+query,
        type : 'POST',
        data : formData,
        processData: false,
        contentType: false,
        success : function(data) {
            $(opts.file).val('')
            if (typeof(opts.disable) != 'undefined') {
                $(opts.disable).attr('disabled', false)
            }
            if (data) {
                if (data.indexOf('{') === 0) {
                    data = JSON.parse(data)
                }
            }
            if (typeof(opts.callback) != 'undefined') {
                opts.callback(data)
            }
        }
    });
    return false;
}
function importFileUpload()
{
    uploadFile({file: '#fileField', deleteExist: true, filename: 'temp', show: '#fileFieldLoader', disable: '#execButton', callback: uploadFileAfter})
}
function uploadFileAfter(data)
{
    if (data.file) {
        $('#fileFieldLoader').html('файл загружен как "'+data.file+'" <a href="#" onclick="uploadFileRemove(); return false;">удалить</a>').css('color', 'green');
    } else if (data.error) {
        $('#fileFieldLoader').html('<span style="color:red">'+data.error+'</span>')
    } else {
        $('#fileFieldLoader').html('<span style="color:red">'+data+'</span>')
    }
}
function uploadFileRemove()
{
    $.post('', 'uploadfileremove=1', function(data) {
        $('#fileFieldLoader').html('')
    });
}

$(document).ready(function(){
    devRefresh()
    // Страница поиска
    if ($('#deleteInfo').length) {
        Search.init()
    }
    // Форма редактирования строки - processEdit
    if ($('#edit-fields-form').length) {
        $('input[type=text]').keyup(function() {
            var nulled = $('input[name="nulled['+$(this).attr('name')+']"]');
            $(nulled).prop('checked', this.value.length == 0 ? 'checked' : '')
        })
        // функция для обработки ссылок json_encode decode serialize на textarea инпутах в tmode=edit
        function fieldAct(obj, act)
        {
            jsquery(act+'='+encodeURIComponent($(obj).parent().prev().val()), {hide: obj});
            return false;
        }
    }
    if ($('#size-folders').length) {
        SizeFolders = new SizeFolders($('#size-folders').data('root'));
    }
    if ($('#whereField').length) {
        new TableData()
    }
    if ($('#exportLogInfo').length) {
        new Export
    }
})
</script>
<?php ?>
<?php ?><style>
body {font-family:Arial}
a, a:visited {text-decoration:none}
/*div.title {background-color:#eee; padding:5px; margin-top:20px;}
div.title:first-child {margin-top:0px;}*/
#hh {font-size:26px; color:#6D8FB3; white-space:nowrap; }
#qMenu {position:absolute; background-color:white; font-size:11px; line-height:125%; border:3px dashed black; padding:3px;
    top:25px; left:9px; display:none; background-color:azure; max-height: 600px; overflow-y: auto; overflow-x: hidden; z-index:1;}
#qMenu a, .tblMenu a {display:block;}
#qMenu a:hover, .tblMenu a:hover {background-color:yellow;}
#qMenu a.active {font-weight:bold; background-color:#66CCFF;}
.nsh:not(.ns-opened) + * {display:none;}
.msg {border:1px solid blue; padding:10px; display:inline-block; margin:10px 0; max-height:100px; overflow:auto; font-size: 12px;}
.myform input[type=text], .myform input[type=number], .myform textarea {border:1px solid #ccc}

.tblMenu {background-color:#eee; height:650px; overflow:auto; font-size:11px}
#sql-quick, #edit-file {position:absolute; top:50px; left:0; right:0; background-color:white; display:none; padding: 10px;
    border: 1px solid #ccc; margin: 5px; z-index:4; font-size:12px;}
#sql-quick textarea, #edit-file textarea {width:99%; height:200px; padding:5px; font-family:Arial; font-size:12px; display:block; margin-top:10px;}

.col-2 {width:50%; display:inline-block; float:left;}
.row:after {clear:both; display: table; content: " ";}

H2 {margin:10px 0; font-size:20px;}
table.optionstable {empty-cells:show; border-collapse:collapse; -margin:10px;}
table.optionstable th {background-color: #eee}
table.optionstable th, table.optionstable td {border:1px solid #E0E0E0; padding: 3px; vertical-align: top;font-size:13px;}
table.optionstable a {text-decoration:none;}
table.optionstable a:hover {text-decoration:underline;}
table.right td { text-align:right;}

/*.tmenu {font-size:11px;}
.tmenu a, .tmenu b {color:#aaa; margin-right:5px;}*/
#overlay {
    z-index: 3;
    position: fixed;
    background-color: #000;
    opacity: 0.8;
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    cursor: pointer;
    display: none;
}
.focusselect {border:1px solid #eee}
.mini {font-size:12px; color:#aaa}
#loader {
    background: white url('http://komu.info/images/upload_inv_mono.gif') center center no-repeat;
    width: 64px;
    height: 16px;
    position: fixed;
    top: 0;
    left: 0;
    padding: 10px;
    border: 1px solid #eee;
    display:none;
}
.phpinfo {display: none;position: absolute;background-color: white;z-index: 20;border: 1px solid rgb(204, 204, 204); padding: 5px;left: 5px;top: 5px;}
#dev.active {color:green; font-weight:bold}

/* Форма поиска */
.srch {font-size:12px;}
.srch input[type=text]:not(.q) {padding: 0px 2px;}
.srch input[type=checkbox] {vertical-align: -2px;}
.srch.top { background-color: white; padding: 5px; border: 1px solid #eee; top: 0px;}
.fl {margin-left:20px; font-size:12px; color:#aaa}
@media (min-width: 576px) {
    .srch.top {position:absolute; right:0; margin-left:10px; z-index:1;}
}
@media (max-width: 575px) {
    .srch.top {margin:10px 0}
}
textarea[name="folders"] {height: 23px; vertical-align: middle;width: 200px; }
textarea[name="folders"]:focus {display: block; width: calc(100% - 10px); height: 200px; margin-bottom: 5px; outline: none;}

/* Постраничная */
div.pages a {background-color:#FFFFCC; border:1px solid #ccc; padding:2px 5px; text-decoration:none;}
div.pages {line-height:200%; margin-bottom:5px; display:inline-block; font-size:12px;}

/* Просмотр данных */
#filter-form {margin-bottom:5px; font-size:12px; display:block;}
#filter-form select {padding:2px;}
#filter-form .wideBlock {width:700px;}
table.data a {font-size:12px;}
table.data td.s {background-color:#F8F8F8;}
table.data .space {background-color:red; width:5px; display:inline-block;}
table.data.shortheaders tr:not(.normal) td {white-space: nowrap; max-width: 125px; overflow: hidden;}

/* Размеры папок */
.cell {min-width:250px; display: flex;}
.cell a {width:100px; white-space: nowrap;}
.cell span {width:100px; text-align:right;}

/* Архивирование */
form.archive {line-height:160%;}
form.archive input[type=text] {width:300px;}
form.archive div.flx {margin-bottom:5px; display:flex; max-width:1000px;}
form.archive div.flx label {width:400px;}
form.archive div.flx input {width:100%;}
div.selects {max-height:300px; overflow:auto; display:inline-block; min-width:200px;}
div.selects ul {list-style:none; margin:0px; padding:0px;}
div.selects ul ul {display:none; padding-left:10px;}
div.selects ul .dir > label {font-weight:bold;}
div.selects ul span {margin-left:10px; color:#aaa; cursor: pointer; font-weight:normal;}

/* Аплоад файлов */
.fileUpload {padding:20px; border:1px solid #aaa}
.fileUpload.dragover {border: 2px solid red;}
.fileUpload.drop {border: 1px solid green;}
body.ctrlKey .fileUpload {border:5px dotted red}
.folders-simple {max-height:400px; overflow:auto; }
.file-hover a:hover, .hv:hover {color:red; cursor: pointer;}
.file-hover .folder a {color:green;}
.file-hover .folder a:hover {color:red}
.fileUpload .file {font-size:12px;}
.hv span {display:none; margin-left:5px; font-size:12px; color:red}
.hv:hover span {display:inline;}
.hv span a {color:red}
.link.active {font-weight:bold;}
.fileUpload.innerList {overflow:auto}
#edit-file textarea {resize: vertical; font-family: Anonymous Pro; font-size:13px; margin-top:10px; margin-bottom:0px; height:500px;}

/* Таблица файлов */
.folders-table td, .folders-table th {font-size:12px; padding-right:15px}
.folders-table td:first-child {font-size:14px; min-width:200px;}
.folders-table td:nth-child(2) {text-align:right;}

/* Блок превью в upload */
.previewBlock {position:fixed; right:0; top:0; min-width:250px; text-align:right; font-size:12px; display:none;}
.previewBlock input {display:block; width:95%; margin-bottom:5px;}
.previewBlock #previewImg { max-width: 250px; max-height:200px}
.previewBlock #fileinfo {padding:5px;}

/* Мини-формы действия в upload */
.fform {margin-top:10px; font-size:12px;}
.fform input[name=folder] {width:300px;}

.top_line > a {margin-right:10px;}

.table.small {width: auto}
.table.small>:not(caption)>*>* {padding: .1rem .1rem;}

a.badge:hover {color: white; opacity: 0.5}
.form-max { max-width: 910px}

.fixed-bottom-block {position: fixed; left: 0; bottom: 0; right: 0; text-align: center; padding: 10px; background: white;
        border-top: 1px solid #ccc; box-shadow: 0px 4px 12px 0px rgba(50, 50, 50, 0.75);}
</style>
<?php ?>
</head>

<body>
    <div id="loader"></div>
    <div id="overlay"></div>


    <?php printQMenu($tables); ?>

    <form method="post" id="sql-quick" action="<?php echo EXP ?>?action=sql-quick" onsubmit="return ajaxSubmit(this)">
        <input type="hidden" name="sql-quick" value="1">
        <input type="submit" name="sql-exec" value="Выполнить SQL" />
        <input onclick="switchSql()" type="button" value="Отмена">
        <!-- <input type="submit" name="quick-json" value="JSON decode" />
        <input type="submit" name="quick-unserialize" value="Unserialize" />
        <input type="submit" name="php-exec" value="PHP exec" /> -->
        <textarea id="value-row" placeholder="Sql или другое"></textarea>

        <div style="margin-top:5px;">
            <input type="submit" value="md5" />
            <input type="submit" name="tms" value="timestamp / date" />
            <input type="submit" name="loadurl" value="url load" />
            <input type="submit" name="http" value="url status" />
            <span style="display:none;">
                <input type="text" name="http_login" style="width:100px;" placeholder="" />
                <input type="text" name="http_pass" style="width:100px;" placeholder="" />
            </span>
            <input type="submit" name="check-email" value="email (test)" />
            <input type="submit" value="translit" />
            <?php
            if (function_exists('idn_to_ascii')) {
                echo '<input type="submit" value="punicode" /> ';
            }
            ?>
            <input type="submit" name="passw" value="password" onclick="$(this).next().show()" />
            <span style="display:none">
                <input type="text" style="width:30px;" name="length" value="10">
                <label><input type="checkbox" checked name="caps" value="1"> caps</label>
                <label><input type="text" name="password-symbols" value="<?=htmlspecialchars('!_@#$%^&*')?>" style="width: 200px"> </label>
            </span>
            <input type="text" id="value-row-alt" name="exp-value" style="width:300px; margin-left:10px;" placeholder="Альтернативное поле ввода" />
        </div>
        <?php sessionSqls() ?>
        <div style="margin-top:5px; display:none; max-height:300px; overflow:auto" id="ajaxResults"></div>
    </form>


<div class="container-fluid">
    <div class="row">
        <div id="hh" class="col-auto">
            <a id="header" onmouseover="openMenu()" onmouseout="hideMenu();" href="<?=$_SERVER['PHP_SELF']?>">Site</a>
            <a href="#" style="color:green" onclick="switchSql(); return false;">manager <?=MS_APP_VERSION?></a>
        </div>
        <div class="col-auto">

            <div style="font-size:12px; color:#666">
                <span style="font-size:12px;"><?php if ($database) { ?> База <b><?=$database?></b> <a href='?action=logoutdb'>другая</a> <?php } ?>
                <a href="?action=logout">выйти</a> &nbsp;&nbsp;</span>
                <span style="color:#ccc">max_time</span> <b><?php echo ini_get('max_execution_time')?></b>
                <span style="color:#ccc">limit</span> <b><?php echo ini_get('memory_limit')?></b>
                <span style="color:#ccc">disk</span> <b title="Свободно <?=formatSize(disk_free_space('.'))?> из
                    <?=formatSize(disk_total_space('.'))?>"><?php echo formatSize(disk_free_space('.')) ?></b>
                <span title="<?=getTmpInfo()?>"><span style="color:#ccc">tmp</span> <b><?=TMP_DIR?></b></span>
                <a href="?phpinfo" style="margin-left:10px;">phpinfo</a>
               <?php $a = explode('.', phpversion()); ?>
                <a href="#" onclick="$(this).next().show(); this.remove(); return false;">php <?=$a[0].'.'.$a[1]?> mysql</a><span class="phpinfo">
                <span style="color:#ccc">php </span><?php echo phpversion()?>
                <span style="color:#ccc">mysql </span><?php echo getServerVersion()?>
                <?php
                if (isset($server)) {
                    echo "  - connected to $server as $user. ";
                }
                echo ' ip:'.$_SERVER['REMOTE_ADDR'];
                echo ' server:'.$_SERVER['SERVER_ADDR'];
                ?>
                </span>
                <?php
                if ($_SERVER['HTTP_HOST'] != 'komu.info') {
                    $add = '';
                    if (time() - filemtime(__FILE__) > 86400*30) {
                    	$add = ' style="color:red" title="Дата exp '.date('Y-m-d H:i:s', filemtime(__FILE__)).'"';
                    }
                	echo ' <a href="?update"'.$add.'>upd</a> ';
                }
                ?>
                <a href="#" id="dev" onclick="devToggle(); return false;">dev</a>
                &nbsp;
            </div>

            <div class="top_line">
                <?php if ($mysqli) { ?>
                <a href="<?=$_SERVER['PHP_SELF']?>">Экспорт</a>
                <a href="?action=tables">Таблицы</a>
                <?php } else { ?>
                <a href="?action=login">Вход в базу</a>
                <?php } ?>
                <a href="?action=zip">Архив</a>
                <a href="?action=upload">Upload</a>
                <a href="?action=utils">Utils</a>
                <?php $pager->printMenus(); ?>

                <br />

                <!--
                search form
                -->
            </div>

            <form method="post" class="srch top" action="/<?=EXP?>?action=search">
                <input onfocus="this.select(); this.nextSibling.style.display='inline'" autocomplete="off" type="text" name="search" placeholder="Поиск" class="q"
                    onkeydown="if (event.which == 13) return false;" style="width:200px"
                    value="<?=htmlspecialchars(trim($_POST['search']))?>" /><span style="<?=$_POST['search']?'':' display:none;'?>">
                <?php if ($mysqli) { ?>
                <input type="submit" value="Найти" />
                <input type="submit" name="fieldSearch" value="Поля" /> <?php } ?>
                <input type="submit" name="fileSearch" value="Файлы" />
                <?php
                $folders = Search::getFolders();
                ?>
                <div style="margin-top:5px;">
                <textarea type="text" name="folders" id="foldersInput" placeholder="По папкам"  /><?=implode(',', $folders)?></textarea>
                <label onclick="$(this).prev().val('*'); $('[name=maxFiles]').val(1000000);  $('[name=depth]').val(20); $('input[name=html-only]').attr('checked', false); $('input[name=no-case]').attr('checked', true)"><input type="checkbox" name="folders-all" <?=$_POST['folders-all']?'checked':''?> value=""> все</label>
                <a href="#" style="color:<?=$_COOKIE['folders'] ? ' black' : '#aaa'; ?>;" title="<?=$_COOKIE['folders'] ? $_COOKIE['folders'] : 'Не выбрано'?>" onclick="chooseFolders(); return false;">изм</a>
                <a href="#" onclick="cook.set('folders', '', 0, '/'); location=location; return false;">сброс</a>
                <?php
                $root = $_COOKIE['folder_root'] ? $_COOKIE['folder_root'] : getcwd();
                ?>
                <a href="#" onclick="changeRoot(); return false;" title="Выбрать корень поиска. Сейчас <?=$root?>">root</a><span style="color:#ccc">=<?=basename($root)?></span>
                Предел <input type="text" name="maxFiles" value="<?=POST('maxFiles', 50000)?>" style="width:50px;" />

                </div>
                <div style="margin-top:5px;">
                сниппет <input type="text" name="snipsize" value="<?=POST('snipsize', 40)?>" style="width:30px;" />
                глубина <input type="text" name="depth" value="<?=POST('depth', 20)?>" style="width:30px;" />
                <label><input type="checkbox" name="regexp" <?=$_POST['regexp']?'checked':''?> value="1"> regexp</label>
                <label><input type="checkbox" name="no-case" <?=$_POST['no-case']?'checked':''?> value="1"> не учитывать регистр</label>
                <label title="<?=implode(',', Search::$onlyExtensionList)?>"><input type="checkbox" name="html-only" <?=$_POST && !$_POST['html-only']?'':'checked'?> value="1"> только txt</label>
                </div>
                <div style="margin-top:5px;">
                <label><input type="checkbox" <?=$_POST['find_changed']?'checked':''?> name="find_changed" onclick="$('input[name=html-only]').attr('checked', false)" value="1">
                поиск измененных с</label> <input type="text" name="find_date" value="<?=$_POST['find_date'] ? $_POST['find_date'] : date('Y-m-d 00:00:00')?>" />
                <label><input type="checkbox" name="changed_archive" value="1"> создать архив</label>
                </div>
                <div style="margin-top:5px;">
                <label><input type="checkbox" name="search_log" checked value="1"> вести лог в <a href="<?=LOG_FILE?>"><?=LOG_FILE?></a></label>
                </div>
                </span>
            </form>

        </div>
    </div>
</div>






<div class="container-fluid">

    <div id="errorBlock" class="alert alert-danger mt-1" style="max-height: 200px; overflow: auto; <?=$pager->errors ? '' : 'display:none'?>">
    <?php
    if ($pager->errors) {
        foreach ($pager->errors as $k => $v) {
            echo '<div>'.$v.'</div>';
        }
    }
    ?>
    </div>

    <div id="successBlock" class="alert alert-success mt-1" style="max-height: 200px; overflow: auto; <?=$pager->messages ? '' : 'display:none'?>">
        <?php
        foreach ($pager->messages as $k => $v) {
            list($msg, $error, $sql) = $v;
            echo $msg;
            if ($error) {
                echo "<br /><span style='color:red; font-size:10px;'>$error</span>";
            }
            if ($sql) {
                echo '<pre>'.$sql.'</pre>';
            }
            echo '<hr>';
        }
        ?>
    </div>


	<?php
    echo $pageContent;
    // Поиск по базе и файлам section
    if (($_POST['search'] || $_POST['find_changed']) && !$_GET['tmode']) {
        Search::process();
    }
    ?>
</div>

<?php
}

changeroot();

ini_set('memory_limit', '2048M');
ini_set('max_execution_time', 180);
ini_set('upload_max_filesize', '100M');
ini_set('post_max_size', '100M');
ini_set('max_file_uploads', 100);
ini_set('display_errors', 'on');
ini_set('short_open_tag', 'on');

define('MS_APP_VERSION', '1.36');
$a = parse_url($_SERVER['REQUEST_URI']);
define('EXP', substr($a['path'], 1));
define('TMP_DIR', findTempDir());
define('LOG_FILE', TMP_DIR.'/temp.html');
define('MS_APP_NAME', 'Site Manager');

if (file_exists(LOG_FILE) && time() - filectime(LOG_FILE) > 300) {
    unlink(LOG_FILE);
}
if (file_exists($fn = TMP_DIR.'/exp.txt')) {
    unlink($fn);
}

global $memory_limit;
$memory_limit = (intval(ini_get('memory_limit'))*1024*1024)/2;

if (ini_get('magic_quotes_gpc') == '1') {
    $_POST = stripslashesRecursive($_POST);
}

if (array_key_exists('get', $_GET)) {
    header('Content-Type: text/plain; charset=utf-8');
    echo file_get_contents(__FILE__);
    exit;
}

// через pager передаются ошибки и собщения в шаблон
$pager = new Pager();
$pager->onStart();
$pageContent = '';

session_start();

if ($_SERVER['REMOTE_ADDR'] == '127.0.0.1') {
    // Обработка ошибок
    set_error_handler(function ($errno, $errmsg, $filename, $linenum, $vars) {
        global $errorsPhp;
        switch ($errno) {
            case E_NOTICE: case E_USER_NOTICE: $error = 'Notice'; break;
            case E_WARNING: case E_USER_WARNING: $error = 'Warning'; break;
            case E_ERROR: case E_USER_ERROR: $error = 'Fatal Error'; break;
            default: $error = 'Unknown'; break;
        }
        if (strpos($errmsg, 'Undefined index') !== false) {
            return;
        }
        if (strpos($errmsg, 'Server sent charset') !== false) {
            return;
        }
        $errorsPhp []= "<div style='color:red'>[$error] $errmsg, $filename, $linenum</div>";
    });
    if ($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') {
        error_reporting(E_ALL);
        function test()
        {
            global $errorsPhp;
            if (!$errorsPhp) {
                return ;
            }
            echo '<div class="container-fluid" style="max-height:400px; overflow:auto">'.implode('', array_unique($errorsPhp)).'</div>';
        }
        register_shutdown_function('test');
    }
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
}

// Логаут из базы
if (GET('action') == 'logout') {
    foreach (array('db_name', 'db_user', 'db_server', 'db_pass') as $k => $v) {
        $_SESSION[$v] = '';
        $_COOKIE[$v] = '';
        setcookie($v, false, 0, '/');
    }
    $_SESSION['loggedByCode'] = '';
}
if (GET('action') == 'logoutdb') {
    $_SESSION['db_name'] = $_COOKIE['db_name'] = '';
}

$code = 'ex.OKEFZyTWnw';
$loggedByCode = $_SESSION['loggedByCode'];
if ($_POST['login_code'] && $code == crypt($_POST['login_code'], 'exp')) {
    $loggedByCode = 1;
    $_SESSION['loggedByCode'] = 1;
}


// Соединение с базой данных
$user   = getRequestParam('db_user');
$pass   = getRequestParam('db_pass');
$server = getRequestParam('db_server');
$database = getRequestParam('db_name');

global $mysqli;
if ($server && $user && $pass) {
    //$mysqli =  DbConnect::connect($server, $user, $pass, $database);
    $mysqli = new mysqli($server, $user, $pass);
    if ($mysqli->connect_error) {
        error($mysqli->connect_error);
        $mysqli = false;
        $user = $pass = $server = $database = '';
    } else {
        function mysqliClose()
        {
            global $mysqli;
            if ($mysqli) {
                $mysqli->close();
            }
        }
        register_shutdown_function('mysqliClose');
    }
}

if (!$loggedByCode && !$mysqli) {
    ?><!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo MS_APP_NAME.' '.MS_APP_VERSION ?></title>

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-giJF6kkoqNQ00vy+HMDP7azOuL0xtbfIcaT9wjKHr8RbDVddVHyTfAAsrekwKmP1" crossorigin="anonymous">

</head>
<body onload="document.querySelector('[name=login_code]').focus();">

<div class="container">
<div class="row">
    <div class="col-lg-4 offset-lg-4">
        <form class="login" method="post" style="display:block; clear:both;" action="/<?php echo EXP ?>" id="loginForm">
            <?php
            if ($_POST) {
                echo '<span style="color:Red">error</span>';
            }
            ?>

            <div class="mb-3 mt-3">
                <label class="form-label">Пароль</label>
                <input type="password" class="form-control" name="login_code" placeholder="Password">
            </div>

            <input type="submit" id="connectButton" value="Login" class="btn btn-primary" />
        </form>
    </div>
</div>
</div>

</body></html>
<?php
    exit;
}

$_SESSION['db_user'] = $user;
$_SESSION['db_pass'] = $pass;
$_SESSION['db_server'] = $server;
$_SESSION['db_name'] = $database;

// Выбор базы данных
$error = '';
if (empty($database)) {
    $error = 'Please select database';
} elseif ($mysqli && !$mysqli->select_db($database)) {
    $error = "Cannot select database $database. Please try again.";
}
if ($error && $mysqli) {

    ob_start();
    echo "<div style='color:red'>$error</div><ul>";
    $q = query('SHOW DATABASES');
    while ($row = $q->fetch_object()) {
        echo '<li><span style="cursor:pointer" onclick="document.forms[\'loginForm\'][\'db_name\'].value=this.innerHTML;'.
        ' document.forms[\'loginForm\'].submit()">'.$row->Database . "</span></li>";
    }
    ?>


    </ul>

<form class="row gy-2 gx-3 align-items-center" action="/<?=EXP?>?action=tables" name="loginForm">
  <div class="col-auto">
    <input type="text" class="form-control" name="db_name" placeholder="Db name" />
  </div>
  <div class="col-auto">
    <button type="submit" class="btn btn-primary">Submit</button>
  </div>
</form>

    <?php
    $pageContent = ob_get_contents();
    ob_end_clean();

    templateLayout(array(), $database, $pageContent);

    // дальше не пойдем, т.к. база не выбрана
    exit;
}

// Очищающие запросы
// если логин, то возвращаем на первую страницу
if ($_POST['db_server'] && strpos($_SERVER['HTTP_REFERER'], '?')) {
    header('Location: '.$_SERVER['HTTP_REFERRER'].'');
}
if ($mysqli) {
    $a = array(
        "SET NAMES utf8",
        "SET character_set_client = utf8",
        "SET character_set_database = utf8",
        "SET character_set_results = utf8",
        "SET character_set_server = utf8"
    );
    foreach ($a as $sql) {
        $mysqli->query($sql);
    }
}








// Различные действия section

if (array_key_exists('phpinfo', $_GET)) {
    phpinfo();
    exit;
}

if (POST('log') == 1) {
    global $logfile;
    $logfile = fopen('log.txt', 'w+');
}


if ($_POST['steps-export']) {
    stepsExport();
    exit;
}



if ($_POST['uploadfileremove']) {
    unlink(getTempFile());
    exit;
}


// Загрузка файла для импорта а также загрузка файла при dropdown аплоаде
if ($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest' && strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') === 0) {
    if (!$_FILES) {
        exit(json_encode(array('error' => 'Ошибка загрузки файла, вероятно размер файла больше допустимого')));
    }

    $uploadOnPart = $_FILES['file']['type'] == 'application/octet-stream'; // Загрузка по частям

    $fileto = $_REQUEST['saveas'] ? $_REQUEST['saveas'] : $_FILES['file']['name'];
    if ($_GET['folder']) {
        $fileto = $_GET['folder'].'/'.$fileto;
    }

    // Проверка расширения из импорта TODO перенести это в js и реализовать просто через saveas как сверху
    if ($_GET['filename']) {
        $ext = mb_strtolower(substr($fileto, 1 + strrpos($fileto, '.')));
        if ($ext != 'zip' && $ext != 'sql') {
            exit(json_encode(array('error' => 'Неверное расширение файла ('.$ext.')')));
        }
        $fileto = $_GET['filename'].'.'.$ext;
    }

    // Удаление существуюшего если есть
    if (file_exists($fileto) && (!array_key_exists('start', $_POST) || $_POST['start'] == 0)) {
        if ($_REQUEST['deleteExist']) {
            unlink($fileto);
        } else {
            exit(json_encode(array('error' => ' Файл уже существует '.$fileto)));
        }
    }

    // Перемещение файла
    $tmp  = $_FILES['file']['tmp_name'];
    // Загрузка по частям
    if ($uploadOnPart) {
        $tmpblob = TMP_DIR.'/blob';
        if (!move_uploaded_file($tmp, $tmpblob)) {
            exit(json_encode(array('error' => 'move_uploaded_file blob error')));
        }
        $content = file_get_contents($tmpblob);
        $a = fopen($fileto, 'a+');
        if (!$a) {
            exit(json_encode(array('error' => 'error create "'.$fileto.'"')));
        }
        fwrite($a, $content);
        fclose($a);
        unlink($tmpblob);
    } else {
        if (!move_uploaded_file($tmp, $fileto)) {
            exit(json_encode(array('error' => 'move_uploaded_file error')));
        }
    }

    exit(json_encode(array('file' => $fileto)));
}

// Выполняем запросы
// делаются проверки, т.к. есть другие запросы в POST (поиск) которые выполняются дальше по коду
if (count($_POST) > 5 && (in_array($_REQUEST['action'], array('sql', 'import', 'export')) || $_POST['execByRows'])) {
    set_error_handler('ajaxErrorHandler');
    $a = fopen(LOG_FILE, 'w+');
    fwrite($a, 'Старт');
    fclose($a);
    register_shutdown_function('ajaxShutdown');
}


// Импорт данных
if ($_POST['execByRows']) {
    $sql = $_POST['execByRows'];
    addLog('начинаю построчное выполнение файла '.$sql.'...');
    if (!file_exists($sql)) {
        addLog('файл не существует');
        exit;
    }
    $handle = fopen($sql, "r");
    addLog('файл открыт');
    if ($handle) {
        global $mysqli;
        $cnt = $execs = $errors = 0;
        $sql = '';
        while (($buffer = fgets($handle, 4096)) !== false) {
            if (strpos($buffer, '--') === 0 || empty($buffer) || $buffer == "\n" || $buffer == "\r\n") {
                continue;
            }
            $cnt ++;
            $sql .= $buffer;
            if (strpos($buffer, ";\n") !== false) {
                $mysqli->query($sql);
                $sql = '';
                $execs ++;
                if ($mysqli->error) {
                    $errors ++;
                }
            }
            if ($cnt % 10000 === 0) {
                addLog('прочитано строк '.$cnt.'');
            }
        }
        fclose($handle);
        addLog('чтение завершено. Всего строк '.$cnt.'. Выполнено '.$execs.'. Ошибок '.$errors.'');
    }
    exit;
}

if (POST('action') == 'import') {
    addLog('import');

    if ($_POST['max_execution_time']) {
    	ini_set('max_execution_time', $_POST['max_execution_time']);
    	addLog('max_execution_time='.ini_get('max_execution_time'));
    }

    $filename = getTempFile();
    $removeArchivedFiles = array();
    $files = array();
    if ($filename) {
        if (strpos($filename, '.sql')) {
            $files []= $filename;
        } elseif (strpos($filename, 'zip') !== false) {
            $removeArchivedFiles = $files = Zip::unpack($filename, '.');
        } else {
            addLog('Не удалось распознать тип файла "'.$filename.'"');
            exit;
        }
    } elseif (POST('ifolder') && !$existFile) {
        addLog(' режим загрузки файлов из папки.  '.POST('ifolder').' .. ');
        $zips = glob('./'.POST('ifolder').'/*.zip');
        if (is_array($zips) && count($zips) > 0) {
            foreach ($zips as $k => $v) {
                $removeArchivedFiles = array_merge($removeArchivedFiles, Zip::unpack($v, POST('ifolder')));
            }
        }
        $files = glob(POST('ifolder').'/*.sql');
        if (count($files) == 0) {
            $folder = POST('ifolder');
            $a = scandir($folder);
            $files = array();
            foreach ($a as $k => $v) {
                if ($v == '.' || $v == '..') {
                    continue;
                }
                if (!strpos($v, '.')) {
                    $files []= $folder .'/'. $v;
                }
            }
            if (!count($files)) {
                addLog('No *.sql files on ifolder '.POST('ifolder'));
                exit;
            }
        }
    } else {
        addLog('Ни один из вариантов загрузки не выбран!');
    }

    if (count($files) == 0) {
        addLog('No *.sql files in folder '.POST('ifolder').'');
        exit;
    }

    natsort($files);

    $data = '';
    foreach ($files as $key => $value) {
        $logStart = 'IMPORT file '.basename($value).' ... ';
        addLog($logStart);

        // Проверка таблицы
        if (POST('save_filled') == 1) {
            $tableName = basename($value, '.sql');
            $countRows = 0;
            $row = getOne('SELECT COUNT(*) AS c FROM `'.$tableName.'`');
            if ($row) {
                $countRows = $row['c'];
            }
            if ($countRows > 0) {
                addLog($logStart.' SKIP FILLED, rows='.$countRows);
                continue;
            }
        }

        if (is_dir($value)) {
            continue;
        }

        // Проверка размера файла
        $size = round(filesize($value)/(1024*1024), 1);
        $content = file_get_contents($value);
        /*if (!isUtf8Codepage($content)) {
            addLog('<span style="color:red">'.$logStart."file $value is not in utf8".'</span>');
            //continue;
        }*/

        if (POST('cut_file') > 0 && $size > POST('cut_file')) {
            addLog($logStart.' cutted to filesize '.POST('cut_file').'mb (from '.$size.'mb)');
            $content = substr($content, 0, POST('cut_file')*1024*1024);
        }

        list($errors, $count, $affected, $success) = execSql(
            $content,
            POST('import_type'),
            POST('max_query'),
            POST('exitOnError')
        );
        $fault = count($errors);
        $succ = $count - $fault;
        if ($fault > 0) {
            addLog("Импорт завершен, строк $count, запросов выполнено: $succ, успешно $success, ошибок
                $fault, затронуто рядов: $affected", 1);
            addLog('<pre style="max-height: 500px; overflow:auto;">'.print_r(array_unique(array_slice($errors, 0, 1000)), 1).'</pre>');
        } else {
            addLog("Импорт завершен, строк $count, ОК", 1);
            if ($_POST['deleteAfterImport']) {
                unlink($value);
            }
        }
    }

    if (!$removeArchivedFiles) {
    	exit;
    }
    foreach ($removeArchivedFiles as $k => $v) {
        addLog('удаляем '.$v.'');
        unlink($v);
        $zip = str_replace('.sql', '.zip', $v);
        if (file_exists($zip)) {
            unlink($zip);
        }
    }
    exit;
}



//
if (POST('action') == 'export') {
    addLog('Начинаем экспорт, тип экспорта: '.POST('ex_type') .'');

    // Если тип экспорта - в файлы в папку - то проверяем, создаем эту папку
    $folder = POST('folder');
    if (POST('ex_type') == 'files') {
        if (!$folder) {
            addLog('Не указана папка при экспорте');
            exit;
        }
        if (!file_exists($folder)) {
            $res = mkdir($folder, 0777);
            addLog('Создаем папку '.$folder);
            if (!$res) {
                addLog('Ошибка создания папки '.$folder);
                exit;
            }
        }
        $perm = substr(decoct(fileperms($folder)), 2, 3);
        if ($perm != '777') {
            chmod($folder, 0777);
            $perm = substr(decoct(fileperms($folder)), 2, 3);
            if ($perm != '777') {
                addLog('Права на папку '.$folder.' не равны 777');
            }
        }
    }


    // saveData2file($table, $data, 'zip')
    function saveData2file($table, $data, $type = 'sql', $folder = '')
    {
        $filename = $table.'.'.$type;
        if ($folder != '') {
            $filename = $folder.'/'.$filename;
        }
        if (file_exists($filename)) {
            if (filesize($filename) > 0) {
                addLog($s.' file exists!');
                return -1;
            }
        }
        $s = "EXPORT table '$table' in file '$filename' ... ";
        if ($type == 'zip') {
            Zip::create($table.'.sql', $filename, $data);
            addLog($s.' - OK');
            return ;
        }
        if (file_exists($filename)) {
            $f = fopen($filename, 'a+');
        } else {
            $f = fopen($filename, 'w+');
        }
        if (!$f) {
            addLog($s.' - ОШИБКА открытия файла, наверное нет прав');
            return ;
        }
        if (!fwrite($f, $data)) {
            addLog($s.' - ОШИБКА записи в файл');
            return;
        }
        fclose($f);
        addLog($s.' - OK');
    }

    $exp = Exporter::exportInit($server, $database);
    $array = array();
    $allow = false;
    $tables = POST('tables');

    addLog('Всего таблиц: '.count($tables).'');
    $isPartMode = false;
    foreach ($tables as $table) {
        if (empty($table)) {
            addLog('Пустое имя таблицы');
            continue;
        }

        $isStruct = POST('isStruct');
        $isData = POST('isData');

        addLog('Экспорт '.$table.'... '.($isStruct ? 'структура' : '').'  '.($isData ? 'данные' : ''));

        $exportfilename = $table.'.zip';
        if ($folder != '') {
            $exportfilename = $folder.'/'.$exportfilename;
        }
        if (file_exists($exportfilename)) {
            addLog("EXPORT table '$table' in file '$exportfilename' FILE EXISTS! Skip.... ");
            continue;
        }

        $exp->setTable($table);

        if ($isStruct) {
            $exp->addIfNot = true;
            $exp->exportStructure($addDelim = true, $_POST['adddrop']);
        }
        if ($isData) {
            $where = $_POST['exportWhere'];
            if (isset($_POST['forceExportByPart'])) {
                $res = saveData2file($table.'.0', $exp->data, 'zip', $folder);
                /*if ($res === -1) {
                    continue;
                }*/
                $exp->data = '';
                // здесь уходит куда-то влево??
                $exp->exportByPart($table, 0, $folder);
                continue;
            }
            $resultsCount = $exp->exportData('INSERT', $where);
            if (is_numeric($resultsCount)) {
                if (POST('ex_type') != 'files') {
                    addLog('Exported "'.$table.'" only '.$resultsCount.' rec - memory limit '.memory_get_usage().
                        " > $memory_limit ...... Export by part");
                    addLog('<h1>Не хватает памяти, экспортируйте данные в папку</h1>');
                    exit;
                }
                addLog('Exported "'.$table.'" only '.$resultsCount.' rec - memory limit '.memory_get_usage().
                    " > $memory_limit ...... Export by part");
                saveData2file($table, $exp->data, 'zip', $folder);
                $exp->data = '';
                $exp->exportByPart($table, $resultsCount, $folder);
                continue;
            }
        }

        if (POST('ex_type') == 'files') {
            saveData2file($table, $exp->data, 'zip', $folder);
            $exp->data = '';
        }
    }
    if (POST('ex_type') != 'textarea') {
        addLog('EXPORT END------------');
    }

    if (POST('ex_type') == 'textarea') {
        $textarea = $exp->send();
        // либо в sqlField прямо прописать вытащив из htmlspecialchars($exp->get()
        echo '<!> document.getElementById("loggerBlock").innerHTML = "'.jsSafe($textarea).'";';
    } elseif (POST('ex_type') == 'zip') {
        $file = $_SERVER['HTTP_HOST'];
        $filename = TMP_DIR.'/temp-export.zip';
        $exp->send('zip', $file, $filename);
        echo '<!>location = "?fileDownload='.$filename.'"';
    }

    exit;
}

// Расчет размера папки
if ($_GET['action'] == 'sizeFolders' && $_GET['folder']) {
    $dir = getRoot();
    $size = (int)dirSize($dir.'/'.$_GET['folder'], $count);
    $_SESSION['folder-sizes'][$_GET['folder']] = $size;
    echo json_encode(array(
        'size' => $size,
        'folder' => $_GET['folder'],
        'count' => (int)$count
    ));
    exit;
}

// Скачать файл
if ($_GET['fileDownload']) {
    $file = $_GET['fileDownload'];
    if (!file_exists($file)) {
        exit('File not exists');
    }

    header("Content-Disposition: attachment; filename=\"" . basename($file) . "\"");
    $fp = fopen($file, 'rb');
    fpassthru($fp);
    exit;
}


// Скачать папку
if ($_GET['download'] && array_key_exists('folder', $_GET)) {
    if (!$_GET['folder']) {
        $_GET['folder'] = dirname(__FILE__);
    }
    $tmp_dir = findTempDir($_GET['folder']);
    $file = $tmp_dir.'/'.basename($_GET['folder']).'.zip';

    if (file_exists($file)) {
        if ($_GET['removeExist']) {
            unlink($file);
        } else {
            echo '<p>Уже существует файл '.$file.', не знаю что дальше делать</p>';
            exit;
        }
    }
    $zip = new ZipArchive();
    $code = $zip->open($file, ZipArchive::CREATE);
    if ($code !== TRUE) {
        addlog("Невозможно открыть '$saveTo' ошибка - ".Zip::zipError($code)."\n");
        exit;
    }

    $a = scandir($_GET['folder']);
    $excludeDirs = explode(',', $_GET['excludeDirs']);
    $files = array();
    foreach ($a as $k => $v) {
        if ($v == '.' || $v == '..') {
            continue;
        }
        if ($excludeDirs && in_array($v, $excludeDirs)) {
            continue;
        }
        $files []= $v;
    }
    zipFilesFolder($zip, $_GET['folder'], $files, $added, $errors, $_GET['fromRoot']);
    $zip->close();

    if ($_GET['downloadOnly']) {
        header('Content-Type: application/zip');
        header('Content-disposition: attachment; filename='.basename($file));
        header('Content-Length: ' . filesize($file));
        readfile($file);
        unlink($file);
    } else {
        echo '<div>added files: '.$added.'. errors: '.$errors.'</div>';
        echo '<a href="'.$file.'">Download</a>';
    }

    exit;
}

if (array_key_exists('update', $_GET)) {
    if (!is_writeable(__FILE__)) {
        echo 'не могу обновить, файл недоступен для записи '.__FILE__.' ('.substr(decoct(fileperms(__FILE__)), 2, 4).')';
        exit;
    }
    $content = loadurl('https://komu.info/exp.php?get');
    if ($content == '') {
        echo 'empty content';
        exit;
    }
    $tmpFile = TMP_DIR.'/exp1.php';
    if (!$a = fopen($tmpFile, 'w+')) {
        echo 'ошибка создания временного файла exp1.php';
        exit;
    }
    if (!fwrite($a, $content)) {
        echo 'ошибка записи в файл exp1.php';
        exit;
    }
    fclose($a);
    if (!unlink(__FILE__)) {
        echo 'ошибка удаления '.__FILE__;
        exit;
    }
    rename($tmpFile, basename(EXP));
    header('Location: /'.EXP);
}



// Действия разные, выполняемые на ajax (без необохдимости ведения лога)
// По идее их надо запихнуть в processRequests, но мне не нравится эта мусорная функция непонятная

// Удаление
if ($_POST['tmode'] == 'delete') {
    $fields = getFields($_GET['table'], $onlyNames = false);
    query('DELETE FROM '.$_GET['table'].' WHERE '.$_POST['where'].' LIMIT 1', $e);
    exit;
}

// Копирование рядов таблицы
$showTable = false;
if ($_GET['tmode']) {
    $fields = getFields($_GET['table'], $onlyNames = false);
    if (GET('tmode') == 'copy' && GET('where')) {
        rowCopy($fields, GET('table'), GET('where'));
        $showTable = true;
    }
}

// Добавление удаление ключей
if ($_POST['dropkey']) {
    if ($_POST['dropkey'] == 'PRIMARY') {
        query('ALTER TABLE `'.GET('table').'` DROP PRIMARY KEY');
        echo 'Удален primary key';
    } else {
        query('ALTER TABLE `'.GET('table').'` DROP KEY `'.$_POST['dropkey'].'`');
    }
    exit;
}
if ($_POST['addkey']) {
    $e = '';
    if ($_POST['addkey'] == 'INDEX') {
        $_POST['addkey'] = '';
    }
    query('ALTER TABLE `'.GET('table').'` ADD '.$_POST['addkey'].' KEY (`'.implode('`,`', explode(',', $_POST['fields'])).'`)', $e);

    if ($e) {
        echo $e;
    }
}

if ($_POST['md5']) {
    echo md5($_POST['md5']);
    exit;
}
if (array_key_exists('passw', $_POST)) {
    echo $p = generatePassword($_POST['length'], $_POST['caps'], $_POST['password-symbols']);
    echo '<br />md5: '.md5($p);
    exit;
}
if ($_POST['loadurl']) {
    $opts = array();
    if ($_POST['http_login'] && $_POST['http_pass']) {
        $opts [CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
        $opts [CURLOPT_USERPWD] = $_POST['http_login'].':'.$_POST['http_pass'];
    }
    echo loadurl($_POST['loadurl'], $opts);
    exit;
}
if ($_POST['http']) {
    $opts = array(CURLOPT_HEADER => 1, CURLOPT_NOBODY => 1);
    if ($_POST['http_login'] && $_POST['http_pass']) {
        $opts [CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
        $opts [CURLOPT_USERPWD] = $_POST['http_login'].':'.$_POST['http_pass'];
    }
    $content = loadurl($_POST['http'], $opts);
    $content = preg_replace('~[\r\n]+~i', "\n", $content);
    echo '<pre>'.htmlspecialchars($content).'</pre>';
    exit;
}
if ($_POST['serialize']) {
    echo 'eval:$(opts.hide).html("<pre>'.jsSafe(serialize($_POST['serialize'])).'</pre>");';
    exit;
}
if ($_POST['unserialize']) {
    echo 'eval:$(opts.hide).html("<pre>'.jsSafe(print_r(unserialize($_POST['unserialize']), 1)).'</pre>");';
    exit;
}
if ($_POST['decode']) {
    echo 'eval:$(opts.hide).html("<pre>'.jsSafe(print_r(json_decode($_POST['decode']), 1)).'</pre>");';
    exit;
}
if ($_POST['encode']) {
    echo 'eval:$(opts.hide).html("<pre>'.jsSafe(json_encode($_POST['encode'])).'</pre>");';
    exit;
}
if ($_POST['translit']) {
    echo translit($_POST['translit']);
    exit;
}
if ($_POST['punicode']) {
    $site = $_POST['punicode'];
    $site = str_replace('http://', '', $site);
    $site = preg_replace('~/$~i', '', $site);
    if (preg_match('~[а-я]~iu', $site)) {
        echo idn_to_ascii($site);
    } else {
        echo idn_to_utf8($site);
    }
    exit;
}

if ($_POST['tms']) {
    $tms = $_POST['tms'];
    if (is_numeric($tms)) {
        echo $tms.' = '.date('Y-m-d H:i:s', $tms);
    } else {
        echo $tms.' = '.strtotime($tms);
    }
    exit;
}

if (array_key_exists('check-email', $_POST)) {
    $email = $_POST['check-email'] ? $_POST['check-email'] : 'andymc@inbox.ru';
    $title = 'Проверка емейл '.$_SERVER['HTTP_HOST'].'';
    ob_start();
    phpinfo();
    $phpinfo = ob_get_contents();
    ob_end_clean();
    $message = 'Время: '.date('d.m.Y H:i:s')."<br />\n\n<pre>".print_r($_SERVER, 1)."</pre><br />\n\n".$phpinfo;
    $from = 'test@'.$_SERVER['HTTP_HOST'].'.ru';
    $headers = "Return-path: <$from>\n".
    "From: $from\n".
    "Content-type: text/html; charset=utf-8\r\n".
    "Reply-To: $from\n".
    "X-Mailer: PHP/" . phpversion();
    $res = mail($email, $title, $message, $headers);
    if ($res) {
        echo $email.' отправлено письмо';
    } else {
        echo 'Ошибка отправки письма';
    }
    exit;
}

// Закачать с URL в текущую папку
if ($_POST['action'] == 'urlLoading') {

    $filename = $_POST['filename'] ? $_POST['filename'] : basename($_POST['folder']);
    $saveTo = $_GET['folder'] ? $_GET['folder'].'/'.$filename : $filename;

    $content = loadurl($_POST['folder']);
    fsave($saveTo, $content);

    if ($_POST['maxWidth']) {
        list($w, $h) = getimagesize($saveTo);
        $maxW = intval($_POST['maxWidth']);
        if ($w > $maxW) {
            $imOld = imagecreatefromjpeg($saveTo);
            $wNew = $maxW;
            $hNew = $h * ($maxW / $w);
            $im = imagecreatetruecolor ($wNew, $hNew);
            $result = imagecopyresampled($im, $imOld, 0, 0, 0, 0, $wNew, $hNew, $w, $h);
            imagejpeg($im, $saveTo, 90) ;
        }
    }
}

// Удаление файла
if (array_key_exists('deleteFile', $_POST)) {
    if (empty($_POST['folder']) || empty($_POST['deleteFile'])) {
        exit('Пустые параметры');
    }
    $file = $_POST['folder'] .'/'. $_POST['deleteFile'];
    if (is_dir($file)) {
        if (!rmdir($file)) {
            echo 'Ошибка удаления папки';
        }
    } elseif (file_exists($file)) {
        $file = iconv('utf-8', 'windows-1251', $file);
        if (!file_exists($file)) {
           echo 'Файл "'.$file.'" уже не существует!';
        } else {
            unlink($file);
            if (file_exists($file)) {
                echo 'Не смог удалить файл "'.$file.'"';
            }
        }
    } else {
        exit('Файл не существует');
    }
    exit;
}

if ($_POST['unpack']) {
    define('DISABLE_ADDLOG', 1);
    if (!file_exists($_POST['unpack'])) {
        var_dump(realpath('.'));
        exit('Файл не существует '.$_POST['unpack'].'');
    }
    Zip::unpack($_POST['unpack'], dirname($_POST['unpack']), $error);
    if ($_POST['unpackdel']) {
        unlink($_POST['unpack']);
    }
    exit;
}

if ($_POST['fileStat']) {
    $file = $_POST['fileStat'];
    $image = getimagesize($file);
    $stat = array();
    if ($image) {
        $stat ['width'] = $image[0];
        $stat ['height'] = $image[1];
        $stat ['bits'] = $image['bits'];
        $stat ['mime'] = $image['mime'];
    }
    $stat ['size'] = formatSize(filesize($file));
    $stat ['Дата создания'] = date('Y-m-d H:i:s', filectime($file));
    $stat ['Дата изменения'] = date('Y-m-d H:i:s', filemtime($file));
    $stat ['Права'] = substr(decoct(fileperms($file)), -3);

    echo '<div style="margin-top:20px;">Статистика файла "'.$file.'"</div>';
    foreach ($stat as $k => $v) {
        echo $k.' <b>'.$v.'</b><br />';
    }
    exit;
}

// Переименование файла
if ($_POST['renameFile'] && $_POST['newName'] && $_POST['folder']) {
    $file = $_POST['folder'] .'/'. $_POST['renameFile'];
    $new = $_POST['folder'] .'/'. $_POST['newName'];
    if (!rename($file, $new)) {
        echo 'Ошибка переименования';
    }
    exit;
}

// Перемещение файла
if ($_POST['moveFile'] && $_POST['movePath'] && $_POST['folder']) {
    $file = $_POST['folder'] .'/'. $_POST['moveFile'];
    $new = $_POST['movePath'] .'/'. $_POST['moveFile'];
    if (!rename($file, $new)) {
        echo 'Ошибка перемещения';
    }
    exit;
}


if ($_GET['ai']) {
    $result = $mysqli->query('ALTER TABLE `'.$_GET['table'].'`  AUTO_INCREMENT = '.$_GET['ai']);
    header('Location: '.$_SERVER['HTTP_REFERER']);
    exit;
}

if ($_GET['raw']) {
    header('Content-Type: text/plain; charset=utf-8');
    echo file_get_contents($_GET['raw']);
    exit;
}

// Список файлов
if ($_GET['mode'] == 'list-files') {
    $folder = $_SERVER['HTTP_HOST'].'_'.basename($_GET['folder']);
    $folder = preg_replace('~[\\/]~i', '_', $folder);
    $folder = preg_replace('~[^a-z\d_-]~i', '', $folder);
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename='.$folder.'.txt');
    header("Pragma: no-cache");
    header("Expires: 0");
    $directory = new \RecursiveDirectoryIterator($_GET['folder']);
    $iterator = new \RecursiveIteratorIterator($directory);
    $files = array();
    foreach ($iterator as $info) {
        $filename = $info->getFilename();
        if ($filename[0] === '.') {
            continue;
        }
        if ($iterator->isDir()) {
            continue;
        }
        echo "\n".$info->getPathname();
    }
    exit;
}

if ($_POST['edit-file']) {
    if (empty($_POST['content'])) {
        exit('Пустой контент');
    }
    if (!file_exists($_POST['name'])) {
        exit('Файл не найден');
    }
    $backup = file_get_contents($_POST['name']);
    fwrite($a = fopen(TMP_DIR.'/backup-'.basename($_POST['name']), 'w+'), $backup); fclose($a);
    if (!$a = fopen($_POST['name'], 'w+')) {
        exit('Ошибка открытия файла на запись');
    }
    if (!fwrite($a, $_POST['content'])) {
        exit('Ошибка записи в файл');
    }
    fclose($a);
    exit;
}

$pager->actions();





// Общие стили и скрипты, шапка section

if (POST('ex_type') != 'zip') {
    header("Content-Type: text/html; charset=utf-8");
} else {
    ob_start();
}
$tables = getAllTables();
if (!$mysqli && !$_GET) {
    header('Location: /'.EXP.'?action=upload');
    exit;
}


// Начало html вывода
// $pageContent = '';
// Архивирование section
if (GET('action') == 'zip') {
    $opts['count']     = POST('count', 100);
    $opts['max']       = POST('max', 500);
    $opts['extension'] = POST('extension', '');
    $opts['exclude']   = POST('exclude', '');
    $opts['exclude_path'] = POST('exclude_path', '');
    $opts['include']   = POST('includeOnly');

    $opts['archiveFolder'] = TMP_DIR;
    if (array_key_exists('archive-folder', $_POST)) {
        $opts['archiveFolder'] = $_POST['archive-folder'];
    } else {
        if (file_exists('upload')) {
            $opts['archiveFolder'] = 'upload';
        }
    }

    $files = glob('*.zip');
    $currentFile = '';
    if ($files) {
        $currentFile = $files[0];
        if ($_POST['filename']) {
            $currentFile = $_POST['filename'];
        }
    }


    ob_start();
    ?>



    <h2>Создать архив <a href="/<?=EXP?>?action=sizeFolders" style="font-size: 11px">Посмотреть размеры папок</a></h2>

    <form method="post" class="archive">
        <input type="hidden" name="action" value="createArchive">

        <input type="radio" class="btn-check" name="chunked" value="0" id="chunked-0" autocomplete="off"
            <?=!$_POST['chunked']?' checked':''?>>
        <label class="btn btn-outline-primary btn-sm" for="chunked-0">в один архив</label>
        <input type="radio" class="btn-check" name="chunked" value="1" id="chunked-1" autocomplete="off"
            <?=!$_POST['chunked']?'':' checked'?> onclick="$('#chunkedConf').show(); $('#ch-hide').hide()">
        <label class="btn btn-outline-primary btn-sm" for="chunked-1"> множество архивов</label>
        <span id="ch-hide" style="color:#ccc; font-size:12px;">когда файлов очень много и не получается создать архив
        из-за лимита в 30 сек на выполнение</span>

        <div style="<?=!$_POST['chunked']?'display:none;':''?> color:red" id="chunkedConf">

            <div class="input-group input-group-sm" style="width: 200px">
              <span class="input-group-text">макс кол-во файлов в одном архиве</span>
              <input type="text" name="count" value="<?php echo $opts['count']?>" class="form-control" />
            </div>

            <div class="input-group input-group-sm" style="width: 200px">
              <span class="input-group-text">макс кол-во файлов всего</span>
              <input type="text" name="max" value="<?php echo $opts['max']?>" class="form-control" />
            </div>
        </div>


        <div class="input-group input-group-sm mb-3 mt-3">
          <span class="input-group-text">Папка куда сохранять архив:</span>
          <input type="text" name="archive-folder" value="<?=$opts['archiveFolder']?>" class="form-control" />
        </div>

        <div class="input-group input-group-sm mb-3">
          <span class="input-group-text">Включить только файлы с расширением:</span>
          <input type="text" name="extension" value="<?=$opts['extension']?>" class="form-control" />
        </div>

        <div class="input-group input-group-sm mb-3">
          <span class="input-group-text">Исключить файлы/папки по имени:</span>
          <input type="text" name="exclude" value="<?=$opts['exclude']?>" class="form-control" />
        </div>

        <div class="input-group input-group-sm mb-3">
          <span class="input-group-text">Исключить пути:</span>
          <input type="text" name="exclude_path" value="<?=$opts['exclude_path']?>" class="form-control" />
        </div>

        <div class="input-group input-group-sm mb-3">
          <span class="input-group-text">Только папки:</span>
          <input type="text" name="includeOnly" value="<?=$opts['include']?>" class="form-control" />
        </div>

        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="archive-log" value="1" id="dolog">
          <label class="form-check-label" for="dolog">
            вести лог архивирования в файл txt
          </label>
        </div>

        <textarea name="filesList" placeholder="Список файлов, которые нужно объединить в архив"
            class="form-control mb-3"></textarea>

        <input onclick="setTimeout(function(o) {o.disabled=true}, 100, this);" type="submit" class="btn btn-primary"
            value="Начать создание архива" />

        <?php
        $archiveFile = archiveFile($opts['archiveFolder'], false);
        if (file_exists($archiveFile)) {
            echo '<div class="alert alert-danger mt-3">Внимание! Файл '.$archiveFile.'
            ('.formatSize(filesize($archiveFile)).') уже существует и будет перезаписан</div>';
        }
        ?>
    </form>




    <?php

    if ($_POST['action'] == 'createArchive') {
        createArchive($opts);
    }

    $pageContent = ob_get_contents();
    ob_end_clean();
}


if ($_GET['action'] == 'login' && $mysqli == false) {
    ob_start();
    ?>
  <form method="post" action="/<?=EXP?>?action=tables" id="loginForm" style="max-width:400px" class="mx-auto mt-3">
      <?php
      if ($_POST) {
          echo '<div class="alert alert-danger">Wrong login data</div>';
      }
      ?>
      <div class="input-group mb-3">
        <span class="input-group-text">server</span>
        <input type="text" class="form-control" name="db_server" value="<?php echo $server ?: 'localhost' ?>">
      </div>
      <div class="input-group mb-3">
        <span class="input-group-text">user</span>
        <input type="text" class="form-control" name="db_user" id="db_user_field" value="<?php echo $user?>">
      </div>
      <div class="input-group mb-3">
        <span class="input-group-text">pass</span>
        <input type="password" class="form-control" name="db_pass" id="db_pass_field" value="<?php echo $pass?>">
      </div>
      <div class="input-group mb-3">
        <span class="input-group-text">database</span>
        <input type="text" class="form-control" name="db_name" value="<?php echo $database?>">
      </div>

      <input type="submit" class="btn btn-primary" value="Login" />
  </form>
<?php
    $pageContent = ob_get_contents();
    ob_end_clean();
}












// Быстрый запрос
if ($_POST['sql-quick']) {
    ob_start();
    $sql = $_POST['sql-exec'];
    echo '<h2>Быстрый запрос</h2>';
    if (!$sql) {
        echo 'Пустой запрос';
        return ;
    }
    $sql = trim($sql);
    saveSqlHistory($sql);
    echo '<div style="margin-bottom:10px; border:1px solid #aaa; padding:10px; overflow:auto; max-height:300px;">'.htmlspecialchars($sql).'</div>';
    if (preg_match('~^(select|show|explain) ~i', $sql)) {
        $a = round(array_sum(explode(" ", microtime())), 10);
        echo printSqlTable($sql, $data = null);
        $a = round(round(array_sum(explode(" ", microtime())), 10) - $a, 5);
        echo 'Time: '.$a;
    } else {
        list($errors, $c, $affected) = execSql($sql);
        echo "c=$c, affected=$affected";
        if (is_array($errors) && count($errors)) {
            echo '<pre>'.htmlspecialchars(implode("\n", array_unique($errors))).'</pre>';
        }
    }
    echo '<hr />';
    $pageContent = ob_get_contents();
    ob_end_clean();
    if (!$_POST['sql-exec']) {
        echo $pageContent;
        exit;
    }
}









// Уники и массовая обработка section
if ($_POST['rx-uniq']) {

    $rx = '~.{0,20}'.$_POST['rx-uniq'].'.{0,20}~i';
    $rx = '~<ul.*?'.'>~i';
    $pageContent .= 'Ищем по регулярке '.htmlspecialchars($rx).'';
    $sql = 'SELECT * FROM '.$_GET['table'].$w;
    $result = query($sql);
    while ($result && $row = $result->fetch_object()) {
        $pageContent .= '<br /><a href="/index.php?option=com_content&view=article&id='.$row->id.'">'.$row->id.'</a>';
        foreach ($row as $k => $content) {
            if (preg_match($rx, $content, $a)) {
                $pageContent .= ' '.htmlspecialchars($a[0]);
            }
        }
    }
    $pageContent .= 'Поиск завершен';
}
if ($_POST['showUniqs']) {
    $w = '';
    if ($_POST['where']) {
        $w = ' WHERE '.$_POST['where'];
    }
    $h = '';
    if ($_POST['havingCnt']) {
        $h = ' HAVING COUNT(*) > '.$_POST['havingCnt'].'';
    }
    $sql = 'SELECT `'.$_POST['fieldUniq'].'` AS f, COUNT(*) as c FROM '.$_GET['table'].$w.' GROUP BY 1 '.$h.' ORDER BY 2 DESC';
    $result = query($sql);

    $uniqs = array();
    $rows = '';
    while ($result && $v = $result->fetch_object()) {
        $uniqs []= $v->f;
        $rows .= '<tr><td>'.$v->f.'</td><td>'.$v->c.'</td></tr>';
    }

    $pageContent = '
    <h2>Уникальные значения</h2>
    <div style="white-space:pre-wrap">'.htmlspecialchars($sql).'</div>
    <div style="margin:10px 0;">'.implode(', ', $uniqs).'</div>
    <table class="optionstable">'.$rows.'</table>';
}
if ($_POST['assoc1'] && $_POST['assoc1'] != 'поле') {
    $sql = 'SELECT `'.$_POST['assoc1'].'` AS assoc1, `'.$_POST['assoc2'].'` AS assoc2 FROM '.$_GET['table'].' ORDER BY `'.$_POST['assoc1'].'` ';
    $result = query($sql);
    while ($result && $v = $result->fetch_object()) {
        if (!is_numeric($v->assoc1)) {
            $v->assoc1 = "'$v->assoc1'";
        }
        if (!is_numeric($v->assoc2)) {
            $v->assoc2 = "'$v->assoc2'";
        }
        $pageContent .= '<br />'."$v->assoc1 => $v->assoc2,";
    }
}

if ($_POST['multy']) {
    $fields = getFields($_GET['table']);
    $pks = array();
    foreach ($fields as $k => $v) {
        if ($v->Key == 'PRI') {
            $pks []= $v->Field;
        }
    }
    $field = $_POST['multy'];
    $sql = 'SELECT `'.$field.'` AS field, '.implode(', ', $pks).' FROM '.$_GET['table'];
    if ($_REQUEST['where']) {
        $sql .= ' WHERE '.$_REQUEST['where'];
    }
    $result = query($sql);
    while ($result && $v = $result->fetch_object()) {
        $content = $v->field;
        if ($_POST['multy_func'] == 'preg_replace') {
            $content = preg_replace($_POST['param1'], $_POST['param2'], $content);
        } elseif ($_POST['multy_func'] == 'strtolower') {
            $content = mb_strtolower($content);
        } else {
            continue;
        }
        $wheres = array();
        foreach ($pks as $pk) {
            $wheres []= '`'.$pk.'`="'.$v->$pk.'"';
        }
        $s = 'update `'.$_GET['table'].'` set `'.$field.'`="'.$mysqli->real_escape_string($content).'" where '.implode(' AND ', $wheres).'';
        query($s);
    }
}

// Операции с полями таблицы section
if ($_GET['tmode']) {
    $fields = getFields($_GET['table'], $onlyNames = false);

    ob_start();

    if (GET('tmode') == 'edit' && GET('where')) {
        echo '<h2>Редактирование строки</h2>';
        processEdit($fields);

    } elseif (GET('tmode') == 'add') {
        echo '<h2>'.($_GET['where'] ? 'Копирование' : 'Добавление').' строки</h2>';
        processEdit($fields);

    } elseif ($_GET['tmode'] == 'fieldlist') {
        echo implode(', ', array_keys($fields));
        echo '<hr />';
        echo "'".implode("', '", array_keys($fields))."'";
        echo '<hr /><div style="float:left;">';
        foreach ($fields as $field => $v) {
            echo "<br />'$field' => '',";
        }
        echo '</div><div style="float:left;">';
        foreach ($fields as $field => $v) {
            echo "<br />'$field' => ,";
        }
        echo '</div><div style="float:left;">';
        foreach ($fields as $field => $v) {
            echo "<br />'' => '$field',";
        }
        echo '</div><div style="float:left;">';
        foreach ($fields as $field => $v) {
            echo "<br />\$obj->$field = '';";
        }
        echo '</div>';

    } elseif ($_GET['tmode'] == 'query') {
        $vals = $sets = array();
        foreach ($fields as $k => $v) {
            $vals []= '';
            $sets []= '`'.$k.'`=\'\'';
        }
        $sql1 = 'INSERT INTO `'.$_GET['table'].'` (`'.implode('`, `', array_keys($fields)).'`) VALUES (\''.implode('\', \'', $vals).'\')';
        $sql2 = 'UPDATE `'.$_GET['table'].'` SET '.implode(', ', $sets).' WHERE ';
        echo '<p>'.$sql1.'</p>';
        echo '<p>'.$sql2.'</p>';
        $showTable = true;

    } elseif (GET('tmode') == 'addField') {
        if ($_GET['field']) {
            echo '<h2>Редактировать поле</h2>';
        } else {
            echo '<h2>Добавить поле к <a href="/'.EXP.'?action=tables&table='.$_GET['table'].'&mode=fields">'.$_GET['table'].'</a></h2>';
        }
        $result = processAddField($fields);
    }

    $pageContent = ob_get_contents();
    ob_end_clean();

}

// Сравнение section
if ($_GET['tmode'] == 'compare') {
    $rows = compareDisplay($msg, $fields);
    $pageContent = '<table class="optionstable data">'.$rows.'</table>';
    if ($msg) {
        msg($msg);
    }
}

// Операции с таблицей section
if (isset($_GET['table'])) {
    if (!isset($_SESSION['tables'])) {
        $_SESSION['tables'] = array();
    }
    if (!in_array($_GET['table'], $_SESSION['tables'])) {
        $_SESSION['tables'][] = $_GET['table'];
    }

    $t = $_GET['table'];

    switch (@($_GET['mode'] ? $_GET['mode'] : $_POST['mode'])) {
    case 'delete':
        msg('Удаляем таблицу '.$t);
        redirect(EXP.'?action=tables', 1);
        query('DROP TABLE '.$t);
        exit;

    case 'truncate':
        msg('Очищаем таблицу '.$t);
        redirect(EXP.'?action=tables', 1);
        query('TRUNCATE TABLE '.$t);
        break;

    case 'rename':
        msg('Переименуем таблицу '.$t);
        $new = $_GET['newName'];
        query('ALTER TABLE `'.$t.'` RENAME TO `'.$new.'`');
        redirect(EXP.'?action=tables&table='.$new, 1);
        exit;

    case 'copy':
        msg('Копируем таблицу '.$t);
        $new = $_GET['newTable'];
        $sql = getTableExport($database, $t);
        $sql = str_replace('`'.$t.'`', '`'.$new.'`', $sql);
        query($sql);
        query('INSERT INTO `'.$new.'` SELECT * FROM `'.$t.'`');
        redirect(EXP.'?action=tables&table='.$t, 1);
        break;

    case 'repair':
        msg('Починка таблицы '.$t);
        query('REPAIR TABLE '.$t); break;

    case 'optimize':
        msg('Оптимизируем таблицу '.$t);
        query('OPTIMIZE TABLE '.$t); break;

    case 'flash':
        msg('Сбрасываем flash таблицы '.$t);
        query('FLUSH TABLE '.$t); break;

    case 'check':
        msg('Проверяем таблицу '.$t);
        query('CHECK TABLE '.$t); break;

    case 'export':
        $pageContent = '<h2>Экспорт '.$t.'
         <a style="font-size:11px;" href="'.url('replace=1').'">REPLACE</a>
        <a style="font-size:11px;" href="'.url('skipAi=1').'">без ключевого поля</a></h2>';
        if ($_GET['where']) {
            $exp = new Exporter($database);
            $exp->insFull = true;
            $exp->setTable($t)->exportData($_GET['replace'] ? 'REPLACE' : 'INSERT', $_GET['where'], $_GET['skipAi'] ? 1 : 0);
            $str = $exp->data;
        } else {
            $str = getTableExport($database, $t, $withData=$tables[$t]->Rows < 1000, array(
                'METHOD' => $_GET['replace'] ? 'REPLACE' : 'INSERT',
                'AI' => $_GET['skipAi'] ? 1 : 0
            ));
        }

        $pageContent .= '<textarea style="width:100%; height:500px;">'.htmlspecialchars($str).'</textarea>';

    }
}






// Структура таблицы section
if ($_GET['table'] && $_GET['mode'] == 'fields') {

    $url = EXP.'?action=tables&table='.$t;
    if (GET('smode') == 'delete' && GET('field')) {
        query('ALTER TABLE '.$t.' DROP '.GET('field'));
    }

    $fields = getFields($t, $onlyNames = false);

    $rows = '';
    foreach ($fields as $field => $p) {
        $p->Type = str_replace(',', ', ', $p->Type);
        $p->actionsLinks =
        '<a href="'.url('smode=delete&field='.$field).'" onclick="if (!confirm(\'Удалить поле '.$field.'?\')) return false;">del</a>
        <a href="/'.EXP.'?action=tables&table='.$_GET['table'].'&tmode=addField&field='.$field.'">edit</a>';
        $p->Field = '<input type="text" class="focusselect" value="'.$p->Field.'" />';
        $style = '';
        if ($p->Null == 'NO') {
            $style = ' style="color:red"';
        }
        $rows .= addRow($p, 'td', $style);
    }

    $data = getData('SHOW KEYS FROM `'.$t.'`');
    if (count($data)) {
        $tableKeys = addRow(array(
            'Key_name',
            'Column_name',
            'Cardinality',
            'Null'
        ));
        foreach ($data as $k => $v) {
            $title = $v['Key_name'];
            if ($v['Non_unique']) {
                $title = 'Index';
            } else {
                if ($v['Key_name'] != 'PRIMARY') {
                    $title = 'Unique';
                }
            }
            $tableKeys .= addRow(array(
                $title,
                $v['Column_name'],
                $v['Cardinality'],
                $v['Null'],
                '<a href="#" onclick="jsquery(\'dropkey='.$v['Key_name'].'\', {hide: this.parentNode.parentNode}); return false;">x</a>'
            ));
        }
    } else {
        $tableKeys = 'Ключей нет';
    }

    ob_start();
    tableTitle('Просмотр структуры', false, $tables);
    ?>
    <p>
        <a href="<?=$url?>&mode=add&tmode=add">Добавить строку</a>
        <a href="<?=$url?>&mode=add&tmode=addField">Добавить поле</a>
    </p>

    <table class="optionstable">
    <tr>
    <?php
    $data = array_pop($fields);
    foreach ($data as $k => $v) {
        if ($k == 'actionsLinks') {
        	$k = '';
        }
        echo '<th align="center" valign="top">'.$k.'</th>';
    }
    ?>
    </tr>
    <?php echo $rows?>
    </table>

    <h2>Ключи</h2>

    <table class="optionstable">
    <?=$tableKeys?>
    </table>
    <form method="post" style="margin-top:15px;">
        <select name="addkey">
            <option>PRIMARY</option>
            <option>UNIQUE</option>
            <option>INDEX</option>
        </select>
        <input type="text" name="fields" value="" placeholder="Список полей через ," onfocus="this.style.width='500px'"
            onkeyup="this.nextSibling.disabled=false" /><input type="submit" disabled value="Создать ключ" />
    </form><?php
    $pageContent .= ob_get_contents();
    ob_end_clean();
}




// Просмотр таблицы section
if ($_GET['table'] && (!$_GET['tmode'] || $showTable) && $_GET['mode'] != 'delete' && $_GET['mode'] != 'fields') {


    $fields = getFields($_GET['table'], $onlyNames = false);
    $fieldNames = array_keys($fields);

    $order = '';
    if ($_SESSION['order']) {
        $order = $_SESSION['order'];
        if (!array_key_exists($order, $fields) && !array_key_exists(substr($order, 1), $fields)) {
            $order = '';
        }
    }
    if (!$order) {
        $order = '-1';
    }
    if ($_GET['order']) {
        $order = $_GET['order'];
        $_SESSION['order'] = $order;
    }
    $orderField = $order;
    if (strpos($order, '-') === 0) {
        $orderField = substr($order, 1);
        if (!is_numeric($orderField)) {
            $orderField = '`'.$orderField.'`';
        }
        $order = $orderField.' DESC';
    } else {
        if (!is_numeric($order)) {
            $order = '`'.$order.'`';
        }
    }

    $limit = $_POST['limit'] ? $_POST['limit'] : 200;
    $start = GET('start');
    $filter = $_REQUEST['filter'];
    $filter_id = POST('filter_id');
    $cut = POST('cut', 250);
    $wrap = POST('wrap', 50);
    $hsc = isset($_POST['cut'])? POST('hsc') : 1;
    $countAllOnly = isset($_POST['countAllOnly'])? POST('countAllOnly') : 0;

    $where = '';

    // если в поле where введено просто слово - считать это фильтром
    if (preg_match('~^[a-zа-я\d]+$~i', $_POST['where'], $a)) {
        $filter = $_POST['where'];
        $_POST['where'] = '';
    }
    // вот этот блок косячит если отправлять фильтр с таблицы
    // class="t-cover" id="recorddiv186001886" data-bgimgfield="img"
    // он считает что это типа запрос что ли
    /*if (preg_match('~( (or|and) |('.implode('|', $fieldNames).')\s*=")~', $filter)) {
        $_REQUEST['where'] = $_POST['where'] = $filter;
        $filter = '';
    }*/

    if ($filter != '') {
        $where = fieldsSearchWhere($fields, $filter, $_POST['filterField']);
    }
    if ($_REQUEST['where'] && !$_GET['tmode']) {
        $where = ' WHERE '.$_REQUEST['where'];
        saveInHistory('where-'.$_GET['table'], $_REQUEST['where']);
    }
    if ($filter_id) {
        if ($_POST['filterField']) {
            $where = ' WHERE `'.htmlspecialchars($_POST['filterField']).'`="'.$mysqli->real_escape_string($filter_id).'"';
        } else {
            $pks = array();
            foreach ($fields as $k => $v) {
                if ($v->Key == 'PRI') {
                    $pks []= '`'.$v->Field.'`='.$filter_id;
                }
            }
            $where = ' WHERE '.implode(' OR ', $pks);
        }
    }
    if ($_POST['replaceFrom']) {
        $q = 'update `'.$_GET['table'].'` set `'.$_POST['replaceField'].'`=REPLACE(`'.
            $mysqli->real_escape_string($_POST['replaceField']).'`, "'.
            $mysqli->real_escape_string($_POST['replaceFrom']).'", "'.
            $mysqli->real_escape_string($_POST['replaceTo']).'") '.$where;

        if ($res = query($q)) {
            msg('Замена выполнена! cnt='.$res.' sql='.htmlspecialchars($q));
        } else {
            error('Ошибка выполнения замены, либо ничего не заменилось cnt='.$res);
        }
    }
    if ($_POST['deleteByWhere'] && $where) {
        $sql = 'delete from `'.$_GET['table'].'` '.$where;
        msg('Выполните этот запрос:<br />'.$sql.'<br /><br />');
    }

    $sql = 'SELECT COUNT(*) AS c FROM '.$_GET['table'].$where;
    $v = getOne($sql);
    if (!$v) {
        error('Запрос вернул ошибку "'.htmlspecialchars($sql).'": '.$mysqli->error);
    }
    $countAll = $v['c'];

    // Показать только общее количество
    if ($countAllOnly) {
        $rows = addRow(array(
            '<div style="font-size:30px;">Всего '.$countAll.'</div>'
        ));
    // Сгенерировать всю таблицу
    } else {
        $pageLinks = generatePagesLinks($limit, $start, $countAll, 5);
        //$pageLinksBottom = generatePagesLinks($limit, $start, $countAll);

        $pks = array();
        foreach ($fields as $k => $v) {
            if ($v->Key == 'PRI') {
                $pks []= $v->Field;
            }
        }

        $tbl = $_GET['table'];
        $result = query($sql = '
        SELECT * FROM '.$_GET['table']."
        $where
        ORDER BY $order LIMIT ".($start * $limit).", $limit");
        $order = str_replace('`', '', $order);
        $rows = '';
        $printStyles = '';
        while ($result && $v = $result->fetch_object()) {
            $row = array();
            $ats = array();
            if ($pks) {
                $w = array();
                foreach ($pks as $pk) {
                    $w []= $pk.'='.$v->$pk;
                }
                $w = urlencode(implode(' AND ', $w));
                $row []= '<a href="#" data-delete="'.$w.'">x</a>&nbsp;'.
                    '<a href="'.url('tmode=edit&where='.$w).'">edit</a>&nbsp;<a href="'.url('tmode=add&where='.$w).'">c</a><a href="'.url('mode=export&where='.$w).'">e</a>';
            } else {
                $row []= 'no primary key';
            }
            $index = 0;
            foreach ($fields as $field => $p) {
                $index ++;
                $isn = is_numeric($v->$field);
                $length = mb_strlen($v->$field);
                $before = $after = '';
                if ($_GET['line'] && $length > 20) {
                    $v->$field = mb_substr($v->$field, 0, 20);
                }
                if ($v->$field === '') {
                    $ats [count($row)]= ' class="s"';
                    // $v->$field = '<span style="color:#ccc">s</span>';
                } else {
                    if ($v->$field && !$isn) {
                        $val = $v->$field;
                        if (mb_strrpos($val, ' ') === 0) {
                            $v->$field = mb_substr($v->$field, 1);
                            $before = '<span class="space">&nbsp;</span>';
                        }
                        if (mb_strrpos($val, ' ') === $length - 1) {
                            $v->$field = mb_substr($v->$field, 0, -1);
                            $after = '<span class="space">&nbsp;</span>';
                        }
                    }
                    if ($hsc) {
                        $v->$field = htmlspecialchars($v->$field);
                        $v->$field = urldecode($v->$field);
                    }
                    if ($wrap > 0) {
                        $v->$field = wordwrap($v->$field, $wrap, "\n", 1);
                    }
                    if ($cut > 0 && $length > $cut) {
                        $all = mb_substr($v->$field, $cut);
                        $v->$field = mb_substr($v->$field, 0, $cut).' <a href="#" class="nsh ns-hide">еще</a><span>'.$all.'</span>';
                    }
                    if ($isn && is_int($v->$field) && strlen($v->$field) == 10) {
                        $v->$field = '<span style="font-size:11px; color:green">'.date('Y-m-d H:i:s', $v->$field).'</span>';
                    }
                }
                if ($field == 'timestamp' && is_numeric($v->$field)) {
                    $v->$field = '<span style="color:blue">'.date('Y-m-d H:i:s', $v->$field).'</span>';
                }
                if ($field == 'alias' && strpos($tbl, '_content') !== false) {
                    $v->$field = '<a href="/'.$v->$field.'" target="_blank">'.$v->$field.'</a>';
                }
                if ($field == $order) {
                    $printStyles = 'table.data tr td:nth-child('.($index+1).') {white-space:normal}';
                }
                $row []= $before . $v->$field . $after;
            }
            $rows .= addRow($row, 'td', '', $ats);
        }
    }

    function fieldOpts($fields, $checked=false)
    {
        $fieldOpts = '<option value="">поле</option>';
        foreach ($fields as $field => $p) {
            $selected = '';
            if ($checked !== false && $checked == $field) {
                $selected = ' selected';
            }
            $fieldOpts .= '<option'.$selected.'>'.$field.'</option>';
        }
        return $fieldOpts;
    }
    $fieldOpts = fieldOpts($fields);

    $ths = '';
    $fullheaders = !$_GET['shortheaders'] || count($fields) < 10;
    foreach ($fields as $k => $v) {
        if ($fullheaders) {
            $header = $k;
        } else {
            $header = preg_replace('~(?<=.)_(?=.)~', '<br />', $k);
        }
        if (strpos($v->Type, 'float') !== false) {
            $style = 'color:#9900CC';
        } elseif (strpos($v->Type, 'int') > 0) {
            $style = 'color:#00CC00';
        } elseif (strpos($v->Type, 'int') !== false) {
            $style = 'color:#006600';
        } elseif (strpos($v->Type, 'datetime') !== false) {
            $style = 'color:#996600';
        } elseif ($v->Type == 'text') {
            $style = 'color:gray';
        } else {
            $style = '';
        }
        $url = EXP.'?action=tables&table='.$_GET['table'].'&order='.($order == $k ? '-'.$k : $k);
        if ($_POST['filter']) {
            $url .= '&filter='.urlencode($_POST['filter']);
        }
        if ($_REQUEST['where']) {
            $url .= '&where='.urlencode($_REQUEST['where']);
        }
        // можно добавить все гет-параметры кроме order where filter
        if ($_GET['start']) {
            $url .= '&start='.$_GET['start'];
        }
        if ($_GET['shortheaders']) {
            $url .= '&shortheaders='.$_GET['shortheaders'];
        }
        $ths .= '<th align="center"'.($orderField == $k ? ' style="background-color:#ccc;"' : '').'><a title="'.$v->Type.($v->Null=='YES'?' NULL':''). ($v->Default?' Default:'.$v->Default.'':'').'" style="'.$style.'" href="'.$url.'">'. $header.'</a></th>';
    }

    ob_start();
if (GET('tmode') != 'addField') {
    tableTitle('Просмотр данных', $countAll, $tables);
}
?>

<style>
<?php echo $printStyles; ?>
</style>

<?=$pageLinks?>

<form method="post" id="filter-form">

<span><input type="text" name="where" placeholder="where условие" value="<?=htmlspecialchars($_REQUEST['where'])?>"
    style="margin-bottom:5px;" id="whereField"  />
<?php
if ($_REQUEST['where']) {
	echo '<label><input type="checkbox" name="deleteByWhere" value="1"> Удалить все по условию</label> ';
}
?></span>

<input type="text" name="filter" placeholder="Поиск" style="width:250px;" value="<?php echo htmlspecialchars($filter)?>" onfocus="this.style.width='500px'; $(this).next().show()" />
<select name="filterField" style="padding:1px;"><?=fieldOpts($fields, $_POST['filterField'])?></select>
<input type="text" name="filter_id" id="filter_id_id" style="width:50px;" placeholder="ID" value="<?php echo $filter_id?>" />

<input type="submit" value="Применить" />

<a href="#" class="nsh" title="Замена будет происходить с учетом текущего where, так же как и удаление!">Замена</a><span>
    <input type="text" name="replaceFrom" placeholder="Искать.." /> <select name="replaceField"><?=$fieldOpts?></select>
    <input type="text" name="replaceTo" placeholder="заменить на" /></span>

<a href="#" class="nsh">Uniq</a><span> <select name="fieldUniq"><?=$fieldOpts?></select>
    <input type="text" name="havingCnt" value="<?=$_POST['havingCnt']?>" placeholder="cnt >" style="width:30px;" />
    <input type="text" name="rx-uniq" value="<?=$_POST['rx-uniq']?>" placeholder="Regexp поиск uniq совпадений" />
    <input type="submit" name="showUniqs" value="Показать уникальные значения" /></span>

<a href="#" class="nsh">Assoc</a><span><select name="assoc1"><?=$fieldOpts?></select> => <select name="assoc2"><?=$fieldOpts?></select> </span>

<a href="#" class="nsh">Opts</a><span> &nbsp;
<?php
$url = EXP.'?action=tables&table='.GET('table');
?>
wrap: <input type="text" name="wrap" value="<?php echo $wrap?>" size=2 />
limit: <input type="text" name="limit" value="<?php echo $limit?>" size=2 />
cut: <input type="text" name="cut" value="<?php echo $cut?>" size=2 />
<label><input type="checkbox" name="hsc" value="1" <?php echo ($hsc?' checked':'')?>/>
    htmlspecialchars</label>
<label><input type="checkbox" name="countAllOnly" value="1" <?php echo ($countAllOnly?' checked': '')?>/> count(*)</label>
</span>

<a href="<?=$url?>&mode=add&tmode=add">Добавить строку</a>
<a href="<?=$url?>&mode=add&tmode=addField">Добавить поле</a>

<a href="#" class="nsh ns-hide">Batch</a>
<div>
    <b>Массовая обработка</b>
    поле
    <select name="multy"><?=$fieldOpts?></select>
    <?php
    $funcs = explode(',', 'preg_replace,strtolower');
    echo '<select class="nsh'.($_POST['multy_func'] ? ' ns-opened' : '').' " name="multy_func"> <option value="">функция</option>';
    foreach ($funcs as $k => $v) {
        $selected = '';
        if ($_POST['multy_func'] == $v) {
        	$selected = ' selected';
        }
        echo ' <option'.$selected.'>'.$v.'</option>';
    }
    echo '</select>';
    ?>
    <span><input type="text" name="param1" value="<?=$_POST['param1']?>" placeholder="param1" /> <input type="text" name="param2" value="<?=$_POST['param2']?>" placeholder="param2" /></span>
</div>
</form>

<table class="optionstable data<?=$_GET['shortheaders'] ? ' shortheaders' : ''?> mb-3">
<tr>
<th><?php if ($_GET['line'] || $_GET['shortheaders']) { ?> <a href="<?=$url?>" class="act">reset</a> <?php } else { ?><a href="<?=$url?>&shortheaders=1" class="act">short</a> <?php } ?></th>
<?=$ths?>
</tr>
<?php echo $rows?>
</table>
<?php
echo $pageLinks;

$pageContent .= ob_get_contents();
ob_end_clean();
}




// Сравнение таблицы section
if ($_GET['tmode'] == 'compareTables') {
    ob_start();
    ?>
    <h2>Выбрать таблицы для сравнение</h2>
    <form method="post">
        <select name="tables[]" multiple style="height:400px; font-size:11px;">
        <?php
        foreach ($tables as $table => $v) {
            $add = '';
            if ($_POST['tables'] && in_array($table, $_POST['tables'])) {
                $add = ' selected';
            }
            echo ' <option'.$add.'>'.$table.'</option>';
        }
        ?>
        </select>
        <p><input type="text" name="field" value="<?=POST('name')?>" placeholder="Поле для сравнения" />
        обрезать выводимое значение до
        <input type="text" name="cut" value="<?=POST('cut', 50)?>" />
        </p>
        <p><input type="submit" value="Сравнить" /></p>
    </form>
    <?php

    if ($_POST['tables']) {
        $t1 = $_POST['tables'][0];
        $t2 = $_POST['tables'][1];
        $output = compareData($t1, $t2, $_POST['field'], $_POST['cut'], $msg);
        if ($msg) {
            echo '<div>'.$msg.'</div>';
        }
        echo $output;
    }
    $pageContent = ob_get_contents();
    ob_end_clean();
}




// Создание таблицы section
if ($_GET['tmode'] == 'createTable') {
    if ($_POST) {
        $data = array();
        foreach ($_POST as $name => $values) {
            if ($name == 'tableName') {
                continue;
            }
            foreach ($values as $key => $val) {
                $data [$key][$name] = $val;
            }
        }
        $res = createTable($_POST['tableName'], $data, $sql, $msg);
        msg($msg, '', $sql);
        if ($res) {
            redirect(EXP.'?action=tables&table='.$_POST['tableName'], 1);
        }

    } else {
        $data [0]= array();
    }

    ob_start();
    ?>

<div class="container">
    <h2>Создание таблицы</h2>
    <form method="post">
        <input type="text" name="tableName" value="<?=$_POST['tableName']?>" placeholder="Название таблицы" class="form-control" />
        <hr />
        <div id="createTable">
        <?php
        foreach ($data as $key => $defaults) {
            if ($key == 0) {
                echo '<div id="firstField">';
            }
            fieldForm($defaults, [], $key);
            if ($key == 0) {
                echo '<hr /></div>';
            }
        } ?>
        </div>
        <input class="btn btn-primary" type="submit" value="Создать таблицу" style="margin-right:30px;">
        <input class="btn btn-primary" type="button" onclick="addNewField(); return false;" value="Добавить поле">
    </form>
</div>
<?php
    $pageContent = ob_get_contents();
    ob_end_clean();
}


// Операции с базой данных
if ($database && $mysqli && $_GET['doit']) {
    if ($_GET['doit'] == 'drops') {
        $t = '';
        foreach ($tables as $k => $v) {
            $t .= 'DROP TABLE `'.$v->Name.'`;'."\n";
        }

        $pageContent = textarea($t);

    } elseif ($_GET['doit'] == 'optimizes') {
        $t = '';
        foreach ($tables as $k => $v) {
            $t .= 'OPTIMIZE TABLE `'.$v->Name.'`;'."\n";
        }
        $pageContent = textarea($t);

    } elseif ($_GET['doit'] == 'charsets') {
        $t = '';
        foreach ($tables as $k => $v) {
            $t .= 'ALTER TABLE `'.$v->Name.'` CONVERT TO CHARACTER SET cp1251 COLLATE cp1251_general_ci;'."\n";
            $t .= 'ALTER TABLE `'.$v->Name.'` DEFAULT CHARACTER SET cp1251 COLLATE cp1251_general_ci;'."\n";
        }
        $pageContent = textarea($t);

    } elseif ($_GET['doit'] == 'txt') {
        $t = '';
        foreach ($tables as $k => $v) {
            $t .= $v->Name.' '.$v->Rows."\n";
        }
        $pageContent = textarea($t);

    } elseif ($_GET['doit'] == 'renames') {
        $t = '';
        foreach ($tables as $k => $v) {
            $t .= 'ALTER TABLE `'.$v->Name.'` RENAME TO `xxx_'.$v->Name.'`;'."\n";
        }
        $pageContent = textarea($t);
    } elseif ($_GET['doit'] == 'process-list') {
        $data = getData('SHOW FULL PROCESSLIST');
        $rows = addRow(array('', 'id', 'user', 'host', 'db', 'command', 'time', 'status', 'sqlQuery'), 'th');
        foreach ($data as $name => $value) {
            $row = array(
                '<a href="'.$_SERVER['REQUEST_URI'].'&kill=' . $value['Id'] . '" class="btn-close"></a>',
                $value['Id'],
                $value['User'],
                $value['Host'],
                (empty($value['db']) ? '<i>нет</i>' : $value['db']),
                $value['Command'],
                $value['Time'],
                (empty($value['State']) ? '---' : $value['State']),
                (empty($value['Info']) ? '---' : $value['Info'])
            );
            $rows .= addRow($row);
        }
        $pageContent = '<h2>Список процессов</h2><table class="table table-condensed small">'.$rows.'</table>';
    } elseif ($_GET['doit'] == 'full') {
        $data = getData('SHOW TABLE STATUS');
        $rows = addRow(array_keys($data[0]), 'th');
        foreach ($data as $row) {
            $rows .= addRow($row);
        }
        $pageContent = '<h2>Table Status</h2><table class="table table-condensed small">'.$rows.'</table>';
    }
}



// Список таблиц section
if (GET('action') == 'tables' && $database && $mysqli && count($_GET) == 1) {
    $timeTo = time() - $_POST['days']*86400 - $_POST['hours']*3600 - $_POST['minutes']*60;
    if ($_POST['time']) {
        $timeTo = strtotime($_POST['time']);
    }
    $rows = '';
    $countAll = $totalSizes = 0;
    foreach ($tables as $k => $v) {
        $countAll ++;
        // echo '<br />optimize table `'.$v->Name.'`;';
        if ($v->Update_time) {
            $time = strtotime($v->Update_time) + 3600 * 4 - date('Z');
            if ($_POST['showChanged'] && $time < $timeTo) {
                continue;
            }
            $updateTime = date2rusString('d.m.Y H:i', $time);
            if (strpos($updateTime, 'дня') !== false || strpos($updateTime, 'ера') !== false) {
                $updateTime = "<b>$updateTime</b>";
            }
        } else {
            $updateTime = '-';
        }
        if ($_POST['showFiltered'] && $_POST['filter_table'] && strpos($v->Name, $_POST['filter_table']) === false) {
            continue;
        }
        $size = $v->Data_length + $v->Index_length;
        $totalSizes += $size;
        $url = '?action=tables&table='.$v->Name.'';
        $rows .= addRow(array(
            '<div style="text-align:left;"><a href="'.$url.'"'.getTableStyle($v->Rows).'>'.$v->Name.'</a></div>',
            $v->Rows,
            '<span'.getTableStyle($v->Rows).'>'.formatSize($size).'</span>',
            $updateTime,
            '<span style="color:#ccc">'.$v->Engine.'  '.date('Z').'</span>',
            substr($v->Collation, 0, strpos($v->Collation, '_')),
            '<div style="text-align:left;"><a href="'.$url.'&mode=fields">Структура</a>
            <a href="#" title="Показать другие действия"
                onmouseover="this.nextSibling.style.display=\'inline\'">≡</a>'.
            '<span style="display:none;">
            <a href="#" onclick="if (confirm(\'Удалить '.$v->Name.'?\')) jsquery(\'mode=delete\', {table: \''.$v->Name.'\', hide: $(this).closest(\'tr\')}); return false;">удал</a>
            <a href="'.$url.'&mode=truncate" onclick="if (!confirm(\'Очистить '.$v->Name.'?\')) return false;">очист</a>
            <a href="#" onclick="if (t=prompt(\'Введите новое название\', \''.$v->Name.'\')) {this.href=\''.$url.'&mode=rename&newName=\'+t; } else {return false;}">'. 'rename</a>
            </span></div>'
        ));
    }

    ob_start();
    ?>    <h2>Список таблиц <?=$database?> (<?=$countAll?>)
        <a href="?action=tables&doit=drops" style="font-size:11px;">Drop-запросы</a>
        <a href="?action=tables&doit=optimizes" style="font-size:11px;">optimize</a>
        <a href="?action=tables&doit=charsets" style="font-size:11px;">charsets</a>
        <a href="?action=tables&doit=renames" style="font-size:11px;">renames</a>
        <a href="?action=tables&doit=txt" style="font-size:11px;">txt-list</a>
        <a href="?action=tables&doit=process-list" style="font-size:11px;">process-list</a>
        <a href="?action=tables&doit=full" style="font-size:11px;">full-data</a>

        </h2>
    <div style="margin-bottom:10px;">
        <form method="post" style="font-size:12px;">
            <input type="text" name="filter_table" style="width:200px;" value="<?=htmlspecialchars($_POST['filter_table'])?>" placeholder="Фильтр таблиц" />
            <input type="submit" name="showFiltered" value="Ок" />
            &nbsp;
            <a href="/<?=EXP?>?action=tables&tmode=createTable">Создать таблицу</a>
            &nbsp;
            <a href="/<?=EXP?>?action=tables&tmode=compare&table=&table2=">Сравнение таблиц</a>
            &nbsp;
            <a href="/<?=EXP?>?action=tables&tmode=compareTables">Сравнение полей таблиц</a>
            &nbsp;
            Показать измененные за
            <input type="text" name="days" value="<?=POST('days')?>" style="width:40px;" />
            дней
            <input type="text" name="hours" value="<?=POST('hours')?>" style="width:40px;" />
            часов
            <input type="text" name="minutes" value="<?=POST('minutes')?>" style="width:40px;" />
            минут
            или с
            <input type="text" name="time" placeholder="<?=date('Y-m-d H:i:s', time() - 86400)?>" value="<?=$_POST['time'] ? $_POST['time'] : date('Y-m-d 00:00:00')?>" />
            <input type="submit" name="showChanged" value="Ок" />
        </form>
    </div>

    <table class="table table-hover table-condesed small">
    <tr>
    <th align="center" valign="top">Таблица</th>
    <th align="center" valign="top">Рядов</th>
    <th align="center" valign="top">Размер</th>
    <th align="center" valign="top">Дата</th>
    <th align="center" valign="top">Engine</th>
    <th align="center" valign="top">Кодировка</th>
    <th align="left" valign="top">Действия</th>
    </tr>
    <?php echo $rows; ?>
    </table>
    <p>Общий размер <?=formatSize($totalSizes)?></p>
<?php
    $pageContent = ob_get_contents();
    ob_end_clean();
}













// Размеры папок section

if (GET('action') == 'sizeFolders') {
    $dir = getRoot();
    $dir = str_replace(getcwd().'/', '', $dir);
    if (!$dir) {
        $dir = '.';
    }

    $a = scandir($dir);
    $size = 0;
    $files = $sort = array();
    foreach ($a as $k => $v) {
        if ($v == '.' || $v == '..') {
            continue;
        }
        $isDir = is_dir($dir.'/'.$v);
        $files []= $v;
        $sort []= !$isDir;
        if (!$isDir) {
            $size += filesize($dir.'/'.$v);
        }
    }
    array_multisort($sort, SORT_ASC, SORT_NUMERIC, $files);


    $lines = '<div style="color:green; font-weight:bold;">Root: '.$dir.'</div>
    <table class="table table-condensed table-hover small">';
    foreach ($files as $k => $v) {
        $add = $v;
        if (is_dir($dir.'/'.$v)) {
            $lines .= addRow(array(
                ' <a href="?action=sizeFolders&root='.$dir.'/'.$v.'">'.$v.'</a>',
                '<span class="calcSize" data-folder="'.$v.'" style="color:#eee; white-space:nowrap;">loading...</span>',
                '<a href="?download=1&folder='.$dir.'/'.$v.'&downloadOnly=1" style="width:100px; text-align:right; font-size:12px;">Скачать</a>'
            ));
        } else {
            $fs = filesize($dir.'/'.$v);
            $lines .= addRow(array(
                $v,
                '<span data-size="'.$fs.'">'.formatSize($fs).'</span>',
                ''
            ));
        }
        //$lines .= '<tr class="cell">'.$add.'</tr>';
    }
    $lines .= '</table>';

    ob_start();
    echo $lines;
    ?>
    <style type="text/css">
    </style>
    <hr />
    <div id="size-folders" data-root="<?=$dir?>" class="mb-2">
        Total: <span id="total" title="<?=$size?>"><?=$size?></span>
        <span style="color:#ccc; margin-left:20px; font-size:12px;">Files: <?=formatSize($size)?></span>
    </div>
    <div>
        <div class="mb-2">Список файлов и папок размером больше  (мб) <input type="text" class="form-control form-control-xs" id="sizeLimit" value="100" style="width: 100px; display: inline"> <input type="button" class="btn btn-primary btn-xs" onclick="SizeFolders.bigFolders()" value="Показать">      </div>
        <input type="text" id="bigFolders" style="width: 90%" class="form-control form-control-xs" >
    </div>
    <?php
    $pageContent = ob_get_contents();
    ob_end_clean();
}







if ($_GET['action'] == 'upload') {
    ob_start();
    if ($_GET['access_log']) {

    $lines = file($_GET['access_log']);
    $lines = array_reverse($lines);

    foreach ($lines as $k => $line) {

        preg_match('~\d{1,255}\.\d{1,255}\.\d{1,255}\.\d{1,255}~i', $line, $a);
        $ip = $a[0];
        preg_match('~(POST|GET) (.*?) HTTP/[\d.]*" (\d+)~i', $line, $a);
        $method = $a[1];
        $url = $a[2];
        $status = $a[3];
        preg_match('~\[(.*?)\]~i', $line, $a);
        $time = strtotime($a[1]);
        if ($_GET['ip'] && $_GET['ip'] != $ip) {
            continue;
        }

        $style = '';
        if ($method == 'POST') {
        	$style = 'color:violet';
        }
        //echo '<br />'.trim($line);
        if ($status != 200) {
        	$style = 'color:red; ';
        }
        echo '<div style="'.$style.'">'.date('Y-m-d H:i:s', $time).' '.$status.' <b><a href="/'.EXP.'?access_log='.$_GET['access_log'].'&ip='.$ip.'">'.$ip.'</a></b> '.$url.'</div>';
    }
}


// Аплоад файлов в папки section
if (GET('action') == 'upload') {

    if (!isset($_SESSION['folders'])) {
    	$_SESSION['folders'] = array();
    }

    $dir = '.';
    if ($_GET['folder']) {
    	$dir = $_GET['folder'];
        if (!in_array($dir, $_SESSION['folders'])) {
        	$_SESSION['folders'][] = $dir;
        }
    }

    if ($_GET['createFolder']) {
    	mkdir($dir.'/'.$_GET['createFolder']);
    }
    if ($_GET['createFile']) {
        fwrite($a = fopen($dir.'/'.$_GET['createFile'], 'w+'), ''); fclose($a);
    }

    if ($_POST['action'] == 'removeFolder') {

        if ($_POST['folder']) {
            echo '<br />Удаление папки <b>'.$_POST['folder'].'</b>';
            if (file_exists($_POST['folder'])) {
                removeDir($_POST['folder']);
            } else {
                echo '<br />Такой папки не существует';
            }

        }
    }

    if ($_POST['action'] == 'copyFolder') {

        function copyDir($copyFrom, $copyTo, $level=0)
        {
            $a = scandir($copyFrom);
            foreach ($a as $k => $v) {
                if ($v == '.' || $v == '..') {
                    continue;
                }
                $pathFrom = $copyFrom .'/'. $v;
                $pathTo = $copyTo .'/'. $v;
                if (is_dir($pathFrom)) {
                    if (!file_exists($pathTo)) {
                        $perm = substr(decoct(fileperms($pathFrom)), 1, 4);
                         if ($_POST['showList']) {
                            echo '<br />mkdir '.$pathTo;
                        } else {
                            eval('mkdir($pathTo, '.$perm.');');
                        }
                    }
                    copyDir($pathFrom, $pathTo, $level+1);
                } else {
                    if (file_exists($pathTo)) {
                        continue;
                    }
                    if ($_POST['showList']) {
                        echo '<br />copy("'.$pathFrom.'", "'.$pathTo.'") ';
                    } else {
                        copy($pathFrom, $pathTo);
                    }
                }
            }
        }

        $copyFrom = $_POST['folder'];
        $copyTo = $_POST['folderTo'];
        if (!file_exists($copyFrom)) {
            echo '<div>Не существует пути '.$copyFrom.'</div>';
            exit;
        }
        if (!file_exists($copyTo)) {
            if (!mkdir($copyTo)) {
                echo '<div>Не существует пути '.$copyTo.', создать не смог</div>';
                exit;
            }
        }
        copyDir($copyFrom, $copyTo);
    }

    if ($_POST['action'] == 'moveAll') {

        $folderCurrent = $_GET['folder'] ?: '.';

        $files = scandirex($folderCurrent);

        $folderTo = $folderCurrent.'/'.$_POST['folder'];
        if (!file_exists($folderTo)) {
            mkdir($folderTo);
        }

        $exclude = explode(',', $_POST['excludeFiles']);
        foreach ($files as $k => $v) {
            if (in_array($v['name'], $exclude) || $v['name'] == $_POST['folder']) {
                continue;
            }
            $renameTo = $folderTo.'/'.$v['name'];
            rename($v['path'], $renameTo);
        }
    }

    if ($_POST['action'] == 'multyActions' && $_POST['subaction']) {
        $files = scandirex($_POST['folder'], 'path');
        $stat = array();
        foreach ($files as $k => $v) {
            if ($_POST['subaction'] == 'delete') {
               if (unlink($v)) {
                    $stat ['Удалено файлов'] ++;
               } else {
                    $stat ['Ошибка удаления файла'] ++;
               }
            }
            if ($_POST['subaction'] == 'setPerms' && $_POST['permValue']) {
                if (chmod($v, $_POST['permValue'])) {
                    $stat ['Изменены права'] ++;
                } else {
                    $stat ['Ошибка изменения прав'] ++;
                }
            }
        }
        echo '<h3>Результаты обработки</h3>';
        echo '<pre>'; print_r($stat); echo '</pre>';
    }

    // Загружаем если нужно
    $uploaded = array();
    if ($_FILES['files'] && $_FILES['files']['name'][0]) {
        $output = '';
        $maxFiles = ini_get('max_file_uploads');
        foreach ($_FILES['files']['name'] as $k => $name) {
        	$error = $_FILES['files']['error'][$k];
            if ($error) {
            	$output .= ' <div style="color:red; margin-bottom:5px;">ошибка загрузки '.$name.' - '.fileUploadError($error).'</div>';
                continue;
            }
        	$tmp = $_FILES['files']['tmp_name'][$k];
            $output .= ($k+1).') загрузка <input type="text" class="focusselect small" style="width:300px;" value="'.htmlspecialchars($name).'" /> ';
            $dest = $dir.'/'.$name;
            $exists = file_exists($dest);
            if ($exists && $_POST['checkExist']) {
            	$output .= ' файл уже существует<br />';
            } elseif (move_uploaded_file($tmp, $dest)) {
                $uploaded []= $name;
                if ($exists) {
                	$output .= ' перезаписан успешно!<br />';
                } else {
                    $output .= ' сохранен успешно!<br />';
                }
            } else {
        	    $output .= ' ошибка перемещения!<br />';
            }
        }
        if (count($_FILES['files']['name']) == $maxFiles) {
            $output = '<div class="alert alert-danger">Внимание! Вероятно, не все файлы загрузились, т.к. есть ограничение на макс количество файлов аплоада ('.$maxFiles.')</div>'. $output;
        }
        if (@$_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
            exit(json_encode(array('output' => $output)));
        }
        if ($output) {
            echo '<div style="padding:10px 0 20px;">'.$output.'</div>';
        }
    }

    function getPrintName($v, $uploaded)
    {
        $add = '';
        if ($uploaded && in_array($v['name'], $uploaded)) {
        	$add = ' style="font-weight:bold; font-size:16px;"';
        }
        $acts = '';
        if (strpos($v['name'], 'zip')) {
        	$acts = '<span class="unzip">unzip</span> <span class="unzipdel">unzip+del</span>';
        }
        if (preg_match('~\.(txt|css|js|php|html?)$~', $v['name'])) {
        	$acts .= ' <span class="fileEdit">edit</span>';
        }
        if ($v['name'] == 'access_log') {
        	$acts .= ' <span><a style="font-weight:bold; color:green" href="?access_log='.$v['path'].'">чтение логов</a></span>';
        }
        if (preg_match('~[а-я]~i', $v['name'])) {
        	//$v['name'] = iconv('utf-8', 'windows-1251', $v['name']);
        }
        if (!$v['name']) {
        	$v['name'] = '[empty name]';
        }
        if (strpos($_GET['folder'], '..') === 0) {
            $a = '<a '.$add.' class="link">'.$v['name'].'</a>';
        } else {
            $a = '<a href="'.$v['path'].'" target="_blank"'.$add.' class="link">'.$v['name'].'</a>';
        }
        return '<div class="hv file">'.$a.
        '<span class="fileDel">del</span><span class="fileRename">ren</span><span class="fileMove">move</span><span class="fileStat">stat</span><span class="fileDownload">скачать</span><span><a href="?raw='.$v['path'].'">view</a></span><span class="fileInputs">инпуты</span> '.$acts.'</div>';
    }

    // Список
    function foldersSimple($files, $uploaded)
    {
        echo '<div class="folders-simple file-hover">';
        foreach ($files as $k => $v) {
            //$v['name'] = iconv('windows-1251', 'utf-8', $v['name']);
            if ($v['is_dir']) {
                $url = '/'.EXP.'?action=upload&folder='.$v['path'].'';
            	echo '<div class="hv folder"><a class="link" href="'.$url.'">'.$v['name'].'</a><span class="fileDel">del</span><span class="fileRename">ren</span><span class="fileMove">move</span></div>';
            } else {
                echo getPrintName($v, $uploaded);
            }
        }
        echo '</div>';
    }

    // Таблица
    function foldersTable($files, $uploaded)
    {
        echo '
        <table class="folders-table file-hover">';
        $fields = array(
            'namedir' => 'Название',
            'size' => 'Размер',
            'ctime' => 'Дата создания',
            'mtime' => 'Дата изменения',
            'wh' => 'w*h'
        );
        foreach ($fields as $k => $v) {
        	$fields [$k] = '<a href="/'.EXP.'?action=upload&folder='.$_GET['folder'].'&mode=table&sort='.$k.($_GET['sort'] == $k && !$_GET['dir'] ? '&dir=desc' : '').'">'.$v.'</a>';
        }
    	echo addRow($fields, 'th');
        $u = '/'.EXP.'?action=upload&mode=table&folder='.$_GET['folder'].'/';
        if ($_GET['sort']) {
        	sortBy($files, $_GET['sort'], $_GET['dir'] ? SORT_DESC : SORT_ASC, $_GET['sort'] == 'namedir' ? SORT_STRING : SORT_NUMERIC);
        }
        foreach ($files as $k => $v) {
            if ($v['is_dir']) {
            	$name = '<a class="link" href="'.$u.$v['name'].'">'.$v['name'].'</a>';
            	$name = '<div class="hv folder">'.$name.'<span class="fileDel">del</span><span class="fileRename">ren</span><span class="fileMove">move</span></div>';
            } else {
                $v['size'] = formatSize($v['size']);
                $name = getPrintName($v, $uploaded);
            }

            $w = $h = '';
            if (preg_match('~(jpe?g|png|gif|bmp)$~i', $v['name'])) {
            	list($w, $h) = getimagesize($v['path']);
            }

            $ctime = filectime($v['path']);
            $mtime = filemtime($v['path']);
        	echo addRow(array(
                $name,
                $v['size'],
                date('Y-m-d H:i:s', $v['ctime']),
                $v['mtime'] != $v['ctime'] ? date('Y-m-d H:i:s', $v['mtime']) : '-',
                substr(decoct(fileperms($v['path'])), -3),
                $w ? "$w * $h" : ''
            ));
        }
        echo '</table>';
    }

    // Полный обзор
    function foldersReview($dir, $uploaded=array())
    {
        $a = scandir($dir);

        $sort_order = array();
        $files = array();
        foreach ($a as $k => $v) {
            $path = $dir.'/'.$v;
            if ($v == '.' || $v == '..') {
                continue;
            }
            $w = $h = '';
            if (!is_dir($path)) {
            	list($w, $h) = getimagesize($path);
            }
            $sort_order [$k]= $w;
            $files [$k] = array(
                'dir' => is_dir($path),
                'name' => $v,
                'width' => $w,
                'height' => $h,
                'path' => $path
            );
        }

        array_multisort($sort_order, SORT_ASC, SORT_NUMERIC, $files);

        $lastW = '';
        foreach ($files as $k => $v) {
            if ($v['dir']) {
                continue;
            }
            $w = ceil($v['width'] / 20);
            $wMlt = $w*20;
            $maxW = 100;
            if ($_GET['size']) {
            	$maxW = 300;
            }
            if ($_GET['size'] && $wMlt != $_GET['size']) {
                continue;
            }
            if ($w != $lastW ) {
                if ($lastW) {
                    echo '<hr />';
                }
            	echo '<h1><a href="'.$_SERVER['REQUEST_URI'].'&size='.$wMlt.'">'.$wMlt.'</a></h1>';
            }
        	echo '<img src="'.$v['path'].'" alt="" style="max-heigth:'.$maxW.'px; max-width:'.$maxW.'px; " title="'.$v['name'].'" data-info="'.$v['width'].'x'.$v['height'].' '.formatSize(filesize($path)).'" />';
            $lastW = $w;
        }

        if (!$_GET['size']) {
            echo '<hr />';
            foreach ($files as $k => $v) {
                if (!$v['dir']) {
                    continue;
                }
                $b = scandir($v['path']);
                echo '<h4><a href="/'.EXP.'?action=upload&folder='.$v['path'].'&review=1">'.$v['name'].'</a> <span style="color:green; background-color:#FFFFCC;">'.(count($b)-2).'</span></h4>';
                foreach ($b as $key => $subImg) {
                    $subPath = $v['path'].'/'.$subImg;
                    if ($subImg == '.' || $subImg == '..') {
                        continue;
                    }
                    echo '<img src="'.$subPath.'" alt="" style="max-heigth:100px; max-width:100px; " title="'.$v['path'].'/'.$subImg.'" />';
                    if ($key > 7) {
                        break;
                    }
                }
            }
        }
        return ;
    }

    // Список папок
    $a = scandir($dir);
    $files = $isDirs = array();
    foreach ($a as $k => $v) {
        if ($v == '.' || $v == '..') {
            continue;
        }
        $path = $v;
        if ($dir != '.') {
        	$path = $dir.'/'.$v;
        }
        $isDir = is_dir($path);
        $sort = intval(!$isDir).'-'.$v;

        $files []= array(
            'name' => $v,
            'namedir' => ($isDir ? '-' : '').$v,
            'path' => $path,
            'is_dir' => $isDir,
            'sort' => $sort,
            'size' => $isDir ? '' : filesize($path),
            'ctime' => filectime($path),
            'mtime' => filemtime($path)
        );
        $isDirs []= $sort;
    }
    array_multisort($isDirs, SORT_ASC, SORT_STRING, $files);

    $topFolder = '..';
    if ($_GET['folder'] && $_GET['folder'] != '.') {
        if (strpos($_GET['folder'], '/') === false) {
        	$topFolder = '.';
        } else {
            $topFolder = preg_replace('~/.*$~i', '', $_GET['folder']);
        }
    }
    ?>


    <div class="previewBlock">
    <img src="" id="previewImg" onclick="this.src=''; return false;" alt=""  />
    &nbsp;
    <?php if ($uploaded) { ?>
    <input type="text" class="focusselect" id="copyField" value="<?=$uploaded[0]?>" />
    <input type="text" class="focusselect" id="copyFieldTag" value="<?=$uploaded[0] ? '<img src="'.$uploaded[0].'" alt="" />' : ''?>" /> <?php } ?>
    <input type="text" class="focusselect" id="copyName" />
    <input type="text" class="focusselect" id="copyFileName" />
    <div id="fileinfo"></div>
    </div>

    <div class="fileUpload">
        <form enctype="multipart/form-data" method="post" class="mb-2">
            <input type="file" name="files[]" data-class="form-control form-control-sm" onchange="checkFiles(this)" multiple />
            <span id="uploadBlock" style="display:none;">
            <label><input type="checkbox" checked name="checkExist" value="1"> не затирать</label>
            <input type="submit" value="Загрузить" class="btn btn-sm btn-danger" />
            </span>
            &nbsp;

            <input type="button" class="btn btn-sm btn-primary" onclick="if (t=prompt('Имя папки')) location='/<?=EXP?>?action=upload&folder=<?=$_GET['folder']?>&createFolder='+t; return false;" value="Создать папку" />
            <input type="button" class="btn btn-sm btn-primary" onclick="if (t=prompt('Имя файла')) location='/<?=EXP?>?action=upload&folder=<?=$_GET['folder']?>&createFile='+t; return false;" value="Создать Файл" />

            <div class="row mt-2 mb-2 small" style="display:none">
                <div class="col-sm-3">
                    <input type="text" name="excludeDirs" class="form-control form-control-sm" placeholder="Исключить папки" />
                </div>
                <div class="col-auto">
                    <label><input type="checkbox" class="form-check-input" checked name="downloadOnly" value="1"> только скачать</label>
                    <label><input type="checkbox" class="form-check-input" name="fromRoot" value="1"> путь от корня</label>
                    <label><input type="checkbox" class="form-check-input" checked name="removeExist" value="1"> удалить если существует</label>
                    <input type="button" onclick="folderArchive(this);" class="btn btn-sm btn-danger ms-2" value="Скачать папку" />
                </div>
            </div>
            <input type="button" class="btn btn-sm btn-primary" onclick="$(this).hide(); $(this).prev().show()" value="Архив папки" />

            <input type="button" class="btn btn-sm btn-primary" onclick="location='/<?=EXP?>?action=sizeFolders&root=<?=$_GET['folder']?>'; return false;" value="Размер папки" />

            <span class="text-danger small">
                Max upload size <b><?=ini_get('upload_max_filesize')?></b>
                Post max size <b><?=ini_get('post_max_size')?></b>
                Max file uploads <b><?=ini_get('max_file_uploads')?></b>
            </span>
        </form>

        <div style="width:80%; float:left;">
            <?php
            if ($_GET['folder']) {
                echo ' <b><a href="/'.EXP.'?action=upload">root</a></b> ';
            	$folders = explode('/', $_GET['folder']);
            	$folderPath = '';
                foreach ($folders as $folder) {
                    $folderPath .= $folder;
                    echo ' / ';
                    echo ' <a href="/'.EXP.'?action=upload&folder='.$folderPath.'">'.$folder.'</a> ';
                    $folderPath .= '/';
                }
                echo '&nbsp;';
            }
            ?>
            <?php $mode = $_GET['mode']; ?>
            <a href="?action=upload&folder=<?=$dir?>" class="badge rounded-pill bg-<?=$mode?'secondary':'primary'?>">Список</a>
            <a href="?action=upload&folder=<?=$dir?>&mode=table" class="badge rounded-pill bg-<?=$mode=='table'?'primary':'secondary'?>">Таблица</a>
            <a href="?action=upload&folder=<?=$dir?>&mode=fullreview" class="badge rounded-pill bg-<?=$mode=='fullreview'?'primary':'secondary'?>">Полный обзор</a>
            <a href="?action=upload&folder=<?=$dir?>&mode=stat&stat=1" class="badge rounded-pill bg-<?=$mode=='stat'?'primary':'secondary'?>">Статистика</a>
            <a href="?action=upload&folder=<?=$dir?>&mode=list-files" class="badge rounded-pill bg-<?=$mode=='list-files'?'primary':'secondary'?>">Список файлов</a>

            <hr />
            <div class="innerList small"><?php
            if ($_GET['stat']) {
                function getDirStat($dir, &$stat)
                {
                    $a = scandir($dir);
                    foreach ($a as $k => $v) {
                        if ($v == '.' || $v == '..') {
                            continue;
                        }
                        $path = $dir.'/'.$v;
                        if (is_dir($path)) {
                            getDirStat($path, $stat);
                        } else {
                            $ext = extension($v);
                            $stat [$ext] ++;
                        }
                    }
                }
                getDirStat($dir, $stat);
                echo '<pre>'; print_r($stat); echo '</pre>';
            }
            if ($_GET['mode'] == 'fullreview') {
            	foldersReview($dir, $uploaded);
            } elseif ($_GET['mode'] == 'table') {
            	foldersTable($files, $uploaded, $dir);
            } else {
                foldersSimple($files, $uploaded);
            }
            ?></div>

        </div>
        <div style="float:right; width:19%;">
            <?php
            if (count($_SESSION['folders'])) {
                echo '<b>Папки сессии:</b><br />';
                $folders = array_unique($_SESSION['folders']);
                asort($folders);
                $add = '';
                if ($_GET['mode']) {
                	$add = '&mode='.$_GET['mode'];
                }
                foreach ($folders as $k => $v) {
                    echo '<a href="?action=upload&folder='.$v.$add.'">'.$v.'</a><br />';
                }
            }
            ?>
            <div id="fileStat"></div>
        </div>
        <div style="clear:both; float:none"></div>


    </div>



    <script type="text/javascript">
    function checkFiles(obj)
    {
        for (var i in obj.files) {
            var file = obj.files[i];
            if (file.size == 0) {
                alert('Файл '+file.name+' пустой');
            }
        }
        document.getElementById('uploadBlock').style.display = 'inline'
    }
    function folderArchive(btn)
    {
        btn.disabled = true
        setTimeout(function(btn) {
            btn.disabled = false
        }, 10000, btn);
        var excludeDirs = $('.fileUpload input[name="excludeDirs"]').val();
        var downloadOnly = $('.fileUpload input[name="downloadOnly"]').prop('checked') ? 1 : '';
        var fromRoot = $('.fileUpload input[name="fromRoot"]').prop('checked') ? 1 : '';
        var removeExist = $('.fileUpload input[name="removeExist"]').prop('checked') ? 1 : '';
        location = '/<?=EXP?>?action=upload&folder=<?=$_GET['folder']?>&download=1&excludeDirs='+excludeDirs+'&downloadOnly='+downloadOnly+'&fromRoot='+fromRoot+'&removeExist='+removeExist;
    }

    $(document).ready(function(){
    let mode = new URLSearchParams(window.location.search).get('mode');
    if (mode == 'fullreview') {
        $('img').dblclick(function() {
            window.open(this.src)
        })
        $('img').click(function() {
            $('.previewBlock').show()
            var path = '/<?=$_GET['folder']?>/'+this.title;
            $('#copyField').val(this.title)
            $('#copyName').val(this.title.substr(0, this.title.indexOf('.')))
            $('#copyFileName').val(path)
            $('#copyFieldTag').val('<img src="'+path+'" alt="" />')
            $('#previewImg').attr('src', this.src);
            $('#fileinfo').html($(this).attr('data-info'))
        })
    }
    if (mode != 'fullreview') {
        setTimeout(function() {
            $('.innerList').css('max-height', screen.height - 300)
        }, 100);
    }
    $('.fileDel').click(function() {
        currentObject = $(this).parent().find('a');
        var name = currentObject.html();
        if (!confirm('Удалить '+name+'?')) {
            return false;
        }
        $.post('/<?=EXP?>?action=upload', 'folder=<?=$dir?>&deleteFile='+name, function(data) {
            if (data) {
                alert(data);
            } else {
                currentObject.html('<span style="color:#ccc">удален</span>');
            }
        });
    })
    $('.fileRename').click(function() {
        currentObject = $(this).parent().find('a');
        var name = currentObject.html();
        if (!(n = prompt('Введите новое название', name))) {
            return false;
        }
        $.post('/<?=EXP?>', 'folder=<?=$dir?>&renameFile='+name+'&newName='+n, function(data) {
            if (data) {
                alert(data);
            } else {
                if (n.indexOf('..') > -1) {
                	currentObject.html('<span style="color:#ccc">перемещен</span>');
                } else {
                    currentObject.html(n);
                    currentObject.attr('href', '/<?=EXP?>?action=upload&folder=<?=$dir?>/'+n);
                }
            }
        });
    })
    $('.fileMove').click(function() {
        currentObject = $(this).parent().find('a');
        var name = currentObject.html();
        if (!(n = prompt('Куда перемещать '+name+'?', '<?=$dir?>'))) {
            return false;
        }
        $.post('/<?=EXP?>', 'folder=<?=$dir?>&moveFile='+name+'&movePath='+n, function(data) {
            if (data) {
                alert(data);
            } else {
                currentObject.html('<span style="color:#ccc">перемещен</span>');
            }
        });
    })
    $('.fileStat').click(function() {
        var name = $(this).parent().find('a').html();
        $.post('/<?=EXP?>', 'fileStat=<?=$dir?>/'+name, function(data) {
            $('#fileStat').html(data)
        });
    })
    $('.fileDownload').click(function() {
        var name = $(this).parent().find('a').html();
        location = '/<?=EXP?>?action=upload&folder=..&fileDownload=<?=$dir?>/'+name;
    })
    $('.unzip, .unzipdel').click(function() {
        var name = $(this).parent().find('a').html();
        var q = 'unpack=<?=$dir?>/'+name;
        if ($(this).hasClass('unzipdel')) {
            q += '&unpackdel=1';
        }
        $.post('/<?=EXP?>', q, function(data) {
            if (data) {
                alert(data);
                return ;
            }
            location = location
        });
    })
    $('.fileInputs').click(function() {
        var a = $(this).parent().find('a').get()
        var aHtml = $(a).html();
        $('.previewBlock').show()
        var folder = '<?=$_GET['folder']?>';
        var path = '/'+aHtml;
        if (folder) {
        	path = '/'+folder+path;
        }
        $('#copyField').val(path)
        $('#copyFileName').val(aHtml)
        $('#copyName').val(aHtml.substr(0, aHtml.indexOf('.')))
        $('#copyFieldTag').val('<img src="'+path+'" alt="" />')
        $('#previewImg').attr('src', path)
    })

    $('.fform input[name=folder]').focus(function() {
        $(this).next().show()
    })
    $('.fform input[type=submit]').click(function() {
        setTimeout(function(o) {o.disabled=true}, 100, this);
    })
    });
    function resizeIframe(obj) {
        obj.style.height = obj.contentWindow.document.body.scrollHeight + 'px';
    }
    // drag drop
    $("html").on("drop", function(e) {
        e.preventDefault();
        e.stopPropagation();
    });
    $('.fileUpload').on('keydown', function(e) {
        console.log(e.keyCode)
    })
    $('.fileUpload').on('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('dragover')
        $('#infoBlock').html('<div style="font-size:30px;">CTRL key - заменить если существуют </div>');
    })
    $('.fileUpload').on('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover')
        $('#infoBlock').html('');
    })

    function slice(file, start, end) {
        let slice = file.mozSlice ? file.mozSlice :
                  file.webkitSlice ? file.webkitSlice :
                  file.slice ? file.slice : noop;

        return slice.bind(file)(start, end);
    }

    function sendbig(e, file, start, end, sliceSize) {

        if (file.size - end < 0) {
            end = file.size;
        }

        let slicedPart = slice(file, start, end);

        let formdata = new FormData();
        if (e.ctrlKey) {
            formdata.append('deleteExist', 1);
        }
        formdata.append('start', start);
        formdata.append('end', end);
        formdata.append('file', slicedPart);
        formdata.append('saveas', file.name);
        console.log('Sending Chunk (Start - End): ' + start + ' ' + end);

        formUpload(formdata, function() {
            if (end < file.size) {
                $('#successBlock').show().html('Часть успешно загружена');
                sendbig(e, file, start + sliceSize, start + (sliceSize * 2), sliceSize)
            } else {
                $('#successBlock').show().html('Файл успешно загружен. Страница будет перезагружена через 1 сек');
                setTimeout(function() {
                    location.reload()
                }, 1000);
            }
        })
    }

    function formUpload(formdata, callback) {
        $.ajax({
            url: location.href,
            type: 'POST',
            data: formdata,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function(response){
                if (response.output !== undefined) {
                    $('.fileUpload').before(response.output)
                    $('#successBlock').show().html('Перезагрузите страницу для применения изменений');
                    return;
                }
                if (response.error) {
                    $('#errorBlock').show().html(response.error);
                } else {
                    if (typeof(callback) == 'undefined') {
                        $('#successBlock').show().html('Файл успешно загружен. Страница будет перезагружена через 1 сек');
                        setTimeout(function() {
                            location.reload()
                        }, 1000);
                    } else {
                        callback()
                    }
                }
            }
        });
    }

    $('.fileUpload').on('drop', function(e) {
        $('#infoBlock').html('');

        e.preventDefault();
        e.stopPropagation();

        $(this).removeClass('dragover')
        $(this).addClass('drop')

        let maxUploadFilesize = 1024 * 1024 * parseInt('<?=ini_get('upload_max_filesize')?>');

        // одиночные и множественные пока загружаются разными кодами
        let fd = new FormData();
        let files = e.originalEvent.dataTransfer.files;
        if (files.length == 1) {
            if (files[0].size > maxUploadFilesize) {
                let sliceSize = maxUploadFilesize - 1024;
                sendbig(e, files[0], 0, sliceSize, sliceSize)
                return;
            }
            if (e.ctrlKey) {
                fd.append('deleteExist', 1);
            }
            fd.append('file', files[0]);
        } else {
            for (file of files) {
                fd.append('files[]', file);
            }
            if (!e.ctrlKey) {
                fd.append('checkExist', 1);
            }
        }
        formUpload(fd)
    })
    </script>

    <div id="infoBlock" style="color:blue"></div>

    <form method="post" class="fform">
        <input type="hidden" name="action" value="urlLoading">
        <input type="text" name="folder" required placeholder="Закачать с URL в текущую папку" value="" />
        <span style="display:none">
        <input type="text" name="filename" placeholder="Имя файла" value="" />
        <input type="text" name="maxWidth" placeholder="jpg max width" style="width:85px;" value="" />
        <input type="submit" value="Загрузить" class="btn btn-sm btn-primary" /></span>
    </form>

    <form method="post" class="fform">
        <input type="hidden" name="action" value="removeFolder">
        <input type="text" autocomplete="off" required name="folder" placeholder="Удаление папки, укажите путь к папке" value="<?=$_POST['action'] == 'removeFolder' ? $_POST['folder'] : ''?>" />
        <span style="<?=$_POST['action'] == 'removeFolder'?'':'display:none;'?>">
        <a href="#" onclick="$(this).parent().prev().val('<?=$_GET['folder']?>'); return false;">текущая</a>
        <input type="submit" value="Снести" />
        <label><input type="checkbox" name="showList" value="1"> показать список удаляемых файлов сначала</label>
        <label><input type="checkbox" name="showFolder" value="1"> показать какая папка будет удалена</label>
        <label><input type="checkbox" name="noDeleteFirst" value="1"> саму папку не удалять</label></span>
    </form>

    <form method="post" class="fform">
        <input type="hidden" name="action" value="copyFolder">
        <input type="text" name="folder" required placeholder="Копирование папки, укажите откуда" value="<?=$_POST['action'] == 'copyFolder' ? $_POST['folder'] : ''?>" />
        <span style="<?=$_POST['action'] == 'copyFolder'?'':'display:none;'?>"> =>
        <input type="text" name="folderTo" placeholder="Куда" value="<?=$_POST['folderTo']?>" style="width:300px;" />
        <input type="submit" value="Скопировать" />
        <label><input type="checkbox" name="showList" value="1"> показать список файлов сначала</label> </span>
    </form>

    <form method="post" class="fform">
        <input type="hidden" name="action" value="multyActions">
        <input type="text" name="folder" required placeholder="Операции с файлами (glob маска*)" value="<?=$_POST['action'] == 'multyActions' ? $_POST['folder'] : ''?>" />
        <span style="<?=$_POST['action'] == 'multyActions'?'':'display:none;'?>">
        <label><input type="radio" checked name="subaction" value="delete"> удалить по маске</label>
        <label><input type="radio" name="subaction" value="setPerms"> установить права</label>
        <input type="text" name="permValue" value="" />
        <input type="submit" value="Выполнить" /></span>
    </form>

    <form method="post" class="fform">
        <input type="hidden" name="action" value="moveAll">
        <input type="text" name="folder" required placeholder="Переместить все в папку" value="<?=$_POST['action'] == 'moveAll' ? $_POST['folder'] : ''?>" />
        <span style="<?=$_POST['action'] == 'moveAll'?'':'display:none;'?>"> =>
            <input type="text" name="excludeFiles" placeholder="Исключить" value="<?=$_POST['excludeFiles']?>" style="width:300px;" />
            <input type="submit" value="Переместить" />
        </span>
    </form>

    <p class="mt-2">Realpath: <?=realpath($_GET['folder'])?></p>


    <p><b>Cookie</b></p>
    <table class="optionstable">
        <?php
    foreach ($_COOKIE as $k => $v) {
        echo '<tr>
            <td><b>'.$k.'</b></td>
            <td><button type="button" class="btn-close" onclick="cook.set(\''.$k.'\', 0, 0, \'/\'); $(this).parents(\'tr\').fadeOut(); return false;"></button></td>
            <td class="text-break">'.$v.'</td>
        </tr>';
    }

    ?>
    </table>


    <form method="post" class="fform" id="edit-file" style="top:0px" data-root="<?=$dir?>">
        <input type="hidden" name="edit-file" value="1">
        <input type="hidden" name="name" value="">
        <h4></h4>
        <input type="submit" disabled value="Сохранить" class="submit btn btn-primary btn-sm" />
        <input type="submit" disabled value="Применить" class="apply btn btn-warning btn-sm" />
        <span class="edit-info text-success h6 ms-3"></span>
        <a href="#" class="btn btn-danger btn-sm" style="float:right;">Закрыть</a>
        <textarea name="content"></textarea>
    </form>
    <link href="https://fonts.googleapis.com/css?family=Anonymous+Pro" rel="stylesheet">

<script type="text/javascript">

editor = {
	init() {
		$(window).on('resize', function() {
			$('#edit-file textarea').height($(window).height() - 120)
		})
		$('.fileEdit').click(this.open)
		$('#edit-file a').click(function() {
		    $('#overlay, #edit-file').hide()
		    return false;
		})
		$('#edit-file textarea').keydown(function() {
		    $('#edit-file [type=submit]').attr('disabled', false)
		})
		$('#edit-file .apply, #edit-file .submit').click(this.save)
	},
	open() {
		let dir = $('#edit-file').data('root');
	    let name = $(this).parent().find('a').html();
	    $('#overlay, #edit-file').show()
	    $('#edit-file h4').html('Редактирование '+name)
	    $('#edit-file [name="name"]').val(dir+'/'+name)
	    $('#edit-file textarea').height($(window).height() - 120)
	    $.get('?raw='+dir+'/'+name, function(data) {
	        $('#edit-file textarea').val(data)
	    });
	},
	save() {
	    let btn = this
	    $.post('', $('#edit-file').serialize(), function(data) {
	        if (data != '') {
	            alert(data)
	            return ;
	        }
	        $('#edit-file .edit-info').html('Успешно сохранено')
	        if ($(btn).hasClass('submit')) {
	            setTimeout(function() {$('#overlay, #edit-file').hide(); }, 1000);
	        }
	        setTimeout(function() {$('#edit-file .edit-info').html('')}, 5000);
	    });
	    return false;
	}
}

// Quick edit
editor.init()


jQuery('#edit-file textarea').keyup(function(e) {
	e = window.event ? window.event : e;
	let code = e.keyCode ? e.keyCode : e.which;
	console.log(code);

    // '
    if (code == 222) {
        //var k = this.value.substr(-1)
        insertValue(this, "'")
    }

    // "
    if (code == 50 && e.shiftKey) {
        insertValue(this, '"')
    }

    // (
    if (code == 57 && e.shiftKey) {
        insertValue(this, ')')
    }

    // enter
    if (code == 13) {
        let space = getLastRowSpace(this)
        space = space + '    ';
        insertValue(this, space, space.length)
    }

})
jQuery('#edit-file textarea').keydown(function(e) {
	e = window.event ? window.event : e;
	let code = e.keyCode ? e.keyCode : e.which;
    // e.shiftKey
    // e.ctrlKey
    // tab
    if (code == 9) {
        e.preventDefault();
        if (this.selectionEnd > this.selectionStart) {
        	var selected = this.value.substr(this.selectionStart, this.selectionEnd - this.selectionStart);
            if (e.shiftKey) {
            	selected = selected.replace(/([\r\n]|^)(\t| {4})/g, "$1")
            } else {
                selected = selected.replace(/([\r\n])/g, "$1\t")
            }
            this.value = this.value.substr(0, this.selectionStart) + selected + this.value.substr(this.selectionEnd);
        } else {
            var s = getCaret(this);
            var v0 = this.value.substr(0, s)
            var v1 = this.value.substr(s)
            this.value = v0 + "    " +  v1
            setTimeout(function(obj, s) {
                obj.setSelectionRange(s, s);
                obj.focus();
            }, 100, this, s + 4);
        }
    }
})
jQuery('#edit-file textarea').dblclick(function (e) {
    var cursorPos = getCaret(this);
    var symb = this.value.substr(cursorPos-1, 2);

    /*if (symb.match(/[a-z]/i) !== null) {
        var startPos = findPosition(false, cursorPos, this.value, '/');
        var startPosK = findPosition(false, cursorPos, this.value, '"');
        var symb = '/';
        if (startPosK > startPos) {
        	var symb = '"';
            startPos = startPosK;
        }
        if (startPos !== false) {
            var endPos = findPosition(true, cursorPos, this.value, symb);
            this.setSelectionRange(startPos, endPos);
        }

    } else*/ if (symb.match(/[а-я\d]/i) !== null) {
        var startPos = findPosition(false, cursorPos, this.value, '>');
        if (startPos !== false) {
            var endPos = findPosition(true, cursorPos, this.value, '<');
            this.setSelectionRange(startPos, endPos);
        }
    }
})

jQuery('#edit-file textarea').keyup(function (e) {
	var e = window.event ? window.event : e;
	var code = e.keyCode ? e.keyCode : e.which;
    if (!e.shiftKey) {
        return ;
    }
    if (code == 32) {
        var currentPos = getCaret(this);
        if (currentPos >= 10) {
        	var prevBlock = this.value.substr(currentPos - 10, 10);
        } else {
            var prevBlock = this.value.substr(0, currentPos);
        }
        prevBlock = prevBlock.toLowerCase()

        var snip = prevBlock.match(/([a-z\d]+)\s*$/i);
        snip = snip[1]
        console.log(snip);

        var snippet = '';
        if (typeof(snippets[snip]) != 'undefined') {
        	var snippet = snippets[snip];
        }

        if (snippet) {

            var insPos = currentPos - snip.length - 1;

            var cursorPos = insPos + snippet.length;

            if (snippet.indexOf('|') > -1) {
            	cursorPos = insPos + snippet.indexOf('|');
                snippet = snippet.replace(/\|/, '')
            }

        	var blockBefore = this.value.substr(0, insPos);
        	var blockAfter = this.value.substr(currentPos);
            this.value = blockBefore + snippet + blockAfter;

            setTimeout(function(obj, cursorPos) {
                obj.setSelectionRange(cursorPos, cursorPos);
                obj.focus();
            }, 100, this, cursorPos);
        }
    }
})

function insertValue(obj, str, offset)
{
    if (typeof(offset) == 'undefined') {
    	offset = 0
    }
    if (!str) {
    	console.error('Пустой str');
    	return
    }
    var s = getCaret(obj);
    var v0 = obj.value.substr(0, s)
    var v1 = obj.value.substr(s)
    obj.value = v0 + str +  v1
    // Ставим курсор на то место, где он и был
    setTimeout(function(obj, s) {
        obj.setSelectionRange(s, s);
        obj.focus();
    }, 100, obj, s + offset);
}

function getLastRowSpace(txt)
{
    var text = jQuery(txt).val().substr(0, jQuery(txt).val().length - 2)
    var v = text.match(/([\r\n]+)(\s*)[^\r\n\s]*$/)[2]
    return v;
}

function getCaret(el) {
  if (el.selectionStart) {
    return el.selectionStart;
  } else if (document.selection) {
    el.focus();
    var r = document.selection.createRange();
    if (r == null) {
      return 0;
    }
    var re = el.createTextRange(),
    rc = re.duplicate();
    re.moveToBookmark(r.getBookmark());
    rc.setEndPoint('EndToStart', re);
    return rc.text.length;
  }
  return 0;
}
function findPosition(forward, from, content, symbol)
{
    var startPos = from;
    var founded = false;
    while (true) {
        if (forward) {
        	startPos ++;
        } else {
            startPos --;
        }
        symb = content.substr(startPos, 1);
        if (symb == '{') {
            founded = false;
        	break;
        }
        if (symb == symbol) {
            if (!forward) {
            	startPos ++;
            }
            founded = true;
            break;
        }
        if (forward && startPos < 0) {
        	break;
        }
        if (!forward && (typeof(symb) == 'undefined' || symb == '')) {
        	break;
        }
    }
    if (!founded) {
        return false;
    }
    return startPos;
}
var snippets = {}
</script>
<script type="text/javascript" src="https://komu.info/snippets.php"></script>

    <?php
}
    $pageContent = ob_get_contents();
    ob_end_clean();
}

if ($_GET['action'] == 'bitrix') {
    ob_start();
    $pager->printSubMenu(Bitrix::$menu);
echo Bitrix::$output;
    $pageContent = ob_get_contents();
    ob_end_clean();
}

if ($_GET['action'] == 'utils') {
    $pageContent = Utils::$output;
}


// Главная страница section
if ((!isset($_POST['action']) || POST('action') == 'sql') && !$_GET['action']) {
    if (!$mysqli) {
    return ;
}

ob_start();

if ($_GET['bySize']) {
    $sort_order = array();
    foreach ($tables as $key => $value) {
    	$sort_order[$key] = $value->Rows;
    }
    array_multisort($sort_order, SORT_DESC, SORT_NUMERIC, $tables);
}

$options = '';
foreach ($tables as $table => $v) {
    $options .= ' <option selected title="Rows: '.$v->Rows.'">'.$table.'</option>';
}
?>

<form id="loadingForm" method="post" action="<?php echo $_SERVER['SCRIPT_NAME'] ?>" enctype="multipart/form-data">

<div class="row">
    <div class="col-md-10">


        <input type="radio" class="btn-check" name="action" value="export" id="export" autocomplete="off" checked>
        <label class="btn btn-outline-primary" for="export">Экспорт</label>

        <input type="radio" class="btn-check" name="action" value="import" id="import" autocomplete="off">
        <label class="btn btn-outline-primary" for="import">Импорт</label>

        <hr>

        <div class="export-col row">

            <div class="col-md-3">
                <select name="ex_type" class="form-control form-select-sm mb-2" onchange="document.getElementById('folder').style.display='inline';">
                    <option value="textarea">textarea</option>
                    <option value="zip" selected>zip архив</option>
                    <option value="files">sql файлы в папку -> </option>
                    <option value="steps">один файл ajax</option>
                </select>
                <input type="text" style="display:none;" name="folder" value="<?=TMP_DIR?>" id="folder" class="export" />

                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="isStruct" value="1" id="isStruct" checked>
                  <label class="form-check-label" for="isStruct">
                    Структура
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="isData" value="1" id="isData" checked>
                  <label class="form-check-label" for="isData">
                    Данные
                  </label>
                </div>

            </div>

            <div class="col-md-5 text-center">
                <select name="tables[]" multiple style="height:100px;" class="form-control form-select-sm mb-1" onfocus="this.style.height='400px'">
                <?=$options?>
                </select>
                <?php echo count($tables); ?>
                <a href="#" onclick="$('select[name=\'tables[]\']').css('height', (i, v) => parseInt(v) + 100);return false;">≡</a>
                    <a href="?bySize=1" style="font-size:11px;">По размеру</a>
            </div>

            <div class="col-md-4">

                <a href="#" onclick="return ns(this)">Опции</a>
                <div style="display:none;" class="small ">
                    <input type="checkbox" name="query_list_fields" value="1" id="query_list_fields" checked="checked" />
                    <label for="query_list_fields">Указать список полей</label>
                    <input type="checkbox" name="ins_zadazd" value="1" id="ins_zadazd" /> <label for="ins_zadazd">DELAYED</label>
                    <input type="checkbox" name="ins_ignore" value="1" id="ins_ignore" /> <label for="ins_ignore">IGNORE</label><br />
                    <input type="checkbox" name="one_query" value="1" id="one_query" /> <label for="one_query">Инсерт одним запросом (меньше объем)</label><br />
                    <label><input type="checkbox" name="adddrop" value="1"  /> Добавить удаление таблиц</label><br />
                    <input type="text" name="exportWhere" style="width:230px;" placeholder="where условие" value="" />
                    <br />
                    <label><input type="checkbox" name="forceExportByPart" value="1" /> Принудительный экспорт по частям размером
                    <input type="text" name="partSize" value="10000" style="width:50px;" /> (в папку)</label>
                </div>
            </div>
        </div>

        <hr>

        <div class="import-col visually-hidden">

            <div class="row">


                <div class="col">
                    <div class="input-group input-group-sm mb-3">
                        <span class="input-group-text">Type</span>
                        <select name="type" class="form-control">
                            <option value="files">sql файлы</option>
                        </select>
                    </div>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">из папки</span>
                        <input type="text" name="ifolder" value="" id="ifolder" class="form-control" />
                    </div>
                </div>
                <div class="col">
                    <div class="mb-2">
                    <input type="file" id="fileField" onchange="importFileUpload(); return false;" name="file" class="form-control form-control-sm" />
                    <?php
                    $file = getTempFile();
                    if ($file) {
                        echo '<span id="fileFieldLoader" class="small" style="color:blue">Уже существует файл '.$file.'. <a href="#" onclick="uploadFileRemove(); return false;">удалить</a></span>';
                    } else {
                        echo '<a href="#" style="display:none; color:red" class="small" id="fileFieldLoader">загружаю...</a>';
                    }
                    ?>
                    </div>
                    <input type="text" name="execByRows" placeholder="Выполнить построчно файл" class="form-control form-control-sm mb-3" value="" />
                    <div class="input-group mb-2 mt-2 input-group-sm">
                      <span class="input-group-text">max_execution_time</span>
                      <input type="number" class="form-control" name="max_execution_time" value="<?=ini_get('max_execution_time')?>">
                    </div>
                </div>

                <div class="col small">

                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="save_filled" value="1" id="imc1">
                      <label class="form-check-label" for="imc1">
                        не перезаписывать заполненные таблицы
                      </label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="exitOnError" value="1" id="imc2">
                      <label class="form-check-label" for="imc2">
                        exit при первой ошибке
                      </label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="log" value="1" id="imc3">
                      <label class="form-check-label" for="imc3">
                        вести лог в файл Log.txt
                      </label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="deleteAfterImport" value="1" id="imc4">
                      <label class="form-check-label" for="imc4">
                        удалить файл после успешного импорта
                      </label>
                    </div>

                    <div class="input-group mb-2 mt-2 input-group-sm">
                      <span class="input-group-text">Обрезать файл до размера (мб)</span>
                      <input type="number" class="form-control" name="cut_file" value="" id="cut_file">
                    </div>
                    <div class="input-group mb-2 input-group-sm">
                      <span class="input-group-text">Максимум запросов из 1 файла</span>
                      <input type="number" class="form-control" name="max_query" value="" id="max_query">
                    </div>
                </div>
            </div>

        </div>


        <hr />

        <div>
            <input type="button" id="execButton" class="btn btn-primary me-2" value="Выполнить" />

             <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" id="clearLog" name="clearLog" checked >
              <label class="form-check-label" for="clearLog">очистить лог</label>
            </div>

            <div style="color:red; display:none; margin:5px 0;" id="resultsError"></div>
            <div style="color:green; display:none" id="resultSuccess"></div>
            <b id="logEvent"></b>
            <div id="exportLogInfo" style="max-height:300px; font-size:12px; margin-top:10px; overflow:auto"></div>
            <mark>Realpath: <?=realpath($_GET['folder'])?></mark>
        </div>
    </div>


    <div class="tblMenu col-md-2">
        <?php
        printSessionTables();
        foreach ($tables as $table => $v) {
            echo '<a href="?action=tables&table='.$table.'"'.getTableStyle($v->Rows).'>'.$table.'</a>';
        }
        ?>
    </div>
</div>
</form>

<div style="background-color:#eee; border:1px solid green; width:100%; display:none;" id="loggerBlock">
    <iframe style="border:1px solid #ccc; width:100%; height:500px; margin-top:10px;" id="logger"
        src=""></iframe>
    <a href="#" onclick="stopUpdate=true; return false;">Остановить</a>
    <a href="#" onclick="stopUpdate=false; return false;">Продолжить</a>
</div>

<div id="results"></div>


<?php
$pageContent = ob_get_contents();
ob_end_clean();
}




templateLayout($tables, $database, $pageContent);

