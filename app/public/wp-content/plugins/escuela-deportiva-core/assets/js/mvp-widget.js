(function () {
	"use strict";

	var root = document.getElementById("ed-mvp-widget");
	if (!root || typeof edMvp === "undefined") {
		return;
	}

	var partidoId = parseInt(root.getAttribute("data-partido-id") || "0", 10);
	if (!partidoId) {
		root.innerHTML =
			'<p class="ed-mvp-widget__msg ed-mvp-widget__msg--warn">' +
			escapeHtml(edMvp.i18nNoPartido) +
			"</p>";
		return;
	}

	function escapeHtml(s) {
		var d = document.createElement("div");
		d.textContent = s == null ? "" : String(s);
		return d.innerHTML;
	}

	function rest(path, opts) {
		opts = opts || {};
		return fetch(edMvp.restBase.replace(/\/$/, "") + path, {
			credentials: "same-origin",
			method: opts.method || "GET",
			headers: Object.assign(
				{
					Accept: "application/json",
					"X-WP-Nonce": edMvp.nonce,
				},
				opts.body ? { "Content-Type": "application/json" } : {}
			),
			body: opts.body ? JSON.stringify(opts.body) : undefined,
		}).then(function (res) {
			return res.json().then(function (body) {
				if (!res.ok) {
					throw new Error(
						(body && (body.message || body.error)) || edMvp.i18nError
					);
				}
				return body;
			});
		});
	}

	function mergeRows(candidatos, resultados) {
		var byId = {};
		(candidatos || []).forEach(function (c) {
			byId[c.jugador_id] = {
				jugador_id: c.jugador_id,
				nombre: c.nombre || "",
				foto: c.foto || null,
				equipo: c.equipo || "",
				votos: 0,
				porcentaje: 0,
			};
		});
		(resultados || []).forEach(function (r) {
			if (!byId[r.jugador_id]) {
				byId[r.jugador_id] = {
					jugador_id: r.jugador_id,
					nombre: r.nombre || "",
					foto: r.foto || null,
					equipo: "",
					votos: 0,
					porcentaje: 0,
				};
			}
			byId[r.jugador_id].votos = r.votos;
			byId[r.jugador_id].porcentaje = r.porcentaje;
			if (r.nombre) {
				byId[r.jugador_id].nombre = r.nombre;
			}
			if (r.foto) {
				byId[r.jugador_id].foto = r.foto;
			}
		});
		return Object.keys(byId).map(function (k) {
			return byId[k];
		});
	}

	function render(data) {
		var html = "";
		if (data.mvp_asignado) {
			html +=
				'<p class="ed-mvp-widget__mvp">' +
				escapeHtml("MVP: #" + data.mvp_asignado) +
				"</p>";
		}
		if (!data.abierta) {
			html +=
				'<p class="ed-mvp-widget__msg">' +
				escapeHtml(edMvp.i18nClosed) +
				"</p>";
		}
		var rows = data.abierta
			? mergeRows(data.candidatos, data.resultados)
			: data.resultados || [];
		if (!rows.length && data.abierta) {
			html +=
				'<p class="ed-mvp-widget__msg">' +
				escapeHtml(edMvp.i18nNoCand) +
				"</p>";
		}
		html += '<ul class="ed-mvp-widget__list">';
		rows.forEach(function (row) {
			html += '<li class="ed-mvp-widget__item">';
			if (row.foto) {
				html +=
					'<img class="ed-mvp-widget__foto" src="' +
					escapeHtml(row.foto) +
					'" alt="" width="48" height="48" loading="lazy">';
			}
			html += '<div class="ed-mvp-widget__meta">';
			html +=
				'<span class="ed-mvp-widget__nombre">' +
				escapeHtml(row.nombre) +
				"</span>";
			if (row.equipo) {
				html +=
					'<span class="ed-mvp-widget__equipo">' +
					escapeHtml(row.equipo) +
					"</span>";
			}
			html +=
				'<span class="ed-mvp-widget__stats">' +
				(row.votos || 0) +
				" vots · " +
				(row.porcentaje || 0) +
				"%</span>";
			html += "</div>";
			if (data.abierta) {
				html +=
					'<button type="button" class="ed-mvp-widget__vote" data-jugador-id="' +
					row.jugador_id +
					'">Votar</button>';
			}
			html += "</li>";
		});
		html += "</ul>";
		if (data.abierta && data.segundos_resto > 0) {
			html +=
				'<p class="ed-mvp-widget__timer" data-seconds="' +
				data.segundos_resto +
				'"></p>';
		}
		root.innerHTML = html;

		if (data.abierta) {
			root.querySelectorAll(".ed-mvp-widget__vote").forEach(function (btn) {
				btn.addEventListener("click", function () {
					var jid = parseInt(btn.getAttribute("data-jugador-id") || "0", 10);
					btn.disabled = true;
					rest("/partido/" + partidoId + "/mvp/votar", {
						method: "POST",
						body: { jugador_id: jid },
					})
						.then(function (res) {
							if (res.ok) {
								alert(edMvp.i18nVoted);
								return load();
							}
							throw new Error(res.error || edMvp.i18nVoteErr);
						})
						.catch(function () {
							btn.disabled = false;
							alert(edMvp.i18nVoteErr);
						});
				});
			});
		}
	}

	function load() {
		return rest("/partido/" + partidoId + "/mvp")
			.then(render)
			.catch(function () {
				root.innerHTML =
					'<p class="ed-mvp-widget__msg ed-mvp-widget__msg--warn">' +
					escapeHtml(edMvp.i18nError) +
					"</p>";
			});
	}

	load();
	setInterval(load, 15000);
})();
