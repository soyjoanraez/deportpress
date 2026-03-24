<?php
/**
 * Vista del dashboard d’administració (Fase 10).
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap ed-admin-dashboard">
	<h1 class="ed-dash-title"></h1>
	<p class="ed-dash-updated-line" hidden>
		<span class="ed-dash-updated-label"></span>
		<strong class="ed-dash-updated-time"></strong>
	</p>
	<div class="ed-dash-loading" role="status">
		<span class="spinner is-active"></span>
		<span class="ed-dash-loading-text"></span>
	</div>
	<div class="ed-dash-body" hidden>
		<div class="ed-kpi-grid" id="ed-kpi-grid"></div>
		<div class="ed-charts-grid">
			<div class="ed-chart-card">
				<h2 class="ed-chart-title ed-chart-title--ingresos"></h2>
				<div class="ed-chart-canvas-wrap">
					<canvas id="ed-chart-ingresos" height="220"></canvas>
				</div>
			</div>
			<div class="ed-chart-card">
				<h2 class="ed-chart-title ed-chart-title--deportes"></h2>
				<div class="ed-chart-canvas-wrap">
					<canvas id="ed-chart-deportes" height="220"></canvas>
				</div>
			</div>
			<div class="ed-chart-card ed-chart-card--wide">
				<h2 class="ed-chart-title ed-chart-title--asistencia"></h2>
				<div class="ed-chart-canvas-wrap ed-chart-canvas-wrap--tall">
					<canvas id="ed-chart-asistencia" height="280"></canvas>
				</div>
			</div>
		</div>
		<div class="ed-tables-grid">
			<div class="ed-table-card">
				<h2 class="ed-table-title ed-table-title--impagos"></h2>
				<div class="ed-table-scroll">
					<table class="widefat striped ed-dash-table" id="ed-table-impagos">
						<thead></thead>
						<tbody></tbody>
					</table>
				</div>
			</div>
			<div class="ed-table-card">
				<h2 class="ed-table-title ed-table-title--baja"></h2>
				<div class="ed-table-scroll">
					<table class="widefat striped ed-dash-table" id="ed-table-baja">
						<thead></thead>
						<tbody></tbody>
					</table>
				</div>
			</div>
		</div>
		<div class="ed-exports-card">
			<h2 class="ed-exports-title"></h2>
			<ul class="ed-exports-list" id="ed-exports-list"></ul>
		</div>
	</div>
</div>
