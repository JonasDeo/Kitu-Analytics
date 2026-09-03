import React, { useEffect, useState } from 'react';
import client from '../api/client';
import { Activity, Shield, TrendingUp, Zap } from 'lucide-react';

interface FraudData {
  risk_score: number;
  risk_level: string;
  flags: Array<{ type: string; severity: string; detail: string; code: string }>;
  total_transactions_analysed: number;
}

interface ModelStatus {
  forecast_models_cached: number;
  repayment_model_trained: boolean;
  repayment_outcomes_collected: number;
  repayment_outcomes_needed_to_train: number;
  model_version: string;
}

const severityColor = (s: string) => ({
  high: 'bg-red-50 text-red-700 border-red-200',
  medium: 'bg-amber-50 text-amber-700 border-amber-200',
  low: 'bg-blue-50 text-blue-700 border-blue-200',
}[s] || 'bg-gray-50 text-gray-700 border-gray-200');

const AdminPage: React.FC = () => {
  const [fraud, setFraud] = useState<FraudData | null>(null);
  const [modelStatus, setModelStatus] = useState<ModelStatus | null>(null);
  const [botCompliance, setBotCompliance] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState<'fraud' | 'models' | 'compliance'>('fraud');

  useEffect(() => {
    Promise.all([
      client.get('/businesses/1/fraud'),
      client.get('/admin/model-status').catch(() => ({ data: null })),
      client.get('/businesses/1/bot-compliance'),
    ]).then(([f, m, b]) => {
      setFraud(f.data);
      setModelStatus(m.data);
      setBotCompliance(b.data);
    }).finally(() => setLoading(false));
  }, []);

  const trainModel = async () => {
    try {
      const res = await client.post('/admin/train-model');
      alert(res.data.message);
    } catch {
      alert('Training failed.');
    }
  };

  if (loading) return (
    <div className="flex items-center justify-center h-64">
      <div className="font-display text-2xl text-navy-900 animate-pulse">Loading admin...</div>
    </div>
  );

  return (
    <div className="space-y-6">

      {/* Hero — platform health */}
      <div className="bg-navy-900 rounded-2xl p-8">
        <p className="text-paper/50 text-sm mb-2">Platform Health</p>
        <div className="flex items-end gap-6 flex-wrap">
          <div>
            <span className={`font-display text-5xl ${fraud?.risk_level === 'low' ? 'text-kitu-green' : fraud?.risk_level === 'medium' ? 'text-kitu-amber' : 'text-kitu-red'}`}>
              {fraud?.risk_level?.toUpperCase() ?? 'UNKNOWN'}
            </span>
            <p className="text-paper/50 text-sm mt-1">Fraud Risk — Business #1</p>
          </div>
          <div className="mb-1 space-y-1">
            <p className="text-paper/70 text-sm">Risk Score: {fraud?.risk_score ?? 0}/100</p>
            <p className="text-paper/50 text-sm">{fraud?.total_transactions_analysed ?? 0} transactions analysed</p>
          </div>
        </div>
        <div className="mt-4 flex gap-6">
          <div>
            <p className="text-paper/40 text-xs">Model Version</p>
            <p className="text-paper text-sm font-mono">{modelStatus?.model_version ?? 'unknown'}</p>
          </div>
          <div>
            <p className="text-paper/40 text-xs">Repayment Outcomes</p>
            <p className="text-paper text-sm">{modelStatus?.repayment_outcomes_collected ?? 0} collected</p>
          </div>
          <div>
            <p className="text-paper/40 text-xs">Forecast Models Cached</p>
            <p className="text-paper text-sm">{modelStatus?.forecast_models_cached ?? 0}</p>
          </div>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 bg-white rounded-xl p-1 shadow-sm border border-navy-900/5 w-fit">
        {([
          { key: 'fraud', label: 'Fraud Detection' },
          { key: 'models', label: 'ML Models' },
          { key: 'compliance', label: 'BoT Compliance' },
        ] as const).map(tab => (
          <button key={tab.key} onClick={() => setActiveTab(tab.key)}
            className={`px-4 py-2 rounded-lg text-xs font-semibold transition-colors ${activeTab === tab.key ? 'bg-navy-900 text-paper' : 'text-navy-700 hover:text-navy-900'}`}>
            {tab.label}
          </button>
        ))}
      </div>

      {/* Fraud tab */}
      {activeTab === 'fraud' && (
        <>
          {fraud?.flags.length === 0 ? (
            <div className="bg-white rounded-2xl p-8 text-center shadow-sm border border-navy-900/5">
              <Shield size={32} className="text-kitu-green mx-auto mb-3" />
              <p className="text-navy-900 font-semibold">No fraud flags detected</p>
              <p className="text-navy-700 text-sm mt-1">All transaction patterns look normal.</p>
            </div>
          ) : (
            <div className="space-y-3">
              {fraud?.flags.map((flag, i) => (
                <div key={i} className={`rounded-2xl p-5 border ${severityColor(flag.severity)}`}>
                  <div className="flex items-center justify-between mb-2">
                    <span className="text-xs font-bold uppercase tracking-wider">{flag.code}</span>
                    <span className={`text-xs px-2 py-0.5 rounded-full font-semibold ${severityColor(flag.severity)}`}>
                      {flag.severity}
                    </span>
                  </div>
                  <p className="text-sm font-medium">{flag.type.replace(/_/g, ' ')}</p>
                  <p className="text-xs mt-1 opacity-80">{flag.detail}</p>
                </div>
              ))}
            </div>
          )}
        </>
      )}

      {/* Models tab */}
      {activeTab === 'models' && modelStatus && (
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            {[
              { icon: <Activity size={18} />, label: 'Forecast Models Cached', value: modelStatus.forecast_models_cached, color: 'text-kitu-green' },
              { icon: <TrendingUp size={18} />, label: 'Repayment Model Trained', value: modelStatus.repayment_model_trained ? 'Yes' : 'No', color: modelStatus.repayment_model_trained ? 'text-kitu-green' : 'text-kitu-amber' },
              { icon: <Zap size={18} />, label: 'Outcomes Collected', value: modelStatus.repayment_outcomes_collected, color: 'text-navy-900' },
              { icon: <Shield size={18} />, label: 'Outcomes Needed', value: modelStatus.repayment_outcomes_needed_to_train, color: 'text-navy-700' },
            ].map((s, i) => (
              <div key={i} className="bg-white rounded-2xl p-5 shadow-sm border border-navy-900/5">
                <div className="text-navy-700 mb-2">{s.icon}</div>
                <p className={`font-display text-2xl ${s.color}`}>{s.value}</p>
                <p className="text-xs text-navy-700 mt-1">{s.label}</p>
              </div>
            ))}
          </div>

          <div className="bg-white rounded-2xl p-6 shadow-sm border border-navy-900/5">
            <p className="text-sm font-semibold text-navy-800 mb-3">Repayment Model Training</p>
            <div className="w-full bg-paper rounded-full h-3 mb-3">
              <div
                className="bg-kitu-green h-3 rounded-full transition-all duration-700"
                style={{ width: `${Math.min((modelStatus.repayment_outcomes_collected / 10) * 100, 100)}%` }}
              />
            </div>
            <p className="text-xs text-navy-700 mb-4">
              {modelStatus.repayment_outcomes_collected}/10 outcomes collected.
              {modelStatus.repayment_outcomes_needed_to_train > 0
                ? ` Need ${modelStatus.repayment_outcomes_needed_to_train} more to train.`
                : ' Ready to train!'}
            </p>
            <button onClick={trainModel}
              disabled={modelStatus.repayment_outcomes_needed_to_train > 0}
              className="bg-navy-900 text-paper text-xs font-semibold px-5 py-2.5 rounded-lg hover:bg-navy-800 transition-colors disabled:opacity-40">
              {modelStatus.repayment_outcomes_needed_to_train > 0 ? 'Collecting data...' : 'Train Model Now'}
            </button>
          </div>
        </div>
      )}

      {/* Compliance tab */}
      {activeTab === 'compliance' && botCompliance && (
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            {[
              { label: 'Report Type', value: botCompliance.report_type },
              { label: 'Data Residency', value: botCompliance.data_residency },
              { label: 'Consent Verified', value: botCompliance.consent_verified ? '✓ Yes' : '✗ No' },
              { label: 'Model Version', value: botCompliance.model_version },
            ].map((s, i) => (
              <div key={i} className="bg-white rounded-2xl p-5 shadow-sm border border-navy-900/5">
                <p className="text-xs text-navy-700 mb-1">{s.label}</p>
                <p className="text-sm font-semibold text-navy-900">{s.value}</p>
              </div>
            ))}
          </div>

          <div className="bg-white rounded-2xl p-6 shadow-sm border border-navy-900/5">
            <p className="text-sm font-semibold text-navy-800 mb-4">Regulatory Notes</p>
            <div className="space-y-2">
              {botCompliance.regulatory_notes?.map((note: string, i: number) => (
                <div key={i} className="flex items-start gap-3">
                  <div className="w-1.5 h-1.5 rounded-full bg-kitu-green mt-1.5 flex-shrink-0" />
                  <p className="text-sm text-navy-700">{note}</p>
                </div>
              ))}
            </div>
          </div>

          <div className="bg-white rounded-2xl p-6 shadow-sm border border-navy-900/5">
            <p className="text-sm font-semibold text-navy-800 mb-4">Fairness Indicators</p>
            <div className="space-y-2">
              {Object.entries(botCompliance.fairness_indicators ?? {}).map(([k, v], i) => (
                <div key={i} className="flex items-start gap-3">
                  <div className="w-1.5 h-1.5 rounded-full bg-blue-400 mt-1.5 flex-shrink-0" />
                  <p className="text-xs text-navy-700"><span className="font-medium">{k.replace(/_/g, ' ')}:</span> {String(v)}</p>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default AdminPage;