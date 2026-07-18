import React, { useEffect, useState } from 'react';
import { useAuth } from '../context/AuthContext';
import {
  getBusinesses,
  requestCreditScore,
  getTransactions,
  getBusinessSummary,
  parseSms,
} from '../api/business';
import { logout } from '../api/auth';
import { useNavigate } from 'react-router-dom';
import { AreaChart, Area, XAxis, YAxis, Tooltip, ResponsiveContainer } from 'recharts';
import { LogOut, TrendingUp, RefreshCw, Plus, X } from 'lucide-react';

interface Business {
  id: number;
  name: string;
  type: string;
  location: string;
  latest_credit_score?: {
    score: number;
    grade: string;
    repayment_likelihood: string;
    explanations?: Array<{
      factor: string;
      impact: string;
      explanation_en: string;
      explanation_sw: string;
    }>;
  };
}

interface Transaction {
  id: number;
  type: string;
  amount: string;
  counterparty_name: string;
  transacted_at: string;
}

interface Summary {
  total_incoming: number;
  total_outgoing: number;
  net_position: number;
  transaction_count: number;
  incoming_count: number;
  outgoing_count: number;
}

const ScoreGauge: React.FC<{ score: number; grade: string }> = ({ score, grade }) => {
  const percentage = (score / 1000) * 100;
  const circumference = 2 * Math.PI * 54;
  const strokeDashoffset = circumference - (percentage / 100) * circumference;
  const gradeColor = { A: '#00A651', B: '#00A651', C: '#F59E0B', D: '#EF4444', F: '#EF4444' }[grade] || '#F59E0B';

  return (
    <div className="flex flex-col items-center">
      <div className="relative w-36 h-36">
        <svg className="w-full h-full -rotate-90" viewBox="0 0 120 120">
          <circle cx="60" cy="60" r="54" fill="none" stroke="#E5E0D8" strokeWidth="8" />
          <circle cx="60" cy="60" r="54" fill="none" stroke={gradeColor} strokeWidth="8"
            strokeLinecap="round" strokeDasharray={circumference} strokeDashoffset={strokeDashoffset}
            style={{ transition: 'stroke-dashoffset 1.2s ease-in-out' }} />
        </svg>
        <div className="absolute inset-0 flex flex-col items-center justify-center">
          <span className="font-display text-4xl text-navy-900">{score}</span>
          <span className="text-xs text-navy-700 uppercase tracking-widest">/ 1000</span>
        </div>
      </div>
      <div className="mt-3">
        <span className="inline-block px-4 py-1 rounded-full text-white text-sm font-semibold"
          style={{ backgroundColor: gradeColor }}>
          Grade {grade}
        </span>
      </div>
    </div>
  );
};

// ── SMS Widget ──────────────────────────────────────────────────────────────
const SmsWidget: React.FC<{ businessId: number; onSuccess: () => void }> = ({ businessId, onSuccess }) => {
  const [open, setOpen] = useState(false);
  const [sms, setSms] = useState('');
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const handleParse = async () => {
    if (!sms.trim()) return;
    setLoading(true);
    setResult(null);
    setError(null);
    try {
      const res = await parseSms(businessId, sms);
      const t = res.data;
      setResult(`✓ ${t.type === 'incoming' ? 'Received' : 'Sent'} TZS ${parseFloat(t.amount).toLocaleString()} — ${t.counterparty_name}`);
      setSms('');
      onSuccess();
    } catch (err: any) {
      setError(err.response?.data?.message || 'Could not parse this SMS.');
    } finally {
      setLoading(false);
    }
  };

  if (!open) {
    return (
      <button
        onClick={() => setOpen(true)}
        className="flex items-center gap-2 bg-kitu-green text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-green-700 transition-colors"
      >
        <Plus size={16} /> Add M-Pesa SMS
      </button>
    );
  }

  return (
    <div className="bg-white rounded-2xl p-6 shadow-sm border border-navy-900/5">
      <div className="flex items-center justify-between mb-4">
        <p className="text-xs font-semibold text-navy-700 uppercase tracking-widest">Paste M-Pesa SMS</p>
        <button onClick={() => { setOpen(false); setResult(null); setError(null); }} className="text-navy-700 hover:text-navy-900">
          <X size={16} />
        </button>
      </div>
      <textarea
        value={sms}
        onChange={(e) => setSms(e.target.value)}
        placeholder={'Confirmed. You have received TZS 45,000 from JOHN MWAKAJE 0789123456 on 29/6/26 at 2:30 PM. ABC123XYZ09'}
        rows={3}
        className="w-full border border-navy-700/20 rounded-lg px-4 py-3 text-navy-900 bg-paper focus:outline-none focus:ring-2 focus:ring-kitu-green text-sm resize-none"
      />
      {result && <p className="text-kitu-green text-sm mt-2 font-medium">{result}</p>}
      {error && <p className="text-kitu-red text-sm mt-2">{error}</p>}
      <button
        onClick={handleParse}
        disabled={loading || !sms.trim()}
        className="mt-3 w-full bg-navy-900 text-white text-sm font-semibold py-2.5 rounded-lg hover:bg-navy-800 transition-colors disabled:opacity-40"
      >
        {loading ? 'Parsing...' : 'Parse & Save Transaction'}
      </button>
    </div>
  );
};

// ── Onboarding ───────────────────────────────────────────────────────────────
const OnboardingScreen: React.FC<{ onCreated: (b: Business) => void }> = ({ onCreated }) => {
  const [name, setName] = useState('');
  const [type, setType] = useState('retail');
  const [industry, setIndustry] = useState('');
  const [location, setLocation] = useState('');
  const [revenue, setRevenue] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const { user } = useAuth();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError('');
    try {
      const { createBusiness } = await import('../api/business');
      const res = await createBusiness({
        name, type, industry, location,
        monthly_revenue_estimate: revenue ? parseFloat(revenue) : undefined,
      });
      onCreated(res.data);
    } catch (err: any) {
      setError(err.response?.data?.message || 'Could not create business.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-navy-900 flex items-center justify-center px-4">
      <div className="w-full max-w-md">
        <div className="text-center mb-10">
          <h1 className="font-display text-5xl text-paper">Kitu</h1>
          <p className="text-kitu-green text-sm mt-2 tracking-widest uppercase">Analytics</p>
        </div>
        <div className="bg-paper rounded-2xl p-8 shadow-2xl">
          <h2 className="font-display text-2xl text-navy-900 mb-1">
            Karibu, {user?.name?.split(' ')[0]} 🎉
          </h2>
          <p className="text-navy-700 text-sm mb-8">
            Tell us about your business to get started.
          </p>

          {error && (
            <div className="bg-red-50 border border-kitu-red text-kitu-red text-sm rounded-lg px-4 py-3 mb-6">
              {error}
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-xs font-semibold text-navy-800 uppercase tracking-wider mb-2">Business Name</label>
              <input value={name} onChange={(e) => setName(e.target.value)}
                placeholder="Jasiri General Store"
                className="w-full border border-navy-700/20 rounded-lg px-4 py-3 text-navy-900 bg-white focus:outline-none focus:ring-2 focus:ring-kitu-green text-sm"
                required />
            </div>
            <div>
              <label className="block text-xs font-semibold text-navy-800 uppercase tracking-wider mb-2">Business Type</label>
              <select value={type} onChange={(e) => setType(e.target.value)}
                className="w-full border border-navy-700/20 rounded-lg px-4 py-3 text-navy-900 bg-white focus:outline-none focus:ring-2 focus:ring-kitu-green text-sm">
                <option value="retail">Retail</option>
                <option value="vendor">Vendor</option>
                <option value="service">Service</option>
                <option value="agricultural">Agricultural</option>
              </select>
            </div>
            <div>
              <label className="block text-xs font-semibold text-navy-800 uppercase tracking-wider mb-2">Industry</label>
              <input value={industry} onChange={(e) => setIndustry(e.target.value)}
                placeholder="Groceries, clothing, transport..."
                className="w-full border border-navy-700/20 rounded-lg px-4 py-3 text-navy-900 bg-white focus:outline-none focus:ring-2 focus:ring-kitu-green text-sm" />
            </div>
            <div>
              <label className="block text-xs font-semibold text-navy-800 uppercase tracking-wider mb-2">Location</label>
              <input value={location} onChange={(e) => setLocation(e.target.value)}
                placeholder="Moshi, Kilimanjaro"
                className="w-full border border-navy-700/20 rounded-lg px-4 py-3 text-navy-900 bg-white focus:outline-none focus:ring-2 focus:ring-kitu-green text-sm" />
            </div>
            <div>
              <label className="block text-xs font-semibold text-navy-800 uppercase tracking-wider mb-2">
                Est. Monthly Revenue (TZS)
              </label>
              <input value={revenue} onChange={(e) => setRevenue(e.target.value)}
                placeholder="500000"
                type="number"
                className="w-full border border-navy-700/20 rounded-lg px-4 py-3 text-navy-900 bg-white focus:outline-none focus:ring-2 focus:ring-kitu-green text-sm" />
            </div>
            <button type="submit" disabled={loading}
              className="w-full bg-kitu-green text-white font-semibold py-3 rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50 text-sm tracking-wide mt-2">
              {loading ? 'Setting up...' : 'Set Up My Business →'}
            </button>
          </form>
        </div>
      </div>
    </div>
  );
};

// ── Main Dashboard ────────────────────────────────────────────────────────────
const DashboardPage: React.FC = () => {
  const { user, logout: authLogout } = useAuth();
  const navigate = useNavigate();
  const [business, setBusiness] = useState<Business | null>(null);
  const [transactions, setTransactions] = useState<Transaction[]>([]);
  const [summary, setSummary] = useState<Summary | null>(null);
  const [loadingScore, setLoadingScore] = useState(false);
  const [loading, setLoading] = useState(true);
  const [noBusinessYet, setNoBusinessYet] = useState(false);

  const loadDashboard = async () => {
    try {
      const bizRes = await getBusinesses();
      if (bizRes.data.length === 0) {
        setNoBusinessYet(true);
        return;
      }
      const biz = bizRes.data[0];
      setBusiness(biz);

      const [txRes, summaryRes] = await Promise.all([
        getTransactions(biz.id),
        getBusinessSummary(biz.id),
      ]);
      setTransactions(txRes.data.data || []);
      setSummary(summaryRes.data);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { loadDashboard(); }, []);

  const handleRequestScore = async () => {
    if (!business) return;
    setLoadingScore(true);
    try {
      const res = await requestCreditScore(business.id);
      setBusiness((prev) => prev ? { ...prev, latest_credit_score: res.data } : prev);
    } finally {
      setLoadingScore(false);
    }
  };

  const handleLogout = async () => {
    await logout();
    authLogout();
    navigate('/login');
  };

  const handleBusinessCreated = (b: Business) => {
    setBusiness(b);
    setNoBusinessYet(false);
    setLoading(false);
  };

  const chartData = transactions
    .slice(0, 30)
    .reverse()
    .map((t) => ({
      date: new Date(t.transacted_at).toLocaleDateString('en-TZ', { day: 'numeric', month: 'short' }),
      amount: parseFloat(t.amount),
    }));

  if (loading) {
    return (
      <div className="min-h-screen bg-paper flex items-center justify-center">
        <div className="font-display text-3xl text-navy-900 animate-pulse">Loading...</div>
      </div>
    );
  }

  if (noBusinessYet) {
    return <OnboardingScreen onCreated={handleBusinessCreated} />;
  }

  return (
    <div className="min-h-screen bg-paper">
      {/* Header */}
      <header className="bg-navy-900 px-6 py-4 flex items-center justify-between">
        <div>
          <h1 className="font-display text-2xl text-paper">Kitu</h1>
          <p className="text-kitu-green text-xs tracking-widest uppercase">Analytics</p>
        </div>
        <div className="flex items-center gap-4">
          <span className="text-paper/70 text-sm hidden md:block">{user?.name}</span>
          <button onClick={handleLogout} className="text-paper/60 hover:text-paper transition-colors">
            <LogOut size={18} />
          </button>
        </div>
      </header>

      <main className="max-w-5xl mx-auto px-4 py-8 space-y-6">

        {/* Welcome + SMS button */}
        <div className="flex items-start justify-between flex-wrap gap-4">
          <div>
            <h2 className="font-display text-3xl text-navy-900">
              Habari, {user?.name?.split(' ')[0]}
            </h2>
            {business && (
              <p className="text-navy-700 text-sm mt-1">{business.name} · {business.location}</p>
            )}
          </div>
          {business && (
            <SmsWidget
              businessId={business.id}
              onSuccess={() => {
                setLoading(true);
                loadDashboard();
              }}
            />
          )}
        </div>

        {/* Score + Stats */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {/* Credit Score */}
          <div className="bg-white rounded-2xl p-6 shadow-sm border border-navy-900/5 flex flex-col items-center">
            <p className="text-xs font-semibold text-navy-700 uppercase tracking-widest mb-4">Credit Score</p>
            {business?.latest_credit_score ? (
              <>
                <ScoreGauge score={business.latest_credit_score.score} grade={business.latest_credit_score.grade} />
                <p className="text-xs text-navy-700 mt-4 text-center">
                  Repayment likelihood:{' '}
                  <span className="font-semibold text-navy-900">
                    {parseFloat(business.latest_credit_score.repayment_likelihood).toFixed(1)}%
                  </span>
                </p>
                <button onClick={handleRequestScore} disabled={loadingScore}
                  className="mt-4 flex items-center gap-2 text-xs text-kitu-green hover:text-green-700 font-semibold transition-colors disabled:opacity-50">
                  <RefreshCw size={12} className={loadingScore ? 'animate-spin' : ''} />
                  Recalculate
                </button>
              </>
            ) : (
              <div className="flex flex-col items-center gap-4 py-4">
                <div className="w-20 h-20 rounded-full border-4 border-dashed border-navy-900/20 flex items-center justify-center">
                  <TrendingUp size={28} className="text-navy-900/30" />
                </div>
                <p className="text-sm text-navy-700 text-center">No score yet</p>
                <button onClick={handleRequestScore} disabled={loadingScore}
                  className="bg-kitu-green text-white text-xs font-semibold px-5 py-2 rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50">
                  {loadingScore ? 'Calculating...' : 'Get My Score'}
                </button>
              </div>
            )}
          </div>

          {/* Stats from real summary */}
          <div className="md:col-span-2 grid grid-cols-2 gap-4">
            <div className="bg-white rounded-2xl p-5 shadow-sm border border-navy-900/5">
              <p className="text-xs font-semibold text-navy-700 uppercase tracking-widest mb-3">Total Incoming</p>
              <p className="font-display text-2xl text-kitu-green">
                TZS {summary?.total_incoming.toLocaleString() ?? '—'}
              </p>
              <p className="text-xs text-navy-700 mt-1">{summary?.incoming_count ?? 0} transactions</p>
            </div>
            <div className="bg-white rounded-2xl p-5 shadow-sm border border-navy-900/5">
              <p className="text-xs font-semibold text-navy-700 uppercase tracking-widest mb-3">Total Outgoing</p>
              <p className="font-display text-2xl text-kitu-red">
                TZS {summary?.total_outgoing.toLocaleString() ?? '—'}
              </p>
              <p className="text-xs text-navy-700 mt-1">{summary?.outgoing_count ?? 0} transactions</p>
            </div>
            <div className="bg-white rounded-2xl p-5 shadow-sm border border-navy-900/5">
              <p className="text-xs font-semibold text-navy-700 uppercase tracking-widest mb-3">Net Position</p>
              <p className={`font-display text-2xl ${(summary?.net_position ?? 0) >= 0 ? 'text-kitu-green' : 'text-kitu-red'}`}>
                TZS {summary?.net_position.toLocaleString() ?? '—'}
              </p>
            </div>
            <div className="bg-white rounded-2xl p-5 shadow-sm border border-navy-900/5">
              <p className="text-xs font-semibold text-navy-700 uppercase tracking-widest mb-3">Transactions</p>
              <p className="font-display text-2xl text-navy-900">{summary?.transaction_count ?? 0}</p>
              <p className="text-xs text-navy-700 mt-1">Last 90 days</p>
            </div>
          </div>
        </div>

        {/* Cash flow chart */}
        {chartData.length > 0 && (
          <div className="bg-white rounded-2xl p-6 shadow-sm border border-navy-900/5">
            <p className="text-xs font-semibold text-navy-700 uppercase tracking-widest mb-6">
              Cash Flow — Last 30 Transactions
            </p>
            <ResponsiveContainer width="100%" height={200}>
              <AreaChart data={chartData}>
                <defs>
                  <linearGradient id="colorAmount" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#00A651" stopOpacity={0.15} />
                    <stop offset="95%" stopColor="#00A651" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <XAxis dataKey="date" tick={{ fontSize: 10, fill: '#243B55' }} tickLine={false} axisLine={false} />
                <YAxis tick={{ fontSize: 10, fill: '#243B55' }} tickLine={false} axisLine={false}
                  tickFormatter={(v) => `${(v / 1000).toFixed(0)}K`} />
                <Tooltip formatter={(value) => [`TZS ${Number(value).toLocaleString()}`, 'Amount']}
                  contentStyle={{ borderRadius: '8px', border: '1px solid #E5E0D8', fontSize: '12px' }} />
                <Area type="monotone" dataKey="amount" stroke="#00A651" strokeWidth={2}
                  fill="url(#colorAmount)" dot={false} />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        )}

        {/* Score explanations */}
        {business?.latest_credit_score?.explanations && (
          <div className="bg-white rounded-2xl p-6 shadow-sm border border-navy-900/5">
            <p className="text-xs font-semibold text-navy-700 uppercase tracking-widest mb-4">
              Why Your Score Is {business.latest_credit_score.score}
            </p>
            <div className="space-y-3">
              {business.latest_credit_score.explanations.map((exp, i) => (
                <div key={i} className="flex items-start gap-3 p-3 rounded-lg bg-paper">
                  <div className={`mt-0.5 w-2 h-2 rounded-full flex-shrink-0 ${exp.impact === 'positive' ? 'bg-kitu-green' : 'bg-kitu-red'}`} />
                  <div>
                    <p className="text-sm text-navy-900">{exp.explanation_en}</p>
                    <p className="text-xs text-navy-700 mt-0.5 italic">{exp.explanation_sw}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* SMS widget (expanded) */}
        {business && (
          <SmsWidget
            businessId={business.id}
            onSuccess={() => {
              setLoading(true);
              loadDashboard();
            }}
          />
        )}

        {/* Recent transactions */}
        <div className="bg-white rounded-2xl p-6 shadow-sm border border-navy-900/5">
          <p className="text-xs font-semibold text-navy-700 uppercase tracking-widest mb-4">Recent Transactions</p>
          <div className="space-y-2">
            {transactions.slice(0, 10).map((t) => (
              <div key={t.id} className="flex items-center justify-between py-2 border-b border-paper last:border-0">
                <div className="flex items-center gap-3">
                  <div className={`w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold ${t.type === 'incoming' ? 'bg-kitu-green' : 'bg-kitu-red'}`}>
                    {t.type === 'incoming' ? '↓' : '↑'}
                  </div>
                  <div>
                    <p className="text-sm font-medium text-navy-900">{t.counterparty_name}</p>
                    <p className="text-xs text-navy-700">
                      {new Date(t.transacted_at).toLocaleDateString('en-TZ', { day: 'numeric', month: 'short', year: 'numeric' })}
                    </p>
                  </div>
                </div>
                <p className={`text-sm font-semibold ${t.type === 'incoming' ? 'text-kitu-green' : 'text-kitu-red'}`}>
                  {t.type === 'incoming' ? '+' : '-'} TZS {parseFloat(t.amount).toLocaleString()}
                </p>
              </div>
            ))}
          </div>
        </div>

      </main>
    </div>
  );
};

export default DashboardPage;