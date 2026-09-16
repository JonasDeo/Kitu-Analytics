import React, { createContext, useContext, useState, useEffect } from 'react';
import { getBranches } from '../api/business';

interface Branch {
  id: number;
  name: string;
  address?: string;
  is_main: boolean;
  is_active: boolean;
}

interface BranchContextType {
  branches: Branch[];
  activeBranch: Branch | null;
  setActiveBranch: (branch: Branch) => void;
  loading: boolean;
}

const BranchContext = createContext<BranchContextType>({} as BranchContextType);

export const BranchProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [branches, setBranches] = useState<Branch[]>([]);
  const [activeBranch, setActiveBranch] = useState<Branch | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getBranches()
      .then((res) => {
        const data: Branch[] = res.data;
        setBranches(data);
        // Default to main branch
        const main = data.find((b) => b.is_main) || data[0] || null;
        setActiveBranch(main);
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  return (
    <BranchContext.Provider value={{ branches, activeBranch, setActiveBranch, loading }}>
      {children}
    </BranchContext.Provider>
  );
};

export const useBranch = () => useContext(BranchContext);