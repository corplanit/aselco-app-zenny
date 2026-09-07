<footer
    class="mt-auto py-4 bg-white dark:bg-bodybg text-center border-t border-defaultborder dark:border-defaultborder/10">
    <div class="container-fluid">
        <span class="text-textmuted dark:text-textmuted/50">
            Copyright © <span id="year"></span>
            <a href="https://aselco.ph/" class="text-dark font-medium">Agusan del Sur Electric Cooperative, Inc. | San Francisco</a>.
            All rights reserved.
        </span>
    </div>
</footer>

<div class="hs-overlay ti-modal hidden" id="header-responsive-search" tabindex="-1"
    aria-labelledby="header-responsive-search" aria-hidden="true">
    <div class="ti-modal-box">
        <div class="ti-modal-dialog">
            <div class="ti-modal-content">
                <div class="ti-modal-body">
                    <div class="input-group">
                        <input type="text" class="form-control border-end-0 !border-s"
                            placeholder="Search Anything ..." aria-label="Search Anything ..."
                            aria-describedby="button-addon2">
                        <button class="ti-btn ti-btn-primary !m-0" type="button" id="button-addon2"><i
                                class="bi bi-search"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function remove_data(id, type) {
        Swal.fire({
            title: "Are you sure?",
            text: "You won't be able to revert this!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: "Yes, delete it!"
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/delete',
                    type: 'post',
                    headers: {
                        'X-CSRF-Token': '{{ csrf_token() }}'
                    },
                    data: {
                        id: id,
                        type: type
                    },
                    success: function(response) {
                        Swal.fire({
                            title: "Deleted!",
                            text: "Your record has been deleted.",
                            icon: "success"
                        });
                        setTimeout(() => {
                            window.location.href = response;
                        }, 2000);
                    },
                    error: function(xhr, status, error) {
                        Swal.fire({
                            title: "Error!",
                            text: "There was a problem deleting your record. " + error,
                            icon: "error"
                        });
                    }
                });
            }
        });
    }
</script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const currentPath = (window.location.pathname.replace(/\/+$/, "") || "/").toLowerCase();
        const menuItems = Array.from(document.querySelectorAll(".app-sidebar .side-menu__item"));

        function itemPath(item) {
            const href = item.getAttribute("href");
            if (!href || href === "#" || href.startsWith("javascript:")) return "";
            try {
                const url = new URL(href, window.location.origin);
                if (url.origin !== window.location.origin) return "";
                return (url.pathname.replace(/\/+$/, "") || "/");
            } catch (e) {
                return href.split("?")[0].replace(/\/+$/, "") || "";
            }
        }

        menuItems.forEach((item) => item.classList.remove("active-menu", "active"));

        let best = null;
        let bestLen = -1;

        menuItems.forEach((item) => {
            const path = itemPath(item).toLowerCase();
            if (!path) return;

            const exact = currentPath === path;
            const nested = path !== "/" && currentPath.startsWith(path + "/");
            if (!exact && !nested) return;

            if (path.length > bestLen) {
                best = item;
                bestLen = path.length;
            }
        });

        if (!best) return;

        best.classList.add("active-menu", "active");

        const submenu = best.closest("ul.slide-menu");
        if (submenu) {
            submenu.style.display = "block";
            const parentLi = submenu.closest("li.slide.has-sub");
            if (parentLi) {
                parentLi.classList.add("open", "active");
                const parentLink = parentLi.querySelector(":scope > a.side-menu__item");
                if (parentLink) {
                    parentLink.classList.add("active-parent-menu", "active");
                }
            }
        }

        function scrollActiveIntoView(el) {
            const sidebar = document.getElementById("sidebar-scroll");
            if (!sidebar || !el) return;

            const scroller = sidebar.querySelector(".simplebar-content-wrapper") || sidebar;
            const scrollerRect = scroller.getBoundingClientRect();
            const elRect = el.getBoundingClientRect();
            const pad = 12;
            const fullyVisible = elRect.top >= scrollerRect.top + pad && elRect.bottom <= scrollerRect.bottom - pad;
            if (fullyVisible) return;

            const offset = elRect.top - scrollerRect.top;
            let target = scroller.scrollTop + offset - scroller.clientHeight / 2 + el.offsetHeight / 2;
            const max = Math.max(0, scroller.scrollHeight - scroller.clientHeight);
            if (target < 0) target = 0;
            if (target > max) target = max;

            if (typeof scroller.scrollTo === "function") {
                scroller.scrollTo({ top: target, behavior: "smooth" });
            } else {
                scroller.scrollTop = target;
            }
        }

        window.requestAnimationFrame(function () {
            window.setTimeout(function () {
                scrollActiveIntoView(best);
            }, 60);
        });
    });
</script>


<style>
    /* Initial state of the .custom-box (hidden and shifted down) */
    .custom-box {
        opacity: 0;
        /* Initially hidden */
        transform: translateY(20px);
        /* Initially moved down */
        animation: fadeUp 0.6s ease-out forwards;
        /* Trigger fade-up animation */
    }

    /* Define the fade-up animation */
    @keyframes fadeUp {
        0% {
            opacity: 0;
            transform: translateY(20px);
            /* Starts down */
        }

        100% {
            opacity: 1;
            transform: translateY(0);
            /* Ends at normal position */
        }
    }
</style>



@if (session('success'))
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Saved Successfully',
            // text: '{{ session('success') }}',
            confirmButtonText: 'OK'
        });
    </script>
@endif

@if (session('error'))
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: '{{ session('error') }}',
            confirmButtonText: 'Try Again'
        });
    </script>
@endif
