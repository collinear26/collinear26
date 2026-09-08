document.addEventListener('DOMContentLoaded', () => {
    // 1. I-render ang Lucide icons isang beses lang sa buong page load
    if (window.lucide) {
        lucide.createIcons();
    }

    const sidebar = document.getElementById('sidebar');
    const collapseBtn = document.getElementById('toggleSidebar');
    const htmlEl = document.documentElement;
    const navMenu = document.getElementById('sidebarNavMenu');
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    const MOBILE_BREAKPOINT = 768; // dapat tugma sa @media (max-width: 768px) sa style.css
    const isMobileViewport = () => window.innerWidth <= MOBILE_BREAKPOINT;

    // MOBILE DRAWER: bukas/sara ng off-canvas sidebar sa maliit na screen
    function openMobileSidebar() {
        sidebar.classList.add('mobile-open');
        sidebarBackdrop.classList.add('visible');
    }
    function closeMobileSidebar() {
        sidebar.classList.remove('mobile-open');
        sidebarBackdrop.classList.remove('visible');
    }

    if (mobileMenuBtn && sidebar && sidebarBackdrop) {
        mobileMenuBtn.addEventListener('click', () => {
            if (sidebar.classList.contains('mobile-open')) {
                closeMobileSidebar();
            } else {
                openMobileSidebar();
            }
        });

        sidebarBackdrop.addEventListener('click', closeMobileSidebar);

        // Isara ang drawer pagkatapos pumili ng nav item (mas madaling makita
        // ang bagong page sa halip na natatakpan pa rin ito ng sidebar)
        if (navMenu) {
            navMenu.querySelectorAll('.nav-item').forEach((link) => {
                link.addEventListener('click', () => {
                    if (isMobileViewport()) closeMobileSidebar();
                });
            });
        }

        // Kung lumaki ulit ang window pabalik sa desktop width habang bukas
        // ang mobile drawer, i-reset ito para hindi na ma-stuck sa "open" state
        window.addEventListener('resize', () => {
            if (!isMobileViewport()) closeMobileSidebar();
        });
    }

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

            // Sa mobile, ibang gamit ang parehong button: isinasara na lang
            // nito ang buong drawer sa halip na i-toggle ang icon-only mode
            // (walang silbi ang collapse-to-icons kung naka-overlay na ito)
            if (isMobileViewport()) {
                closeMobileSidebar();
                setTimeout(() => { isAnimating = false; }, 250);
                return;
            }

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

    // 3. DARK MODE TOGGLE
    const themeToggleBtn = document.getElementById('themeToggleBtn');
    if (themeToggleBtn) {
        // Alamin ang KASALUKUYANG effective theme (hindi lang ang naka-save sa
        // localStorage — kung wala pang naka-save, sundin ang OS preference,
        // kaparehong logic ng ginagamit ng anti-flicker script/CSS media query)
        function getEffectiveTheme() {
            const saved = localStorage.getItem('theme');
            if (saved === 'dark' || saved === 'light') return saved;
            return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        function applyTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            themeToggleBtn.setAttribute('aria-checked', theme === 'dark' ? 'true' : 'false');
        }

        applyTheme(getEffectiveTheme());

        themeToggleBtn.addEventListener('click', () => {
            const next = getEffectiveTheme() === 'dark' ? 'light' : 'dark';
            localStorage.setItem('theme', next);
            applyTheme(next);
        });
    }

    // 4. PRESERVE SIDEBAR SCROLL POSITION
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