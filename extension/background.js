// Файл: extension/background.js

console.log('SW active');

const userTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
console.log('User time zone:', userTimeZone);

const serverUrl = 'https://2.59.183.62/skyengcal';

// Получить cookies с домена skyeng.ru
async function getSkyengCookies() {
  const cookies = await chrome.cookies.getAll({ domain: 'skyeng.ru' });
  return cookies.map(c => `${c.name}=${c.value}`).join('; ');
}

// Отправить cookies + timezone и получить teacher_id
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

  try {
    const data = JSON.parse(text);
    if (!data.teacher_id) throw new Error('missing teacher_id');
    return data.teacher_id;
  } catch (err) {
    console.error('Ошибка разбора JSON из upload:', err.message);
    throw new Error('Invalid JSON in upload_cookies.php response');
  }
}

// Вызов fetch.php → создаёт JSON файл расписания
async function fetchSchedule(teacherId) {
  const response = await fetch(`${serverUrl}/fetch.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `teacher_id=${encodeURIComponent(teacherId)}`,
    credentials: 'include'
  });

  const text = await response.text();
  console.log('fetch.php response:', text);

  if (!response.ok) {
    throw new Error(`fetch failed: ${response.status} ${text}`);
  }

  return text;
}

// Запрос генерации ICS файла на сервере
async function generateIcsOnServer(teacherId) {
  const response = await fetch(`${serverUrl}/generate.php/${teacherId}.ics`);
  const text = await response.text();
  console.log('generate.php response:', text);

  const icsUrl = `${serverUrl}/ics/${teacherId}.ics`;
  return icsUrl;
}

// Обработка сообщений из popup.js
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.action === 'getAndUpload') {
    (async () => {
      try {
        const cookieString = await getSkyengCookies();
        const teacherId = await uploadCookies(cookieString);
        await fetchSchedule(teacherId);
        const icsUrl = await generateIcsOnServer(teacherId);

        sendResponse({ success: true, teacherId, icsUrl });
      } catch (err) {
        console.error('Ошибка в pipeline:', err.message);
        sendResponse({ success: false, error: err.message });
      }
    })();
    return true;
  }
});

// Обновление cookies автоматически
chrome.runtime.onInstalled.addListener(() => {
  chrome.alarms.create('updateCookies', {
    delayInMinutes: 1,
    periodInMinutes: 12 * 60
  });
});

chrome.alarms.onAlarm.addListener(async (alarm) => {
  if (alarm.name === 'updateCookies') {
    try {
      const cookies = await getSkyengCookies();
      const teacherId = await uploadCookies(cookies);
      await fetchSchedule(teacherId);
      await generateIcsOnServer(teacherId);
    } catch (err) {
      console.error('Ошибка автообновления:', err.message);
    }
  }
});

// DEBUG
self.getSkyengCookies = getSkyengCookies;
self.uploadCookies = uploadCookies;
self.fetchSchedule = fetchSchedule;
self.generateIcsOnServer = generateIcsOnServer;
