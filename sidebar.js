document.addEventListener('DOMContentLoaded', () => {
    // 1. I-render ang Lucide icons isang beses lang sa buong page load
    if (window.lucide) {
        lucide.createIcons();
    }

    const sidebar = document.getElementById('sidebar');
    const collapseBtn = document.getElementById('toggleSidebar');
    const htmlEl = document.documentElement;
    const navMenu = document.getElementById('sidebarNavMenu');

    // 2. SIDEBAR TOGGLE & COLLAPSE STATE
    if (collapseBtn && sidebar) {
        // Kunin ang estado mula sa localStorage habang naglo-load pa lang ang pahina
        if (localStorage.getItem('sidebar-collapsed') === 'true') {
            sidebar.classList.add('collapsed');
        }

        // Alisin ang anti-flicker class mula sa html tag para bumalik sa normal ang transition
        htmlEl.classList.remove('sidebar-is-collapsed');

        let isAnimating = false;

        collapseBtn.addEventListener('click', () => {
            if (isAnimating) return;
            isAnimating = true;

            // I-toggle ang collapsed class sa sidebar
            sidebar.classList.toggle('collapsed');
            const isCollapsed = sidebar.classList.contains('collapsed');

            // I-save ang state sa localStorage
            localStorage.setItem('sidebar-collapsed', isCollapsed);

            // I-release ang lock pagkatapos ng transition duration para iwas-glitch sa sunud-sunod na click
            setTimeout(() => {
                isAnimating = false;
            }, 250);
        });
    }

    // 3. PRESERVE SIDEBAR SCROLL POSITION
    if (navMenu) {
        const savedScroll = sessionStorage.getItem('sidebar-scroll-pos');
        if (savedScroll !== null) {
            requestAnimationFrame(() => {
                navMenu.scrollTop = parseInt(savedScroll, 10);
            });
        }

        navMenu.addEventListener('scroll', () => {
            sessionStorage.setItem('sidebar-scroll-pos', navMenu.scrollTop);
        });

        navMenu.querySelectorAll('.nav-item').forEach((link) => {
            link.addEventListener('click', () => {
                sessionStorage.setItem('sidebar-scroll-pos', navMenu.scrollTop);
            });
        });
    }
});