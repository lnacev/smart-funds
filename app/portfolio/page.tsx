import { ArrowUpRight, ArrowDownRight } from "lucide-react";
import { assets, getTotalValue } from "@/lib/data";

export default function PortfolioPage() {
  const totalValue = getTotalValue();

  return (
    <div className="p-6 max-w-7xl mx-auto">
      <div className="mb-8">
        <h1 className="text-2xl font-bold mb-1" style={{ color: "var(--foreground)" }}>
          Portfolio
        </h1>
        <p className="text-sm" style={{ color: "var(--muted)" }}>
          All your holdings in one place
        </p>
      </div>

      <div
        className="rounded-2xl overflow-hidden"
        style={{ background: "var(--surface)", border: "1px solid var(--border)" }}
      >
        <div className="p-5 border-b" style={{ borderColor: "var(--border)" }}>
          <div className="grid grid-cols-6 gap-4">
            <span className="text-xs font-semibold uppercase tracking-wider col-span-2" style={{ color: "var(--muted)" }}>Asset</span>
            <span className="text-xs font-semibold uppercase tracking-wider text-right" style={{ color: "var(--muted)" }}>Price</span>
            <span className="text-xs font-semibold uppercase tracking-wider text-right" style={{ color: "var(--muted)" }}>24h</span>
            <span className="text-xs font-semibold uppercase tracking-wider text-right" style={{ color: "var(--muted)" }}>Value</span>
            <span className="text-xs font-semibold uppercase tracking-wider text-right" style={{ color: "var(--muted)" }}>Return</span>
          </div>
        </div>

        <div className="divide-y" style={{ borderColor: "var(--border)" }}>
          {assets.map((asset) => {
            const value = asset.currentPrice * asset.quantity;
            const cost = asset.avgPrice * asset.quantity;
            const gain = value - cost;
            const gainPct = (gain / cost) * 100;
            const allocation = (value / totalValue) * 100;
            const positive24h = asset.change24h >= 0;
            const positiveAll = gain >= 0;

            return (
              <div key={asset.id} className="p-5 hover:opacity-80 transition-opacity">
                <div className="grid grid-cols-6 gap-4 items-center">
                  {/* Asset info */}
                  <div className="flex items-center gap-3 col-span-2">
                    <div
                      className="w-9 h-9 rounded-xl flex items-center justify-center text-xs font-bold text-white flex-shrink-0"
                      style={{ background: asset.color }}
                    >
                      {asset.ticker.slice(0, 2)}
                    </div>
                    <div>
                      <p className="text-sm font-semibold" style={{ color: "var(--foreground)" }}>
                        {asset.name}
                      </p>
                      <div className="flex items-center gap-2">
                        <span className="text-xs font-medium" style={{ color: "var(--muted)" }}>
                          {asset.ticker}
                        </span>
                        <span
                          className="text-xs px-1.5 py-0.5 rounded capitalize"
                          style={{ background: "var(--surface-2)", color: "var(--muted)" }}
                        >
                          {asset.type}
                        </span>
                      </div>
                    </div>
                  </div>

                  {/* Current price */}
                  <div className="text-right">
                    <p className="text-sm font-medium" style={{ color: "var(--foreground)" }}>
                      ${asset.currentPrice.toLocaleString("en-US", { maximumFractionDigits: 2 })}
                    </p>
                    <p className="text-xs" style={{ color: "var(--muted)" }}>
                      ×{asset.quantity}
                    </p>
                  </div>

                  {/* 24h change */}
                  <div className="text-right">
                    <div
                      className="inline-flex items-center gap-1 text-xs font-medium"
                      style={{ color: positive24h ? "var(--green)" : "var(--red)" }}
                    >
                      {positive24h ? <ArrowUpRight size={13} /> : <ArrowDownRight size={13} />}
                      {Math.abs(asset.change24h).toFixed(2)}%
                    </div>
                  </div>

                  {/* Value + allocation */}
                  <div className="text-right">
                    <p className="text-sm font-semibold" style={{ color: "var(--foreground)" }}>
                      ${value.toLocaleString("en-US", { maximumFractionDigits: 0 })}
                    </p>
                    <p className="text-xs" style={{ color: "var(--muted)" }}>
                      {allocation.toFixed(1)}%
                    </p>
                  </div>

                  {/* All-time return */}
                  <div className="text-right">
                    <p
                      className="text-sm font-semibold"
                      style={{ color: positiveAll ? "var(--green)" : "var(--red)" }}
                    >
                      {positiveAll ? "+" : ""}${gain.toLocaleString("en-US", { maximumFractionDigits: 0 })}
                    </p>
                    <p
                      className="text-xs font-medium"
                      style={{ color: positiveAll ? "var(--green)" : "var(--red)" }}
                    >
                      {positiveAll ? "+" : ""}{gainPct.toFixed(1)}%
                    </p>
                  </div>
                </div>

                {/* Allocation bar */}
                <div className="mt-3 ml-12">
                  <div
                    className="h-1 rounded-full overflow-hidden"
                    style={{ background: "var(--surface-2)" }}
                  >
                    <div
                      className="h-full rounded-full"
                      style={{ width: `${allocation}%`, background: asset.color }}
                    />
                  </div>
                </div>
              </div>
            );
          })}
        </div>

        {/* Footer totals */}
        <div
          className="p-5 border-t"
          style={{ borderColor: "var(--border)", background: "var(--surface-2)" }}
        >
          <div className="grid grid-cols-6 gap-4 items-center">
            <div className="col-span-2">
              <p className="text-sm font-bold" style={{ color: "var(--foreground)" }}>
                Total
              </p>
              <p className="text-xs" style={{ color: "var(--muted)" }}>
                {assets.length} assets
              </p>
            </div>
            <div />
            <div />
            <div className="text-right">
              <p className="text-sm font-bold" style={{ color: "var(--foreground)" }}>
                ${totalValue.toLocaleString("en-US", { maximumFractionDigits: 0 })}
              </p>
              <p className="text-xs" style={{ color: "var(--muted)" }}>100%</p>
            </div>
            <div className="text-right">
              <p className="text-sm font-bold" style={{ color: "var(--green)" }}>
                +$23,870
              </p>
              <p className="text-xs font-medium" style={{ color: "var(--green)" }}>
                +39.3%
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
