(function () {
	"use strict";

	if (typeof edMsgForm === "undefined") {
		return;
	}

	if (edMsgForm.coachOnly) {
		var tipoCoach = document.getElementById("msg-tipo-destino");
		if (tipoCoach) {
			["club", "nucleo"].forEach(function (val) {
				var opt = tipoCoach.querySelector('option[value="' + val + '"]');
				if (opt) {
					opt.remove();
				}
			});
		}
	}

	var api = edMsgForm.restBase.replace(/\/$/, "");
	var headers = {
		"Content-Type": "application/json",
		"X-WP-Nonce": edMsgForm.nonce,
		Accept: "application/json",
	};

	function esc(s) {
		var d = document.createElement("div");
		d.textContent = s == null ? "" : String(s);
		return d.innerHTML;
	}

	function fetchJson(path, opts) {
		opts = opts || {};
		return fetch(api + path, {
			credentials: "same-origin",
			method: opts.method || "GET",
			headers: Object.assign({}, headers, opts.extraHeaders || {}),
			body: opts.body ? JSON.stringify(opts.body) : undefined,
		}).then(function (res) {
			if (!res.ok) {
				return res.json().then(function (body) {
					var msg =
						body && body.message ? body.message : "HTTP " + res.status;
					throw new Error(msg);
				});
			}
			return res.status === 204 ? {} : res.json();
		});
	}

	var tipoSel = document.getElementById("msg-tipo-destino");
	var destWrap = document.getElementById("msg-destino-selector");
	var destSel = document.getElementById("msg-destino-id");
	var btn = document.getElementById("msg-btn-enviar");
	var resultado = document.getElementById("msg-resultado");
	var hist = document.getElementById("ed-msg-historial-lista");

	var labels = {
		deporte: edMsgForm.labelDeporte,
		categoria: edMsgForm.labelCategoria,
		nucleo: edMsgForm.labelNucleo,
		jugador: edMsgForm.labelJugador,
	};

	if (tipoSel && destWrap && destSel) {
		tipoSel.addEventListener("change", function () {
			var tipo = tipoSel.value;
			if (tipo === "club") {
				destWrap.hidden = true;
				return;
			}
			destWrap.hidden = false;
			destSel.innerHTML =
				'<option value="">' + esc(edMsgForm.loading) + "</option>";
			fetchJson("/mensajes/destinos?tipo=" + encodeURIComponent(tipo))
				.then(function (rows) {
					var lab = labels[tipo] || "—";
					destSel.innerHTML =
						'<option value="">' + esc(lab) + "</option>" +
						(rows || [])
							.map(function (item) {
								return (
									'<option value="' +
									esc(String(item.id)) +
									'">' +
									esc(item.title) +
									"</option>"
								);
							})
							.join("");
				})
				.catch(function () {
					destSel.innerHTML =
						'<option value="">' + esc(edMsgForm.loadError) + "</option>";
				});
		});
	}

	if (btn) {
		btn.addEventListener("click", function () {
			var tipo = tipoSel ? tipoSel.value : "club";
			var destino =
				tipo === "club" ? null : destSel && destSel.value ? parseInt(destSel.value, 10) : 0;
			var asuntoEl = document.getElementById("msg-asunto");
			var cuerpoEl = document.getElementById("msg-cuerpo");
			var asunto = asuntoEl ? asuntoEl.value.trim() : "";
			var cuerpo = cuerpoEl ? cuerpoEl.value.trim() : "";

			if (!asunto || !cuerpo) {
				window.alert(edMsgForm.alertIncomplete);
				return;
			}
			if (tipo !== "club" && !destino) {
				window.alert(edMsgForm.alertDestino);
				return;
			}

			btn.disabled = true;
			btn.textContent = edMsgForm.sending;

			fetchJson("/mensajes/enviar", {
				method: "POST",
				body: {
					tipo_destino: tipo,
					destino_id: tipo === "club" ? null : destino,
					asunto: asunto,
					cuerpo: cuerpo,
				},
			})
				.then(function (data) {
					resultado.hidden = false;
					if (data.ok) {
						resultado.className = "ed-msg-form__result ed-msg-form__result--ok";
						resultado.innerHTML = esc(edMsgForm.okMsg);
						if (asuntoEl) {
							asuntoEl.value = "";
						}
						if (cuerpoEl) {
							cuerpoEl.value = "";
						}
						loadHistorial();
					} else {
						resultado.className = "ed-msg-form__result ed-msg-form__result--err";
						resultado.innerHTML = esc(edMsgForm.errMsg);
					}
				})
				.catch(function () {
					resultado.hidden = false;
					resultado.className = "ed-msg-form__result ed-msg-form__result--err";
					resultado.innerHTML = esc(edMsgForm.errMsg);
				})
				.then(function () {
					btn.disabled = false;
					btn.textContent = edMsgForm.btnSend;
				});
		});
	}

	function loadHistorial() {
		if (!hist) {
			return;
		}
		fetchJson("/mensajes/enviados?limit=15")
			.then(function (rows) {
				if (!rows || !rows.length) {
					hist.innerHTML =
						'<p class="ed-vacio">' + esc(edMsgForm.histEmpty) + "</p>";
					return;
				}
				hist.innerHTML = rows
					.map(function (m) {
						return (
							'<article class="ed-msg-hist-item"><strong>' +
							esc(m.asunto) +
							"</strong><div class=\"ed-msg-hist-item__meta\">" +
							esc(m.creado_en) +
							" · " +
							esc(m.tipo_destino) +
							" · " +
							esc(String(m.total_leidos)) +
							"/" +
							esc(String(m.total_receptores)) +
							" " +
							esc(edMsgForm.readLabel) +
							"</div></article>"
						);
					})
					.join("");
			})
			.catch(function () {
				hist.innerHTML =
					'<p class="ed-vacio">' + esc(edMsgForm.histError) + "</p>";
			});
	}

	loadHistorial();
})();
