import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import client from '../api/client';
import { Shield, Check, X } from 'lucide-react';

interface ConsentState {
  data_processing: boolean;
  lender_access: boolean;
  model_training: boolean;
}

interface ExistingConsent {
  consent_type: string;
  granted: boolean;
}

const ConsentPage: React.FC = () => {
  const { t } = useTranslation();
  const { user } = useAuth();
  const navigate = useNavigate();

  const [consents, setConsents] = useState<ConsentState>({
    data_processing: true,   // pre-checked — required
    lender_access: false,
    model_training: false,
  });

  const [existing, setExisting] = useState<ExistingConsent[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    client.get('/consent').then(res => {
      const existingConsents = res.data;
      setExisting(existingConsents);

      // Pre-fill from existing consents
      const granted = existingConsents
        .filter((c: ExistingConsent) => c.granted)
        .map((c: ExistingConsent) => c.consent_type);

      setConsents({
        data_processing: granted.includes('data_processing') || true,
        lender_access: granted.includes('lender_access'),
        model_training: granted.includes('model_training'),
      });
    }).finally(() => setLoading(false));
  }, []);

  const handleSave = async () => {
    setSaving(true);
    try {
      const toGrant = Object.entries(consents)
        .filter(([_, v]) => v)
        .map(([k]) => k);

      // Grant each consented type
      for (const type of toGrant) {
        const alreadyGranted = existing.some(
          e => e.consent_type === type && e.granted
        );
        if (!alreadyGranted) {
          await client.post('/consent/grant', { consent_type: type });
        }
      }

      setSaved(true);
      setTimeout(() => navigate('/dashboard'), 1500);
    } finally {
      setSaving(false);
    }
  };

  const handleWithdraw = async (type: string) => {
    try {
      await client.post('/consent/withdraw', { consent_type: type });
      setExisting(prev => prev.filter(e => e.consent_type !== type));
      setConsents(prev => ({ ...prev, [type]: false }));
    } catch {}
  };

  if (loading) return (
    <div className="min-h-screen bg-navy-900 flex items-center justify-center">
      <div className="font-display text-2xl text-paper animate-pulse">
        {t('common.loading')}
      </div>
    </div>
  );

  const consentItems = [
    {
      key: 'data_processing' as const,
      label: t('consent.data_processing'),
      desc: t('consent.data_processing_desc'),
      required: true,
    },
    {
      key: 'lender_access' as const,
      label: t('consent.lender_access'),
      desc: t('consent.lender_access_desc'),
      required: false,
    },
    {
      key: 'model_training' as const,
      label: t('consent.model_training'),
      desc: t('consent.model_training_desc'),
      required: false,
    },
  ];

  return (
    <div className="min-h-screen bg-navy-900 flex items-center justify-center px-4 py-12">
      <div className="w-full max-w-lg">

        {/* Header */}
        <div className="text-center mb-8">
          <div className="w-16 h-16 bg-kitu-green/20 rounded-full flex items-center justify-center mx-auto mb-4">
            <Shield size={28} className="text-kitu-green" />
          </div>
          <h1 className="font-display text-3xl text-paper mb-2">
            {t('consent.title')}
          </h1>
          <p className="text-paper/60 text-sm">
            {t('consent.subtitle')}
          </p>
        </div>

        {/* Consent items */}
        <div className="space-y-3 mb-6">
          {consentItems.map((item) => {
            const isGranted = existing.some(
              e => e.consent_type === item.key && e.granted
            );

            return (
              <div key={item.key}
                className={`bg-navy-800 rounded-2xl p-5 border transition-colors ${consents[item.key] ? 'border-kitu-green/40' : 'border-navy-700'}`}>
                <div className="flex items-start justify-between gap-4">
                  <div className="flex-1">
                    <div className="flex items-center gap-2 mb-1">
                      <p className="text-paper font-semibold text-sm">{item.label}</p>
                      <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${item.required ? 'bg-kitu-green/20 text-kitu-green' : 'bg-paper/10 text-paper/50'}`}>
                        {item.required ? t('consent.required') : t('consent.optional')}
                      </span>
                    </div>
                    <p className="text-paper/60 text-xs leading-relaxed">{item.desc}</p>
                    {isGranted && (
                      <button
                        onClick={() => handleWithdraw(item.key)}
                        className="mt-2 text-xs text-red-400 hover:text-red-300 transition-colors">
                        {t('consent.withdraw')}
                      </button>
                    )}
                  </div>

                  {/* Toggle */}
                  <button
                    onClick={() => !item.required && setConsents(prev => ({ ...prev, [item.key]: !prev[item.key] }))}
                    disabled={item.required}
                    className={`w-12 h-6 rounded-full transition-colors flex-shrink-0 relative ${consents[item.key] ? 'bg-kitu-green' : 'bg-navy-700'} ${item.required ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer'}`}>
                    <div className={`w-5 h-5 bg-white rounded-full absolute top-0.5 transition-transform ${consents[item.key] ? 'translate-x-6' : 'translate-x-0.5'}`} />
                  </button>
                </div>
              </div>
            );
          })}
        </div>

        {/* PDPA note */}
        <div className="bg-navy-800/50 rounded-xl p-4 mb-6 border border-navy-700">
          <p className="text-paper/50 text-xs leading-relaxed">
            🔒 {t('consent.pdpa_note')}
          </p>
        </div>

        {/* Actions */}
        {saved ? (
          <div className="flex items-center justify-center gap-2 bg-kitu-green/20 border border-kitu-green/40 rounded-2xl p-4">
            <Check size={18} className="text-kitu-green" />
            <p className="text-kitu-green font-semibold text-sm">{t('common.success')}</p>
          </div>
        ) : (
          <div className="space-y-3">
            <button
              onClick={handleSave}
              disabled={saving || !consents.data_processing}
              className="w-full bg-kitu-green text-white font-semibold py-3 rounded-xl hover:bg-green-700 transition-colors disabled:opacity-50 text-sm">
              {saving ? t('consent.saving') : t('consent.save')}
            </button>
            <button
              onClick={() => navigate('/dashboard')}
              className="w-full text-paper/40 hover:text-paper/60 text-sm py-2 transition-colors">
              {t('consent.skip')}
            </button>
          </div>
        )}
      </div>
    </div>
  );
};

export default ConsentPage;