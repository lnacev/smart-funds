"use client";

import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Area,
  AreaChart,
} from "recharts";
import { portfolioHistory } from "@/lib/data";

interface CustomTooltipProps {
  active?: boolean;
  payload?: Array<{ value: number }>;
  label?: string;
}

function CustomTooltip({ active, payload, label }: CustomTooltipProps) {
  if (active && payload && payload.length) {
    return (
      <div
        className="px-3 py-2 rounded-lg text-sm shadow-lg"
        style={{
          background: "var(--surface-2)",
          border: "1px solid var(--border)",
          color: "var(--foreground)",
        }}
      >
        <p style={{ color: "var(--muted)" }} className="text-xs mb-1">
          {label}
        </p>
        <p className="font-bold">
          ${payload[0].value.toLocaleString("en-US")}
        </p>
      </div>
    );
  }
  return null;
}

export default function PortfolioChart() {
  return (
    <ResponsiveContainer width="100%" height={220}>
      <AreaChart data={portfolioHistory} margin={{ top: 5, right: 10, left: 0, bottom: 0 }}>
        <defs>
          <linearGradient id="portfolioGradient" x1="0" y1="0" x2="0" y2="1">
            <stop offset="5%" stopColor="#6366f1" stopOpacity={0.3} />
            <stop offset="95%" stopColor="#6366f1" stopOpacity={0} />
          </linearGradient>
        </defs>
        <CartesianGrid
          strokeDasharray="3 3"
          stroke="var(--border)"
          vertical={false}
        />
        <XAxis
          dataKey="date"
          tick={{ fill: "var(--muted)", fontSize: 12 }}
          axisLine={false}
          tickLine={false}
        />
        <YAxis
          tick={{ fill: "var(--muted)", fontSize: 12 }}
          axisLine={false}
          tickLine={false}
          tickFormatter={(v) => `$${(v / 1000).toFixed(0)}k`}
          width={50}
        />
        <Tooltip content={<CustomTooltip />} />
        <Area
          type="monotone"
          dataKey="value"
          stroke="#6366f1"
          strokeWidth={2.5}
          fill="url(#portfolioGradient)"
          dot={false}
          activeDot={{ r: 5, fill: "#6366f1", stroke: "var(--surface)", strokeWidth: 2 }}
        />
      </AreaChart>
    </ResponsiveContainer>
  );
}
