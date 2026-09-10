import React from 'react';
import { useTranslation } from 'react-i18next';

const LanguageToggle: React.FC = () => {
  const { i18n } = useTranslation();
  const isSw = i18n.language === 'sw';

  const toggle = () => {
    i18n.changeLanguage(isSw ? 'en' : 'sw');
  };

  return (
    <button
      onClick={toggle}
      className="flex items-center gap-1.5 text-xs font-semibold text-paper/60 hover:text-paper transition-colors px-3 py-1.5 rounded-lg border border-paper/10 hover:border-paper/20"
    >
      <span className="text-base">{isSw ? '🇹🇿' : '🇬🇧'}</span>
      <span>{isSw ? 'SW' : 'EN'}</span>
    </button>
  );
};

export default LanguageToggle;