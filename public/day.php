<?php
include('inc.php');


if(!in_array($_GET['channel'], Config::supported_channels())) {
  header('HTTP/1.1 404 Not Found');
  die('channel not found');
}

if($_GET['channel'] == 'pdxbots' && $_SERVER['REMOTE_ADDR'] != '162.213.78.244') {
  die('forbidden');
}


# Pre-load variables required for header
$permalink = false;

loadUsers($_GET['channel']);
$timezones = loadTimezones();

# Get timezone of viewer from cookie
list($tzname, $tz) = getViewerTimezone();
$utc = new DateTimeZone('UTC');


# Get the start/end times for this day
$start = new DateTime($_GET['date'].' 00:00:00', $utc);
$start->setTimeZone($tz);
$date = clone $start;
$end = new DateTime($_GET['date'].' 23:59:59', $utc);
$end->setTimeZone($tz);

# Return 404 for days in the future
if($start->format('U') > time()) {
  header('HTTP/1.1 404 Not Found');
  die();
}


$start_utc = new DateTime($_GET['date'].' 00:00:00', $utc);
$end_utc = new DateTime($_GET['date'].' 23:59:59', $utc);

$dateTitle = $start_utc->format('Y-m-d');

$tmrw = new DateTime($_GET['date'].' 00:00:00', $utc);
$tmrw->add(new DateInterval('P1D'));
$tomorrow = $tmrw->format('Y-m-d');
$ystr = new DateTime($_GET['date'].' 00:00:00', $utc);
$ystr->sub(new DateInterval('P1D'));
$yesterday = $ystr->format('Y-m-d');
if($tmrw->format('U') > time()) $tomorrow = false;

$channel = '#'.$_GET['channel'];
$channel_link = Config::base_url_for_channel($channel);

if (!isAfterFirst($channel, $yesterday)) {
    $yesterday = false;
}
$channelName = $channel;

// #indiewebcamp channel was renamed to #indieweb on 2016-07-04
if($start->format('U') < 1467615600 && $channelName == '#indieweb') $channelName = '#indiewebcamp';


$db = new Quartz\DB('data/'.Config::logpath_for_channel($channel), 'r');
$results = $db->queryRange(clone $start_utc, clone $end_utc);

ob_start();
$num_lines = 0;
$noindex = true;
include('templates/header.php');
include('templates/header-bar.php');

?>
<main>

<h2 class="date"><span class="channel-name"><?= $channelName ?></span> <?= $dateTitle ?></h2>

<div class="logs">
  <div id="log-lines">
    <?php
    $lastday = $start;
    if($lastday->format('Y-m-d') != $start_utc->format('Y-m-c')) {
      echo '<div class="daymark">'.$start->setTimeZone($tz)->format('Y-m-d').' <span class="tz">'.$tzname.'</span></div>';
    }
    $last_line_type = false;
    $cluster = [];
    foreach($results as $line) {
      $num_lines++;
      $localdate = clone $line->date;
      $localdate->setTimeZone($tz);
      if($localdate->format('Y-m-d') != $lastday->format('Y-m-d')) {
        if(count($cluster)) {
          echo render_cluster($cluster);
          $cluster = [];
        }
        echo '<div class="daymark">'.$localdate->format('Y-m-d').' <span class="tz">'.$tzname.'</span></div>';
      }
      $current = format_line($channel, $line->date, $tz, $line->data);
      if($current['cluster'] && (!$last_line_type || $current['cluster'] == $last_line_type)) {
        $cluster[] = $current;
      } else {
        if(count($cluster)) {
          echo render_cluster($cluster);
          $cluster = [];
        }
        echo $current['html'];
      }
      $lastday = $localdate;
      $last_line_type = $current['cluster'];
    }
    if(count($cluster)) {
      echo render_cluster($cluster);
    }
    ?>
  </div>
  <span id="bottom"></span>
</div>

<?php if(!isset($tomorrow) || !$tomorrow): /* Set the channel name to activate realtime streaming, only when viewing "today" */ ?>
  <input id="active-channel" type="hidden" value="<?= Config::irc_channel_for_slug($_GET['channel']) ?>">
  <input id="tz-offset" type="hidden" value="<?= $start->format('P') ?>">
<?php endif; ?>

<?php include('templates/footer-bar.php'); ?>

<script type="text/javascript">/*<![CDATA[*/
  if(window.location.hash && window.location.hash != '#top' && window.location.hash != '#bottom') {
    var n = document.getElementById(window.location.hash.replace('#',''));
    n.classList.add('hilite');
  }
  window.addEventListener("hashchange", function(){
    var n = document.getElementsByClassName('line');
    Array.prototype.filter.call(n, function(el){ el.classList.remove('hilite') });
    var n = document.getElementById(window.location.hash.replace('#',''));
    n.classList.add('hilite');
  }, false);
/*]]>*/</script>

</main>
<?php
include('templates/footer.php');

$output = ob_get_clean();

echo $output;
