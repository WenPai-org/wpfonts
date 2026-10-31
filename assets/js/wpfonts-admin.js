(function () {
	var input = document.getElementById("wpfonts-package-file");
	var zone = document.getElementById("wpfonts-install-zone");
	var form = document.getElementById("wpfonts-install-form");
	var status = document.getElementById("wpfonts-install-status");
	if (!input || !zone || !form) {
		return;
	}

	function isZip(file) {
		return !!(file && /\.zip$/i.test(file.name));
	}

	var busy = false;

	function install(file, fromInput) {
		if (busy || !isZip(file)) {
			return;
		}
		busy = true;
		if (!fromInput && typeof DataTransfer !== "undefined") {
			var list = new DataTransfer();
			list.items.add(file);
			input.files = list.files;
		}
		if (status) {
			status.hidden = false;
			status.textContent = status.getAttribute("data-busy") || "";
		}
		zone.classList.add("is-busy");
		form.submit();
	}

	input.addEventListener("click", function (event) {
		event.stopPropagation();
	});

	input.addEventListener("change", function () {
		install(input.files && input.files[0], true);
	});

	zone.addEventListener("click", function () {
		input.click();
	});

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
		install(file, false);
	});
})();
