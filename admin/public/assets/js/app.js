
try {
    // Dropdown stop
    var dropdownMenus = document.querySelectorAll('.dropdown-menu.stop');
    dropdownMenus.forEach(function (dropdownMenu) {
        dropdownMenu.addEventListener('click', function (event) {
            event.stopPropagation();
        });
    });
} catch (err) {
}

try {
    // Icon
    lucide.createIcons();
} catch (err) { }


try {
    // TopBar Light Dark + persistencia
    var themeConfig = window.APP_THEME || {};
    var themeColorToggle = document.getElementById('light-dark-mode');
    var htmlRoot = document.documentElement;

    function applyTheme(theme) {
        htmlRoot.setAttribute('data-bs-theme', theme);
        htmlRoot.setAttribute('data-startbar', theme);
        if (themeConfig) {
            themeConfig.current = theme;
        }
    }

    function persistTheme(theme) {
        if (!themeConfig.endpoint) return;
        var payload = new URLSearchParams();
        payload.append('theme', theme);
        payload.append('csrf_token', themeConfig.csrf || '');

        fetch(themeConfig.endpoint, {
            method: 'POST',
            body: payload,
            credentials: 'same-origin'
        }).then(function (resp) {
            return resp.json().catch(function () { return {}; });
        }).then(function (data) {
            if (data && data.success && data.theme) {
                themeConfig.current = data.theme;
            }
        }).catch(function () {
            // ignora falha silenciosamente
        });
    }

    if (themeColorToggle) {
        themeColorToggle.addEventListener('click', function () {
            var currentTheme = htmlRoot.getAttribute('data-bs-theme') || themeConfig.current || 'light';
            var nextTheme = currentTheme === 'light' ? 'dark' : 'light';
            applyTheme(nextTheme);
            persistTheme(nextTheme);
        });
    }

    if (themeConfig.current) {
        applyTheme(themeConfig.current);
    }
} catch (err) { }

try {

    // ─── Sidebar: persistencia via localStorage ───────────────────────────────
    var SIDEBAR_KEY = 'pulse_sidebar_size';

    function getSavedSidebarSize() {
        try { return localStorage.getItem(SIDEBAR_KEY); } catch(e) { return null; }
    }

    function setSidebarSize(size, persist) {
        document.body.setAttribute("data-sidebar-size", size);
        if (persist !== false) {
            try { localStorage.setItem(SIDEBAR_KEY, size); } catch(e) {}
        }
    }

    var collapsedToggle = document.querySelector(".mobile-menu-btn");
    var sidebarOverlay   = document.querySelector('.startbar-overlay');

    // Clique no botao de toggle -> salva preferencia do usuario
    if (collapsedToggle) {
        collapsedToggle.addEventListener('click', function () {
            var current = document.body.getAttribute("data-sidebar-size");
            setSidebarSize(current === "collapsed" ? "default" : "collapsed", true);
        });
    }

    // Overlay mobile fecha sem sobrescrever preferencia desktop
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function () {
            setSidebarSize("collapsed", false);
        });
    }

    // Mobile (<992px) forca collapsed; desktop respeita preferencia salva
    var changeSidebarSize = function () {
        if (window.innerWidth < 992) {
            document.body.setAttribute("data-sidebar-size", "collapsed");
        } else {
            var saved = getSavedSidebarSize();
            document.body.setAttribute("data-sidebar-size",
                (saved === "collapsed" || saved === "default") ? saved : "default"
            );
        }
    };

    window.addEventListener('resize', changeSidebarSize);
    changeSidebarSize();

} catch (err) {
}


try {
    function initTooltips() {
        if (!window.bootstrap || !window.bootstrap.Tooltip) {
            return;
        }
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            if (el._tooltipInstance) {
                return;
            }
            el._tooltipInstance = new bootstrap.Tooltip(el);
        });
    }
    window.initTooltips = initTooltips;
    initTooltips();

    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
} catch (err) {
}


/*********************/
/*   Menu Sticky     */
/*********************/
function windowScroll() {
    var navbar = document.getElementById("topbar-custom");
    if (navbar != null) {
        if (
            document.body.scrollTop >= 50 ||
            document.documentElement.scrollTop >= 50
        ) {
            navbar.classList.add("nav-sticky");
        } else {
            navbar.classList.remove("nav-sticky");
        }
    }
}

window.addEventListener('scroll', function (ev) {
    ev.preventDefault();
    windowScroll();
});


var initVerticalMenu = function () {
    var navCollapse = document.querySelectorAll('.navbar-nav li .collapse');
    var navToggle   = document.querySelectorAll(".navbar-nav li [data-bs-toggle='collapse']");

    navToggle.forEach(function (toggle) {
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
        });
    });

    // Abre apenas um menu por vez
    navCollapse.forEach(function (collapse) {
        collapse.addEventListener('show.bs.collapse', function (event) {
            var parent = event.target.closest('.collapse.show');
            document.querySelectorAll('.navbar-nav .collapse.show').forEach(function (element) {
                if (element !== event.target && element !== parent) {
                    var collapseInstance = new bootstrap.Collapse(element);
                    collapseInstance.hide();
                }
            });
        });
    });

    if (document.querySelector(".navbar-nav")) {
        // Ativa item do menu com base na URL atual
        document.querySelectorAll(".navbar-nav a").forEach(function (link) {
            var pageUrl = window.location.href.split(/[?#]/)[0];

            if (link.href === pageUrl) {
                link.classList.add("active");
                link.parentNode.classList.add("active");

                var parentCollapseDiv = link.closest(".collapse");
                while (parentCollapseDiv) {
                    parentCollapseDiv.classList.add("show");
                    parentCollapseDiv.parentElement.children[0].classList.add("active");
                    parentCollapseDiv.parentElement.children[0].setAttribute("aria-expanded", "true");
                    parentCollapseDiv = parentCollapseDiv.parentElement.closest(".collapse");
                }
            }
        });

        setTimeout(function () {
            var activatedItem = document.querySelector('.nav-item li a.active');

            if (activatedItem != null) {
                var simplebarContent = document.querySelector('.main-nav .simplebar-content-wrapper');
                var offset = activatedItem.offsetTop - 300;
                if (simplebarContent && offset > 100) {
                    scrollTo(simplebarContent, offset, 600);
                }
            }
        }, 200);

        function easeInOutQuad(t, b, c, d) {
            t /= d / 2;
            if (t < 1) return c / 2 * t * t + b;
            t--;
            return -c / 2 * (t * (t - 2) - 1) + b;
        }

        function scrollTo(element, to, duration) {
            var start = element.scrollTop, change = to - start, currentTime = 0, increment = 20;
            var animateScroll = function () {
                currentTime += increment;
                var val = easeInOutQuad(currentTime, start, change, duration);
                element.scrollTop = val;
                if (currentTime < duration) {
                    setTimeout(animateScroll, increment);
                }
            };
            animateScroll();
        }
    }
};

initVerticalMenu();
