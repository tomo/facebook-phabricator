/**
 * @provides javelin-behavior-differential-edit-inline-comments
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-util
 *           javelin-vector
 *           differential-inline-comment-editor
 */

JX.behavior('differential-edit-inline-comments', function(config) {

  var selecting = false;
  var reticle = JX.$N('div', {className: 'differential-reticle'});
  JX.DOM.hide(reticle);
  document.body.appendChild(reticle);

  var origin = null;
  var target = null;
  var root   = null;
  var changeset = null;

  var editor = null;

  function updateReticle() {
    var top = origin;
    var bot = target;
    if (JX.$V(top).y > JX.$V(bot).y) {
      var tmp = top;
      top = bot;
      bot = tmp;
    }
    var code = target.nextSibling;

    var pos = JX.$V(top).add(1 + JX.Vector.getDim(target).x, 0);
    var dim = JX.Vector.getDim(code).add(-4, 0);
    dim.y = (JX.$V(bot).y - pos.y) + JX.Vector.getDim(bot).y;

    pos.setPos(reticle);
    dim.setDim(reticle);

    JX.DOM.show(reticle);
  }

  function hideReticle() {
    JX.DOM.hide(reticle);
  }

  JX.DifferentialInlineCommentEditor.listen('done', function() {
    selecting = false;
    editor = false;
    hideReticle();
  });

  function isOnRight(node) {
    return node.parentNode.firstChild != node;
  }

  function isNewFile(node) {
    var data = JX.Stratcom.getData(root);
    return isOnRight(node) || (data.left != data.right);
  }

  function getRowNumber(th_node) {
    try {
      return parseInt(th_node.id.match(/^C\d+[ON]L(\d+)$/)[1], 10);
    } catch (x) {
      return undefined;
    }
  }

  JX.Stratcom.listen(
    'mousedown',
    ['differential-changeset', 'tag:th'],
    function(e) {
      if (editor  ||
          selecting ||
          getRowNumber(e.getTarget()) === undefined) {
        return;
      }

      selecting = true;
      root = e.getNode('differential-changeset');

      origin = target = e.getTarget();

      var data = e.getNodeData('differential-changeset');
      if (isOnRight(target)) {
        changeset = data.left;
      } else {
        changeset = data.right;
      }

      updateReticle();

      e.kill();
    });

  JX.Stratcom.listen(
    'mouseover',
    ['differential-changeset', 'tag:th'],
    function(e) {
      if (!selecting ||
          editor ||
          (getRowNumber(e.getTarget()) === undefined) ||
          (isOnRight(e.getTarget()) != isOnRight(origin)) ||
          (e.getNode('differential-changeset') !== root)) {
        return;
      }

      target = e.getTarget();

      updateReticle();
    });

  JX.Stratcom.listen(
    'mouseup',
    null,
    function(e) {
      if (editor || !selecting) {
        return;
      }

      var o = getRowNumber(origin);
      var t = getRowNumber(target);

      var insert;
      var len;
      if (t < o) {
        len = (o - t);
        o = t;
        insert = origin.parentNode;
      } else {
        len = (t - o);
        insert = target.parentNode;
      }

      editor = new JX.DifferentialInlineCommentEditor(config.uri)
        .setTemplates(config.undo_templates)
        .setOperation('new')
        .setChangeset(changeset)
        .setLineNumber(o)
        .setLength(len)
        .setIsNew(isNewFile(target) ? 1 : 0)
        .setOnRight(isOnRight(target) ? 1 : 0)
        .setRow(insert.nextSibling)
        .start();

      e.kill();
    });

  JX.Stratcom.listen(
    ['mouseover', 'mouseout'],
    'differential-inline-comment',
    function(e) {
      if (selecting || editor) {
        return;
      }

      if (e.getType() == 'mouseout') {
        hideReticle();
      } else {
        root = e.getNode('differential-changeset');

        var data = e.getNodeData('differential-inline-comment');
        var change = e.getNodeData('differential-changeset');

        var prefix;
        if (data.on_right) {
          prefix = 'C' + (change.left) + 'NL';
        } else {
          prefix = 'C' + (change.right) + 'OL';
        }

        origin = JX.$(prefix + data.number);
        target = JX.$(prefix + (parseInt(data.number, 10) +
                                parseInt(data.length, 10)));

        updateReticle();
      }
    });

  var action_handler = function(op, e) {
    var data = e.getNodeData('differential-inline-comment');
    var node = e.getNode('differential-inline-comment');

    editor = new JX.DifferentialInlineCommentEditor(config.uri)
      .setTemplates(config.undo_templates)
      .setOperation(op)
      .setID(data.id)
      .setOnRight(data.on_right)
      .setOriginalText(data.original)
      .setRow(node.parentNode.parentNode)
      .start();

    e.kill();
  }

  for (var op in {'edit' : 1, 'delete' : 1, 'reply' : 1}) {
    JX.Stratcom.listen(
      'click',
      ['differential-inline-comment', 'differential-inline-' + op],
      JX.bind(null, action_handler, op));
  }

});

