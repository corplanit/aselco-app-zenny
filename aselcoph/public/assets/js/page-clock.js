(function () {
  "use strict";

  var root = document.getElementById("page-live-clock");
  if (!root) return;

  var dateEl = root.querySelector("[data-clock-date]");
  var timeEl = root.querySelector("[data-clock-time]");
  var tz = root.getAttribute("data-timezone") || "Asia/Manila";

  var dateFmt = new Intl.DateTimeFormat("en-PH", {
    timeZone: tz,
    weekday: "short",
    month: "short",
    day: "numeric",
    year: "numeric",
  });

  var timeFmt = new Intl.DateTimeFormat("en-PH", {
    timeZone: tz,
    hour: "numeric",
    minute: "2-digit",
    second: "2-digit",
    hour12: true,
  });

  function tick() {
    var now = new Date();
    if (dateEl) dateEl.textContent = dateFmt.format(now);
    if (timeEl) timeEl.textContent = timeFmt.format(now);
    root.setAttribute("datetime", now.toISOString());
  }

  tick();
  setInterval(tick, 1000);
})();
