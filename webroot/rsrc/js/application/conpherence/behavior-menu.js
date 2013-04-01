/**
 * @provides javelin-behavior-conpherence-menu
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-request
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-behavior-device
 */

JX.behavior('conpherence-menu', function(config) {

  var thread = {
    selected: null,
    node: null,
    visible: null
  };

  function selectthread(node) {
    if (node === thread.node) {
      return;
    }

    if (thread.node) {
      JX.DOM.alterClass(thread.node, 'conpherence-selected', false);
      JX.DOM.alterClass(thread.node, 'hide-unread-count', false);
    }

    JX.DOM.alterClass(node, 'conpherence-selected', true);
    JX.DOM.alterClass(node, 'hide-unread-count', true);

    thread.node = node;

    var data = JX.Stratcom.getData(node);
    thread.selected = data.phid;

    // TODO: These URIs don't work yet, so don't push them until they do.
    // JX.History.push(config.base_uri + 'view/' + data.id + '/');

    redrawthread();
  }

  function redrawthread() {
    if (!thread.node) {
      return;
    }
    if (thread.visible == thread.selected) {
      return;
    }

    var data = JX.Stratcom.getData(thread.node);

    var uri = config.base_uri + 'view/' + data.id + '/';
    var widget_uri = config.base_uri + 'widget/' + data.id + '/';

    new JX.Workflow(uri, {})
      .setHandler(onresponse)
      .start();

    new JX.Workflow(widget_uri, {})
      .setHandler(onwidgetresponse)
      .start();

    thread.visible = thread.selected;
  }

  function onwidgetresponse(response) {
    var widgets = JX.$H(response.widgets);
    var widgetsRoot = JX.$(config.widgets_pane);
    JX.DOM.setContent(widgetsRoot, widgets);
  }

  function onresponse(response) {
    var header = JX.$H(response.header);
    var messages = JX.$H(response.messages);
    var form = JX.$H(response.form);
    var headerRoot = JX.$(config.header);
    var messagesRoot = JX.$(config.messages);
    var formRoot = JX.$(config.form_pane);
    var widgetsRoot = JX.$(config.widgets_pane);
    var menuRoot = JX.$(config.menu_pane);
    JX.DOM.setContent(headerRoot, header);
    JX.DOM.setContent(messagesRoot, messages);
    messagesRoot.scrollTop = messagesRoot.scrollHeight;
    JX.DOM.setContent(formRoot, form);
  }

  JX.Stratcom.listen(
    'click',
    'conpherence-menu-click',
    function(e) {
      e.kill();
      selectthread(e.getNode('conpherence-menu-click'));
    });

  JX.Stratcom.listen('click', 'conpherence-edit-metadata', function (e) {
    e.kill();
    var root = JX.$(config.form_pane);
    var form = JX.DOM.find(root, 'form');
    var data = e.getNodeData('conpherence-edit-metadata');
    new JX.Workflow.newFromForm(form, data)
      .setHandler(function (r) {
        // update the header
        JX.DOM.setContent(
          JX.$(config.header),
          JX.$H(r.header)
        );

        // update the menu entry as well
        JX.DOM.replace(
          JX.$(r.conpherence_phid + '-nav-item'),
          JX.$H(r.nav_item)
        );
      })
      .start();
  });

  JX.Stratcom.listen('click', 'show-older-messages', function(e) {
    e.kill();
    var last_offset = e.getNodeData('show-older-messages').offset;
    var conf_id = e.getNodeData('show-older-messages').ID;
    JX.DOM.remove(e.getNode('show-older-messages'));
    var messages_root = JX.$(config.messages);
    new JX.Request('/conpherence/view/'+conf_id+'/', function(r) {
      var messages = JX.$H(r.messages);
      JX.DOM.prependContent(messages_root,
      JX.$H(messages));
    }).setData({ offset: last_offset+1 }).send();
  });


  // On mobile, we just show a thread list, so we don't want to automatically
  // select or load any threads. On Desktop, we automatically select the first
  // thread.

  function ondevicechange() {
    if (JX.Device.getDevice() != 'desktop') {
      return;
    }

    // If there's no thread selected yet, select the first thread.
    if (!thread.selected) {
      var threads = JX.DOM.scry(document.body, 'a', 'conpherence-menu-click');
      if (threads.length) {
        selectthread(threads[0]);
      }
    }

    // We might have a selected but undrawn thread for
    redrawthread();
  }

  JX.Stratcom.listen('phabricator-device-change', null, ondevicechange);
  ondevicechange();


  // If there's a currently visible thread, select it.
  if (config.selected_conpherence_id) {
    var threads = JX.DOM.scry(document.body, 'a', 'conpherence-menu-click');
    for (var ii = 0; ii < threads.length; ii++) {
      var data = JX.Stratcom.getData(threads[ii]);
      if (data.phid == config.selected_conpherence_id) {
        selectthread(threads[ii]);
        break;
      }
    }
  }

});
