(function ($) {

var ExecuteOnceReady = function(exec, error, final) {
  this.execute_function = exec;
  this.error_function = error;
  this.final_function = final;
  this.executed = false;
  this.started = false;
  this.needed = 0;
  this.veto = false;
  return this;
}
ExecuteOnceReady.prototype.execute = function() {
  if (!this.executed) {
    this.executed = true;
    if (!this.veto) {
      this.execute_function();
    }
    else {
      if (this.error_function) {
        this.error_function();
      }
    }
    if (this.final_function) {
      this.final_function();
    }
  }
}
ExecuteOnceReady.prototype.start = function() {
  var self = this;
  this.started = true;
  if (this.needed <= 0) {
    // Force this to be asynchronous.
    setTimeout(function() { self.execute(); }, 0);
  }
}
ExecuteOnceReady.prototype.ready = function(veto) {
  this.veto = this.veto || veto;
  this.needed--;
  if (this.started && this.needed <= 0) {
    this.execute();
  }
}
ExecuteOnceReady.prototype.error = function() {
  this.ready(true);
}
ExecuteOnceReady.prototype.need = function() {
  this.needed++;
}


var Webform = function($form) {
  this.$form = $form;
  this.activeButton = null;
  this.passSubmit = false;
  this.bind();
  return this;
};

Webform.prototype.bind = function() {
  var self = this;
  this.$form.find('.form-actions input[type=submit]').click(function(event) {
    if (!self.passSubmit) {
      self.activeButton = event.target;
    }
  });
  this.$form.bind('submit', function (event) {
    var button = self.activeButton;
    if (button && $(button).attr('formnovalidate') || self.passSubmit) {
      return;
    }
    event.preventDefault();
    self.showProgress();
    self.validate(new ExecuteOnceReady(
      self.submitFunction(),
      function() {
        self.removeProgress();
        self.activeButton = null;
      },
      null
    ));
  });
  if (this.$form.find('[name=webform_ajax_wrapper_id]').length > 0) {
    function beforeSubmit(form_values, $form, options) {
      var ed = options.data;
      var button = $form.find('input[name="'+ed._triggering_element_name+'"][value="'+ed._triggering_element_value+'"]').first();
      if (button && $(button).attr('formnovalidate') || self.passSubmit) {
        return true;
      }
      self.activeButton = button;
      self.showProgress();
      self.validate(new ExecuteOnceReady(
        self.ajaxSubmitFunction(options),
        null,
        function() {
          self.removeProgress();
          self.activeButton = null;
        }
      ));
      return false;
    }
    this.$form.find('.ajax-processed').each(function () {
      var ajax_id = $(this).attr('id');
      if (ajax_id in Drupal.ajax) {
        var s = Drupal.ajax[ajax_id].options
        var originalBeforeSubmit = s.beforeSubmit
        s.beforeSubmit = function (form_values, $form, options) {
          var ret = originalBeforeSubmit(form_values, $form, options);
          if (typeof ret == 'undefined') {
            ret = true;
          }
          return ret && beforeSubmit(form_values, $form, options);
        }
      }
    });
  }
  // Keep track of the selected paymethod and update the currently visible form.
  this.$form.find('.paymethod-select-wrapper').bind('change', function(event) {
    if ($(event.target).attr('name') === 'submitted[paymethod_select][payment_method_selector]') {
      var pmid = $('.paymethod-select-radios input:checked', this).val();
      if (this.dataset.pmidSelected !== pmid) {
        this.dataset.pmidSelected = pmid;
        self.updatePaymethodForms($('[data-pmid='+pmid+']', this));
      }
    }
  })
  self.updatePaymethodForms();
};

Webform.prototype.updatePaymethodForms = function($fieldsets) {
  // Fields required by a paymethod should be required when they are displayed.
  $fieldsets = $fieldsets || this.getSelectedFieldsets();
  $fieldsets.each(function() {
    if (!document.body.contains(this)) {
      // Guard against running for unmounted elements.
      return
    }
    $(this).find('[data-controller-required]').each(function() {
      this.required = true;
      if (typeof $.fn.rules === 'function') {
        $(this).rules('add', {required: true});
      }
    })
    $(this).siblings('.payment-method-form').each(function() {
      this.required = false;
      if (typeof $.fn.rules === 'function') {
        $(this).rules('remove', 'required');
      }
    });
  });

}

Webform.prototype.getSelectedFieldsets = function() {
  var $fieldsets = $([]);
  this.$form.find('.paymethod-select-wrapper').each(function() {
    var pmid = this.dataset.pmidSelected;
    if (pmid) {
      $.merge($fieldsets, $('[data-pmid='+pmid+']', this));
    }
    else {
      $.merge($fieldsets, $('.payment-method-form', this).first());
    }
  });
  return $fieldsets;
}

Webform.prototype.validate = function(submitter) {
  var self = this;
  self.jsValidation = false;
  if (Drupal.payment_handler) {
    this.getSelectedFieldsets().each(function() {
      var pmid = parseInt(this.dataset.pmid);
      if (pmid in Drupal.payment_handler) {
        var ret = Drupal.payment_handler[pmid](pmid, $(this), submitter, self);
        if (!ret) {
          submitter.need();
        }
        self.jsValidation = true;
      }
    });
  }
  submitter.start();
};

Webform.prototype.showSuccess = function(message) {
  if (this.jsValidation) {
    $('<div class="messages status payment-success">' + message + '</div>')
      .insertAfter(this.$form.find('.paymethod-select-wrapper'));
  }
}

Webform.prototype.submitFunction = function() {
  var self = this;
  var button = this.activeButton;
  return function() {
    self.passSubmit = true;
    if (button) {
      // Create a temporary non-disabled clone of the button and click it.
      $(button).clone().prop('disabled', false).hide()
        .appendTo($(button).parent()).click().detach();
    }
    else {
      self.$form.submit();
    }
    self.passSubmit = false;
  };
};

Webform.prototype.ajaxSubmitFunction = function(options) {
  var self = this;
  return function() {
    self.passSubmit = true;
    self.$form.ajaxSubmit(options);
    self.passSubmit = false;
  }
};

Webform.prototype.showProgress = function() {
  this.buttons = this.$form.find('input[type=submit]:not(:disabled)');
  this.buttons.prop('disabled', true);
  this.progress_element = $('<div class="ajax-progress ajax-progress-throbber"><div class="throbber">&nbsp;</div></div>');
  $(this.activeButton).after(this.progress_element);
};

Webform.prototype.removeProgress = function() {
  this.progress_element.remove();
  this.buttons.prop('disabled', false);
}

Drupal.behaviors.WebformPaymethodSelect = {
  attach: function(context) {
    var self = this;
    $('.payment-method-form', context).closest('form').each(function() {
      var $form = $(this);
      new Webform($form);
    });
  },
};

})(jQuery);
