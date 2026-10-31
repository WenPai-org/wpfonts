(function () {
	if (!window.wp || !wp.plugins || !wp.element || !wp.blockEditor || !wp.components || !wp.data || !wp.blocks) {
		return;
	}

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var registerPlugin = wp.plugins.registerPlugin;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var Button = wp.components.Button;
	var TextControl = wp.components.TextControl;
	var cfg = window.wpfontsEditor || {};
	var i18n = cfg.i18n || {};
	var cdn = cfg.cdn || {};
	var requestSerial = 0;
	var searchTimer = 0;

	function acceptsFont(name) {
		var typography = wp.blocks.getBlockSupport(name, "typography");
		if (true === typography) {
			return true;
		}
		return !!(typography && (typography.fontFamily || typography.__experimentalFontFamily));
	}

	function addLink(doc, url) {
		if (!doc || !doc.head) {
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

	function cloudUrl(stack) {
		if (!cdn.ready || !cdn.host) {
			return "";
		}
		var match = /wenfeng-[a-z0-9-]+/.exec(stack || "");
		if (!match) {
			return "";
		}
		return "https://" + cdn.host + "/api/css?family=" + encodeURIComponent(match[0]) + "&subset=" + encodeURIComponent(cdn.subset || "zh-common");
	}

	function ensureCloudCss(stack) {
		var url = cloudUrl(stack);
		if (!url) {
			return;
		}
		addLink(document, url);
		var iframe = document.querySelector('iframe[name="editor-canvas"]');
		if (iframe && iframe.contentDocument) {
			addLink(iframe.contentDocument, url);
		}
	}

	function FamilyList(props) {
		if (!props.items || !props.items.length) {
			return el("p", { className: "wpfonts-editor-note" }, props.empty);
		}
		return el(
			"div",
			{ className: "wpfonts-editor-list" },
			props.items.map(function (item) {
				return el(
					Button,
					{
						key: item.stack,
						variant: "secondary",
						className: "wpfonts-editor-family",
						isPressed: props.current === item.stack,
						onClick: function () {
							props.onPick(item.stack, props.cloud);
						}
					},
					item.name
				);
			})
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

		var queryState = useState("");
		var query = queryState[0];
		var setQuery = queryState[1];
		var packageState = useState(cfg.packages || []);
		var packages = packageState[0];
		var setPackages = packageState[1];
		var fontState = useState([]);
		var fonts = fontState[0];
		var setFonts = fontState[1];
		var metaState = useState({ total: 0, truncated: false, failed: false });
		var meta = metaState[0];
		var setMeta = metaState[1];
		var noteState = useState("");
		var note = noteState[0];
		var setNote = noteState[1];

		function load(value) {
			if (!wp.apiFetch) {
				setMeta({ total: 0, truncated: false, failed: true });
				return;
			}
			requestSerial += 1;
			var serial = requestSerial;
			wp.apiFetch({ path: "/wpfonts/v1/families?search=" + encodeURIComponent(value || "") })
				.then(function (data) {
					if (serial !== requestSerial || !data) {
						return;
					}
					setPackages(data.packages || []);
					var rows = [];
					(data.fonts || []).forEach(function (font) {
						var family = font && font.fontFamily ? String(font.fontFamily) : "";
						if (!/^wenfeng-[a-z0-9-]+$/.test(family)) {
							return;
						}
						rows.push({
							name: font.name || family,
							stack: '"' + family + '", sans-serif'
						});
					});
					setFonts(rows);
					setMeta({
						total: data.total || 0,
						truncated: !!data.truncated,
						failed: false
					});
				})
				.catch(function () {
					if (serial !== requestSerial) {
						return;
					}
					setMeta({ total: 0, truncated: false, failed: true });
				});
		}

		useEffect(function () {
			load("");
		}, []);

		function onSearch(value) {
			setQuery(value);
			window.clearTimeout(searchTimer);
			searchTimer = window.setTimeout(function () {
				load(value);
			}, 250);
		}

		function onPick(stack, isCloud) {
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
			if (isCloud) {
				ensureCloudCss(stack);
				setNote(cdn.ready ? (i18n.applied || "") : (i18n.cdnOff || ""));
				return;
			}
			setNote(i18n.applied || "");
		}

		var cloudEmpty = i18n.noCatalog || "";
		if (meta.failed) {
			cloudEmpty = i18n.fail || cloudEmpty;
		} else if (query && !fonts.length) {
			cloudEmpty = i18n.emptySearch || cloudEmpty;
		}

		return el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: i18n.title || "", initialOpen: true },
				el("p", { className: "wpfonts-editor-note" }, i18n.intro || ""),
				note ? el("p", { className: "wpfonts-editor-note" }, note) : null,
				el("p", { className: "wpfonts-editor-note" }, i18n.packages || ""),
				el(FamilyList, {
					items: packages,
					empty: i18n.noPackages || "",
					current: current,
					cloud: false,
					onPick: onPick
				}),
				el("p", { className: "wpfonts-editor-note" }, i18n.cloud || ""),
				el(TextControl, {
					label: i18n.search || "",
					value: query,
					onChange: onSearch,
					__nextHasNoMarginBottom: true
				}),
				el(FamilyList, {
					items: fonts,
					empty: cloudEmpty,
					current: current,
					cloud: true,
					onPick: onPick
				}),
				meta.truncated ? el("p", { className: "wpfonts-editor-note" }, i18n.more || "") : null
			)
		);
	}

	registerPlugin("wpfonts-families", {
		render: Panel
	});
})();
