import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { useAuth } from './contexts/AuthContext';
import Shell from './components/Shell';
import { Spinner } from './components/UI';
import { Landing, Login, Register, ForgotPassword, ChangePassword } from './pages/AuthPages';
import PublicDisplay from './pages/PublicDisplay';
import { AdminDashboard, StaffManagement, StudentManagement, ServicesManagement, CountersManagement, AllQueues, Reports, AnnouncementsAdmin, AuditLogs, ResetRequests, SystemSettings } from './pages/AdminPages';
import { StaffDashboard, StaffHistory } from './pages/StaffPages';
import { StudentDashboard, StudentServices, StudentHistory } from './pages/StudentPages';
import { Profile, Announcements, Notifications } from './pages/SharedPages';
function Gate({ role, children }) {const {user,loading}=useAuth();if(loading)return <Spinner label="Preparing your portal..."/>;if(!user)return <Navigate to="/login" replace/>;if(user.must_change_password)return <Navigate to="/change-password" replace/>;if(role && user.role!==role)return <Navigate to={`/${user.role}`} replace/>;return children;}
function RootRedirect(){const {user,loading}=useAuth();if(loading)return <Spinner/>;return <Navigate to={user?(user.must_change_password?'/change-password':`/${user.role}`):'/login'} replace/>;}
export default function App(){const base=import.meta.env.BASE_URL.replace(/\/$/,'');return <BrowserRouter basename={base||'/'}><Routes>
  <Route path="/" element={<Landing/>}/><Route path="/login" element={<Login/>}/><Route path="/register" element={<Register/>}/><Route path="/forgot-password" element={<ForgotPassword/>}/><Route path="/display" element={<PublicDisplay/>}/><Route path="/change-password" element={<ChangePassword/>}/>
  <Route element={<Gate role="admin"><Shell/></Gate>} path="/admin"><Route index element={<AdminDashboard/>}/><Route path="staff" element={<StaffManagement/>}/><Route path="students" element={<StudentManagement/>}/><Route path="queues" element={<AllQueues/>}/><Route path="services" element={<ServicesManagement/>}/><Route path="counters" element={<CountersManagement/>}/><Route path="reports" element={<Reports/>}/><Route path="announcements" element={<AnnouncementsAdmin/>}/><Route path="reset-requests" element={<ResetRequests/>}/><Route path="audit" element={<AuditLogs/>}/><Route path="settings" element={<SystemSettings/>}/><Route path="profile" element={<Profile/>}/></Route>
  <Route element={<Gate role="staff"><Shell/></Gate>} path="/staff"><Route index element={<StaffDashboard/>}/><Route path="history" element={<StaffHistory/>}/><Route path="notifications" element={<Notifications/>}/><Route path="profile" element={<Profile/>}/></Route>
  <Route element={<Gate role="student"><Shell/></Gate>} path="/student"><Route index element={<StudentDashboard/>}/><Route path="services" element={<StudentServices/>}/><Route path="history" element={<StudentHistory/>}/><Route path="notifications" element={<Notifications/>}/><Route path="announcements" element={<Announcements/>}/><Route path="profile" element={<Profile/>}/></Route>
  <Route path="*" element={<div className="mx-auto flex min-h-screen max-w-lg flex-col items-center justify-center p-6 text-center"><div className="text-7xl font-black text-primary-600">404</div><h1 className="mt-4 text-2xl font-bold">Page not found</h1><p className="mt-2 text-slate-500">The page you requested doesn't exist.</p><a className="btn-primary mt-6" href={import.meta.env.BASE_URL}>Go home</a></div>}/>
</Routes></BrowserRouter>}
