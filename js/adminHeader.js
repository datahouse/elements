$(function() {
  'use strict';

  // View Logic
  var hasExpanded = false;

  $('.admin-header-element .minimize').on('click', function(e) {
    $('.admin-header-element').addClass('tiny');
    $('#elements-page-wrapper').removeClass('with-admin-bar');
    hasExpanded = false;
    e.stopImmediatePropagation();
  });

  $('body').on('click', '.admin-header-element.tiny', function() {
    $('.admin-header-element').removeClass('tiny');
    $('#elements-page-wrapper').addClass('with-admin-bar');
    hasExpanded = true;
  });

  //
  // Auto-save functionality for the meta modal dialog
  //
  var formModalDirty = false;
  var formModalLastSave = (new Date()).getTime();
  var FORM_MODAL_SAVE_INTERVAL = 500; // in milliseconds

  function metaFormModalChanged() {
    formModalDirty = true;
    setTimeout(metaFormModalMaybeSave, FORM_MODAL_SAVE_INTERVAL);
  }

  function metaFormModalMaybeSave() {
    if (!formModalDirty) {
      return;
    }
    var now = (new Date()).getTime();
    var lastSaveDelta = now - formModalLastSave;
    if (lastSaveDelta > FORM_MODAL_SAVE_INTERVAL) {
      metaFormModalSave();
      formModalDirty = false;
      formModalLastSave = now;
    }
  }

  function metaFormModalSave() {
    if ($('#metaFormModalUrlDefaults').length > 0) {
      saveSlugs();
    }
    saveTags();
  }

  function addDefaultUrl(language, url) {
    return $('<div/>').addClass('metaFormModalUrlDefaults form-group')
      .append(
        $('<div/>').addClass('url-error')
          .attr('id', 'url-default-' + language + '-error')
      )
      .append(
        $('<input/>').addClass('url form-control')
          .attr('type', 'text')
          .attr('name', 'url_default_' + language)
          .attr('value', url)
          .on('input', '', null, function () {
            metaFormModalChanged();
          })
      )
  }

  function addUrlAlias(idx, url) {
    return $('<div/>').addClass('metaFormModalAliases form-group')
      .append($('<div/>').addClass('url-error')
        .attr('id', 'url-alias-' + idx + '-error')
      )
      .append(
        $('<input/>').addClass('url form-control')
          .attr('type', 'text')
          .attr('name', 'url_alias_' + idx)
          .attr('value', url)
          .on('input', '', null, function() {
            metaFormModalChanged();
          })
      )
      .append(
        $('<i/>').addClass('fa fa-times remove').on('click', function(event) {
          $(event.target).closest('.metaFormModalAliases').remove();
          metaFormModalChanged();
        })
      );
  }

  var $aliases = $('#metaFormModalAliases');
  $aliases.find('.add').on('click', function() {
    var $entries = $aliases.find('.metaFormModalAliasEntries');
    $entries.append(
      addUrlAlias($entries.children().length, '')
    );
  });

  $(document).ready(function () {
    if (typeof PAGE_ELEMENT_ID !== 'undefined') {
      // Collect the URLs from the PAGE_SLUGS global that match the current
      // language and display them to the user.
      var defaultUrl;
      var aliases = [];
      $.each(PAGE_SLUGS, function (idx, url_info) {
        if (url_info.language == EDIT_LANGUAGE) {
          if (url_info.default) {
            defaultUrl = url_info;
          } else {
            aliases.push(url_info);
          }
        }
      });

      var $defaultNodes = $('.metaFormModalUrlDefaultEntries');
      $defaultNodes.append(addDefaultUrl(
        EDIT_LANGUAGE,
        defaultUrl ? defaultUrl.url : ''
      ));

      var $aliasNodes = $('.metaFormModalAliasEntries');
      $.each(aliases, function (idx, url_info) {
        $aliasNodes.append(addUrlAlias(idx, url_info.url));
      });

      // Add an input handler to the tag fields.
      $('.metaFormModalTag input').on('input', '', null, function () {
        metaFormModalChanged();
      });
    }
  });

  $('#pageState').on('click', function() {
    var $modal = $('#statusFormModal');
    // FIXME: the new state shouldn't be hard-coded to 'publish', but
    // chosen from the set of available states for the given application
    // of elements.
    var state = 'published';
    setPageElementState(
      PAGE_ELEMENT_ID,
      ELEMENTS_INFO[PAGE_ELEMENT_ID]['version'],
      state,
      function(response) {
        $modal.modal('hide');
        processJsonStringResponse(response, true);
      },
      function(response) {
        processResponse(response, false);
      }
    );
  });

  /*
  FIXME: instead of the above 'onclick' handler, the following method should
  eventually display a modal dialog allowing the user to select an action
  (rather than just published) and the referenced elements to publish.

  $('#statusFormModal').on('shown.bs.modal', function () {
    $('#statusFormModalSave').focus();
  })
  */

  $('.logout').on('click', function() {
    $.ajax({
      'url': 'admin/logout',
      'type': 'DELETE',
      'dataType': 'text/plain',
      'data': {},
      'success': function(response) {
        // We cannot quite use processResponse here, because the
        // DELETE request has no response.
        if (window.location.href.indexOf('/admin/') > -1) {
          window.location.replace(ROOT_URL + '/');
        } else {
          window.location.reload();
        }
      },
      'error': function(response) {
        processResponse(response, false);
      }
    });
  });

  function saveSlugs() {
    var pageUrls = {};
    var addPageUrl = function (idx, element) {
      var $formInput = $(element).find('.url');
      var nameParts = $formInput.attr('name').split('_');
      var slugKey = nameParts[1] + '-' + nameParts[2];
      var url = $formInput.val().trim();
      if (url == '') {
        // Ensure to reset the error message in that case.
        setSlugErrorMessage(slugKey, '');
        return;
      }
      var isDefault = nameParts[1] == 'default';
      pageUrls[slugKey] = {
        url: url,
        language: EDIT_LANGUAGE,
        default: isDefault
      };
    };

    // collect slugs from the default urls
    $.each($('.metaFormModalUrlDefaultEntries').children(), function (idx, element) {
      addPageUrl(idx, element);
    });
    // collect slugs from the aliases
    $.each($('.metaFormModalAliasEntries').children(), function (idx, element) {
      addPageUrl(idx, element);
    });
    // re-add all slugs for other languages
    $.each(PAGE_SLUGS, function (idx, url_info) {
      if (url_info.language && url_info.language != EDIT_LANGUAGE) {
        pageUrls['unchanged_' + idx] = url_info;
      }
    });

    var callback = function (response) {
      // reset meta modal error
      var $metaFormModal = $('#metaFormModal');
      $metaFormModal.find('.error').text('');

      PAGE_SLUGS = $.map(pageUrls, function(value, key) { return value });
      processJsonStringResponse(response, true);
    };
    var errback = function (response) {
      // reset meta modal error
      var $metaFormModal = $('#metaFormModal');
      $metaFormModal.find('.error').text('');

      processResponse(response, false);
    };
    var vno = ELEMENTS_INFO[PAGE_ELEMENT_ID]['version'];
    var requestUrl = ROOT_URL + '/admin/element/' + PAGE_ELEMENT_ID +
        '/urls/' + vno;
    sendAjaxRequest(JSON.stringify(pageUrls), callback, errback, requestUrl);
  }

  function saveTags() {
    $.each($('.metaFormModalFieldName'), function (idx, node) {
      var field_name = $(node).attr('data-field-name');
      var current_value = $(node).attr('data-current-value');
      var url = ROOT_URL + '/admin/element/' + PAGE_ELEMENT_ID +
          '/field/' + ELEMENTS_INFO[PAGE_ELEMENT_ID]['version'] +
          '/' + field_name + '/' + ELEMENTS_INFO[PAGE_ELEMENT_ID]['language'];
      var $input_node = $('#tag-' + field_name.substr(4));
      var new_value = $input_node.val();
      if (new_value != current_value) {
        $.ajax({
          url: url,
          type: 'POST',
          data: {
            body: $input_node.val(),
            edit_language: EDIT_LANGUAGE
          },
          success: function (json) {
            // don't display a success message, it's just disturbing.
            // FIXME: parsing server responses should be unified.
          },
          error: function (data) {
            var json = JSON.parse(data);
            toastr.error(json.message);
          }
        })
      }
    });
  }

  $('#templateFormModalSave').on('click', function() {
    var $modal = $('#templateFormModal');
    var template = $('#template').val();

    setPageElementTemplate(
        PAGE_ELEMENT_ID,
        ELEMENTS_INFO[PAGE_ELEMENT_ID]['version'],
        template,
        function(response) {
          $modal.modal('hide');
          processResponse(response, true);
        },
        function(response) {
          processResponse(response, false);
        }
    );
    return false;
  });
});

/**
 * A general purpose processor.
 *
 * @param respObj the JSON-encoded response
 * @param success whether or not this was a successful request
 */
function processResponseObject(respObj, success)
{
  var needsReload = false;
  if (typeof respObj.client_info !== 'undefined') {
    needsReload = processClientInfo(respObj.client_info);
  }
  if (!needsReload && typeof respObj.message !== 'undefined' &&
    respObj.message.length > 0
  ) {
    if (success) {
      toastr.success(respObj.message);
    } else {
      toastr.error(respObj.message);
    }
  }
  if (needsReload) {
    if ('default_url' in ELEMENTS_INFO[PAGE_ELEMENT_ID]) {
      document.location.href = ELEMENTS_INFO[PAGE_ELEMENT_ID]['default_url'];
    } else {
      document.location.reload();
    }
  }
}

function processJsonStringResponse(responseText, success)
{
  try {
    var respObj = JSON.parse(responseText);
    return processResponseObject(respObj, success);
  }
  catch (err) {
    console.warn("Invalid JSON response from server: ");
    console.warn(err);
    toastr.error("Invalid server response");
  }
}

/**
 * A general purpose response processor.
 *
 * @param response
 * @param success
 */
function processResponse(response, success)
{
  if (typeof response.getResponseHeader === 'function') {
    if (response.readyState == 4) {  // the 'done' state
      var contentType = response.getResponseHeader('Content-Type');
      if (contentType) {
        var parts = response.getResponseHeader('Content-Type').split(';', 2);
        contentType = parts[0];
      } else {
        contentType = 'text/plain';
      }
      if (contentType == 'text/json' || contentType == 'application/json') {
        processJsonStringResponse(response.responseText, success);
      } else {
        if (success) {
          console.warn("Server returned a success response of unknown type");
        } else {
          toastr.error(response.responseText);
        }
      }
    } else {
      // Some other kind of incomplete state of the request, maybe an abort
      // by the browser, for example. We don't show an error in any of those
      // cases.
    }
  } else if (typeof response === 'object') {
    processResponseObject(response, success);
  } else if (typeof response === 'string') {
    processJsonStringResponse(response, success);
  } else {
    console.warn("Unknown response type: " + (typeof response));
    toastr.error("Internal Server Error");
  }
}

/**
 * This should evolve into a general purpose clientInfo processor, applying
 * all kinds of changes displayed anywhere, called from all AJAX callbacks.
 *
 * @param client_info
 */
function processClientInfo(client_info) {
  var slugKey;
  var needsReload = false;
  $.each(client_info, function (element_id, per_element_info) {
    $.each(per_element_info, function (i, info) {
      var type = info[0];
      var value = info[1];
      switch (type) {
        case 'meta_error':
          var $metaFormModal = $('#metaFormModal');
          $metaFormModal.find('.error').text(value);
          break;
        case 'state':
          var new_state = value;
          ELEMENTS_INFO[element_id]['state'] = new_state;
          if (element_id == PAGE_ELEMENT_ID) {
            $('#pageState').html(new_state);
          }
          break;
        case 'set_default_url':
          ELEMENTS_INFO[element_id]['default_url'] = value;
          break;
        case 'reload_now':
          needsReload = value;
          break;
        case 'version':
          ELEMENTS_INFO[element_id]['version'] = value;
          break;
        case 'slug_good':
          slugKey = value[0];
          setSlugErrorMessage(slugKey, '');
          break;
        case 'slug_taken':
        case 'slug_invalid':
        case 'slug_duplicate':
          slugKey = value[0];
          var errMsg = value[1];
          setSlugErrorMessage(slugKey, errMsg);
          break;
        default:
          console.log("unknown client info type: " + type);
      }
    });
  });
  return needsReload;
}

function setSlugErrorMessage(slugKey, errMsg)
{
  var parts = slugKey.split('_');
  if (parts[0] != 'unchanged') {
    var $errNode = $('#url-' + slugKey + '-error');
    if ($errNode.length == 0) {
      // This may happen if the user removes an alias before the result of
      // the former save operation returns.
      // console.log("failed to find " + 'url-' + slugKey + '-error')
    } else {
      $errNode.text(errMsg);
    }
  }
}

/**
 * update the page elements template
 * @param element_id
 * @param vno
 * @param template
 * @param callback
 * @param errback
 */
function setPageElementTemplate(element_id, vno, template, callback, errback) {
  var url = ROOT_URL + '/admin/element/' + element_id
      + '/template/' + vno;
  sendAjaxRequest(template, callback, errback, url);
}

/**
 * Update the page element state
 *
 * @param element_id hash string
 * @param vno versions number
 * @param new_state element state
 * @param callback function success callback
 * @param errback function error callback
 */
function setPageElementState(element_id, vno, new_state, callback, errback) {
  var url = ROOT_URL + '/admin/element/' + element_id
      + '/state/' + vno;
  var references = {};
  // collect all referenced elements and send them to the server as well
  $.each(ELEMENTS_INFO, function(element_id, info) {
    if (info['type'] == 'referenced') {
      references[element_id] = {
        language: info['language'],
        version: info['version']
      };
    }
  });

  var payload = {
    'new_state': new_state,
    'references': references
  };
  sendAjaxRequest(JSON.stringify(payload), callback, errback, url);
}
