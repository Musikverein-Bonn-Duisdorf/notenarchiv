/**
 * ARCHIV-51: onclick → page-local delete modal with POST form inside.
 */
(function () {
  function el(id) {
    return document.getElementById(id);
  }

  window.archivOpenDeleteModal = function (id) {
    var modal = el(id);
    if (!modal) return false;
    modal.style.display = 'block';
    modal.classList.add('w3-show');
    return false;
  };

  window.archivCloseDeleteModal = function (id) {
    var modal = el(id);
    if (!modal) return false;
    modal.style.display = 'none';
    modal.classList.remove('w3-show');
    return false;
  };

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var modals = document.querySelectorAll('.archiv-delete-modal.w3-show');
    for (var i = 0; i < modals.length; i++) {
      modals[i].style.display = 'none';
      modals[i].classList.remove('w3-show');
    }
  });
})();
