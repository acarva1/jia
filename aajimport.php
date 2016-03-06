<?php
require './includes/config.inc.php';
if (isset($_SESSION['id']) && $_SESSION['isAdmin'] === true) {
    //on form submit
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require MYSQL;
        //get all user names to assign authorship
        $q = "SELECT CONCAT_WS(' ', first_name, last_name), id FROM users";
        $stmt = $dbc->query($q);
        $userNames = array();
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $userNames[$row[0]] = $row[1];
        }
        //get description, time, band members from link page
        $stmt = $dbc->prepare('CALL mass_insert(?,?,?,?,?,?,?,?)');
        foreach ($_POST['shows'] as $c) {
            $show = unserialize(base64_decode($c));
            //extract details from event link page
            $show->parseLink();
            //get the end time by add 3 hours to start time
            /*$endTime = (int)substr($show->startTime, 0, 2);
            $endTime += 3;
            if ($endTime >= 24) $endTime -= 24;
            $endTime = substr('0'.$endTime,-2) . substr($show->startTime, -2); */
            //check for author
            $uid = 3; //default creator account
            foreach ($userNames as $name=>$id) {
                if (stristr($show->title, $name)) {
                    //match found, use their id
                    $uid = $id;
                    //if band empty, make band be the leader
                    if (empty($this->band)) {
                        $this->band = $name;
                    }
                    break;
                }
            }
            //add to DB
            if ($stmt->execute(array($show->venue, $show->edate, $show->startTime, null, $show->title, $uid, $show->band, $show->desc)))
                echo "Successfully added $show->title.<br />";
        }
    }else{
        //get the html from x pages
        for ($i=1; $i<4; $i++) {
            $html = file_get_contents('http://austin.jazznearyou.com/calendar.php?pg='.$i);
            //find the table
            preg_match('/<table class="table e-calendar table-striped">.*?<\/table>/s', $html, $table);
            //process it
            $t = new Table($table[0]);
        }
        
        
        //create the form
        ?>
<form action="aajimport.php" method="post">
    <select name="shows[]" size="30" multiple>
    
        <?php
    foreach (Table::$rows as $row) {
        $show = base64_encode(serialize($row));
        echo "<option value='".htmlspecialchars(json_encode($show))."' selected>$row->title</option>";
    }
    ?>
        
    </select>
    <input type="submit" value="Submit" />
</form>
<?php
    }
}

class Table {
    public static $rows = array();
    
    public function __construct($html) {
        //get rows
        preg_match_all('/<tr id="tr\d{6}">(.*?)<\/tr>/s', $html, $rows, PREG_PATTERN_ORDER);
        for ($i=0; $i<count($rows[1]); $i++) {
            array_push(self::$rows, new Row($rows[1][$i]));
            //self::rows[] = new Row($rows[1][$i]);
        }
    }
}

class Row {
    public $link;
    public $title;
    public $venue;
    public $edate;
    public $startTime;
    public $band = '';
    public $desc;
    
    public function __construct($html) {
        //get title and link
        preg_match('/<td class="b-40">.*?href="(.*?)".*?>(.*?)<\/a/s', $html, $d);
        $this->link = 'http://austin.jazznearyou.com' . $d[1];
        $this->title = trim($d[2]);
        //get venue
        preg_match('/<td class="b-30">(.*?)<br/s', $html, $d);
        $this->venue = trim(strip_tags($d[1]));
    }
    
    public function parseLink() {
        $html = file_get_contents($this->link);
        //echo htmlentities($html);
        //get date
        if (preg_match('/<meta .*? content="(\d{4}-\d{2}-\d{2})"/s', $html, $date))
            $this->edate = $date[1];
        //get start time
        if (preg_match('/<p.*?class="col-xs-8 styledtext">.*?<br \/>(\d{1,2}:\d{2} [apm]{2})/s', $html, $time))
            $this->parseTime($time[1]);
        //get band members
        if (preg_match_all('/<div class="col-xs-4 musician-thumblist-item bottom-40">.*?<span class="caption-text">(.*?)<\/a>(.*?)<\/span>/s', $html, $band, PREG_SET_ORDER))
            $this->parseBand($band);
        //get description
        if (preg_match('/<div class="row" itemprop="description">.*?<p class="styledtext">(.*?)<\/p>/s', $html, $desc)) {
            $this->desc = strip_tags($desc[1], '<br>');
        }
    }
    
    public function parseTime($str) {
        $period = substr($str, -2);
        list($hour, $min) = explode(':', $str);
        $hour = substr('0'.$hour, -2);
        $min = substr($min, 0, 2);
        if ($period === 'pm' && $hour !== '12') {
            $hour = (int)$hour + 12;
        }else if ($period === 'am' && $hour === '12') {
            $hour = '00';
        }
        $this->startTime = $hour . $min;
    }
    
    public function parseBand($array) {
        for ($i=0; $i<count($array); $i++) {
            if ($i>0) $this->band .= '|';
            $this->band .= trim(strip_tags($array[$i][1])) . ',' . trim(strip_tags($array[$i][2])); //name=>instrument
        }
    }
}

?>