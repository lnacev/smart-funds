export interface Asset {
  id: string;
  name: string;
  ticker: string;
  type: "stock" | "etf" | "crypto" | "bond" | "cash";
  quantity: number;
  avgPrice: number;
  currentPrice: number;
  change24h: number; // percent
  color: string;
}

export interface Transaction {
  id: string;
  date: string;
  asset: string;
  ticker: string;
  type: "buy" | "sell" | "dividend" | "deposit" | "withdrawal";
  quantity: number;
  price: number;
  total: number;
}

export interface PortfolioSnapshot {
  date: string;
  value: number;
}

export const assets: Asset[] = [
  {
    id: "1",
    name: "Apple Inc.",
    ticker: "AAPL",
    type: "stock",
    quantity: 25,
    avgPrice: 152.3,
    currentPrice: 189.84,
    change24h: 0.82,
    color: "#6366f1",
  },
  {
    id: "2",
    name: "Microsoft Corp.",
    ticker: "MSFT",
    type: "stock",
    quantity: 15,
    avgPrice: 310.5,
    currentPrice: 421.5,
    change24h: 1.14,
    color: "#8b5cf6",
  },
  {
    id: "3",
    name: "Vanguard S&P 500 ETF",
    ticker: "VOO",
    type: "etf",
    quantity: 10,
    avgPrice: 380.0,
    currentPrice: 512.3,
    change24h: 0.54,
    color: "#a855f7",
  },
  {
    id: "4",
    name: "Bitcoin",
    ticker: "BTC",
    type: "crypto",
    quantity: 0.5,
    avgPrice: 38000,
    currentPrice: 67450,
    change24h: -2.31,
    color: "#ec4899",
  },
  {
    id: "5",
    name: "Ethereum",
    ticker: "ETH",
    type: "crypto",
    quantity: 3.2,
    avgPrice: 2100,
    currentPrice: 3520,
    change24h: -1.45,
    color: "#f43f5e",
  },
  {
    id: "6",
    name: "US Treasury Bond",
    ticker: "GOVZ",
    type: "bond",
    quantity: 5,
    avgPrice: 95.0,
    currentPrice: 97.2,
    change24h: 0.12,
    color: "#0ea5e9",
  },
  {
    id: "7",
    name: "Cash & Equivalents",
    ticker: "USD",
    type: "cash",
    quantity: 1,
    avgPrice: 5420,
    currentPrice: 5420,
    change24h: 0,
    color: "#22c55e",
  },
];

export const transactions: Transaction[] = [
  {
    id: "t1",
    date: "2026-04-08",
    asset: "Apple Inc.",
    ticker: "AAPL",
    type: "buy",
    quantity: 5,
    price: 188.5,
    total: 942.5,
  },
  {
    id: "t2",
    date: "2026-04-07",
    asset: "Bitcoin",
    ticker: "BTC",
    type: "sell",
    quantity: 0.1,
    price: 69200,
    total: 6920.0,
  },
  {
    id: "t3",
    date: "2026-04-05",
    asset: "Microsoft Corp.",
    ticker: "MSFT",
    type: "buy",
    quantity: 3,
    price: 418.0,
    total: 1254.0,
  },
  {
    id: "t4",
    date: "2026-04-03",
    asset: "Cash",
    ticker: "USD",
    type: "deposit",
    quantity: 1,
    price: 2500,
    total: 2500,
  },
  {
    id: "t5",
    date: "2026-04-01",
    asset: "Vanguard S&P 500 ETF",
    ticker: "VOO",
    type: "dividend",
    quantity: 10,
    price: 1.45,
    total: 14.5,
  },
  {
    id: "t6",
    date: "2026-03-28",
    asset: "Ethereum",
    ticker: "ETH",
    type: "buy",
    quantity: 0.5,
    price: 3410,
    total: 1705,
  },
  {
    id: "t7",
    date: "2026-03-25",
    asset: "US Treasury Bond",
    ticker: "GOVZ",
    type: "buy",
    quantity: 2,
    price: 95.5,
    total: 191,
  },
  {
    id: "t8",
    date: "2026-03-20",
    asset: "Apple Inc.",
    ticker: "AAPL",
    type: "buy",
    quantity: 10,
    price: 175.2,
    total: 1752,
  },
  {
    id: "t9",
    date: "2026-03-15",
    asset: "Cash",
    ticker: "USD",
    type: "withdrawal",
    quantity: 1,
    price: 1000,
    total: 1000,
  },
  {
    id: "t10",
    date: "2026-03-10",
    asset: "Bitcoin",
    ticker: "BTC",
    type: "buy",
    quantity: 0.2,
    price: 66500,
    total: 13300,
  },
];

export const portfolioHistory: PortfolioSnapshot[] = [
  { date: "Oct", value: 68400 },
  { date: "Nov", value: 72100 },
  { date: "Dec", value: 69800 },
  { date: "Jan", value: 75300 },
  { date: "Feb", value: 78900 },
  { date: "Mar", value: 81200 },
  { date: "Apr", value: 84650 },
];

export function getTotalValue(): number {
  return assets.reduce((sum, a) => sum + a.currentPrice * a.quantity, 0);
}

export function getTotalCost(): number {
  return assets.reduce((sum, a) => sum + a.avgPrice * a.quantity, 0);
}

export function getTotalGain(): number {
  return getTotalValue() - getTotalCost();
}

export function getTotalGainPercent(): number {
  return (getTotalGain() / getTotalCost()) * 100;
}

export function getDailyChange(): number {
  return assets.reduce(
    (sum, a) => sum + (a.currentPrice * a.quantity * a.change24h) / 100,
    0
  );
}

export function getAllocationData() {
  const total = getTotalValue();
  return assets.map((a) => ({
    name: a.ticker,
    fullName: a.name,
    value: parseFloat(((a.currentPrice * a.quantity * 100) / total).toFixed(1)),
    amount: a.currentPrice * a.quantity,
    color: a.color,
  }));
}
