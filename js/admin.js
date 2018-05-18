$(function() {
  'use strict';

  $('.elements__add-button').on('click', function (event) {
    var element_id = event.target.getAttribute('data-element-id');
    var sub_name = event.target.getAttribute('data-sub-name');
    if (element_id in ELEMENTS_INFO) {
      addToEleLoop(
          element_id,
          sub_name,
          function (response) {
            processResponse(response, true);
          },
          function (response) {
            processResponse(response, false);
          }
      );
    } else {
      toastr.error("Fatal error: unknown element id: " + element_id);
      console.log("Unknown element id: " + element_id);
    }
  });

  $('.elements__panel').parent().hover(function(event) {
    $(this).addClass("elements__selectable");
    var $controller = $(this).find('.elements__panel');
    $controller.show();
  }, function(event) {
    $(this).removeClass("elements__selectable");
    var $controller = $(this).find('.elements__panel');
    $controller.hide();
  });

  $('div.elements__panel .elements__btn').on('click', function(event) {
    var $btn = $(event.target);
    var limit = 10;
    while (limit > 0 && !$btn.hasClass('elements__btn')) {
      $btn = $btn.parent();
      limit -= 1;
    }
    if (!$btn.hasClass('elements__btn')) {
      console.log("button not found.");
      return;
    }

    var $selector = $btn.parent();
    while (limit > 0 && !$selector.hasClass('elements__panel')) {
      $selector = $selector.parent();
      limit -= 1;
    }
    if (!$selector.hasClass('elements__panel')) {
      console.log("reference selector element not found.");
      return;
    }

    var base_element_id = $selector.attr('data-element-id');
    var action = $btn.attr('data-action');
    if (action == 'prev' || action == 'next') {
      var ref_name = $selector.attr('data-ref-name');
      // console.log("acting on reference " + ref_name + ", element " + base_element_id);

      var options = getOptionsForRefSelector($selector, ref_name);
      var old_idx = getSelectedIndex(options);
      // console.log("old index: " + old_idx + " old element_id " + options[old_idx].element_id);

      // Select the next or previous element in the list of options, excluding
      // the empty element at the very end.
      var new_idx = (old_idx + (action == 'next' ? 1 : -1))
          % (options.length - 1);
      if (new_idx < 0) new_idx += options.length - 1;
      var new_ref_element_id = options[new_idx].element_id;
      // console.log("new index: " + new_idx + " new element_id " + new_ref_element_id);

      options[old_idx].node
          .addClass('elements__inactive')
          .removeClass('elements__selected');
      options[new_idx].node
          .addClass('elements__selected')
          .removeClass('elements__inactive');

      saveReferencedElement(base_element_id, ref_name, new_ref_element_id);
    } else if (action == 'delete') {
      var sub_name = $selector.attr('data-sub-name');
      var sub_index = $selector.attr('data-sub-index');
      // console.log("acting on sub-element " + sub_name + ", index " + sub_index + ", element " + base_element_id);
      deleteSubElement(base_element_id, sub_name, sub_index);
    } else {
      console.log("error: unknown action: " + action);
    }
  });

  // For the 'cache' sub-site, only
  $('#cacheTabs').find('a').click(function (e) {
    e.preventDefault();
    $(this).tab('show')
  })
});

/**
 * append an element to an eleloop
 *
 * @param element_id id of the parent element to which to add a child or sub
 * @param sub_name   name of the sub-element collection (optional)
 * @param callback   success callback function
 * @param errback    failure callback function
 */
function addToEleLoop(element_id, sub_name, callback, errback) {
  var vno = ELEMENTS_INFO[element_id]['version'];
  var pageLanguage = ELEMENTS_INFO[element_id]['language'];
  var url = ROOT_URL + '/admin/element/' + element_id
      + '/add_sub/' + vno + '/' + sub_name + '/' + pageLanguage;
  sendAjaxRequest(null, callback, errback, url);
}

function saveReferencedElement(base_element_id, ref_name, new_ref_element_id)
{
  var vno = ELEMENTS_INFO[base_element_id]['version'];
  var url = ROOT_URL + '/admin/element/' + base_element_id
      + '/reference/' + vno + '/' + ref_name;
  sendAjaxRequest(
      new_ref_element_id,
      function (response) {
        processJsonStringResponse(response, true);
      },
      function (response) {
        processResponse(response, false);
      },
      url
  );
}

// Returns all options for a selection of a referenced element, including the
// empty element.
function getOptionsForRefSelector($selector, refName)
{
  var options = [];
  var $parent = $selector.parent().parent();
  $parent.children()
      .filter('.eleref-' + refName)
      .each(function (index) {
    $node = $(this);
    options.push({
      node: $node,
      selected: $node.hasClass('elements__selected'),
      element_id: $node.attr('data-ref-element-id'),
    });
  });
  return options;
}

function getSelectedIndex(options)
{
  for (var idx = 0; idx < options.length; idx++) {
    if (options[idx].selected) {
      return idx;
    }
  }
  return -1;
}

function deleteSubElement(base_element_id, sub_name, sub_index)
{
  var vno = ELEMENTS_INFO[base_element_id]['version'];
  var pageLanguage = ELEMENTS_INFO[base_element_id]['language'];
  var url = ROOT_URL + '/admin/element/' + base_element_id
    + '/remove_sub/' + vno + '/' + sub_name + '/' + sub_index + '/'
    + pageLanguage;
  sendAjaxRequest(
    null,
    function (response) {
      processResponse(response, true);
    },
    function (response) {
      processResponse(response, false);
    },
    url
  );
}

/**
 * sends the actual request and the response to either callback
 * @param payload
 * @param callback
 * @param errback
 * @param url
 * @param method
 */
function sendAjaxRequest(payload, callback, errback, url, method) {
  var type = (typeof method !== 'undefined') ?  method : 'POST';
  $.ajax({
    url: url,
    type: type,
    contentType: 'text/plain',
    dataType: 'text',
    data: payload,
    success: callback,
    error: errback
  });
}
