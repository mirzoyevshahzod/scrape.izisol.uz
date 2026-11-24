// inject.js (sahifa dunyosi) dan kelgan javobni kengaytmaning background'iga uzatadi.
window.addEventListener('message', (event) => {
  if (event.source !== window || !event.data || event.data.__belarusQueue !== true) return;
  chrome.runtime.sendMessage({ type: 'belarus-queue', text: event.data.text });
});
