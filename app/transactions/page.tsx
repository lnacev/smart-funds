import { ArrowUpRight, ArrowDownRight } from "lucide-react";
import { transactions } from "@/lib/data";

function TransactionBadge({ type }: { type: string }) {
  const styles: Record<string, { bg: string; color: string; label: string }> = {
    buy: { bg: "#22c55e22", color: "#22c55e", label: "Buy" },
    sell: { bg: "#ef444422", color: "#ef4444", label: "Sell" },
    dividend: { bg: "#6366f122", color: "#6366f1", label: "Dividend" },
    deposit: { bg: "#0ea5e922", color: "#0ea5e9", label: "Deposit" },
    withdrawal: { bg: "#f9731622", color: "#f97316", label: "Withdrawal" },
  };
  const s = styles[type] || styles.buy;
  return (
    <span
      className="text-xs font-medium px-2.5 py-1 rounded-full"
      style={{ background: s.bg, color: s.color }}
    >
      {s.label}
    </span>
  );
}

export default function TransactionsPage() {
  return (
    <div className="p-6 max-w-7xl mx-auto">
      <div className="mb-8">
        <h1 className="text-2xl font-bold mb-1" style={{ color: "var(--foreground)" }}>
          Transactions
        </h1>
        <p className="text-sm" style={{ color: "var(--muted)" }}>
          Your complete transaction history
        </p>
      </div>

      {/* Summary cards */}
      <div className="grid grid-cols-3 gap-4 mb-6">
        {[
          { label: "Total Invested", value: "$28,844", sub: "10 transactions", color: "var(--accent)" },
          { label: "Total Withdrawn", value: "$7,920", sub: "2 transactions", color: "#ef4444" },
          { label: "Dividends Received", value: "$14.50", sub: "1 payment", color: "#22c55e" },
        ].map((card) => (
          <div
            key={card.label}
            className="rounded-2xl p-5"
            style={{ background: "var(--surface)", border: "1px solid var(--border)" }}
          >
            <p className="text-xs font-medium mb-2" style={{ color: "var(--muted)" }}>
              {card.label}
            </p>
            <p className="text-xl font-bold" style={{ color: "var(--foreground)" }}>
              {card.value}
            </p>
            <p className="text-xs mt-1" style={{ color: card.color }}>
              {card.sub}
            </p>
          </div>
        ))}
      </div>

      {/* Table */}
      <div
        className="rounded-2xl overflow-hidden"
        style={{ background: "var(--surface)", border: "1px solid var(--border)" }}
      >
        <div className="p-5 border-b" style={{ borderColor: "var(--border)" }}>
          <div className="grid grid-cols-6 gap-4">
            <span className="text-xs font-semibold uppercase tracking-wider" style={{ color: "var(--muted)" }}>Date</span>
            <span className="text-xs font-semibold uppercase tracking-wider col-span-2" style={{ color: "var(--muted)" }}>Asset</span>
            <span className="text-xs font-semibold uppercase tracking-wider text-center" style={{ color: "var(--muted)" }}>Type</span>
            <span className="text-xs font-semibold uppercase tracking-wider text-right" style={{ color: "var(--muted)" }}>Quantity / Price</span>
            <span className="text-xs font-semibold uppercase tracking-wider text-right" style={{ color: "var(--muted)" }}>Total</span>
          </div>
        </div>

        <div className="divide-y" style={{ borderColor: "var(--border)" }}>
          {transactions.map((tx) => {
            const isPositive = tx.type === "buy" || tx.type === "deposit" || tx.type === "dividend";
            return (
              <div key={tx.id} className="p-5 hover:opacity-80 transition-opacity">
                <div className="grid grid-cols-6 gap-4 items-center">
                  <p className="text-sm" style={{ color: "var(--muted)" }}>
                    {tx.date}
                  </p>

                  <div className="col-span-2 flex items-center gap-3">
                    <div
                      className="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                      style={{ background: "var(--surface-2)" }}
                    >
                      {isPositive ? (
                        <ArrowUpRight size={14} color="#22c55e" />
                      ) : (
                        <ArrowDownRight size={14} color="#ef4444" />
                      )}
                    </div>
                    <div>
                      <p className="text-sm font-medium" style={{ color: "var(--foreground)" }}>
                        {tx.asset}
                      </p>
                      <p className="text-xs" style={{ color: "var(--muted)" }}>
                        {tx.ticker}
                      </p>
                    </div>
                  </div>

                  <div className="flex justify-center">
                    <TransactionBadge type={tx.type} />
                  </div>

                  <div className="text-right">
                    <p className="text-sm font-medium" style={{ color: "var(--foreground)" }}>
                      {tx.type !== "deposit" && tx.type !== "withdrawal"
                        ? `${tx.quantity} units`
                        : "—"}
                    </p>
                    <p className="text-xs" style={{ color: "var(--muted)" }}>
                      {tx.type !== "deposit" && tx.type !== "withdrawal"
                        ? `@ $${tx.price.toLocaleString("en-US")}`
                        : "Cash"}
                    </p>
                  </div>

                  <div className="text-right">
                    <p
                      className="text-sm font-semibold"
                      style={{ color: isPositive ? "var(--green)" : "var(--red)" }}
                    >
                      {isPositive ? "+" : "-"}$
                      {tx.total.toLocaleString("en-US", { maximumFractionDigits: 0 })}
                    </p>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
