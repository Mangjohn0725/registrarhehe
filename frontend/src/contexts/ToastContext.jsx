import { createContext, useContext, useState } from 'react';
import { CheckCircle2, AlertCircle, X } from 'lucide-react';
const Ctx = createContext(null);
export function ToastProvider({ children }) {
  const [messages, setMessages] = useState([]);
  const toast = (text, type = 'success') => {
    const id = Date.now() + Math.random();
    setMessages(v => [...v, { id, text, type }]);
    setTimeout(() => setMessages(v => v.filter(m => m.id !== id)), 4800);
  };
  return <Ctx.Provider value={toast}>{children}<div className="fixed top-5 right-5 z-[99] space-y-2 w-[min(360px,calc(100vw-40px))]" aria-live="polite">{messages.map(m => <div key={m.id} className={`flex items-start gap-3 rounded-2xl border bg-white p-4 shadow-xl ${m.type === 'error' ? 'border-red-200' : 'border-primary-100'}`}>{m.type === 'error' ? <AlertCircle size={20} className="text-red-500 shrink-0"/> : <CheckCircle2 size={20} className="text-emerald-500 shrink-0"/>}<div className="flex-1 text-sm text-slate-700">{m.text}</div><button aria-label="Dismiss notification" onClick={() => setMessages(v => v.filter(x => x.id !== m.id))}><X size={16}/></button></div>)}</div></Ctx.Provider>;
}
export const useToast = () => useContext(Ctx);
