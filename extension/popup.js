// Файл: extension/popup.js

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

      if (response && response.success && response.teacherId) {
        status.textContent = 'OK';
        // Формируем URL на основе teacherId
        const icsUrl = `http://2.59.183.62/skyengcal/generate.php/${response.teacherId}.ics`;
        field.textContent = icsUrl;
        field.style.display = 'block';
        instr.style.display = 'block';
      } else {
        console.error('Response error:', response);
        status.textContent = 'Ошибка получения данных';
      }
    } catch (e) {
      console.error(e);
      status.textContent = 'Ошибка: ' + e.message;
    }
  });

  // Клик по полю для копирования ссылки
  field.addEventListener('click', () => {
    const text = field.textContent;
    navigator.clipboard.writeText(text)
      .then(() => status.textContent = 'Ссылка скопирована')
      .catch(err => {
        console.error('Clipboard error:', err);
        status.textContent = 'Не удалось скопировать';
      });
  });
});
