(function () {
  "use strict";

  var overlay = document.getElementById("menu-search-modal");
  var openBtn = document.getElementById("menu-search-open");
  var field = document.getElementById("menu-search-field");
  var results = document.getElementById("menu-search-results");
  if (!overlay || !openBtn || !field || !results) return;

  var items = [];
  var filtered = [];
  var active = 0;
  var isMac = /Mac|iPhone|iPad/.test(navigator.platform || "");

  var kbd = openBtn.querySelector("kbd");
  if (kbd) kbd.textContent = isMac ? "⌘K" : "Ctrl K";

  function itemLabel(a) {
    var labelEl = a.querySelector(".side-menu__label");
    if (labelEl) {
      var clone = labelEl.cloneNode(true);
      clone.querySelectorAll(".badge").forEach(function (b) {
        b.remove();
      });
      return (clone.textContent || "").replace(/\s+/g, " ").trim();
    }
    return (a.textContent || "").replace(/\s+/g, " ").trim();
  }

  function findCategory(a) {
    var node = a.closest("li.slide") || a.parentElement;
    while (node) {
      var prev = node.previousElementSibling;
      while (prev) {
        if (prev.classList.contains("slide__category")) {
          return (prev.textContent || "").replace(/\s+/g, " ").trim();
        }
        prev = prev.previousElementSibling;
      }
      node = node.parentElement ? node.parentElement.closest("li.slide") : null;
    }
    return "Menu";
  }

  function collectItems() {
    var list = [];
    var seen = {};

    document.querySelectorAll("#sidebar a.side-menu__item").forEach(function (a) {
      var href = (a.getAttribute("href") || "").trim();
      if (!href || href.indexOf("javascript:") === 0) return;

      var label = itemLabel(a);
      if (!label) return;

      var key = href + "|" + label;
      if (seen[key]) return;
      seen[key] = true;

      var icon = "";
      var iconEl = a.querySelector(".side-menu__icon");
      if (iconEl) icon = iconEl.className;

      list.push({
        href: href,
        label: label,
        category: findCategory(a),
        icon: icon,
        el: a,
      });
    });

    items = list;
  }

  function grouped(list) {
    var groups = [];
    var map = {};
    list.forEach(function (item) {
      var name = item.category || "Menu";
      if (!map[name]) {
        map[name] = { name: name, items: [] };
        groups.push(map[name]);
      }
      map[name].items.push(item);
    });
    return groups;
  }

  function render() {
    if (!filtered.length) {
      results.innerHTML = '<div class="menu-search-empty">No matching menu items</div>';
      return;
    }

    var html = "";
    var index = 0;
    grouped(filtered).forEach(function (group) {
      html += '<section class="menu-search-group">';
      html += '<h3 class="menu-search-group__label">' + escapeHtml(group.name) + "</h3>";
      group.items.forEach(function (item) {
        html +=
          '<a class="menu-search-item' +
          (index === active ? " is-active" : "") +
          '" role="option" href="' +
          escapeHtml(item.href) +
          '" data-index="' +
          index +
          '">' +
          (item.icon ? '<i class="' + escapeHtml(item.icon) + '" aria-hidden="true"></i>' : "") +
          '<span class="menu-search-item__copy"><strong>' +
          escapeHtml(item.label) +
          "</strong></span></a>";
        index += 1;
      });
      html += "</section>";
    });
    results.innerHTML = html;

    var current = results.querySelector('.menu-search-item[data-index="' + active + '"]');
    if (current) {
      current.scrollIntoView({ block: "nearest" });
    }
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function filter(q) {
    q = (q || "").trim().toLowerCase();
    if (!q) {
      filtered = items.slice();
    } else {
      filtered = items.filter(function (item) {
        return (
          item.label.toLowerCase().indexOf(q) !== -1 ||
          item.category.toLowerCase().indexOf(q) !== -1
        );
      });
    }
    active = 0;
    render();
  }

  function open() {
    collectItems();
    overlay.hidden = false;
    document.body.classList.add("menu-search-open");
    field.value = "";
    filter("");
    window.setTimeout(function () {
      field.focus();
    }, 20);
  }

  function close() {
    overlay.hidden = true;
    document.body.classList.remove("menu-search-open");
  }

  function go(index) {
    var item = filtered[index];
    if (!item) return;

    var href = item.href;
    if (item.el && (href === "#" || item.el.getAttribute("onclick") || item.el.hasAttribute("data-hs-overlay"))) {
      close();
      item.el.click();
      return;
    }

    window.location.href = href;
  }

  openBtn.addEventListener("click", function (e) {
    e.preventDefault();
    open();
  });

  overlay.addEventListener("click", function (e) {
    if (e.target === overlay) close();
  });

  field.addEventListener("input", function () {
    filter(field.value);
  });

  results.addEventListener("mouseover", function (e) {
    var row = e.target.closest(".menu-search-item");
    if (!row) return;
    active = Number(row.getAttribute("data-index") || 0);
    results.querySelectorAll(".menu-search-item").forEach(function (el) {
      el.classList.toggle("is-active", Number(el.getAttribute("data-index")) === active);
    });
  });

  results.addEventListener("click", function (e) {
    var row = e.target.closest(".menu-search-item");
    if (!row) return;
    var item = filtered[Number(row.getAttribute("data-index") || 0)];
    if (!item) return;
    if (item.el && (item.href === "#" || item.el.getAttribute("onclick") || item.el.hasAttribute("data-hs-overlay"))) {
      e.preventDefault();
      go(Number(row.getAttribute("data-index") || 0));
    }
  });

  document.addEventListener("keydown", function (e) {
    var key = e.key;
    var combo = (e.ctrlKey || e.metaKey) && (key === "k" || key === "K");

    if (combo) {
      e.preventDefault();
      if (overlay.hidden) open();
      else field.focus();
      return;
    }

    if (overlay.hidden) return;

    if (key === "Escape") {
      e.preventDefault();
      close();
      return;
    }

    if (key === "ArrowDown") {
      e.preventDefault();
      if (!filtered.length) return;
      active = (active + 1) % filtered.length;
      render();
      return;
    }

    if (key === "ArrowUp") {
      e.preventDefault();
      if (!filtered.length) return;
      active = (active - 1 + filtered.length) % filtered.length;
      render();
      return;
    }

    if (key === "Enter") {
      e.preventDefault();
      go(active);
    }
  });
})();
