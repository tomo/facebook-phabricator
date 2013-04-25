/**
 * @provides phortune-credit-card-form
 * @requires javelin-install
 *           javelin-dom
 */

/**
 * Simple wrapper for credit card forms generated by `PhortuneCreditCardForm`.
 *
 * To construct an object for a form:
 *
 *   new JX.PhortuneCreditCardForm(form_root_node);
 *
 * To read card data from a form:
 *
 *   var data = ccform.getCardData();
 */
JX.install('PhortuneCreditCardForm', {
  construct : function(root) {
    this._root = root;
  },

  members : {
    _root : null,

    getCardData : function() {
      var root = this._root;

      return {
        number : JX.DOM.find(root, 'input',  'number-input').value,
        cvc    : JX.DOM.find(root, 'input',  'cvc-input'   ).value,
        month  : JX.DOM.find(root, 'select', 'month-input' ).value,
        year   : JX.DOM.find(root, 'select', 'year-input'  ).value
      };
    }
  }

});
