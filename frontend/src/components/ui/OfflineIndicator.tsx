import React, { useEffect, useState } from 'react';
import { getQueueCount, triggerBackgroundSync } from '../../utils/offlineQueue';

const OfflineIndicator: React.FC = () => {
  const [isOnline, setIsOnline] = useState(navigator.onLine);
  const [queueCount, setQueueCount] = useState(0);

  useEffect(() => {
    const handleOnline = () => {
      setIsOnline(true);
      triggerBackgroundSync();
      // Refresh queue count
      getQueueCount().then(setQueueCount);
    };
    const handleOffline = () => setIsOnline(false);

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    // Check queue count on mount
    getQueueCount().then(setQueueCount);

    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, []);

  if (isOnline && queueCount === 0) return null;

  return (
    <div className={`fixed bottom-4 left-4 right-4 md:left-auto md:right-4 md:w-80 rounded-xl px-4 py-3 flex items-center gap-3 shadow-lg z-50 ${isOnline ? 'bg-kitu-green' : 'bg-kitu-amber'}`}>
      <span className="text-lg">{isOnline ? '🔄' : '📡'}</span>
      <div>
        <p className="text-white font-semibold text-sm">
          {isOnline ? 'Unaungana tena...' : 'Huna mtandao'}
        </p>
        <p className="text-white/80 text-xs">
          {isOnline
            ? `Inatuma ${queueCount} miamala iliyohifadhiwa...`
            : 'Data ya zamani inaonyeshwa. Miamala mipya itahifadhiwa.'}
        </p>
      </div>
    </div>
  );
};

export default OfflineIndicator;