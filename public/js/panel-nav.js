(function () {
    var root = document.documentElement;
    var openBtn = document.getElementById('nav-abrir');
    var closeBtn = document.getElementById('nav-cerrar');
    var backdrop = document.getElementById('nav-backdrop');
    var aside = document.getElementById('panel-nav');

    if (!openBtn || !aside) {
        return;
    }

    var esMovil = window.matchMedia('(max-width: 767.98px)');

    function syncAria(abierta) {
        var movil = esMovil.matches;
        openBtn.setAttribute('aria-expanded', abierta ? 'true' : 'false');
        aside.setAttribute('aria-hidden', movil && !abierta ? 'true' : 'false');
        if (backdrop) {
            backdrop.setAttribute('aria-hidden', abierta ? 'false' : 'true');
        }
    }

    function abrir() {
        root.classList.add('nav-abierta');
        syncAria(true);
    }

    function cerrar() {
        root.classList.remove('nav-abierta');
        syncAria(false);
    }

    syncAria(false);

    openBtn.addEventListener('click', abrir);
    if (closeBtn) {
        closeBtn.addEventListener('click', cerrar);
    }
    if (backdrop) {
        backdrop.addEventListener('click', cerrar);
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && root.classList.contains('nav-abierta')) {
            cerrar();
        }
    });

    window.matchMedia('(min-width: 768px)').addEventListener('change', function (e) {
        if (e.matches) {
            cerrar();
        }
    });
})();
