(function () {
	"use strict";

	var root = document.getElementById("ed-rankings-widget");
	if (!root || typeof edRankings === "undefined") {
		return;
	}

	var torneoId = parseInt(root.getAttribute("data-torneo-id") || "0", 10);
	var tipo = root.getAttribute("data-tipo") || "goles";
	var limit = parseInt(root.getAttribute("data-limit") || "15", 10);

	function escapeHtml(s) {
		var d = document.createElement("div");
		d.textContent = s == null ? "" : String(s);
		return d.innerHTML;
	}

	function fetchJson(path) {
		return fetch(edRankings.restBase.replace(/\/$/, "") + path, {
			credentials: "same-origin",
			headers: { Accept: "application/json" },
		}).then(function (res) {
			return res.json().then(function (body) {
				if (!res.ok) {
					throw new Error(body.message || "HTTP " + res.status);
				}
				return body;
			});
		});
	}

	function renderRows(rows, title) {
		if (!rows || !rows.length) {
			return (
				'<p class="ed-rankings-widget__empty">' +
				escapeHtml(edRankings.i18nEmpty) +
				"</p>"
			);
		}
		var h = title ? "<h4>" + escapeHtml(title) + "</h4>" : "";
		h += '<ol class="ed-rankings-widget__list">';
		rows.forEach(function (r, i) {
			var name = r.jugador_nombre || r.nombre || "#" + r.jugador_id;
			h +=
				"<li><span class=\"ed-rankings-widget__pos\">" +
				(i + 1) +
				'</span><span class="ed-rankings-widget__name">' +
				escapeHtml(name) +
				'</span><span class="ed-rankings-widget__val">' +
				escapeHtml(String(r.valor != null ? r.valor : r.votos || 0)) +
				"</span></li>";
		});
		h += "</ol>";
		return h;
	}

	function load() {
		var p;
		if (torneoId > 0) {
			p = fetchJson("/torneo/" + torneoId + "/rankings").then(function (data) {
				var section = (data[tipo] || data.goles || []).slice(0, limit);
				return renderRows(section, tipo);
			});
		} else {
			var q =
				"/rankings?tipo=" +
				encodeURIComponent(tipo) +
				"&limit=" +
				encodeURIComponent(String(limit));
			p = fetchJson(q).then(function (rows) {
				return renderRows(rows, null);
			});
		}
		return p
			.then(function (html) {
				root.innerHTML = html;
			})
			.catch(function () {
				root.innerHTML =
					'<p class="ed-rankings-widget__error">' +
					escapeHtml(edRankings.i18nError) +
					"</p>";
			});
	}

	load();
})();
