function inArray(element, array) {
	var found = false;
	for ( var i = 0 ; i < array.length ; i++ ) {
		if (array[i] == element) {
			found = true;
			break;
		}
	}
	return found;
}

function updateSelect(oSelect, aStrings) {
	// remove old groups.
	var oldSelected = getSelected(oSelect);

	while (oSelect.options.length > 0) {
		var tmpItem = oSelect.item(0);
		oSelect.removeChild(tmpItem);
	}

	// insert the new elements.
	aStrings.sort();
	for ( var i = 0 ; i < aStrings.length ; i++) {
		var sValue = aStrings[i];
		var oNewOption = document.createElement("option");
		oNewOption.value = sValue;
		oNewOption.text = sValue;
		if (inArray(sValue, oldSelected)) {
			oNewOption.selected = true;
		} else {
			oNewOption.selected = false;
		}
		oSelect.appendChild(oNewOption);
	}
}

function getSelected(oSelect) {
	// find the new groups
	var aResult = new Array();
	for ( var i = 0 ; i < oSelect.options.length ; i++ ) {
		var oOption = oSelect.item(i);
		if (!oOption.selected) {
			continue;
		}
		aResult[aResult.length] = oOption.value;
	}
	return aResult;
}

function updateGroups() {
	var oForm = document.forms['form'];
	var aIps = getSelected(oForm.elements['computers[]']);
	var groups = new Array();
	for (var i = 0 ; i < aIps.length ; i++) {
		var sIp = aIps[i];
		for (var group in all[sIp]) {
			if (!inArray(group, groups)) {
				groups[groups.length] = group;
			}
		}
	}
	var oSelect2 = oForm.elements["groups[]"];
	updateSelect(oSelect2, groups);
}

function updateGraphs() {
	var oForm = document.forms['form'];
	var aIps = getSelected(oForm.elements['computers[]']);
	var aGroups = getSelected(oForm.elements["groups[]"]);

	var graphs = new Array();
	for (var i = 0 ; i < aIps.length ; i++) {
		var sIp = aIps[i];
		for (var j = 0 ; j < aGroups.length ; j++) {
			var sGroup = aGroups[j];
			for (var graph in all[sIp][sGroup]) {
				if (!inArray(graph, graphs)) {
					graphs[graphs.length] = graph;
				}
			}
		}
	}
	updateSelect(oForm.elements['graphs[]'], graphs);
}

function update() {
	updateGroups();
	updateGraphs();
}
