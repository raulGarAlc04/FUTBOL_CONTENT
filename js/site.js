(function () {
    var toggle = document.querySelector('[data-nav-toggle]');
    var panels = document.querySelectorAll('[data-nav-panel]');
    if (!toggle || !panels.length) return;

    function isOpen() {
        return panels[0].classList.contains('is-open');
    }

    function setOpen(open) {
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        panels.forEach(function (p) {
            p.classList.toggle('is-open', open);
            if (p.classList.contains('nav-overlay')) {
                p.setAttribute('aria-hidden', open ? 'false' : 'true');
            }
        });
        document.body.classList.toggle('nav-open', open);
    }

    toggle.addEventListener('click', function () {
        setOpen(!isOpen());
    });

    panels.forEach(function (p) {
        p.addEventListener('click', function (e) {
            if (p.classList.contains('nav-overlay')) setOpen(false);
            if (e.target.closest('a')) setOpen(false);
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') setOpen(false);
    });
})();
