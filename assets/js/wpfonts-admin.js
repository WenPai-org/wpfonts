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

(function () {
	var root = document.getElementById("wpfonts-catalog");
	var search = document.getElementById("wpfonts-catalog-search");
	var family = document.getElementById("wpfonts-family");
	var list = document.getElementById("wpfonts-catalog-list");
	var status = document.getElementById("wpfonts-catalog-status");
	var scope = document.getElementById("wpfonts-catalog-scope");
	if (!root || !list || !family) {
		return;
	}

	var cfg = window.wpfontsCatalog || {};
	var loadingText = root.getAttribute("data-loading") || "";
	var failText = root.getAttribute("data-fail") || "";
	var emptyText = root.getAttribute("data-empty") || "";
	var loaded = [];

	function trim(value) {
		return (value || "").replace(/^\s+|\s+$/g, "");
	}

	function familyValue() {
		return trim(family.value).toLowerCase();
	}

	function paint() {
		var current = familyValue();
		var radios = list.querySelectorAll('input[name="wpfonts_catalog_pick"]');
		for (var i = 0; i < radios.length; i++) {
			var on = radios[i].value === current;
			radios[i].checked = on;
			var label = radios[i].closest ? radios[i].closest(".choice") : null;
			if (label) {
				label.classList.toggle("is-on", on);
			}
		}
	}

	function setStatus(text, show) {
		if (!status) {
			return;
		}
		status.hidden = !show;
		if (show) {
			status.textContent = text;
		}
	}

	function render(rows, fromLoadedPage) {
		list.textContent = "";
		if (!rows || !rows.length) {
			list.hidden = true;
			if (scope) {
				scope.hidden = true;
			}
			return;
		}
		list.hidden = false;
		var current = familyValue();
		for (var i = 0; i < rows.length; i++) {
			var row = rows[i];
			if (!row || !row.fontFamily) {
				continue;
			}
			var label = document.createElement("label");
			label.className = "choice" + (row.fontFamily === current ? " is-on" : "");
			var input = document.createElement("input");
			input.type = "radio";
			input.name = "wpfonts_catalog_pick";
			input.value = row.fontFamily;
			input.checked = row.fontFamily === current;
			var text = document.createElement("span");
			var title = document.createElement("span");
			title.className = "t";
			title.textContent = row.name || row.fontFamily;
			var meta = document.createElement("span");
			meta.className = "d";
			meta.textContent = row.fontFamily;
			text.appendChild(title);
			text.appendChild(meta);
			label.appendChild(input);
			label.appendChild(text);
			list.appendChild(label);
		}
		if (scope) {
			scope.hidden = !fromLoadedPage;
		}
	}

	function filterLoaded(query) {
		var needle = query.toLowerCase();
		var rows = [];
		for (var i = 0; i < loaded.length; i++) {
			var name = (loaded[i].name || "").toLowerCase();
			var slug = (loaded[i].fontFamily || "").toLowerCase();
			if (name.indexOf(needle) !== -1 || slug.indexOf(needle) !== -1) {
				rows.push(loaded[i]);
			}
		}
		return rows;
	}

	function readLoaded() {
		var node = document.getElementById("wpfonts-catalog-data");
		if (node && node.textContent) {
			try {
				var parsed = JSON.parse(node.textContent);
				if (parsed && parsed.length) {
					loaded = parsed;
					return;
				}
			} catch (error) {
				loaded = [];
			}
		}
		var existing = list.querySelectorAll(".choice");
		loaded = [];
		for (var i = 0; i < existing.length; i++) {
			var input = existing[i].querySelector("input");
			var title = existing[i].querySelector(".t");
			if (!input || !input.value) {
				continue;
			}
			loaded.push({
				name: title ? title.textContent : input.value,
				fontFamily: input.value
			});
		}
	}

	list.addEventListener("change", function (event) {
		var target = event.target;
		if (!target || target.name !== "wpfonts_catalog_pick") {
			return;
		}
		family.value = target.value;
		paint();
	});

	family.addEventListener("input", paint);

	if (search) {
		search.addEventListener("keydown", function (event) {
			if (event.key === "Enter") {
				event.preventDefault();
			}
		});
		var timer = 0;
		search.addEventListener("input", function () {
			var query = trim(search.value);
			if (timer) {
				window.clearTimeout(timer);
			}
			if (!query) {
				render(loaded, false);
				setStatus("", false);
				return;
			}
			var local = filterLoaded(query);
			if (local.length) {
				render(local, true);
				setStatus("", false);
			} else {
				list.hidden = true;
				if (scope) {
					scope.hidden = true;
				}
				setStatus(loadingText, true);
			}
			if (!cfg.ajaxUrl || !cfg.nonce || !window.fetch || !window.FormData) {
				if (!local.length) {
					setStatus(emptyText || failText, true);
				}
				return;
			}
			timer = window.setTimeout(function () {
				var body = new window.FormData();
				body.append("action", "wpfonts_catalog");
				body.append("_ajax_nonce", cfg.nonce);
				body.append("page", "1");
				body.append("search", query);
				window.fetch(cfg.ajaxUrl, {
					method: "POST",
					credentials: "same-origin",
					body: body
				}).then(function (response) {
					return response.json();
				}).then(function (payload) {
					if (trim(search.value) !== query) {
						return;
					}
					var fonts = payload && payload.success && payload.data ? payload.data.fonts : null;
					if (!fonts) {
						if (!local.length) {
							list.hidden = true;
							setStatus(failText, true);
						}
						return;
					}
					if (!fonts.length) {
						list.hidden = true;
						if (scope) {
							scope.hidden = true;
						}
						setStatus(emptyText, true);
						return;
					}
					render(fonts, false);
					setStatus("", false);
				}).catch(function () {
					if (trim(search.value) !== query) {
						return;
					}
					if (!local.length) {
						list.hidden = true;
						setStatus(failText, true);
					}
				});
			}, 400);
		});
	}

	readLoaded();
	paint();
})();
