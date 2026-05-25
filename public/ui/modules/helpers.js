export function showStatus(node, message, type = '') {
  node.textContent = message;
  node.classList.remove('ok', 'error');
  if (type) node.classList.add(type);
}

export function formatDate(dateString) {
  if (!dateString) return '-';
  const date = new Date(dateString);

  return Number.isNaN(date.getTime()) ? dateString : date.toLocaleString();
}

export function randomFingerprint() {
  if (window.crypto?.randomUUID) {
    return `device-${window.crypto.randomUUID().slice(0, 16)}`;
  }

  return `device-${Math.random().toString(16).slice(2, 18)}`;
}
