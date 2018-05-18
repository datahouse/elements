$(function() {
  'use strict';

  // Tree Logic
  renderData();
});

/**
 * get tree data and render tree
 * 
 * @returns promise of ajax request 
 */
function renderData() {
  if ($('.tree').length > 0) {
    return $.get('admin/tree/data', function(data) {
      render($('.tree'), data);
    });
  }
}

/**
 * 
 * @param $element jQuery element where render tree 
 * @param data array
 */
function render($element, data) {
  $element.html(nodeRender(data, 0));

  var ns = $('.tree ol.sortable').nestedSortable({
    forcePlaceholderSize: true,
    handle: '.dragzone img',
    helper: 'clone',
    items: 'li:not(.no-draggable, .dropdown-menu-item)',
    opacity: 0.6,
    placeholder: 'placeholder',
    tabSize: 20,
    tolerance: 'pointer',
    toleranceElement: '.item',
    isTree: true,
    expandOnHover: 1000,
    startCollapsed: true,

    branchClass: 'branch',
    collapsedClass: 'collapsed',
    disableNestingClass: 'no-nesting',
    errorClass: 'dd-error',
    expandedClass: 'expanded',
    hoveringClass: 'dd-hover',
    leafClass: 'leaf',
    disabledClass: 'dd-disable',
    disableParentChange: true,

    isAllowed: function(item, parent) {
      if (!parent) return false; //don't allow top level moving
      return true;
    },

    change: function() {
      // Fires when the item is dragged to a new location. This triggers for each location it is dragged into not just the ending location.

    },

    sort: function(e, b) {
      // Fires when the item is dragged.
    },

    revert: function() {
      // Fires once the object has moved if the new location is invalid.
    },

    relocate: function(e, b) {
      // Only fires once when the item is done being moved to its final
      // location.
      var item = $(b.item[0]);
      var next = item.next();
      var parent = item.parent().closest('li');
      var url = ROOT_URL + '/admin/element/' + item.attr('data-node-id')
        + '/move_child/' + item.attr('data-element-version') + '/'
        + EDIT_LANGUAGE;

      $.ajax({
        'url': url,
        'type': 'POST',
        'dataType': 'json',
        'data': {
          newParentId: parent.attr('data-node-id'),
          insertBefore: next ? next.attr('data-node-id') : null
        }
      })
      .done(function (data, status, response) {
        toastr['success'](data.message);
      })
      .fail( function(xhr, status, error) {
        toastr['error'](xhr.responseJSON.message);
      });
    }
  });

  var $body = $('body');
  $body.on('click', '.disclose', function(e) {
    $(this).closest('li').toggleClass('collapsed').toggleClass('expanded');
  });

  $body.on('click', '.node-edit', function(e) {
    e.preventDefault();
    toggleForm($(this));
  });

  $body.on('click', '.node-add-form button', function() {
    saveNodeTitle($(this), 'new_child');
  });

  $body.on('click', '.node-edit-form button', function() {
    saveNodeTitle($(this), 'rename');
  });

  //Adding empty node
  $body.on('click', '.node-add', function() {
    addEmptyNode($(this));
  });

  $body.on('show.bs.modal', function (e) {
    var nodeId = $(e.relatedTarget).data('node-id');
    $('#delete-modal-yes').attr('data-node-id', nodeId)
  });

  $body.on('click', '#deleteModal button.btn-danger', function () {
    removeNodeById($('#delete-modal-yes').attr('data-node-id'));
    $('#deleteModal').modal('hide');
  });

  $body.on('keyup', '.form-control', function(e){
    if(e.keyCode == 13)
    {
      $(this).next().trigger('click');
    }
  });
}

/**
 * 
 *
 * @param data array render a node
 * @param depth int depth in the tree
 * @param parentReadOnly bool
 * @param isSortableOnly bool
 * @returns jQuery element
 */
function nodeRender(data, depth, parentReadOnly, isSortableOnly) {
  if (parentReadOnly === undefined) parentReadOnly = false;

  var sortable = '';
  if (depth === 0) {
    sortable = 'sortable ui-sortable ';
  }
  var list = $('<ol/>').addClass(sortable + 'branch expanded list-grp');
  var len = data.length - 1;
  if (len === -1) {
    nodeEmpty().appendTo(list);
  }

  $.each(data, function(i, item) {
    var disableNesting = '';
    if (item.disableNesting) {
      disableNesting = ' no-nesting';
    }
    var disableDraggable = '';
    if (item.disableDraggable) {
      disableDraggable = ' no-draggable collapsed';
    }
    var nodesReadOnly = '';
    if (item.nodesReadOnly || parentReadOnly || item.view_only) {
      disableNesting = ' no-nesting';
      disableDraggable = ' no-draggable collapsed';
    }
    var sortableOnly = '';
    if (isSortableOnly) {
      sortableOnly = ' sortable-only';
    }

    var li = $('<li/>')
      .addClass('list-grp-item' + disableNesting + disableDraggable + sortableOnly)
      .attr('id', 'id_' + item.id)
      .attr('data-node-id', item.id)
      .attr('data-element-version', item.version)
      .attr('data-view-only', item.view_only)
      .append(nodeContent(item, parentReadOnly, disableDraggable))
      .appendTo(list);

    if (item.children !== undefined && item.children.length > 0) {
      li.append(
        nodeRender(
          item.children,
          depth + 1,
          item.nodesReadOnly || parentReadOnly,
          item.nodesSortableOnly
        )
      ).addClass('has-children');
    } else {
      li.addClass('leaf');
      nodeEmpty().appendTo(li);
    }

    if (i === len && !parentReadOnly) {
      nodeEmpty().appendTo(list);
    }
  });

  return list;
}

/**
 * Render content of a node
 * 
 * @param item object 
 * @param parentReadOnly bool
 * @param disableDraggable bool
 * @returns jQuery element
 */
function nodeContent(item, parentReadOnly, disableDraggable)
{
  var drag_img = $('<div/>')
    .addClass('dragzone')
    .append($('<img>')
    .attr('src', ROOT_URL + '/assets/elements/images/draggable.png'));
  var another_div = $('<div/>')
    .addClass('col-sm-10')
    .append(nodeDisclose())
    .append(nodeTitle(
        item.label,
        item.link,
        item.disableDraggable || parentReadOnly || disableDraggable,
        item.view_only
    ));
  if (!item.view_only) {
    another_div.append(nodeEditForm());
  }
  var result = $('<div/>')
    .addClass('item clearfix')
    .append($('<span/>').addClass('marker'))
    .append(drag_img)
    .append(another_div);
  if (!item.view_only) {
    result.append(nodeAction(item.id, item.languages));
  }
  return result;
}

/**
 * Render disclose of a node
 * 
 * @returns jQuery element
 */
function nodeDisclose() {
  return $('<div/>')
    .addClass('disclose')
    .append('<i class="fa fa-chevron-right" aria-hidden="true"></i>')
    .append('<i class="fa fa-chevron-down" aria-hidden="true"></i>');
}

/**
 * Render title of a node
 * 
 * @param title string
 * @param link string
 * @param disableDraggable bool
 * @param isViewOnly bool
 * @returns jQuery element
 */
function nodeTitle(title, link, disableDraggable, isViewOnly) {
  var draggable = ' draggable';
  if (disableDraggable) {
    draggable = '';
  }

  var html;
  if (typeof link !== 'undefined') {
    html = '<a href="' + link + '">' + title + '</a>';
    if (!isViewOnly) {
      html += ' <a href><i class="fa fa-pencil node-edit" aria-hidden="true"></i></a>';
    }
  } else {
    html = title;
  }
  return $('<div/>')
    .addClass('title' + draggable)
    .html(html);
}

/**
 * Render title edit form
 * 
 * @returns jQuery element
 */
function nodeEditForm() {
  return $('<div/>')
    .addClass('form-inline node-edit-form hide')
    .append(
      $('<div/>')
        .addClass('form-group')
        .append('<input class="form-control" data-key="title" type="text"/>')
        .append(' <button class="btn btn-primary" type="button">Save</button>')
    );
}

/**
 * Render action bar of a node
 * 
 * @param nodeId hash string 
 * @param languages array
 * @returns jQuery element
 */
function nodeAction(nodeId, languages) {
  var div = $('<div/>')
    .addClass('col-sm-2 action-buttons');

  $.each(languages, function(i, lang) {
    var ul = $('<ul/>')
      .addClass('dropdown-menu')
      .attr('aria-labelledby', 'node_' + nodeId);

    switch (lang.status) {
      case 'published':
        ul.append('<li class="dropdown-menu-item"><a href="view.html">edit</a></li>')
          .append('<li class="dropdown-menu-item"><a href="#" aria-hidden="true" data-toggle="modal" data-target="#publishModal">options</a></li>')
          .append('<li class="dropdown-menu-item"><a href="#">disable</a></li>');
        break;
      case 'edited':
        ul.append('<li class="dropdown-menu-item"><a href="view.html">edit</a></li>')
          .append('<li class="dropdown-menu-item"><a href="#">show diff</a></li>')
          .append('<li class="dropdown-menu-item"><a href="view.html">show original</a></li>')
          .append('<li class="dropdown-menu-item"><a href="#" aria-hidden="true" data-toggle="modal" data-target="#publishModal">publish</a></li>')
          .append('<li class="dropdown-menu-item"><a href="#">revert</a></li>');
        break;
      case 'disabled':
        ul.append('<li class="dropdown-menu-item"><a href="view.html">edit</a></li>')
          .append('<li class="dropdown-menu-item"><a href="#" aria-hidden="true" data-toggle="modal" data-target="#publishModal">publish</a></li>');
        break;
    }

    div.append(
      $('<div/>')
        .addClass('dropdown')
        .append(
          '<a class="node-' + lang.status + '" id="node_' + nodeId + '" data-target="#" href="#" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">' +
          '<i class="fa fa-cloud" aria-hidden="true"></i><span class="caret"></span><span class="language-name">' + lang.short + '</span>' +
          '</a>'
        )
        .append(ul)
    );
  });

  if (GLOBAL_OPTIONS['ENABLE_ELEMENT_ADD_REMOVE']) {
    div.append('<i class="fa fa-plus node-add" aria-hidden="true"></i>');
    div.append('<i class="fa fa-times node-delete" data-toggle="modal" data-target="#deleteModal" data-node-id="' + nodeId + '" aria-hidden="true"></i>');
  }

  return div;
}

/**
 * Render empty element with an add new node form
 * 
 * @returns jQuery element
 */
function nodeEmpty() {
  return $('<li/>')
    .addClass('list-grp-item no-draggable no-nesting list-new-page')
    .addClass('hide')
    .append(
      $('<div/>')
        .addClass('form-inline node-add-form row')
        .append(
          $('<div/>')
            .addClass('form-group')
            .append('<input class="form-control" type="text"/>')
            .append(' <button class="btn btn-primary" type="button">Save</button>')
        )
    );
}

/**
 * Transform html tree to json
 * FIXME Unfinished, untested experimental function
 * 
 * @param $element of the tree
 * @returns array 
 */
function toJson($element) {
  var items = [];

  $element.children().each(function() {
    var $sub = $(this).find('ul');
    var item = { text: $(this).children('div:first-child').html() };
    if ($sub.length !== 0) {
      item.nodes = toJson($sub);
    }
    items.push(item);
  });

  return items;
}

/**
 * Updates the node edit form
 * 
 * @param $element jQuery element
 * @param request_type switches creation / editing (new_child / rename)
 */
function saveNodeTitle($element, request_type) {
  if (request_type == undefined) {
    return false;
  }
  var $this = $element;
  var $node = $this.closest('li');
  var $prev = $this.prev();
  var $parent = $node.parent().closest('li');
  $this.parent().children('.error').remove();
  if ($prev.val().length > 0) {
    var lang = EDIT_LANGUAGE;
    var new_element_name = $prev.val();
    var element_id = $parent.attr('data-node-id');
    if (request_type == 'rename') {
      element_id = $node.attr('data-node-id');
    }
    var url = 'admin/element/' + element_id + '/' + request_type + '/';
    if (request_type == 'rename') {
      url += $node.attr('data-element-version') + '/';
    }
    url += lang;

    $.ajax({
      'url': url,
      'type': 'POST',
      'dataType': 'json',
      'data': {body: new_element_name},
      'success': function(data) {
        if (request_type == 'new_child') {
          toggleForm($this);
          savedNode($node, 'success', data.message);
          $prev.val('');
          $node.addClass('hide');
          var $newNode = insertNode($node, data.changes);
          nodeEmpty().appendTo($newNode);
          if (!$parent.hasClass('has-children') && $parent.hasClass('leaf')) {
            $parent.removeClass('leaf');
            $parent.addClass('has-children');
          }
        } else {
          toggleForm($this, true);
          //set new version, if one was added
          if (data.changes && data.changes.version) {
            $node.attr('data-element-version', data.changes.version);
          }
          savedNode($node, 'success', data.message);
        }
      },
      'error': function (xhr, status, error) {
        savedNode($node, 'error', xhr.responseText);
      },
      'fail': function (xhr, status, error) {
        savedNode($node, 'error', xhr.responseText);
      }
    });
  } else {
    savedNode($node, 'error', 'Please enter a name');
  }
}

/**
 * @param $node
 * @param $changes
 * @returns {*|jQuery}
 */
function insertNode($node, $changes) {
  return $('<li/>')
    .addClass('list-grp-item leaf')
    .attr('id', 'id_' + $changes.element_id)
    .attr('data-node-id', $changes.element_id)
    .attr('data-element-version', $changes.vno)
    .append(nodeContent($changes, '', ''))
    .insertBefore($node);
}

/**
 * Show useful feedback of a response to user with a toastr
 * 
 * @param $node jQuery element
 * @param type string
 * @param text string
 */
function savedNode($node, type, text) {
  $node.addClass('saved-' + type);
  toastr[type](text);
  setTimeout(function() {
    $node.removeClass('saved-' + type);
  }, 5000);
}

/**
 * Toggle node edit form 
 * 
 * @param $element jQuery element
 * @param rename indication whether element was renamed
 */
function toggleForm($element, rename) {
  if (typeof rename === 'undefined') rename = false;
  var $li = $element.closest('li');
  var $title = $li.find('.title a').first();
  var $form = $li.find('.node-edit-form').first();
  $title.parent().toggleClass('hide');
  $form.toggleClass('hide');
  if (!rename) {
    // brings cursor at the end of text
    $form.find('.form-control').focus().val('').val($title.html());
  } else {
    //set the link text
    $title.text($element.prev().val());
  }
}

/**
 * @param $plsBtn
 */
function addEmptyNode($plsBtn) {
  var $element = $plsBtn.closest('li'); //nodeEmpty
  var $list = $element.children('ol');
  var $formNew;
  if ($list != undefined && $list.length > 0) {
    $formNew = $list.children('li.list-new-page');
    $formNew.removeClass('hide');
  } else {
    //TODO: Have a box for leaf nodes too
    $formNew = $element.children('li.list-new-page');
    $formNew.removeClass('hide');
  }
  $formNew.find('.form-control').focus();
}

/**
 *
 * @param id of node to delete
 */
function removeNodeById(id) {
  var $element = $('#id_' + id);
  var element_id = $element.attr('data-node-id');
  var vno = $element.attr('data-element-version');
  var url = 'admin/element/' + element_id + '/remove/' + vno;

  $.ajax({
    'url': url,
    'type': 'POST',
    'dataType': 'json',
    'data': {body : element_id},
    'success': function(data) {
      toastr.success(data.message);
      $element.remove();
    },
    'error': function(data) {
      toastr.error(data.message);
    }
  });
}
