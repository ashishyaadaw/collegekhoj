  </main>
</div>
<script>
(function () {
  var body = document.body, toggle = document.querySelector('.adm-menu-toggle'), backdrop = document.querySelector('.adm-backdrop');
  function setMenu(open) {
    body.classList.toggle('adm-menu-open', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    backdrop.hidden = !open;
  }
  toggle.addEventListener('click', function () { setMenu(!body.classList.contains('adm-menu-open')); });
  backdrop.addEventListener('click', function () { setMenu(false); });

  var search = document.getElementById('adm-search'), links = document.querySelectorAll('.adm-nav-link'), empty = document.querySelector('.adm-nav-empty');
  search.addEventListener('input', function () {
    var q = search.value.trim().toLowerCase(), shown = 0;
    links.forEach(function (link) {
      var match = !q || link.textContent.toLowerCase().indexOf(q) !== -1;
      link.hidden = !match;
      if (match) { shown++; }
    });
    empty.hidden = shown > 0;
    if (q) { setMenu(true); }
  });
  search.addEventListener('keydown', function (event) {
    if (event.key === 'Enter') {
      var first = Array.prototype.find.call(links, function (link) { return !link.hidden; });
      if (first) { window.location.href = first.href; }
    } else if (event.key === 'Escape') {
      search.value = ''; search.dispatchEvent(new Event('input')); search.blur(); setMenu(false);
    }
  });
  document.addEventListener('keydown', function (event) {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); search.focus(); search.select(); }
  });
})();
</script>
</body></html>
