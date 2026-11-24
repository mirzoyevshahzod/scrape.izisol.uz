// Sahifaning o'z dunyosida (MAIN world) ishlaydi: sayt o'zi yuborgan
// navbat so'rovining javobini o'qiydi va relay.js ga uzatadi.
// O'zidan hech qanday so'rov yubormaydi, captcha va himoyaga tegmaydi.
(() => {
  const MATCH = /belarusborder\.by\/info\/monitoring-new/;
  const forward = (url, text) => window.postMessage({ __belarusQueue: true, url: String(url), text }, location.origin);

  const originalFetch = window.fetch;
  window.fetch = async function (...args) {
    const response = await originalFetch.apply(this, args);
    try {
      const url = typeof args[0] === 'string' ? args[0] : args[0] && args[0].url;
      if (url && MATCH.test(url) && response.ok) {
        response.clone().text().then((text) => forward(url, text)).catch(() => {});
      }
    } catch (e) { /* sahifa ishiga xalaqit bermasin */ }
    return response;
  };

  const open = XMLHttpRequest.prototype.open;
  XMLHttpRequest.prototype.open = function (method, url, ...rest) {
    this.__belarusQueueUrl = String(url);
    return open.call(this, method, url, ...rest);
  };

  const send = XMLHttpRequest.prototype.send;
  XMLHttpRequest.prototype.send = function (...args) {
    if (this.__belarusQueueUrl && MATCH.test(this.__belarusQueueUrl)) {
      this.addEventListener('load', () => {
        try {
          if (this.status !== 200) return;
          const text = this.responseType === '' || this.responseType === 'text' ? this.responseText : JSON.stringify(this.response);
          forward(this.__belarusQueueUrl, text);
        } catch (e) { /* sahifa ishiga xalaqit bermasin */ }
      });
    }
    return send.apply(this, args);
  };
})();
