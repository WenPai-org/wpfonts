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
	function revealSpecimen(img) {
		var next = img.getAttribute("data-fallback") || "";
		if (next) {
			img.removeAttribute("data-fallback");
			img.setAttribute("src", next);
			return;
		}
		img.hidden = true;
		var wrap = img.closest ? img.closest(".wpfonts-specimen-wrap, .choice") : null;
		if (!wrap) {
			wrap = img.parentNode;
		}
		if (!wrap) {
			return;
		}
		if (wrap.classList) {
			wrap.classList.add("is-specimen-missing");
		}
		var fallback = wrap.querySelector(".wpfonts-specimen-fallback");
		if (fallback) {
			fallback.hidden = false;
		}
	}

	document.addEventListener("error", function (event) {
		var img = event.target;
		if (!img || !img.getAttribute || !img.classList || !img.classList.contains("wpfonts-specimen")) {
			return;
		}
		revealSpecimen(img);
	}, true);
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
	var updatingText = root.getAttribute("data-updating") || "";
	var updatedText = root.getAttribute("data-updated") || "";
	var staleText = root.getAttribute("data-stale") || "";
	var refreshBtn = document.getElementById("wpfonts-catalog-refresh");
	var loaded = [];
	var refreshing = false;
	var selected = {};
	var holds = document.getElementById("wpfonts-family-holds");
	var glyphQueue = [];
	var glyphActive = 0;
	var glyphSeen = {};
	var glyphFaces = {};
	var glyphWait = {};
	var glyphObserver = null;
	var glyphLabel = root.getAttribute("data-glyph") || "字形预览";
	var glyphSample = root.getAttribute("data-glyph-sample") || "永和九年";

	function safeFace(value) {
		return String(value || "").replace(/["'\\<>{};]/g, "").replace(/^\s+|\s+$/g, "");
	}

	function allowedCss(url) {
		return /^https:\/\/(?:cn|hk|en)\.windfonts\.com\/api\/css\?family=wenfeng-[a-z0-9-]+&subset=(?:en|zh-common|zh|full)$/.test(url || "");
	}

	function allowedSpecimen(url) {
		return /^https:\/\/(?:cn|hk|en)\.windfonts\.com\/fonts-packages\/[a-z0-9-]+\/preview\/(?:preview|card|og)\.(?:webp|png)$/.test(url || "");
	}

	function pngSibling(url) {
		if (!allowedSpecimen(url) || !/\.webp$/.test(url)) {
			return "";
		}
		return url.replace(/\.webp$/, ".png");
	}

	function specimenShowing(label) {
		if (!label || !label.querySelector) {
			return false;
		}
		var img = label.querySelector("img.wpfonts-specimen");
		return !!(img && !img.hidden && label.classList && !label.classList.contains("is-specimen-missing"));
	}

	function ensureCssLink(url) {
		if (!allowedCss(url) || !document.head) {
			return;
		}
		var existing = document.querySelectorAll("link[data-wpfonts-css]");
		var i;
		for (i = 0; i < existing.length; i++) {
			if (existing[i].getAttribute("data-wpfonts-css") === url) {
				return;
			}
		}
		var link = document.createElement("link");
		link.rel = "stylesheet";
		link.href = url;
		link.setAttribute("data-wpfonts-css", url);
		document.head.appendChild(link);
	}

	function markGlyph(label, face) {
		face = safeFace(face);
		if (!label || !face) {
			return;
		}
		var stack = '"' + face + '", sans-serif';
		label.setAttribute("data-css-face", face);
		var nodes = label.querySelectorAll(".wpfonts-glyph");
		var i;
		for (i = 0; i < nodes.length; i++) {
			nodes[i].style.fontFamily = stack;
		}
		var text = label.querySelector("span");
		if (!text || text.querySelector(".wpfonts-glyph-sample")) {
			return;
		}
		var note = document.createElement("span");
		note.className = "meta wpfonts-glyph-label";
		note.textContent = glyphLabel;
		var sample = document.createElement("span");
		sample.className = "wpfonts-glyph-sample wpfonts-glyph";
		sample.style.fontFamily = stack;
		sample.textContent = glyphSample;
		text.appendChild(note);
		text.appendChild(sample);
	}

	function loadGlyphCss(url) {
		var controller = window.AbortController ? new window.AbortController() : null;
		var timer = window.setTimeout(function () {
			if (controller) {
				controller.abort();
			}
		}, 5000);
		var opts = {
			credentials: "omit",
			method: "GET"
		};
		if (controller) {
			opts.signal = controller.signal;
		}
		window.fetch(url, opts).then(function (response) {
			if (!response.ok) {
				throw new Error("css");
			}
			return response.text();
		}).then(function (css) {
			var match = /@font-face\s*\{[^}]*font-family\s*:\s*([^;}]+)/i.exec(css || "");
			var face = match ? safeFace(match[1]) : "";
			if (!face) {
				return;
			}
			glyphFaces[url] = face;
			ensureCssLink(url);
			var waiting = glyphWait[url] || [];
			var i;
			for (i = 0; i < waiting.length; i++) {
				markGlyph(waiting[i], face);
			}
			glyphWait[url] = [];
		}).catch(function () {
			glyphFaces[url] = "";
		}).then(function () {
			window.clearTimeout(timer);
			glyphActive--;
			pumpGlyphs();
		});
	}

	function pumpGlyphs() {
		while (glyphActive < 2 && glyphQueue.length) {
			var url = glyphQueue.shift();
			if (!url || !allowedCss(url) || !window.fetch) {
				continue;
			}
			glyphActive++;
			loadGlyphCss(url);
		}
	}

	function queueGlyph(label) {
		var url = label.getAttribute("data-css-url") || "";
		if (!allowedCss(url)) {
			return;
		}
		if (glyphFaces[url]) {
			ensureCssLink(url);
			markGlyph(label, glyphFaces[url]);
			return;
		}
		if (!glyphWait[url]) {
			glyphWait[url] = [];
		}
		glyphWait[url].push(label);
		if (glyphSeen[url]) {
			return;
		}
		glyphSeen[url] = true;
		glyphQueue.push(url);
		pumpGlyphs();
	}

	function watchGlyph(label) {
		if (!label) {
			return;
		}
		if (specimenShowing(label)) {
			return;
		}
		var face = label.getAttribute("data-css-face") || "";
		var url = label.getAttribute("data-css-url") || "";
		if (face) {
			if (allowedCss(url)) {
				ensureCssLink(url);
			}
			markGlyph(label, face);
			return;
		}
		if (!allowedCss(url)) {
			return;
		}
		if (glyphObserver) {
			glyphObserver.observe(label);
			return;
		}
		queueGlyph(label);
	}

	function watchGlyphs() {
		var labels = list.querySelectorAll(".choice");
		var i;
		for (i = 0; i < labels.length; i++) {
			watchGlyph(labels[i]);
		}
	}

	document.addEventListener("error", function (event) {
		var img = event.target;
		if (!img || !img.classList || !img.classList.contains("wpfonts-specimen") || !list.contains(img)) {
			return;
		}
		var label = img.closest ? img.closest(".choice") : null;
		if (!label || !label.classList.contains("is-specimen-missing")) {
			return;
		}
		watchGlyph(label);
	}, true);

	if (window.IntersectionObserver) {
		glyphObserver = new window.IntersectionObserver(function (entries) {
			var i;
			for (i = 0; i < entries.length; i++) {
				if (!entries[i].isIntersecting) {
					continue;
				}
				glyphObserver.unobserve(entries[i].target);
				queueGlyph(entries[i].target);
			}
		}, { root: list, rootMargin: "80px" });
	}

	function trim(value) {
		return (value || "").replace(/^\s+|\s+$/g, "");
	}

	function rememberSelected() {
		var nodes = document.querySelectorAll('input[name="wpfonts_settings[families][]"]');
		var i;
		for (i = 0; i < nodes.length; i++) {
			if (nodes[i].type === "hidden" || nodes[i].checked) {
				selected[nodes[i].value] = true;
			}
		}
	}

	function syncHolds() {
		if (!holds) {
			return;
		}
		holds.textContent = "";
		var visible = {};
		var boxes = list.querySelectorAll('input[type="checkbox"]');
		var i;
		for (i = 0; i < boxes.length; i++) {
			visible[boxes[i].value] = true;
		}
		var token;
		for (token in selected) {
			if (!Object.prototype.hasOwnProperty.call(selected, token) || !selected[token] || visible[token]) {
				continue;
			}
			var input = document.createElement("input");
			input.type = "hidden";
			input.name = "wpfonts_settings[families][]";
			input.value = token;
			holds.appendChild(input);
		}
	}

	function paint() {
		var boxes = list.querySelectorAll('input[type="checkbox"]');
		var i;
		for (i = 0; i < boxes.length; i++) {
			var on = !!selected[boxes[i].value];
			boxes[i].checked = on;
			var label = boxes[i].closest ? boxes[i].closest(".choice") : null;
			if (label) {
				label.classList.toggle("is-on", on);
			}
		}
		syncHolds();
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
		for (var i = 0; i < rows.length; i++) {
			var row = rows[i];
			if (!row || !row.fontFamily) {
				continue;
			}
			var label = document.createElement("label");
			label.className = "choice" + (selected[row.fontFamily] ? " is-on" : "");
			if (row.cssFace) {
				label.setAttribute("data-css-face", row.cssFace);
			}
			if (row.cssUrl) {
				label.setAttribute("data-css-url", row.cssUrl);
			}
			var cardUrl = allowedSpecimen(row.cardPreviewUrl) ? row.cardPreviewUrl : "";
			if (cardUrl) {
				label.setAttribute("data-card-url", cardUrl);
			}
			if (allowedSpecimen(row.previewUrl)) {
				label.setAttribute("data-preview-url", row.previewUrl);
			}
			var input = document.createElement("input");
			input.type = "checkbox";
			input.name = "wpfonts_settings[families][]";
			input.value = row.fontFamily;
			input.checked = !!selected[row.fontFamily];
			var text = document.createElement("span");
			if (cardUrl) {
				var img = document.createElement("img");
				img.className = "wpfonts-specimen";
				img.alt = row.name || row.fontFamily;
				img.decoding = "async";
				img.loading = "lazy";
				var png = pngSibling(cardUrl);
				if (png) {
					img.setAttribute("data-fallback", png);
				}
				img.src = cardUrl;
				text.appendChild(img);
			}
			var title = document.createElement("span");
			title.className = "t wpfonts-glyph";
			title.textContent = row.name || row.fontFamily;
			var meta = document.createElement("span");
			meta.className = "d";
			meta.textContent = row.fontFamily;
			text.appendChild(title);
			text.appendChild(meta);
			label.appendChild(input);
			label.appendChild(text);
			if (row.cssFace) {
				markGlyph(label, row.cssFace);
				if (cardUrl) {
					var sample = text.querySelector(".wpfonts-glyph-sample");
					var note = text.querySelector(".wpfonts-glyph-label");
					if (sample && note) {
						var box = document.createElement("span");
						box.className = "wpfonts-specimen-fallback";
						box.hidden = true;
						box.appendChild(note);
						box.appendChild(sample);
						text.appendChild(box);
					}
					title.style.fontFamily = "";
				} else if (row.cssUrl) {
					ensureCssLink(row.cssUrl);
				}
			}
			list.appendChild(label);
			watchGlyph(label);
		}
		if (scope) {
			scope.hidden = !fromLoadedPage;
		}
		syncHolds();
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
			var stored = {
				name: title ? title.textContent : input.value,
				fontFamily: input.value
			};
			if (existing[i].getAttribute("data-css-face")) {
				stored.cssFace = existing[i].getAttribute("data-css-face");
			}
			if (existing[i].getAttribute("data-css-url")) {
				stored.cssUrl = existing[i].getAttribute("data-css-url");
			}
			if (existing[i].getAttribute("data-card-url")) {
				stored.cardPreviewUrl = existing[i].getAttribute("data-card-url");
			}
			if (existing[i].getAttribute("data-preview-url")) {
				stored.previewUrl = existing[i].getAttribute("data-preview-url");
			}
			loaded.push(stored);
		}
	}

	function finishRefresh(message, fonts) {
		refreshing = false;
		if (refreshBtn) {
			refreshBtn.disabled = false;
		}
		if (fonts && fonts.length) {
			loaded = fonts;
		}
		var query = search ? trim(search.value) : "";
		var view = query ? filterLoaded(query) : loaded;
		render(view, !!query && view.length > 0);
		if (!loaded.length) {
			list.hidden = true;
		}
		setStatus(message, !!message);
	}

	if (refreshBtn) {
		refreshBtn.addEventListener("click", function (event) {
			event.preventDefault();
			if (refreshing) {
				return;
			}
			refreshing = true;
			refreshBtn.disabled = true;
			setStatus(updatingText, true);
			if (!cfg.ajaxUrl || !cfg.nonce || !window.fetch || !window.FormData) {
				finishRefresh(loaded.length ? staleText : failText, null);
				return;
			}
			var body = new window.FormData();
			body.append("action", "wpfonts_catalog");
			body.append("_ajax_nonce", cfg.nonce);
			body.append("refresh", "1");
			window.fetch(cfg.ajaxUrl, {
				method: "POST",
				credentials: "same-origin",
				body: body
			}).then(function (response) {
				return response.json();
			}).then(function (payload) {
				var data = payload && payload.data ? payload.data : {};
				var fonts = data && data.fonts;
				if (payload && payload.success && fonts && fonts.length) {
					finishRefresh(data.message || updatedText, fonts);
					return;
				}
				if (fonts && fonts.length) {
					finishRefresh(data.message || staleText, fonts);
					return;
				}
				finishRefresh(loaded.length ? (data.message || staleText) : ((data && data.message) || failText), null);
			}).catch(function () {
				finishRefresh(loaded.length ? staleText : failText, null);
			});
		});
	}

	list.addEventListener("change", function (event) {
		var target = event.target;
		if (!target || target.name !== "wpfonts_settings[families][]") {
			return;
		}
		if (target.checked) {
			selected[target.value] = true;
		} else {
			delete selected[target.value];
		}
		paint();
	});

	family.addEventListener("input", function () {
		paint();
	});

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
	rememberSelected();
	paint();
	watchGlyphs();
})();
