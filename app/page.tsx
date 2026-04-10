import {
  TrendingUp,
  TrendingDown,
  DollarSign,
  Activity,
  ArrowUpRight,
  ArrowDownRight,
} from "lucide-react";
import {
  assets,
  transactions,
  getTotalValue,
  getTotalGain,
  getTotalGainPercent,
  getDailyChange,
} from "@/lib/data";
import PortfolioChart from "@/components/charts/PortfolioChart";
import AllocationChart from "@/components/charts/AllocationChart";

function StatCard({
  label,
  value,
  change,
  changeLabel,
  positive,
  icon: Icon,
}: {
  label: string;
  value: string;
  change: string;
  changeLabel: string;
  positive: boolean;
  icon: React.ComponentType<{ size?: number; color?: string }>;
}) {
  return (
    <div
      className="rounded-2xl p-5"
      style={{ background: "var(--surface)", border: "1px solid var(--border)" }}
    >
      <div className="flex items-start justify-between mb-4">
        <p className="text-sm font-medium" style={{ color: "var(--muted)" }}>
          {label}
        </p>
        <div
          className="w-9 h-9 rounded-xl flex items-center justify-center"
          style={{ background: "var(--surface-2)" }}
        >
          <Icon size={17} color="var(--accent)" />
        </div>
      </div>
      <p className="text-2xl font-bold mb-2" style={{ color: "var(--foreground)" }}>
        {value}
      </p>
      <div className="flex items-center gap-1">
        {positive ? (
          <ArrowUpRight size={14} color="var(--green)" />
        ) : (
          <ArrowDownRight size={14} color="var(--red)" />
        )}
        <span
          className="text-xs font-medium"
          style={{ color: positive ? "var(--green)" : "var(--red)" }}
        >
          {change}
        </span>
        <span className="text-xs" style={{ color: "var(--muted)" }}>
          {changeLabel}
        </span>
      </div>
    </div>
  );
}

function TransactionBadge({ type }: { type: string }) {
  const styles: Record<string, { bg: string; color: string; label: string }> = {
    buy: { bg: "#22c55e22", color: "#22c55e", label: "Buy" },
    sell: { bg: "#ef444422", color: "#ef4444", label: "Sell" },
    dividend: { bg: "#6366f122", color: "#6366f1", label: "Dividend" },
    deposit: { bg: "#0ea5e922", color: "#0ea5e9", label: "Deposit" },
    withdrawal: { bg: "#f9731622", color: "#f97316", label: "Withdraw" },
  };
  const s = styles[type] || styles.buy;
  return (
    <span
      className="text-xs font-medium px-2 py-0.5 rounded-full"
      style={{ background: s.bg, color: s.color }}
    >
      {s.label}
    </span>
  );
}

export default function DashboardPage() {
  const totalValue = getTotalValue();
  const totalGain = getTotalGain();
  const totalGainPct = getTotalGainPercent();
  const dailyChange = getDailyChange();
  const dailyChangePct = (dailyChange / (totalValue - dailyChange)) * 100;
  const recentTransactions = transactions.slice(0, 5);
  const topAssets = [...assets]
    .sort((a, b) => b.currentPrice * b.quantity - a.currentPrice * a.quantity)
    .slice(0, 5);

  return (
    <div className="p-6 max-w-7xl mx-auto">
      {/* Header */}
      <div className="mb-8">
        <h1 className="text-2xl font-bold mb-1" style={{ color: "var(--foreground)" }}>
          Overview
        </h1>
        <p className="text-sm" style={{ color: "var(--muted)" }}>
          Wednesday, April 9, 2026
        </p>
      </div>

      {/* Stats grid */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <StatCard
          label="Total Portfolio"
          value={`$${totalValue.toLocaleString("en-US", { maximumFractionDigits: 0 })}`}
          change={`$${Math.abs(dailyChange).toFixed(0)}`}
          changeLabel="today"
          positive={dailyChange >= 0}
          icon={DollarSign}
        />
        <StatCard
          label="Total Gain"
          value={`$${totalGain.toLocaleString("en-US", { maximumFractionDigits: 0 })}`}
          change={`${totalGainPct.toFixed(1)}%`}
          changeLabel="all time"
          positive={totalGain >= 0}
          icon={TrendingUp}
        />
        <StatCard
          label="Day Change"
          value={`${dailyChange >= 0 ? "+" : ""}$${dailyChange.toFixed(0)}`}
          change={`${Math.abs(dailyChangePct).toFixed(2)}%`}
          changeLabel="vs yesterday"
          positive={dailyChange >= 0}
          icon={Activity}
        />
        <StatCard
          label="Best Performer"
          value="MSFT"
          change="+35.8%"
          changeLabel="return"
          positive={true}
          icon={TrendingDown}
        />
      </div>

      {/* Charts row */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        {/* Portfolio chart */}
        <div
          className="lg:col-span-2 rounded-2xl p-5"
          style={{ background: "var(--surface)", border: "1px solid var(--border)" }}
        >
          <div className="flex items-center justify-between mb-4">
            <div>
              <h2 className="font-semibold text-sm" style={{ color: "var(--foreground)" }}>
                Portfolio Performance
              </h2>
              <p className="text-xs" style={{ color: "var(--muted)" }}>
                Last 7 months
              </p>
            </div>
            <span
              className="text-xs font-medium px-3 py-1 rounded-full"
              style={{ background: "#22c55e22", color: "#22c55e" }}
            >
              +23.8%
            </span>
          </div>
          <PortfolioChart />
        </div>

        {/* Allocation chart */}
        <div
          className="rounded-2xl p-5"
          style={{ background: "var(--surface)", border: "1px solid var(--border)" }}
        >
          <div className="mb-4">
            <h2 className="font-semibold text-sm" style={{ color: "var(--foreground)" }}>
              Asset Allocation
            </h2>
            <p className="text-xs" style={{ color: "var(--muted)" }}>
              Current portfolio
            </p>
          </div>
          <AllocationChart />
        </div>
      </div>

      {/* Bottom row */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {/* Top holdings */}
        <div
          className="rounded-2xl p-5"
          style={{ background: "var(--surface)", border: "1px solid var(--border)" }}
        >
          <h2 className="font-semibold text-sm mb-4" style={{ color: "var(--foreground)" }}>
            Top Holdings
          </h2>
          <div className="space-y-3">
            {topAssets.map((asset) => {
              const value = asset.currentPrice * asset.quantity;
              const gain = (asset.currentPrice - asset.avgPrice) / asset.avgPrice * 100;
              const positive = gain >= 0;
              return (
                <div key={asset.id} className="flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <div
                      className="w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold text-white"
                      style={{ background: asset.color }}
                    >
                      {asset.ticker.slice(0, 2)}
                    </div>
                    <div>
                      <p className="text-sm font-medium" style={{ color: "var(--foreground)" }}>
                        {asset.ticker}
                      </p>
                      <p className="text-xs" style={{ color: "var(--muted)" }}>
                        {asset.quantity} units
                      </p>
                    </div>
                  </div>
                  <div className="text-right">
                    <p className="text-sm font-semibold" style={{ color: "var(--foreground)" }}>
                      ${value.toLocaleString("en-US", { maximumFractionDigits: 0 })}
                    </p>
                    <p
                      className="text-xs font-medium"
                      style={{ color: positive ? "var(--green)" : "var(--red)" }}
                    >
                      {positive ? "+" : ""}{gain.toFixed(1)}%
                    </p>
                  </div>
                </div>
              );
            })}
          </div>
        </div>

        {/* Recent transactions */}
        <div
          className="rounded-2xl p-5"
          style={{ background: "var(--surface)", border: "1px solid var(--border)" }}
        >
          <h2 className="font-semibold text-sm mb-4" style={{ color: "var(--foreground)" }}>
            Recent Transactions
          </h2>
          <div className="space-y-3">
            {recentTransactions.map((tx) => (
              <div key={tx.id} className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <div
                    className="w-8 h-8 rounded-lg flex items-center justify-center"
                    style={{ background: "var(--surface-2)" }}
                  >
                    {tx.type === "buy" || tx.type === "deposit" ? (
                      <ArrowUpRight size={14} color="#22c55e" />
                    ) : (
                      <ArrowDownRight size={14} color="#ef4444" />
                    )}
                  </div>
                  <div>
                    <p className="text-sm font-medium" style={{ color: "var(--foreground)" }}>
                      {tx.ticker}
                    </p>
                    <p className="text-xs" style={{ color: "var(--muted)" }}>
                      {tx.date}
                    </p>
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <TransactionBadge type={tx.type} />
                  <p
                    className="text-sm font-semibold"
                    style={{
                      color:
                        tx.type === "sell" || tx.type === "withdrawal"
                          ? "var(--red)"
                          : "var(--green)",
                    }}
                  >
                    {tx.type === "sell" || tx.type === "withdrawal" ? "-" : "+"}$
                    {tx.total.toLocaleString("en-US", { maximumFractionDigits: 0 })}
                  </p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
