import axios from 'axios';
export const API_BASE = import.meta.env.VITE_API_BASE_URL || '/api';
export const api = axios.create({ baseURL: API_BASE, withCredentials: true, timeout: 18000 });
let csrf = '';
export const updateCsrf = (value) => { csrf = value || ''; };
export const ensureCsrf = async () => {
  if (!csrf) { const response = await api.get('/auth/csrf'); updateCsrf(response.data.data.csrf); }
  return csrf;
};
api.interceptors.request.use(async (config) => {
  if (!['get', 'head', 'options'].includes((config.method || 'get').toLowerCase())) {
    config.headers['X-CSRF-Token'] = await ensureCsrf();
  }
  return config;
});
export const getError = (err) => err?.response?.data?.message || (err?.code === 'ECONNABORTED' ? 'Request timed out. Check your network.' : err?.message || 'Something went wrong.');
export const request = async (method, url, data) => (await api({ method, url, data })).data.data;
