<?php
require './includes/config.inc.php';
if (isset($_SESSION['id']) && $_SESSION['isAdmin'] === true) {
    //on form submit
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shows'])) {
        //get DB info
        require MYSQL;

        $q = "SELECT `date`, start_time FROM events WHERE venue LIKE '%Brass House%'";
        $stmt = $dbc->query($q);
        $existingDates = array();
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $existingDates[$row[0]] = $row[1]; 
        }

        //get all user names to allow for proper ownership.
        $q = "SELECT CONCAT_WS(' ', first_name, last_name), id FROM users";
        $stmt = $dbc->query($q);
        $userNames = array();
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $userNames[$row[0]] = $row[1];
        }
        //prepare query
        $sql = 'CALL mass_insert(?,?,?,?,?,?)';
        $stmt = $dbc->prepare($sql);
        //loop through shows
        foreach ($_POST['shows'] as $show) {
            $show = json_decode(htmlspecialchars_decode($show));
            //check if already exists
            if (array_key_exists($show->date, $existingDates) && $show->startTime === $existingDates[$show->date]) {
                continue;
            }
            //check for authorship
            $uid = 3; //the admin user acct
            foreach ($userNames as $name=>$id) {
                if (stristr($show->summary, $name)) {
                    $uid = $id;
                    break;
                }
            }
            //add show to DB
            $data = array('Brass House', $show->date, $show->startTime, $show->endTime, $show->summary, $uid);
            if ($stmt->execute($data)) {
                echo "$show->summary was added.<br>";
            }else print_r($dbc->errorInfo());
        }
    }
    
    //calendar URL
    $url = 'https://calendar.google.com/calendar/htmlembed?title=Brass%20House%20Austin%20Calendar%20of%20Events&src=mc7n5ov8c9i4s047liqp9j07ls%40group.calendar.google.com';
    
    //imports the given month format:'20160201'
    $curDate = date('Y').date('m').'01';
    function importMonth($date) {
        global $url;
        //get end date
        $curYM = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-';
        $endYear = (int)substr($date, 0, 4);
        $endMonth = (int)substr($date, 4, 2) + 1;
        if ($endMonth === 13) {
            $endMonth = '01';
            $endYear++;
        }else $endMonth = substr('0'.$endMonth, -2);
        $endDate = $endYear . $endMonth . '01';
        //add date info to url and get HTML
        $url .= "&dates=$date/$endDate";
        $bh = file_get_contents($url);
        $bh = preg_replace('/\v/', '', $bh);
        //get date of top left cell
        preg_match('/<td class="date-marker date-(month|not-month)">(\d+)<\/td>/', $bh, $match);
        $startDate = $match[2];
        //if its from the previous month, how many days in that month?
        if ($startDate !== '1') {
            $prevMonth = date('n') -1;
            $year = date('Y');
            //if going back a year
            if ($prevMonth == 0) {
                $year--;
                $prevMonth = 12;
            }
            $daysPrevMonth = cal_days_in_month(CAL_GREGORIAN, $prevMonth, $year);
            $daysLeft = $daysPrevMonth - (int)$startDate + 1;
        }else $daysLeft = 0;
        $daysCurMonth = date('t');
        //collect all grid-row tr's
        preg_match_all('/class="grid-row">(.*?)(<\/tr>|<tr)/', $bh, $rows, PREG_PATTERN_ORDER);
        $r = -1; //keeps track of row number
        //$ir = -1; //inner row number
        $shows = array(); //$shows[date] = array(array(startTime=>t, endTime=>t, summary=>s))
        
        for ($i=0; $i<count($rows[1]); $i++) {
            
            //determine position
            //if ($i%7 === 0) $ir++;
            if ($i%5 === 0) {
                $r++;
                //reset rowSpan at the top of each row
                $rowSpan = array(); //keeps track of row numbers that are getting spanned (skipped)
            }
            
            //isolate cells
            $row = $rows[1][$i];
            
            preg_match_all('/<td.*?.(?=(<td|<\/td))/', $row, $cells, PREG_PATTERN_ORDER);
            //echo count($cells[0]);
            
            for ($c=0,$v=0; $c<7; $c++) {
                
                //check for row spanning
                if (in_array($c, $rowSpan)) {
                    continue;
                }
                $cell = $cells[0][$v];
                $v++;
                if (stristr($cell, 'rowspan=')) $rowSpan[] = $c;
                //get time and summary info
                unset($time, $summary);
                preg_match('/<span class="event-time">([0-9apm]{3,})<\/span>/', $cell, $time);
                preg_match('/<span class="event-summary">(.+?)<\/span>/', $cell, $summary);
                //add event to the array
                if (!empty($time[1]) && !empty($summary[1])) {
                    //get date
                    $d = $c + ($r*7) - $daysLeft + 1;
                    if ($d > $daysCurMonth) continue; //if we're into the next month
                    //convert time
                    $period = substr($time[1], -2);
                    $time = (int)substr($time[1], 0, -2);
                    
                    if ($period === 'pm' && $time !== 12) {
                        $time += 12;
                    }
                    if ($time < 19) {
                        $endTime = $time + 2;
                    }else $endTime = $time + 4;
                    if ($endTime >= 24) $endTime -= 24; //if going into the am
                    $time = substr('0'.$time, -2) . '00';
                    $endTime = substr('0'.$endTime, -2) . '00';
                    //add show
                    $d = $curYM . substr('0'.$d, -2);
                    $shows[$d][] = array('startTime'=>$time, 'endTime'=>$endTime, 'summary'=>$summary[1]);
                }
            }
        }
        return $shows;
    }
    
    $shows = importMonth($curDate);
    
    //draw multiselect form
    ?>
<form action="bhimport.php" method="post">
    <select name="shows[]" size="30" multiple>
    
        <?php
    foreach ($shows as $date=>$showArray) {
        foreach ($showArray as $show) {
            $show['date'] = $date;
            echo '<option value="'.htmlspecialchars(json_encode($show)).'" selected>' . $show['summary'] . '</option>';
        }
    }
    ?>
        
    </select>
    <input type="submit" value="Submit" />
</form>
<?php
}else echo 'Access Denied.';
?>