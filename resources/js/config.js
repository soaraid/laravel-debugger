// Settings the server renders into the page (see AppController).
const element = document.getElementById('debugger-config');

export const config = JSON.parse(element?.textContent || '{}');
