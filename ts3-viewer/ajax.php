<?php
	require("connection.php");
	// Opens a connection to a MySQL server
	$pdo = new PDO("mysql:host=$dbhost;dbname=$db;charset=utf8mb4", $dbuser, $dbpass);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
  
  $stmtEvents = $pdo->prepare("SELECT channelEvent.channelId, UNIX_TIMESTAMP(channelEvent.date), channelEvent.clientCount FROM channelEvent 
    LEFT JOIN channel on channel.id = channelEvent.channelId 
    LEFT JOIN server ON server.id = channel.serverId 
    WHERE server.uid = ? 
    ORDER BY date ASC LIMIT 10000");
  $stmtEvents->execute(Array($serverUid));
  $stmtChannel = $pdo->prepare("SELECT channel.id, channel.name, channel.channelId, channel.parentId, channel.position, channel.description, server.name as serverName, server.id as serverId FROM channel 
    LEFT JOIN server ON server.id = channel.serverId
    WHERE server.uid = ?");
  $stmtChannel->execute(Array($serverUid));
	$json = Array(
		"eventColumns" => Array("id", "date", "clientCount"),
		"events" => $stmtEvents->fetchAll(PDO::FETCH_NUM),
		"channels" => $stmtChannel->fetchAll(PDO::FETCH_ASSOC)
  );
	echo json_encode($json);
?>