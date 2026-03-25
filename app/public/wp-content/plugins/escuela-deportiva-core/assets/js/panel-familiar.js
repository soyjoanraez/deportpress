(function () {
	"use strict";

	var app = document.getElementById("ed-panel-familiar-app");
	var loading = document.getElementById("ed-panel-familiar-loading");
	if (!app || typeof edPanel === "undefined") {
		return;
	}

	var jugadoresCache = [];

	if (
		typeof window.OneSignalDeferred !== "undefined" &&
		edPanel.oneSignalTags &&
		Object.keys(edPanel.oneSignalTags).length
	) {
		window.OneSignalDeferred = window.OneSignalDeferred || [];
		window.OneSignalDeferred.push(async function (OneSignal) {
			try {
				if (OneSignal.User && OneSignal.User.addTags) {
					await OneSignal.User.addTags(edPanel.oneSignalTags);
				}
			} catch (e) {
				/* OneSignal opcional */
			}
		});
	}

	function hideLoading(el) {
		if (el) {
			el.classList.add("is-hidden");
		}
	}

	function esc(s) {
		var d = document.createElement("div");
		d.textContent = s == null ? "" : String(s);
		return d.innerHTML;
	}

	function fetchJson(path, opts) {
		opts = opts || {};
		return fetch(edPanel.restBase.replace(/\/$/, "") + path, {
			credentials: "same-origin",
			method: opts.method || "GET",
			headers: Object.assign(
				{
					"X-WP-Nonce": edPanel.nonce,
					Accept: "application/json",
				},
				opts.body ? { "Content-Type": "application/json" } : {}
			),
			body: opts.body ? JSON.stringify(opts.body) : undefined,
		}).then(function (res) {
			if (res.status === 401) {
				window.location.href = edPanel.loginUrl;
				return Promise.reject(new Error("401"));
			}
			if (!res.ok) {
				return res.json().then(function (body) {
					var msg =
						body && body.message
							? body.message
							: "HTTP " + res.status;
					throw new Error(msg);
				});
			}
			return res.status === 204 ? {} : res.json();
		});
	}

	function renderCards(jugadores) {
		app.innerHTML = "";
		jugadoresCache = jugadores || [];
		fillAsistenciaSelect(jugadoresCache);
		syncCalendarioJugadorFromPanel();
		if (!jugadores || !jugadores.length) {
			app.innerHTML =
				'<p class="ed-panel-familiar__intro">' +
				esc(edPanel.i18nEmpty) +
				"</p>";
			hideLoading(loading);
			return;
		}

		jugadores.forEach(function (j) {
			var card = document.createElement("article");
			card.className = "ed-panel-familiar__card";
			var imgHtml = j.foto_url
				? '<img class="ed-panel-familiar__card-img" src="' +
				  esc(j.foto_url) +
				  '" alt="" loading="lazy" width="400" height="300">'
				: '<div class="ed-panel-familiar__card-img" aria-hidden="true"></div>';
			var dep =
				j.deporte && j.deporte.titulo ? j.deporte.titulo : "—";
			var cat =
				j.categoria && j.categoria.titulo ? j.categoria.titulo : "—";
			card.innerHTML =
				imgHtml +
				'<div class="ed-panel-familiar__card-body">' +
				'<h3 class="ed-panel-familiar__card-name">' +
				esc(j.nombre) +
				" " +
				esc(j.apellidos) +
				"</h3>" +
				'<p class="ed-panel-familiar__card-meta">' +
				esc(dep) +
				" · " +
				esc(cat) +
				"</p>" +
				'<button type="button" class="ed-panel-familiar__card-link ed-panel-familiar__js-detail" data-id="' +
				esc(String(j.id)) +
				'">' +
				esc(edPanel.i18nDetail) +
				"</button>" +
				"</div>";
			app.appendChild(card);
		});

		hideLoading(loading);
	}

	function syncCalendarioJugadorFromPanel() {
		var cal = document.getElementById("ed-calendario");
		if (!cal) {
			return;
		}
		var sel = document.getElementById("ed-asist-jugador-sel");
		var jid =
			sel && sel.value
				? sel.value
				: jugadoresCache[0] && jugadoresCache[0].id
					? String(jugadoresCache[0].id)
					: "";
		if (jid) {
			cal.setAttribute("data-jugador-id", jid);
		} else {
			cal.setAttribute("data-jugador-id", "");
		}
		if (typeof window.edCalendarioRefresh === "function") {
			window.edCalendarioRefresh();
		}
	}

	function fillAsistenciaSelect(jugadores) {
		var sel = document.getElementById("ed-asist-jugador-sel");
		if (!sel) {
			return;
		}
		sel.innerHTML = "";
		jugadores.forEach(function (j) {
			var o = document.createElement("option");
			o.value = String(j.id);
			o.textContent = (j.nombre || "") + " " + (j.apellidos || "");
			sel.appendChild(o);
		});
	}

	function formatFecha(str) {
		if (!str) {
			return "—";
		}
		var d = new Date(str);
		if (isNaN(d.getTime())) {
			return String(str).slice(0, 10);
		}
		return d.toLocaleDateString("ca-ES", {
			day: "2-digit",
			month: "2-digit",
			year: "numeric",
		});
	}

	function renderPagos(plazos) {
		var cont = document.getElementById("ed-pagos-lista");
		var ld = document.getElementById("ed-pagos-loading");
		if (!cont) {
			return;
		}
		hideLoading(ld);
		if (!plazos || !plazos.length) {
			cont.innerHTML =
				'<p class="ed-panel-familiar__intro">' +
				esc(edPanel.i18nPagosEmpty) +
				"</p>";
			return;
		}
		var iconos = {
			pagado: "✓",
			pendiente: "⏱",
			fallido: "✗",
			cancelado: "—",
		};
		cont.innerHTML = plazos
			.map(function (p) {
				var fact = p.factura_url
					? '<a href="' +
					  esc(p.factura_url) +
					  '" target="_blank" rel="noopener" class="ed-link-factura">' +
					  esc(edPanel.i18nFactura) +
					  "</a>"
					: "";
				var sub =
					p.estado === "pagado"
						? edPanel.i18nPagadoEl + " " + formatFecha(p.fecha_pagado)
						: edPanel.i18nPrevistoEl + " " + formatFecha(p.fecha_prevista);
				return (
					'<article class="ed-pago-card">' +
					'<div class="ed-pago-card__header">' +
					"<div><div class=\"ed-pago-card__deporte\">" +
					esc(p.deporte_nombre || "") +
					'</div><div class="ed-pago-card__plazo">' +
					esc(edPanel.i18nPlazo) +
					" " +
					p.plazo +
					" / 2</div></div>" +
					'<span class="ed-badge ed-badge--' +
					esc(p.estado) +
					'">' +
					esc(iconos[p.estado] || "") +
					" " +
					esc(p.estado) +
					"</span></div>" +
					'<div class="ed-pago-card__importe">' +
					Number(p.importe).toFixed(2) +
					" €</div>" +
					'<div class="ed-pago-card__meta">' +
					esc(sub) +
					"</div>" +
					'<div class="ed-pago-card__footer"><span class="ed-pago-card__jugador">#' +
					p.jugador_id +
					"</span>" +
					fact +
					"</div></article>"
				);
			})
			.join("");
	}

	function renderAsistencias(data) {
		var cont = document.getElementById("ed-asistencias-cont");
		if (!cont || !data) {
			return;
		}
		var iconos = {
			asistio: "✓",
			no_asistio: "✗",
			convocado: "●",
			no_convocado: "○",
		};
		var regs = (data.registros || []).slice(0, 15);
		cont.innerHTML =
			'<div class="ed-asistencia-stats">' +
			'<div class="ed-stat-card"><div class="ed-stat-card__valor">' +
			esc(String(data.pct_entrenamientos)) +
			'%</div><div class="ed-stat-card__label">' +
			esc(edPanel.i18nStatEntreno) +
			"</div></div>" +
			'<div class="ed-stat-card"><div class="ed-stat-card__valor">' +
			esc(String(data.pct_partidos)) +
			'%</div><div class="ed-stat-card__label">' +
			esc(edPanel.i18nStatPartido) +
			"</div></div></div>" +
			regs
				.map(function (a) {
					return (
						'<div class="ed-asistencia-item">' +
						'<span class="ed-asistencia-item__fecha">' +
						formatFecha(a.fecha) +
						"</span>" +
						'<span class="ed-asistencia-item__nombre">' +
						esc(a.sesion_nombre || a.tipo_sesion || "") +
						"</span>" +
						'<span class="ed-asistencia-icon ed-asistencia-icon--' +
						esc(a.estado) +
						'">' +
						esc(iconos[a.estado] || "?") +
						"</span></div>"
					);
				})
				.join("");
	}

	var pagosLoaded = false;
	var asistLoadedId = null;
	var entradasLoaded = false;
	var barPedidosLoaded = false;
	var mensajesLoaded = false;

	function actualizarBadgeMensajes() {
		fetchJson("/mensajes/no-leidos")
			.then(function (data) {
				var badge = document.getElementById("ed-badge-mensajes");
				if (!badge) {
					return;
				}
				var n = data && typeof data.count === "number" ? data.count : 0;
				if (n > 0) {
					badge.textContent = n > 99 ? "99+" : String(n);
					badge.classList.add("is-visible");
					badge.setAttribute("aria-hidden", "false");
				} else {
					badge.textContent = "";
					badge.classList.remove("is-visible");
					badge.setAttribute("aria-hidden", "true");
				}
			})
			.catch(function () {});
	}

	function renderMensajes(mensajes) {
		var cont = document.getElementById("ed-mensajes-lista");
		if (!cont) {
			return;
		}
		if (!mensajes || !mensajes.length) {
			cont.innerHTML =
				'<p class="ed-vacio">' + esc(edPanel.i18nMsgEmpty) + "</p>";
			return;
		}
		cont.innerHTML = mensajes
			.map(function (m) {
				var nuevo = !m.leido
					? '<span class="ed-badge-nuevo">' + esc(edPanel.i18nMsgNew) + "</span>"
					: "";
				var cardClass = m.leido ? "ed-mensaje-card" : "ed-mensaje-card ed-mensaje-card--nuevo";
				return (
					'<article class="' +
					cardClass +
					'" data-id="' +
					esc(String(m.id)) +
					'">' +
					'<div class="ed-mensaje-card__header">' +
					'<strong class="ed-mensaje-card__asunto">' +
					esc(m.asunto) +
					"</strong>" +
					nuevo +
					"</div>" +
					'<div class="ed-mensaje-card__meta">' +
					"<span>👤 " +
					esc(m.remitente || "—") +
					"</span> <span>🕐 " +
					esc(formatFecha(m.creado_en)) +
					"</span></div>" +
					'<div class="ed-mensaje-card__cuerpo is-hidden" id="msg-cuerpo-' +
					esc(String(m.id)) +
					'"></div>' +
					'<button type="button" class="ed-mensaje-card__btn" data-ed-toggle-msg="' +
					esc(String(m.id)) +
					'">' +
					esc(edPanel.i18nMsgView) +
					"</button></article>"
				);
			})
			.join("");
		mensajes.forEach(function (m) {
			var el = document.getElementById("msg-cuerpo-" + m.id);
			if (el && m.cuerpo) {
				el.innerHTML = m.cuerpo;
			}
		});
	}

	function loadMensajes() {
		var cont = document.getElementById("ed-mensajes-lista");
		var ld = document.getElementById("ed-mensajes-loading");
		if (!cont) {
			return;
		}
		if (ld) {
			ld.classList.remove("is-hidden");
		}
		fetchJson("/mensajes?limit=20")
			.then(function (rows) {
				mensajesLoaded = true;
				if (ld) {
					ld.classList.add("is-hidden");
				}
				renderMensajes(rows);
				actualizarBadgeMensajes();
			})
			.catch(function () {
				if (ld) {
					ld.classList.add("is-hidden");
				}
				cont.innerHTML =
					'<div class="ed-panel-familiar__error" role="alert">' +
					esc(edPanel.i18nError) +
					"</div>";
			});
	}

	var mensajesLista = document.getElementById("ed-mensajes-lista");
	if (mensajesLista) {
		mensajesLista.addEventListener("click", function (e) {
			var btn = e.target.closest("[data-ed-toggle-msg]");
			if (!btn || !mensajesLista.contains(btn)) {
				return;
			}
			var id = btn.getAttribute("data-ed-toggle-msg");
			var cuerpo = document.getElementById("msg-cuerpo-" + id);
			if (!cuerpo) {
				return;
			}
			var hidden = cuerpo.classList.contains("is-hidden");
			if (hidden) {
				cuerpo.classList.remove("is-hidden");
				btn.textContent = edPanel.i18nMsgHide;
			} else {
				cuerpo.classList.add("is-hidden");
				btn.textContent = edPanel.i18nMsgView;
			}
		});
	}

	function loadPagos() {
		var ld = document.getElementById("ed-pagos-loading");
		if (ld) {
			ld.classList.remove("is-hidden");
		}
		fetchJson("/pagos")
			.then(function (plazos) {
				renderPagos(plazos);
				pagosLoaded = true;
			})
			.catch(function () {
				hideLoading(ld);
				var cont = document.getElementById("ed-pagos-lista");
				if (cont) {
					cont.innerHTML =
						'<div class="ed-panel-familiar__error" role="alert">' +
						esc(edPanel.i18nError) +
						"</div>";
				}
			});
	}

	function loadEntradas() {
		var cont = document.getElementById("ed-entradas-lista");
		var ld = document.getElementById("ed-entradas-loading");
		if (!cont) {
			return;
		}
		if (ld) {
			ld.classList.remove("is-hidden");
		}
		fetchJson("/entradas")
			.then(function (rows) {
				entradasLoaded = true;
				if (ld) {
					ld.classList.add("is-hidden");
				}
				if (!rows || !rows.length) {
					cont.innerHTML =
						'<p class="ed-panel-familiar__intro">' +
						esc(edPanel.i18nEntradasEmpty) +
						"</p>";
					return;
				}
				cont.innerHTML = rows
					.map(function (e) {
						var rifa =
							e.numeros_rifa && e.numeros_rifa.length
								? '<div class="ed-entrada-mini__meta">' +
								  esc(e.numeros_rifa.join(", ")) +
								  "</div>"
								: "";
						var img = e.qr_url
							? '<img src="' +
							  esc(e.qr_url) +
							  '" alt="QR" class="ed-entrada-mini__qr" width="120" height="120" loading="lazy">'
							: "";
						return (
							'<article class="ed-entrada-mini">' +
							img +
							'<div><strong>' +
							esc(e.torneo || "") +
							'</strong><div class="ed-entrada-mini__meta">' +
							esc(e.estado) +
							" · " +
							Number(e.precio).toFixed(2) +
							" €</div>" +
							rifa +
							"</div></article>"
						);
					})
					.join("");
			})
			.catch(function () {
				if (ld) {
					ld.classList.add("is-hidden");
				}
				cont.innerHTML =
					'<div class="ed-panel-familiar__error" role="alert">' +
					esc(edPanel.i18nError) +
					"</div>";
			});
	}

	function loadBarPedidos() {
		var cont = document.getElementById("ed-bar-mis-pedidos");
		var ld = document.getElementById("ed-bar-loading");
		if (!cont) {
			return;
		}
		if (ld) {
			ld.classList.remove("is-hidden");
		}
		fetchJson("/bar/mis-pedidos")
			.then(function (rows) {
				barPedidosLoaded = true;
				if (ld) {
					ld.classList.add("is-hidden");
				}
				if (!rows || !rows.length) {
					cont.innerHTML =
						'<p class="ed-panel-familiar__intro">' +
						esc(edPanel.i18nBarEmpty) +
						"</p>";
					return;
				}
				cont.innerHTML = rows
					.map(function (p) {
						return (
							'<article class="ed-bar-pedido-mini">' +
							"<strong>#" +
							esc(String(p.id)) +
							"</strong> " +
							esc(p.torneo_nombre || "") +
							'<div class="ed-entrada-mini__meta">' +
							esc(p.franja_horaria || "") +
							" · " +
							esc(p.estado || "") +
							" · " +
							Number(p.total).toFixed(2) +
							" €</div></article>"
						);
					})
					.join("");
			})
			.catch(function () {
				if (ld) {
					ld.classList.add("is-hidden");
				}
				cont.innerHTML =
					'<div class="ed-panel-familiar__error" role="alert">' +
					esc(edPanel.i18nError) +
					"</div>";
			});
	}

	function loadAsistencias(jugadorId) {
		var ld = document.getElementById("ed-asist-loading");
		var cont = document.getElementById("ed-asistencias-cont");
		if (!jugadorId || !cont) {
			return;
		}
		if (ld) {
			ld.classList.remove("is-hidden");
		}
		fetchJson("/asistencias/jugador/" + encodeURIComponent(jugadorId))
			.then(function (data) {
				renderAsistencias(data);
				asistLoadedId = jugadorId;
				hideLoading(ld);
			})
			.catch(function () {
				hideLoading(ld);
				cont.innerHTML =
					'<div class="ed-panel-familiar__error" role="alert">' +
					esc(edPanel.i18nError) +
					"</div>";
			});
	}

	document.querySelectorAll(".ed-panel-familiar__tab").forEach(function (tab) {
		tab.addEventListener("click", function () {
			var pane = tab.getAttribute("data-pane");
			document.querySelectorAll(".ed-panel-familiar__tab").forEach(function (t) {
				t.classList.toggle("is-active", t === tab);
				t.setAttribute("aria-selected", t === tab ? "true" : "false");
			});
			document.querySelectorAll(".ed-panel-familiar__pane").forEach(function (p) {
				var show = p.id === "ed-pane-" + pane;
				p.hidden = !show;
				p.classList.toggle("is-active", show);
			});
			if (pane === "pagos" && !pagosLoaded) {
				loadPagos();
			}
			if (pane === "asistencias") {
				var sel = document.getElementById("ed-asist-jugador-sel");
				var jid = sel && sel.value ? sel.value : null;
				if (jid && jid !== String(asistLoadedId)) {
					loadAsistencias(jid);
				} else if (jid && !asistLoadedId) {
					loadAsistencias(jid);
				}
			}
			if (pane === "entradas" && !entradasLoaded) {
				loadEntradas();
			}
			if (pane === "bar" && !barPedidosLoaded) {
				loadBarPedidos();
			}
			if (pane === "calendario") {
				syncCalendarioJugadorFromPanel();
			}
			if (pane === "mensajes") {
				if (!mensajesLoaded) {
					loadMensajes();
				} else {
					actualizarBadgeMensajes();
				}
			}
		});
	});

	var selAsist = document.getElementById("ed-asist-jugador-sel");
	if (selAsist) {
		selAsist.addEventListener("change", function () {
			loadAsistencias(selAsist.value);
			var cal = document.getElementById("ed-calendario");
			if (cal && selAsist.value) {
				cal.setAttribute("data-jugador-id", selAsist.value);
			}
			if (typeof window.edCalendarioRefresh === "function") {
				window.edCalendarioRefresh();
			}
		});
	}

	app.addEventListener("click", function (e) {
		var btn = e.target.closest(".ed-panel-familiar__js-detail");
		if (!btn || !app.contains(btn)) {
			return;
		}
		var id = btn.getAttribute("data-id");
		if (!id) {
			return;
		}
		e.preventDefault();
		var existing = app.querySelector(".ed-panel-familiar__detail");
		if (existing) {
			existing.remove();
		}
		fetchJson("/jugador/" + encodeURIComponent(id))
			.then(function (data) {
				var wrap = document.createElement("div");
				wrap.className = "ed-panel-familiar__detail";
				var eq =
					data.equipo && data.equipo.titulo
						? data.equipo.titulo
						: "—";
				wrap.innerHTML =
					"<h4>" +
					esc(data.nombre) +
					" " +
					esc(data.apellidos) +
					"</h4>" +
					"<dl>" +
					"<dt>" +
					esc(edPanel.i18nBirth) +
					"</dt><dd>" +
					esc(data.fecha_nacimiento || "—") +
					"</dd>" +
					"<dt>" +
					esc(edPanel.i18nPosition) +
					"</dt><dd>" +
					esc(data.posicion || "—") +
					"</dd>" +
					"<dt>" +
					esc(edPanel.i18nDorsal) +
					"</dt><dd>" +
					esc(data.dorsal || "—") +
					"</dd>" +
					"<dt>" +
					esc(edPanel.i18nTeam) +
					"</dt><dd>" +
					esc(eq) +
					"</dd>" +
					"<dt>" +
					esc(edPanel.i18nState) +
					"</dt><dd>" +
					esc(data.estado || "—") +
					"</dd>" +
					"</dl>";
				app.appendChild(wrap);
				wrap.scrollIntoView({ behavior: "smooth", block: "nearest" });
			})
			.catch(function () {});
	});

	actualizarBadgeMensajes();

	fetchJson("/nucleo/jugadores")
		.then(renderCards)
		.catch(function () {
			hideLoading(loading);
			app.innerHTML =
				'<div class="ed-panel-familiar__error" role="alert">' +
				esc(edPanel.i18nError) +
				"</div>";
		});
})();
