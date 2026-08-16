import React from 'react';
import { 
  LayoutDashboard, Box, Activity, Users, Settings, 
  Search, Bell, ChevronDown, CheckCircle, Server, 
  Zap, Database, Shield, MoreVertical, LogOut, Code,
  AlertCircle
} from 'lucide-react';

export function Dark() {
  return (
    <div className="min-h-screen flex bg-slate-950 text-slate-50 font-sans selection:bg-indigo-500/30">
      {/* Sidebar */}
      <aside className="w-64 bg-slate-900 border-r border-slate-800/60 flex flex-col relative z-20">
        <div className="h-16 flex items-center px-6 border-b border-slate-800/60 bg-gradient-to-r from-indigo-500/10 to-transparent">
          <div className="flex items-center gap-3">
            <div className="w-8 h-8 rounded-lg bg-indigo-500 flex items-center justify-center shadow-[0_0_15px_rgba(99,102,241,0.5)]">
              <Zap className="w-5 h-5 text-white" />
            </div>
            <span className="font-bold tracking-wide text-white">ARM-System</span>
          </div>
        </div>
        
        <div className="flex-1 py-6 px-4 space-y-8 overflow-y-auto">
          <div className="space-y-1">
            <p className="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Main Menu</p>
            <NavItem icon={<LayoutDashboard size={18} />} label="Dashboard" active />
            <NavItem icon={<Box size={18} />} label="API Modules" />
            <NavItem icon={<Activity size={18} />} label="System Logs" badge="12" />
            <NavItem icon={<Users size={18} />} label="API Accounts" />
          </div>

          <div className="space-y-1">
            <p className="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Configuration</p>
            <NavItem icon={<Database size={18} />} label="Databases" />
            <NavItem icon={<Shield size={18} />} label="Security" />
            <NavItem icon={<Settings size={18} />} label="Settings" />
          </div>
        </div>

        <div className="p-4 border-t border-slate-800/60">
          <button className="flex items-center gap-3 w-full px-3 py-2 text-sm text-slate-400 hover:text-white transition-colors rounded-md hover:bg-slate-800/50">
            <div className="w-8 h-8 rounded-full bg-slate-800 flex items-center justify-center overflow-hidden border border-slate-700">
              <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=Admin" alt="Admin" className="w-full h-full object-cover" />
            </div>
            <div className="flex-1 text-left">
              <p className="text-sm font-medium text-white">Admin User</p>
              <p className="text-xs text-slate-500">admin@coderstation</p>
            </div>
            <LogOut size={16} />
          </button>
        </div>
      </aside>

      {/* Main Content */}
      <main className="flex-1 flex flex-col min-w-0 relative">
        {/* Background glow effects */}
        <div className="absolute top-0 left-0 right-0 h-96 bg-indigo-500/5 blur-[120px] pointer-events-none rounded-full" />
        
        {/* Topbar */}
        <header className="h-16 flex items-center justify-between px-8 border-b border-slate-800/60 bg-slate-900/50 backdrop-blur-md sticky top-0 z-10">
          <div className="flex items-center gap-4 text-slate-400">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" />
              <input 
                type="text" 
                placeholder="Search endpoints, logs..." 
                className="w-64 bg-slate-800/50 border border-slate-700/50 rounded-full pl-9 pr-4 py-1.5 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500/50 transition-all"
              />
            </div>
          </div>
          
          <div className="flex items-center gap-4">
            <button className="relative p-2 text-slate-400 hover:text-white transition-colors rounded-full hover:bg-slate-800/50">
              <Bell size={20} />
              <span className="absolute top-1.5 right-1.5 w-2 h-2 bg-rose-500 rounded-full ring-2 ring-slate-900" />
            </button>
            <div className="h-6 w-px bg-slate-800" />
            <button className="flex items-center gap-2 text-sm font-medium text-slate-300 hover:text-white">
              <span className="px-2 py-1 rounded bg-indigo-500/20 text-indigo-400 border border-indigo-500/20 text-xs">v1.2.4</span>
            </button>
          </div>
        </header>

        {/* Dashboard Content */}
        <div className="flex-1 overflow-auto p-8 relative z-0 space-y-8">
          <div>
            <h1 className="text-2xl font-bold text-white mb-1">System Overview</h1>
            <p className="text-slate-400 text-sm">Monitor ARM-System API health and module performance.</p>
          </div>

          {/* Stats Grid */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <StatCard 
              title="Total API Endpoints" 
              value="124" 
              trend="+12% from last month" 
              trendUp 
              icon={<Code className="w-5 h-5 text-indigo-400" />}
              glowColor="indigo"
            />
            <StatCard 
              title="Active Modules" 
              value="5" 
              trend="All systems nominal" 
              icon={<Box className="w-5 h-5 text-emerald-400" />}
              glowColor="emerald"
            />
            <StatCard 
              title="System Status" 
              value="99.9%" 
              trend="Uptime over 30 days" 
              icon={<Server className="w-5 h-5 text-blue-400" />}
              glowColor="blue"
            />
            <StatCard 
              title="Registered Accounts" 
              value="1,204" 
              trend="+4% this week" 
              trendUp 
              icon={<Users className="w-5 h-5 text-purple-400" />}
              glowColor="purple"
            />
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {/* Modules Table */}
            <div className="lg:col-span-2 bg-slate-900/40 border border-slate-800/60 rounded-xl overflow-hidden backdrop-blur-sm relative shadow-2xl">
              <div className="p-5 border-b border-slate-800/60 flex justify-between items-center bg-slate-900/50">
                <h2 className="text-lg font-semibold text-white">API Modules</h2>
                <button className="text-sm text-indigo-400 hover:text-indigo-300 font-medium">View All</button>
              </div>
              <div className="overflow-x-auto">
                <table className="w-full text-sm text-left">
                  <thead className="text-xs text-slate-400 bg-slate-900/80 uppercase">
                    <tr>
                      <th className="px-5 py-3 font-medium">Module Name</th>
                      <th className="px-5 py-3 font-medium">Base Route</th>
                      <th className="px-5 py-3 font-medium">Status</th>
                      <th className="px-5 py-3 font-medium">Endpoints</th>
                      <th className="px-5 py-3 font-medium text-right">Calls (24h)</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-800/60">
                    <ModuleRow name="Voter" route="/api/v1/voter" status="Active" endpoints={24} calls="12.4k" />
                    <ModuleRow name="Election" route="/api/v1/election" status="Active" endpoints={18} calls="8.2k" />
                    <ModuleRow name="Candidate" route="/api/v1/candidate" status="Active" endpoints={15} calls="9.1k" />
                    <ModuleRow name="College" route="/api/v1/college" status="Active" endpoints={12} calls="4.3k" />
                    <ModuleRow name="API-Account" route="/api/v1/account" status="Warning" endpoints={20} calls="2.1k" />
                  </tbody>
                </table>
              </div>
            </div>

            {/* Activity Log */}
            <div className="bg-slate-900/40 border border-slate-800/60 rounded-xl overflow-hidden backdrop-blur-sm shadow-2xl flex flex-col">
              <div className="p-5 border-b border-slate-800/60 bg-slate-900/50">
                <h2 className="text-lg font-semibold text-white">Recent Activity</h2>
              </div>
              <div className="p-5 flex-1 overflow-y-auto space-y-6">
                <ActivityItem 
                  title="New API key generated" 
                  desc="Client A generated a new production key." 
                  time="2 mins ago" 
                  icon={<Shield className="w-4 h-4 text-emerald-400" />} 
                  iconBg="bg-emerald-500/10" 
                  iconBorder="border-emerald-500/20"
                />
                <ActivityItem 
                  title="System backup completed" 
                  desc="Automated daily database backup finished." 
                  time="1 hour ago" 
                  icon={<Database className="w-4 h-4 text-blue-400" />} 
                  iconBg="bg-blue-500/10" 
                  iconBorder="border-blue-500/20"
                />
                <ActivityItem 
                  title="Rate limit triggered" 
                  desc="Client B exceeded 1000 req/min limit." 
                  time="3 hours ago" 
                  icon={<AlertCircle className="w-4 h-4 text-rose-400" />} 
                  iconBg="bg-rose-500/10" 
                  iconBorder="border-rose-500/20"
                />
                <ActivityItem 
                  title="Module config updated" 
                  desc="Admin user updated Election module settings." 
                  time="5 hours ago" 
                  icon={<Settings className="w-4 h-4 text-indigo-400" />} 
                  iconBg="bg-indigo-500/10" 
                  iconBorder="border-indigo-500/20"
                />
                <ActivityItem 
                  title="New user registered" 
                  desc="voter_1042 created an account." 
                  time="12 hours ago" 
                  icon={<Users className="w-4 h-4 text-purple-400" />} 
                  iconBg="bg-purple-500/10" 
                  iconBorder="border-purple-500/20"
                />
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  );
}

function NavItem({ icon, label, active = false, badge }: { icon: React.ReactNode, label: string, active?: boolean, badge?: string }) {
  return (
    <a href="#" className={`flex items-center justify-between px-3 py-2.5 rounded-lg transition-all group relative ${
      active 
        ? 'text-white bg-indigo-500/10 shadow-[inset_2px_0_0_rgba(99,102,241,1)]' 
        : 'text-slate-400 hover:text-white hover:bg-slate-800/50'
    }`}>
      <div className="flex items-center gap-3">
        <div className={`${active ? 'text-indigo-400' : 'text-slate-500 group-hover:text-slate-300'}`}>
          {icon}
        </div>
        <span className="font-medium text-sm">{label}</span>
      </div>
      {badge && (
        <span className="px-2 py-0.5 rounded-full bg-slate-800 text-xs font-medium text-slate-300 border border-slate-700">
          {badge}
        </span>
      )}
    </a>
  );
}

function StatCard({ title, value, trend, trendUp, icon, glowColor }: any) {
  const glowClasses = {
    indigo: "group-hover:shadow-[0_0_20px_rgba(99,102,241,0.15)] border-t-indigo-500/50",
    emerald: "group-hover:shadow-[0_0_20px_rgba(52,211,153,0.15)] border-t-emerald-500/50",
    blue: "group-hover:shadow-[0_0_20px_rgba(96,165,250,0.15)] border-t-blue-500/50",
    purple: "group-hover:shadow-[0_0_20px_rgba(192,132,252,0.15)] border-t-purple-500/50",
  }[glowColor as string] || "border-t-slate-700/50";

  const iconBgClasses = {
    indigo: "bg-indigo-500/10 border-indigo-500/20",
    emerald: "bg-emerald-500/10 border-emerald-500/20",
    blue: "bg-blue-500/10 border-blue-500/20",
    purple: "bg-purple-500/10 border-purple-500/20",
  }[glowColor as string] || "bg-slate-800 border-slate-700";

  return (
    <div className={`bg-slate-900/40 backdrop-blur-sm border border-slate-800/60 p-5 rounded-xl border-t-2 transition-all duration-300 group ${glowClasses}`}>
      <div className="flex justify-between items-start mb-4">
        <div>
          <p className="text-slate-400 text-sm font-medium mb-1">{title}</p>
          <h3 className="text-2xl font-bold text-white tracking-tight">{value}</h3>
        </div>
        <div className={`p-2 rounded-lg border ${iconBgClasses}`}>
          {icon}
        </div>
      </div>
      <div className="flex items-center gap-2 text-xs">
        {trendUp !== undefined && (
          <span className={trendUp ? "text-emerald-400 font-medium" : "text-rose-400 font-medium"}>
            {trendUp ? "↑" : "↓"}
          </span>
        )}
        <span className="text-slate-500">{trend}</span>
      </div>
    </div>
  );
}

function ModuleRow({ name, route, status, endpoints, calls }: any) {
  const isWarning = status === 'Warning';
  return (
    <tr className="hover:bg-slate-800/30 transition-colors group">
      <td className="px-5 py-3.5 whitespace-nowrap">
        <div className="flex items-center gap-3">
          <div className="w-8 h-8 rounded bg-slate-800 border border-slate-700 flex items-center justify-center">
            <Box size={14} className="text-slate-400 group-hover:text-indigo-400 transition-colors" />
          </div>
          <span className="font-medium text-slate-200">{name}</span>
        </div>
      </td>
      <td className="px-5 py-3.5 whitespace-nowrap">
        <code className="px-2 py-1 rounded bg-slate-950 border border-slate-800 text-slate-400 text-xs font-mono">
          {route}
        </code>
      </td>
      <td className="px-5 py-3.5 whitespace-nowrap">
        <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border ${
          isWarning 
            ? 'bg-rose-500/10 text-rose-400 border-rose-500/20'
            : 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
        }`}>
          <span className={`w-1.5 h-1.5 rounded-full ${isWarning ? 'bg-rose-500 animate-pulse' : 'bg-emerald-500'}`} />
          {status}
        </span>
      </td>
      <td className="px-5 py-3.5 whitespace-nowrap text-slate-300">
        {endpoints}
      </td>
      <td className="px-5 py-3.5 whitespace-nowrap text-right text-slate-300 font-medium">
        {calls}
      </td>
    </tr>
  );
}

function ActivityItem({ title, desc, time, icon, iconBg, iconBorder }: any) {
  return (
    <div className="flex gap-4 relative">
      <div className="absolute left-4 top-8 bottom-[-24px] w-px bg-slate-800/60 last:hidden" />
      <div className={`w-8 h-8 rounded-full border flex items-center justify-center shrink-0 z-10 ${iconBg} ${iconBorder}`}>
        {icon}
      </div>
      <div className="flex-1 pb-1">
        <div className="flex justify-between items-start mb-0.5">
          <p className="text-sm font-medium text-slate-200">{title}</p>
          <span className="text-xs text-slate-500 whitespace-nowrap ml-2">{time}</span>
        </div>
        <p className="text-sm text-slate-400">{desc}</p>
      </div>
    </div>
  );
}
