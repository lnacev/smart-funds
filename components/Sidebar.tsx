"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  LayoutDashboard,
  PieChart,
  ArrowLeftRight,
  TrendingUp,
  Settings,
  Wallet,
} from "lucide-react";

const navItems = [
  { href: "/", label: "Dashboard", icon: LayoutDashboard },
  { href: "/portfolio", label: "Portfolio", icon: PieChart },
  { href: "/transactions", label: "Transactions", icon: ArrowLeftRight },
];

export default function Sidebar() {
  const pathname = usePathname();

  return (
    <aside
      className="w-64 flex-shrink-0 flex flex-col h-full border-r"
      style={{
        background: "var(--surface)",
        borderColor: "var(--border)",
      }}
    >
      {/* Logo */}
      <div
        className="flex items-center gap-3 px-6 py-5 border-b"
        style={{ borderColor: "var(--border)" }}
      >
        <div
          className="w-9 h-9 rounded-xl flex items-center justify-center"
          style={{ background: "var(--accent)" }}
        >
          <TrendingUp size={18} color="white" />
        </div>
        <div>
          <p className="font-bold text-sm" style={{ color: "var(--foreground)" }}>
            SmartFunds
          </p>
          <p className="text-xs" style={{ color: "var(--muted)" }}>
            Financial Dashboard
          </p>
        </div>
      </div>

      {/* Navigation */}
      <nav className="flex-1 px-3 py-4">
        <p
          className="text-xs font-semibold uppercase tracking-wider px-3 mb-3"
          style={{ color: "var(--muted)" }}
        >
          Menu
        </p>
        <ul className="space-y-1">
          {navItems.map((item) => {
            const Icon = item.icon;
            const active = pathname === item.href;
            return (
              <li key={item.href}>
                <Link
                  href={item.href}
                  className="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-sm font-medium"
                  style={{
                    background: active ? "var(--accent)" : "transparent",
                    color: active ? "white" : "var(--muted)",
                  }}
                >
                  <Icon size={18} />
                  {item.label}
                </Link>
              </li>
            );
          })}
        </ul>
      </nav>

      {/* Portfolio value */}
      <div
        className="mx-3 mb-3 p-4 rounded-xl"
        style={{ background: "var(--surface-2)" }}
      >
        <div className="flex items-center gap-2 mb-2">
          <Wallet size={14} style={{ color: "var(--muted)" }} />
          <p className="text-xs" style={{ color: "var(--muted)" }}>
            Total Portfolio
          </p>
        </div>
        <p className="text-lg font-bold" style={{ color: "var(--foreground)" }}>
          $84,650.42
        </p>
        <p className="text-xs mt-1" style={{ color: "var(--green)" }}>
          +$23,870 (39.3%)
        </p>
      </div>

      {/* Settings */}
      <div
        className="px-3 pb-4 border-t pt-3"
        style={{ borderColor: "var(--border)" }}
      >
        <Link
          href="/settings"
          className="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all text-sm font-medium"
          style={{ color: "var(--muted)" }}
        >
          <Settings size={18} />
          Settings
        </Link>
      </div>
    </aside>
  );
}
