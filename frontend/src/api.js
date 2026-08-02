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

export function listAllForms() {
  return apiRequest('/forms?scope=all');
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

export function listDrafts(formId) {
  return apiRequest(`/forms/${formId}/drafts`);
}

// Admin tenants (admin-sistema)
export function adminListTenants(params = {}) {
  const searchParams = new URLSearchParams()
  if (params.per_page) searchParams.set('per_page', String(params.per_page))
  const qs = searchParams.toString()
  return apiRequest(`/admin/tenants${qs ? `?${qs}` : ''}`)
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
export function listTenantUsers(tenantId, params = {}) {
  const searchParams = new URLSearchParams()
  if (params.per_page) searchParams.set('per_page', String(params.per_page))
  const qs = searchParams.toString()
  return apiRequest(`/tenants/${tenantId}/users${qs ? `?${qs}` : ''}`)
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
export function listSubmissions(params = {}) {
  const searchParams = new URLSearchParams()
  if (params.status) searchParams.set('status', params.status)
  if (params.form_id) searchParams.set('form_id', String(params.form_id))
  if (params.per_page) searchParams.set('per_page', String(params.per_page))
  const qs = searchParams.toString()
  return apiRequest(`/submissions${qs ? `?${qs}` : ''}`)
}

export function getSubmission(submissionId) {
  return apiRequest(`/submissions/${submissionId}`)
}

export async function exportSubmissionsCsv({ form_id, version_ids, date_from, date_to }) {
  const response = await fetch('/api/submissions/export', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${getAuthToken()}`,
    },
    body: JSON.stringify({ form_id, version_ids, date_from, date_to, format: 'csv' }),
  })

  if (!response.ok) {
    const isJson = response.headers.get('content-type')?.includes('application/json')
    const body = isJson ? await response.json() : null
    const error = new Error(body?.message || 'Erro ao exportar submissões')
    error.status = response.status
    error.body = body
    throw error
  }

  const blob = await response.blob()
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'submissoes.csv'
  document.body.appendChild(a)
  a.click()
  a.remove()
  window.URL.revokeObjectURL(url)
}

// Audit logs (admin)
export function listAuditLogs(params = {}) {
  const searchParams = new URLSearchParams()
  if (params.action) searchParams.set('action', params.action)
  if (params.user_id) searchParams.set('user_id', String(params.user_id))
  if (params.entity_type) searchParams.set('entity_type', params.entity_type)
  if (params.entity_id) searchParams.set('entity_id', String(params.entity_id))
  if (params.per_page) searchParams.set('per_page', String(params.per_page))
  const qs = searchParams.toString()
  return apiRequest(`/audit-logs${qs ? `?${qs}` : ''}`)
}

export function getAuditLog(logId) {
  return apiRequest(`/audit-logs/${logId}`)
}
