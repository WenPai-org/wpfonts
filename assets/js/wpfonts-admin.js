(function () {
	var input = document.getElementById("wpfonts-package-file");
	var zone = document.getElementById("wpfonts-install-zone");
	var name = document.getElementById("wpfonts-package-name");
	var button = document.getElementById("wpfonts-package-install");
	if (!input || !name || !button) {
		return;
	}

	function emptyText() {
		return name.getAttribute("data-empty") || "";
	}

	function show(file) {
		var ok = !!(file && /\.zip$/i.test(file.name));
		name.textContent = ok ? file.name : emptyText();
		button.disabled = !ok;
	}

	function take(file) {
		if (!file || !/\.zip$/i.test(file.name) || typeof DataTransfer === "undefined") {
			return;
		}
		var list = new DataTransfer();
		list.items.add(file);
		input.files = list.files;
		show(file);
	}

	input.addEventListener("change", function () {
		show(input.files && input.files[0]);
	});

	if (!zone) {
		return;
	}

	zone.addEventListener("dragover", function (event) {
		event.preventDefault();
		zone.classList.add("is-drag");
	});

	zone.addEventListener("dragleave", function (event) {
		if (event.relatedTarget && zone.contains(event.relatedTarget)) {
			return;
		}
		zone.classList.remove("is-drag");
	});

	zone.addEventListener("drop", function (event) {
		event.preventDefault();
		zone.classList.remove("is-drag");
		var file = event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0];
		take(file);
	});
})();
