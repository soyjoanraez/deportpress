(function () {
	"use strict";

	if (typeof edBarCliente === "undefined") {
		return;
	}

	var api = edBarCliente.restBase.replace(/\/$/, "");
	var torneoId = parseInt(edBarCliente.torneoId, 10);

	function esc(s) {
		var d = document.createElement("div");
		d.textContent = s == null ? "" : String(s);
		return d.innerHTML;
	}

	function renderProductos(items) {
		var wrap = document.getElementById("ed-bar-productos");
		if (!wrap) {
			return;
		}
		if (!items || !items.length) {
			wrap.innerHTML = "<p>" + esc(edBarCliente.i18nNoProductos) + "</p>";
			return;
		}
		wrap.innerHTML = items
			.map(function (p) {
				var img = p.foto_url
					? '<img src="' + esc(p.foto_url) + '" alt="" class="ed-bar-cliente__thumb">'
					: "";
				return (
					'<div class="ed-bar-cliente__item" data-id="' +
					esc(p.id) +
					'">' +
					img +
					'<div class="ed-bar-cliente__item-body">' +
					"<strong>" +
					esc(p.nombre) +
					"</strong>" +
					'<span class="ed-bar-cliente__precio">' +
					Number(p.precio).toFixed(2) +
					" €</span>" +
					'<label class="ed-bar-cliente__qty"><span class="screen-reader-text">' +
					esc(edBarCliente.i18nCantidad) +
					'</span><input type="number" min="0" max="99" value="0" class="ed-bar-qty"></label>' +
					"</div></div>"
				);
			})
			.join("");
	}

	function recogerLineas() {
		var lineas = [];
		document.querySelectorAll(".ed-bar-cliente__item").forEach(function (row) {
			var id = row.getAttribute("data-id");
			var inp = row.querySelector(".ed-bar-qty");
			var q = inp ? parseInt(inp.value, 10) : 0;
			if (id && q > 0) {
				lineas.push({ producto_id: parseInt(id, 10), cantidad: q });
			}
		});
		return lineas;
	}

	document.addEventListener("DOMContentLoaded", function () {
		var msg = document.getElementById("ed-bar-msg");
		if (!torneoId) {
			if (msg) {
				msg.hidden = false;
				msg.textContent = edBarCliente.i18nNoTorneo;
			}
			return;
		}
		fetch(api + "/torneo/" + torneoId + "/bar/productos", {
			credentials: "same-origin",
			headers: { Accept: "application/json" },
		})
			.then(function (r) {
				return r.json();
			})
			.then(renderProductos)
			.catch(function () {});

		fetch(api + "/torneo/" + torneoId + "/bar/franjas", {
			credentials: "same-origin",
			headers: { Accept: "application/json" },
		})
			.then(function (r) {
				return r.json();
			})
			.then(function (franjas) {
				var sel = document.getElementById("ed-bar-franja");
				if (!sel || !Array.isArray(franjas)) {
					return;
				}
				sel.innerHTML = franjas
					.map(function (f) {
						return "<option value=\"" + esc(f) + "\">" + esc(f) + "</option>";
					})
					.join("");
			})
			.catch(function () {});

		document.getElementById("ed-bar-form")?.addEventListener("submit", function (e) {
			e.preventDefault();
			var lineas = recogerLineas();
			if (!lineas.length) {
				if (msg) {
					msg.hidden = false;
					msg.textContent = edBarCliente.i18nVacio;
				}
				return;
			}
			var body = {
				nombre_cliente: document.getElementById("ed-bar-nombre")?.value || "",
				telefono: document.getElementById("ed-bar-tel")?.value || "",
				franja_horaria: document.getElementById("ed-bar-franja")?.value || "",
				metodo_pago: document.getElementById("ed-bar-pago")?.value || "efectivo",
				notas: document.getElementById("ed-bar-notas")?.value || "",
				lineas: lineas,
			};
			fetch(api + "/torneo/" + torneoId + "/bar/pedido", {
				method: "POST",
				credentials: "same-origin",
				headers: {
					"Content-Type": "application/json",
					Accept: "application/json",
					"X-WP-Nonce": edBarCliente.nonce,
				},
				body: JSON.stringify(body),
			})
				.then(function (r) {
					return r.json().then(function (data) {
						return { ok: r.ok, data: data };
					});
				})
				.then(function (pack) {
					if (msg) {
						msg.hidden = false;
					}
					if (pack.ok && pack.data && pack.data.ok) {
						if (msg) {
							msg.textContent =
								edBarCliente.i18nOk + " #" + pack.data.pedido_id;
						}
						document.getElementById("ed-bar-form")?.reset();
						document.querySelectorAll(".ed-bar-qty").forEach(function (i) {
							i.value = "0";
						});
					} else if (msg) {
						msg.textContent =
							(pack.data && pack.data.error) || edBarCliente.i18nError;
					}
				})
				.catch(function () {
					if (msg) {
						msg.hidden = false;
						msg.textContent = edBarCliente.i18nError;
					}
				});
		});
	});
})();
