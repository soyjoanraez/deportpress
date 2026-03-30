(function () {
	"use strict";
	window.edTrackClick = function (anuncioId) {
		if (!anuncioId || !window.edPubRest) {
			return;
		}
		var base = window.edPubRest.replace(/\/$/, "");
		fetch(base + "/publicidad/" + anuncioId + "/click", {
			method: "POST",
			headers: { "Content-Type": "application/json" },
			credentials: "same-origin",
			keepalive: true,
			body: "{}",
		}).catch(function () {});
	};
})();
