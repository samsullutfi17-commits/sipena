(function () {
    var STORAGE_KEY = 'sipena_sidebar_state';

    function measureHeader() {
        var h = document.querySelector('.header');
        return h ? h.getBoundingClientRect().height : 68;
    }

    function applyHeaderHeight() {
        var hh = measureHeader();
        document.documentElement.style.setProperty('--header-h', hh + 'px');
        document.body.style.paddingTop = hh + 'px';
    }

    function injectToggleButton() {
        var headerFlex = document.querySelector('.header-flex');
        if (!headerFlex || document.getElementById('sidebarToggleBtn')) return;

        var titleDiv = headerFlex.firstElementChild;
        if (!titleDiv) return;

        var leftGroup = document.createElement('div');
        leftGroup.className = 'header-left-group';

        var btn = document.createElement('button');
        btn.id = 'sidebarToggleBtn';
        btn.className = 'sidebar-toggle-btn';
        btn.title = 'Tampilkan / Sembunyikan Sidebar';
        btn.setAttribute('aria-label', 'Toggle Sidebar');
        btn.innerHTML = '<i class="fas fa-bars"></i>';
        btn.addEventListener('click', onToggleClick);

        headerFlex.insertBefore(leftGroup, titleDiv);
        leftGroup.appendChild(btn);
        leftGroup.appendChild(titleDiv);
    }

    function injectBackdrop() {
        if (document.getElementById('sidebarBackdrop')) return;
        var bd = document.createElement('div');
        bd.id = 'sidebarBackdrop';
        bd.className = 'sidebar-backdrop';
        bd.addEventListener('click', closeMobile);
        document.body.appendChild(bd);
    }

    function isMobile() {
        return window.innerWidth <= 768;
    }

    function onToggleClick() {
        if (isMobile()) {
            toggleMobile();
        } else {
            toggleDesktop();
        }
    }

    function toggleDesktop() {
        var container = document.querySelector('.admin-container');
        if (!container) return;
        var hidden = container.classList.toggle('sidebar-collapsed');
        try { localStorage.setItem(STORAGE_KEY, hidden ? 'hidden' : 'visible'); } catch (e) {}
    }

    function toggleMobile() {
        var sidebar = document.querySelector('.admin-sidebar');
        var backdrop = document.getElementById('sidebarBackdrop');
        if (!sidebar) return;
        var open = sidebar.classList.toggle('sidebar-mobile-open');
        if (backdrop) backdrop.classList.toggle('active', open);
        document.body.classList.toggle('sidebar-no-scroll', open);
    }

    function closeMobile() {
        var sidebar = document.querySelector('.admin-sidebar');
        var backdrop = document.getElementById('sidebarBackdrop');
        if (sidebar) sidebar.classList.remove('sidebar-mobile-open');
        if (backdrop) backdrop.classList.remove('active');
        document.body.classList.remove('sidebar-no-scroll');
    }

    function restoreDesktopState() {
        if (isMobile()) return;
        var container = document.querySelector('.admin-container');
        if (!container) return;
        try {
            var saved = localStorage.getItem(STORAGE_KEY);
            if (saved === 'hidden') container.classList.add('sidebar-collapsed');
        } catch (e) {}
    }

    function onResize() {
        applyHeaderHeight();
        if (!isMobile()) {
            closeMobile();
        }
    }

    function init() {
        applyHeaderHeight();
        injectToggleButton();
        injectBackdrop();
        restoreDesktopState();
        window.addEventListener('resize', onResize);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
