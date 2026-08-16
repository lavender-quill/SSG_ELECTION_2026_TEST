(function () {
    var sidebar = document.querySelector('.sidebar');
    var layout  = document.querySelector('.layout');
    if (!sidebar || !layout) return;

    function closeSidebar() {
        sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('open');
        document.body.style.overflow = '';
    }

    function toggleSidebar() {
        var open = sidebar.classList.toggle('open');
        if (overlay) overlay.classList.toggle('open', open);
        document.body.style.overflow = open ? 'hidden' : '';
    }

    var overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    overlay.addEventListener('click', closeSidebar);
    layout.insertBefore(overlay, layout.firstChild);

    var closeBtn = document.createElement('button');
    closeBtn.className = 'sidebar-close-btn';
    closeBtn.innerHTML = '&times;';
    closeBtn.setAttribute('aria-label', 'Close menu');
    closeBtn.addEventListener('click', closeSidebar);
    sidebar.appendChild(closeBtn);

    window.toggleSidebar = toggleSidebar;
    window.closeSidebar  = closeSidebar;

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeSidebar();
    });
})();
