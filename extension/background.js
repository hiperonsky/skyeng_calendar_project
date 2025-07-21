// Файл: extension/background.js

console.log('SW active');

const userTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
console.log('User time zone:', userTimeZone);

const serverUrl = 'http://2.59.183.62/skyengcal';

// Получить cookies с домена skyeng.ru
async function getSkyengCookies() {
  const cookies = await chrome.cookies.getAll({ domain: 'skyeng.ru' });
  return cookies.map(c => `${c.name}=${c.value}`).join('; ');
}

// Отправить cookies + timezone на сервер
async function uploadCookies(cookieString) {
  const response = await fetch(`${serverUrl}/upload_cookies.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `cookies=${encodeURIComponent(cookieString)}&timezone=${encodeURIComponent(userTimeZone)}`,
    credentials: 'include'
  });

  const text = await response.text();
  if (!response.ok) {
    throw new Error(`upload failed: ${response.status} ${text}`);
  }

  console.log('uploadCookies response:', text);
  return text;
}

// Запрос расписания и teacher_id
async function fetchSchedule() {
  const response = await fetch(`${serverUrl}/fetch.php`, {
    method: 'POST',
    credentials: 'include'
  });

  const status = response.status;
  const raw = await response.text();

  console.log(`FETCH  → Status ${status}`);
  console.log('FETCH  → Body:', raw);

  try {
    const data = JSON.parse(raw);
    if (!data.teacher_id) throw new Error('missing teacher_id');
    return data.teacher_id;
  } catch (err) {
    console.error('Ошибка разбора JSON:', err.message);
    throw new Error(`Bad JSON, status ${status}`);
  }
}

// Открытие ICS-ссылки в новой вкладке (в worker-контексте window не существует)
function generateIcs(teacherId) {
  const icsUrl = `${serverUrl}/generate.php/${teacherId}.ics`;
  console.log('Opening ICS URL:', icsUrl);

  chrome.tabs.create({ url: icsUrl });
}

// Обработка сообщений из popup.js
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.action === 'getAndUpload') {
    (async () => {
      try {
        const ck = await getSkyengCookies();
        await uploadCookies(ck);

        const teacherId = await fetchSchedule();
        generateIcs(teacherId);

        sendResponse({ success: true, teacherId });
      } catch (err) {
        console.error('Ошибка в pipeline:', err.message);
        sendResponse({ success: false, error: err.message });
      }
    })();
    return true; // Делает sendResponse асинхронным
  }
});

// Обновление cookies по расписанию (каждые 12 часов)
chrome.runtime.onInstalled.addListener(() => {
  chrome.alarms.create('updateCookies', {
    delayInMinutes: 1,
    periodInMinutes: 12 * 60
  });
});

chrome.alarms.onAlarm.addListener(async (alarm) => {
  if (alarm.name === 'updateCookies') {
    try {
      const ck = await getSkyengCookies();
      if (ck) {
        await uploadCookies(ck);
        const teacherId = await fetchSchedule();
        generateIcs(teacherId);
      }
    } catch (err) {
      console.error('Ошибка автоматического обновления расписания:', err.message);
    }
  }
});

// DEBUG: экспорт функций в глобальный scope для ручного вызова в DevTools
self.getSkyengCookies = getSkyengCookies;
self.uploadCookies = uploadCookies;
self.fetchSchedule = fetchSchedule;
self.generateIcs = generateIcs;
