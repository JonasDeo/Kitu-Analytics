import React, { useEffect, useState } from 'react';
import { useAuth } from '../context/AuthContext';
import { getBusinesses, requestCreditScore, getTransactions } from '../api/business';
import { logout } from '../api/auth';
import { useNavigate } from 'react-router-dom';
import { AreaChart, Area, XAxis, YAxis, Tooltip, ResponsiveContainer } from 'recharts';
import { LogOut, TrendingUp, AlertCircle, RefreshCw } from 'lucide-react';

interface Business {
  id: number;
  name: string;
  type: string;
  location: string;
  latest_credit_score?: {
    score: number;
    grade: string;
    repayment_likelihood: string;
    cash_flow_stability_score: string;
    transaction_frequency_score: string;
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

const ScoreGauge: React.FC<{ score: number; grade: string }> = ({ score, grade }) => {
  const percentage = (score / 1000) * 100;
  const circumference = 2 * Math.PI * 54;
  const strokeDashoffset = circumference - (percentage / 100) * circumference;

  const gradeColor = {
    A: '#00A651', B: '#00A651', C: '#F59E0B', D: '#EF4444', F: '#EF4444'
  }[grade] || '#F59E0B';

  return (
    <div className="flex flex-col items-center">
      <div className="relative w-36 h-36">
        <svg className="w-full h-full -rotate-90" viewBox="0 0 120 120">
          <circle cx="60" cy="60" r="54" fill="none" stroke="#E5E0D8" strokeWidth="8" />
          <circle
            cx="60" cy="60" r="54" fill="none"
            stroke={gradeColor} strokeWidth="8"
            strokeLinecap="round"
            strokeDasharray={circumference}
            strokeDashoffset={strokeDashoffset}
            style={{ transition: 'stroke-dashoffset 1.2s ease-in-out' }}
          />
        </svg>
        <div className="absolute inset-0 flex flex-col items-center justify-center">
          <span className="font-display text-4xl text-navy-900">{score}</span>
          <span className="text-xs text-navy-700 uppercase tracking-widest">/ 1000</span>
        </div>
      </div>
      <div className="mt-3 text-center">
        <span
          className="inline-block px-4 py-1 rounded-full text-white text-sm font-semibold"
          style={{ backgroundColor: gradeColor }}
        >
          Grade {grade}
        </span>
      </div>
    </div>
  );
};

const DashboardPage: React.FC = () => {
  const { user, logout: authLogout } = useAuth();
  const navigate = useNavigate();
  const [business, setBusiness] = useState<Business | null>(null);
  const [transactions, setTransactions] = useState<Transaction[]>([]);
  const [loadingScore, setLoadingScore] = useState(false);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getBusinesses()
      .then((res) => {
        if (res.data.length > 0) {
          setBusiness(res.data[0]);
          return getTransactions(res.data[0].id);
        }
      })
      .then((res) => { if (res) setTransactions(res.data.data || []); })
      .finally(() => setLoading(false));
  }, []);

  const handleRequestScore = async () => {
    if (!business) return;
    setLoadingScore(true);
    try {
      const res = await requestCreditScore(business.id);
      setBusiness((prev) => prev ? {
        ...prev,
        latest_credit_score: res.data
      } : prev);
    } finally {
      setLoadingScore(false);
    }
  };

  const handleLogout = async () => {
    await logout();
    authLogout();
    navigate('/login');
  };

  // Build chart data from transactions
  const chartData = transactions
    .slice(0, 30)
    .reverse()
    .map((t) => ({
      date: new Date(t.transacted_at).toLocaleDateString('en-TZ', { day: 'numeric', month: 'short' }),
      amount: parseFloat(t.amount),
      type: t.type,
    }));

  const totalIncoming = transactions
    .filter((t) => t.type === 'incoming')
    .reduce((sum, t) => sum + parseFloat(t.amount), 0);

  const totalOutgoing = transactions
    .filter((t) => t.type !== 'incoming')
    .reduce((sum, t) => sum + parseFloat(t.amount), 0);

  if (loading) {
    return (
      <div className="min-h-screen bg-paper flex items-center justify-center">
        <div className="font-display text-3xl text-navy-900 animate-pulse">Loading...</div>
      </div>
    );
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

        {/* Welcome */}
        <div>
          <h2 className="font-display text-3xl text-navy-900">
            Habari, {user?.name?.split(' ')[0]} 👋
          </h2>
          {business && (
            <p className="text-navy-700 text-sm mt-1">{business.name} · {business.location}</p>
          )}
        </div>

        {/* Top row: Score + Stats */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">

          {/* Credit Score Card */}
          <div className="bg-white rounded-2xl p-6 shadow-sm border border-navy-900/5 flex flex-col items-center">
            <p className="text-xs font-semibold text-navy-700 uppercase tracking-widest mb-4">Credit Score</p>
            {business?.latest_credit_score ? (
              <>
                <ScoreGauge
                  score={business.latest_credit_score.score}
                  grade={business.latest_credit_score.grade}
                />
                <p className="text-xs text-navy-700 mt-4 text-center">
                  Repayment likelihood: <span className="font-semibold text-navy-900">
                    {parseFloat(business.latest_credit_score.repayment_likelihood).toFixed(1)}%
                  </span>
                </p>
                <button
                  onClick={handleRequestScore}
                  disabled={loadingScore}
                  className="mt-4 flex items-center gap-2 text-xs text-kitu-green hover:text-green-700 font-semibold transition-colors disabled:opacity-50"
                >
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
                <button
                  onClick={handleRequestScore}
                  disabled={loadingScore}
                  className="bg-kitu-green text-white text-xs font-semibold px-5 py-2 rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50"
                >
                  {loadingScore ? 'Calculating...' : 'Get My Score'}
                </button>
              </div>
            )}
          </div>

          {/* Stats */}
          <div className="md:col-span-2 grid grid-cols-2 gap-4">
            <div className="bg-white rounded-2xl p-5 shadow-sm border border-navy-900/5">
              <p className="text-xs font-semibold text-navy-700 uppercase tracking-widest mb-3">Total Incoming</p>
              <p className="font-display text-2xl text-kitu-green">
                TZS {totalIncoming.toLocaleString()}
              </p>
              <p className="text-xs text-navy-700 mt-1">{transactions.filter(t => t.type === 'incoming').length} transactions</p>
            </div>
            <div className="bg-white rounded-2xl p-5 shadow-sm border border-navy-900/5">
              <p className="text-xs font-semibold text-navy-700 uppercase tracking-widest mb-3">Total Outgoing</p>
              <p className="font-display text-2xl text-kitu-red">
                TZS {totalOutgoing.toLocaleString()}
              </p>
              <p className="text-xs text-navy-700 mt-1">{transactions.filter(t => t.type !== 'incoming').length} transactions</p>
            </div>
            <div className="bg-white rounded-2xl p-5 shadow-sm border border-navy-900/5">
              <p className="text-xs font-semibold text-navy-700 uppercase tracking-widest mb-3">Net Position</p>
              <p className={`font-display text-2xl ${totalIncoming - totalOutgoing >= 0 ? 'text-kitu-green' : 'text-kitu-red'}`}>
                TZS {(totalIncoming - totalOutgoing).toLocaleString()}
              </p>
            </div>
            <div className="bg-white rounded-2xl p-5 shadow-sm border border-navy-900/5">
              <p className="text-xs font-semibold text-navy-700 uppercase tracking-widest mb-3">Transactions</p>
              <p className="font-display text-2xl text-navy-900">{transactions.length}</p>
              <p className="text-xs text-navy-700 mt-1">Last 90 days</p>
            </div>
          </div>
        </div>

        {/* Cash flow chart */}
        {chartData.length > 0 && (
          <div className="bg-white rounded-2xl p-6 shadow-sm border border-navy-900/5">
            <p className="text-xs font-semibold text-navy-700 uppercase tracking-widest mb-6">Cash Flow — Last 30 Transactions</p>
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
                <Tooltip
                  formatter={(value) => [`TZS ${Number(value).toLocaleString()}`, 'Amount']}
                  contentStyle={{ borderRadius: '8px', border: '1px solid #E5E0D8', fontSize: '12px' }}
                />
                <Area type="monotone" dataKey="amount" stroke="#00A651" strokeWidth={2} fill="url(#colorAmount)" dot={false} />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        )}

        {/* Score explanations */}
        {business?.latest_credit_score?.explanations && (
          <div className="bg-white rounded-2xl p-6 shadow-sm border border-navy-900/5">
            <p className="text-xs font-semibold text-navy-700 uppercase tracking-widest mb-4">Why Your Score Is {business.latest_credit_score.score}</p>
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

        {/* Recent transactions */}
        <div className="bg-white rounded-2xl p-6 shadow-sm border border-navy-900/5">
          <p className="text-xs font-semibold text-navy-700 uppercase tracking-widest mb-4">Recent Transactions</p>
          <div className="space-y-2">
            {transactions.slice(0, 8).map((t) => (
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