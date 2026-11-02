/**
 * wenpai-admin-ui kit behaviour. No jQuery. Product pages may no-op
 * by using data-wenpai-post on toggles (those submit via admin-post).
 */
(function () {
	"use strict";

	var FADE_MS = 5000;

	function inApp(el) {
		return el && el.closest && el.closest(".wenpai-app");
	}

	function reduceMotion() {
		return window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
	}

	/* Legacy button.toggle (0.2.x). The 0.3 .wenpai-switch needs no JS. Removed in 0.4. */
	function setToggle(btn, on) {
		btn.classList.toggle("on", on);
		btn.setAttribute("aria-pressed", on ? "true" : "false");
		var label = on ? btn.getAttribute("data-on") : btn.getAttribute("data-off");
		if (!label) {
			return;
		}
		var knob = btn.querySelector("i");
		btn.textContent = "";
		if (knob) {
			btn.appendChild(knob);
		} else {
			var i = document.createElement("i");
			btn.appendChild(i);
		}
		btn.appendChild(document.createTextNode(" " + label));
	}

	function syncChoice(radio) {
		var name = radio.getAttribute("name");
		if (!name) {
			return;
		}
		var root = radio.form || radio.closest(".wenpai-app") || document;
		var nodes = root.querySelectorAll(".choice input[type=\"radio\"]");
		for (var i = 0; i < nodes.length; i++) {
			if (nodes[i].name !== name) {
				continue;
			}
			var lab = nodes[i].closest(".choice");
			if (lab) {
				lab.classList.toggle("is-on", nodes[i].checked);
			}
		}
	}

	function syncGate(gate) {
		var box = gate.querySelector('input[type="checkbox"]');
		if (!box) {
			return;
		}
		var host = gate.closest("form") || gate.closest(".card") || gate.parentElement;
		if (!host) {
			return;
		}
		var apply = host.querySelectorAll("[data-wenpai-need-apply]");
		for (var i = 0; i < apply.length; i++) {
			apply[i].disabled = !box.checked;
		}
	}

	/* ---- depends_on (settings framework 0.3.0) ------------------------------
	 * One condition:  data-wenpai-depends-on="opt[field]" data-wenpai-depends-value="1"
	 *                 (value may be a JSON list = any of) [data-wenpai-depends-not]
	 *                 [data-wenpai-depends-mode="disable"]
	 * Tree:           data-wenpai-depends='{"mode":"hide|disable","relation":"AND|OR",
	 *                 "rules":[{"on":"opt[a]","value":["1"],"not":false,"reason":"…"}, {group}]}'
	 *                 (a bare JSON list = AND, 0.3.0-dev format).
	 * hide:    `hidden` + `inert`; inputs still submit.
	 * disable: controls `disabled`, the reason of the first failing condition is
	 *          written into .wenpai-field__reason; the server keeps the saved value.
	 * An element whose trigger control is not on the page is left as PHP printed it.
	 * Without JS nothing is hidden or disabled.
	 */
	var DEP_SEL = "[data-wenpai-depends-on], [data-wenpai-depends]";

	function attrQuote(s) {
		return String(s).replace(/["\\]/g, "\\$&");
	}

	function parseList(raw) {
		if (raw && raw.charAt(0) === "[") {
			try {
				var arr = JSON.parse(raw);
				if (Array.isArray(arr)) {
					return arr.map(String);
				}
			} catch (err) {
				/* a plain value that starts with "[" */
			}
		}
		return [raw === null ? "1" : raw];
	}

	function normNode(n) {
		if (!n || typeof n !== "object") {
			return null;
		}
		if (n.on !== undefined) {
			return {
				on: String(n.on),
				value: (Array.isArray(n.value) ? n.value : [n.value]).map(String),
				not: !!n.not,
				reason: n.reason ? String(n.reason) : "",
				saved: typeof n.saved === "boolean" ? n.saved : undefined
			};
		}
		var rules = Array.isArray(n) ? n : (Array.isArray(n.rules) ? n.rules : []);
		return {
			relation: n.relation === "OR" ? "OR" : "AND",
			rules: rules.map(normNode).filter(Boolean),
			reason: n.reason ? String(n.reason) : ""
		};
	}

	function treeOf(el) {
		if (el._wenpaiTree) {
			return el._wenpaiTree;
		}
		var raw = el.getAttribute("data-wenpai-depends");
		var tree = null;
		if (raw) {
			try {
				var parsed = JSON.parse(raw);
				tree = normNode(parsed);
				tree.mode = !Array.isArray(parsed) && parsed.mode === "disable" ? "disable" : "hide";
			} catch (err) {
				tree = null;
			}
		} else {
			tree = {
				relation: "AND",
				reason: el.getAttribute("data-wenpai-depends-reason") || "",
				rules: [{
					on: el.getAttribute("data-wenpai-depends-on"),
					value: parseList(el.getAttribute("data-wenpai-depends-value")),
					not: el.hasAttribute("data-wenpai-depends-not"),
					reason: "",
					saved: el.hasAttribute("data-wenpai-depends-saved") ? el.getAttribute("data-wenpai-depends-saved") === "1" : undefined
				}],
				mode: el.getAttribute("data-wenpai-depends-mode") === "disable" ? "disable" : "hide"
			};
		}
		el._wenpaiTree = tree;
		return tree;
	}

	function controlsFor(scope, name) {
		var q = attrQuote(name);
		return scope.querySelectorAll('[name="' + q + '"], [name="' + q + '[]"]');
	}

	/* Current values of a field. Toggle: "1" or "0". Multiselect: the checked values. */
	function valuesOf(nodes) {
		var vals = [];
		var hiddenVals = [];
		var sawChoice = false;
		for (var i = 0; i < nodes.length; i++) {
			var n = nodes[i];
			var type = (n.type || "").toLowerCase();
			if (type === "checkbox" || type === "radio") {
				sawChoice = true;
				if (n.checked) {
					vals.push(n.value);
				}
			} else if (type === "hidden") {
				hiddenVals.push(n.value);
			} else if (n.tagName === "SELECT" && n.multiple) {
				for (var o = 0; o < n.options.length; o++) {
					if (n.options[o].selected) {
						vals.push(n.options[o].value);
					}
				}
			} else {
				vals.push(n.value);
			}
		}
		if (sawChoice && !vals.length) {
			return hiddenVals;
		}
		return vals.length ? vals : hiddenVals;
	}

	/* A trigger that is itself hidden or disabled by depends_on never matches (same as PHP inactive_fields()). */
	function hiddenByDepends(node) {
		return !!(node.closest && node.closest("[data-wenpai-dep-hidden], [data-wenpai-dep-disabled]"));
	}

	/*
	 * A secret trigger with an empty box but a saved value (data-wenpai-saved)
	 * still has a value: the box is always empty on load. Not when the user
	 * ticked 「清除已保存的值」.
	 */
	function savedSecret(node) {
		if (!node.matches || !node.matches("input[data-wenpai-secret]") || node.value !== "" || !node.hasAttribute("data-wenpai-saved")) {
			return false;
		}
		var wrap = node.closest(".wenpai-field");
		var clear = wrap && wrap.querySelector(".wenpai-field__clear input[type=\"checkbox\"]");
		return !(clear && clear.checked);
	}

	/*
	 * Returns {ok: bool, reason: string}, or null when it cannot be decided here.
	 * A trigger whose control is not on the page (another tab) uses `saved`, its
	 * result against saved values, printed by PHP. Without it (a secret trigger)
	 * the condition is undecided and the state PHP printed stays.
	 */
	function evalNode(scope, n) {
		if (n.on !== undefined) {
			var nodes = controlsFor(scope, n.on);
			if (!nodes.length) {
				if (typeof n.saved === "boolean") {
					return { ok: n.saved, reason: n.saved ? "" : n.reason };
				}
				return null;
			}
			if (hiddenByDepends(nodes[0])) {
				/* A hidden or disabled trigger never satisfies a condition, `not` included (same as PHP node_met()). */
				return { ok: false, reason: n.reason };
			}
			var match;
			if (savedSecret(nodes[0])) {
				/* Saved secret: known to be non-empty, its value is not on the page. */
				if (n.value.some(function (v) { return v !== ""; })) {
					return null;
				}
				match = false;
			} else {
				var vals = valuesOf(nodes);
				match = vals.some(function (v) {
					return n.value.indexOf(v) !== -1;
				});
			}
			var ok = n.not ? !match : match;
			return { ok: ok, reason: ok ? "" : n.reason };
		}
		var firstReason = "";
		var any = false;
		var all = true;
		var open = false;
		for (var i = 0; i < n.rules.length; i++) {
			var r = evalNode(scope, n.rules[i]);
			if (r === null) {
				open = true;
				continue;
			}
			if (n.relation === "OR" && r.ok) {
				return { ok: true, reason: "" };
			}
			if (n.relation !== "OR" && !r.ok) {
				return { ok: false, reason: r.reason || n.reason };
			}
			any = any || r.ok;
			all = all && r.ok;
			if (!r.ok && !firstReason) {
				firstReason = r.reason;
			}
		}
		if (open) {
			return null;
		}
		var res = n.relation === "OR" ? any : all;
		return { ok: res, reason: res ? "" : (firstReason || n.reason) };
	}

	function usable(c) {
		return c && c.type !== "hidden" && !c.disabled && !hiddenByDepends(c) && !(c.closest && c.closest("[hidden]"));
	}

	/* Focus inside a block that gets hidden or disabled goes to the control the user changed. */
	function moveFocusOut(el, scope, tree, origin) {
		var active = document.activeElement;
		if (!active || !el.contains(active)) {
			return;
		}
		if (usable(origin)) {
			origin.focus();
			return;
		}
		var first = tree;
		while (first && first.on === undefined && first.rules && first.rules.length) {
			first = first.rules[0];
		}
		if (!first || first.on === undefined) {
			return;
		}
		var back = controlsFor(scope, first.on);
		for (var i = 0; i < back.length; i++) {
			if (usable(back[i])) {
				back[i].focus();
				return;
			}
		}
	}

	function applyHide(el, show, scope, tree, origin) {
		if (show === !el.hasAttribute("data-wenpai-dep-hidden")) {
			return false;
		}
		if (!show) {
			moveFocusOut(el, scope, tree, origin);
			el.hidden = true;
			el.setAttribute("inert", "");
			el.setAttribute("data-wenpai-dep-hidden", "");
		} else {
			el.hidden = false;
			el.removeAttribute("inert");
			el.removeAttribute("data-wenpai-dep-hidden");
		}
		return true;
	}

	function applyDisable(el, enable, reason, scope, tree, origin) {
		var note = el.querySelector(".wenpai-field__reason");
		if (note) {
			note.textContent = enable ? "" : reason;
			note.hidden = enable || !reason;
		}
		if (enable === !el.hasAttribute("data-wenpai-dep-disabled")) {
			return false;
		}
		var ctl = el.querySelectorAll("input, select, textarea, button");
		if (!enable) {
			moveFocusOut(el, scope, tree, origin);
			/* Hidden companions too (a toggle's "0"): a disabled field submits nothing; the server keeps the stored value. */
			for (var i = 0; i < ctl.length; i++) {
				if (ctl[i].disabled) {
					continue;
				}
				ctl[i].disabled = true;
				ctl[i].setAttribute("data-wenpai-dep-dis", "");
			}
			el.setAttribute("data-wenpai-dep-disabled", "");
			el.classList.add("is-disabled");
		} else {
			for (var j = 0; j < ctl.length; j++) {
				if (ctl[j].hasAttribute("data-wenpai-dep-dis")) {
					ctl[j].disabled = false;
					ctl[j].removeAttribute("data-wenpai-dep-dis");
				}
			}
			el.removeAttribute("data-wenpai-dep-disabled");
			el.classList.remove("is-disabled");
		}
		return true;
	}

	/* origin: the control the user changed; focus goes back to it. */
	function syncDepends(root, origin) {
		var scope = root || document;
		var els = scope.querySelectorAll(".wenpai-app [data-wenpai-depends-on], .wenpai-app [data-wenpai-depends]");
		/* Repeat so chains settle (a hides b, b hides c). */
		for (var pass = 0; pass < 5; pass++) {
			var changed = false;
			for (var i = 0; i < els.length; i++) {
				var el = els[i];
				var tree = treeOf(el);
				if (!tree) {
					continue;
				}
				var form = el.closest("form") || el.closest(".wenpai-app") || document;
				var res = evalNode(form, tree);
				if (res === null) {
					continue;
				}
				var did = tree.mode === "disable"
					? applyDisable(el, res.ok, res.reason || tree.reason, form, tree, origin)
					: applyHide(el, res.ok, form, tree, origin);
				changed = changed || did;
			}
			if (!changed) {
				break;
			}
		}
	}

	/* ---- field actions (data-wenpai-action) --------------------------------- */

	function submitPairs(scope, name) {
		var out = [];
		var nodes = controlsFor(scope, name);
		for (var i = 0; i < nodes.length; i++) {
			var n = nodes[i];
			var type = (n.type || "").toLowerCase();
			if ((type === "checkbox" || type === "radio") && !n.checked) {
				continue;
			}
			if (n.tagName === "SELECT" && n.multiple) {
				for (var o = 0; o < n.options.length; o++) {
					if (n.options[o].selected) {
						out.push([n.name, n.options[o].value]);
					}
				}
				continue;
			}
			out.push([n.name, n.value]);
		}
		return out;
	}

	function jsonAttr(el, name, fallback) {
		var raw = el.getAttribute(name);
		if (!raw) {
			return fallback;
		}
		try {
			return JSON.parse(raw);
		} catch (err) {
			return fallback;
		}
	}

	/* POST to admin-ajax. Resolves {ok, message, data, code}. Times out after 15 s. */
	function request(url, action, nonce, pairs, extra) {
		var fd = new FormData();
		fd.append("action", action);
		fd.append("_wpnonce", nonce || "");
		(pairs || []).forEach(function (p) {
			fd.append(p[0], p[1]);
		});
		Object.keys(extra || {}).forEach(function (k) {
			fd.append(k, extra[k]);
		});
		var ctrl = window.AbortController ? new window.AbortController() : null;
		var timer = window.setTimeout(function () {
			if (ctrl) {
				ctrl.abort();
			}
		}, 15000);
		return window.fetch(url || window.ajaxurl, {
			method: "POST",
			body: fd,
			credentials: "same-origin",
			signal: ctrl ? ctrl.signal : undefined
		}).then(function (r) {
			return r.json();
		}).then(function (j) {
			window.clearTimeout(timer);
			var d = (j && j.data) || {};
			return { ok: !!(j && j.success && d.ok !== false), message: d.message || "", data: d.data === undefined ? null : d.data, code: d.code ? String(d.code) : "" };
		}, function () {
			window.clearTimeout(timer);
			return { ok: false, message: "", data: null, code: "" };
		});
	}

	function actionScope(btn) {
		return btn.closest("form") || btn.closest(".wenpai-app") || document;
	}

	function syncActionReady(btn) {
		if (btn.getAttribute("aria-busy") === "true") {
			return;
		}
		var needs = jsonAttr(btn, "data-wenpai-requires", []);
		var scope = actionScope(btn);
		var missing = false;
		needs.forEach(function (name) {
			var nodes = controlsFor(scope, name);
			var have = false;
			for (var i = 0; i < nodes.length; i++) {
				if (nodes[i].type !== "hidden" && (nodes[i].value !== "" || nodes[i].hasAttribute("data-wenpai-saved"))) {
					have = true;
				}
			}
			missing = missing || !have;
		});
		btn.disabled = missing || btn.closest("[data-wenpai-dep-disabled]") !== null;
		var res = document.getElementById(btn.getAttribute("data-wenpai-result"));
		if (res && needs.length) {
			var note = btn.getAttribute("data-wenpai-requires-note") || "";
			if (missing && !res.getAttribute("data-wenpai-filled")) {
				res.className = "wenpai-field__result is-note";
				res.textContent = note;
			} else if (!missing && res.classList.contains("is-note")) {
				res.className = "wenpai-field__result";
				res.textContent = "";
			}
		}
	}

	/* The WP_Error code is never shown; it is on data-wenpai-code and in the wenpai:action event. */
	function setResult(res, ok, message, code) {
		res.setAttribute("role", ok ? "status" : "alert");
		res.className = "wenpai-field__result " + (ok ? "is-ok" : "is-bad");
		res.textContent = message;
		res.setAttribute("data-wenpai-filled", "1");
		if (code) {
			res.setAttribute("data-wenpai-code", code);
		} else {
			res.removeAttribute("data-wenpai-code");
		}
	}

	function clearResultsFor(control) {
		var name = control.name;
		if (!name) {
			return;
		}
		var scope = actionScope(control);
		var btns = scope.querySelectorAll("[data-wenpai-action]");
		for (var i = 0; i < btns.length; i++) {
			var inc = jsonAttr(btns[i], "data-wenpai-include", []);
			var base = name.replace(/\[\]$/, "");
			if (inc.indexOf(base) === -1) {
				continue;
			}
			var res = document.getElementById(btns[i].getAttribute("data-wenpai-result"));
			if (res && res.getAttribute("data-wenpai-filled")) {
				res.textContent = "";
				res.className = "wenpai-field__result";
				res.removeAttribute("data-wenpai-filled");
				res.removeAttribute("data-wenpai-code");
				res.setAttribute("role", "status");
			}
			syncActionReady(btns[i]);
		}
	}

	function runAction(btn) {
		if (btn.disabled || btn.getAttribute("aria-busy") === "true") {
			return;
		}
		var scope = actionScope(btn);
		var pairs = [];
		jsonAttr(btn, "data-wenpai-include", []).forEach(function (name) {
			pairs = pairs.concat(submitPairs(scope, name));
		});
		var res = document.getElementById(btn.getAttribute("data-wenpai-result"));
		var label = btn.innerHTML;
		btn.disabled = true;
		btn.setAttribute("aria-busy", "true");
		/* data-wenpai-busy is the in-progress copy without 「…」 (e.g. 正在测试); the kit appends it. */
		btn.textContent = (btn.getAttribute("data-wenpai-busy") || "") + "…";
		var spin = document.createElement("span");
		spin.className = "spin";
		spin.setAttribute("aria-hidden", "true");
		btn.appendChild(spin);
		return request(btn.getAttribute("data-wenpai-url"), btn.getAttribute("data-wenpai-action"), btn.getAttribute("data-wenpai-nonce"), pairs, jsonAttr(btn, "data-wenpai-data", {})).then(function (r) {
			if (res) {
				setResult(res, r.ok, r.message || btn.getAttribute(r.ok ? "data-wenpai-ok" : "data-wenpai-fail") || "", r.code);
			}
			btn.innerHTML = label;
			btn.removeAttribute("aria-busy");
			btn.disabled = false;
			syncActionReady(btn);
			var ev;
			var detail = { action: btn.getAttribute("data-wenpai-action"), ok: r.ok, message: r.message, code: r.code, data: r.data };
			if (typeof window.CustomEvent === "function") {
				ev = new window.CustomEvent("wenpai:action", { bubbles: true, detail: detail });
				btn.dispatchEvent(ev);
			}
			return r;
		});
	}

	/* ---- secret: a typed new value wins over "clear" ------------------------- */

	function syncSecretClear(input) {
		var wrap = input.closest(".wenpai-field");
		var clear = wrap && wrap.querySelector(".wenpai-field__clear input[type=\"checkbox\"]");
		if (!clear) {
			return;
		}
		var typed = input.value !== "";
		if (typed) {
			clear.checked = false;
		}
		clear.disabled = typed || input.disabled;
	}

	/*
	 * A password input drops line breaks, so a pasted PEM would be saved folded
	 * into one line. Refuse the paste and flag it; the server answers with the
	 * secret_multiline field error and keeps the stored value.
	 */
	function multilineFlag(input) {
		var wrap = input.closest(".wenpai-field") || input.parentNode;
		return wrap.querySelector("input[data-wenpai-multiline-flag]");
	}

	function onSecretPaste(e) {
		var input = e.target;
		if (!input.matches || !input.matches("input[data-wenpai-secret]")) {
			return;
		}
		var text = (e.clipboardData || window.clipboardData) ? (e.clipboardData || window.clipboardData).getData("text") : "";
		if (!/[\r\n]/.test(String(text).trim())) {
			return;
		}
		e.preventDefault();
		input.value = "";
		syncSecretClear(input);
		var wrap = input.closest(".wenpai-field") || input.parentNode;
		if (!multilineFlag(input)) {
			var flag = document.createElement("input");
			flag.type = "hidden";
			flag.name = input.name.replace(/\[[^\]]*\]$/, "[__wenpai_multiline][]");
			flag.value = input.getAttribute("data-wenpai-secret");
			flag.setAttribute("data-wenpai-multiline-flag", "");
			input.parentNode.insertBefore(flag, input.nextSibling);
		}
		var msgId = input.id + "-multiline";
		var msg = document.getElementById(msgId);
		if (!msg) {
			msg = document.createElement("p");
			msg.className = "wenpai-field__error";
			msg.id = msgId;
			msg.setAttribute("role", "alert");
			var after = wrap.querySelector(".wenpai-field__row") || input;
			after.parentNode.insertBefore(msg, after.nextSibling);
			input.setAttribute("aria-describedby", (msgId + " " + (input.getAttribute("aria-describedby") || "")).trim());
		}
		msg.textContent = input.getAttribute("data-wenpai-multiline") || "";
		input.setAttribute("aria-invalid", "true");
	}

	function clearMultiline(input) {
		var flag = multilineFlag(input);
		if (!flag || input.value === "") {
			return;
		}
		flag.parentNode.removeChild(flag);
		var msg = document.getElementById(input.id + "-multiline");
		if (msg) {
			msg.parentNode.removeChild(msg);
		}
		input.setAttribute("aria-describedby", (input.getAttribute("aria-describedby") || "").replace(input.id + "-multiline", "").trim());
		if (!input.closest(".is-invalid")) {
			input.removeAttribute("aria-invalid");
		}
	}

	document.addEventListener("paste", onSecretPaste);

	/* ---- notices ------------------------------------------------------------ */

	/* A receipt that is already in the page at load is re-inserted once so screen readers read it. */
	function announce(root) {
		var nodes = document.querySelectorAll("[data-wenpai-announce]");
		for (var i = 0; i < nodes.length; i++) {
			if (nodes[i]._wenpaiAnnounced) {
				continue;
			}
			nodes[i]._wenpaiAnnounced = true;
			(function (el) {
				var html = el.innerHTML;
				el.innerHTML = "";
				window.setTimeout(function () {
					el.innerHTML = html;
				}, 120);
			})(nodes[i]);
		}
	}

	/* Fade after 5 s; hover or focus inside pauses; then collapse without animation. */
	function scheduleFade(el) {
		if (el._wenpaiFade) {
			return;
		}
		el._wenpaiFade = true;
		var timer = null;
		function start() {
			window.clearTimeout(timer);
			timer = window.setTimeout(function () {
				el.classList.add("is-gone");
				window.setTimeout(function () {
					if (el.classList.contains("is-gone")) {
						el.hidden = true;
						/* Collapse an emptied kit wrapper too (it stays behind when core moves the notice). */
						var wrap = el.parentNode;
						if (wrap && wrap.classList && wrap.classList.contains("wenpai-notice-wrap") && !wrap.querySelector(".notice:not([hidden])")) {
							wrap.hidden = true;
						}
					}
				}, 450);
			}, FADE_MS);
		}
		function pause() {
			window.clearTimeout(timer);
			el.classList.remove("is-gone");
		}
		el.addEventListener("mouseenter", pause);
		el.addEventListener("focusin", pause);
		el.addEventListener("mouseleave", function () {
			if (!el.contains(document.activeElement)) {
				start();
			}
		});
		el.addEventListener("focusout", function (e) {
			if (!el.contains(e.relatedTarget) && !el.matches(":hover")) {
				start();
			}
		});
		start();
	}

	function relocateTopNotices() {
		if (!document.body || !document.body.classList.contains("wenpai-ui-page")) {
			return;
		}
		var main = document.querySelector(".wenpai-app > .wenpai-main");
		var content = document.getElementById("wpbody-content");
		if (!main || !content) {
			return;
		}
		var moved = [];
		var kids = content.children;
		for (var i = 0; i < kids.length; i++) {
			var el = kids[i];
			if (!el.classList) {
				continue;
			}
			/* Core version nag stays out of the product shell. */
			if (el.classList.contains("update-nag")) {
				continue;
			}
			if (
				el.classList.contains("notice") ||
				el.classList.contains("updated") ||
				el.classList.contains("error") ||
				el.classList.contains("fade")
			) {
				moved.push(el);
			}
		}
		for (var j = 0; j < moved.length; j++) {
			main.insertBefore(moved[j], main.firstChild);
		}
	}

	function boot(root) {
		relocateTopNotices();
		var app = root || document;
		var radios = app.querySelectorAll(".wenpai-app .choice input[type=\"radio\"]");
		for (var i = 0; i < radios.length; i++) {
			syncChoice(radios[i]);
		}
		syncDepends(app);
		var btns = app.querySelectorAll(".wenpai-app [data-wenpai-action]");
		for (var b = 0; b < btns.length; b++) {
			btns[b].hidden = false;
			syncActionReady(btns[b]);
		}
		var secrets = app.querySelectorAll(".wenpai-app .wenpai-field input[type=\"password\"]");
		for (var s = 0; s < secrets.length; s++) {
			syncSecretClear(secrets[s]);
		}
		announce(app);
		var gates = app.querySelectorAll(".wenpai-app .wenpai-gate");
		for (var g = 0; g < gates.length; g++) {
			syncGate(gates[g]);
		}
		if (reduceMotion()) {
			return;
		}
		/*
		 * The fading element is the notice itself, looked up in the whole
		 * document: on a plain .wrap page core common.js moves notices out of
		 * their container (after the h1), so a container-scoped lookup misses it.
		 */
		var fades = document.querySelectorAll(".notice[data-wenpai-fade], .wenpai-notice-wrap[data-wenpai-fade]");
		for (var w = 0; w < fades.length; w++) {
			scheduleFade(fades[w]);
		}
	}

	document.addEventListener("click", function (e) {
		var act = e.target.closest && e.target.closest(".wenpai-app [data-wenpai-action]");
		if (act) {
			e.preventDefault();
			runAction(act);
			return;
		}
		/* Error summary links: land focus on the control, not the wrapper. */
		var link = e.target.closest && e.target.closest(".wenpai-app .wenpai-error-links a[href^=\"#\"]");
		if (link) {
			var target = document.getElementById(link.getAttribute("href").slice(1));
			if (target) {
				var ctl = target.matches("input, select, textarea") ? target : target.querySelector("input:not([type=\"hidden\"]), select, textarea");
				if (ctl && !ctl.disabled) {
					e.preventDefault();
					target.scrollIntoView({ block: "center" });
					ctl.focus();
				}
			}
			return;
		}
		var btn = e.target.closest && e.target.closest(".wenpai-app button.toggle");
		if (!btn) {
			return;
		}
		if (btn.disabled || btn.classList.contains("is-locked") || btn.closest(".is-static")) {
			return;
		}
		if (btn.hasAttribute("data-wenpai-post") || btn.type === "submit") {
			return;
		}
		e.preventDefault();
		setToggle(btn, !btn.classList.contains("on"));
	});

	function onEdit(e) {
		var t = e.target;
		if (!inApp(t) || !t.name) {
			return;
		}
		if (t.type === "password" && t.closest(".wenpai-field")) {
			syncSecretClear(t);
			clearMultiline(t);
		}
		clearResultsFor(t);
	}

	document.addEventListener("input", onEdit);

	document.addEventListener("change", function (e) {
		var t = e.target;
		if (!inApp(t)) {
			return;
		}
		onEdit(e);
		if (t.name && document.querySelector(DEP_SEL)) {
			syncDepends(document, t);
		}
		if (t.matches(".choice input[type=\"radio\"]")) {
			syncChoice(t);
		}
		if (t.matches(".wenpai-gate input[type=\"checkbox\"]")) {
			var gate = t.closest(".wenpai-gate");
			if (gate) {
				syncGate(gate);
			}
		}
	});

	window.wenpaiAdmin = {
		request: request,
		runAction: runAction,
		syncDepends: syncDepends
	};

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", function () {
			boot(document);
		});
	} else {
		boot(document);
	}
})();
