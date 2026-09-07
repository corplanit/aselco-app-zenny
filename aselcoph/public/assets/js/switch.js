(function () {
  "use strict";

  const html = document.documentElement;

  function isDark() {
    return html.classList.contains("dark");
  }

  function applyTheme(mode) {
    const dark = mode === "dark";

    html.classList.toggle("dark", dark);
    html.setAttribute("data-theme-mode", dark ? "dark" : "light");
    html.setAttribute("data-header-styles", dark ? "dark" : "light");
    html.setAttribute("data-menu-styles", "dark");
    html.style.colorScheme = dark ? "dark" : "light";
    html.style.setProperty("--primary", "14 124 58");
    html.style.setProperty("--primary-rgb", "14, 124, 58");

    if (dark) {
      localStorage.setItem("layout-theme", "dark");
      localStorage.setItem("xyntradarktheme", "true");
      localStorage.setItem("xyntraMenu", "dark");
      localStorage.setItem("xyntraHeader", "dark");
      localStorage.removeItem("xyntralighttheme");
    } else {
      localStorage.setItem("layout-theme", "light");
      localStorage.removeItem("xyntradarktheme");
      localStorage.removeItem("xyntraMenu");
      localStorage.removeItem("xyntraHeader");
      localStorage.removeItem("darkBgRGB");
      localStorage.removeItem("bodyBgRGB");
      html.style.removeProperty("--body-bg");
      html.style.removeProperty("--dark-bg");
      html.style.removeProperty("--body-bg-rgb");
      html.style.removeProperty("--body-bg-rgb2");
      html.style.removeProperty("--light");
      html.style.removeProperty("--form-control-bg");
      html.style.removeProperty("--input-border");
    }

    localStorage.removeItem("hs_theme");
    syncToggleUi(dark);
  }

  function syncToggleUi(dark) {
    const mode = dark ? "dark" : "light";

    document.querySelectorAll("[data-theme-set]").forEach((btn) => {
      btn.classList.toggle("is-active", btn.getAttribute("data-theme-set") === mode);
      btn.setAttribute("aria-pressed", String(btn.getAttribute("data-theme-set") === mode));
    });

    document.querySelectorAll("[data-hs-theme-click-value]").forEach((btn) => {
      const value = btn.getAttribute("data-hs-theme-click-value");
      btn.classList.toggle("is-active", (dark && value === "dark") || (!dark && value === "light"));
    });
  }

  window.AselcoTheme = {
    apply: applyTheme,
    current: () => (isDark() ? "dark" : "light"),
    toggle: () => applyTheme(isDark() ? "light" : "dark"),
  };

  document.addEventListener("click", (event) => {
    const btn = event.target.closest("[data-hs-theme-click-value], [data-theme-set]");
    if (!btn) return;

    const mode = btn.getAttribute("data-theme-set") || btn.getAttribute("data-hs-theme-click-value");
    if (mode !== "dark" && mode !== "light") return;

    event.preventDefault();
    applyTheme(mode);
  });

  document.addEventListener("DOMContentLoaded", () => {
    applyTheme(isDark() ? "dark" : "light");
    html.classList.add("theme-ready");
  });
})();
