(function () {
	if (!window.wp || !wp.plugins || !wp.element || !wp.blockEditor || !wp.components || !wp.data || !wp.blocks) {
		return;
	}

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var registerPlugin = wp.plugins.registerPlugin;
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

	function addLink(doc, url) {
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
		var link = doc.createElement("link");
		link.rel = "stylesheet";
		link.href = url;
		doc.head.appendChild(link);
	}

	function ensureCss(url) {
		if (!url) {
			return;
		}
		addLink(document, url);
		var iframe = document.querySelector('iframe[name="editor-canvas"]');
		if (iframe && iframe.contentDocument) {
			addLink(iframe.contentDocument, url);
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
					props.onPick(props.stack, props.css || "");
				}
			},
			props.name
		);
	}

	function Panel() {
		var selected = wp.data.useSelect(function (select) {
			return select("core/block-editor").getSelectedBlock();
		}, []);
		var current = "";
		if (selected && selected.attributes && selected.attributes.style && selected.attributes.style.typography) {
			current = selected.attributes.style.typography.fontFamily || "";
		}
		var noteState = useState("");
		var note = noteState[0];
		var setNote = noteState[1];
		var packages = cfg.packages || [];
		var cloud = cfg.cloud || null;

		function onPick(stack, css) {
			if (!selected) {
				return;
			}
			if (!acceptsFont(selected.name)) {
				setNote(i18n.unsupported || "");
				return;
			}
			var style = Object.assign({}, selected.attributes.style || {});
			style.typography = Object.assign({}, style.typography || {}, { fontFamily: stack });
			wp.data.dispatch("core/block-editor").updateBlockAttributes(selected.clientId, { style: style });
			ensureCss(css);
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
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: i18n.title || "", initialOpen: true },
				el("p", { className: "wpfonts-editor-note" }, i18n.intro || ""),
				note ? el("p", { className: "wpfonts-editor-note" }, note) : null,
				el("p", { className: "wpfonts-editor-note" }, i18n.packages || ""),
				packageButtons.length
					? el("div", { className: "wpfonts-editor-list" }, packageButtons)
					: el("p", { className: "wpfonts-editor-note" }, i18n.noPackages || ""),
				el("p", { className: "wpfonts-editor-note" }, i18n.cloud || ""),
				cloud && cloud.stack
					? el(
						"div",
						{ className: "wpfonts-editor-list" },
						el(FamilyButton, {
							name: cloud.name,
							stack: cloud.stack,
							css: cloud.css || "",
							current: current,
							onPick: onPick
						})
					)
					: el("p", { className: "wpfonts-editor-note" }, i18n.noCloud || "")
			)
		);
	}

	registerPlugin("wpfonts-families", {
		render: Panel
	});
})();
