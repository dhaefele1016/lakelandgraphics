/* Lakeland Graphics — Contact Us form behaviour
   Validation and real submit to send-contact.php. The department <select> sends
   a key (e.g. "Sales Inquiries"); the server maps it to recipients, so no staff addresses
   ever reach the browser. */
(function () {
  var form = document.getElementById('contactForm');
  if (!form) return;

  var wrap = document.getElementById('cform');

  // Timestamp used server-side as a simple bot check.
  var loadedAt = Date.now();

  /* ---------- validation ---------- */
  var required = ['name', 'email', 'department', 'comment'];
  function validate() {
    var ok = true;
    required.forEach(function (id) {
      var el = document.getElementById('c_' + id);
      var field = el.closest('.field');
      var valid = (el.value || '').trim() !== '';
      if (id === 'email') valid = valid && /.+@.+\..+/.test(el.value.trim());
      field.classList.toggle('field--invalid', !valid);
      if (!valid && ok) { el.focus(); }
      if (!valid) ok = false;
    });
    return ok;
  }

  // clear error as the user types / picks
  function clearField(e) {
    var field = e.target.closest && e.target.closest('.field--invalid');
    if (field) field.classList.remove('field--invalid');
  }
  form.addEventListener('input', clearField);
  form.addEventListener('change', clearField);

  var submitBtn = form.querySelector('button[type="submit"]');
  var errBox = document.getElementById('formError');

  function showError(msg) {
    if (!errBox) { window.alert(msg); return; }
    errBox.textContent = msg;
    errBox.hidden = false;
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (errBox) errBox.hidden = true;
    if (!validate()) return;

    var name = document.getElementById('c_name').value.trim();
    var dept = document.getElementById('c_department');
    var deptLabel = dept.options[dept.selectedIndex].text;

    var data = new FormData();
    data.append('name', name);
    data.append('company', document.getElementById('c_company').value.trim());
    data.append('email', document.getElementById('c_email').value.trim());
    data.append('phone', document.getElementById('c_phone').value.trim());
    data.append('department', dept.value);
    data.append('comment', document.getElementById('c_comment').value.trim());
    data.append('website', (document.getElementById('c_website') || {}).value || '');
    data.append('started', String(loadedAt));

    var label = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = 'Sending…'; }

    fetch('send-contact.php', { method: 'POST', body: data })
      .then(function (res) {
        return res.json().catch(function () { throw new Error('Unexpected server response.'); })
          .then(function (json) {
            if (!res.ok || !json.ok) throw new Error(json.error || 'Something went wrong.');
            return json;
          });
      })
      .then(function () {
        document.getElementById('recapName').textContent = name;
        document.getElementById('recapDept').textContent = deptLabel;
        wrap.classList.add('is-sent');
        window.scrollTo({ top: (wrap.getBoundingClientRect().top + window.scrollY - 120), behavior: 'smooth' });
      })
      .catch(function (err) {
        showError(err.message || 'We could not send your message. Please call 800.495.8107.');
      })
      .finally(function () {
        if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = label; }
      });
  });

  var resetBtn = document.getElementById('cReset');
  if (resetBtn) resetBtn.addEventListener('click', function () {
    form.reset();
    loadedAt = Date.now();
    wrap.classList.remove('is-sent');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
})();
