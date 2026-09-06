<script>
(function () {
  if (window.ccbPrestaPaginationReady) {
    return;
  }

  window.ccbPrestaPaginationReady = true;
  document.addEventListener('click', function (event) {
    var target = event.target;
    if (!target || !target.closest) {
      return;
    }

    var control = target.closest(
      '.cci-blog-listing .pagination a[href], .cci-blog-listing .js-pager-link[data-ps-data]'
    );
    if (!control
      || control.disabled
      || control.classList.contains('disabled')
      || control.getAttribute('aria-disabled') === 'true'
      || control.closest('li.current')) {
      return;
    }

    var url = control.getAttribute('href') || control.getAttribute('data-ps-data');
    if (!url) {
      return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();
    window.location.assign(url);
  }, true);
})();
</script>
