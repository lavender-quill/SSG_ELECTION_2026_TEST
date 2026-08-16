import React, { useState } from 'react';
import { 
  BarChart3, 
  Settings, 
  Users, 
  Database, 
  Activity, 
  ShieldCheck, 
  Menu, 
  Bell, 
  Search,
  ChevronDown,
  LayoutDashboard,
  Server,
  FileCode2,
  Key,
  LogOut
} from 'lucide-react';

export function Light() {
  const [sidebarOpen, setSidebarOpen] = useState(true);

  return (
    <div className="flex h-screen w-full bg-slate-50 text-slate-900 font-sans overflow-hidden">
      {/* Sidebar */}
      <aside className={`bg-white border-r border-slate-200 transition-all duration-300 flex flex-col ${sidebarOpen ? 'w-64' : 'w-20'}`}>
        <div className="h-16 flex items-center justify-between px-4 border-b border-slate-200">
          <div className="flex items-center gap-2 overflow-hidden whitespace-nowrap">
            <div className="bg-blue-600 text-white p-1.5 rounded-md flex-shrink-0">
              <ShieldCheck size={20} />
            </div>
            {sidebarOpen && <span className="font-bold text-lg tracking-tight">ARM System</span>}
          </div>
          <button onClick={() => setSidebarOpen(!sidebarOpen)} className="p-1 text-slate-400 hover:text-slate-600 rounded-md">
            <Menu size={20} />
          </button>
        </div>

        <div className="flex-1 overflow-y-auto py-4">
          <nav className="px-3 space-y-1">
            <NavItem icon={<LayoutDashboard size={20} />} label="Dashboard" active isOpen={sidebarOpen} />
            <NavItem icon={<Server size={20} />} label="API Endpoints" isOpen={sidebarOpen} />
            <NavItem icon={<FileCode2 size={20} />} label="Modules" isOpen={sidebarOpen} />
            <NavItem icon={<Users size={20} />} label="Accounts" isOpen={sidebarOpen} />
            <NavItem icon={<Key size={20} />} label="Access Keys" isOpen={sidebarOpen} />
            <NavItem icon={<Activity size={20} />} label="System Logs" isOpen={sidebarOpen} />
          </nav>
        </div>

        <div className="p-4 border-t border-slate-200">
          <nav className="space-y-1">
            <NavItem icon={<Settings size={20} />} label="Settings" isOpen={sidebarOpen} />
            <NavItem icon={<LogOut size={20} />} label="Sign Out" isOpen={sidebarOpen} className="text-red-600 hover:bg-red-50 hover:text-red-700" />
          </nav>
        </div>
      </aside>

      {/* Main Content */}
      <div className="flex-1 flex flex-col overflow-hidden">
        {/* Topbar */}
        <header className="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-6 shrink-0">
          <div className="w-1/3 max-w-md">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={18} />
              <input 
                type="text" 
                placeholder="Search endpoints, modules..." 
                className="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-colors"
              />
            </div>
          </div>
          
          <div className="flex items-center gap-4">
            <button className="relative p-2 text-slate-400 hover:text-slate-600 rounded-full hover:bg-slate-50 transition-colors">
              <Bell size={20} />
              <span className="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full border border-white"></span>
            </button>
            <div className="h-8 w-px bg-slate-200 mx-1"></div>
            <button className="flex items-center gap-3 hover:bg-slate-50 p-1.5 pr-2 rounded-lg transition-colors border border-transparent hover:border-slate-200">
              <div className="w-8 h-8 bg-blue-100 text-blue-700 rounded-full flex items-center justify-center font-semibold text-sm">
                AD
              </div>
              <div className="flex items-center gap-1">
                <span className="text-sm font-medium text-slate-700">Admin</span>
                <ChevronDown size={16} className="text-slate-400" />
              </div>
            </button>
          </div>
        </header>

        {/* Dashboard Content */}
        <main className="flex-1 overflow-y-auto p-6 lg:p-8">
          <div className="max-w-7xl mx-auto space-y-6">
            
            <div className="flex items-center justify-between">
              <div>
                <h1 className="text-2xl font-bold text-slate-900 tracking-tight">System Overview</h1>
                <p className="text-sm text-slate-500 mt-1">ARM-System API management dashboard</p>
              </div>
              <div className="flex items-center gap-3">
                <span className="flex items-center gap-2 text-sm font-medium text-emerald-600 bg-emerald-50 px-3 py-1.5 rounded-full border border-emerald-100">
                  <span className="relative flex h-2 w-2">
                    <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                  </span>
                  System Online
                </span>
                <button className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm shadow-blue-600/20 transition-all">
                  Generate Report
                </button>
              </div>
            </div>

            {/* Stats Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <StatCard 
                title="Total API Endpoints" 
                value="128" 
                trend="+12% this month" 
                icon={<Server className="text-blue-600" size={24} />} 
                color="blue"
              />
              <StatCard 
                title="Active Modules" 
                value="5" 
                trend="All systems operational" 
                icon={<Database className="text-indigo-600" size={24} />} 
                color="indigo"
              />
              <StatCard 
                title="API Requests" 
                value="1.2M" 
                trend="452 req/s average" 
                icon={<Activity className="text-emerald-600" size={24} />} 
                color="emerald"
              />
              <StatCard 
                title="Registered Accounts" 
                value="4,821" 
                trend="+84 this week" 
                icon={<Users className="text-amber-600" size={24} />} 
                color="amber"
              />
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
              {/* Modules Table */}
              <div className="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div className="p-5 border-b border-slate-200 flex items-center justify-between">
                  <h2 className="text-base font-semibold text-slate-900">Core API Modules</h2>
                  <button className="text-sm font-medium text-blue-600 hover:text-blue-700">View All</button>
                </div>
                <div className="overflow-x-auto">
                  <table className="w-full text-sm text-left">
                    <thead className="text-xs text-slate-500 uppercase bg-slate-50/50 border-b border-slate-200">
                      <tr>
                        <th className="px-6 py-3 font-medium">Module Name</th>
                        <th className="px-6 py-3 font-medium">Version</th>
                        <th className="px-6 py-3 font-medium">Status</th>
                        <th className="px-6 py-3 font-medium text-right">Endpoints</th>
                        <th className="px-6 py-3 font-medium text-right">Actions</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      <ModuleRow name="Voter" version="v1.4.2" status="active" endpoints={32} />
                      <ModuleRow name="Election" version="v1.2.0" status="active" endpoints={24} />
                      <ModuleRow name="Candidate" version="v1.1.5" status="active" endpoints={18} />
                      <ModuleRow name="College" version="v1.0.8" status="maintenance" endpoints={12} />
                      <ModuleRow name="API-Account" version="v2.0.1" status="active" endpoints={42} />
                    </tbody>
                  </table>
                </div>
              </div>

              {/* Activity Log */}
              <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                <div className="p-5 border-b border-slate-200">
                  <h2 className="text-base font-semibold text-slate-900">Recent Activity</h2>
                </div>
                <div className="p-5 flex-1 overflow-y-auto">
                  <div className="space-y-6">
                    <ActivityItem 
                      action="API Key Generated" 
                      details="New read-only key for AdminDashboard" 
                      time="10 mins ago" 
                      type="key"
                    />
                    <ActivityItem 
                      action="Module Updated" 
                      details="Voter module updated to v1.4.2" 
                      time="2 hours ago" 
                      type="system"
                    />
                    <ActivityItem 
                      action="Failed Auth Attempt" 
                      details="Invalid token from 192.168.1.45" 
                      time="4 hours ago" 
                      type="alert"
                    />
                    <ActivityItem 
                      action="Data Sync Completed" 
                      details="College module synchronized" 
                      time="5 hours ago" 
                      type="sync"
                    />
                    <ActivityItem 
                      action="New Account Registered" 
                      details="user_78291 created via API" 
                      time="Yesterday" 
                      type="user"
                    />
                  </div>
                </div>
                <div className="p-3 border-t border-slate-100 bg-slate-50/50 mt-auto">
                  <button className="w-full text-sm font-medium text-slate-600 hover:text-slate-900 text-center py-1.5">
                    View Full Logs
                  </button>
                </div>
              </div>
            </div>

          </div>
        </main>
      </div>
    </div>
  );
}

// Components

function NavItem({ icon, label, active, isOpen, className = "" }: { icon: React.ReactNode, label: string, active?: boolean, isOpen: boolean, className?: string }) {
  return (
    <a 
      href="#" 
      className={`flex items-center gap-3 px-3 py-2 rounded-lg transition-colors overflow-hidden whitespace-nowrap ${
        active 
          ? 'bg-blue-50 text-blue-700 font-medium' 
          : `text-slate-600 hover:bg-slate-100 hover:text-slate-900 ${className}`
      }`}
    >
      <div className={`flex-shrink-0 ${active ? 'text-blue-600' : ''}`}>
        {icon}
      </div>
      {isOpen && <span>{label}</span>}
    </a>
  );
}

function StatCard({ title, value, trend, icon, color }: { title: string, value: string, trend: string, icon: React.ReactNode, color: string }) {
  const colorMap: Record<string, string> = {
    blue: 'bg-blue-50',
    indigo: 'bg-indigo-50',
    emerald: 'bg-emerald-50',
    amber: 'bg-amber-50',
  };

  return (
    <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between relative overflow-hidden group">
      <div className="flex justify-between items-start mb-4">
        <div>
          <p className="text-sm font-medium text-slate-500 mb-1">{title}</p>
          <h3 className="text-3xl font-bold text-slate-900 tracking-tight">{value}</h3>
        </div>
        <div className={`p-3 rounded-lg ${colorMap[color]} transition-transform group-hover:scale-110`}>
          {icon}
        </div>
      </div>
      <p className="text-xs text-slate-500 font-medium">{trend}</p>
    </div>
  );
}

function ModuleRow({ name, version, status, endpoints }: { name: string, version: string, status: 'active' | 'maintenance', endpoints: number }) {
  return (
    <tr className="hover:bg-slate-50/80 transition-colors group">
      <td className="px-6 py-4 whitespace-nowrap">
        <div className="flex items-center gap-3">
          <div className="w-8 h-8 rounded bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-500">
            <Database size={16} />
          </div>
          <span className="font-medium text-slate-900">{name}</span>
        </div>
      </td>
      <td className="px-6 py-4 whitespace-nowrap text-slate-600 font-mono text-xs">{version}</td>
      <td className="px-6 py-4 whitespace-nowrap">
        {status === 'active' ? (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200/50">
            <span className="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
            Operational
          </span>
        ) : (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200/50">
            <span className="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
            Maintenance
          </span>
        )}
      </td>
      <td className="px-6 py-4 whitespace-nowrap text-right text-slate-600 font-medium">{endpoints}</td>
      <td className="px-6 py-4 whitespace-nowrap text-right">
        <button className="text-slate-400 hover:text-blue-600 transition-colors opacity-0 group-hover:opacity-100">
          <Settings size={18} />
        </button>
      </td>
    </tr>
  );
}

function ActivityItem({ action, details, time, type }: { action: string, details: string, time: string, type: 'key' | 'system' | 'alert' | 'sync' | 'user' }) {
  const icons = {
    key: <Key size={14} className="text-blue-600" />,
    system: <Server size={14} className="text-indigo-600" />,
    alert: <ShieldCheck size={14} className="text-red-600" />,
    sync: <Activity size={14} className="text-emerald-600" />,
    user: <Users size={14} className="text-amber-600" />,
  };

  const bgColors = {
    key: 'bg-blue-50 border-blue-100',
    system: 'bg-indigo-50 border-indigo-100',
    alert: 'bg-red-50 border-red-100',
    sync: 'bg-emerald-50 border-emerald-100',
    user: 'bg-amber-50 border-amber-100',
  };

  return (
    <div className="flex gap-4 relative">
      {/* Timeline line */}
      <div className="absolute left-4 top-8 bottom-[-24px] w-px bg-slate-100 last:hidden"></div>
      
      <div className={`relative z-10 w-8 h-8 rounded-full border flex items-center justify-center shrink-0 ${bgColors[type]}`}>
        {icons[type]}
      </div>
      
      <div className="pb-1">
        <p className="text-sm font-medium text-slate-900">{action}</p>
        <p className="text-xs text-slate-500 mt-0.5">{details}</p>
        <p className="text-xs text-slate-400 mt-1 font-medium">{time}</p>
      </div>
    </div>
  );
}
