import { useState } from 'react';
import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { Bell, BookOpen, ChartColumnIncreasing, ChevronRight, ClipboardList, FileText, GraduationCap, LayoutDashboard, LogOut, Megaphone, Menu, Monitor, PanelLeftClose, Settings, ShieldCheck, Ticket, UserCircle2, UserRoundCog, X } from 'lucide-react';
import { useAuth } from '../contexts/AuthContext';
import { useToast } from '../contexts/ToastContext';
import logo from '../assets/olpc-logo.jpg';

const nav = {
  admin: [
    ['', 'Overview', LayoutDashboard], ['staff', 'Staff Accounts', UserRoundCog], ['students', 'Students', GraduationCap], ['queues', 'All Queues', Ticket], ['services', 'Services', BookOpen], ['counters', 'Counters', Monitor], ['reports', 'Reports', ChartColumnIncreasing], ['announcements', 'Announcements', Megaphone], ['reset-requests', 'Reset Requests', ShieldCheck], ['audit', 'Audit Logs', ClipboardList], ['settings', 'Settings', Settings], ['profile', 'My Profile', UserCircle2]
  ],
  staff: [['', 'Workspace', LayoutDashboard], ['history', 'My Transactions', FileText], ['notifications', 'Notifications', Bell], ['profile', 'My Profile', UserCircle2]],
  student: [['', 'My Dashboard', LayoutDashboard], ['services', 'Get Queue Ticket', Ticket], ['history', 'Queue History', ClipboardList], ['announcements', 'Announcements', Megaphone], ['notifications', 'Notifications', Bell], ['profile', 'My Profile', UserCircle2]]
};

export function Brand({ light = false }) {
  return (
    <div className="flex items-center gap-3">
      <span className={`flex h-14 w-14 items-center justify-center overflow-hidden rounded-2xl border ${light ? 'border-white/25 bg-white/10' : 'border-primary-100 bg-white'} shadow-sm`}>
        <img src={logo} alt="Our Lady of the Pillar College of San Manuel logo" className="h-full w-full object-cover" />
      </span>
      <div className="leading-tight">
        <div className={`text-[10px] font-bold uppercase tracking-[.22em] ${light ? 'text-accent-100' : 'text-primary-700'}`}>OLPC San Manuel</div>
        <div className={`font-extrabold tracking-tight ${light ? 'text-white' : 'text-slate-900'}`}>Registrar Portal</div>
        <div className={`text-[11px] font-medium tracking-[.15em] ${light ? 'text-white/75' : 'text-slate-500'}`}>QUEUE MANAGEMENT SYSTEM</div>
      </div>
    </div>
  );
}

export default function Shell() {
  const { user, logout } = useAuth();
  const toast = useToast();
  const navigate = useNavigate();
  const [open, setOpen] = useState(false);
  const [collapsed, setCollapsed] = useState(false);
  const items = nav[user?.role] || [];
  const base = `/${user?.role}`;
  const leave = async () => {
    try {
      await logout();
      navigate('/login');
    } catch (e) {
      toast('Could not log out. Try again.', 'error');
    }
  };
  const initials = (user?.full_name || 'U').split(/\s+/).slice(0, 2).map((x) => x[0]).join('').toUpperCase();

  return <div className="min-h-screen bg-surface-100">
    {open && <div onClick={() => setOpen(false)} className="fixed inset-0 z-30 bg-slate-900/50 lg:hidden" />}
    <aside className={`fixed inset-y-0 left-0 z-40 flex flex-col border-r border-primary-100 bg-white transition-all duration-200 ${open ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'} ${collapsed ? 'w-[86px]' : 'w-[270px]'}`}>
      <div className={`flex h-[88px] items-center justify-between border-b border-primary-100 ${collapsed ? 'px-4' : 'px-6'}`}>
        <div className={collapsed ? 'hidden' : ''}><Brand /></div>
        {collapsed && <img src={logo} alt="School logo" className="mx-auto h-11 w-11 rounded-xl border border-primary-100 object-cover" />}
        <button onClick={() => setOpen(false)} className="rounded-lg p-1 hover:bg-slate-100 lg:hidden"><X size={20} /></button>
      </div>
      <div className="flex-1 overflow-y-auto px-3 py-5"><p className={`mb-3 px-3 text-[10px] font-bold uppercase tracking-[.2em] text-slate-400 ${collapsed ? 'hidden' : ''}`}>WORKSPACE</p>
        <nav className="space-y-1" aria-label="Main navigation">{items.map(([path, label, Icon]) => <NavLink onClick={() => setOpen(false)} key={label} end={!path} to={path ? `${base}/${path}` : base} title={collapsed ? label : undefined} className={({ isActive }) => `group flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium transition-all ${isActive ? 'bg-primary-50 font-bold text-primary-800 ring-1 ring-inset ring-primary-100' : 'text-slate-500 hover:bg-primary-50 hover:text-primary-800'} ${collapsed ? 'justify-center' : ''}`}><Icon size={19} className="shrink-0" />{!collapsed && <span className="flex-1">{label}</span>}{!collapsed && <ChevronRight size={14} className="opacity-0 group-hover:opacity-40" />}</NavLink>)}</nav>
      </div>
      <div className="border-t border-primary-100 p-4"><div className={`flex items-center gap-3 rounded-xl bg-primary-50 p-3 ${collapsed ? 'justify-center' : ''}`}><div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-accent-100 text-sm font-bold text-primary-800">{initials}</div>{!collapsed && <div className="min-w-0"><div className="truncate text-sm font-bold">{user?.full_name}</div><div className="text-xs capitalize text-slate-500">{user?.role}</div></div>}</div><button onClick={leave} className={`mt-2 flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-red-500 hover:bg-red-50 ${collapsed ? 'justify-center' : ''}`}><LogOut size={18} />{!collapsed && 'Sign out'}</button></div>
    </aside>
    <div className={`transition-all duration-200 ${collapsed ? 'lg:pl-[86px]' : 'lg:pl-[270px]'}`}>
      <header className="sticky top-0 z-20 flex h-[88px] items-center justify-between border-b border-primary-100 bg-white/95 px-5 backdrop-blur lg:px-9"><div className="flex items-center gap-3"><button className="rounded-xl p-2 hover:bg-slate-100 lg:hidden" onClick={() => setOpen(true)} aria-label="Open menu"><Menu size={22} /></button><button className="hidden rounded-xl p-2 text-slate-400 hover:bg-slate-100 lg:block" onClick={() => setCollapsed((v) => !v)} aria-label="Toggle sidebar"><PanelLeftClose size={19} /></button><div><div className="text-sm font-bold text-slate-900">Registrar Portal</div><div className="text-xs text-slate-500">Our Lady of the Pillar College of San Manuel</div></div></div><div className="flex items-center gap-3"><a href={`${import.meta.env.BASE_URL}display`} className="hidden items-center gap-2 rounded-xl border border-accent-200 bg-accent-50 px-3 py-2 text-xs font-semibold text-primary-800 hover:bg-accent-100 sm:flex" target="_blank" rel="noreferrer"><Monitor size={16} /> Public display</a><div className="h-9 w-9 rounded-full bg-brand-gradient text-center text-sm font-bold leading-9 text-white">{initials}</div></div></header>
      <main className="mx-auto max-w-[1500px] px-4 py-7 sm:px-6 lg:px-9 lg:py-8"><Outlet /></main>
      <footer className="px-9 pb-7 text-center text-xs text-slate-500">Our Lady of the Pillar College of San Manuel · Registrar Queue Management System</footer>
    </div>
  </div>;
}
