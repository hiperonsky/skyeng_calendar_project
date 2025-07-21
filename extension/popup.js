document.addEventListener('DOMContentLoaded', () => {
  const btn    = document.getElementById('btn');
  const status = document.getElementById('status');
  const field  = document.getElementById('ics-field');
  const instr  = document.getElementById('instruction');

  btn.addEventListener('click', async () => {
    status.textContent = 'Загрузка...';
    field.style.display = 'none';
    instr.style.display = 'none';

    try {
      const response = await new Promise(resolve =>
        chrome.runtime.sendMessage({ action: 'getAndUpload' }, resolve)
      );
      if (response && response.success && response.icsFile) {
        status.textContent = 'OK';
        const icsUrl = `http://2.59.183.62/skyengcal/${response.icsFile}`;
        field.textContent = icsUrl;
        field.style.display = 'block';
        instr.style.display = 'block';
      } else {
        status.textContent = 'Ошибка';
      }
    } catch (e) {
      console.error(e);
      status.textContent = 'Ошибка: ' + e.message;
    }
  });

  field.addEventListener('click', () => {
    const text = field.textContent;
    navigator.clipboard.writeText(text)
      .then(() => status.textContent = 'Ссылка скопирована')
      .catch(() => status.textContent = 'Не удалось скопировать');
  });
});
