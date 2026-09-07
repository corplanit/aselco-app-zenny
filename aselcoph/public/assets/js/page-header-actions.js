(function () {
  "use strict";

  var root = document.querySelector(".page-header-card__actions");
  if (!root) return;

  var buttons = [];
  root.querySelectorAll("a.ti-btn, button.ti-btn, .ti-btn, a.btn, button.btn").forEach(function (btn) {
    if (buttons.indexOf(btn) === -1) buttons.push(btn);
  });
  if (!buttons.length) return;

  buttons.forEach(function (btn) {
    solidify(btn);
    ensureIcon(btn);
  });

  var hasPrimary = buttons.some(function (btn) {
    return btn.classList.contains("ti-btn-primary") || btn.classList.contains("bg-primary");
  });
  if (!hasPrimary) {
    var pick =
      buttons.find(function (btn) {
        return !btn.classList.contains("ti-btn-danger") && !btn.classList.contains("ti-btn-warning");
      }) || buttons[0];
    pick.classList.remove("ti-btn-secondary");
    pick.classList.add("ti-btn-primary", "text-white", "bg-primary");
  }

  function solidify(btn) {
    var danger = /danger/i.test(btn.className);
    var warning = /warning/i.test(btn.className);
    var primary = btn.classList.contains("ti-btn-primary") || btn.classList.contains("bg-primary");

    btn.classList.remove(
      "ti-btn-light",
      "ti-btn-outline-primary",
      "ti-btn-outline-warning",
      "ti-btn-outline-danger",
      "ti-btn-outline-secondary",
      "ti-btn-soft-secondary",
      "ti-btn-soft-primary",
      "bg-white",
      "text-dark"
    );

    if (danger) {
      btn.classList.add("ti-btn-danger", "text-white");
      return;
    }
    if (warning) {
      btn.classList.add("ti-btn-warning", "text-white");
      return;
    }
    if (primary) {
      btn.classList.add("ti-btn-primary", "text-white", "bg-primary");
      return;
    }
    btn.classList.add("ti-btn-secondary");
  }

  function buttonText(btn) {
    var clone = btn.cloneNode(true);
    clone.querySelectorAll("i, svg, .badge").forEach(function (el) {
      el.remove();
    });
    return (clone.textContent || "").replace(/\s+/g, " ").trim();
  }

  function iconFor(text) {
    var t = text.toLowerCase();
    if (/escalat/.test(t)) return "bi-exclamation-triangle";
    if (/export|csv|download/.test(t)) return "bi-download";
    if (/report|analytic/.test(t)) return "bi-bar-chart";
    if (/intake|headset/.test(t)) return "bi-headset";
    if (/upload/.test(t)) return "bi-upload";
    if (/retriev|test/.test(t)) return "bi-search";
    if (/categor/.test(t)) return "bi-tags";
    if (/document/.test(t)) return "bi-file-earmark-text";
    if (/re-?index/.test(t)) return "bi-arrow-repeat";
    if (/deactiv/.test(t)) return "bi-pause-circle";
    if (/activat/.test(t)) return "bi-play-circle";
    if (/edit/.test(t)) return "bi-pencil-square";
    if (/queue/.test(t)) return "bi-list-ul";
    if (/dashboard/.test(t)) return "bi-speedometer2";
    if (/publish|push|send/.test(t)) return "bi-send";
    if (/back|return/.test(t)) return "bi-arrow-left";
    if (/pending|approv/.test(t)) return "bi-clock-history";
    if (/announcement|megaphone/.test(t)) return "bi-megaphone";
    if (/register|create|new |add |plus|load/.test(t)) return "bi-plus-circle";
    if (/save/.test(t)) return "bi-check2-circle";
    if (/preview|view/.test(t)) return "bi-eye";
    if (/print/.test(t)) return "bi-printer";
    if (/refresh/.test(t)) return "bi-arrow-clockwise";
    if (/filter/.test(t)) return "bi-funnel";
    if (/user|consumer|account/.test(t)) return "bi-person-plus";
    if (/calendar/.test(t)) return "bi-calendar-event";
    if (/logout|sign out/.test(t)) return "bi-box-arrow-right";
    if (/ticket/.test(t)) return "bi-ticket-detailed";
    if (/menu/.test(t)) return "bi-list";
    if (/setting/.test(t)) return "bi-gear";
    return "bi-arrow-right-circle";
  }

  function ensureIcon(btn) {
    if (btn.querySelector("i, svg")) return;
    var label = buttonText(btn);
    if (!label) return;

    var icon = document.createElement("i");
    icon.className = "bi " + iconFor(label);
    icon.setAttribute("aria-hidden", "true");
    btn.insertBefore(icon, btn.firstChild);
  }
})();
