const API_BASE = '/api';

function getAuthToken() {
  return window.localStorage.getItem('fe_token') ?? '';
}

export async function apiRequest(path, options = {}) {
  const headers = new Headers(options.headers || {});

  headers.set('Content-Type', 'application/json');

  const token = getAuthToken();
  if (token) {
    headers.set('Authorization', `Bearer ${token}`);
  }

  const response = await fetch(`${API_BASE}${path}`, {
    ...options,
    headers,
  });

  const isJson = response.headers.get('content-type')?.includes('application/json');
  const body = isJson ? await response.json() : null;

  if (!response.ok) {
    const error = new Error(body?.message || 'Erro ao comunicar com a API');
    error.status = response.status;
    error.body = body;
    throw error;
  }

  return body;
}

export async function login({ email, password, tenantSlug }) {
  const payload = { email, password };
  if (tenantSlug) {
    payload.tenant_slug = tenantSlug;
  }

  const data = await apiRequest('/auth/login', {
    method: 'POST',
    body: JSON.stringify(payload),
  });

  const token = data?.data?.token;
  if (token) {
    window.localStorage.setItem('fe_token', token);
  }

  return data;
}

export function logout() {
  window.localStorage.removeItem('fe_token');
  return apiRequest('/auth/logout', { method: 'POST' }).catch(() => {});
}

export function getCurrentUser() {
  return apiRequest('/auth/me');
}

export function listForms() {
  return apiRequest('/forms');
}

export function getForm(formId) {
  return apiRequest(`/forms/${formId}`);
}

export function createForm({ name, description }) {
  return apiRequest('/forms', {
    method: 'POST',
    body: JSON.stringify({ name, description }),
  });
}

export function listFormVersions(formId) {
  return apiRequest(`/forms/${formId}/versions`);
}

export function publishFormVersion(formId, versionId) {
  return apiRequest(`/forms/${formId}/versions/${versionId}/publish`, {
    method: 'POST',
  });
}

export function listFields(formId, versionId) {
  return apiRequest(`/forms/${formId}/versions/${versionId}/fields`);
}

export function createField(formId, versionId, payload) {
  return apiRequest(`/forms/${formId}/versions/${versionId}/fields`, {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export function deleteField(formId, versionId, fieldId) {
  return apiRequest(`/forms/${formId}/versions/${versionId}/fields/${fieldId}`, {
    method: 'DELETE',
  });
}

export function submitForm(formId, values) {
  return apiRequest(`/forms/${formId}/submit`, {
    method: 'POST',
    body: JSON.stringify({ values }),
  });
}

export function saveDraft(formId, values) {
  return apiRequest(`/forms/${formId}/drafts`, {
    method: 'POST',
    body: JSON.stringify({ values }),
  });
}
