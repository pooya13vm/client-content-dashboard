document.addEventListener('DOMContentLoaded', function () {
  var modal = document.querySelector('.ccd-schedule-modal');
  var openButton = document.querySelector('.ccd-schedule-open');
  if (!modal || !openButton) return;
  var cancelButton = modal.querySelector('.ccd-schedule-cancel');
  var firstInput = modal.querySelector('input');
  function openModal() {
    modal.hidden = false;
    modal.classList.add('is-open');
    if (firstInput) firstInput.focus();
  }
  function closeModal() {
    modal.classList.remove('is-open');
    modal.hidden = true;
    openButton.focus();
  }
  openButton.addEventListener('click', openModal);
  if (cancelButton) cancelButton.addEventListener('click', closeModal);
  modal.addEventListener('click', function (event) {
    if (event.target === modal) closeModal();
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !modal.hidden) closeModal();
  });
});
