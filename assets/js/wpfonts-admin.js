(function () {
	document.addEventListener("change", function (event) {
		var input = event.target;
		if (!input || input.id !== "wpfonts-package-file") {
			return;
		}
		var out = document.getElementById("wpfonts-package-name");
		if (!out) {
			return;
		}
		var empty = out.getAttribute("data-empty") || "";
		var file = input.files && input.files[0];
		out.textContent = file && file.name ? file.name : empty;
	});
})();
