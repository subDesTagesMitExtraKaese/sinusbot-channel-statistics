# Sinusbot Channel Statistics

## log number of clients per channel to mysql

sinusbot script `channel-statistics.js`

## display graphs on website

`ts3-viewer/`

needs `ts3-viewer/connection.php`
```php
<?php
$dbhost="localhost";
$db="<db>";
$dbuser="<user>";
$dbpass="<pass>";

$serverUid = "<ts3 server uid>";
?>
```

![demo](ts3-stats.png)
