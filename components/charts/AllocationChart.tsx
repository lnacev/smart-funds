"use client";

import { PieChart, Pie, Cell, Tooltip, ResponsiveContainer } from "recharts";
import { getAllocationData } from "@/lib/data";

interface CustomTooltipProps {
  active?: boolean;
  payload?: Array<{ payload: { fullName: string; value: number; amount: number } }>;
}

function CustomTooltip({ active, payload }: CustomTooltipProps) {
  if (active && payload && payload.length) {
    const data = payload[0].payload;
    return (
      <div
        className="px-3 py-2 rounded-lg text-sm shadow-lg"
        style={{
          background: "var(--surface-2)",
          border: "1px solid var(--border)",
          color: "var(--foreground)",
        }}
      >
        <p className="font-semibold text-xs mb-1">{data.fullName}</p>
        <p className="font-bold">{data.value}%</p>
        <p style={{ color: "var(--muted)" }} className="text-xs">
          ${data.amount.toLocaleString("en-US", { maximumFractionDigits: 0 })}
        </p>
      </div>
    );
  }
  return null;
}

export default function AllocationChart() {
  const data = getAllocationData();

  return (
    <div className="flex items-center gap-4">
      <div style={{ width: 160, height: 160, flexShrink: 0 }}>
        <ResponsiveContainer width="100%" height="100%">
          <PieChart>
            <Pie
              data={data}
              cx="50%"
              cy="50%"
              innerRadius={50}
              outerRadius={75}
              paddingAngle={2}
              dataKey="value"
            >
              {data.map((entry, index) => (
                <Cell key={`cell-${index}`} fill={entry.color} stroke="none" />
              ))}
            </Pie>
            <Tooltip content={<CustomTooltip />} />
          </PieChart>
        </ResponsiveContainer>
      </div>
      <div className="flex-1 space-y-2">
        {data.map((item) => (
          <div key={item.name} className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <div
                className="w-2.5 h-2.5 rounded-full flex-shrink-0"
                style={{ background: item.color }}
              />
              <span className="text-xs font-medium" style={{ color: "var(--muted)" }}>
                {item.name}
              </span>
            </div>
            <span className="text-xs font-bold" style={{ color: "var(--foreground)" }}>
              {item.value}%
            </span>
          </div>
        ))}
      </div>
    </div>
  );
}
