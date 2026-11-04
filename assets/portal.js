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
    if (event.key === 'Escape' && !document.querySelector('.ck-modal.is-open')) {
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

  // Counselling modals: [data-open-modal="id"] opens a dialog; [data-counselling] links open the
  // form with their data-course / data-university preselected (their href still works without JS).
  let activeModal = null;
  // After a course is chosen, list only the universities that offer it.
  const filterUniversities = (form) => {
    const course = form.elements.course_id.value;
    const select = form.elements.university_id;
    [...select.options].forEach((option) => {
      const offers = !course || !option.value || option.dataset.courses.split(',').includes(course);
      option.hidden = !offers;
      option.disabled = !offers;
    });
    if (select.selectedOptions[0]?.disabled) {
      select.value = '';
    }
  };
  let lastFocus = null;
  const focusables = (root) => [...root.querySelectorAll('a[href],button:not([disabled]),input:not([type=hidden]):not([tabindex="-1"]),select,textarea')]
    .filter((el) => el.offsetParent !== null);
  const closeModal = () => {
    if (!activeModal) {
      return;
    }
    activeModal.classList.remove('is-open');
    activeModal.hidden = true;
    activeModal = null;
    document.body.classList.remove('modal-open');
    lastFocus?.focus?.();
  };
  const openModal = (id, preset = {}) => {
    const modal = document.getElementById(id);
    if (!modal) {
      return;
    }
    if (activeModal && activeModal !== modal) {
      activeModal.classList.remove('is-open');
      activeModal.hidden = true;
    } else {
      lastFocus = document.activeElement;
    }
    setDrawer(false);
    closeMenus();
    const form = modal.querySelector('.counsel-form');
    if (form) {
      // Reopening after a successful submission shows the form again.
      form.hidden = false;
      modal.querySelector('.counsel-success').hidden = true;
      if (preset.course !== undefined) {
        form.elements.course_id.value = preset.course;
        filterUniversities(form);
      }
      if (preset.university !== undefined) {
        form.elements.university_id.value = preset.university;
      }
      form.elements.source.value = window.location.pathname + window.location.search;
    }
    modal.hidden = false;
    activeModal = modal;
    document.body.classList.add('modal-open');
    requestAnimationFrame(() => {
      modal.classList.add('is-open');
      (focusables(modal).find((el) => el.matches('input,select')) || focusables(modal)[0])?.focus();
    });
  };
  document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-open-modal]');
    const counselLink = event.target.closest('[data-counselling]');
    if (opener) {
      event.preventDefault();
      openModal(opener.dataset.openModal);
    } else if (counselLink) {
      event.preventDefault();
      openModal('counselling-modal', { course: counselLink.dataset.course ?? '', university: counselLink.dataset.university ?? '' });
    } else if (event.target.closest('[data-close-modal]')) {
      closeModal();
    }
  });
  document.addEventListener('keydown', (event) => {
    if (!activeModal) {
      return;
    }
    if (event.key === 'Escape') {
      closeModal();
    } else if (event.key === 'Tab') {
      const items = focusables(activeModal);
      if (!items.length) {
        return;
      }
      const first = items[0];
      const last = items[items.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    }
  });

  const counselForm = document.querySelector('.counsel-form');
  counselForm?.elements.course_id.addEventListener('change', () => filterUniversities(counselForm));
  counselForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const message = counselForm.querySelector('.counsel-message');
    const submit = counselForm.querySelector('.counsel-submit');
    const showError = (text) => {
      message.textContent = text;
      message.hidden = false;
    };
    const invalid = [...counselForm.elements].find((el) => el.willValidate && !el.checkValidity());
    if (invalid) {
      showError(invalid.type === 'checkbox' ? 'Please accept the consent to be contacted.' : `Please enter a valid ${invalid.getAttribute('aria-label').toLowerCase()}.`);
      invalid.focus();
      return;
    }
    message.hidden = true;
    submit.disabled = true;
    try {
      const response = await fetch(counselForm.action, { method: 'POST', body: new FormData(counselForm), headers: { Accept: 'application/json' } });
      const result = await response.json();
      if (!result.ok) {
        showError(result.message);
        return;
      }
      const success = counselForm.parentElement.querySelector('.counsel-success');
      success.querySelector('p').textContent = result.message;
      counselForm.hidden = true;
      success.hidden = false;
      success.querySelector('button')?.focus();
      counselForm.reset();
    } catch {
      showError('Something went wrong. Please try again or call us.');
    } finally {
      submit.disabled = false;
    }
  });

  const params = new URLSearchParams(window.location.search);
  if (params.has('counsel')) {
    openModal('counselling-modal', { course: params.get('course') ?? '', university: params.get('university') ?? '' });
  }

  const backToTop = document.querySelector('.back-to-top');
  if (backToTop) {
    const toggleBackToTop = () => {
      backToTop.hidden = window.scrollY < 400;
    };
    window.addEventListener('scroll', toggleBackToTop, { passive: true });
    toggleBackToTop();
    backToTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
  }
});
