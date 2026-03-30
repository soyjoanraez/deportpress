/**
 * Calendari setmanal (REST ed/v1/calendario/semana).
 *
 * @package escuela-deportiva-core
 */
(function () {
	"use strict";

	var state = {
		container: null,
		fechaRef: new Date(),
	};

	function cfg() {
		var c = window.edCalendario || {};
		var p = window.edPanel || {};
		return {
			restBase: String(c.restBase || p.restBase || "").replace(/\/$/, ""),
			nonce: c.nonce || p.nonce || "",
			i18nError: c.i18nError || "Error de càrrega.",
			i18nLoading: c.i18nLoading || "Carregant…",
			i18nLegEnt: c.i18nLegEnt || "Entrenament",
			i18nLegTor: c.i18nLegTor || "Torneig",
			i18nLegLiga: c.i18nLegLiga || "Lliga",
		};
	}

	function localYmd(d) {
		var y = d.getFullYear();
		var m = String(d.getMonth() + 1).padStart(2, "0");
		var day = String(d.getDate()).padStart(2, "0");
		return y + "-" + m + "-" + day;
	}

	function parseYmdLocal(ymd) {
		var p = String(ymd).split("-");
		var y = parseInt(p[0], 10);
		var mo = parseInt(p[1], 10) - 1;
		var da = parseInt(p[2], 10);
		return new Date(y, mo, da);
	}

	function diaNombreCa(d) {
		var n = d.getDay();
		var idx = n === 0 ? 6 : n - 1;
		return ["Dl", "Dt", "Dc", "Dj", "Dv", "Ds", "Dg"][idx];
	}

	function escAttr(s) {
		return String(s == null ? "" : s)
			.replace(/&/g, "&amp;")
			.replace(/"/g, "&quot;")
			.replace(/</g, "&lt;")
			.replace(/>/g, "&gt;");
	}

	function escHtml(s) {
		var d = document.createElement("div");
		d.textContent = s == null ? "" : String(s);
		return d.innerHTML;
	}

	var ICONOS = {
		entrenamiento: "🏃",
		partido_torneo: "⚽",
		partido_liga: "🏆",
	};

	var COLORES = {
		entrenamiento: "#E3F2FD",
		partido_torneo: "#FFF3E0",
		partido_liga: "#E8F5E9",
	};

	function formatRango(inicio, fin) {
		var a = parseYmdLocal(inicio);
		var b = parseYmdLocal(fin);
		var o = { day: "2-digit", month: "short" };
		return (
			a.toLocaleDateString("ca-ES", o) + " — " + b.toLocaleDateString("ca-ES", o)
		);
	}

	function fetchSemana() {
		var el = state.container;
		if (!el) {
			return;
		}
		var cf = cfg();
		if (!cf.restBase) {
			el.innerHTML =
				'<p class="ed-cal-widget__error" role="alert">' +
				escHtml(cf.i18nError) +
				"</p>";
			return;
		}

		var fechaStr = localYmd(state.fechaRef);
		var params = new URLSearchParams({ fecha: fechaStr });
		var jid = el.getAttribute("data-jugador-id");
		var cid = el.getAttribute("data-categoria-id");
		if (jid && String(jid).trim() !== "" && String(jid) !== "0") {
			params.set("jugador_id", String(jid).trim());
		}
		if (cid && String(cid).trim() !== "" && String(cid) !== "0") {
			params.set("categoria_id", String(cid).trim());
		}

		var headers = { Accept: "application/json" };
		if (cf.nonce) {
			headers["X-WP-Nonce"] = cf.nonce;
		}

		el.innerHTML =
			'<p class="ed-cal-widget__loading">' + escHtml(cf.i18nLoading) + "</p>";

		fetch(cf.restBase + "/calendario/semana?" + params.toString(), {
			credentials: "same-origin",
			headers: headers,
		})
			.then(function (res) {
				if (!res.ok) {
					throw new Error("HTTP " + res.status);
				}
				return res.json();
			})
			.then(function (data) {
				renderSemana(el, data);
			})
			.catch(function () {
				el.innerHTML =
					'<p class="ed-cal-widget__error" role="alert">' +
					escHtml(cf.i18nError) +
					"</p>";
			});
	}

	function renderSemana(cont, data) {
		var cf = cfg();
		var inicio = data.semana_inicio;
		var fin = data.semana_fin;
		var eventos = data.eventos || [];

		var eventosMap = {};
		eventos.forEach(function (e) {
			var fh = e.fecha_hora || "";
			var dia = fh.split(" ")[0];
			if (!eventosMap[dia]) {
				eventosMap[dia] = [];
			}
			eventosMap[dia].push(e);
		});

		var hoyStr = localYmd(new Date());
		var diasHtml = "";
		var d0 = parseYmdLocal(inicio);
		for (var i = 0; i < 7; i++) {
			var d = new Date(d0.getFullYear(), d0.getMonth(), d0.getDate() + i);
			var key = localYmd(d);
			var hoy = key === hoyStr;
			var events = eventosMap[key] || [];
			diasHtml +=
				'<div class="ed-cal__dia' +
				(hoy ? " ed-cal__dia--hoy" : "") +
				'">' +
				'<div class="ed-cal__dia-header">' +
				'<span class="ed-cal__dia-nombre">' +
				escHtml(diaNombreCa(d)) +
				"</span>" +
				'<span class="ed-cal__dia-num">' +
				escHtml(String(d.getDate())) +
				"</span>" +
				"</div>" +
				'<div class="ed-cal__dia-eventos">' +
				events.map(renderEvento).join("") +
				"</div></div>";
		}

		cont.innerHTML =
			'<div class="ed-cal__nav">' +
			'<button type="button" class="ed-cal__nav-btn" data-cal-prev aria-label="Setmana anterior">‹</button>' +
			'<span class="ed-cal__nav-label">' +
			escHtml(formatRango(inicio, fin)) +
			"</span>" +
			'<button type="button" class="ed-cal__nav-btn" data-cal-next aria-label="Setmana següent">›</button>' +
			"</div>" +
			'<div class="ed-cal__semana">' +
			diasHtml +
			"</div>" +
			'<div class="ed-cal__leyenda">' +
			'<span style="background:#E3F2FD">🏃 ' + escHtml(cf.i18nLegEnt) + "</span>" +
			'<span style="background:#FFF3E0">⚽ ' + escHtml(cf.i18nLegTor) + "</span>" +
			'<span style="background:#E8F5E9">🏆 ' + escHtml(cf.i18nLegLiga) + "</span>" +
			"</div>";

		cont.querySelector("[data-cal-prev]").addEventListener("click", function () {
			state.fechaRef.setDate(state.fechaRef.getDate() - 7);
			fetchSemana();
		});
		cont.querySelector("[data-cal-next]").addEventListener("click", function () {
			state.fechaRef.setDate(state.fechaRef.getDate() + 7);
			fetchSemana();
		});
	}

	function renderEvento(e) {
		var tipo = e.tipo || "";
		var bg = COLORES[tipo] || "#f5f5f5";
		var icon = ICONOS[tipo] || "•";
		var fh = e.fecha_hora || "";
		var hora = fh.split(" ")[1] ? fh.split(" ")[1].slice(0, 5) : "";
		var marc = e.marcador
			? '<span class="ed-cal__evento-marcador">' + escHtml(e.marcador) + "</span>"
			: "";
		var inner =
			'<span class="ed-cal__evento-hora">' +
			escHtml(hora) +
			"</span>" +
			'<span class="ed-cal__evento-icono">' +
			icon +
			"</span>" +
			'<span class="ed-cal__evento-titulo">' +
			escHtml(e.titulo || "") +
			"</span>" +
			marc;
		if (e.url) {
			return (
				'<a class="ed-cal__evento" style="background:' +
				escAttr(bg) +
				';" href="' +
				escAttr(e.url) +
				'">' +
				inner +
				"</a>"
			);
		}
		return (
			'<div class="ed-cal__evento" style="background:' +
			escAttr(bg) +
			';">' +
			inner +
			"</div>"
		);
	}

	function mount(container) {
		if (!container) {
			return;
		}
		state.container = container;
		state.fechaRef = new Date();
		fetchSemana();
	}

	document.addEventListener("DOMContentLoaded", function () {
		var c = document.getElementById("ed-calendario");
		if (c) {
			mount(c);
		}
	});

	window.edCalendarioRefresh = function () {
		var c = document.getElementById("ed-calendario");
		if (!c) {
			return;
		}
		state.container = c;
		fetchSemana();
	};
})();
