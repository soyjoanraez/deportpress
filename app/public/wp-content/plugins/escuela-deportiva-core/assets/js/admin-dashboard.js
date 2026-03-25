/**
 * Dashboard admin: KPIs, Chart.js, taules i enllaços CSV (Fase 10).
 */
(function () {
	'use strict';

	var cfg = typeof ED_Dashboard === 'undefined' ? null : ED_Dashboard;
	if (!cfg || !cfg.apiUrl || !cfg.nonce) {
		return;
	}

	var i18n = cfg.i18n || {};
	var charts = { ingresos: null, deportes: null, asistencia: null };

	function $(sel, root) {
		return (root || document).querySelector(sel);
	}

	function text(sel, val) {
		var el = $(sel);
		if (el) {
			el.textContent = val;
		}
	}

	function show(el, on) {
		if (!el) {
			return;
		}
		el.hidden = !on;
	}

	function fmtMoney(n) {
		var x = Number(n);
		if (isNaN(x)) {
			x = 0;
		}
		try {
			return new Intl.NumberFormat('ca-ES', {
				style: 'currency',
				currency: 'EUR',
				maximumFractionDigits: 2
			}).format(x);
		} catch (e) {
			return x.toFixed(2) + ' €';
		}
	}

	function fmtNum(n, frac) {
		var x = Number(n);
		if (isNaN(x)) {
			return '—';
		}
		return new Intl.NumberFormat('ca-ES', {
			maximumFractionDigits: frac != null ? frac : 0
		}).format(x);
	}

	function esc(s) {
		if (s == null) {
			return '';
		}
		var d = document.createElement('div');
		d.textContent = String(s);
		return d.innerHTML;
	}

	function api(path) {
		var base = cfg.apiUrl.replace(/\/$/, '');
		var p = path.replace(/^\//, '');
		return base + '/' + p;
	}

	function fetchJson(path) {
		return fetch(api(path), {
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': cfg.nonce,
				Accept: 'application/json'
			}
		}).then(function (r) {
			if (!r.ok) {
				throw new Error('HTTP ' + r.status);
			}
			return r.json();
		});
	}

	function exportUrl(tipo) {
		var u = new URL(api('admin/exportar/' + tipo), window.location.origin);
		u.searchParams.set('_wpnonce', cfg.nonce);
		return u.toString();
	}

	function kpiCard(label, value, sub) {
		var subHtml = sub
			? '<p class="ed-kpi-card__sub">' + esc(sub) + '</p>'
			: '';
		return (
			'<div class="ed-kpi-card">' +
			'<p class="ed-kpi-card__label">' +
			esc(label) +
			'</p>' +
			'<p class="ed-kpi-card__value">' +
			esc(value) +
			'</p>' +
			subHtml +
			'</div>'
		);
	}

	function renderKpis(k) {
		var grid = $('#ed-kpi-grid');
		if (!grid) {
			return;
		}
		var pctSub =
			fmtNum(k.total_plazos, 0) + ' ' + (i18n.kpiPagosSub || '');
		var html = '';
		html += kpiCard(
			i18n.kpiJugadores || '',
			fmtNum(k.inscripciones_activas, 0)
		);
		html += kpiCard(
			i18n.kpiIngresosMes || '',
			fmtMoney(k.ingresos_mes)
		);
		html += kpiCard(
			i18n.kpiPagosPct || '',
			fmtNum(k.pct_pagados, 1) + '%',
			pctSub
		);
		html += kpiCard(
			i18n.kpiPendientes || '',
			fmtNum(k.plazos_pendientes, 0)
		);
		html += kpiCard(
			i18n.kpiFallidos || '',
			fmtNum(k.plazos_fallidos, 0)
		);
		html += kpiCard(
			i18n.kpiAsistencia || '',
			fmtNum(k.pct_asistencia, 1) + '%',
			i18n.kpiAsistSub || ''
		);
		html += kpiCard(
			i18n.kpiMensajes || '',
			fmtNum(k.mensajes_pendientes, 0)
		);
		html += kpiCard(
			i18n.kpiTorneos || '',
			fmtNum(k.torneos_activos, 0)
		);
		grid.innerHTML = html;
	}

	function destroyCharts() {
		Object.keys(charts).forEach(function (key) {
			if (charts[key]) {
				charts[key].destroy();
				charts[key] = null;
			}
		});
	}

	function chartDefaults() {
		if (typeof Chart === 'undefined' || !Chart.defaults) {
			return;
		}
		Chart.defaults.font.family =
			'-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';
		Chart.defaults.color = '#646970';
	}

	function renderChartIngresos(rows) {
		var canvas = $('#ed-chart-ingresos');
		if (!canvas || typeof Chart === 'undefined') {
			return;
		}
		var labels = (rows || []).map(function (r) {
			return r.label || r.mes || '';
		});
		var data = (rows || []).map(function (r) {
			return Number(r.total) || 0;
		});
		charts.ingresos = new Chart(canvas.getContext('2d'), {
			type: 'line',
			data: {
				labels: labels,
				datasets: [
					{
						label: i18n.datasetIngresos || '€',
						data: data,
						borderColor: '#2271b1',
						backgroundColor: 'rgba(34, 113, 177, 0.12)',
						fill: true,
						tension: 0.25
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { display: true } },
				scales: {
					y: {
						beginAtZero: true,
						ticks: {
							callback: function (v) {
								return fmtMoney(v);
							}
						}
					}
				}
			}
		});
	}

	function renderChartDeportes(rows) {
		var canvas = $('#ed-chart-deportes');
		if (!canvas || typeof Chart === 'undefined') {
			return;
		}
		var labels = (rows || []).map(function (r) {
			return r.nombre || '';
		});
		var data = (rows || []).map(function (r) {
			return Number(r.total) || 0;
		});
		var colors = [
			'#2271b1',
			'#00a32a',
			'#dba617',
			'#d63638',
			'#826eb4',
			'#3582c4',
			'#1d2327'
		];
		var bg = labels.map(function (_, i) {
			return colors[i % colors.length];
		});
		charts.deportes = new Chart(canvas.getContext('2d'), {
			type: 'doughnut',
			data: {
				labels: labels,
				datasets: [
					{
						data: data,
						backgroundColor: bg,
						borderWidth: 1,
						borderColor: '#fff'
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { position: 'right' } }
			}
		});
	}

	function renderChartAsistencia(rows) {
		var canvas = $('#ed-chart-asistencia');
		if (!canvas || typeof Chart === 'undefined') {
			return;
		}
		var labels = (rows || []).map(function (r) {
			return r.categoria || '';
		});
		var data = (rows || []).map(function (r) {
			return Number(r.pct) || 0;
		});
		charts.asistencia = new Chart(canvas.getContext('2d'), {
			type: 'bar',
			data: {
				labels: labels,
				datasets: [
					{
						label: i18n.datasetAsistPct || '%',
						data: data,
						backgroundColor: 'rgba(0, 163, 42, 0.35)',
						borderColor: '#00a32a',
						borderWidth: 1
					}
				]
			},
			options: {
				indexAxis: 'y',
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { display: false } },
				scales: {
					x: { beginAtZero: true, max: 100 }
				}
			}
		});
	}

	function renderTableImpagos(rows) {
		var table = $('#ed-table-impagos');
		if (!table) {
			return;
		}
		var thead = table.querySelector('thead');
		var tbody = table.querySelector('tbody');
		thead.innerHTML =
			'<tr>' +
			'<th>' +
			esc(i18n.thJugador) +
			'</th><th>' +
			esc(i18n.thDeporte) +
			'</th><th>' +
			esc(i18n.thImporte) +
			'</th><th>' +
			esc(i18n.thFecha) +
			'</th><th>' +
			esc(i18n.thEstado) +
			'</th>' +
			'</tr>';
		if (!rows || !rows.length) {
			tbody.innerHTML =
				'<tr><td colspan="5"><p class="ed-dash-empty">' +
				esc(i18n.tablaEmptyImp) +
				'</p></td></tr>';
			return;
		}
		tbody.innerHTML = rows
			.map(function (r) {
				return (
					'<tr>' +
					'<td>' +
					esc(r.jugador_nombre) +
					'</td><td>' +
					esc(r.deporte_nombre) +
					'</td><td>' +
					esc(fmtMoney(r.importe)) +
					'</td><td>' +
					esc(r.fecha_prevista || '') +
					'</td><td>' +
					esc(r.estado) +
					'</td>' +
					'</tr>'
				);
			})
			.join('');
	}

	function renderTableBaja(rows) {
		var table = $('#ed-table-baja');
		if (!table) {
			return;
		}
		var thead = table.querySelector('thead');
		var tbody = table.querySelector('tbody');
		thead.innerHTML =
			'<tr>' +
			'<th>' +
			esc(i18n.thJugador) +
			'</th><th>' +
			esc(i18n.thSesiones) +
			'</th><th>' +
			esc(i18n.thAsist) +
			'</th><th>' +
			esc(i18n.thPct) +
			'</th>' +
			'</tr>';
		if (!rows || !rows.length) {
			tbody.innerHTML =
				'<tr><td colspan="4"><p class="ed-dash-empty">' +
				esc(i18n.tablaEmptyBaja) +
				'</p></td></tr>';
			return;
		}
		tbody.innerHTML = rows
			.map(function (r) {
				return (
					'<tr>' +
					'<td>' +
					esc(r.jugador_nombre) +
					'</td><td>' +
					esc(fmtNum(r.total, 0)) +
					'</td><td>' +
					esc(fmtNum(r.asistencias, 0)) +
					'</td><td>' +
					esc(fmtNum(r.pct, 1) + '%') +
					'</td>' +
					'</tr>'
				);
			})
			.join('');
	}

	function renderExports() {
		var ul = $('#ed-exports-list');
		if (!ul) {
			return;
		}
		var items = [
			{ tipo: 'jugadores', label: i18n.expJugadores },
			{ tipo: 'pagos', label: i18n.expPagos },
			{ tipo: 'asistencias', label: i18n.expAsist },
			{ tipo: 'impagos', label: i18n.expImpagos },
			{ tipo: 'baja_asistencia', label: i18n.expBaja }
		];
		ul.innerHTML = items
			.map(function (it) {
				var href = exportUrl(it.tipo);
				return (
					'<li><a href="' +
					esc(href) +
					'">' +
					esc(it.label) +
					' (' +
					esc(i18n.exportCsv || 'CSV') +
					')</a></li>'
				);
			})
			.join('');
	}

	function applyStaticLabels() {
		text('.ed-dash-title', i18n.title || '');
		text('.ed-dash-loading-text', i18n.loading || '');
		text('.ed-dash-updated-label', (i18n.updated || '') + ' ');
		text('.ed-chart-title--ingresos', i18n.chartIngresos || '');
		text('.ed-chart-title--deportes', i18n.chartDeportes || '');
		text('.ed-chart-title--asistencia', i18n.chartAsistencia || '');
		text('.ed-table-title--impagos', i18n.alertImpagos || '');
		text('.ed-table-title--baja', i18n.alertBaja || '');
		text('.ed-exports-title', i18n.exportsTitle || '');
	}

	function run() {
		applyStaticLabels();
		var loading = $('.ed-dash-loading');
		var body = $('.ed-dash-body');
		var updatedLine = $('.ed-dash-updated-line');
		var updatedTime = $('.ed-dash-updated-time');

		Promise.all([
			fetchJson('admin/kpis'),
			fetchJson('admin/ingresos-mes'),
			fetchJson('admin/inscripciones-deporte'),
			fetchJson('admin/asistencia-categoria'),
			fetchJson('admin/impagos'),
			fetchJson('admin/baja-asistencia')
		])
			.then(function (results) {
				var kpis = results[0];
				destroyCharts();
				chartDefaults();
				renderKpis(kpis);
				renderChartIngresos(results[1]);
				renderChartDeportes(results[2]);
				renderChartAsistencia(results[3]);
				renderTableImpagos(results[4]);
				renderTableBaja(results[5]);
				renderExports();

				if (updatedTime) {
					updatedTime.textContent = new Intl.DateTimeFormat('ca-ES', {
						dateStyle: 'medium',
						timeStyle: 'short'
					}).format(new Date());
				}
				show(loading, false);
				show(body, true);
				show(updatedLine, true);
			})
			.catch(function () {
				if (loading) {
					loading.innerHTML =
						'<p class="ed-dash-empty">Error en carregar les dades. Torna a provar.</p>';
				}
			});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', run);
	} else {
		run();
	}
})();
