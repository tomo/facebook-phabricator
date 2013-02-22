/**
 * @provides javelin-behavior-pholio-mock-view
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-vector
 *           javelin-magical-init
 *           javelin-request
 */
JX.behavior('pholio-mock-view', function(config) {
  var is_dragging = false;
  var is_typing = false;

  var wrapper = JX.$('mock-wrapper');
  var image;
  var imageData;
  var startPos;
  var endPos;

  var selection_border;
  var selection_fill;
  var active_image;

  function get_image(id) {
    for (var ii = 0; ii < config.images.length; ii++) {
      if (config.images[ii].id == id) {
        return config.images[ii];
      }
    }
    return null;
  }

  function select_image(image_id) {
    var image = get_image(image_id);
    active_image = image;

    var main = JX.$(config.mainID);
    main.src = image.fullURI;
    JX.DOM.show(main);

    // NOTE: This is to clear inline comment reticles.
    JX.DOM.setContent(wrapper, main);

    load_inline_comments();
  }

  JX.Stratcom.listen(
    'click',
    'mock-thumbnail',
    function(e) {
      e.kill();
      select_image(e.getNodeData('mock-thumbnail').imageID);
    });

  // Select and show the first image.
  select_image(config.images[0].id);

  function draw_rectangle(nodes, current, init) {
    for (var ii = 0; ii < nodes.length; ii++) {
      var node = nodes[ii];

      JX.$V(
        Math.abs(current.x-init.x),
        Math.abs(current.y-init.y))
      .setDim(node);

      JX.$V(
        (current.x-init.x < 0) ? current.x:init.x,
        (current.y-init.y < 0) ? current.y:init.y)
      .setPos(node);
    }
  }

  function getRealXY(parent, point) {
    var pos = {x: (point.x - parent.x), y: (point.y - parent.y)};
    var dim = JX.Vector.getDim(image);

    pos.x = Math.max(0, Math.min(pos.x, dim.x));
    pos.y = Math.max(0, Math.min(pos.y, dim.y));

    return pos;
  }

  JX.Stratcom.listen('mousedown', 'mock-wrapper', function(e) {
    if (!e.isNormalMouseEvent()) {
      return;
    }

    if (is_typing) {
      JX.DOM.remove(JX.$('pholio-new-inline-comment-dialog'));
      JX.DOM.remove(selection_fill);
      JX.DOM.remove(selection_border);
    }

    image = JX.$(config.mainID);
    imageData = JX.Stratcom.getData(image);

    e.getRawEvent().target.draggable = false;
    is_dragging = true;

    startPos = getRealXY(JX.$V(wrapper),JX.$V(e));

    selection_border = JX.$N(
      'div',
      {className: 'pholio-mock-select-border'});

    selection_fill = JX.$N(
      'div',
      {className: 'pholio-mock-select-fill'});

    JX.$V(startPos.x, startPos.y).setPos(selection_border);
    JX.$V(startPos.x, startPos.y).setPos(selection_fill);

    JX.DOM.appendContent(wrapper, [selection_border, selection_fill]);
  });

  JX.enableDispatch(document.body, 'mousemove');
  JX.Stratcom.listen('mousemove',null, function(e) {
    if (!is_dragging) {
      return;
    }

    draw_rectangle(
      [selection_border, selection_fill],
      getRealXY(JX.$V(wrapper),
      JX.$V(e)), startPos);
  });

  JX.Stratcom.listen(
    'mouseup',
    null,
    function(e) {
      if (!is_dragging) {
        return;
      }
      is_dragging = false;
      is_typing = true;

      endPos = getRealXY(JX.$V(wrapper), JX.$V(e));

      var create_inline = new JX.Request("/pholio/inline/save/", function(r) {
        JX.DOM.appendContent(JX.$('pholio-mock-image-container'), JX.$H(r));

        var dialog = JX.$('pholio-new-inline-comment-dialog');

        var wrapperVector = JX.$V(wrapper);
        var wrapperDimensions = JX.Vector.getDim(wrapper);

        JX.$V(
          wrapperVector.x + Math.max(startPos.x,endPos.x),
          wrapperVector.y + Math.max(startPos.y,endPos.y)
        ).setPos(dialog);

        });
      create_inline.addData({mockID: config.mockID});
      create_inline.send();

    });

    function load_inline_comments() {
      var comment_holder = JX.$('mock-inline-comments');
      JX.DOM.setContent(comment_holder, '');

      var id = active_image.id;
      var inline_comments_uri = "/pholio/inline/" + id + "/";
      var inline_comments = new JX.Request(inline_comments_uri, function(r) {

        if (r.length > 0) {
          for(i=0; i < r.length; i++) {
            var inlineSelection = JX.$N(
              'div',
              {
                id: r[i].phid + "_selection",
                className: 'pholio-mock-select-border'
              });

            JX.Stratcom.addData(
              inlineSelection,
              {phid: r[i].phid});

            JX.Stratcom.addSigil(inlineSelection, "image_selection");
            JX.DOM.appendContent(comment_holder, JX.$H(r[i].contentHTML));

            JX.DOM.appendContent(wrapper, inlineSelection);

            JX.$V(r[i].x, r[i].y).setPos(inlineSelection);
            JX.$V(r[i].width, r[i].height).setDim(inlineSelection);

            if (r[i].transactionphid == null) {

              var inlineDraft = JX.$N(
                'div',
                {
                  className: 'pholio-mock-select-fill',
                  id: r[i].phid + "_fill"
                });

              JX.$V(r[i].x, r[i].y).setPos(inlineDraft);
              JX.$V(r[i].width, r[i].height).setDim(inlineDraft);

              JX.Stratcom.addData(
                inlineDraft,
                {phid: r[i].phid});

              JX.Stratcom.addSigil(inlineDraft, "image_selection");
              JX.DOM.appendContent(wrapper, inlineDraft);
            }
          }
        }

        JX.Stratcom.listen(
          'mouseover',
          'image_selection',
          function(e) {
            var data = e.getNodeData('image_selection');

            var inline_comment = JX.$(data.phid + "_comment");
            JX.DOM.alterClass(inline_comment,
              'pholio-mock-inline-comment-highlight', true);
        });

        JX.Stratcom.listen(
          'mouseout',
          'image_selection',
          function(e) {
          var data = e.getNodeData('image_selection');

          var inline_comment = JX.$(data.phid + "_comment");
            JX.DOM.alterClass(inline_comment,
              'pholio-mock-inline-comment-highlight', false);
        });

      });

      inline_comments.send();
    }

    JX.Stratcom.listen(
      'click',
      'inline-delete',
      function(e) {
        var data = e.getNodeData('inline-delete');
        e.kill();
        interrupt_typing();

        JX.DOM.hide(
          JX.$(data.phid + "_comment"),
          JX.$(data.phid + "_fill"),
          JX.$(data.phid + "_selection"));

        var deleteURI = '/pholio/inline/delete/' + data.id + '/';
        var del = new JX.Request(deleteURI, function(r) {

          });
        del.send();

      });

    JX.Stratcom.listen(
      'click',
      'inline-edit',
      function(e) {
        var data = e.getNodeData('inline-edit');
        e.kill();

        interrupt_typing();

        var editURI = "/pholio/inline/edit/" + data.id + '/';

        var edit_dialog = new JX.Request(editURI, function(r) {
          var dialog = JX.$N(
            'div',
            {
              className: 'pholio-edit-inline-popup'
            },
            JX.$H(r));

          JX.DOM.setContent(JX.$(data.phid + '_comment'), dialog);
        });

        edit_dialog.send();
      });

    JX.Stratcom.listen(
      'click',
      'inline-edit-cancel',
      function(e) {
        var data = e.getNodeData('inline-edit-cancel');
        e.kill();
        load_inline_comment(data.id);
    });

    JX.Stratcom.listen(
      'click',
      'inline-edit-submit',
      function(e) {
        var data = e.getNodeData('inline-edit-submit');
        var editURI = "/pholio/inline/edit/" + data.id + '/';
        e.kill();

        var edit = new JX.Request(editURI, function(r) {
          load_inline_comment(data.id);
        });
        edit.addData({
          op: 'update',
          content: JX.DOM.find(JX.$(data.phid + '_comment'), 'textarea').value
        });
        edit.send();
    });

    JX.Stratcom.listen(
      'click',
      'inline-save-cancel',
      function(e) {
        e.kill();

        interrupt_typing();
      }
    );

    JX.Stratcom.listen(
      'click',
      'inline-save-submit',
      function(e) {
        e.kill();

        var new_content = JX.DOM.find(
          JX.$('pholio-new-inline-comment-dialog'),
          'textarea').value;

        if (new_content == null || new_content.length == 0) {
          alert("Empty comment")
          return;
        }

        var saveURI = "/pholio/inline/save/";

        var inlineComment = new JX.Request(saveURI, function(r) {
          if (!r.success) return;

          JX.DOM.appendContent(
            JX.$('mock-inline-comments'),
            JX.$H(r.contentHTML));

            JX.Stratcom.addSigil(selection_fill, 'image_selection');
            selection_fill.id = r.phid + '_fill';
            JX.Stratcom.addData(selection_fill, {phid: r.phid});
            selection_border.id = r.phid + '_selection';

            JX.DOM.remove(JX.$('pholio-new-inline-comment-dialog'));
            is_typing = false;
          });

        var commentToAdd = {
          mockID: config.mockID,
          op: 'save',
          imageID: active_image.id,
          startX: Math.min(startPos.x, endPos.x),
          startY: Math.min(startPos.y, endPos.y),
          endX: Math.max(startPos.x,endPos.x),
          endY: Math.max(startPos.y,endPos.y),
          comment: new_content
        };

        inlineComment.addData(commentToAdd);
        inlineComment.send();


      }
    );

    function load_inline_comment(id) {
      var viewInlineURI = '/pholio/inline/view/' + id + '/';
      var inline_comment = new JX.Request(viewInlineURI, function(r) {
        JX.DOM.replace(JX.$(r.phid + '_comment'), JX.$H(r.contentHTML));
      });
      inline_comment.send();
    }

    function interrupt_typing() {
      if (is_typing == true) {
        JX.DOM.remove(selection_fill);
        JX.DOM.remove(selection_border);
        JX.DOM.remove(JX.$('pholio-new-inline-comment-dialog'));
        is_typing = false;
      }
    }

    load_inline_comments();
});
