import React from 'react';
import { BrowserRouter, Routes, Route, Navigate, NavLink } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AuthProvider, useAuth } from './context/AuthContext';
import LoginPage from './pages/LoginPage';
import RegisterPage from './pages/RegisterPage';
import DashboardPage from './pages/DashboardPage';
import BookkeepingPage from './pages/BookkeepingPage';
import LenderPortalPage from './pages/LenderPortalPage';
import AdminPage from './pages/AdminPage';
import ConsentPage from './pages/ConsentPage';
import LanguageToggle from './components/ui/LanguageToggle';
import { logout } from './api/auth';
import { LogOut } from 'lucide-react';
import OfflineIndicator from './components/ui/OfflineIndicator';

const PrivateRoute: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const { token, loading } = useAuth();
  const { t } = useTranslation();
  if (loading) return (
    <div className="min-h-screen bg-paper flex items-center justify-center">
      <div className="text-navy-900 font-display text-2xl">{t('common.loading')}</div>
    </div>
  );
  return token ? <>{children}</> : <Navigate to="/login" />;
};

const AppShell: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const { user, logout: authLogout } = useAuth();
  const { t } = useTranslation();

  const handleLogout = async () => {
    await logout();
    authLogout();
    window.location.href = '/login';
  };

  const navItems = [
    { to: '/dashboard', label: t('nav.credit_score') },
    { to: '/bookkeeping', label: t('nav.bookkeeping') },
    { to: '/lender', label: t('nav.lender') },
    { to: '/admin', label: t('nav.admin') },
  ];

  return (
    <div className="min-h-screen bg-paper">
      <header className="bg-navy-900 px-6 py-0 flex items-center justify-between">
        <div className="flex items-center gap-8">
          <div className="py-4">
            <h1 className="font-display text-xl text-paper leading-none">Kitu</h1>
            <p className="text-kitu-green text-xs tracking-widest uppercase">Analytics</p>
          </div>
          <nav className="hidden md:flex items-center gap-1">
            {navItems.map(item => (
              <NavLink key={item.to} to={item.to}
                className={({ isActive }) =>
                  `px-4 py-5 text-xs font-semibold transition-colors border-b-2 ${isActive
                    ? 'text-paper border-kitu-green'
                    : 'text-paper/50 border-transparent hover:text-paper/80'}`
                }>
                {item.label}
              </NavLink>
            ))}
          </nav>
        </div>
        <div className="flex items-center gap-3">
          <LanguageToggle />
          <span className="text-paper/40 text-xs hidden md:block">{user?.name}</span>
          <button onClick={handleLogout} title={t('common.logout')} className="text-paper/50 hover:text-paper transition-colors">
            <LogOut size={16} />
          </button>
        </div>
      </header>
      <main className="max-w-5xl mx-auto px-4 py-8">
        {children}
      </main>
      <OfflineIndicator />
    </div>
  );
};

const App: React.FC = () => {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/register" element={<RegisterPage />} />
          <Route path="/consent" element={
            <PrivateRoute><ConsentPage /></PrivateRoute>
          } />
          <Route path="/dashboard" element={
            <PrivateRoute><AppShell><DashboardPage /></AppShell></PrivateRoute>
          } />
          <Route path="/bookkeeping" element={
            <PrivateRoute><AppShell><BookkeepingPage /></AppShell></PrivateRoute>
          } />
          <Route path="/lender" element={
            <PrivateRoute><AppShell><LenderPortalPage /></AppShell></PrivateRoute>
          } />
          <Route path="/admin" element={
            <PrivateRoute><AppShell><AdminPage /></AppShell></PrivateRoute>
          } />
          <Route path="*" element={<Navigate to="/dashboard" />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  );
};

export default App;