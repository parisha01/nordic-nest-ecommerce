/* =========================================================
   NORDIC NEST — site.js (dynamic version)
   Handles: mobile nav toggle, gallery lightbox, and
            progressive-enhancement client-side field hints.

   IMPORTANT: unlike the static assignment, forms here submit
   for real to PHP, which performs the authoritative
   server-side validation, error handling and database writes.
   The JS below only gives instant on-blur feedback as a UX
   nicety — it never blocks or fakes a submission.
   ========================================================= */

document.addEventListener('DOMContentLoaded', function () {

  /* ---------- Mobile nav toggle ---------- */
  var navToggle = document.querySelector('.nav-toggle');
  var mainNav = document.querySelector('.main-nav');

  if (navToggle && mainNav) {
    navToggle.addEventListener('click', function () {
      var isOpen = mainNav.classList.toggle('is-open');
      navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      navToggle.textContent = isOpen ? '✕' : '☰';
    });
  }

  /* ---------- Gallery lightbox ---------- */
  var galleryItems = document.querySelectorAll('.gallery-item');
  var lightbox = document.getElementById('lightbox');

  if (galleryItems.length && lightbox) {
    var lightboxImg = lightbox.querySelector('img');
    var lightboxCaption = lightbox.querySelector('.lightbox-caption');
    var closeBtn = lightbox.querySelector('.lightbox-close');
    var lastFocused = null;

    function openLightbox(item) {
      var fullSrc = item.getAttribute('data-full') || item.querySelector('img').src;
      var caption = item.getAttribute('data-caption') || '';
      lightboxImg.src = fullSrc;
      lightboxImg.alt = item.querySelector('img').alt || '';
      lightboxCaption.textContent = caption;
      lastFocused = document.activeElement;
      lightbox.classList.add('is-open');
      lightbox.setAttribute('aria-hidden', 'false');
      closeBtn.focus();
      document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
      lightbox.classList.remove('is-open');
      lightbox.setAttribute('aria-hidden', 'true');
      lightboxImg.src = '';
      document.body.style.overflow = '';
      if (lastFocused) lastFocused.focus();
    }

    galleryItems.forEach(function (item) {
      item.addEventListener('click', function () { openLightbox(item); });
      item.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          openLightbox(item);
        }
      });
    });

    closeBtn.addEventListener('click', closeLightbox);
    lightbox.addEventListener('click', function (e) {
      if (e.target === lightbox) closeLightbox();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && lightbox.classList.contains('is-open')) {
        closeLightbox();
      }
    });
  }

  /* ---------------------------------------------------------
     Progressive-enhancement validation hints.
     Applies to any <form class="contact-form"> on the site
     (register, login, contact, review, product form, etc).
     Shows a live hint on blur using each field's existing
     HTML5 validity API (required, minlength, type=email, ...),
     but never intercepts submit — the real check happens in
     PHP once the form reaches the server.
     --------------------------------------------------------- */
  document.querySelectorAll('form.contact-form').forEach(function (form) {
    form.querySelectorAll('input, textarea, select').forEach(function (field) {
      field.addEventListener('blur', function () {
        var row = field.closest('.form-row');
        if (!row) return;
        var errorEl = row.querySelector('.field-error');

        if (field.checkValidity() || field.value.trim() === '') {
          // Don't fight the server-rendered error message that may already
          // be showing from a previous submission — only clear a purely
          // client-side hint we added ourselves.
          if (errorEl && errorEl.dataset.clientHint === 'true') {
            row.classList.remove('has-error');
            errorEl.textContent = '';
          }
        } else if (field.value.trim() !== '') {
          row.classList.add('has-error');
          if (!errorEl) {
            errorEl = document.createElement('p');
            errorEl.className = 'field-error';
            row.appendChild(errorEl);
          }
          errorEl.dataset.clientHint = 'true';
          errorEl.textContent = field.validationMessage;
        }
      });
    });
  });

});
