function fetchChart(computer, service, delta) {
	var $container = $("#" + btoa(computer+service).split("=").join("\\="));
	if ($container.length == 0) {
		console.error("container not found: " + $container.selector);
		return;
	}

	var url = "jsonview.php?computer="+ computer +"&timedelta="+delta+"&service="+service;
	jQuery.get(url, function(result) {
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
				pointInterval : result.meta.step * 1000,
				data : els,
				pointStart: Date.now() - delta*1000
			};
		});
		showChart(series, result.meta.step, service, $container, delta);
	}, "json");
}


function getVector(data, index) {
	var res = [];
	for (i =0; i<data.length; i++) {
		res.push(data[i][index]);
	}
	return res;
}


function showChart(aSeries, interval, service, $container, delta) {
	Highcharts.setOptions({
		global: {
			useUTC: false
		}
	});
	var stacking = jQuery("#stacking").val();
	$container.highcharts({
		chart: {
			type: 'area',
			zoomType: 'xy',
			plotshadow: true,
			height: 300
		},
		title: {
			text: service
		},
		xAxis: {
			tickInterval: interval * 1000,
			type: 'datetime',
			title: {
				text: "date"
			},
			labels: {
				maxStaggerLines: 1
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
			pointFormat: '{series.name}:<b>{point.y}</b>'
		},
		series: aSeries
	});
}

