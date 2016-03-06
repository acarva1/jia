<?php
require '../includes/config.inc.php';

function toJSON($text) {
    //the JSONP call back
    return 'jia_callBack(' . json_encode($text).');';
}
ob_start('toJSON');
//buffer all output to JSON

?>
        <style type="text/css">
<?php
//assemble style
if (isset($_GET['bg'], $_GET['font'], $_GET['color'], $_GET['link'])) {
    $bg = urldecode($_GET['bg']);
    $font = urldecode($_GET['font']);
    $color = urldecode($_GET['color']);
    $link = urldecode($_GET['link']);
    if (validateColors($_GET['bg'], $_GET['color'], $_GET['link'])) {
        echo <<<EOF
#jiaDiv {
    color: $color;
    background-color: $bg;
    font-family: $font;
    padding: 5px;
}
#jiaDiv h2 {
    font-size: 2em;
}
#jiaDiv a {
    color: $link;
}
#jiaDiv ul {
    list-style-type: none;
    padding-left: 10px;
}
#jiaDiv li.date {
    font-weight: bold;
    font-size: 1.5em;
}
#jiaDiv li.event {
    font-weight: 500;
    margin-top: 7px;
    font-size: .7em;
}
#jiaDiv li.date:not(:last-child) {
    margin-bottom: 15px;
}
#jiaDiv .jiaFooter {
    font-size: .7em;
}

EOF;

        
    }
}
echo '</style>';      
echo '</head>';
echo '<body>';

//validate user name
if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT, array('min_range'=>1))) {
    //get associated event info
    require MYSQL;
    $stmt = $dbc->query("CALL get_profile_events({$_GET['id']})");
    $stmt->setFetchMode(PDO::FETCH_CLASS, 'Event');
    //create event obj for each returned row
    while ($stmt->fetch()) {}
    $stmt->closeCursor();
    //get user name
    $name = $dbc->query("SELECT CONCAT_WS(' ', first_name, last_name) AS name FROM users WHERE id={$_GET['id']}");
    $name = $name->fetchColumn();
    //build the calendar list
    if (!empty($name)) {
        //print the header
        echo "<h2>My Upcoming Shows</h2>";
        //create the list
        if (empty(Event::$events)) {
            echo '<i>There are currently no upcoming events.</i><br />';
        }else{
            echo '<ul>';
            foreach (Event::$events as $date=>$eventArray) {
                //print the date header
                echo '<li class="date"><u>' . $date . '</u>';
                //show individual events for each date
                echo '<ul>';
                foreach ($eventArray as $event) {
                    echo '<li class="event">';
                    $event->getHTML();
                    echo '</li>';
                }
                echo '</ul>';
                echo '</li>';
            }
            echo '</ul>';
        }
        echo '<i class="jiaFooter">Courtesy of <a href="http://jazzinaustin.com" target="_blank">Jazz in Austin</a></i>';
    }
}

class Event
{
    static $events = array();
    
    public function __construct()
    {
        self::$events[$this->edate][] = $this;
    }
    
    public function getHTML()
    {
        //print formatted event info
        if (!empty($this->end_time)){
            echo parseTime($this->start_time) . ' - ' . parseTime($this->end_time) . ': ';
        }else{
            echo parseTime($this->start_time) . ': ';
        }
        echo '<a href="http://jazzinaustin.com/events.php?id=' . $this->id . '" target="_blank">' . $this->title;
        echo ' @ ' . $this->venue . '</a>';
    }
    
    public $id;
    public $title;
    public $venue;
    public $start_time;
    public $end_time;
    public $edate;
}
//validate color input
function validateColors() {
    $args = func_get_args();
    foreach ($args as &$arg) {
        if (!isset($arg)) return false;
        if (!preg_match('/^#[0-9A-Fa-f]{3,6}$|^none$/', $arg)) return false;
        $arg = urldecode($arg);
    }
    return true;
}
?>