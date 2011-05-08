/**
 * @provides javelin-behavior-differential-populate
 * @requires javelin-behavior
 *           javelin-request
 *           javelin-util
 *           javelin-dom
 */

JX.behavior('differential-populate', function(config) {

  function onresponse(target, response) {
    JX.DOM.replace(JX.$(target), JX.$H(response));
  }

  for (var k in config.registry) {
    new JX.Request(config.uri, JX.bind(null, onresponse, k))
      .setData({
        id: config.registry[k][0],
        vs: config.registry[k][1],
        whitespace: config.whitespace
      })
      .send();
  }

});
