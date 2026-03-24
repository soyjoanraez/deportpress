(function () {
	"use strict";

	var form = document.getElementById("ed-coach-form");
	var body = document.getElementById("ed-coach-jugadores-body");
	var titleEl = document.getElementById("ed-coach-session-title");
	var metaEl = document.getElementById("ed-coach-session-meta");
	var typeEl = document.getElementById("ed-coach-session-type");
	var msgEl = document.getElementById("ed-coach-msg");
	var loading = document.getElementById("ed-coach-loading");
	var saveBtn = document.getElementById("ed-coach-save");
	var eventsEl = document.getElementById("ed-coach-events");
	var weekLabelEl = document.getElementById("ed-coach-week-label");
	var prevWeekBtn = document.getElementById("ed-coach-week-prev");
	var nextWeekBtn = document.getElementById("ed-coach-week-next");
	var typeFilterEl = document.getElementById("ed-coach-type-filter");
	var categoryFilterEl = document.getElementById("ed-coach-category-filter");
	var emptyDetailEl = document.getElementById("ed-coach-empty-detail");

	if (!eventsEl || typeof edCoachPanel === "undefined") {
		return;
	}

	var calendarData = null;
	var selectedEvent = null;
	var sessionDetail = null;
	var currentWeekDate = edCoachPanel.today || "";

	function esc(s) {
		var d = document.createElement("div");
		d.textContent = s == null ? "" : String(s);
		return d.innerHTML;
	}

	function hideLoading() {
		if (loading) {
			loading.classList.add("is-hidden");
		}
	}

	function showMsg(text, isError) {
		if (!msgEl) {
			return;
		}
		msgEl.textContent = text || "";
		msgEl.classList.toggle("is-error", !!isError);
	}

	function showLoading(text) {
		if (!loading) {
			return;
		}
		loading.classList.remove("is-hidden");
		loading.textContent = text || edCoachPanel.i18nLoadingCalendar;
	}

	function fetchJson(path, opts) {
		opts = opts || {};
		return fetch(edCoachPanel.restBase.replace(/\/$/, "") + path, {
			credentials: "same-origin",
			method: opts.method || "GET",
			headers: Object.assign(
				{
					"X-WP-Nonce": edCoachPanel.nonce,
					Accept: "application/json",
				},
				opts.body ? { "Content-Type": "application/json" } : {}
			),
			body: opts.body ? JSON.stringify(opts.body) : undefined,
		}).then(function (res) {
			if (res.status === 401) {
				window.location.href = edCoachPanel.loginUrl;
				return Promise.reject(new Error("401"));
			}
			if (!res.ok) {
				return res.json().then(function (body) {
					var m =
						body && body.message ? body.message : "HTTP " + res.status;
					throw new Error(m);
				});
			}
			return res.status === 204 ? {} : res.json();
		});
	}

	function parseDate(value) {
		if (!value) {
			return null;
		}

		var iso = /^\d{4}-\d{2}-\d{2}$/.test(value)
			? value + "T12:00:00"
			: value.replace(" ", "T");
		var date = new Date(iso);
		return isNaN(date.getTime()) ? null : date;
	}

	function formatDate(value, opts) {
		var date = parseDate(value);
		if (!date) {
			return edCoachPanel.i18nNoDate;
		}
		try {
			return date.toLocaleDateString("ca-ES", opts || { day: "2-digit", month: "2-digit", year: "numeric" });
		} catch (e) {
			return value;
		}
	}

	function formatDateTime(value) {
		var date = parseDate(value);
		if (!date) {
			return edCoachPanel.i18nNoDate;
		}
		try {
			return date.toLocaleString("ca-ES", {
				day: "2-digit",
				month: "2-digit",
				year: "numeric",
				hour: "2-digit",
				minute: "2-digit",
			});
		} catch (e) {
			return value;
		}
	}

	function addDays(base, days) {
		var date = parseDate(base) || new Date();
		date.setDate(date.getDate() + days);
		return date.toISOString().slice(0, 10);
	}

	function buildWeekLabel(start, end) {
		if (!weekLabelEl) {
			return;
		}
		weekLabelEl.textContent = edCoachPanel.i18nWeekLabel
			.replace("%1$s", formatDate(start))
			.replace("%2$s", formatDate(end));
	}

	function renderCategoryOptions(items) {
		if (!categoryFilterEl) {
			return;
		}
		var current = categoryFilterEl.value || "";
		categoryFilterEl.innerHTML = "";
		var first = document.createElement("option");
		first.value = "";
		first.textContent = edCoachPanel.i18nCategoryAll;
		categoryFilterEl.appendChild(first);
		(items || []).forEach(function (item) {
			var option = document.createElement("option");
			option.value = String(item.id || "");
			option.textContent = item.titulo || "#" + item.id;
			if (option.value === current) {
				option.selected = true;
			}
			categoryFilterEl.appendChild(option);
		});
	}

	function getEventTypeLabel(tipo) {
		return tipo === "entrenamiento" ? edCoachPanel.i18nTraining : edCoachPanel.i18nMatch;
	}

	function getContextLabel(player) {
		var parts = [];
		if (player.equipo) {
			parts.push(player.equipo);
		}
		if (player.dorsal) {
			parts.push(edCoachPanel.i18nDorsal.replace("%d", String(player.dorsal)));
		}
		return parts.length ? parts.join(" · ") : edCoachPanel.i18nNoContext;
	}

	function getPlayerName(player) {
		var name = ((player.nombre || "") + " " + (player.apellidos || "")).trim();
		if (name) {
			return name;
		}
		return edCoachPanel.i18nPlayerFallback.replace("%d", String(player.id || 0));
	}

	function renderEvents() {
		if (!eventsEl) {
			return;
		}
		eventsEl.innerHTML = "";

		if (!calendarData || !Array.isArray(calendarData.eventos) || !calendarData.eventos.length) {
			eventsEl.innerHTML =
				'<div class="ed-panel-entrenador__empty"><p>' +
				esc(edCoachPanel.i18nNoEvents) +
				"</p></div>";
			if (form) {
				form.hidden = true;
			}
			if (emptyDetailEl) {
				emptyDetailEl.hidden = false;
				emptyDetailEl.innerHTML = "<p>" + esc(edCoachPanel.i18nSelectEvent) + "</p>";
			}
			return;
		}

		calendarData.eventos.forEach(function (event) {
			var button = document.createElement("button");
			button.type = "button";
			button.className =
				"ed-panel-entrenador__event" +
				(selectedEvent && selectedEvent.id === event.id ? " is-active" : "");
			button.setAttribute("data-event-id", String(event.id || ""));
			button.innerHTML =
				'<span class="ed-panel-entrenador__event-type">' +
				esc(getEventTypeLabel(event.attendance_type || event.tipo)) +
				"</span>" +
				'<strong class="ed-panel-entrenador__event-title">' +
				esc(event.titulo || "#") +
				"</strong>" +
				'<span class="ed-panel-entrenador__event-meta">' +
				esc(formatDateTime(event.fecha_hora)) +
				"</span>" +
				'<span class="ed-panel-entrenador__event-meta">' +
				esc(event.lugar || edCoachPanel.i18nNoLocation) +
				"</span>" +
				(event.categoria
					? '<span class="ed-panel-entrenador__event-tag">' + esc(event.categoria) + "</span>"
					: "") +
				(event.marcador
					? '<span class="ed-panel-entrenador__event-score">' + esc(event.marcador) + "</span>"
					: "");
			button.addEventListener("click", function () {
				selectEvent(event);
			});
			eventsEl.appendChild(button);
		});
	}

	function stateOptionsFor(type) {
		if (type === "partido") {
			return [
				{ value: "convocado", label: edCoachPanel.optConvocado },
				{ value: "no_convocado", label: edCoachPanel.optNoConvocado },
				{ value: "asistio", label: edCoachPanel.optAsistio },
				{ value: "no_asistio", label: edCoachPanel.optNoAsistio },
			];
		}

		return [
			{ value: "asistio", label: edCoachPanel.optAsistio },
			{ value: "no_asistio", label: edCoachPanel.optNoAsistio },
			{ value: "justificada", label: edCoachPanel.optJustificada },
			{ value: "tarde", label: edCoachPanel.optTarde },
		];
	}

	function renderJugadores(rows, type) {
		if (!body) {
			return;
		}
		var options = stateOptionsFor(type);
		body.innerHTML = "";
		(rows || []).forEach(function (j) {
			var tr = document.createElement("tr");
			var selectHtml = options
				.map(function (option) {
					return (
						'<option value="' +
						esc(option.value) +
						'"' +
						(option.value === j.estado ? " selected" : "") +
						">" +
						esc(option.label) +
						"</option>"
					);
				})
				.join("");
			tr.innerHTML =
				"<td>" +
				esc(getPlayerName(j)) +
				"</td>" +
				"<td>" +
				esc(getContextLabel(j)) +
				"</td>" +
				'<td><select class="ed-panel-entrenador__estado" data-jugador-id="' +
				esc(String(j.id)) +
				'">' +
				selectHtml +
				"</select></td>";
			body.appendChild(tr);
		});
	}

	function loadCalendar() {
		var query = "/coach/calendario?fecha=" + encodeURIComponent(currentWeekDate || edCoachPanel.today || "");
		if (typeFilterEl && typeFilterEl.value) {
			query += "&tipo_evento=" + encodeURIComponent(typeFilterEl.value);
		}
		if (categoryFilterEl && categoryFilterEl.value) {
			query += "&categoria_id=" + encodeURIComponent(categoryFilterEl.value);
		}

		showMsg("", false);
		showLoading(edCoachPanel.i18nLoadingCalendar);
		fetchJson(query)
			.then(function (data) {
				calendarData = data || {};
				currentWeekDate = calendarData.semana_inicio || currentWeekDate;
				buildWeekLabel(calendarData.semana_inicio, calendarData.semana_fin);
				renderCategoryOptions(calendarData.categorias || []);
				hideLoading();
				renderEvents();

				if (selectedEvent && Array.isArray(calendarData.eventos)) {
					var nextSelected = null;
					calendarData.eventos.forEach(function (event) {
						if (selectedEvent && event.id === selectedEvent.id) {
							nextSelected = event;
						}
					});
					if (nextSelected) {
						selectEvent(nextSelected, true);
						return;
					}
				}

				selectedEvent = null;
				sessionDetail = null;
				if (form) {
					form.hidden = true;
				}
				if (emptyDetailEl) {
					emptyDetailEl.hidden = false;
					emptyDetailEl.innerHTML = "<p>" + esc(edCoachPanel.i18nSelectEvent) + "</p>";
				}
			})
			.catch(function () {
				hideLoading();
				showMsg(edCoachPanel.i18nError, true);
			});
	}

	function selectEvent(event, keepMessage) {
		selectedEvent = event || null;
		renderEvents();
		if (!selectedEvent || !selectedEvent.can_take_attendance || !selectedEvent.source_id) {
			sessionDetail = null;
			if (form) {
				form.hidden = true;
			}
			if (emptyDetailEl) {
				emptyDetailEl.hidden = false;
				emptyDetailEl.innerHTML =
					"<p>" +
					esc(
						selectedEvent && !selectedEvent.can_take_attendance
							? edCoachPanel.i18nAttendanceLocked
							: edCoachPanel.i18nSelectEvent
					) +
					"</p>";
			}
			return;
		}
		if (!keepMessage) {
			showMsg("", false);
		}
		showLoading(edCoachPanel.i18nLoadingSession);
		fetchJson(
			"/coach/sesion/" +
				encodeURIComponent(selectedEvent.attendance_type) +
				"/" +
				encodeURIComponent(selectedEvent.source_id)
		)
			.then(function (detail) {
				sessionDetail = detail || null;
				hideLoading();
				if (!sessionDetail || !Array.isArray(sessionDetail.jugadores) || !sessionDetail.jugadores.length) {
					if (form) {
						form.hidden = true;
					}
					if (emptyDetailEl) {
						emptyDetailEl.hidden = false;
					}
					showMsg(edCoachPanel.i18nNoPlayers, true);
					return;
				}
				if (emptyDetailEl) {
					emptyDetailEl.hidden = true;
				}
				form.hidden = false;
				if (typeEl) {
					typeEl.textContent = getEventTypeLabel(sessionDetail.tipo);
				}
				if (titleEl) {
					titleEl.textContent = sessionDetail.titulo || selectedEvent.titulo || "";
				}
				if (metaEl) {
					var meta = [formatDateTime(sessionDetail.fecha_hora)];
					if (selectedEvent && selectedEvent.categoria) {
						meta.push(edCoachPanel.i18nCategory + ": " + selectedEvent.categoria);
					}
					meta.push(edCoachPanel.i18nLocation + ": " + (sessionDetail.lugar || edCoachPanel.i18nNoLocation));
					if (selectedEvent && selectedEvent.estado) {
						meta.push(edCoachPanel.i18nStatus + ": " + selectedEvent.estado);
					}
					if (selectedEvent && selectedEvent.marcador) {
						meta.push(edCoachPanel.i18nScore + ": " + selectedEvent.marcador);
					}
					metaEl.textContent = meta.join(" · ");
				}
				renderJugadores(sessionDetail.jugadores, sessionDetail.tipo);
				if (saveBtn) {
					saveBtn.disabled = !!sessionDetail.cerrada;
				}
				if (sessionDetail.cerrada) {
					showMsg(edCoachPanel.i18nAttendanceClosed, true);
				}
			})
			.catch(function (e) {
				hideLoading();
				showMsg(e.message || edCoachPanel.i18nError, true);
			});
	}

	if (prevWeekBtn) {
		prevWeekBtn.addEventListener("click", function () {
			currentWeekDate = addDays(calendarData && calendarData.semana_inicio ? calendarData.semana_inicio : currentWeekDate, -7);
			loadCalendar();
		});
	}

	if (nextWeekBtn) {
		nextWeekBtn.addEventListener("click", function () {
			currentWeekDate = addDays(calendarData && calendarData.semana_inicio ? calendarData.semana_inicio : currentWeekDate, 7);
			loadCalendar();
		});
	}

	if (typeFilterEl) {
		typeFilterEl.addEventListener("change", function () {
			loadCalendar();
		});
	}

	if (categoryFilterEl) {
		categoryFilterEl.addEventListener("change", function () {
			loadCalendar();
		});
	}

	if (saveBtn) {
		saveBtn.addEventListener("click", function () {
			if (!sessionDetail || !sessionDetail.id || !sessionDetail.fecha || !sessionDetail.tipo) {
				showMsg(edCoachPanel.i18nPickSession, true);
				return;
			}
			var selects = body ? body.querySelectorAll("select.ed-panel-entrenador__estado") : [];
			var registros = [];
			selects.forEach(function (s) {
				var jid = s.getAttribute("data-jugador-id");
				if (!jid) {
					return;
				}
				registros.push({
					jugador_id: parseInt(jid, 10),
					estado: s.value,
				});
			});
			if (!registros.length) {
				showMsg(edCoachPanel.i18nNoPlayers, true);
				return;
			}
			saveBtn.disabled = true;
			showMsg(edCoachPanel.i18nSaving, false);
			fetchJson("/asistencias/sesion", {
				method: "POST",
				body: {
					sesion_id: sessionDetail.id,
					tipo_sesion: sessionDetail.tipo,
					fecha: sessionDetail.fecha,
					registros: registros,
				},
			})
				.then(function () {
					showMsg(edCoachPanel.i18nSaved, false);
					loadCalendar();
				})
				.catch(function (e) {
					showMsg(e.message || edCoachPanel.i18nError, true);
				})
				.finally(function () {
					saveBtn.disabled = !!(sessionDetail && sessionDetail.cerrada);
				});
		});
	}

	loadCalendar();
})();
