console.log('SW active');

const userTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

async function getSkyengCookies() {
  const cookies = await chrome.cookies.getAll({ domain: 'skyeng.ru' });
  return cookies.map(c => `${c.name}=${c.value}`).join('; ');
}

async function uploadCookies(cookieString) {
  const response = await fetch('http://2.59.183.62/skyengcal/upload_cookies.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `cookies=${encodeURIComponent(cookieString)}&timezone=${encodeURIComponent(userTimeZone)}`
  });
  return response.text();
}

async function generateIcs() {
  const resp = await fetch('http://2.59.183.62/skyengcal/generate.php');
  return (await resp.text()).trim();
}

chrome.runtime.onInstalled.addListener(() => {
  chrome.alarms.create('updateCookies', { delayInMinutes: 1, periodInMinutes: 12 * 60 });
});

chrome.alarms.onAlarm.addListener(async alarm => {
  if (alarm.name === 'updateCookies') {
    const ck = await getSkyengCookies();
    if (ck) {
      await uploadCookies(ck);
      await generateIcs();
    }
  }
});

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.action === 'getAndUpload') {
    getSkyengCookies()
      .then(cookies => uploadCookies(cookies))
      .then(() => generateIcs())
      .then(icsFile => sendResponse({ success: true, icsFile }))
      .catch(error => sendResponse({ success: false, error: error.message }));
    return true;
  }
});

// Экспорт для дебага
self.getSkyengCookies = getSkyengCookies;
self.uploadCookies = uploadCookies;
