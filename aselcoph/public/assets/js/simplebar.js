(function () {
  "use strict";

  var myElement = document.getElementById("sidebar-scroll");
  if (!myElement) return;

  var bar = new SimpleBar(myElement, { autoHide: true });

  function fitSidebarScroll() {
    try {
      bar.recalculate();
      var wrap = myElement.querySelector(".simplebar-content-wrapper");
      if (wrap) {
        wrap.style.overflowY = "auto";
      }
    } catch (e) {}
  }

  window.addEventListener("resize", fitSidebarScroll);
  requestAnimationFrame(fitSidebarScroll);
})();
