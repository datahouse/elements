$(function() {
  'use strict';
    // toastr config
    toastr.options.positionClass = 'toast-bottom-full-width';
    toastr.options.timeOut = 3000;

    // froala editor config
    if (typeof ELEMENTS_INFO !== 'undefined') {
      $.each(ELEMENTS_INFO, function (element_id, ele_info) {
        $.each(ele_info.fields, function (field_name, field_def) {
          var name;
          if (ele_info.type == 'main_page') {
            name = '.__ele_field-' + field_name;
          } else if (ele_info.type == 'referenced') {
            if ('ref_index' in ele_info) {
              name = '.__ele_field-' + ele_info.ref_name + '-' + ele_info.ref_index
                  + '-' + field_name;
            } else {
              name = '.__ele_field-' + ele_info.ref_name + '-' + field_name;
            }
          } else {
            console.log("error: unknown element type in ELEMENTS_INFO");
          }
          var $domElement = $(name);
          $domElement.froalaEditor(
              getFroalaOptions(element_id, field_name, field_def)
          );
          addFroalaCallbacks($domElement);
        });
      });
    }

    // Initialize Froala callbacks on a given DOM element.
    function addFroalaCallbacks($domElement) {
      $domElement
        .on ('froalaEditor.save.before', function (e, editor) {
          //update froala editor saveURL
          editor.opts.saveURL = getFroalaSaveURL(
              editor.opts.element_id,
              editor.opts.field_name
          );
          editor.opts.imageUploadURL = ROOT_URL + '/admin/blob-upload/image';
          editor.opts.imageMaxSize = FILE_UPLOAD_SIZE_LIMIT;
          editor.opts.imageAllowedTypes = ['jpeg', 'jpg', 'png', 'gif'];
        })
        .on('froalaEditor.file.beforeUpload', function (e, editor, files) {
          editor.opts.fileUploadURL = ROOT_URL + '/admin/blob-upload/document';
          editor.opts.fileUploadMaxSize = FILE_UPLOAD_SIZE_LIMIT;
        })
        .on('froalaEditor.save.after', function (e, editor, response) {
          processResponse(response, true);
          var changes = response.changes;
          if (changes != undefined) {
            if (changes.state != undefined) {
              $('#pageState').html(changes.state);
              PAGE_ELEMENT_STATE = changes.state;
            }
            if (changes.version != undefined) {
              ELEMENTS_INFO[editor.opts.element_id]['version'] = changes.version;
            }
          }
        })
        .on('froalaEditor.save.error', function (e, editor, error, response) {
          processResponse(response, false);
        })

        // Froala provides an 'uploaded' handler as well, but it does not allow
        // changing the result returned from the server. Therefore it's not of
        // much use for us.

        // The messages here aren't very useful, i.e. "something went wrong
        // during saving the image". The Froala documentation specifies some
        // error codes, which we might interpret.
        .on('froalaEditor.image.error', function (e, editor, error) {
          toastr.error(error.message);
        })
        .on('froalaEditor.image.replaced', function (e, editor, $img, response) {
          toastr.success(JSON.parse(response).message);
        })
        .on('froalaEditor.file.inserted', function (e, editor, $file, response) {
          toastr.success(JSON.parse(response).message);
        })
        .on('froalaEditor.blur', function (e, editor) {
          //this is triggered when the editor loses focus; save here now
          editor.save.save();
          // do not allow normal saving after this
        })
      ;
    }

    // Assemble a Froala configuration to be passed to the Froala initializer.
    function getFroalaOptions(element_id, field_name, field_def) {
      var options = {};
      if ("froalaConfig" in field_def) {
        options = field_def["froalaConfig"];
      } else {
        // if there was not froalaConfig given, then default should be empty,
        // not full froala options
        options.toolbarButtons = [];
      }

      var pageAdminUrl = getFroalaSaveURL(element_id, field_name);

      //options that cannot be overwritten by a specific configuration
      options.key = 'LDIE1QCYRWa2GPIb1d1H1==';
      options.saveURL = pageAdminUrl;
      // FIXME: merge with the above imageUploadURL setters
      options.imageUploadURL = ROOT_URL + '/admin/blob-upload/images';
      options.fileUploadURL = ROOT_URL + '/admin/blob-upload/documents';
      options.toolbarInline = true;
      options.initOnClick = true;
      options.toolbarVisibleWithoutSelection = true;
      options.saveParams = { edit_language : EDIT_LANGUAGE };
      options.element_id = element_id;
      options.field_name = field_name;
      options.linkAutoPrefix = '';

      if (options.toolbarButtons !== undefined) {
        options.toolbarButtonsMD = options.toolbarButtons;
        options.toolbarButtonsSM = options.toolbarButtons;
        options.toolbarButtonsXS = options.toolbarButtons;
      } else {
        options.toolbarButtonsMD = [];
        options.toolbarButtonsSM = [];
        options.toolbarButtonsXS = [];
      }

      // Allow projects to override this saveInterval
      if (!('saveInterval' in options)) {
        options['saveInterval'] = 2500;
      }

      // set default enter option to P
      if (options.enter == undefined) {
        options.enter = $.FroalaEditor.ENTER_P;
      }

      return options;
    }
  
    function getFroalaSaveURL(element_id, field_name) {
      return ROOT_URL + '/admin/element/' + element_id +
          '/field/' + ELEMENTS_INFO[element_id]['version'] +
          '/' + field_name + '/' + ELEMENTS_INFO[element_id]['language'];
    }
  });
