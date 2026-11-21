import { getAuthToken, setAuthToken, clearAuthToken } from './auth/token.js';
import { csvDataRows } from './utils/csv.js';

const API_BASE = '/api';

async function responseError(response, token, authenticated) {
  if (response.status === 401 && authenticated) clearAuthToken('expired', token);
  const isJson = response.headers.get('content-type')?.includes('application/json');
  const body = isJson ? await response.json().catch(() => null) : null;
  const error = new Error(body?.message || 'Não foi possível comunicar com o servidor. Tente novamente.');
  error.status = response.status;
  error.body = body;
  return error;
}

export async function apiRequest(path, options = {}) {
  const headers = new Headers(options.headers || {});

  headers.set('Content-Type', 'application/json');
  headers.set('Accept', 'application/json');

  const token = getAuthToken();
  const authenticated = path !== '/auth/login';
  if (token && authenticated) {
    headers.set('Authorization', `Bearer ${token}`);
  }

  const response = await fetch(`${API_BASE}${path}`, {
    ...options,
    signal: options.signal ? AbortSignal.any([options.signal, AbortSignal.timeout(15000)]) : AbortSignal.timeout(15000),
    headers,
  });

  if (!response.ok) {
    throw await responseError(response, token, authenticated);
  }

  const isJson = response.headers.get('content-type')?.includes('application/json');
  return isJson ? response.json() : null;
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
    setAuthToken(token);
  } else {
    throw new Error('O servidor não retornou uma sessão válida. Tente novamente.');
  }

  return data;
}

export async function logout() {
  const token = getAuthToken();
  clearAuthToken('logout', token);
  try {
    if (token) await fetch(`${API_BASE}/auth/logout`, {
      signal: AbortSignal.timeout(15000),
      method: 'POST', headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
    });
  } catch {
    // Mesmo que o token já tenha expirado ou a API esteja indisponível,
    // o logout local deve sempre ser concluído.
  }
}

export function getCurrentUser() {
  return apiRequest('/auth/me');
}

function queryPath(path, params, keys) {
  const query = new URLSearchParams()
  for (const key of keys) {
    const value = params[key]
    if (value !== undefined && value !== null && value !== '') query.set(key, String(value))
  }
  return `${path}${query.size ? `?${query}` : ''}`
}

export function listForms(params = {}, options = {}) {
  return apiRequest(queryPath('/forms', params, ['search', 'sort', 'order']), options)
}

export function listAllForms(params = {}, options = {}) {
  return apiRequest(queryPath('/forms', { ...params, scope: 'all' }, ['scope', 'search', 'sort', 'order']), options)
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

export function updateFormStatus(formId, isActive) {
  return apiRequest(`/forms/${formId}/status`, {
    method: 'PATCH',
    body: JSON.stringify({ is_active: !!isActive }),
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

export function createFormVersion(formId) {
  return apiRequest(`/forms/${formId}/versions`, {
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

export function updateField(formId, versionId, fieldId, payload) {
  return apiRequest(`/forms/${formId}/versions/${versionId}/fields/${fieldId}`, {
    method: 'PUT',
    body: JSON.stringify(payload),
  });
}

export function deleteField(formId, versionId, fieldId) {
  return apiRequest(`/forms/${formId}/versions/${versionId}/fields/${fieldId}`, {
    method: 'DELETE',
  });
}

export function submitForm(formId, values, versionId) {
  return apiRequest(`/forms/${formId}/submit`, {
    method: 'POST',
    body: JSON.stringify({ values, version_id: versionId }),
  });
}

export function saveDraft(formId, values, versionId) {
  return apiRequest(`/forms/${formId}/drafts`, {
    method: 'POST',
    body: JSON.stringify({ values, version_id: versionId }),
  });
}

export function listDrafts(formId) {
  return apiRequest(`/forms/${formId}/drafts`);
}

export function updateDraft(formId, draftId, values) {
  return apiRequest(`/forms/${formId}/drafts/${draftId}`, {
    method: 'PATCH', body: JSON.stringify({ values }),
  })
}

export function finalizeDraft(formId, draftId, values) {
  return apiRequest(`/forms/${formId}/drafts/${draftId}/submit`, {
    method: 'POST', body: JSON.stringify({ values }),
  })
}

export async function uploadFormAttachment(formId, file, fieldName, options = {}) {
  const token = getAuthToken();
  const headers = new Headers();
  headers.set('Accept', 'application/json');
  if (token) {
    headers.set('Authorization', `Bearer ${token}`);
  }
  const formData = new FormData();
  formData.append('file', file);
  formData.append('field_name', fieldName);

  const response = await fetch(`${API_BASE}/forms/${formId}/attachments`, {
    method: 'POST',
    body: formData,
    headers,
    signal: options.signal ? AbortSignal.any([options.signal, AbortSignal.timeout(30000)]) : AbortSignal.timeout(30000),
  });

  if (!response.ok) {
    throw await responseError(response, token, true);
  }

  return response.json();
}

export function getSubmissionAttachmentUrl(submissionId, fieldId) {
  return `${API_BASE}/submissions/${submissionId}/attachments/${fieldId}`;
}

export async function downloadSubmissionAttachment(submissionId, fieldId, filename = 'anexo') {
  const token = getAuthToken();
  const headers = new Headers();
  if (token) headers.set('Authorization', `Bearer ${token}`);
  const response = await fetch(getSubmissionAttachmentUrl(submissionId, fieldId), {
    headers,
    signal: AbortSignal.timeout(30000),
  });
  if (!response.ok) {
    throw await responseError(response, token, true);
  }
  const blob = await response.blob();
  const url = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.URL.revokeObjectURL(url);
}

// Admin tenants (admin-sistema)
export function adminListTenants(params = {}, options = {}) {
  return apiRequest(queryPath(`/admin/tenants`, params, ['page', 'per_page']), options)
}

export function adminCreateTenant({ name, slug }) {
  return apiRequest('/admin/tenants', {
    method: 'POST',
    body: JSON.stringify({ name, slug }),
  })
}

export function adminUpdateTenantStatus(tenantId, isActive) {
  return apiRequest(`/admin/tenants/${tenantId}/status`, {
    method: 'PATCH',
    body: JSON.stringify({ is_active: !!isActive }),
  })
}

// Tenant users (admin do tenant)
export function listTenantUsers(tenantId, params = {}, options = {}) {
  return apiRequest(queryPath(`/tenants/${tenantId}/users`, params, ['page', 'per_page']), options)
}

export function createTenantUser(tenantId, { name, email, password, role }) {
  return apiRequest(`/tenants/${tenantId}/users`, {
    method: 'POST',
    body: JSON.stringify({ name, email, password, role }),
  })
}

export function updateTenantUserStatus(tenantId, userId, isActive) {
  return apiRequest(`/tenants/${tenantId}/users/${userId}/status`, {
    method: 'PATCH',
    body: JSON.stringify({ is_active: !!isActive }),
  })
}

// Submissions listing and detail
export function listSubmissions(params = {}, options = {}) {
  return apiRequest(queryPath(`/submissions`, params, ['page', 'per_page', 'status', 'form_id', 'version_id', 'user_id', 'date_from', 'date_to']), options)
}

export function getSubmission(submissionId) {
  return apiRequest(`/submissions/${submissionId}`)
}

export async function exportSubmissionsCsv({ form_id, version_ids, date_from, date_to, separator }) {
  const token = getAuthToken()
  const response = await fetch('/api/submissions/export', {
    signal: AbortSignal.timeout(120000),
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'text/csv, application/json',
      Authorization: `Bearer ${token}`,
    },
    body: JSON.stringify({ form_id, version_ids, date_from, date_to, separator, format: 'csv' }),
  })

  if (!response.ok) {
    throw await responseError(response, token, true)
  }

  const blob = await response.blob()
  const rows = csvDataRows(await blob.text())
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'submissoes.csv'
  document.body.appendChild(a)
  a.click()
  a.remove()
  window.URL.revokeObjectURL(url)
  return { bytes: blob.size, rows }
}

// Audit logs (admin)
export function listAuditLogs(params = {}, options = {}) {
  return apiRequest(queryPath(`/audit-logs`, params, ['page', 'per_page', 'action', 'user_id', 'entity_type', 'entity_id', 'date_from', 'date_to']), options)
}

export function getAuditLog(logId) {
  return apiRequest(`/audit-logs/${logId}`)
}
