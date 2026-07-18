import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { login } from '../api/auth';
import { useAuth } from '../context/AuthContext';

const LoginPage: React.FC = () => {
  const [phone, setPhone] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const { setToken } = useAuth();
  const navigate = useNavigate();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError('');
    try {
      const res = await login(phone, password);
      setToken(res.data.token);
      navigate('/dashboard');
    } catch (err: any) {
      setError(err.response?.data?.message || 'Login failed. Check your credentials.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-navy-900 flex items-center justify-center px-4">
      <div className="w-full max-w-md">

        {/* Logo */}
        <div className="text-center mb-10">
          <h1 className="font-display text-5xl text-paper">Kitu</h1>
          <p className="text-kitu-green text-sm mt-2 tracking-widest uppercase">Analytics</p>
        </div>

        {/* Card */}
        <div className="bg-paper rounded-2xl p-8 shadow-2xl">
          <h2 className="font-display text-2xl text-navy-900 mb-1">Karibu tena</h2>
          <p className="text-navy-700 text-sm mb-8">Sign in to your business dashboard</p>

          {error && (
            <div className="bg-red-50 border border-kitu-red text-kitu-red text-sm rounded-lg px-4 py-3 mb-6">
              {error}
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-5">
            <div>
              <label className="block text-xs font-semibold text-navy-800 uppercase tracking-wider mb-2">
                Phone Number
              </label>
              <input
                type="tel"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                placeholder="07XXXXXXXX"
                className="w-full border border-navy-700/20 rounded-lg px-4 py-3 text-navy-900 bg-white focus:outline-none focus:ring-2 focus:ring-kitu-green text-sm"
                required
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-navy-800 uppercase tracking-wider mb-2">
                Password
              </label>
              <input
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="••••••••"
                className="w-full border border-navy-700/20 rounded-lg px-4 py-3 text-navy-900 bg-white focus:outline-none focus:ring-2 focus:ring-kitu-green text-sm"
                required
              />
            </div>
            <button
              type="submit"
              disabled={loading}
              className="w-full bg-kitu-green text-white font-semibold py-3 rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50 text-sm tracking-wide"
            >
              {loading ? 'Signing in...' : 'Sign In'}
            </button>
          </form>

          <p className="text-center text-sm text-navy-700 mt-6">
            No account?{' '}
            <Link to="/register" className="text-kitu-green font-semibold hover:underline">
              Register your business
            </Link>
          </p>
        </div>
      </div>
    </div>
  );
};

export default LoginPage;