<?php
  require("libchart/classes/libchart.php");
  require("connection.php");

  // error_reporting(E_ALL);
  // ini_set("display_errors", 1);
	// Opens a connection to a MySQL server
	$pdo = new PDO("mysql:host=$dbhost;dbname=$db;charset=utf8mb4", $dbuser, $dbpass);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);


  if(!isset($_REQUEST['channelId'])) {
    echo "no channelId set";
    exit(1);
  }

  $channelId = intval($_REQUEST['channelId']);
  
  $stmtEvents = $pdo->prepare("SELECT MAX(channelEvent.clientCount) AS clientCount, FROM_UNIXTIME( TRUNCATE( UNIX_TIMESTAMP( channelEvent.date)/3600, 0 ) * 3600 )  AS time_slice
    FROM channelEvent 
    LEFT JOIN channel on channel.id = channelEvent.channelId 
    LEFT JOIN server ON server.id = channel.serverId 
    WHERE server.uid = ? AND channel.channelId = ? AND date BETWEEN DATE_SUB(NOW(),INTERVAL 1 WEEK) AND NOW()
    GROUP BY time_slice
    ORDER BY date ASC LIMIT 1000");
  $stmtEvents->execute(Array($serverUid, $channelId));
  $stmtChannel = $pdo->prepare("SELECT channel.id, channel.name, channel.channelId, channel.parentId, channel.position, channel.description, server.name as serverName, server.id as serverId FROM channel 
    LEFT JOIN server ON server.id = channel.serverId
    WHERE server.uid = ? AND channel.channelId = ?");
  $stmtChannel->execute(Array($serverUid, $channelId));
  $events = $stmtEvents->fetchAll(PDO::FETCH_ASSOC);
	$channels = $stmtChannel->fetchAll(PDO::FETCH_ASSOC);

  if(count($channels) == 0) {
    echo "no channel found";
    exit(2);
  }
  
  $serie1 = new XYDataSet();
  foreach($events as $event) {
    $date = new DateTime($event['time_slice']);
    $serie1->addPoint(new Point($date->format('H:i'), $event['clientCount']));
  }

  $chart = new LineChart(500, 250);
  $chart->setDataSet($serie1);
  $chart->setTitle($channels[0]['name']);

  header("Content-Type: image/png");
  $chart->render();
?>