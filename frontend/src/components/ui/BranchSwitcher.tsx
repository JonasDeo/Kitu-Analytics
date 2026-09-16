import React, { useState } from 'react';
import { useBranch } from '../../context/BranchContext';
import { ChevronDown, MapPin, Check } from 'lucide-react';

const BranchSwitcher: React.FC = () => {
  const { branches, activeBranch, setActiveBranch } = useBranch();
  const [open, setOpen] = useState(false);

  if (branches.length === 0) return null;

  // Don't show switcher if only one branch
  if (branches.length === 1) {
    return (
      <div className="flex items-center gap-2 text-paper/60 text-xs">
        <MapPin size={12} />
        <span>{activeBranch?.name}</span>
      </div>
    );
  }

  return (
    <div className="relative">
      <button
        onClick={() => setOpen(!open)}
        className="flex items-center gap-2 bg-navy-800 hover:bg-navy-700 transition-colors px-3 py-2 rounded-lg text-xs font-semibold text-paper"
      >
        <MapPin size={12} className="text-kitu-green" />
        <span>{activeBranch?.name ?? 'Chagua Tawi'}</span>
        <ChevronDown size={12} className={`transition-transform ${open ? 'rotate-180' : ''}`} />
      </button>

      {open && (
        <>
          {/* Backdrop */}
          <div
            className="fixed inset-0 z-10"
            onClick={() => setOpen(false)}
          />
          {/* Dropdown */}
          <div className="absolute right-0 top-full mt-2 w-56 bg-white rounded-xl shadow-xl border border-navy-900/5 z-20 overflow-hidden">
            <div className="px-4 py-3 border-b border-paper">
              <p className="text-xs font-semibold text-navy-700 uppercase tracking-wider">
                Chagua Tawi
              </p>
            </div>
            <div className="py-1">
              {branches.map((branch) => (
                <button
                  key={branch.id}
                  onClick={() => {
                    setActiveBranch(branch);
                    setOpen(false);
                  }}
                  className="w-full flex items-center justify-between px-4 py-3 hover:bg-paper transition-colors text-left"
                >
                  <div>
                    <p className="text-sm font-medium text-navy-900">{branch.name}</p>
                    {branch.address && (
                      <p className="text-xs text-navy-700 mt-0.5">{branch.address}</p>
                    )}
                    {branch.is_main && (
                      <span className="text-xs text-kitu-green font-medium">Tawi Kuu</span>
                    )}
                  </div>
                  {activeBranch?.id === branch.id && (
                    <Check size={16} className="text-kitu-green flex-shrink-0" />
                  )}
                </button>
              ))}
            </div>
          </div>
        </>
      )}
    </div>
  );
};

export default BranchSwitcher;