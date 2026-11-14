import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { api, updateCsrf } from '../lib/api';
const Context = createContext(null);
export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [startupError, setStartupError] = useState('');
  const refresh = useCallback(async () => {
    try { const r = await api.get('/auth/me'); setUser(r.data.data.user); setStartupError(''); return r.data.data.user; }
    catch (e) { setUser(null); setStartupError(e?.response?.data?.message || 'Cannot reach the backend. Confirm Apache and MySQL are running.'); return null; }
  }, []);
  useEffect(() => { refresh().finally(() => setLoading(false)); }, [refresh]);
  const login = async (identity, password) => {
    const r = await api.post('/auth/login', { identity, password });
    updateCsrf(r.data.data.csrf);
    setUser(r.data.data.user);
    return r.data.data.user;
  };
  const register = async (data) => {
    const r = await api.post('/auth/register-student', data);
    updateCsrf(r.data.data.csrf);
    setUser(r.data.data.user);
    return r.data.data.user;
  };
  const logout = async () => { try { await api.post('/auth/logout'); } finally { updateCsrf(''); setUser(null); } };
  return <Context.Provider value={{ user, loading, startupError, login, register, logout, refresh }}>{children}</Context.Provider>;
}
export function useAuth() { const value = useContext(Context); if (!value) throw new Error('useAuth needs AuthProvider'); return value; }
