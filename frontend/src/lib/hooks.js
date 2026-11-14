import { useCallback, useEffect, useState } from 'react';
import { api, getError } from './api';
export function useRemote(url, poll = 0) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const refresh = useCallback(async (silent = false) => {
    if (!silent) setLoading(true);
    try { const result = await api.get(url); setData(result.data.data); setError(''); return result.data.data; }
    catch (e) { setError(getError(e)); return null; }
    finally { if (!silent) setLoading(false); }
  }, [url]);
  useEffect(() => { refresh(); if (poll) { const timer = setInterval(() => refresh(true), poll); return () => clearInterval(timer); } }, [refresh, poll]);
  return { data, loading, error, refresh };
}
export const shortDate = (date) => date ? new Date(String(date).replace(' ', 'T')).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : '—';
export const number = (value) => Number(value || 0).toLocaleString('en-PH');
