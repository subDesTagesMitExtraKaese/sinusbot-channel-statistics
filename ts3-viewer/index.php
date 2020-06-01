<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<title>TS3 channel usage</title>
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link href="stylesheet.css" rel="stylesheet" type="text/css">
		<!--[if lte IE 8]><script language="javascript" type="text/javascript" src="../flot/excanvas.min.js"></script><![endif]-->
		<script language="javascript" type="text/javascript" src="../flot/jquery.min.js"></script>
		<script language="javascript" type="text/javascript" src="../flot/jquery.flot.min.js"></script>
		<script language="javascript" type="text/javascript" src="../flot/jquery.flot.stack.min.js"></script>
		<script language="javascript" type="text/javascript" src="../flot/jquery.flot.time.min.js"></script>
		<script language="javascript" type="text/javascript" src="../flot/jquery.flot.selection.min.js"></script>
		<script language="javascript" type="text/javascript" src="../flot/jquery.flot.resize.min.js"></script>

    </head>
	<body>
    <header class="main-head">TS3 channel usage</header>
    <div id="tree"></div>
    <div id="graphs"></div>
    <script>
    function update() {
      fetch("ajax.php").then(res=>res.json()).then(function(json) {
        
        let channelTree = {channelId: 0, name: 'Server'};
        makeTree(channelTree, json.channels, json.events);
        showTree(channelTree, document.getElementById('tree'));

        let div = $("#graphs")[0];
        div.innerHTML = "";
        for(const channel of channelTree.children) {
          if(channel.clientCount == 0)
            continue;

          let container = document.createElement("div");
          container.classList.add("demo-container");
          div.appendChild(container);

          let placeholder = document.createElement("div");
          placeholder.classList.add("demo-placeholder");
          container.appendChild(placeholder);

          plotEvents(channel, placeholder);
        }

        console.log(channelTree);
      });
    }
    update();
    setInterval(update, 30 * 1000);

    function makeTree(tree, channels, events) {
      tree.events = events.filter(e => e[0] == tree.id);
      tree.children = channels.filter((ch) => ch.parentId == tree.channelId).sort((a, b) => a.position - b.position);
      tree.clientCount = tree.events.reduce((sum, e) => sum+e[2], 0) / (tree.events.length > 0 ? tree.events.length : 1);

      for(const child of tree.children) {
        tree.clientCount += makeTree(child, channels.filter( ch => ch.parentId != tree.channelId), events.filter( e => e[0] != tree.id));
      }
      return tree.clientCount;
    }

    function showTree(tree, el) {
      el.innerHTML = `${formatHeadings(tree.name)}`;
      if(tree.events.length > 0 && (tree.events.length != 1 || tree.events[0][2] != 0))
        for(const event of tree.events) {
          el.innerHTML += `<br>${(new Date(event[1]*1000)).toLocaleTimeString('de-DE', {timeZone: 'Europe/Berlin'})}: ${event[2]}`
        }
      let ul = document.createElement('ul');
      el.appendChild(ul);
      for(const channel of tree.children) {
        if(channel.clientCount == 0)
          continue;
        let li = document.createElement('li');
        ul.appendChild(li);
        showTree(channel, li);
      }
    }

    function formatHeadings(str, html = true) {
      const res = /(?:\[(.)spacer[^\]]*\])?(.*)/.exec(str);
      if(!html)
        return res[2];
      switch(res[1]) {
        case '*':
          return `<div class='channel'>${res[2].repeat(50)}</div>`;
        case 'l':
          return `<div class='channel' align='left'>${res[2]}</div>`;
        case 'c':
          return `<div class='channel' align='center'>${res[2]}</div>`;
        case 'r':
          return `<div class='channel' align='right'>${res[2]}</div>`;
        default:
          return `<div class='channel'>${res[2]}</div>`;
      }
    }
    
    function plotEvents(tree, div) {
      let series = [];
      function iterChilds(tree, prefix="") {
        const data = tree.events.map(e => [e[1]*1000, e[2]]);
        const name = prefix + formatHeadings(tree.name, false);
        if(data.length > 0 && (data.length != 1 || data[0][1] != 0)) {
          series.push({
            label: name,
            data: data,
            lines: {
              show: true,
              fill: true,
              steps: true
            }
          });
        }
        for(const channel of tree.children) {
          iterChilds(channel, name + ' / ');
        }
      }
      iterChilds(tree);

      return $.plot(div, series, {
        xaxis: {
          mode: "time",
          timeBase: "milliseconds",
          timezone: "browser"
        }
      });
    }
    </script>
	</body>
</html>
