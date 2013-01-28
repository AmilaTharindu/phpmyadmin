/* vim: set expandtab sw=4 ts=4 sts=4: */

var chart_xaxis_idx = -1;
var chart_series;
var chart_series_index = -1;
var temp_chart_title;
var currentChart = null;
var chart_data = null;
var nonJqplotSettings = null;
var currentSettings = null;


$(document).ready(function() {
    chart_series = 'columns';
    chart_xaxis_idx = $('select[name="chartXAxis"]').attr('value');

    // from jQuery UI
    $('#resizer').resizable({
        minHeight:240,
        minWidth:300
    });

    $('#resizer').bind('resizestop', function(event,ui) {
        // make room so that the handle will still appear
        $('#querychart').height($('#resizer').height() * 0.96);
        $('#querychart').width($('#resizer').width() * 0.96);
        currentChart.replot( {resetAxes: true})
    });

    nonJqplotSettings = {
        chart: {
            type: 'line',
            width: $('#resizer').width() - 20,
            height: $('#resizer').height() - 20
        }
    };

    currentSettings = {
        grid: {
            drawBorder: false,
            shadow: false,
            background: 'rgba(0,0,0,0)'
        },
        axes: {
            xaxis: {
                label: $('input[name="xaxis_label"]').val(),
                labelRenderer: $.jqplot.CanvasAxisLabelRenderer
            },
            yaxis: {
                label: $('input[name="yaxis_label"]').val(),
                labelRenderer: $.jqplot.CanvasAxisLabelRenderer
            }
        },
        title: {
            text: $('input[name="chartTitle"]').attr('value'),
            escapeHtml: true
            //margin:20
        },
        legend: {
            show: true,
            placement: 'outsideGrid',
            location: 'se'
        }
    };

    $('#querychart').html('');

    $('input[name="chartType"]').click(function() {
        nonJqplotSettings.chart.type = $(this).attr('value');

        drawChart();

        if ($(this).attr('value') == 'bar' || $(this).attr('value') == 'column')
            $('span.barStacked').show();
        else
            $('span.barStacked').hide();
    });

    $('input[name="barStacked"]').click(function() {
        if(this.checked) {
            $.extend(true,currentSettings,{ stackSeries: true });
        } else {
            $.extend(true,currentSettings,{ stackSeries: false });
        }
        drawChart();
    });

    $('input[name="chartTitle"]').focus(function() {
        temp_chart_title = $(this).val();
    });
    $('input[name="chartTitle"]').keyup(function() {
        var title = $(this).attr('value');
        if (title.length == 0) {
            title = ' ';
        }
        currentSettings.title = $('input[name="chartTitle"]').val();
        drawChart();
    });
    $('input[name="chartTitle"]').blur(function() {
        if ($(this).val() != temp_chart_title) {
            drawChart();
        }
    });

    $('select[name="chartXAxis"]').change(function() {
        chart_xaxis_idx = this.value;
        drawChart();
    });
    $('select[name="chartSeries"]').change(function() {
        chart_series = this.value;
        chart_series_index = this.selectedIndex;
        drawChart();
    });

    /* Sucks, we cannot just set axis labels, we have to redraw the chart completely */
    $('input[name="xaxis_label"]').keyup(function() {
        currentSettings.axes.xaxis.label = $(this).attr('value');
        drawChart();
    });
    $('input[name="yaxis_label"]').keyup(function() {
        currentSettings.axes.yaxis.label = $(this).attr('value');
        drawChart();
    });

});

/**
 * Ajax Event handler for 'Go' button click
 *
 */
$("#tblchartform").live('submit', function(event) {

    if(!checkFormElementInRange(this, 'session_max_rows', PMA_messages['strNotValidRowNumber'], 1)
        || !checkFormElementInRange(this, 'pos', PMA_messages['strNotValidRowNumber'], 0-1)) {
        return false;
    }

    var $form = $(this);
    if (! checkSqlQuery($form[0])) {
        return false;
    }

    // remove any div containing a previous error message
    $('.error').remove();

    var $msgbox = PMA_ajaxShowMessage();

    PMA_prepareForAjaxRequest($form);

    $.post($form.attr('action'), $form.serialize() , function(data) {
        if (data.success == true) {
            $('.success').fadeOut();

            if (typeof data.chartData != 'undefined') {
                chart_data = jQuery.parseJSON(data.chartData);
                drawChart();
                $('#querychart').show();
            }
        } else {
            PMA_ajaxRemoveMessage($msgbox);
            PMA_ajaxShowMessage(data.error, false);
            chart_data = null;
            drawChart();
        }
        PMA_ajaxRemoveMessage($msgbox);
    }, "json"); // end $.post()

    return false;
}); // end

function drawChart() {
    nonJqplotSettings.chart.width = $('#resizer').width() - 20;
    nonJqplotSettings.chart.height = $('#resizer').height() - 20;

    // todo: a better way using .replot() ?
    if (currentChart != null) {
        currentChart.destroy();
    }
    currentChart = PMA_queryChart(chart_data, currentSettings, nonJqplotSettings);
}

function in_array(element,array)
{
    for(var i=0; i < array.length; i++)
        if(array[i] == element) return true;
    return false;
}

function PMA_queryChart(data, passedSettings, passedNonJqplotSettings)
{
    if ($('#querychart').length == 0) return;

    var columnNames = Array();

    var series = new Array();
    var xaxis = { type: 'linear' };
    var yaxis = new Object();

    $.each(data[0],function(index,element) {
        columnNames.push(index);
    });

    switch(passedNonJqplotSettings.chart.type) {
        case 'column':
        case 'spline':
        case 'line':
        case 'bar':
            var j = 0;
            for (var i = 0; i < columnNames.length; i++) {
                if (i != chart_xaxis_idx) {
                    series[j] = new Array();
                    if (chart_series == 'columns' || chart_series == columnNames[i]) {
                        $.each(data,function(key,value) {
                            series[j].push(
                                [
                                value[columnNames[chart_xaxis_idx]],
                                // todo: not always a number?
                                parseFloat(value[columnNames[i]])
                                ]
                            );
                        });
                        j++;
                    }
                }
            }
            if(columnNames.length == 2)
                yaxis.title = { text: columnNames[0] };
            break;

        case 'pie':
            // only available for a specific column
            // todo: warn the user about this
            if (chart_series != 'columns') {
                series[0] = new Array();
                $.each(data,function(key,value) {
                    series[0].push(
                        [
                        value[columnNames[chart_xaxis_idx]],
                        parseFloat(value[chart_series])
                        ]
                     );
                });
                break;
            }
    }

    var settings = {
        title: {
            text: '',
            escapeHtml: true
            //margin:20
        }
    };

    if (passedNonJqplotSettings.chart.type == 'line') {
        settings.axes = {
            xaxis: {
            },
            yaxis: {
            }
        }
    }

    if (passedNonJqplotSettings.chart.type == 'bar') {
        settings.seriesDefaults = {
            renderer: $.jqplot.BarRenderer,
            rendererOptions: {
                barDirection: 'vertical',
                highlightMouseOver: true
            }
        };
        settings.axes = {
            xaxis: {
                renderer: $.jqplot.CategoryAxisRenderer
            },
            yaxis: {
            }
        };
    }

    if (passedNonJqplotSettings.chart.type == 'spline') {
        settings.seriesDefaults = {
            rendererOptions: {
                smooth: true
            }
        };
    }

    if (passedNonJqplotSettings.chart.type == 'pie') {
        settings.seriesDefaults = {
            renderer: $.jqplot.PieRenderer,
            rendererOptions: {
                showDataLabels: true,
                highlightMouseOver: true,
                showDataLabels: true,
                dataLabels: 'value'
            }
        };
    }
    // Overwrite/Merge default settings with passedsettings
    $.extend(true, settings, passedSettings);

    settings.series = new Array();
    for (var i = 0; i < columnNames.length; i++) {
        if (parseInt(chart_xaxis_idx) != i) {
            if (chart_series == 'columns' || chart_series == columnNames[i]) {
                settings.series.push({ label: columnNames[i] });
            }
        }
    }

    return $.jqplot('querychart', series, settings);
}
