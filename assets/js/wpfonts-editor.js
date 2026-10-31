(function () {
	if (!window.wp || !wp.hooks || !wp.element || !wp.blockEditor || !wp.components || !wp.data || !wp.blocks) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var addFilter = wp.hooks.addFilter;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var Button = wp.components.Button;
	var cfg = window.wpfontsEditor || {};
	var i18n = cfg.i18n || {};

	function acceptsFont(name) {
		var typography = wp.blocks.getBlockSupport(name, "typography");
		if (true === typography) {
			return true;
		}
		return !!(typography && (typography.fontFamily || typography.__experimentalFontFamily));
	}

	function addLink(doc, url, preconnect, note) {
		if (!doc || !doc.head || !url) {
			return;
		}
		var links = doc.querySelectorAll("link[rel='stylesheet']");
		var i;
		for (i = 0; i < links.length; i++) {
			if (links[i].getAttribute("href") === url) {
				return;
			}
		}
		if (note && note.indexOf("<!--") === 0) {
			var body = note.replace(/^<!--\s*/, "").replace(/\s*-->$/, "");
			doc.head.appendChild(doc.createComment(" " + body + " "));
		}
		if (preconnect) {
			var hint = doc.createElement("link");
			hint.rel = "preconnect";
			hint.href = preconnect;
			hint.setAttribute("crossorigin", "");
			doc.head.appendChild(hint);
		}
		var link = doc.createElement("link");
		link.rel = "stylesheet";
		link.href = url;
		doc.head.appendChild(link);
	}

	function ensureCss(url, preconnect, note) {
		if (!url) {
			return;
		}
		addLink(document, url, preconnect, note);
		var iframe = document.querySelector('iframe[name="editor-canvas"]');
		if (iframe && iframe.contentDocument) {
			addLink(iframe.contentDocument, url, preconnect, note);
		}
	}

	function FamilyButton(props) {
		return el(
			Button,
			{
				variant: "secondary",
				className: "wpfonts-editor-family",
				isPressed: props.current === props.stack,
				onClick: function () {
					props.onPick(props.stack, props.css || "", props.preconnect || "", props.licenseNote || "");
				}
			},
			props.name
		);
	}

	function FamilyPanel(props) {
		var attributes = props.attributes || {};
		var current = "";
		if (attributes.style && attributes.style.typography) {
			current = attributes.style.typography.fontFamily || "";
		}
		var noteState = useState("");
		var note = noteState[0];
		var setNote = noteState[1];
		var packages = cfg.packages || [];
		var cloudList = [];
		if (Object.prototype.toString.call(cfg.cloud) === "[object Array]") {
			cloudList = cfg.cloud;
		} else if (cfg.cloud && cfg.cloud.stack) {
			cloudList = [cfg.cloud];
		}

		function onPick(stack, css, preconnect, licenseNote) {
			if ("function" !== typeof props.setAttributes || !acceptsFont(props.name)) {
				setNote(i18n.unsupported || "");
				return;
			}
			var style = Object.assign({}, attributes.style || {});
			style.typography = Object.assign({}, style.typography || {}, { fontFamily: stack });
			props.setAttributes({ style: style });
			ensureCss(css, preconnect, licenseNote);
			setNote(i18n.applied || "");
		}

		var packageButtons = packages.map(function (item) {
			return el(FamilyButton, {
				key: item.stack,
				name: item.name,
				stack: item.stack,
				current: current,
				onPick: onPick
			});
		});

		return el(
			PanelBody,
			{ title: i18n.title || "", initialOpen: true },
			el("p", { className: "wpfonts-editor-note" }, i18n.intro || ""),
			note ? el("p", { className: "wpfonts-editor-note" }, note) : null,
			el("p", { className: "wpfonts-editor-note" }, i18n.packages || ""),
			packageButtons.length
				? el("div", { className: "wpfonts-editor-list" }, packageButtons)
				: el("p", { className: "wpfonts-editor-note" }, i18n.noPackages || ""),
			el("p", { className: "wpfonts-editor-note" }, i18n.cloud || ""),
			cloudList.length
				? el(
					"div",
					{ className: "wpfonts-editor-list" },
					cloudList.map(function (item) {
						return el(FamilyButton, {
							key: item.stack,
							name: item.name,
							stack: item.stack,
							css: item.css || "",
							preconnect: item.preconnect || "",
							licenseNote: item.licenseNote || "",
							current: current,
							onPick: onPick
						});
					})
				)
				: el("p", { className: "wpfonts-editor-note" }, i18n.noCloud || "")
		);
	}

	function addPanel(BlockEdit) {
		return function WpfontsBlockEdit(props) {
			return el(
				Fragment,
				null,
				el(BlockEdit, props),
				el(InspectorControls, null, el(FamilyPanel, {
					name: props.name,
					attributes: props.attributes,
					setAttributes: props.setAttributes
				}))
			);
		};
	}

	// InspectorControls only renders inside the block edit context. A plugin
	// mounted on the editor root has no mayDisplayControls flag, so WordPress
	// drops the panel.
	addFilter("editor.BlockEdit", "wpfonts/inspector-panel", addPanel);
})();
