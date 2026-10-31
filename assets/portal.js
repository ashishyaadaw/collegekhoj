document.addEventListener('DOMContentLoaded', () => {
  const nav = document.querySelector('.nav-links');
  const menuToggle = document.querySelector('.menu-toggle');
  const header = document.querySelector('.site-header');
  const mobileQuery = window.matchMedia('(max-width: 850px)');
  const setDrawer = (open) => {
    if (!nav || !menuToggle) {
      return;
    }
    if (open && header) {
      nav.style.setProperty('--nav-top', `${Math.max(0, header.getBoundingClientRect().bottom)}px`);
    }
    menuToggle.setAttribute('aria-expanded', String(open));
    menuToggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    nav.classList.toggle('open', open);
    document.body.classList.toggle('nav-open', open && mobileQuery.matches);
  };
  menuToggle?.addEventListener('click', () => setDrawer(menuToggle.getAttribute('aria-expanded') !== 'true'));
  nav?.addEventListener('click', (event) => {
    if (event.target.closest('a') && mobileQuery.matches) {
      setDrawer(false);
    }
  });
  mobileQuery.addEventListener('change', (event) => {
    if (!event.matches) {
      setDrawer(false);
    }
  });
  const closeMenus = (except = null) => {
    document.querySelectorAll('.nav-menu').forEach((item) => {
      if (item === except) {
        return;
      }
      item.classList.remove('open');
      item.querySelector('.nav-trigger')?.setAttribute('aria-expanded', 'false');
    });
  };
  document.querySelectorAll('.nav-trigger').forEach((trigger) => {
    trigger.addEventListener('click', () => {
      const menu = trigger.closest('.nav-menu');
      const expanded = trigger.getAttribute('aria-expanded') === 'true';
      closeMenus(menu);
      menu?.classList.toggle('open', !expanded);
      trigger.setAttribute('aria-expanded', String(!expanded));
    });
  });
  document.addEventListener('click', (event) => {
    if (!event.target.closest('.nav-menu')) {
      closeMenus();
    }
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      const openTrigger = document.querySelector('.nav-menu.open .nav-trigger');
      if (openTrigger) {
        closeMenus();
        openTrigger.focus();
      } else if (nav?.classList.contains('open')) {
        setDrawer(false);
        menuToggle?.focus();
      }
    }
  });
});
