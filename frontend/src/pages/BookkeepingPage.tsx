import React, { useEffect, useState } from 'react';
import {
  getBookkeepingDailyReport, getBookkeepingSummary,
  getBookkeepingProducts, getBookkeepingDebtors
} from '../api/business';
import {
  BarChart, Bar, XAxis, YAxis, Tooltip,
  ResponsiveContainer, Cell
} from 'recharts';
import { ShoppingBag, TrendingUp, AlertTriangle, Users } from 'lucide-react';

interface DailyReport {
  date: string;
  sales_count: number;
  total_revenue: number;
  total_collected: number;
  total_owed: number;
  total_expenses: number;
  net_profit: number;
}

interface Summary {
  period_days: number;
  sales: {
    total_sales: number;
    total_revenue: string;
    total_collected: string;
    total_outstanding: string;
    partial_sales: number;
  };
  expenses: { total_expenses: string };
  net_profit: number;
  top_products: Array<{ name: string; qty_sold: number; revenue: number }>;
}

interface ProductReport {
  total_products: number;
  low_stock_count: number;
  low_stock_items: Array<{ name: string; total_stock: number }>;
  products: Array<{
    name: string;
    sale_price: string;
    cost_price: string;
    profit_margin: number;
    total_stock: number;
    is_low_stock: boolean;
  }>;
}

interface DebtorReport {
  customers_who_owe_us: { count: number; total: number; list: Array<{ name: string; total_owed: number; phone?: string }> };
  suppliers_we_owe: { count: number; total: number; list: Array<{ name: string; total_owed: number }> };
}

const BookkeepingPage: React.FC = () => {
  const [daily, setDaily] = useState<DailyReport | null>(null);
  const [summary, setSummary] = useState<Summary | null>(null);
  const [products, setProducts] = useState<ProductReport | null>(null);
  const [debtors, setDebtors] = useState<DebtorReport | null>(null);
  const [loading, setLoading] = useState(true);
  const [activeSection, setActiveSection] = useState<'overview' | 'stock' | 'debtors'>('overview');

  useEffect(() => {
    Promise.all([
      getBookkeepingDailyReport(),
      getBookkeepingSummary(30),
      getBookkeepingProducts(),
      getBookkeepingDebtors(),
    ]).then(([d, s, p, db]) => {
      setDaily(d.data);
      setSummary(s.data);
      setProducts(p.data);
      setDebtors(db.data);
    }).finally(() => setLoading(false));
  }, []);

  if (loading) return (
    <div className="flex items-center justify-center h-64">
      <div className="font-display text-2xl text-navy-900 animate-pulse">Inapakia...</div>
    </div>
  );

  const topProductsChart = summary?.top_products?.map(p => ({
    name: p.name.length > 12 ? p.name.slice(0, 12) + '…' : p.name,
    revenue: p.revenue,
  })) || [];

  return (
    <div className="space-y-6">

      {/* Hero number — today's revenue */}
      <div className="bg-navy-900 rounded-2xl p-8">
        <p className="text-paper/50 text-sm mb-2">Mapato ya Leo</p>
        <div className="flex items-end gap-4 flex-wrap">
          <span className="font-display text-6xl text-paper">
            TZS {(daily?.total_revenue ?? 0).toLocaleString()}
          </span>
          <div className="mb-2 space-y-1">
            <p className="text-kitu-green text-sm">
              ↑ Imekusanywa: TZS {(daily?.total_collected ?? 0).toLocaleString()}
            </p>
            <p className="text-kitu-amber text-sm">
              ⏳ Inadaiwa: TZS {(daily?.total_owed ?? 0).toLocaleString()}
            </p>
          </div>
        </div>
        <div className="mt-4 flex gap-6 flex-wrap">
          {[
            { label: 'Mauzo', value: daily?.sales_count ?? 0, unit: '' },
            { label: 'Faida Halisi', value: `TZS ${(daily?.net_profit ?? 0).toLocaleString()}`, unit: '' },
            { label: 'Gharama', value: `TZS ${(daily?.total_expenses ?? 0).toLocaleString()}`, unit: '' },
          ].map((s, i) => (
            <div key={i}>
              <p className="text-paper/40 text-xs">{s.label}</p>
              <p className="text-paper font-semibold text-lg">{s.value}{s.unit}</p>
            </div>
          ))}
        </div>
      </div>

      {/* Section tabs */}
      <div className="flex gap-1 bg-white rounded-xl p-1 shadow-sm border border-navy-900/5 w-fit">
        {([
          { key: 'overview', label: 'Muhtasari wa Mwezi' },
          { key: 'stock', label: 'Bidhaa & Stoki' },
          { key: 'debtors', label: 'Wadai & Madeni' },
        ] as const).map((tab) => (
          <button key={tab.key} onClick={() => setActiveSection(tab.key)}
            className={`px-4 py-2 rounded-lg text-xs font-semibold transition-colors ${activeSection === tab.key ? 'bg-navy-900 text-paper' : 'text-navy-700 hover:text-navy-900'}`}>
            {tab.label}
          </button>
        ))}
      </div>

      {/* Overview section */}
      {activeSection === 'overview' && summary && (
        <>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            {[
              { icon: <ShoppingBag size={18} />, label: 'Mauzo (Siku 30)', value: summary.sales?.total_sales ?? 0, color: 'text-navy-900' },
              { icon: <TrendingUp size={18} />, label: 'Mapato Yote', value: `TZS ${Number(summary.sales?.total_revenue ?? 0).toLocaleString()}`, color: 'text-kitu-green' },
              { icon: <AlertTriangle size={18} />, label: 'Bado Inadaiwa', value: `TZS ${Number(summary.sales?.total_outstanding ?? 0).toLocaleString()}`, color: 'text-kitu-amber' },
              { icon: <Users size={18} />, label: 'Mauzo ya Awamu', value: summary.sales?.partial_sales ?? 0, color: 'text-navy-700' },
            ].map((s, i) => (
              <div key={i} className="bg-white rounded-2xl p-5 shadow-sm border border-navy-900/5">
                <div className="text-navy-700 mb-2">{s.icon}</div>
                <p className={`font-display text-xl ${s.color}`}>{s.value}</p>
                <p className="text-xs text-navy-700 mt-1">{s.label}</p>
              </div>
            ))}
          </div>

          {topProductsChart.length > 0 && (
            <div className="bg-white rounded-2xl p-6 shadow-sm border border-navy-900/5">
              <p className="text-sm font-semibold text-navy-800 mb-6">Bidhaa Zinazoongoza kwa Mapato</p>
              <ResponsiveContainer width="100%" height={200}>
                <BarChart data={topProductsChart} layout="vertical">
                  <XAxis type="number" tick={{ fontSize: 10 }} tickFormatter={v => `${(v/1000).toFixed(0)}K`} axisLine={false} tickLine={false} />
                  <YAxis type="category" dataKey="name" tick={{ fontSize: 11, fill: '#243B55' }} width={90} axisLine={false} tickLine={false} />
                  <Tooltip formatter={(v) => [`TZS ${Number(v).toLocaleString()}`, 'Mapato']} contentStyle={{ borderRadius: '8px', fontSize: '12px' }} />
                  <Bar dataKey="revenue" radius={[0, 6, 6, 0]}>
                    {topProductsChart.map((_, i) => (
                      <Cell key={i} fill={i === 0 ? '#00A651' : '#243B55'} fillOpacity={1 - i * 0.15} />
                    ))}
                  </Bar>
                </BarChart>
              </ResponsiveContainer>
            </div>
          )}
        </>
      )}

      {/* Stock section */}
      {activeSection === 'stock' && products && (
        <>
          {products.low_stock_count > 0 && (
            <div className="bg-amber-50 border border-kitu-amber rounded-2xl p-4 flex items-center gap-3">
              <AlertTriangle size={20} className="text-kitu-amber flex-shrink-0" />
              <p className="text-sm text-navy-900">
                <span className="font-semibold">{products.low_stock_count} bidhaa</span> zina stoki ndogo.
                {products.low_stock_items.map(i => ` ${i.name}`).join(',')}
              </p>
            </div>
          )}

          <div className="bg-white rounded-2xl shadow-sm border border-navy-900/5 overflow-hidden">
            <div className="px-6 py-4 border-b border-paper">
              <p className="text-sm font-semibold text-navy-800">Bidhaa Zote ({products.total_products})</p>
            </div>
            <div className="divide-y divide-paper">
              {products.products.map((p, i) => (
                <div key={i} className="px-6 py-4 flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <div className={`w-2 h-2 rounded-full ${p.is_low_stock ? 'bg-kitu-red' : 'bg-kitu-green'}`} />
                    <div>
                      <p className="text-sm font-medium text-navy-900">{p.name}</p>
                      <p className="text-xs text-navy-700">
                        Bei: TZS {Number(p.sale_price).toLocaleString()} · Faida: {p.profit_margin}%
                      </p>
                    </div>
                  </div>
                  <div className="text-right">
                    <p className={`text-sm font-semibold ${p.is_low_stock ? 'text-kitu-red' : 'text-navy-900'}`}>
                      {p.total_stock} zilizobaki
                    </p>
                    {p.is_low_stock && <p className="text-xs text-kitu-red">Stoki ndogo</p>}
                  </div>
                </div>
              ))}
            </div>
          </div>
        </>
      )}

      {/* Debtors section */}
      {activeSection === 'debtors' && debtors && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">

          {/* Customers who owe us */}
          <div className="bg-white rounded-2xl shadow-sm border border-navy-900/5 overflow-hidden">
            <div className="px-6 py-4 border-b border-paper flex justify-between items-center">
              <p className="text-sm font-semibold text-navy-800">Wanaodaiwa Kwetu</p>
              <span className="text-xs font-semibold text-kitu-red bg-red-50 px-2 py-1 rounded-full">
                TZS {debtors.customers_who_owe_us.total.toLocaleString()}
              </span>
            </div>
            {debtors.customers_who_owe_us.list.length === 0 ? (
              <div className="px-6 py-8 text-center">
                <p className="text-sm text-navy-700">Hakuna madeni ya wateja 🎉</p>
              </div>
            ) : (
              <div className="divide-y divide-paper">
                {debtors.customers_who_owe_us.list.map((c, i) => (
                  <div key={i} className="px-6 py-3 flex justify-between items-center">
                    <div>
                      <p className="text-sm font-medium text-navy-900">{c.name}</p>
                      {c.phone && <p className="text-xs text-navy-700">{c.phone}</p>}
                    </div>
                    <p className="text-sm font-semibold text-kitu-red">
                      TZS {c.total_owed.toLocaleString()}
                    </p>
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Suppliers we owe */}
          <div className="bg-white rounded-2xl shadow-sm border border-navy-900/5 overflow-hidden">
            <div className="px-6 py-4 border-b border-paper flex justify-between items-center">
              <p className="text-sm font-semibold text-navy-800">Tunaowalipa (Wasambazaji)</p>
              <span className="text-xs font-semibold text-kitu-amber bg-amber-50 px-2 py-1 rounded-full">
                TZS {debtors.suppliers_we_owe.total.toLocaleString()}
              </span>
            </div>
            {debtors.suppliers_we_owe.list.length === 0 ? (
              <div className="px-6 py-8 text-center">
                <p className="text-sm text-navy-700">Hakuna madeni kwa wasambazaji 🎉</p>
              </div>
            ) : (
              <div className="divide-y divide-paper">
                {debtors.suppliers_we_owe.list.map((s, i) => (
                  <div key={i} className="px-6 py-3 flex justify-between items-center">
                    <p className="text-sm font-medium text-navy-900">{s.name}</p>
                    <p className="text-sm font-semibold text-kitu-amber">
                      TZS {s.total_owed.toLocaleString()}
                    </p>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

export default BookkeepingPage;