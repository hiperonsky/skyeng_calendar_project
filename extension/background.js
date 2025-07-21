// Файл: extension/background.js

console.log('SW active');

const userTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
console.log('User time zone:', userTimeZone);

const serverUrl = 'http://2.59.183.62/skyengcal';

// Функция для получения cookies Skyeng
async function getSkyengCookies() {
  const cookies = await chrome.cookies.getAll({ domain: 'skyeng.ru' });
  return cookies.map(c => `${c.name}=${c.value}`).join('; ');
}

// 1. Отправка cookies на сервер
async function uploadCookies(cookieString) {
  try {
    const response = await fetch(`${serverUrl}/upload_cookies.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `cookies=${encodeURIComponent(cookieString)}&timezone=${encodeURIComponent(userTimeZone)}`,
      credentials: 'include'
    });
    if (!response.ok) {
      throw new Error(`upload failed: ${response.status} ${response.statusText}`);
    }
    const text = (await response.text()).trim();
    console.log('uploadCookies response:', text);
    return text;
  } catch (err) {
    console.error('uploadCookies error:', err);
    throw err;
  }
}

// 2. Запрос JSON расписания и teacher_id
async function fetchSchedule() {
  const url = `${serverUrl}/fetch.php`;
  const response = await fetch(url, {
    method: 'POST',
    credentials: 'include'
  });

  const status = response.status;
  const raw = await response.text();  // читаем as text

  console.log(`FETCH  → Status ${status}`);
  console.log('FETCH  → Body:', raw);

  try {
    const data = JSON.parse(raw);

    if (!data.teacher_id) throw new Error('missing teacher_id');
    return data.teacher_id;

  } catch (e) {
    console.error('JSON parse failed', e.message);
    throw new Error(`Bad JSON, status ${status}`);
  }
}


// 3. Генерация и открытие ICS-файла
async function generateIcs(teacherId) {
  try {
    const icsUrl = `${serverUrl}/generate.php/${teacherId}.ics`;
    console.log('Opening ICS URL:', icsUrl);
    window.open(icsUrl, '_blank');
  } catch (err) {
    console.error('generateIcs error:', err);
    throw err;
  }
}

// Альтернативный вариант через сообщения из popup.js
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.action === 'getAndUpload') {
    (async () => {
      try {
        const ck = await getSkyengCookies();
        await uploadCookies(ck);
        const teacherId = await fetchSchedule();
        await generateIcs(teacherId);
        sendResponse({ success: true, teacherId });
      } catch (error) {
        sendResponse({ success: false, error: error.message });
      }
    })();
    return true; // Асинхронный ответ
  }
});

// Планировщик для периодического обновления
chrome.runtime.onInstalled.addListener(() => {
  chrome.alarms.create('updateCookies', { delayInMinutes: 1, periodInMinutes: 12 * 60 });
});
chrome.alarms.onAlarm.addListener(async alarm => {
  if (alarm.name === 'updateCookies') {
    try {
      const ck = await getSkyengCookies();
      if (ck) {
        await uploadCookies(ck);
        const teacherId = await fetchSchedule();
        await generateIcs(teacherId);
      }
    } catch (err) {
      console.error('Alarm pipeline error:', err);
    }
  }
});

// Экспорт функций для отладки в консоли Service Worker
self.getSkyengCookies = getSkyengCookies;
self.uploadCookies = uploadCookies;
self.fetchSchedule = fetchSchedule;
self.generateIcs = generateIcs;
