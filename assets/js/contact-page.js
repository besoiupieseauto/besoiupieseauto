/**
 * Contact — FAQ + formular mesaje
 */
(function () {
  'use strict';

  document.querySelectorAll('.ct-faq-q').forEach(function (q) {
    q.addEventListener('click', function () {
      var item = q.closest('.ct-faq-item');
      if (!item) {
        return;
      }
      var wasOpen = item.classList.contains('open');
      document.querySelectorAll('.ct-faq-item').forEach(function (i) {
        i.classList.remove('open');
      });
      if (!wasOpen) {
        item.classList.add('open');
      }
    });
  });

  var form = document.getElementById('contact-form');
  if (!form) {
    return;
  }

  form.addEventListener('submit', async function (event) {
    event.preventDefault();

    var status = document.getElementById('contact-form-status');
    var submitButton = form.querySelector('button[type="submit"]');
    var formData = new FormData(form);

    if (status) {
      status.style.display = 'block';
      status.className = 'status-loading';
      status.textContent = 'Se trimite mesajul...';
    }
    if (submitButton) {
      submitButton.disabled = true;
    }

    try {
      var response = await fetch('/admin/api/messages_endpoint.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          type_product: 'add',
          name: String(formData.get('name') || '').trim(),
          email: String(formData.get('email') || '').trim(),
          phone: String(formData.get('phone') || '').trim(),
          subject: String(formData.get('subject') || 'general').trim(),
          message_body: String(formData.get('message_body') || '').trim(),
          source_url: window.location.href,
          channel: 'website',
          direction: 'inbound',
          delivery_status: 'received',
          bot_status: 'needs_human',
          message_status: 'new',
          is_read: 0
        })
      });
      var result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(result.message || 'Mesajul nu a putut fi trimis.');
      }

      form.reset();
      if (status) {
        status.className = 'status-success';
        status.textContent = '';
        status.innerHTML = '<i class="fa-solid fa-circle-check"></i> Mesajul a fost trimis cu succes. Te contactăm în cel mai scurt timp!';
      }
    } catch (error) {
      if (status) {
        status.className = 'status-error';
        status.textContent = '';
        status.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> ' + (error.message || 'A apărut o eroare la trimitere.');
      }
    } finally {
      if (submitButton) {
        submitButton.disabled = false;
      }
    }
  });
})();
