/**
 * Main JS – minimal UI interactions
 */
(function () {
  'use strict';

  // Auto-dismiss flash alerts after 6 seconds
  document.querySelectorAll('#flash-messages .alert').forEach(function (el) {
    setTimeout(function () {
      var bsAlert = bootstrap.Alert.getOrCreateInstance(el);
      bsAlert.close();
    }, 6000);
  });

  // Confirm on links with data-confirm attribute
  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-confirm]');
    if (!el) return;
    if (!window.confirm(el.getAttribute('data-confirm') || 'Confirmar esta ação?')) {
      e.preventDefault();
    }
  });

  // Password strength indicator
  var pwInput = document.querySelector('input[name="password"], input[name="new_password"]');
  if (pwInput) {
    var feedback = document.createElement('div');
    feedback.className = 'progress mt-1';
    feedback.style.height = '4px';
    feedback.innerHTML = '<div id="pw-strength-bar" class="progress-bar" style="width:0"></div>';
    pwInput.parentNode.appendChild(feedback);

    pwInput.addEventListener('input', function () {
      var val = this.value;
      var score = 0;
      if (val.length >= 8)            score++;
      if (/[A-Z]/.test(val))          score++;
      if (/[0-9]/.test(val))          score++;
      if (/[^A-Za-z0-9]/.test(val))   score++;

      var bar    = document.getElementById('pw-strength-bar');
      var colors = ['bg-danger','bg-warning','bg-info','bg-success'];
      var width  = (score / 4) * 100;
      bar.style.width = width + '%';
      bar.className   = 'progress-bar ' + (colors[score - 1] || '');
    });
  }

  // Preview logo URL
  var logoInput = document.querySelector('input[name="logo_url"]');
  if (logoInput) {
    var preview = document.createElement('img');
    preview.style.cssText = 'max-height:48px;margin-top:6px;display:none';
    preview.alt = 'Preview';
    logoInput.parentNode.appendChild(preview);

    function updateLogoPreview() {
      var url = logoInput.value.trim();
      if (url) {
        preview.src   = url;
        preview.style.display = 'block';
      } else {
        preview.style.display = 'none';
      }
    }

    logoInput.addEventListener('input', updateLogoPreview);
    updateLogoPreview();
  }

  // Sync color pickers with text inputs (fallback, in case inline handlers fail)
  document.querySelectorAll('input[type="color"]').forEach(function (picker) {
    var textId = picker.id === 'colorPrimary'   ? 'primaryHex'   :
                 picker.id === 'colorSecondary' ? 'secondaryHex' : null;
    if (!textId) return;
    var textInput = document.getElementById(textId);
    if (!textInput) return;

    picker.addEventListener('input', function () {
      textInput.value = this.value;
    });
    textInput.addEventListener('input', function () {
      if (/^#[0-9a-fA-F]{6}$/.test(this.value)) {
        picker.value = this.value;
      }
    });
  });

  // Toggle password visibility
  document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var target = document.getElementById(this.getAttribute('data-toggle-password'));
      if (!target) return;
      target.type = target.type === 'password' ? 'text' : 'password';
      var icon = this.querySelector('i');
      if (icon) {
        icon.classList.toggle('bi-eye');
        icon.classList.toggle('bi-eye-slash');
      }
    });
  });

  // Tooltip initialization
  var tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
  tooltips.forEach(function (el) {
    new bootstrap.Tooltip(el);
  });

})();
