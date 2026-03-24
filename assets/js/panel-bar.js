(function () {
	"use strict";

	if (typeof edBarPanel === "undefined") {
		return;
	}

	var api = edBarPanel.restBase.replace(/\/$/, "");
	var headers = {
		"Content-Type": "application/json",
		"X-WP-Nonce": edBarPanel.nonce,
		Accept: "application/json",
	};
	var torneoId = parseInt(edBarPanel.torneoId, 10);
	var tabEstado = "pendiente";
	var franjaActiva = "todas";

	var ESTADOS = ["pendiente", "preparando", "listo", "entregado"];

	function esc(s) {
		var d = document.createElement("div");
		d.textContent = s == null ? "" : String(s);
		return d.innerHTML;
	}

	function cargarFranjas() {
		var cont = document.getElementById("bar-franjas-tabs");
		if (!cont || !torneoId) {
			return Promise.resolve();
		}
		return fetch(api + "/torneo/" + torneoId + "/bar/franjas", {
			credentials: "same-origin",
			headers: { "X-WP-Nonce": edBarPanel.nonce, Accept: "application/json" },
		})
			.then(function (r) {
				return r.json();
			})
			.then(function (data) {
				var arr = Array.isArray(data) ? data : [];
				cont.innerHTML =
					'<button type="button" class="ed-tab' +
					(franjaActiva === "todas" ? " is-active" : "") +
					'" data-franja="todas">' +
					esc(edBarPanel.i18nTodas) +
					"</button>" +
					arr
						.map(function (f) {
							return (
								'<button type="button" class="ed-tab' +
								(franjaActiva === f ? " is-active" : "") +
								'" data-franja="' +
								esc(f) +
								'">' +
								esc(f) +
								"</button>"
							);
						})
						.join("");
				cont.querySelectorAll(".ed-tab").forEach(function (btn) {
					btn.addEventListener("click", function () {
						franjaActiva = btn.getAttribute("data-franja") || "todas";
						cont.querySelectorAll(".ed-tab").forEach(function (b) {
							b.classList.toggle("is-active", b === btn);
						});
						cargarPedidos();
					});
				});
			});
	}

	function renderTabsEstado() {
		var cont = document.getElementById("bar-estado-tabs");
		if (!cont) {
			return;
		}
		cont.innerHTML = ESTADOS.map(function (st) {
			var lab = edBarPanel.labels[st] || st;
			return (
				'<button type="button" class="ed-tab' +
				(tabEstado === st ? " is-active" : "") +
				'" data-estado="' +
				esc(st) +
				'">' +
				esc(lab) +
				"</button>"
			);
		}).join("");
		cont.querySelectorAll(".ed-tab").forEach(function (btn) {
			btn.addEventListener("click", function () {
				tabEstado = btn.getAttribute("data-estado") || "pendiente";
				cont.querySelectorAll(".ed-tab").forEach(function (b) {
					b.classList.toggle("is-active", b === btn);
				});
				cargarPedidos();
			});
		});
	}

	function cargarPedidos() {
		var list = document.getElementById("bar-pedidos-lista");
		var ld = document.getElementById("bar-loading");
		if (!list || !torneoId) {
			return Promise.resolve();
		}
		if (ld) {
			ld.hidden = false;
		}
		var qs = new URLSearchParams({ estado: tabEstado });
		if (franjaActiva !== "todas") {
			qs.set("franja", franjaActiva);
		}
		return fetch(api + "/torneo/" + torneoId + "/bar/pedidos?" + qs.toString(), {
			credentials: "same-origin",
			headers: { "X-WP-Nonce": edBarPanel.nonce, Accept: "application/json" },
		})
			.then(function (r) {
				return r.json();
			})
			.then(function (data) {
				if (ld) {
					ld.hidden = true;
				}
				if (!Array.isArray(data) || !data.length) {
					list.innerHTML =
						'<p class="ed-panel-bar__empty">' + esc(edBarPanel.i18nEmpty) + "</p>";
					return;
				}
				list.innerHTML = data
					.map(function (p) {
						var lineas = (p.lineas || [])
							.map(function (l) {
								return (
									"<li>" +
									esc(l.cantidad) +
									"× " +
									esc(l.nombre) +
									" (" +
									esc(l.categoria || "") +
									")</li>"
								);
							})
							.join("");
						var next = edBarPanel.nextEstado[p.estado];
						var btn = next
							? '<button type="button" class="ed-btn ed-btn--sm ed-bar-next" data-id="' +
							  esc(p.id) +
							  '" data-next="' +
							  esc(next) +
							  '">' +
							  esc(edBarPanel.i18nAvanzar) +
							  "</button>"
							: "";
						return (
							'<article class="ed-pedido-card ed-pedido-card--' +
							esc(p.estado) +
							'">' +
							'<header class="ed-pedido-card__head">' +
							"<strong>#" +
							esc(p.id) +
							"</strong> " +
							esc(p.nombre_cliente) +
							' <span class="ed-badge">' +
							esc(p.franja_horaria) +
							"</span></header>" +
							"<ul class=\"ed-pedido-card__lines\">" +
							lineas +
							"</ul>" +
							'<footer class="ed-pedido-card__foot">' +
							esc(p.total) +
							" € · " +
							esc(p.metodo_pago) +
							btn +
							"</footer></article>"
						);
					})
					.join("");
				list.querySelectorAll(".ed-bar-next").forEach(function (b) {
					b.addEventListener("click", function () {
						var id = b.getAttribute("data-id");
						var nx = b.getAttribute("data-next");
						fetch(api + "/bar/pedido/" + id + "/estado", {
							method: "POST",
							credentials: "same-origin",
							headers: headers,
							body: JSON.stringify({ estado: nx }),
						}).then(function () {
							cargarPedidos();
						});
					});
				});
			})
			.catch(function () {
				if (ld) {
					ld.hidden = true;
				}
				list.innerHTML =
					'<p class="ed-panel-bar__error">' + esc(edBarPanel.i18nError) + "</p>";
			});
	}

	document.addEventListener("DOMContentLoaded", function () {
		if (!torneoId) {
			var list = document.getElementById("bar-pedidos-lista");
			if (list) {
				list.innerHTML =
					'<p class="ed-panel-bar__error">' + esc(edBarPanel.i18nNoTorneo) + "</p>";
			}
			return;
		}
		renderTabsEstado();
		cargarFranjas().then(cargarPedidos);
		setInterval(cargarPedidos, 15000);
	});
})();
