import React, { useState, useEffect } from 'react';
import { getLenderPreApprovals, getLenderPortfolio, getLenderRevenue } from '../api/business';
import { CheckCircle, DollarSign, Users, TrendingUp } from 'lucide-react';

interface Lead {
  business_name: string;
  location: string;
  credit_score: number;
  grade: string;
  repayment_likelihood: number;
  recommended_max_loan_tzs: number;
  transaction_count: number;
  total_incoming_tzs: number;
  phone: string;
}

interface Portfolio {
  lender: string;
  total_queries: number;
  total_billed_tzs: number;
}

const DEMO_API_KEY = 'lender_test_CRDB2024XYZ';

const gradeColor = (grade: string) => ({
  A: 'bg-green-100 text-green-800',
  B: 'bg-green-50 text-green-700',
  C: 'bg-amber-50 text-amber-700',
  D: 'bg-red-50 text-red-700',
  F: 'bg-red-100 text-red-800',
}[grade] || 'bg-gray-100 text-gray-700');

const LenderPortalPage: React.FC = () => {
  const [leads, setLeads] = useState<Lead[]>([]);
  const [portfolio, setPortfolio] = useState<Portfolio | null>(null);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState<'leads' | 'portfolio' | 'billing'>('leads');
  const [minScore, setMinScore] = useState(500);
  const [revenueEvents, setRevenueEvents] = useState<any[]>([]);

  const loadData = async () => {
    setLoading(true);
    try {
      const [leadsRes, portfolioRes, revenueRes] = await Promise.all([
        getLenderPreApprovals(DEMO_API_KEY, minScore),
        getLenderPortfolio(DEMO_API_KEY),
        getLenderRevenue(DEMO_API_KEY),
      ]);
      setLeads(leadsRes.data.leads || []);
      setPortfolio(portfolioRes.data);
      setRevenueEvents(revenueRes.data.data || []);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { loadData(); }, [minScore]);

  if (loading) return (
    <div className="flex items-center justify-center h-64">
      <div className="font-display text-2xl text-navy-900 animate-pulse">Loading portal...</div>
    </div>
  );

  return (
    <div className="space-y-6">

      {/* Hero — leads available */}
      <div className="bg-navy-800 rounded-2xl p-8">
        <p className="text-paper/50 text-sm mb-2">Pre-Approved Leads Available</p>
        <div className="flex items-end gap-6 flex-wrap">
          <span className="font-display text-6xl text-paper">{leads.length}</span>
          <div className="mb-2 space-y-1">
            <p className="text-blue-400 text-sm">
              Min score: {minScore} · {portfolio?.lender ?? 'CRDB Microfinance'}
            </p>
            <p className="text-paper/50 text-sm">
              TZS {portfolio?.total_billed_tzs?.toLocaleString() ?? 0} billed to date
            </p>
          </div>
        </div>

        {/* Score filter */}
        <div className="mt-6 flex items-center gap-4">
          <p className="text-paper/50 text-xs">Filter by min score:</p>
          {[400, 500, 600, 700].map(s => (
            <button key={s} onClick={() => setMinScore(s)}
              className={`px-3 py-1 rounded-lg text-xs font-semibold transition-colors ${minScore === s ? 'bg-blue-500 text-white' : 'bg-navy-900 text-paper/60 hover:text-paper'}`}>
              {s}+
            </button>
          ))}
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 bg-white rounded-xl p-1 shadow-sm border border-navy-900/5 w-fit">
        {([
          { key: 'leads', label: 'Pre-Approved Leads' },
          { key: 'portfolio', label: 'Portfolio' },
          { key: 'billing', label: 'Billing' },
        ] as const).map(tab => (
          <button key={tab.key} onClick={() => setActiveTab(tab.key)}
            className={`px-4 py-2 rounded-lg text-xs font-semibold transition-colors ${activeTab === tab.key ? 'bg-navy-900 text-paper' : 'text-navy-700 hover:text-navy-900'}`}>
            {tab.label}
          </button>
        ))}
      </div>

      {/* Leads tab */}
      {activeTab === 'leads' && (
        <>
          {leads.length === 0 ? (
            <div className="bg-white rounded-2xl p-12 text-center shadow-sm border border-navy-900/5">
              <p className="text-navy-700 text-sm">No leads meet the minimum score of {minScore}.</p>
              <button onClick={() => setMinScore(400)} className="mt-3 text-xs text-blue-500 hover:underline">
                Lower threshold to 400
              </button>
            </div>
          ) : (
            <div className="space-y-3">
              {leads.map((lead, i) => (
                <div key={i} className="bg-white rounded-2xl p-5 shadow-sm border border-navy-900/5">
                  <div className="flex items-start justify-between flex-wrap gap-4">
                    <div className="flex items-center gap-4">
                      <div className="w-10 h-10 rounded-full bg-navy-900 flex items-center justify-center text-paper font-display text-lg">
                        {lead.business_name[0]}
                      </div>
                      <div>
                        <p className="font-semibold text-navy-900">{lead.business_name}</p>
                        <p className="text-xs text-navy-700">{lead.location} · {lead.phone}</p>
                      </div>
                    </div>
                    <div className="flex items-center gap-3">
                      <span className={`px-3 py-1 rounded-full text-xs font-bold ${gradeColor(lead.grade)}`}>
                        Grade {lead.grade}
                      </span>
                      <span className="font-display text-2xl text-navy-900">{lead.credit_score}</span>
                    </div>
                  </div>
                  <div className="mt-4 grid grid-cols-3 gap-4">
                    <div>
                      <p className="text-xs text-navy-700">Max Recommended Loan</p>
                      <p className="text-sm font-semibold text-kitu-green">TZS {lead.recommended_max_loan_tzs.toLocaleString()}</p>
                    </div>
                    <div>
                      <p className="text-xs text-navy-700">Repayment Likelihood</p>
                      <p className="text-sm font-semibold text-navy-900">{lead.repayment_likelihood.toFixed(1)}%</p>
                    </div>
                    <div>
                      <p className="text-xs text-navy-700">Total Incoming (90d)</p>
                      <p className="text-sm font-semibold text-navy-900">TZS {lead.total_incoming_tzs.toLocaleString()}</p>
                    </div>
                  </div>
                  <div className="mt-3 flex justify-end">
                    <span className="text-xs text-navy-700 bg-paper px-3 py-1 rounded-full">
                      Query billed: TZS 2,500
                    </span>
                  </div>
                </div>
              ))}
            </div>
          )}
        </>
      )}

      {/* Portfolio tab */}
      {activeTab === 'portfolio' && portfolio && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          {[
            { icon: <Users size={18} />, label: 'Total Queries', value: portfolio.total_queries },
            { icon: <DollarSign size={18} />, label: 'Total Billed (TZS)', value: portfolio.total_billed_tzs?.toLocaleString() },
            { icon: <CheckCircle size={18} />, label: 'Active Leads', value: leads.length },
            { icon: <TrendingUp size={18} />, label: 'Avg Score (Leads)', value: leads.length ? Math.round(leads.reduce((s, l) => s + l.credit_score, 0) / leads.length) : 'N/A' },
          ].map((s, i) => (
            <div key={i} className="bg-white rounded-2xl p-5 shadow-sm border border-navy-900/5">
              <div className="text-blue-500 mb-2">{s.icon}</div>
              <p className="font-display text-2xl text-navy-900">{s.value}</p>
              <p className="text-xs text-navy-700 mt-1">{s.label}</p>
            </div>
          ))}
        </div>
      )}

      {/* Billing tab */}
      {activeTab === 'billing' && (
        <div className="bg-white rounded-2xl shadow-sm border border-navy-900/5 overflow-hidden">
          <div className="px-6 py-4 border-b border-paper">
            <p className="text-sm font-semibold text-navy-800">Billing Events</p>
          </div>
          {revenueEvents.length === 0 ? (
            <div className="px-6 py-8 text-center">
              <p className="text-sm text-navy-700">No billing events yet.</p>
            </div>
          ) : (
            <div className="divide-y divide-paper">
              {revenueEvents.map((e, i) => (
                <div key={i} className="px-6 py-4 flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium text-navy-900">{e.event_type}</p>
                    <p className="text-xs text-navy-700">{e.reference} · {new Date(e.created_at).toLocaleDateString()}</p>
                  </div>
                  <div className="text-right">
                    <p className="text-sm font-semibold text-navy-900">TZS {Number(e.amount_tzs).toLocaleString()}</p>
                    <span className={`text-xs px-2 py-0.5 rounded-full ${e.status === 'billed' ? 'bg-amber-50 text-amber-700' : 'bg-green-50 text-green-700'}`}>
                      {e.status}
                    </span>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default LenderPortalPage;