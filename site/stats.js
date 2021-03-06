jQuery(function() {
	$("div.rjchart").map(function(i, obj) {
		fetchChart($(obj));
	});

	$("input[name='filter-services']").on("keyup", function(e) {
		e.preventDefault();
		var search = $(this).val().toLowerCase();
		$("select[name='services[]'] option").map(function(i, obj) {
			var val = $(obj).text().toLowerCase();
			var show = val.indexOf(search) > -1;
			$(obj).prop("hidden", ! show);
		});
	});
});

function fetchChart($container) {
	if ($container.length == 0) {
		console.error("container not found: " + $container.selector);
		return;
	}
	var computer = $container.data("computer");
	var service = $container.data("service");
	var starttime = $container.data("starttime");
	var spike_detect = $("input[name='spike_detect']").val();

	var d = jQuery.ajax({
		url : 'jsonview.php',
		data : {
			computer : computer,
			service : service,
			start : starttime,
			spike_detect : spike_detect
		}
	});
	d.done(function(result) {
		if (result.error) {
			$err = $("<div style='color:red'/>").appendTo($container).text(result.error);
			return;
		}

		var legend = result.meta.legend;
		var series = legend.map(function(l) {
			var index = legend.indexOf(l);
			var els = getVector(result.data, index);
			return {
				name : l,
				index: legend.length - 1 - index,
				pointInterval : result.meta.step * 1000,
				data : els,
				pointStart: starttime * 1000
			};
		});
		showChart(series, result.meta.step, service, $container, starttime, result.meta.stacked);

		var str = [];
		for (var key in result.meta.spikes) {
			var count = result.meta.spikes[key]['removed'].length;
			if (count > 0) {
				str.push("Removed " + count + " spike points from " + key);
			}
		}
		$container.append($("<span/>").html(str.join("<br/>")));
	});
}


function getVector(data, index) {
	var res = [];
	for (i =0; i<data.length; i++) {
		res.push(data[i][index]);
	}
	return res;
}


function showChart(aSeries, interval, service, $container, starttime, stacking) {
	Highcharts.setOptions({
		global: {
			useUTC: true,
			timezoneOffset : 0
		},
		lang : {
			decimalPoint: ',',
			thousandsSep: '.'
		}
	});
	$container.highcharts({
		chart: {
			type: 'area',
			zoomType: 'xy',
			plotshadow: true,
			height: 300,
			spacingRight: 0
		},
		legend: {
			reversed : true
		},
		title: {
			text: service
		},
		xAxis: {
			type: 'datetime',
			title: {
				text: "date"
			},
			labels: {
				maxStaggerLines: 1
			},
			plotBands: {
				color: 'lightgrey',
				from: Date.now() - interval*1000,
				to: Date.now()
			}
		},
		yAxis: {
			allowDecimals : true,
			title: {
				text: service
			},
		},
		plotOptions: {
			pointInterval: interval,
			series: {
				animation: false
			},
			area: {
				stacking: stacking,
				marker: {
					enabled: false,
					symbol: 'circle',
					radius: 2,
					states: {
						hover: {
							enabled: true
						}
					}
				}
			}
		},
		tooltip: {
			pointFormat: '{series.name}:<b>{point.y}</b><br/>',
			valueDecimals : 2,
			shared : true
		},
		series: aSeries
	});
}

