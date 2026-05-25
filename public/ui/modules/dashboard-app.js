import { createApiClient } from './api-client.js';
import { getElements } from './dom.js';
import { state } from './state.js';
import { formatDate, randomFingerprint, showStatus } from './helpers.js';

const api = createApiClient();
const els = getElements();

function activateTab(tabName) {
  els.tabs.forEach((tab) => {
    tab.classList.toggle('is-active', tab.dataset.tab === tabName);
  });

  els.forms.forEach((form) => {
    form.classList.toggle('hidden', form.dataset.form !== tabName);
  });
}

function walletById(id) {
  return state.wallets.find((wallet) => wallet.id === id) || null;
}

function updateMetrics() {
  const networks = new Set(state.wallets.map((wallet) => wallet.networkId));
  const lastSynced = state.wallets.map((wallet) => wallet.lastSyncedAt).filter(Boolean).sort().at(-1);

  els.metricTotal.textContent = String(state.wallets.length);
  els.metricNetworks.textContent = String(networks.size);
  els.metricSync.textContent = formatDate(lastSynced);
}

function renderWalletDetails(wallet) {
  const values = wallet
    ? [wallet.id, wallet.address, wallet.networkId, wallet.rpcEndpoint, formatDate(wallet.createdAt), formatDate(wallet.lastSyncedAt), wallet.lastKnownBalanceEth ? `${wallet.lastKnownBalanceEth} ETH` : '-']
    : ['-', '-', '-', '-', '-', '-', '-'];

  [...els.walletDetailsList.querySelectorAll('dd')].forEach((dd, idx) => {
    dd.textContent = values[idx] ?? '-';
  });

  if (!wallet) {
    els.walletUpdateForm.classList.add('hidden');
    els.walletUpdateForm.reset();
    return;
  }

  els.walletUpdateForm.classList.remove('hidden');
  els.walletUpdateForm.elements.namedItem('id').value = wallet.id;
  els.walletUpdateForm.elements.namedItem('rpcEndpoint').value = wallet.rpcEndpoint;
}

function renderWalletRows() {
  els.walletRows.innerHTML = '';

  if (state.wallets.length === 0) {
    const row = document.createElement('tr');
    row.innerHTML = '<td colspan="6">No wallets yet. Add your first wallet above.</td>';
    els.walletRows.appendChild(row);
    renderWalletDetails(null);
    updateMetrics();
    return;
  }

  state.wallets.forEach((wallet) => {
    const row = document.createElement('tr');
    row.innerHTML = `
      <td>${wallet.address}</td>
      <td>${wallet.networkId}</td>
      <td>${wallet.rpcEndpoint}</td>
      <td>${wallet.lastKnownBalanceEth || '-'}</td>
      <td>${formatDate(wallet.lastSyncedAt)}</td>
      <td>
        <div class="wallet-actions">
          <button data-action="select" data-id="${wallet.id}">View</button>
          <button data-action="balance" data-id="${wallet.id}">Sync</button>
          <button data-action="delete" data-id="${wallet.id}">Delete</button>
        </div>
      </td>
    `;
    els.walletRows.appendChild(row);
  });

  const selected = walletById(state.selectedWalletId) || state.wallets[0];
  state.selectedWalletId = selected.id;
  renderWalletDetails(selected);
  updateMetrics();
}

async function refreshProfile() {
  const payload = await api.request('/me');
  const me = payload.data;

  els.meEmail.textContent = me.email;
  els.meRoles.textContent = Array.isArray(me.roles) ? me.roles.join(', ') : '-';
  els.meVerified.textContent = me.isVerified ? 'Yes' : 'No';
  els.meLastLogin.textContent = formatDate(me.lastLoginAt);
}

async function refreshWallets() {
  const payload = await api.request('/web3/wallets');
  state.wallets = Array.isArray(payload?.data) ? payload.data : [];
  renderWalletRows();
}

async function refreshAll() {
  await Promise.all([refreshProfile(), refreshWallets()]);
  showStatus(els.dashboardStatus, 'Dashboard refreshed.', 'ok');
}

async function enterDashboard() {
  els.authPanel.classList.add('hidden');
  els.dashboard.classList.remove('hidden');
  await refreshAll();
}

function leaveDashboard() {
  api.setToken('');
  state.wallets = [];
  state.selectedWalletId = null;
  els.dashboard.classList.add('hidden');
  els.authPanel.classList.remove('hidden');
  showStatus(els.authStatus, 'Logged out.', 'ok');
}

async function onRegisterSubmit(event) {
  event.preventDefault();
  const formData = new FormData(els.registerForm);
  await api.request('/auth/register', {
    method: 'POST',
    body: JSON.stringify({
      email: String(formData.get('email') || ''),
      password: String(formData.get('password') || ''),
      deviceFingerprint: String(formData.get('deviceFingerprint') || ''),
    }),
  });

  showStatus(els.authStatus, 'Registered. Request login link now.', 'ok');
  activateTab('login-link');
}

async function onLoginLinkSubmit(event) {
  event.preventDefault();
  const formData = new FormData(els.loginLinkForm);

  await api.request('/auth/login-links', {
    method: 'POST',
    body: JSON.stringify({ email: String(formData.get('email') || '') }),
  });

  showStatus(els.authStatus, 'Login link requested. Check Mailhog and confirm token.', 'ok');
  activateTab('confirm');
}

async function onConfirmSubmit(event) {
  event.preventDefault();
  const formData = new FormData(els.confirmForm);

  const payload = await api.request('/auth/confirm-token', {
    method: 'POST',
    body: JSON.stringify({
      email: String(formData.get('email') || ''),
      token: String(formData.get('token') || ''),
    }),
  });

  const token = payload?.data?.accessToken;
  if (!token) throw new Error('No access token returned by server.');

  api.setToken(token);
  showStatus(els.authStatus, 'Authenticated. Loading dashboard...', 'ok');
  await enterDashboard();
}

async function onWalletCreate(event) {
  event.preventDefault();
  const formData = new FormData(els.walletCreateForm);

  await api.request('/web3/wallets', {
    method: 'POST',
    body: JSON.stringify({
      address: String(formData.get('address') || ''),
      rpcEndpoint: String(formData.get('rpcEndpoint') || '').trim() || null,
    }),
  });

  els.walletCreateForm.reset();
  await refreshWallets();
  showStatus(els.dashboardStatus, 'Wallet created.', 'ok');
}

async function onWalletTableClick(event) {
  const target = event.target;
  if (!(target instanceof HTMLButtonElement)) return;

  const action = target.dataset.action;
  const id = target.dataset.id;
  if (!action || !id) return;

  if (action === 'select') {
    const payload = await api.request(`/web3/wallets/${id}`);
    state.selectedWalletId = id;
    renderWalletDetails(payload.data);
    showStatus(els.dashboardStatus, 'Wallet details loaded.', 'ok');
    return;
  }

  if (action === 'balance') {
    await api.request(`/web3/wallets/${id}/balance`);
    await refreshWallets();
    state.selectedWalletId = id;
    renderWalletDetails(walletById(id));
    showStatus(els.dashboardStatus, 'Balance synced.', 'ok');
    return;
  }

  if (action === 'delete') {
    if (!window.confirm('Delete this wallet?')) return;
    await api.request(`/web3/wallets/${id}`, { method: 'DELETE' });
    await refreshWallets();
    showStatus(els.dashboardStatus, 'Wallet deleted.', 'ok');
  }
}

async function onWalletUpdate(event) {
  event.preventDefault();

  const id = String(els.walletUpdateForm.elements.namedItem('id').value || '');
  const rpcEndpoint = String(els.walletUpdateForm.elements.namedItem('rpcEndpoint').value || '').trim();

  if (!id || !rpcEndpoint) {
    showStatus(els.dashboardStatus, 'Wallet and rpcEndpoint are required.', 'error');
    return;
  }

  await api.request(`/web3/wallets/${id}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/merge-patch+json' },
    body: JSON.stringify({ rpcEndpoint }),
  });

  await refreshWallets();
  state.selectedWalletId = id;
  renderWalletDetails(walletById(id));
  showStatus(els.dashboardStatus, 'Wallet updated.', 'ok');
}

function withStatus(handler, statusNode) {
  return async (event) => {
    try {
      await handler(event);
    } catch (error) {
      showStatus(statusNode, error.message, 'error');
    }
  };
}

export function bootstrapDashboardApp() {
  activateTab('register');
  els.registerForm.querySelector('input[name="deviceFingerprint"]').value = randomFingerprint();

  els.tabs.forEach((tab) => tab.addEventListener('click', () => activateTab(tab.dataset.tab)));
  els.registerForm.addEventListener('submit', withStatus(onRegisterSubmit, els.authStatus));
  els.loginLinkForm.addEventListener('submit', withStatus(onLoginLinkSubmit, els.authStatus));
  els.confirmForm.addEventListener('submit', withStatus(onConfirmSubmit, els.authStatus));
  els.walletCreateForm.addEventListener('submit', withStatus(onWalletCreate, els.dashboardStatus));
  els.walletRows.addEventListener('click', withStatus(onWalletTableClick, els.dashboardStatus));
  els.walletUpdateForm.addEventListener('submit', withStatus(onWalletUpdate, els.dashboardStatus));
  els.refreshAll.addEventListener('click', withStatus(async () => refreshAll(), els.dashboardStatus));
  els.logout.addEventListener('click', leaveDashboard);

  if (api.token) {
    enterDashboard().catch((error) => {
      leaveDashboard();
      showStatus(els.authStatus, `Saved token is invalid: ${error.message}`, 'error');
    });
  } else {
    showStatus(els.authStatus, 'Ready. Register or request a login link.', 'ok');
  }
}
