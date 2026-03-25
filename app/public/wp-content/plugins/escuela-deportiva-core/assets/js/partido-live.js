(function () {
	"use strict";

	var wrap = document.getElementById("ed-partido-live");
	if (!wrap || typeof edPartidoLive === "undefined") {
		return;
	}

	var partidoId = edPartidoLive.partidoId;
	var desdeId = 0;
	var pollMs = 5000;

	function esc(s) {
		var d = document.createElement("div");
		d.textContent = s == null ? "" : String(s);
		return d.innerHTML;
	}

	function fetchPoll() {
		var url =
			edPartidoLive.restBase.replace(/\/$/, "") +
			"/partido/" +
			partidoId +
			"/eventos?desde_id=" +
			desdeId;
		return fetch(url, {
			credentials: "same-origin",
			headers: { Accept: "application/json" },
		}).then(function (res) {
			return res.json();
		});
	}

	function applyMarcador(m) {
		if (!m) {
			return;
		}
		var gl = document.getElementById("live-goles-local");
		var gv = document.getElementById("live-goles-visitante");
		var badge = document.getElementById("live-estado-badge");
		var min = document.getElementById("live-minuto");
		if (gl) {
			gl.textContent = String(
				m.goles_local != null ? m.goles_local : m.local || 0
			);
		}
		if (gv) {
			gv.textContent = String(
				m.goles_visitante != null ? m.goles_visitante : m.visitante || 0
			);
		}
		if (badge && m.estado) {
			badge.textContent = m.estado;
			badge.className =
				"ed-live-badge ed-live-badge--" + String(m.estado).replace(/\s+/g, "_");
		}
		if (min) {
			var mm =
				m.minuto_actual != null
					? m.minuto_actual
					: m.minuto != null
						? m.minuto
						: null;
			min.textContent = mm != null ? "min. " + mm : "";
		}
	}

	function appendEventos(eventos) {
		var box = document.getElementById("live-timeline");
		if (!box) {
			return;
		}
		var loading = box.querySelector(".ed-partido-live__loading");
		if (loading) {
			loading.remove();
		}
		(eventos || []).forEach(function (e) {
			var line =
				'<div class="ed-timeline__row">' +
				'<span class="ed-timeline__min">' +
				esc(e.minuto != null ? e.minuto + "′" : "—") +
				"</span>" +
				'<span class="ed-timeline__tipo">' +
				esc(e.tipo) +
				"</span>" +
				'<span class="ed-timeline__txt">' +
				esc(e.jugador_nombre || e.descripcion || "") +
				"</span>" +
				"</div>";
			box.insertAdjacentHTML("beforeend", line);
		});
	}

	function tick() {
		fetchPoll()
			.then(function (data) {
				applyMarcador(data.marcador);
				appendEventos(data.eventos);
				desdeId = data.ultimo_id || desdeId;
			})
			.catch(function () {});
	}

	tick();
	setInterval(tick, pollMs);

	var btn = document.getElementById("btn-seguir-partido");
	if (
		btn &&
		typeof window.OneSignalDeferred !== "undefined" &&
		edPartidoLive.partidoId
	) {
		btn.addEventListener("click", function () {
			window.OneSignalDeferred = window.OneSignalDeferred || [];
			window.OneSignalDeferred.push(async function (OneSignal) {
				try {
					var tags = {};
					tags["partido_" + edPartidoLive.partidoId] = "1";
					if (edPartidoLive.torneoId) {
						tags["torneo_" + edPartidoLive.torneoId] = "1";
					}
					if (OneSignal.User && OneSignal.User.addTags) {
						await OneSignal.User.addTags(tags);
					}
					btn.textContent = edPartidoLive.i18nFollowing;
					btn.disabled = true;
				} catch (err) {
					btn.disabled = false;
				}
			});
		});
	}
})();
