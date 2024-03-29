registerPlugin({
  name: 'Client Statistics Script',
  version: '1.0',
  description: 'log client events to db',
  author: 'mcj201',
  vars: [
    {
      name: 'host',
      title: 'MySQL Host',
      type: 'string'
    },
    {
      name: 'username',
      title: 'MySQL User',
      type: 'string'
    },
    {
      name: 'password',
      title: 'MySQL Password',
      type: 'password'
    },
    {
      name: 'database',
      title: 'MySQL Database',
      type: 'string'
    },
  ],
  autorun: false,
  requiredModules: [
    'db'
  ]
}, function(sinusbot, config, meta) {
  const db = require('db');
  const engine = require('engine');
  const backend = require('backend');
  const event = require('event');
  const helpers = require('helpers');
  let dbc = null; 

  function reconnect() {
    if(!dbc) {
      dbc = db.connect({ driver: 'mysql', host: config.host, username: config.username, password: config.password, database: config.database }, function(err) {
        if (err) {
             engine.log(err);
        } else {
          engine.log('connection successful');
        }
      });
    }
  }

  function remove_non_utf8(str) {

    if ((str === null) || (str === ''))
      return "";
    else
      str = str.toString();
    return str.replace(/[^\w\s\[\]\-:.\/!\?\(\)äáöüéôûµÄÁÖÜß=#<>\+\*\'\\`,\"“„€&%@§~^°|;]/g, '');
  }

  reconnect();
  setInterval(reconnect, 1000 * 3600);
  
  let channelIdMap = {};
  let serverId = null;
  event.on('connect', () => {
    const serverinfo = backend.extended().getServerInfo();
    updateServer(serverinfo, function(id) {
      serverId = id;
      engine.log('serverId: ' + serverId);
      backend.getChannels().forEach(channel => updateChannel(serverId, channel, function(id) {
        channelIdMap[channel.id()] = id;
        updateChannelEvent(id, channel);
      }));
    });
  });
  
  event.on('clientMove', ({ client, fromChannel, toChannel }) => {
    if(fromChannel) updateChannelEvent(channelIdMap[fromChannel.id()], fromChannel);
    if(toChannel)   updateChannelEvent(channelIdMap[toChannel.id()], toChannel);
  });

  event.on('channelCreate', (channel, client) => {
    updateChannel(serverId, channel, function(id) {
      channelIdMap[channel.id()] = id;
      updateChannelEvent(id, channel);
    });
  });

  event.on('channelUpdate', (channel, client) => {
    updateChannel(serverId, channel, function(id) {
      channelIdMap[channel.id()] = id;
      updateChannelEvent(id, channel);
    });
  });


  function updateServer(serverinfo, cb) {
    if(!dbc || !serverinfo) {
      engine.log('error on server update');
      return;
    }
    dbc.query("SELECT id, name FROM server WHERE uid = ?", serverinfo.UID(), function(err, res) {
      if (!err) {
        if(res.length > 0) {
          const serverId = res[0].id;
          dbc.exec("UPDATE server SET name = ? WHERE id = ?", serverinfo.name(), serverId);

          cb(serverId);
        } else {
          dbc.exec("INSERT INTO server (uid, name) VALUES (?, ?)", serverinfo.UID(), serverinfo.name(), function() {
            dbc.query("SELECT id FROM server WHERE uid = ?", serverinfo.UID(), function(err, res) {
              if(!err && res.length > 0) {
                cb(res[0].id);
              } else {
                engine.log(err, res);
              }
            });
          });
        }
      } else {
        engine.log(err, res);
      }
    });
  }

  function updateChannel(serverId, channel, cb) {
    if(!dbc || !serverId) {
      return;
    }
    const parentId = channel.parent() ? channel.parent().id() : NaN;
    dbc.query("SELECT * FROM channel WHERE channelId = ? AND serverId = ?", channel.id(), serverId, function(err, res) {
      if (!err) {
        if(res.length > 0) {
          const id = res[0].id;
          dbc.exec("UPDATE channel SET name = ?, parentId = ?, position = ?, description = ? WHERE channelId = ? AND serverId = ?",
            remove_non_utf8(channel.name()), parentId, channel.position(), remove_non_utf8(channel.description()), channel.id(), serverId);
          cb(id);
        } else {
          dbc.exec("INSERT INTO channel (channelId, name, serverId, parentId, position, description) VALUES (?, ?, ?, ?, ?, ?)", 
            channel.id(), remove_non_utf8(channel.name()), serverId, parentId, channel.position(), remove_non_utf8(channel.description()), function() {
              dbc.query("SELECT id FROM channel WHERE channelId = ? AND serverId = ?", channel.id(), serverId, function(err, res) {
                if(!err && res.length > 0) {
                  cb(res[0].id);
                } else {
                  engine.log(err, res);
                }
              });
          });
        }
      } else {
        engine.log(err, res);
      }
    });
  }

  function updateChannelEvent(id, channel) {
    if(!dbc || !id) {
      return;
    }
    const clients = channel.getClientCount();
    dbc.query("SELECT * FROM channelEvent WHERE channelId = ? AND date > DATE_SUB(NOW(), INTERVAL 1 MINUTE) ORDER BY date DESC LIMIT 1", id, function(err, res) {
      if(!err) {
        if(res.length > 0) {
          engine.log('channel ' + remove_non_utf8(channel.name()) + ' updated to ' + clients + ' clients');
          dbc.exec("UPDATE channelEvent SET clientCount = ? WHERE id = ?", clients, res[0].id);
        } else {
          dbc.query("SELECT * FROM channelEvent WHERE channelId = ? ORDER BY date DESC LIMIT 1", id, function(err, res) {
            if(!err && res.length > 0 && res[0].clientCount === clients)
              return;
            if(clients > 0)
              engine.log('channel ' + remove_non_utf8(channel.name()) + ' has now ' + clients + ' clients');
            dbc.exec("INSERT INTO channelEvent (channelId, clientCount) VALUES (?, ?)", id, clients);
          });
        }
      } else {
        engine.log(err, res);
      }
    });
  }
});