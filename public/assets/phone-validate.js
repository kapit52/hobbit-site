// Телефон: всегда начинается с +7, затем ровно 10 цифр.
(function () {
  function national(val) {
    var d = (val || '').replace(/\D/g, '');
    if (d.length && (d[0] === '7' || d[0] === '8')) d = d.slice(1);
    return d.slice(0, 10);
  }

  function attach(inp) {
    inp.setAttribute('inputmode', 'numeric');
    inp.setAttribute('maxlength', '12'); // «+7» и 10 цифр
    if (!inp.placeholder) inp.placeholder = '+7 900 000-00-00';
    if (!inp.title) inp.title = 'Номер в формате +7 и 10 цифр';

    function sync() {
      var n = national(inp.value);
      inp.value = '+7' + n;
      var ok = n.length === 10 || (n.length === 0 && !inp.required);
      inp.setCustomValidity(ok ? '' : 'Введите 10 цифр после +7');
    }

    // Изначально поле показывает «+7»
    if (national(inp.value).length === 0) inp.value = '+7';
    else sync();

    inp.addEventListener('focus', function () { if (!inp.value) inp.value = '+7'; });
    inp.addEventListener('input', sync);
    inp.addEventListener('keydown', function (e) {
      // Не даём стереть префикс «+7»
      var start = inp.selectionStart, end = inp.selectionEnd;
      if ((e.key === 'Backspace' && start <= 2 && end <= 2) ||
          (e.key === 'Delete' && start < 2)) {
        e.preventDefault();
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[type="tel"]').forEach(attach);
  });
})();
