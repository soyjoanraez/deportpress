(function () {
	"use strict";

	if (typeof edEscaner === "undefined") {
		return;
	}

	var api = edEscaner.restBase.replace(/\/$/, "");
	var headers = {
		"Content-Type": "application/json",
		"X-WP-Nonce": edEscaner.nonce,
		Accept: "application/json",
	};
	var escaneando = false;

	function validarToken(token) {
		token = String(token || "").trim();
		if (!token) {
			return Promise.resolve();
		}
		return fetch(api + "/entrada/validar", {
			method: "POST",
			credentials: "same-origin",
			headers: headers,
			body: JSON.stringify({ token: token }),
		})
			.then(function (res) {
				return res.json().then(function (data) {
					return { ok: res.ok, data: data };
				});
			})
			.then(function (pack) {
				mostrarResultado(pack.data, pack.ok);
				cargarStats();
			})
			.catch(function () {});
	}

	function mostrarResultado(data, httpOk) {
		var cont = document.getElementById("ed-resultado");
		if (!cont || !data) {
			return;
		}
		cont.hidden = false;
		if (data.ok) {
			var rifa =
				data.numeros_rifa && data.numeros_rifa.length
					? "<div class=\"ed-resultado__rifa\">" +
					  edEscaner.i18nRifa +
					  " <strong>" +
					  data.numeros_rifa.join(", ") +
					  "</strong></div>"
					: "";
			cont.innerHTML =
				'<div class="ed-resultado ed-resultado--ok">' +
				"<strong>" +
				(data.mensaje || "") +
				"</strong>" +
				"<div>" +
				(data.titular || "") +
				"</div>" +
				'<div class="ed-resultado__torneo">' +
				(data.torneo || "") +
				"</div>" +
				rifa +
				"</div>";
		} else {
			var cod = data.codigo || "";
			var msg =
				{
					already_used: edEscaner.i18nAlready,
					not_found: edEscaner.i18nNotFound,
					cancelled: edEscaner.i18nCancelled,
				}[cod] || data.error || "";
			var extra = data.usado_en
				? '<div class="ed-resultado__meta">' +
				  edEscaner.i18nUsedAt +
				  " " +
				  data.usado_en +
				  "</div>"
				: "";
			cont.innerHTML =
				'<div class="ed-resultado ed-resultado--err"><strong>' +
				msg +
				"</strong>" +
				extra +
				"</div>";
		}
		setTimeout(function () {
			cont.hidden = true;
		}, 4000);
	}

	function cargarStats() {
		var tid = parseInt(edEscaner.torneoId, 10);
		if (!tid) {
			return;
		}
		fetch(api + "/torneo/" + tid + "/entradas/stats", {
			credentials: "same-origin",
			headers: { "X-WP-Nonce": edEscaner.nonce, Accept: "application/json" },
		})
			.then(function (r) {
				return r.json();
			})
			.then(function (d) {
				var t = document.getElementById("stat-total");
				var u = document.getElementById("stat-usadas");
				var v = document.getElementById("stat-validas");
				if (t) {
					t.textContent = d.total != null ? String(d.total) : "—";
				}
				if (u) {
					u.textContent = d.usadas != null ? String(d.usadas) : "—";
				}
				if (v) {
					v.textContent = d.validas != null ? String(d.validas) : "—";
				}
			})
			.catch(function () {});
	}

	function iniciarCamara() {
		var wrap = document.getElementById("ed-camara-wrap");
		var video = document.getElementById("ed-camara-video");
		if (!wrap || !video || !navigator.mediaDevices) {
			if (wrap) {
				wrap.innerHTML =
					'<p class="ed-escaner__no-cam">' +
					edEscaner.i18nNoCam +
					"</p>";
			}
			return;
		}
		navigator.mediaDevices
			.getUserMedia({ video: { facingMode: "environment" } })
			.then(function (stream) {
				video.srcObject = stream;
				video.play();
				requestAnimationFrame(scanFrame);
			})
			.catch(function () {
				wrap.innerHTML =
					'<p class="ed-escaner__no-cam">' +
					edEscaner.i18nNoCam +
					"</p>";
			});
	}

	function scanFrame() {
		var video = document.getElementById("ed-camara-video");
		if (!video || !video.srcObject) {
			return;
		}
		if (video.readyState !== video.HAVE_ENOUGH_DATA) {
			requestAnimationFrame(scanFrame);
			return;
		}
		if (typeof jsQR === "undefined") {
			requestAnimationFrame(scanFrame);
			return;
		}
		var canvas = document.createElement("canvas");
		canvas.width = video.videoWidth;
		canvas.height = video.videoHeight;
		var ctx = canvas.getContext("2d");
		if (!ctx) {
			requestAnimationFrame(scanFrame);
			return;
		}
		ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
		var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
		var code = jsQR(imageData.data, imageData.width, imageData.height);
		if (code && code.data && !escaneando) {
			try {
				var u = new URL(code.data);
				var token = u.searchParams.get("token");
				if (token) {
					escaneando = true;
					validarToken(token).finally(function () {
						setTimeout(function () {
							escaneando = false;
						}, 2500);
					});
				}
			} catch (e) {
				/* URL invàlida */
			}
		}
		requestAnimationFrame(scanFrame);
	}

	document.getElementById("ed-btn-validar-manual")?.addEventListener("click", function () {
		var inp = document.getElementById("ed-token-manual");
		validarToken(inp ? inp.value : "");
	});

	document.addEventListener("DOMContentLoaded", function () {
		iniciarCamara();
		cargarStats();
	});
})();
