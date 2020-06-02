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
		<script language="javascript" type="text/javascript" src="../flot/jquery.flot.navigate.min.js"></script>
		<script language="javascript" type="text/javascript" src="../flot/jquery.flot.resize.min.js"></script>

    </head>
	<body>
    <div class="wrapper">
      <header class="main-head">TS3 channel usage</header>
      <div id="graphs"></div>
      <div id="settings">
        <input type=button onclick="updateDate(addDays(startDate, -1))" value="-1 Day">
        <input type="date" onchange="updateDate(this.valueAsDate)" id="date">
        <input type=button onclick="updateDate(addDays(startDate, 1))" value="+1 Day">
      </div>
      <div id="tree"></div>
    </div>
    <script>
    var startDate, endDate;
    var eventTimeRange = [0, 0];

    function updateDate(newDate) {
      newDate.setHours(6, 0, 0, 0);
      if(newDate.getTime() > eventTimeRange[1]) 
        newDate = new Date(eventTimeRange[1]);
      if(newDate.getTime() < eventTimeRange[0])
        newDate = new Date(eventTimeRange[0]);

      newDate.setHours(6, 0, 0, 0);
      startDate = newDate;
      endDate = addDays(startDate, 1);
      document.getElementById('date').valueAsDate = startDate;
      updatePlots();
    }
    
    
    function update(init = false) {
      fetch("ajax.php").then(res=>res.json()).then(function(json) {
        console.log(json);
        let channelTree = {channelId: 0, name: 'Server', id: 0};
        eventTimeRange[0] = json.events.reduce((min, e) => min && (min < e[1]) ? min : e[1]) * 1000 - 12*3600000;
        eventTimeRange[1] = json.events.reduce((max, e) => max && (max > e[1]) ? max : e[1]) * 1000 + 12*3600000;
        var dateInp = document.getElementById('date');
        dateInp.setAttribute('min', (new Date(eventTimeRange[0])).toISOString().split("T")[0]);
        dateInp.setAttribute('max', (new Date(eventTimeRange[1])).toISOString().split("T")[0]);

        if(init) 
          updateDate(new Date());
        makeTree(channelTree, json.channels, json.events);
        showTree(channelTree, document.getElementById('tree'));

        if(init) {
          for(const channel of channelTree.children) {
            if(channel.activeCount == 0)
              continue;
            let inp = document.getElementById(`cb_${channel.channelId}`);
            inp.checked = true;

            plotEvents(channel, document.getElementById("graphs"), true);
          }
        }
        console.log(channelTree);
      });
    }
    update(true);
    setInterval(update, 30 * 1000);

    function makeTree(tree, channels, events) {
      tree.events = events.filter(e => e[0] == tree.id);
      if(tree.events.length > 0) {
        tree.events.splice(0,0,[tree.events[0][0], (new Date()).getTime()/1000, tree.events[0][2]]);
      }
      tree.children = channels.filter((ch) => ch.parentId == tree.channelId).sort((a, b) => a.position - b.position);
      tree.clientCount = tree.events.reduce((sum, e) => sum+e[2], 0) / (tree.events.length > 0 ? tree.events.length : 1);
      tree.activeCount = tree.events.length > 0 ? tree.events[0][2] : 0;
      for(const child of tree.children) {
        makeTree(child, channels.filter( ch => ch.parentId != tree.channelId), events.filter( e => e[0] != tree.id));
        tree.clientCount += child.clientCount;
        tree.activeCount += child.activeCount;
      }
    }
    function showTree(tree, el) {
      let ul = document.getElementById(`ul_${tree.channelId}`);
      if(!ul) {
        let inp = document.createElement('input');
        inp.type = 'checkbox';
        inp.id = `cb_${tree.channelId}`;
        inp.classList.add('checkbox');
        el.appendChild(inp);

        let cnt = document.createElement('label');
        cnt.classList.add("count");
        cnt.htmlFor = `cb_${tree.channelId}`;
        el.appendChild(cnt);
        
        let label = document.createElement('label');
        label.htmlFor = `cb_${tree.channelId}`;
        el.appendChild(label);

        ul = document.createElement('ul');
        ul.id = `ul_${tree.channelId}`;
        el.appendChild(ul);
      }
      let inp = document.getElementById(`cb_${tree.channelId}`);
      inp.onchange = function() {
        plotEvents(tree, document.getElementById("graphs"), this.checked);
      };
      if(inp.checked) {
        plotEvents(tree, document.getElementById("graphs"), true);
      }

      if(tree.events.length > 0)
        inp.nextSibling.textContent = tree.events[0][2] != 0 ? tree.events[0][2] : '';

      inp.nextSibling.nextSibling.innerHTML = formatHeadings(tree.name);

      let prevLi = {};
      let showSpacer = false;
      for(const channel of tree.children) {
        if(channel.clientCount == 0) {
          showSpacer = formatHeadings(channel.name, false).length == 1;
          continue;
        }
        let li = document.getElementById(`li_${channel.channelId}`);
        if(!li) {
          li = document.createElement('li');
          li.id = `li_${channel.channelId}`;
          if(prevLi.nextSibling) 
            ul.insertBefore(li, prevLi.nextSibling);
          else
            ul.appendChild(li);
        }
        if(showSpacer) {
          li.classList.add("spacer");
          showSpacer = false;
        }
        prevLi = li;
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

    var plots = {};
    var viewMin = 0;
    var viewMax = 0;
    function plotEvents(tree, div, visible = true) {
      let placeholder = document.getElementById(`graph_${tree.channelId}`);
      if(!placeholder) {

        container = document.createElement("div");
        container.classList.add("demo-container");
        div.appendChild(container);

        placeholder = document.createElement("div");
        placeholder.classList.add("demo-placeholder");
        placeholder.id = `graph_${tree.channelId}`;
        container.appendChild(placeholder);
      }

      if(!document.getElementById(`cb_${tree.channelId}`).checked) {
        placeholder.parentNode.style.display = 'none';
        return;
      } else {
        placeholder.parentNode.style.display = 'block';
      }
      let series = [];
      function iterChilds(tree, prefix="") {
        const data = tree.events.map(e => [e[1]*1000, e[2]]);
        const name = prefix + formatHeadings(tree.name, false);
        if(data.length > 0 && (data.length != 2 || data[0][1] != 0)) {
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
      if(!plots[tree.channelId]) {
        plots[tree.channelId] = $.plot(placeholder, series, {
          xaxis: {
            mode: "time",
            timeBase: "milliseconds",
            timezone: "browser",
            min: viewMin ? viewMin : startDate.getTime(),
            max: viewMax ? viewMax : endDate.getTime(),
            zoomRange: [60000, null],
            panRange: eventTimeRange
          },
          yaxis: {
            zoomRange: false,
            panRange: false
          },
          zoom: {
            interactive: true
          },
          pan: {
            interactive: true
          },
          legend: {
            position: 'nw'
          }
        });
        $(placeholder).bind("plotpan plotzoom", function (event, plot) {
          var axes = plot.getAxes();
          viewMin = axes.xaxis.min;
          viewMax = axes.xaxis.max;
          for(const id in plots) {
            plots[id].getOptions().xaxes[0].min = axes.xaxis.min;
            plots[id].getOptions().xaxes[0].max = axes.xaxis.max;
            plots[id].setupGrid();
            plots[id].draw();
          }
        });
      } else {
        plots[tree.channelId].getOptions().xaxis.panRange = eventTimeRange;
        plots[tree.channelId].setData(series);
        plots[tree.channelId].setupGrid();
        plots[tree.channelId].draw();
      }
    }

    function updatePlots() {
      viewMin = startDate.getTime();
      viewMax = endDate.getTime();
      for(const id in plots) {
        let opt = plots[id].getXAxes()[0].options;
        opt.min = viewMin;
        opt.max = viewMax;
        plots[id].setupGrid();
        plots[id].draw();
      }
    }

    function addDays(date, days) {
      var result = new Date(date);
      result.setDate(result.getDate() + days);
      return result;
    }
    </script>
	</body>
</html>
