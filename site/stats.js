

// Dit moet omdat rrdtool geen geldige JSON uitkakt, anders konden we gewoon jQuery.getJSON() kunnen gebruiken, maar helaas.
// TODO: haal dit weg als rrdtool gefixed wordt.
function hackEval(sData) {
	return eval("(" + sData + ")");
}

function fetchChart(computer, service, delta) {
	var container = btoa(computer+service).split("=").join("\\=");
	var h = jQuery.get("jsonview.php?computer="+ computer +"&timedelta="+delta+"&service="+service,
			function(ret) { 
				ldata = (hackEval(ret));
				var legend = ldata.meta.legend;
				series = [];
				legend.map(function(l){
					var index = legend.indexOf(l);
					var els = getVector(ldata.data, index);
					series.push({ name : l, pointInterval : ldata.meta.step * 1000,  data : els, pointStart: Date.now() - delta*1000});
				});
				showChart(series, ldata.meta.step, service, container, delta);
			}
	);
}


function getVector(data, index) {
	var res = [];
	for (i =0; i<ldata.data.length; i++) {
		res.push(ldata.data[i][index]);
	}
	return res;
}


function showChart(aSeries, interval, service, container, delta) {
	var stacking = jQuery("#stacking").val();
	$('#'+container).highcharts({
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

