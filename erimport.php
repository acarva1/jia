<?php
require './includes/config.inc.php';
if (isset($_SESSION['id']) && $_SESSION['isAdmin'] === true) {
    
    class Row {
        static $rows = array();
        public $cells = array();
        public function __construct($str) {
            //isolate cells
            preg_match_all('/<td.*?<\/td>/', $str, $cells, PREG_PATTERN_ORDER);
            foreach ($cells[0] as $cell) {
                //create cell objects and add to row's cell array
                $co = new Cell(strip_tags($cell, '<br>'));
                array_push($this->cells, $co);
            }
            //add to array of rows
            self::$rows[] = $this;
        }
    }

    class Cell {
        public $day;
        public $content;
        public function __construct($c) {
            preg_match('/(\d+)?.*?([A-Z].*)/', $c, $d);
            $this->day = array_key_exists(1, $d)?$d[1]:''; //extract the date
            if (!empty($this->day)) $this->day = substr('0'.$this->day,-2);
            $this->content = array_key_exists(2, $d)?$d[2]:''; //extract content
            if (!empty($this->content)) {
                $this->content = str_replace(array('<br>', '<br />'), ' ', $this->content);
            }
        }
    }

    require MYSQL;

    $q = "SELECT `date`, start_time FROM events WHERE venue LIKE '%Elephant Room%' AND MONTH(`date`)=MONTH(NOW())";
    $stmt = $dbc->query($q);
    $existingDates = array();
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        if ($row[1] === '2130') {
            $existingDates[$row[0]] = $row[1]; //get all existing ER dates for this month
        }else{
            $existingDates[$row[0].'hh'] = $row[1];
        }
    }

    //get all user names to allow for proper ownership.
    $q = "SELECT CONCAT_WS(' ', first_name, last_name), id FROM users";
    $stmt = $dbc->query($q);
    $userNames = array();
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $userNames[$row[0]] = $row[1];
    }


    $month = date('m'); //current month

    $er = file_get_contents('http://www.elephantroom.com'); //get html from ER calendar

    $er = preg_replace('/\v/', '', $er); //get rid of vertical spaces to allow for reg exp searches

    preg_match_all('/<tr.*?<\/tr>/', $er, $rows, PREG_PATTERN_ORDER); //isolate table rows

    for ($i=2; $i<count($rows[0])-1; $i++) {
        new Row($rows[0][$i]); //create row object
    }

    //$sql = 'INSERT INTO `events` (venue, date, start_time, end_time, title, user_id) VALUES ("Elephant Room", ?, ?, ?, ?, ?)';
    $sql = 'CALL mass_insert(?,?,?,?,?,?)';
    $stmt = $dbc->prepare($sql);
    //loop through all rows (main rows, not happy hour)
    for ($i=0; $i<count(Row::$rows); $i+=2) {
        $row = Row::$rows[$i];
        //loop through the cells
        for ($p=0; $p<count($row->cells); $p++) {
            $cell = $row->cells[$p];
            if (empty($cell->content)) continue;
            //check for user name
            $uid = 3; //default creator account
            foreach ($userNames as $name=>$id) {
                if (stristr($cell->content, $name)) {
                    //match found, use their id
                    $uid = $id;
                }
            }

            //import 930 show
            $data = array('Elephant Room', date('Y').'-'.$month.'-'.$cell->day, '2130', '0130', $cell->content, $uid);
            //dont import if already exists
            if (!array_key_exists($data[1], $existingDates) || ($existingDates[$data[1]] && $existingDates[$data[1]] !== $data[2]))
                $stmt->execute($data);
            //import happy hour if exists
            $hhcell = Row::$rows[$i+1]->cells[$p];
            if (!empty($hhcell->content)) {
                //check for owner
                $uid = 3; //default creator account
                foreach ($userNames as $name=>$id) {
                    if (stristr($cell->content, $name)) {
                        //match found, use their id
                        $uid = $id;
                    }
                }
                $data[2] = '1800';
                $data[3] = '2000';
                $data[4] = $hhcell->content;
                $data[5] = $uid;
                if (!array_key_exists($data[1].'hh', $existingDates) || ($existingDates[$data[1].'hh'] && $existingDates[$data[1].'hh'] !== $data[2]))
                    $stmt->execute($data);
            }
        }
    }
    echo "This month's ER shows have been imported";
}else echo 'Access Denied.';
?>