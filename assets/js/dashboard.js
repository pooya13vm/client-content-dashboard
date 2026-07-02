document.addEventListener('DOMContentLoaded', function () {
  var picker = document.getElementById('ccd-template');
  if (!picker) return;
  function showTemplate() {
    document.querySelectorAll('.ccd-template-fields').forEach(function (group) {
      group.hidden = group.dataset.template !== picker.value;
      group.querySelectorAll('input, textarea, select').forEach(function (field) {
        field.disabled = group.hidden;
      });
    });
  }
  picker.addEventListener('change', showTemplate);
  showTemplate();
});
