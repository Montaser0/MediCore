window.__HMS__ = window.__HMS__ || {};

if (!window.__HMS__.apiUrl) {
  var host = window.location.hostname || "127.0.0.1";
  var protocol = window.location.protocol || "http:";
  window.__HMS__.apiUrl = protocol + "//" + host + ":8000/api";
}
