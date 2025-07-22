// Файл: extension/popup.js

document.addEventListener('DOMContentLoaded', () => {
  const btn    = document.getElementById('btn');
  const status = document.getElementById('status');
  const field  = document.getElementById('ics-field');
  const instr  = document.getElementById('instruction');

  btn.addEventListener('click', async () => {
    status.textContent     = 'Загрузка...';
    field.style.display    = 'none';
    instr.style.display    = 'none';

    try {
      const response = await new Promise(resolve =>
        chrome.runtime.sendMessage({ action: 'getAndUpload' }, resolve)
      );

      if (response && response.success && response.icsUrl) {
        status.textContent     = 'Готово';
        field.textContent      = response.icsUrl;
        field.style.display    = 'block';
        instr.style.display    = 'block';
      } else {
        console.error('Ответ неуспешен:', response);
        status.textContent = 'Ошибка получения ссылки';
      }
    } catch (e) {
      console.error(e);
      status.textContent = 'Ошибка: ' + e.message;
    }
  });

  // Копирование ссылки по клику
  field.addEventListener('click', () => {
    const text = field.textContent;
    navigator.clipboard.writeText(text)
      .then(() => status.textContent = 'Ссылка скопирована')
      .catch(err => {
        console.error('Ошибка буфера обмена:', err);
        status.textContent = 'Не удалось скопировать';
      });
  });
});
