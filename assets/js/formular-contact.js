/**
 * Formular contact standalone — HTML/CSS/JS
 */
(function () {
  'use strict';

  var form = document.getElementById('fc-form');
  if (!form) {
    return;
  }

  var statusEl = document.getElementById('fc-status');
  var submitBtn = form.querySelector('.fc-submit');

  var API_URL = form.dataset.endpoint || '/admin/api/messages_endpoint.php';

  function showStatus(type, message) {
    if (!statusEl) {
      return;
    }
    statusEl.className = 'fc-status is-visible fc-status--' + type;
    statusEl.textContent = message;
  }

  function clearFieldErrors() {
    form.querySelectorAll('.fc-field.is-invalid').forEach(function (field) {
      field.classList.remove('is-invalid');
    });
  }

  function setFieldError(name, message) {
    var field = form.querySelector('.fc-field[data-field="' + name + '"]');
    if (!field) {
      return;
    }
    field.classList.add('is-invalid');
    var err = field.querySelector('.fc-field-error');
    if (err) {
      err.textContent = message;
    }
  }

  function validateEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
  }

  function validatePhone(value) {
    if (!value) {
      return true;
    }
    var digits = value.replace(/\D/g, '');
    return digits.length >= 9 && digits.length <= 12;
  }

  function validateForm(data) {
    clearFieldErrors();
    var ok = true;

    if (!data.name) {
      setFieldError('name', 'Introdu numele tău.');
      ok = false;
    }
    if (!data.email) {
      setFieldError('email', 'Email obligatoriu.');
      ok = false;
    } else if (!validateEmail(data.email)) {
      setFieldError('email', 'Adresă de email invalidă.');
      ok = false;
    }
    if (data.phone && !validatePhone(data.phone)) {
      setFieldError('phone', 'Număr de telefon invalid (min. 9 cifre).');
      ok = false;
    }
    if (!data.message_body || data.message_body.length < 10) {
      setFieldError('message_body', 'Mesajul trebuie să aibă cel puțin 10 caractere.');
      ok = false;
    }

    return ok;
  }

  function readForm() {
    var fd = new FormData(form);
    return {
      name: String(fd.get('name') || '').trim(),
      email: String(fd.get('email') || '').trim(),
      phone: String(fd.get('phone') || '').trim(),
      subject: String(fd.get('subject') || 'general').trim(),
      message_body: String(fd.get('message_body') || '').trim()
    };
  }

  async function sendPayload(data) {
    var response = await fetch(API_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        type_product: 'add',
        name: data.name,
        email: data.email,
        phone: data.phone,
        message_body: data.message_body,
        subject: data.subject,
        channel: 'website',
        direction: 'inbound',
        delivery_status: 'received',
        bot_status: 'needs_human',
        message_status: 'new',
        is_read: 0
      })
    });

    var result = {};
    try {
      result = await response.json();
    } catch (_) {
      throw new Error('Răspuns invalid de la server.');
    }

    if (!response.ok || !result.success) {
      throw new Error(result.message || 'Mesajul nu a putut fi trimis.');
    }

    return result;
  }

  form.addEventListener('submit', async function (event) {
    event.preventDefault();

    var data = readForm();
    if (!validateForm(data)) {
      showStatus('error', 'Verifică câmpurile marcate.');
      return;
    }

    if (submitBtn) {
      submitBtn.disabled = true;
    }
    showStatus('loading', 'Se trimite mesajul…');

    try {
      await sendPayload(data);
      form.reset();
      showStatus('success', 'Mesaj trimis! Te contactăm în cel mai scurt timp.');
    } catch (error) {
      showStatus('error', error.message || 'Eroare la trimitere. Încearcă din nou sau sună-ne.');
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
      }
    }
  });

  form.querySelectorAll('input, textarea, select').forEach(function (el) {
    el.addEventListener('input', function () {
      var field = el.closest('.fc-field');
      if (field) {
        field.classList.remove('is-invalid');
      }
    });
  });
})();
