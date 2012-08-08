/**
 * @provides javelin-behavior-repository-crossreference
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-uri
 */

JX.behavior('repository-crossreference', function(config) {

  // NOTE: Pretty much everything in this file is a worst practice. We're
  // constrained by the markup generated by the syntax highlighters.

  var container = JX.$(config.container);
  JX.DOM.alterClass(container, 'repository-crossreference', true);
  JX.DOM.listen(
    container,
    'click',
    'tag:span',
    function(e) {
      if (window.getSelection && !window.getSelection().isCollapsed) {
        return;
      }
      var target = e.getTarget();
      var map = {nc : 'class', nf : 'function'};
      while (target !== document.body) {
        if (JX.DOM.isNode(target, 'span') && (target.className in map)) {
          var symbol = target.textContent || target.innerText;
          var uri = JX.$U('/diffusion/symbol/' + symbol + '/');
          uri.addQueryParams({
            type : map[target.className],
            lang : config.lang,
            projects : config.projects.join(','),
            jump : true
          });
          window.open(uri);
          e.kill();
          break;
        }
        target = target.parentNode;
      }
    });

});
