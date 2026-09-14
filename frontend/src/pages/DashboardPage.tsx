import React, { useEffect, useState } from 'react';
import { useAuth } from '../context/AuthContext';
import { useTranslation } from 'react-i18next';
import {
  getBusinesses, requestCreditScore, getTransactions,
  getBusinessSummary, parseSms, getNetworkAnalysis,
  getForecast, getCreditReport,
  submitAppeal,
  parsePhotoOcr,
} from '../api/business';
import { logout } from '../api/auth';
import { useNavigate } from 'react-router-dom';
import {
  AreaChart, Area, XAxis, YAxis, Tooltip, ResponsiveContainer,
  BarChart, Bar, Cell, ReferenceLine,
} from 'recharts';
import { LogOut, TrendingUp, RefreshCw, Plus, X, Download, Users, Globe } from 'lucide-react';

// ── Types ────────────────────────────────────────────────────────────────────
interface Business {
  id: number; name: string; type: string; location: string;
  latest_credit_score?: {
    score: number; grade: string; repayment_likelihood: string;
    explanations?: Array<{ factor: string; impact: string; explanation_en: string; explanation_sw: string }>;
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

interface NetworkData {
  total_nodes: number;
  unique_counterparties: number;
  loyal_counterparties: number;
  network_health_score: number;
  top_counterparties: Array<{ phone: string; total_volume: number }>;
}

interface ForecastDay {
  date: string;
  predicted_net_flow: number;
  seasonal_multiplier: number;
}

interface Forecast {
  forecast: ForecastDay[];
  summary: {
    total_predicted_net_flow: number;
    average_daily_flow: number;
    outlook: string;
  };
}

// ── Appeal Widget ─────────────────────────────────────────────────────────────
const AppealWidget: React.FC<{ businessId: number; score: number }> = ({ businessId, score }) => {
  const { t } = useTranslation();

  const [open, setOpen] = useState(false);
  const [reason, setReason] = useState('');
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async () => {
    if (reason.trim().length < 10) {
      setError('Tafadhali andika maelezo zaidi.');
      return;
    }

    setLoading(true);
    setError('');

    try {
      await submitAppeal(businessId, reason);
      setSuccess(true);
      setReason('');
    } catch (err: any) {
      setError(err.response?.data?.message || 'Imeshindwa kutuma malalamiko.');
    } finally {
      setLoading(false);
    }
  };

  if (success) return (
    <div className="mt-4 p-4 bg-green-50 border border-kitu-green rounded-xl text-sm text-kitu-green font-medium">
      ✓ Malalamiko yako yamepokelewa. Tutayashughulikia ndani ya masaa 48.
    </div>
  );

  if (!open) return (
    <button
      onClick={() => setOpen(true)}
      className="mt-4 text-xs text-navy-700 hover:text-navy-900 underline underline-offset-2 transition-colors"
    >
      {t('dashboard.appeal_link')}
    </button>
  );

  return (
    <div className="mt-4 p-4 bg-paper rounded-xl border border-navy-900/10 w-full">
      <div className="flex justify-between items-center mb-3">
        <p className="text-xs font-semibold text-navy-800 uppercase tracking-wider">
          {t('dashboard.appeal_title')}
        </p>

        <button onClick={() => setOpen(false)}>
          <X size={14} className="text-navy-700" />
        </button>
      </div>

      <p className="text-xs text-navy-700 mb-3">
        Alama yako ya sasa ni <strong>{score}</strong>. Eleza kwa nini unafikiri data si sahihi.
      </p>

      <textarea
        value={reason}
        onChange={(e) => setReason(e.target.value)}
        rows={3}
        placeholder="Mfano: Miamala mingine ni ya matumizi ya nyumbani, si ya biashara. Ninataka iondolewe..."
        className="w-full border border-navy-700/20 rounded-lg px-3 py-2 text-navy-900 bg-white focus:outline-none focus:ring-2 focus:ring-kitu-green text-xs resize-none"
      />

      {error && (
        <p className="text-kitu-red text-xs mt-1">{error}</p>
      )}

      <button
        onClick={handleSubmit}
        disabled={loading || reason.trim().length < 10}
        className="mt-2 w-full bg-navy-900 text-white text-xs font-semibold py-2 rounded-lg hover:bg-navy-800 transition-colors disabled:opacity-40"
      >
        {loading ? 'Inatuma...' : t('dashboard.appeal_submit')}
      </button>
    </div>
  );
};

// ── Score Gauge ───────────────────────────────────────────────────────────────
const ScoreGauge: React.FC<{ score: number; grade: string }> = ({ score, grade }) => {
  const pct = (score / 1000) * 100;
  const circ = 2 * Math.PI * 54;
  const offset = circ - (pct / 100) * circ;

  const color = {
    A: '#00A651',
    B: '#00A651',
    C: '#F59E0B',
    D: '#EF4444',
    F: '#EF4444'
  }[grade] || '#F59E0B';

  return (
    <div className="flex flex-col items-center">
      <div className="relative w-36 h-36">
        <svg className="w-full h-full -rotate-90" viewBox="0 0 120 120">
          <circle
            cx="60"
            cy="60"
            r="54"
            fill="none"
            stroke="#E5E0D8"
            strokeWidth="8"
          />

          <circle
            cx="60"
            cy="60"
            r="54"
            fill="none"
            stroke={color}
            strokeWidth="8"
            strokeLinecap="round"
            strokeDasharray={circ}
            strokeDashoffset={offset}
            style={{ transition: 'stroke-dashoffset 1.2s ease-in-out' }}
          />
        </svg>

        <div className="absolute inset-0 flex flex-col items-center justify-center">
          <span className="font-display text-4xl text-navy-900">
            {score}
          </span>

          <span className="text-xs text-navy-700 uppercase tracking-widest">
            / 1000
          </span>
        </div>
      </div>

      <span
        className="mt-3 inline-block px-4 py-1 rounded-full text-white text-sm font-semibold"
        style={{ backgroundColor: color }}
      >
        Grade {grade}
      </span>
    </div>
  );
};

const OcrWidget: React.FC<{ businessId: number; onSuccess: () => void }> = ({ businessId, onSuccess }) => {
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const fileRef = React.useRef<HTMLInputElement>(null);

  const handleFile = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setLoading(true); setResult(null); setError(null);
    try {
      const res = await parsePhotoOcr(businessId, file);
      const d = res.data;
      setResult(`✓ Nimepata miamala ${d.transactions_found} — imehifadhiwa ${d.transactions_saved} mpya`);
      onSuccess();
    } catch (err: any) {
      setError(err.response?.data?.message || 'OCR imeshindwa. Jaribu tena.');
    } finally {
      setLoading(false);
      if (fileRef.current) fileRef.current.value = '';
    }
  };

  if (!open) return (
    <button onClick={() => setOpen(true)}
      className="flex items-center gap-2 bg-navy-800 text-paper text-sm font-semibold px-4 py-2 rounded-lg hover:bg-navy-700 transition-colors">
      📸 Pakia Picha ya M-Pesa
    </button>
  );

  return (
    <div className="bg-white rounded-2xl p-6 shadow-sm border border-navy-900/5">
      <div className="flex items-center justify-between mb-4">
        <p className="text-xs font-semibold text-navy-700 uppercase tracking-widest">OCR — Pakia Picha ya SMS</p>
        <button onClick={() => { setOpen(false); setResult(null); setError(null); }}>
          <X size={16} />
        </button>
      </div>
      <p className="text-xs text-navy-700 mb-4">
        Piga picha ya SMS zako za M-Pesa. Mfumo utasoma na kuhifadhi miamala automatically.
      </p>
      <input
        ref={fileRef}
        type="file"
        accept="image/jpeg,image/png,image/webp"
        onChange={handleFile}
        disabled={loading}
        className="w-full text-sm text-navy-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-navy-900 file:text-paper file:text-xs file:font-semibold hover:file:bg-navy-800 cursor-pointer"
      />
      {loading && <p className="text-navy-700 text-sm mt-3 animate-pulse">⏳ Inasoma picha...</p>}
      {result && <p className="text-kitu-green text-sm mt-3 font-medium">{result}</p>}
      {error && <p className="text-kitu-red text-sm mt-3">{error}</p>}
    </div>
    
  );
};

// ── SMS Widget ────────────────────────────────────────────────────────────────
const SmsWidget: React.FC<{ businessId: number; onSuccess: () => void }> = ({ businessId, onSuccess }) => {
  const { t } = useTranslation();

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
      const transaction = res.data;

      setResult(
        `✓ ${transaction.type === 'incoming' ? 'Received' : 'Sent'} TZS ${parseFloat(transaction.amount).toLocaleString()} — ${transaction.counterparty_name}`
      );

      setSms('');
      onSuccess();
    } catch (err: any) {
      setError(
        err.response?.data?.message || 'Could not parse this SMS.'
      );
    } finally {
      setLoading(false);
    }
  };

  if (!open) return (
    <button
      onClick={() => setOpen(true)}
      className="flex items-center gap-2 bg-kitu-green text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-green-700 transition-colors"
    >
      <Plus size={16} />
      {t('dashboard.add_sms')}
    </button>
  );

  return (
    <div className="bg-white rounded-2xl p-6 shadow-sm border border-navy-900/5">
      <div className="flex items-center justify-between mb-4">
        <p className="text-xs font-semibold text-navy-700 uppercase tracking-widest">
          Paste M-Pesa SMS
        </p>

        <button
          onClick={() => {
            setOpen(false);
            setResult(null);
            setError(null);
          }}
        >
          <X size={16} />
        </button>
      </div>

      <textarea
        value={sms}
        onChange={(e) => setSms(e.target.value)}
        rows={3}
        placeholder="Confirmed. You have received TZS 45,000 from JOHN MWAKAJE 0789123456 on 29/6/26 at 2:30 PM. ABC123XYZ09"
        className="w-full border border-navy-700/20 rounded-lg px-4 py-3 text-navy-900 bg-paper focus:outline-none focus:ring-2 focus:ring-kitu-green text-sm resize-none"
      />

      {result && (
        <p className="text-kitu-green text-sm mt-2 font-medium">
          {result}
        </p>
      )}

      {error && (
        <p className="text-kitu-red text-sm mt-2">
          {error}
        </p>
      )}

      <button
        onClick={handleParse}
        disabled={loading || !sms.trim()}
        className="mt-3 w-full bg-navy-900 text-white text-sm font-semibold py-2.5 rounded-lg hover:bg-navy-800 transition-colors disabled:opacity-40"
      >
        {loading ? t('dashboard.parsing') : t('dashboard.parse_sms')}
      </button>
    </div>
  );
};

// ── Onboarding ────────────────────────────────────────────────────────────────
const OnboardingScreen: React.FC<{ onCreated: (b: Business) => void }> = ({ onCreated }) => {
  const { t } = useTranslation();

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
        name,
        type,
        industry,
        location,
        monthly_revenue_estimate: revenue
          ? parseFloat(revenue)
          : undefined
      });

      onCreated(res.data);
    } catch (err: any) {
      setError(
        err.response?.data?.message || 'Could not create business.'
      );
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-navy-900 flex items-center justify-center px-4">
      <div className="w-full max-w-md">

        <div className="text-center mb-10">
          <h1 className="font-display text-5xl text-paper">
            Kitu
          </h1>

          <p className="text-kitu-green text-sm mt-2 tracking-widest uppercase">
            Analytics
          </p>
        </div>

        <div className="bg-paper rounded-2xl p-8 shadow-2xl">
          <h2 className="font-display text-2xl text-navy-900 mb-1">
            Karibu, {user?.name?.split(' ')[0]} 🎉
          </h2>

          <p className="text-navy-700 text-sm mb-8">
            {t('onboarding.subtitle')}
          </p>

          {error && (
            <div className="bg-red-50 border border-kitu-red text-kitu-red text-sm rounded-lg px-4 py-3 mb-6">
              {error}
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-4">

            {[
              {
                label: t('onboarding.business_name'),
                value: name,
                set: setName,
                placeholder: 'Jasiri General Store',
                required: true
              },
              {
                label: t('onboarding.industry'),
                value: industry,
                set: setIndustry,
                placeholder: 'Groceries, nguo, usafiri...'
              },
              {
                label: t('onboarding.location'),
                value: location,
                set: setLocation,
                placeholder: 'Moshi, Kilimanjaro'
              },
            ].map((f) => (
              <div key={f.label}>
                <label className="block text-xs font-semibold text-navy-800 uppercase tracking-wider mb-2">
                  {f.label}
                </label>

                <input
                  value={f.value}
                  onChange={(e) => f.set(e.target.value)}
                  placeholder={f.placeholder}
                  required={f.required}
                  className="w-full border border-navy-700/20 rounded-lg px-4 py-3 text-navy-900 bg-white focus:outline-none focus:ring-2 focus:ring-kitu-green text-sm"
                />
              </div>
            ))}

            <div>
              <label className="block text-xs font-semibold text-navy-800 uppercase tracking-wider mb-2">
                {t('onboarding.business_type')}
              </label>

              <select
                value={type}
                onChange={(e) => setType(e.target.value)}
                className="w-full border border-navy-700/20 rounded-lg px-4 py-3 text-navy-900 bg-white focus:outline-none focus:ring-2 focus:ring-kitu-green text-sm"
              >
                <option value="retail">
                  {t('onboarding.types.retail')}
                </option>

                <option value="vendor">
                  {t('onboarding.types.vendor')}
                </option>

                <option value="service">
                  {t('onboarding.types.service')}
                </option>

                <option value="agricultural">
                  {t('onboarding.types.agricultural')}
                </option>
              </select>
            </div>

            <div>
              <label className="block text-xs font-semibold text-navy-800 uppercase tracking-wider mb-2">
                {t('onboarding.monthly_revenue')}
              </label>

              <input
                value={revenue}
                onChange={(e) => setRevenue(e.target.value)}
                placeholder="500000"
                type="number"
                className="w-full border border-navy-700/20 rounded-lg px-4 py-3 text-navy-900 bg-white focus:outline-none focus:ring-2 focus:ring-kitu-green text-sm"
              />
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full bg-kitu-green text-white font-semibold py-3 rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50 text-sm tracking-wide mt-2"
            >
              {loading
                ? t('onboarding.submitting')
                : t('onboarding.submit')}
            </button>

          </form>
        </div>
      </div>
    </div>
  );
};

// ── Main Dashboard ────────────────────────────────────────────────────────────
const DashboardPage: React.FC = () => {
  const { t } = useTranslation();

  const { user, logout: authLogout } = useAuth();
  const navigate = useNavigate();

  const [business, setBusiness] = useState<Business | null>(null);
  const [transactions, setTransactions] = useState<Transaction[]>([]);
  const [summary, setSummary] = useState<Summary | null>(null);
  const [network, setNetwork] = useState<NetworkData | null>(null);
  const [forecast, setForecast] = useState<Forecast | null>(null);
  const [loadingScore, setLoadingScore] = useState(false);
  const [loadingReport, setLoadingReport] = useState(false);
  const [loading, setLoading] = useState(true);
  const [noBusinessYet, setNoBusinessYet] = useState(false);
  const [activeTab, setActiveTab] = useState<
    'overview' | 'forecast' | 'network'
  >('overview');

  const loadDashboard = async () => {
    try {
      const bizRes = await getBusinesses();

      if (bizRes.data.length === 0) {
        setNoBusinessYet(true);
        return;
      }

      const biz = bizRes.data[0];
      setBusiness(biz);

      const [
        txRes,
        summaryRes,
        networkRes,
        forecastRes
      ] = await Promise.all([
        getTransactions(biz.id),
        getBusinessSummary(biz.id),
        getNetworkAnalysis(biz.id),
        getForecast(biz.id),
      ]);

      setTransactions(txRes.data.data || []);
      setSummary(summaryRes.data);
      setNetwork(networkRes.data);
      setForecast(forecastRes.data);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadDashboard();
  }, []);

  const handleRequestScore = async () => {
    if (!business) return;

    setLoadingScore(true);

    try {
      const res = await requestCreditScore(business.id);

      setBusiness((prev) =>
        prev
          ? {
              ...prev,
              latest_credit_score: res.data
            }
          : prev
      );
    } finally {
      setLoadingScore(false);
    }
  };

  const handleDownloadReport = async () => {
    if (!business) return;

    setLoadingReport(true);

    try {
      const res = await getCreditReport(business.id);

      const url = window.URL.createObjectURL(
        new Blob([res.data], {
          type: 'application/pdf'
        })
      );

      const a = document.createElement('a');
      a.href = url;
      a.download = `kitu_report_${business.name}.pdf`;
      a.click();

      window.URL.revokeObjectURL(url);
    } finally {
      setLoadingReport(false);
    }
  };

  const handleLogout = async () => {
    await logout();
    authLogout();
    navigate('/login');
  };

  const chartData = transactions
    .slice(0, 30)
    .reverse()
    .map((t) => ({
      date: new Date(t.transacted_at).toLocaleDateString(
        'en-TZ',
        {
          day: 'numeric',
          month: 'short'
        }
      ),
      amount: parseFloat(t.amount),
    }));

  const forecastChartData =
    forecast?.forecast.map((f) => ({
      date: new Date(f.date).toLocaleDateString(
        'en-TZ',
        {
          day: 'numeric',
          month: 'short'
        }
      ),
      flow: f.predicted_net_flow,
      positive:
        f.predicted_net_flow >= 0
          ? f.predicted_net_flow
          : 0,
      negative:
        f.predicted_net_flow < 0
          ? f.predicted_net_flow
          : 0,
    })) || [];

  if (loading) {
    return (
      <div className="min-h-screen bg-paper flex items-center justify-center">
        <div className="font-display text-3xl text-navy-900 animate-pulse">
          {t('dashboard.loading')}
        </div>
      </div>
    );
  }

  if (noBusinessYet) {
    return (
      <OnboardingScreen
        onCreated={(b) => {
          setBusiness(b);
          setNoBusinessYet(false);
          setLoading(false);
        }}
      />
    );
  }

  return (
    <div className="min-h-screen bg-paper">

      {/* Header */}
      <header className="bg-navy-900 px-6 py-4 flex items-center justify-between">
        <div>
          <h1 className="font-display text-2xl text-paper">
            Kitu
          </h1>

          <p className="text-kitu-green text-xs tracking-widest uppercase">
            Analytics
          </p>
        </div>

        <div className="flex items-center gap-3">
          {business && (
            <button
              onClick={handleDownloadReport}
              disabled={loadingReport}
              className="flex items-center gap-2 text-paper/70 hover:text-paper text-xs transition-colors disabled:opacity-40"
            >
              <Download size={14} />

              {loadingReport
                ? 'Inatengeneza...'
                : 'Ripoti ya PDF'}
            </button>
          )}

          <span className="text-paper/50 text-xs hidden md:block">
            {user?.name}
          </span>

          <button
            onClick={handleLogout}
            className="text-paper/60 hover:text-paper transition-colors"
          >
            <LogOut size={18} />
          </button>
        </div>
      </header>

      <main className="max-w-5xl mx-auto px-4 py-8 space-y-6">

        {/* Welcome */}
        <div className="flex items-start justify-between flex-wrap gap-4">
          <div>
            <h2 className="font-display text-3xl text-navy-900">
              {t('dashboard.greeting')}, {user?.name?.split(' ')[0]} 👋
            </h2>

            {business && (
              <p className="text-navy-700 text-sm mt-1">
                {business.name} · {business.location}
              </p>
            )}
          </div>

          <div className="flex items-center gap-3 flex-wrap">
            {business && (
              <>
                <OcrWidget
                  businessId={business.id}
                  onSuccess={() => {
                    setLoading(true);
                    loadDashboard();
                  }}
                />

                <SmsWidget
                  businessId={business.id}
                  onSuccess={() => {
                    setLoading(true);
                    loadDashboard();
                  }}
                />
              </>
            )}
          </div>
        </div>

        {/* Score + Stats */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">

          <div className="bg-white rounded-2xl p-6 shadow-sm border border-navy-900/5 flex flex-col items-center">

            <p className="text-xs font-semibold text-navy-700 uppercase tracking-widest mb-4">
              {t('dashboard.credit_score_label')}
            </p>

            {business?.latest_credit_score ? (
              <>
                <ScoreGauge
                  score={business.latest_credit_score.score}
                  grade={business.latest_credit_score.grade}
                />

                <p className="text-xs text-navy-700 mt-4 text-center">
                  {t('dashboard.repayment_likelihood')}:{' '}
                  <span className="font-semibold text-navy-900">
                    {parseFloat(
                      business.latest_credit_score.repayment_likelihood
                    ).toFixed(1)}
                    %
                  </span>
                </p>

                <button
                  onClick={handleRequestScore}
                  disabled={loadingScore}
                  className="mt-4 flex items-center gap-2 text-xs text-kitu-green hover:text-green-700 font-semibold transition-colors disabled:opacity-50"
                >
                  <RefreshCw
                    size={12}
                    className={loadingScore ? 'animate-spin' : ''}
                  />

                  {t('dashboard.recalculate')}
                </button>

                <AppealWidget
                  businessId={business.id}
                  score={business.latest_credit_score.score}
                />
              </>
            ) : (
              <div className="flex flex-col items-center gap-4 py-4">

                <div className="w-20 h-20 rounded-full border-4 border-dashed border-navy-900/20 flex items-center justify-center">
                  <TrendingUp
                    size={28}
                    className="text-navy-900/30"
                  />
                </div>

                <p className="text-sm text-navy-700 text-center">
                  {t('dashboard.no_score')}
                </p>

                <button
                  onClick={handleRequestScore}
                  disabled={loadingScore}
                  className="bg-kitu-green text-white text-xs font-semibold px-5 py-2 rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50"
                >
                  {loadingScore
                    ? 'Inahesabu...'
                    : t('dashboard.get_score')}
                </button>
              </div>
            )}
          </div>

          <div className="md:col-span-2 grid grid-cols-2 gap-4">
            {[
              {
                label: t('dashboard.total_incoming'),
                value: summary?.total_incoming,
                count: summary?.incoming_count,
                color: 'text-kitu-green'
              },
              {
                label: t('dashboard.total_outgoing'),
                value: summary?.total_outgoing,
                count: summary?.outgoing_count,
                color: 'text-kitu-red'
              },
              {
                label: t('dashboard.net_position'),
                value: summary?.net_position,
                color:
                  (summary?.net_position ?? 0) >= 0
                    ? 'text-kitu-green'
                    : 'text-kitu-red'
              },
              {
                label: t('dashboard.transactions'),
                value: null,
                count: summary?.transaction_count,
                color: 'text-navy-900',
                label2: t('dashboard.last_90_days')
              },
            ].map((s, i) => (
              <div
                key={i}
                className="bg-white rounded-2xl p-5 shadow-sm border border-navy-900/5"
              >
                <p className="text-xs font-semibold text-navy-700 uppercase tracking-widest mb-3">
                  {s.label}
                </p>

                {s.value !== undefined && s.value !== null ? (
                  <p className={`font-display text-2xl ${s.color}`}>
                    TZS {s.value.toLocaleString()}
                  </p>
                ) : (
                  <p className={`font-display text-2xl ${s.color}`}>
                    {s.count ?? 0}
                  </p>
                )}

                {s.count !== undefined &&
                  s.value !== undefined &&
                  s.value !== null && (
                    <p className="text-xs text-navy-700 mt-1">
                      {s.count} miamala
                    </p>
                  )}

                {s.label2 && (
                  <p className="text-xs text-navy-700 mt-1">
                    {s.label2}
                  </p>
                )}
              </div>
            ))}
          </div>
        </div>

        {/* Tabs */}
        <div className="flex gap-1 bg-white rounded-xl p-1 shadow-sm border border-navy-900/5 w-fit">
          {(['overview', 'forecast', 'network'] as const).map((tab) => (
            <button
              key={tab}
              onClick={() => setActiveTab(tab)}
              className={`px-5 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-colors ${
                activeTab === tab
                  ? 'bg-navy-900 text-paper'
                  : 'text-navy-700 hover:text-navy-900'
              }`}
            >
              {tab === 'overview'
                ? t('dashboard.overview')
                : tab === 'forecast'
                  ? t('dashboard.forecast')
                  : t('dashboard.network')}
            </button>
          ))}
        </div>

        {/* Overview Tab */}
        {activeTab === 'overview' && (
          <>
            {chartData.length > 0 && (
              <div className="bg-white rounded-2xl p-6 shadow-sm border border-navy-900/5">

                <p className="text-xs font-semibold text-navy-700 uppercase tracking-widest mb-6">
                  {t('dashboard.cash_flow_chart')}
                </p>

                <ResponsiveContainer width="100%" height={200}>
                  <AreaChart data={chartData}>
                    <defs>
                      <linearGradient
                        id="colorAmount"
                        x1="0"
                        y1="0"
                        x2="0"
                        y2="1"
                      >
                        <stop
                          offset="5%"
                          stopColor="#00A651"
                          stopOpacity={0.15}
                        />

                        <stop
                          offset="95%"
                          stopColor="#00A651"
                          stopOpacity={0}
                        />
                      </linearGradient>
                    </defs>

                    <XAxis
                      dataKey="date"
                      tick={{
                        fontSize: 10,
                        fill: '#243B55'
                      }}
                      tickLine={false}
                      axisLine={false}
                    />

                    <YAxis
                      tick={{
                        fontSize: 10,
                        fill: '#243B55'
                      }}
                      tickLine={false}
                      axisLine={false}
                      tickFormatter={(v) =>
                        `${(v / 1000).toFixed(0)}K`
                      }
                    />

                    <Tooltip
                      formatter={(value) => [
                        `TZS ${Number(value).toLocaleString()}`,
                        'Kiasi'
                      ]}
                      contentStyle={{
                        borderRadius: '8px',
                        border: '1px solid #E5E0D8',
                        fontSize: '12px'
                      }}
                    />

                    <Area
                      type="monotone"
                      dataKey="amount"
                      stroke="#00A651"
                      strokeWidth={2}
                      fill="url(#colorAmount)"
                      dot={false}
                    />
                  </AreaChart>
                </ResponsiveContainer>
              </div>
            )}

            {business?.latest_credit_score?.explanations && (
              <div className="bg-white rounded-2xl p-6 shadow-sm border border-navy-900/5">

                <p className="text-xs font-semibold text-navy-700 uppercase tracking-widest mb-4">
                  {t('dashboard.score_explanation')}{' '}
                  {business.latest_credit_score.score}
                </p>

                <div className="space-y-3">
                  {business.latest_credit_score.explanations.map((exp, i) => (
                    <div
                      key={i}
                      className="flex items-start gap-3 p-3 rounded-lg bg-paper"
                    >
                      <div
                        className={`mt-0.5 w-2 h-2 rounded-full flex-shrink-0 ${
                          exp.impact === 'positive'
                            ? 'bg-kitu-green'
                            : 'bg-kitu-red'
                        }`}
                      />

                      <div>
                        <p className="text-sm text-navy-900">
                          {exp.explanation_en}
                        </p>

                        <p className="text-xs text-navy-700 mt-0.5 italic">
                          {exp.explanation_sw}
                        </p>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            <div className="bg-white rounded-2xl p-6 shadow-sm border border-navy-900/5">

              <p className="text-xs font-semibold text-navy-700 uppercase tracking-widest mb-4">
                {t('dashboard.recent_transactions')}
              </p>

              <div className="space-y-2">
                {transactions.slice(0, 10).map((t) => (
                  <div
                    key={t.id}
                    className="flex items-center justify-between py-2 border-b border-paper last:border-0"
                  >
                    <div className="flex items-center gap-3">

                      <div
                        className={`w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold ${
                          t.type === 'incoming'
                            ? 'bg-kitu-green'
                            : 'bg-kitu-red'
                        }`}
                      >
                        {t.type === 'incoming' ? '↓' : '↑'}
                      </div>

                      <div>
                        <p className="text-sm font-medium text-navy-900">
                          {t.counterparty_name}
                        </p>

                        <p className="text-xs text-navy-700">
                          {new Date(
                            t.transacted_at
                          ).toLocaleDateString(
                            'en-TZ',
                            {
                              day: 'numeric',
                              month: 'short',
                              year: 'numeric'
                            }
                          )}
                        </p>
                      </div>
                    </div>

                    <p
                      className={`text-sm font-semibold ${
                        t.type === 'incoming'
                          ? 'text-kitu-green'
                          : 'text-kitu-red'
                      }`}
                    >
                      {t.type === 'incoming' ? '+' : '-'} TZS{' '}
                      {parseFloat(t.amount).toLocaleString()}
                    </p>
                  </div>
                ))}
              </div>
            </div>
          </>
        )}

        {/* Forecast Tab */}
        {activeTab === 'forecast' && forecast && (
          <>
            <div className="grid grid-cols-3 gap-4">
              {[
                {
                  label: 'Jumla Iliyotabiriwa (Siku 14)',
                  value: `TZS ${forecast.summary.total_predicted_net_flow.toLocaleString()}`,
                  color:
                    forecast.summary.outlook === 'positive'
                      ? 'text-kitu-green'
                      : 'text-kitu-red'
                },
                {
                  label: 'Wastani wa Kila Siku',
                  value: `TZS ${forecast.summary.average_daily_flow.toLocaleString()}`,
                  color: 'text-navy-900'
                },
                {
                  label: 'Mwelekeo',
                  value:
                    forecast.summary.outlook === 'positive'
                      ? '📈 Nzuri'
                      : '📉 Angalia',
                  color:
                    forecast.summary.outlook === 'positive'
                      ? 'text-kitu-green'
                      : 'text-kitu-red'
                },
              ].map((s, i) => (
                <div
                  key={i}
                  className="bg-white rounded-2xl p-5 shadow-sm border border-navy-900/5"
                >
                  <p className="text-xs font-semibold text-navy-700 uppercase tracking-widest mb-2">
                    {s.label}
                  </p>

                  <p className={`font-display text-lg ${s.color}`}>
                    {s.value}
                  </p>
                </div>
              ))}
            </div>

            <div className="bg-white rounded-2xl p-6 shadow-sm border border-navy-900/5">

              <p className="text-xs font-semibold text-navy-700 uppercase tracking-widest mb-6">
                Utabiri wa Fedha — Siku 14 Zijazo
              </p>

              <ResponsiveContainer width="100%" height={220}>
                <BarChart data={forecastChartData}>
                  <XAxis
                    dataKey="date"
                    tick={{
                      fontSize: 10,
                      fill: '#243B55'
                    }}
                    tickLine={false}
                    axisLine={false}
                  />

                  <YAxis
                    tick={{
                      fontSize: 10,
                      fill: '#243B55'
                    }}
                    tickLine={false}
                    axisLine={false}
                    tickFormatter={(v) =>
                      `${(v / 1000).toFixed(0)}K`
                    }
                  />

                  <Tooltip
                    formatter={(value) => [
                      `TZS ${Number(value).toLocaleString()}`,
                      'Utabiri'
                    ]}
                    contentStyle={{
                      borderRadius: '8px',
                      border: '1px solid #E5E0D8',
                      fontSize: '12px'
                    }}
                  />

                  <ReferenceLine
                    y={0}
                    stroke="#0D1B2A"
                    strokeOpacity={0.2}
                  />

                  <Bar
                    dataKey="flow"
                    radius={[4, 4, 0, 0]}
                  >
                    {forecastChartData.map((entry, index) => (
                      <Cell
                        key={index}
                        fill={
                          entry.flow >= 0
                            ? '#00A651'
                            : '#EF4444'
                        }
                        fillOpacity={0.8}
                      />
                    ))}
                  </Bar>
                </BarChart>
              </ResponsiveContainer>
            </div>

            <div className="bg-white rounded-2xl p-6 shadow-sm border border-navy-900/5">

              <p className="text-xs font-semibold text-navy-700 uppercase tracking-widest mb-4">
                Msimu na Matukio
              </p>

              <div className="space-y-2">
                {forecast.forecast.map((f, i) => (
                  <div
                    key={i}
                    className="flex items-center justify-between py-2 border-b border-paper last:border-0"
                  >
                    <div className="flex items-center gap-3">

                      <div
                        className={`w-2 h-2 rounded-full ${
                          f.predicted_net_flow >= 0
                            ? 'bg-kitu-green'
                            : 'bg-kitu-red'
                        }`}
                      />

                      <p className="text-sm text-navy-900">
                        {new Date(
                          f.date
                        ).toLocaleDateString(
                          'en-TZ',
                          {
                            weekday: 'short',
                            day: 'numeric',
                            month: 'short'
                          }
                        )}
                      </p>
                    </div>

                    <div className="flex items-center gap-6">
                      <span className="text-xs text-navy-700">
                        Msimu: ×{f.seasonal_multiplier}
                      </span>

                      <p
                        className={`text-sm font-semibold ${
                          f.predicted_net_flow >= 0
                            ? 'text-kitu-green'
                            : 'text-kitu-red'
                        }`}
                      >
                        {f.predicted_net_flow >= 0 ? '+' : ''}
                        TZS {f.predicted_net_flow.toLocaleString()}
                      </p>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </>
        )}

        {/* Network Tab */}
        {activeTab === 'network' && network && (
          <>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
              {[
                {
                  label: 'Washirika Wote',
                  value: network.unique_counterparties,
                  icon: <Users size={18} />
                },
                {
                  label: 'Washirika wa Kuamini',
                  value: network.loyal_counterparties,
                  icon: <Users size={18} />
                },
                {
                  label: 'Afya ya Mtandao',
                  value: `${network.network_health_score}%`,
                  icon: <Globe size={18} />
                },
                {
                  label: 'Viungo vya Mtandao',
                  value: network.total_nodes,
                  icon: <Globe size={18} />
                },
              ].map((s, i) => (
                <div
                  key={i}
                  className="bg-white rounded-2xl p-5 shadow-sm border border-navy-900/5"
                >
                  <div className="text-kitu-green mb-2">
                    {s.icon}
                  </div>

                  <p className="font-display text-2xl text-navy-900">
                    {s.value}
                  </p>

                  <p className="text-xs text-navy-700 mt-1">
                    {s.label}
                  </p>
                </div>
              ))}
            </div>

            <div className="bg-white rounded-2xl p-6 shadow-sm border border-navy-900/5">

              <p className="text-xs font-semibold text-navy-700 uppercase tracking-widest mb-4">
                Washirika Wakuu kwa Kiasi
              </p>

              <div className="space-y-3">
                {network.top_counterparties.map((c, i) => {
                  const maxVol =
                    network.top_counterparties[0].total_volume;

                  const pct =
                    (c.total_volume / maxVol) * 100;

                  return (
                    <div key={i} className="space-y-1">

                      <div className="flex justify-between text-sm">
                        <span className="text-navy-900 font-medium">
                          {c.phone}
                        </span>

                        <span className="text-navy-700">
                          TZS {c.total_volume.toLocaleString()}
                        </span>
                      </div>

                      <div className="h-2 bg-paper rounded-full overflow-hidden">
                        <div
                          className="h-full bg-kitu-green rounded-full transition-all duration-700"
                          style={{ width: `${pct}%` }}
                        />
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          </>
        )}

      </main>
    </div>
  );
};

export default DashboardPage;