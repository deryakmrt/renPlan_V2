
/*! dropdown_fix.js — keeps hover dropdowns open while moving into submenu; adds touch/keyboard support
   Usage: include after your HTML and before closing </body>:
   <script src="dropdown_fix.js"></script>
*/
(function () {
  const OPEN_CLASS = 'open';
  const HOVER_OPEN_DELAY_MS = 60;
  const HOVER_CLOSE_DELAY_MS = 180;

  /** Utility: is `child` inside `parent`? */
  function isInside(child, parent) {
    if (!child) return false;
    return parent === child || parent.contains(child);
  }

  /** Close all other dropdowns except el (if provided) */
  function closeOthers(except) {
    document.querySelectorAll('.has-dropdown.' + OPEN_CLASS).forEach(dd => {
      if (except && dd === except) return;
      dd.classList.remove(OPEN_CLASS);
    });
  }

  /** Setup one dropdown */
  function setup(dd) {
    let openTimer = null;
    let closeTimer = null;

    // prefer the first direct anchor/button as trigger
    const trigger = dd.querySelector(':scope > a, :scope > button, :scope > .trigger') || dd;

    function open() {
      if (dd.classList.contains(OPEN_CLASS)) return;
      closeOthers(dd);
      dd.classList.add(OPEN_CLASS);
    }
    function close() {
      dd.classList.remove(OPEN_CLASS);
    }

    // --- Hover (desktop) ---
    dd.addEventListener('mouseenter', () => {
      if (closeTimer) { clearTimeout(closeTimer); closeTimer = null; }
      openTimer = setTimeout(open, HOVER_OPEN_DELAY_MS);
    });
    dd.addEventListener('mouseleave', (ev) => {
      if (openTimer) { clearTimeout(openTimer); openTimer = null; }
      const to = ev.relatedTarget;
      // if moving to a child (submenu), don't close
      if (isInside(to, dd)) return;
      closeTimer = setTimeout(() => {
        // still outside? then close
        if (!isInside(document.activeElement, dd) && !isInside(to, dd)) close();
      }, HOVER_CLOSE_DELAY_MS);
    });

    // --- Focus (keyboard) ---
    dd.addEventListener('focusin', open);
    dd.addEventListener('focusout', (ev) => {
      const to = ev.relatedTarget;
      if (!isInside(to, dd)) {
        // add a tiny delay so focus can land inside menu items
        setTimeout(() => { if (!dd.contains(document.activeElement)) close(); }, 50);
      }
    });

    // --- Touch/Click toggle ---
    trigger.addEventListener('click', (ev) => {
      // If the trigger is a navigation link and user intends to open menu, prevent immediate navigation on touch
      // Only prevent if this dropdown actually has a menu
      const menu = dd.querySelector(':scope > .dropdown-menu');
      if (menu) {
        // If already open, let the click pass through (second click can navigate)
        if (!dd.classList.contains(OPEN_CLASS)) {
          ev.preventDefault();
          ev.stopPropagation();
          open();
        }
      }
    });

    // stop clicks inside menu from closing immediately
    dd.addEventListener('click', (ev) => {
      if (isInside(ev.target, dd.querySelector(':scope > .dropdown-menu'))) {
        ev.stopPropagation();
      }
    });
  }

  // Click outside closes all
  document.addEventListener('click', (ev) => {
    const dd = ev.target.closest('.has-dropdown');
    if (!dd) closeOthers(null);
  });

  // Escape closes all
  document.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape') closeOthers(null);
  });

  // Initialize
  document.querySelectorAll('.has-dropdown').forEach(setup);

  // Support dynamically added dropdowns
  const mo = new MutationObserver((muts) => {
    muts.forEach(m => {
      m.addedNodes.forEach(n => {
        if (!(n instanceof Element)) return;
        if (n.matches && n.matches('.has-dropdown')) setup(n);
        n.querySelectorAll && n.querySelectorAll('.has-dropdown').forEach(setup);
      });
    });
  });
  mo.observe(document.documentElement, { childList: true, subtree: true });
})();
