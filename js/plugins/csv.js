(function($) {

  'use strict';

  $.extend($.FroalaEditor.POPUP_TEMPLATES, {
    'csv.insert': '[_BUTTONS_][_UPLOAD_LAYER_][_PLOT_LAYER_][_PROGRESS_BAR_]'
  });

  // Extend defaults.
  $.extend($.FroalaEditor.DEFAULTS, {
    csvUploadParam: 'csv',
    csvUploadParams: {},
    csvUploadMethod: 'POST',
    csvMaxSize: 10 * 1024 * 1024,
    csvAllowedTypes: ['csv'],
    csvInsertButtons: ['csvBack', '|', 'csvTable', 'csvPlot'],
    csvUseSelectedText: false
  });


  $.FroalaEditor.PLUGINS.csv = function(editor) {
    var BAD_LINK = 1;
    var MISSING_LINK = 2;
    var ERROR_DURING_UPLOAD = 3;
    var BAD_RESPONSE = 4;
    var MAX_SIZE_EXCEEDED = 5;
    var BAD_CSV_TYPE = 6;
    var NO_CORS_IE = 7;
    var BAD_CSV = 8;

    var error_messages = {};
    error_messages[BAD_LINK] = 'Csv cannot be loaded from the passed link.';
    error_messages[MISSING_LINK] = 'No link in upload response.';
    error_messages[ERROR_DURING_UPLOAD] = 'Error during csv upload.';
    error_messages[BAD_RESPONSE] = 'Parsing response failed.';
    error_messages[MAX_SIZE_EXCEEDED] = 'Csv is too large.';
    error_messages[BAD_CSV_TYPE] = 'Csv file type is invalid.';
    error_messages[NO_CORS_IE] = 'Files can be uploaded only to same domain in IE 8 and IE 9.';
    error_messages[BAD_CSV] = 'Unable to read CSV';

    var csvState;

    function showInsertPopup() {
      var $btn = editor.$tb.find('.fr-command[data-cmd="insertCsv"]');

      var $popup = editor.popups.get('csv.insert');
      if (!$popup) $popup = _initInsertPopup();

      hideProgressBar();
      if (!$popup.hasClass('fr-active')) {
        editor.popups.refresh('csv.insert');
        editor.popups.setContainer('csv.insert', editor.$tb);

        var left = $btn.offset().left + $btn.outerWidth() / 2;
        var top = $btn.offset().top + (editor.opts.toolbarBottom ? 0 : $btn.outerHeight());
        editor.popups.show('csv.insert', left, top, $btn.outerHeight());
      }
    }

    /**
     * Show progress bar.
     */
    function showProgressBar() {
      var $popup = editor.popups.get('csv.insert');
      if ($popup) {
        $popup.find('.fr-layer.fr-active').removeClass('fr-active').addClass('fr-pactive');
        $popup.find('.fr-csv-progress-bar-layer').addClass('fr-active');
        $popup.find('.fr-buttons').hide();

        _setProgressMessage('Uploading', 0);
      }
    }

    /**
     * Hide progress bar.
     */
    function hideProgressBar(dismiss) {
      var $popup = editor.popups.get('csv.insert');

      if ($popup) {
        $popup.find('.fr-layer.fr-pactive').addClass('fr-active').removeClass('fr-pactive');
        $popup.find('.fr-csv-progress-bar-layer').removeClass('fr-active');
        $popup.find('.fr-buttons').show();

        if (dismiss) {
          editor.popups.show('csv.insert', null, null);
        }
      }
    }

    /**
     * Set a progress message.
     */
    function _setProgressMessage(message, progress) {
      var $popup = editor.popups.get('csv.insert');

      if ($popup) {
        var $layer = $popup.find('.fr-csv-progress-bar-layer');
        $layer.find('h3').text(message + (progress ? ' ' + progress + '%' : ''));

        $layer.removeClass('fr-error');

        if (progress) {
          $layer.find('div').removeClass('fr-indeterminate');
          $layer.find('div > span').css('width', progress + '%');
        }
        else {
          $layer.find('div').addClass('fr-indeterminate');
        }
      }
    }

    /**
     * Show error message to the user.
     */
    function _showErrorMessage(message) {
      var $popup = editor.popups.get('csv.insert');
      var $layer = $popup.find('.fr-csv-progress-bar-layer');
      $layer.addClass('fr-error');
      $layer.find('h3').text(message);
    }

    /**
     * Insert the uploaded csv.
     */
    function insert(link, text, response) {
      editor.edit.on();

      // Focus in the editor.
      editor.events.focus(true);
      editor.selection.restore();

      // Insert the link.
      editor.html.insert('<a href="' + link + '" id="fr-inserted-csv" class="fr-csv">' + (text || editor.selection.text()) + '</a>');

      // Get the csv.
      var $csv = editor.$el.find('#fr-inserted-csv');
      $csv.removeAttr('id');

      editor.popups.hide('csv.insert');

      editor.undo.saveStep();

      editor.events.trigger('csv.inserted', [$csv, response]);
    }

    /**
     * Parse csv response.
     */
    function _parseResponse(response) {
      try {
        if (editor.events.trigger('csv.uploaded', [response], true) === false) {
          editor.edit.on();
          return false;
        }

        var resp = $.parseJSON(response);
        if (resp.link) {
          return resp;
        } else {
          // No link in upload request.
          _throwError(MISSING_LINK, response);
          return false;
        }
      } catch (ex) {

        // Bad response.
        _throwError(BAD_RESPONSE, response);
        return false;
      }
    }

    /**
     * Throw an csv error.
     */
    function _throwError(code, response) {
      editor.edit.on();
      _showErrorMessage(editor.language.translate('Something went wrong. Please try again.'));

      editor.events.trigger('csv.error', [{
        code: code,
        message: error_messages[code]
      }, response]);
    }

    function upload(files) {
      // Check if we should cancel the csv upload.
      if (editor.events.trigger('csv.beforeUpload', [files]) === false) {
        return false;
      }

      // Make sure we have what to upload.
      if (typeof files != 'undefined' && files.length > 0) {
        var file = files[0];

        // Check csv max size.
        if (file.size > editor.opts.csvMaxSize) {
          _throwError(MAX_SIZE_EXCEEDED);
          return false;
        }

        if (getState() === 'table') {
          _printTable(file);
        } else if (csvState === 'plot') {
          _printPlot(file);
        }
      }
    }

    function _printTable(file) {
      var reader = new FileReader();
      reader.readAsText(file);
      reader.onload = function(event){
        var csv = event.target.result;
        var data = $.csv.toArrays(csv);
        var html = '<table class="table">';
        html += _tableHead(data.shift());
        html += _tableBody(data);
        html += '</table>';

        editor.html.insert(html);
        editor.selection.restore();
      };
      reader.onerror = function() { _throwError(BAD_CSV, file.fileName);  };
    }

    function _printPlot(file) {
      var margin = {top: 20, right: 20, bottom: 30, left: 40},
        width = 960 - margin.left - margin.right,
        height = 500 - margin.top - margin.bottom;

        var x = d3.scale.linear()
          .range([0, width]);

        var y = d3.scale.linear()
          .range([height, 0]);

        //var color = d3.scale.category10();

        var xAxis = d3.svg.axis()
          .scale(x)
          .orient("bottom");

        var yAxis = d3.svg.axis()
          .scale(y)
          .orient("left");

        editor.html.insert('<div id="insert_svg"></div>');
        var svg = d3.select("#insert_svg").append("svg")
          .attr("width", width + margin.left + margin.right)
          .attr("height", height + margin.top + margin.bottom)
          .append("g")
          .attr("transform", "translate(" + margin.left + "," + margin.top + ")");

        var reader = new FileReader();
        reader.readAsText(file);
        reader.onload = function(event){
          var data = d3.csv.parse(event.target.result);
          data.forEach(function(d) {
            d.xPosition = +d.xPosition;
            d.yPosition = +d.yPosition;
          });

          x.domain(d3.extent(data, function(d) { return d.xPosition; })).nice();
          y.domain(d3.extent(data, function(d) { return d.yPosition; })).nice();

          svg.append("g")
              .attr("class", "x axis")
              .attr("transform", "translate(0," + height + ")")
              .call(xAxis)
            .append("text")
              .attr("class", "label")
              .attr("x", width / 2)
              .attr("y", 20)
              .style("text-anchor", "middle")
              .text("Veränderung letzter Monat");

          svg.append("g")
              .attr("class", "x axis")
              .attr("transform", "translate(0," + height + ")")
              .call(xAxis)
            .append("text")
              .attr("class", "label")
              .attr("x", width)
              .attr("y", 20)
              .style("text-anchor", "end")
              .text("++");

          svg.append("g")
              .attr("class", "x axis")
              .attr("transform", "translate(0," + height + ")")
              .call(xAxis)
            .append("text")
              .attr("class", "label")
              .attr("x", 0)
              .attr("y", 20)
              .style("text-anchor", "start")
              .text("--");

          svg.append("g")
              .attr("class", "y axis")
              .call(yAxis)
            .append("text")
              .attr("class", "label")
              .attr("transform", "rotate(-90)")
              .attr("y", -10)
              .attr("x", height / 2 * -1)
              .style("text-anchor", "middle")
              .text("Aktuelle Lage");

          svg.append("g")
              .attr("class", "y axis")
              .call(yAxis)
            .append("text")
              .attr("transform", "rotate(-90)")
              .attr("y", -10)
              .attr("x", 0)
              .style("text-anchor", "end")
              .text("++");

          svg.append("g")
              .attr("class", "y axis")
              .call(yAxis)
            .append("text")
              .attr("transform", "rotate(-90)")
              .attr("y", -10)
              .attr("x", height * -1)
              .style("text-anchor", "start")
              .text("--");

          svg.append("line")
              .attr("x1", 0)
              .attr("y1", 0)
              .attr("x2", width)
              .attr("y2", 0)
              .style("stroke-dasharray", ("3, 3"))
              .style("stroke-opacity", 0.6)
              .style("stroke", "black")
              .attr("stroke-width", 1);

          svg.append("line")
              .attr("x1", width)
              .attr("y1", 0)
              .attr("x2", width)
              .attr("y2", height)
              .style("stroke-dasharray", ("3, 3"))
              .style("stroke-opacity", 0.6)
              .style("stroke", "black")
              .attr("stroke-width", 1);

          svg.append("line")
              .attr("x1", 0)
              .attr("y1", function(d) { return height / 2; })
              .attr("x2", width)
              .attr("y2", function(d) { return height / 2; })
              .style("stroke-dasharray", ("3, 3"))
              .style("stroke-opacity", 0.6)
              .style("stroke", "black")
              .attr("stroke-width", 1);

          svg.append("line")
              .attr("x1", function(d) { return width / 2; })
              .attr("y1", 0)
              .attr("x2", function(d) { return width / 2; })
              .attr("y2", height)
              .style("stroke-dasharray", ("3, 3"))
              .style("stroke-opacity", 0.6)
              .style("stroke", "black")
              .attr("stroke-width", 1);

          svg.selectAll(".dot")
              .data(data)
            .enter().append("circle")
              .attr("class", "dot")
              .attr("r", 16)
              .attr("cx", function(d) { return x(d.xPosition); })
              .attr("cy", function(d) { return y(d.yPosition); })
              .style("fill", function(d) {
                switch(d.type) {
                  case "high": return "#ae2746";
                  case "low": return "#4d4d4d";
                  case "normal":
                  default: return "#d0d0d0";
                }
              });

          svg.selectAll(".text")
              .data(data)
            .enter().append("text")
              .attr("class", "text")
              .attr("x", function(d) { return x(d.xPosition) - 11; })
              .attr("y", function(d) { return y(d.yPosition) + 5; })
              .text(function(d) { return d.label; })
              .style("fill", function(d) {
                switch(d.type) {
                  case "high":
                  case "low": return "#ffffff";
                  case "normal":
                  default: return "#000000";
                }
              });

          svg.selectAll(".tick").attr("visibility","hidden");

/*
          var legend = svg.selectAll(".legend")
              .data(color.domain())
            .enter().append("g")
              .attr("class", "legend")
              .attr("transform", function(d, i) { return "translate(0," + i * 20 + ")"; });


          legend.append("rect")
              .attr("x", width - 18)
              .attr("width", 18)
              .attr("height", 18)
              .style("fill", color);

          legend.append("text")
              .attr("x", width - 24)
              .attr("y", 9)
              .attr("dy", ".35em")
              .style("text-anchor", "end")
              .text(function(d) { return d; });
*/

          $("#insert_svg svg").attr("version", 1.1).attr("xmlns", "http://www.w3.org/2000/svg");
          $('#insert_svg').html(function(index, oldhtml) {
              var enc = encodeURI(oldhtml.replace('<br>',''));
              return '<img src=\'data:image/svg+xml,' + enc + '\' alt="Grafik" />';
          });
          $('#insert_svg').attr('id', '');

          editor.undo.saveStep();
          editor.selection.restore();
        };
        reader.onerror = function() { _throwError(BAD_CSV, file.fileName);  };
    }

    function _tableHead(row) {
      var html = '<thead>\r\n<tr>\r\n';
      html += _tableRow(row, 'th');
      html += '</tr>\r\n</thead>\r\n';

      return html;
    }

    function _tableBody(data) {
      var html = '<tbody>\r\n';
      for (var row in data) {
        html += '<tr>\r\n';
        html += _tableRow(data[row], 'td');
        html += '</tr>\r\n';
      }
      html += '</tbody>\r\n';

      return html;
    }

    function _tableRow(row, type) {
      var html = '';
      for (var item in row) {
        html += '<' + type + '>' + row[item] + '</' + type + '>' + '\r\n';
      }

      return html;
    }

    function _bindInsertEvents($popup) {
      // Drag over the dropable area.
      $popup.on('dragover dragenter', '.fr-csv-upload-layer', function() {
        $(this).addClass('fr-drop');
        return false;
      });

      // Drag end.
      $popup.on('dragleave dragend', '.fr-csv-upload-layer', function() {
        $(this).removeClass('fr-drop');
        return false;
      });

      // Drop.
      $popup.on('drop', '.fr-csv-upload-layer', function(e) {
        e.preventDefault();
        e.stopPropagation();

        $(this).removeClass('fr-drop');

        var dt = e.originalEvent.dataTransfer;
        if (dt && dt.files) {
          upload(dt.files);
        }
      });

      $popup.on('change', '.fr-csv-upload-layer input[type="file"]', function() {
        if (this.files) {
          upload(this.files);
        }

        // Else IE 9 case.

        // Chrome fix.
        $(this).val('');
      });
    }

    function _hideInsertPopup() {
      hideProgressBar();
    }

    function _initInsertPopup() {
      var csv_buttons = '';
      csv_buttons = '<div class="fr-buttons">' + editor.button.buildList(editor.opts.csvInsertButtons) + '</div>';

      setState('table');
      var upload_layer = '<div class="fr-csv-upload-layer fr-csv-upload-table-layer fr-active fr-layer" id="fr-csv-upload-layer-' + editor.id + '"><strong>' + editor.language.translate('Drop csv for Table') + '</strong><br>(' + editor.language.translate('or click') + ')<div class="fr-form"><input type="file" name="' + editor.opts.csvUploadParam + '" accept="/*" tabIndex="-1"></div></div>';
      var plot_layer = '<div class="fr-csv-upload-layer fr-csv-upload-plot-layer fr-layer" id="fr-csv-upload-layer-' + editor.id + '"><strong>' + editor.language.translate('Drop csv for Plot') + '</strong><br>(' + editor.language.translate('or click') + ')<div class="fr-form"><input type="file" name="' + editor.opts.csvUploadParam + '" accept="/*" tabIndex="-1"></div></div>';

      // Progress bar.
      var progress_bar_layer = '<div class="fr-csv-progress-bar-layer fr-layer"><h3 class="fr-message">Uploading</h3><div class="fr-loader"><span class="fr-progress"></span></div><div class="fr-action-buttons"><button type="button" class="fr-command" data-cmd="csvDismissError" tabIndex="2">OK</button></div></div>';

      var template = {
        buttons: csv_buttons,
        upload_layer: upload_layer,
        plot_layer: plot_layer,
        progress_bar: progress_bar_layer
      };

      // Set the template in the popup.
      var $popup = editor.popups.create('csv.insert', template);

      editor.popups.onHide('csv.insert', _hideInsertPopup);
      _bindInsertEvents($popup);

      return $popup;
    }

    function _onRemove(link) {
      if ($(link).hasClass('fr-csv')) {
        return editor.events.trigger('csv.unlink', [link]);
      }
    }

    function _initEvents() {
      var preventDefault = function(e) {
        e.preventDefault();
      };

      editor.events.on('dragenter', preventDefault);
      editor.events.on('dragover', preventDefault);

      // Drop inside the editor.
      editor.events.on('drop', function(e) {
        editor.popups.hideAll();

        // Check if we are dropping files.
        var dt = e.originalEvent.dataTransfer;
        if (dt && dt.files && dt.files.length) {
          var csv = dt.files[0];
          if (csv && typeof csv.type != 'undefined') {
            // Dropped csv is an csv that we allow.
            if (editor.opts.csvAllowedTypes.indexOf(csv.type) >= 0 || editor.opts.csvAllowedTypes.indexOf('*') >= 0) {
              editor.markers.remove();
              editor.markers.insertAtPoint(e.originalEvent);
              editor.$el.find('.fr-marker').replaceWith($.FroalaEditor.MARKERS);

              // Hide popups.
              editor.popups.hideAll();

              // Show the csv insert popup.
              var $popup = editor.popups.get('csv.insert');
              if (!$popup) $popup = _initInsertPopup();
              editor.popups.setContainer('csv.insert', $(editor.opts.scrollableContainer));
              editor.popups.show('csv.insert', e.originalEvent.pageX, e.originalEvent.pageY);
              showProgressBar();

              // Upload files.
              upload(dt.files);

              // Cancel anything else.
              e.preventDefault();
              e.stopPropagation();
            }
          }
        }
      });
    }

    function back() {
      editor.events.disableBlur();
      editor.selection.restore();
      editor.events.enableBlur();

      editor.popups.hide('csv.insert');
      editor.toolbar.showInline();
    }

    function setState(state) {
      csvState = state;
    }

    function getState() {
      return csvState;
    }

    /**
     * Show the csv upload layer.
     */
    function showLayer(name) {
      var $popup = editor.popups.get('csv.insert');

      // Show the new layer.
      $popup.find('.fr-layer').removeClass('fr-active');
      $popup.find('.fr-csv-upload-' + name + '-layer').addClass('fr-active');
    }

    /**
     * Refresh the upload csv table button.
     */
    function refreshUploadTableButton($btn) {
      var $popup = editor.popups.get('csv.insert');
      if ($popup.find('.fr-csv-upload-table-layer').hasClass('fr-active')) {
        $btn.addClass('fr-active');
      }
    }

    /**
     * Refresh the upload csv plot button.
     */
    function refreshUploadPlotButton($btn) {
      var $popup = editor.popups.get('csv.insert');
      if ($popup.find('.fr-csv-upload-plot-layer').hasClass('fr-active')) {
        $btn.addClass('fr-active');
      }
    }

    /*
     * Initialize.
     */
    function _init() {
      _initEvents();

      editor.events.on('link.beforeRemove', _onRemove);
    }

    return {
      _init: _init,
      showInsertPopup: showInsertPopup,
      upload: upload,
      insert: insert,
      showLayer: showLayer,
      refreshUploadTableButton: refreshUploadTableButton,
      refreshUploadPlotButton: refreshUploadPlotButton,
      back: back,
      hideProgressBar: hideProgressBar,
      setState: setState,
      getState: getState
    };
  };

  // Insert csv button.
  $.FroalaEditor.DefineIcon('insertCsv', { NAME: 'database' });
  $.FroalaEditor.RegisterCommand('insertCsv', {
    title: 'Upload Csv',
    undo: false,
    focus: true,
    refershAfterCallback: false,
    popup: true,
    callback: function() {
      if (!this.popups.isVisible('csv.insert')) {
        this.csv.showInsertPopup();
      }
      else {
        if (this.$el.find('.fr-marker')) {
          this.events.disableBlur();
          this.selection.restore();
        }
        this.popups.hide('csv.insert');
      }
    },
    plugin: 'csv'
  });

  $.FroalaEditor.DefineIcon('csvBack', { NAME: 'arrow-left' });
  $.FroalaEditor.RegisterCommand('csvBack', {
    title: 'Back',
    undo: false,
    focus: false,
    back: true,
    refreshAfterCallback: false,
    callback: function() {
      this.csv.back();
    },
    refresh: function($btn) {
      if (!this.opts.toolbarInline) {
        $btn.addClass('fr-hidden');
        $btn.next('.fr-separator').addClass('fr-hidden');
      }
      else {
        $btn.removeClass('fr-hidden');
        $btn.next('.fr-separator').removeClass('fr-hidden');
      }
    }
  });

  $.FroalaEditor.DefineIcon('csvTable', { NAME: 'table' });
  $.FroalaEditor.RegisterCommand('csvTable', {
    title: 'As Table',
    undo: false,
    focus: false,
    callback: function() {
      this.csv.showLayer('table');
      this.csv.setState('table');
    },
    refresh: function($btn) {
      this.csv.refreshUploadTableButton($btn);
    }
  });

  $.FroalaEditor.DefineIcon('csvPlot', { NAME: 'pie-chart' });
  $.FroalaEditor.RegisterCommand('csvPlot', {
    title: 'As Plot',
    undo: false,
    focus: false,
    callback: function() {
      this.csv.showLayer('plot');
      this.csv.setState('plot');
    },
    refresh: function($btn) {
      this.csv.refreshUploadPlotButton($btn);
    }
  });

  $.FroalaEditor.RegisterCommand('csvDismissError', {
    title: 'OK',
    callback: function() {
      this.csv.hideProgressBar(true);
    }
  });

})(jQuery);
