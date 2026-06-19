(function() {
    const mobileBtn = document.getElementById('mobile-menu-btn');
    const mobileNav = document.getElementById('mobile-nav');
    const overlay = document.getElementById('menu-overlay');
    const closeBtn = document.getElementById('close-mobile-menu');

    function closeMenu() {
        if (mobileNav) mobileNav.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
        document.body.classList.remove('menu-open');
    }

    function openMenu() {
        if (mobileNav) mobileNav.classList.add('open');
        if (overlay) overlay.classList.add('active');
        document.body.classList.add('menu-open');
    }

    if (mobileBtn) {
        mobileBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (mobileNav && mobileNav.classList.contains('open')) closeMenu();
            else openMenu();
        });
    }
    if (overlay) overlay.addEventListener('click', closeMenu);
    if (closeBtn) closeBtn.addEventListener('click', closeMenu);
    if (mobileNav) {
        mobileNav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', closeMenu);
        });
    }
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            const target = document.querySelector(targetId);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth' });
                closeMenu();
            }
        });
    });
})();