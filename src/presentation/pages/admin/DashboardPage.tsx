import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useTenantAwareEffect, useDocumentTitle } from "@/presentation/hooks";
import { format, subDays } from "date-fns";
import {
  FileText,
  Users,
  CheckCircle,
  Clock,
  Upload,
  Download,
  RefreshCw,
  Calendar,
  LogIn,
  FileSignature,
  Trash2,
  Eye,
  UserPlus,
  Building2,
  AlertTriangle,
} from "lucide-react";
import { StatsCard } from "@/presentation/components/common";
import { Button } from "@/presentation/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/presentation/components/ui/card";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/presentation/components/ui/table";
import { Badge } from "@/presentation/components/ui/badge";
import { Skeleton } from "@/presentation/components/ui/skeleton";
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell } from "recharts";
import { useReportsStore } from "@/presentation/stores/reportsStore";
import { useAuthStore } from "@/presentation/stores/authStore";
import { TenantAutocompleteSelector } from "@/presentation/components/shared/TenantAutocompleteSelector";
import { DateRangePicker, DateRange } from "@/presentation/components/ui/date-range-picker";
import { Tenant } from "@/core/domain/entities/Tenant";

// Map action categories to icons
const getActionIcon = (action: string) => {
  if (action.startsWith('user.login')) return <LogIn className="w-4 h-4 text-blue-500" />;
  if (action.startsWith('user.logout')) return <LogIn className="w-4 h-4 text-gray-500" />;
  if (action.startsWith('user.created') || action.startsWith('user.updated')) return <UserPlus className="w-4 h-4 text-green-500" />;
  if (action.startsWith('document.signed')) return <FileSignature className="w-4 h-4 text-green-500" />;
  if (action.startsWith('document.viewed')) return <Eye className="w-4 h-4 text-blue-500" />;
  if (action.startsWith('document.uploaded')) return <Upload className="w-4 h-4 text-purple-500" />;
  if (action.startsWith('document.deleted')) return <Trash2 className="w-4 h-4 text-red-500" />;
  if (action.startsWith('vacation.')) return <Calendar className="w-4 h-4 text-amber-500" />;
  if (action.startsWith('tenant.')) return <Building2 className="w-4 h-4 text-indigo-500" />;
  return <FileText className="w-4 h-4 text-gray-500" />;
};

// Map action categories to badge variants
const getActionBadge = (category: string) => {
  switch (category) {
    case 'user':
      return <Badge className="bg-blue-100 text-blue-800 hover:bg-blue-100">Usuario</Badge>;
    case 'document':
      return <Badge className="bg-purple-100 text-purple-800 hover:bg-purple-100">Documento</Badge>;
    case 'vacation':
      return <Badge className="bg-amber-100 text-amber-800 hover:bg-amber-100">Vacaciones</Badge>;
    case 'tenant':
      return <Badge className="bg-indigo-100 text-indigo-800 hover:bg-indigo-100">Organización</Badge>;
    default:
      return <Badge className="bg-gray-100 text-gray-800 hover:bg-gray-100">Sistema</Badge>;
  }
};

export function AdminDashboardView() {
  const navigate = useNavigate();
  useDocumentTitle('Dashboard');
  const { currentTenant, user } = useAuthStore();
  const {
    documentStats,
    vacationStats,
    userStats,
    recentActivity,
    isLoadingDashboard,
    error,
    fetchDashboardStats,
    exportDocuments,
    isExporting,
  } = useReportsStore();

  // State for root users to select a tenant
  const [selectedTenantId, setSelectedTenantId] = useState<string | null>(null);
  const [selectedTenant, setSelectedTenant] = useState<Tenant | null>(null);

  // Date range state - default last 30 days (only used when a specific range is chosen)
  const [dateRange, setDateRange] = useState<DateRange>({
    from: subDays(new Date(), 30),
    to: new Date(),
  });
  // "Todo el tiempo" by default: cards show the organization's real totals on load.
  const [allTime, setAllTime] = useState(true);

  const isRoot = user?.role === 'root';

  // Get current tenant ID - for root use selected, for admin use currentTenant
  const tenantId = isRoot
    ? (selectedTenantId ? Number(selectedTenantId) : undefined)
    : (currentTenant?.id ? Number(currentTenant.id) : undefined);

  // Format dates for API. When "Todo el tiempo" is active, send no range (real totals).
  const startDate = allTime || !dateRange.from ? undefined : format(dateRange.from, 'yyyy-MM-dd');
  const endDate = allTime || !dateRange.to ? undefined : format(dateRange.to, 'yyyy-MM-dd');

  // Fetch dashboard data on mount and when filters change
  // ✅ MIGRATED: Now reacts automatically to tenant filter changes
  useTenantAwareEffect(() => {
    fetchDashboardStats(tenantId, startDate, endDate);
  }, [tenantId, startDate, endDate, fetchDashboardStats]);

  // Prepare chart data with fallbacks
  const documentsChartData = documentStats?.by_month ?? [];
  const statusDistributionData = documentStats?.status_distribution ?? [];

  const handleRefresh = () => {
    fetchDashboardStats(tenantId, startDate, endDate);
  };

  const handleExport = async () => {
    await exportDocuments({
      tenant_id: tenantId,
      start_date: startDate,
      end_date: endDate,
    });
  };

  const handleTenantChange = (id: string | null) => {
    setSelectedTenantId(id);
    if (!id) {
      setSelectedTenant(null);
    }
  };

  const handleDateRangeChange = (values: { range: DateRange }) => {
    setAllTime(false);
    setDateRange(values.range);
  };

  // Loading skeleton for stats cards
  const StatsCardSkeleton = () => (
    <Card className="relative overflow-hidden">
      <CardContent className="p-6">
        <div className="flex items-center justify-between">
          <div className="space-y-2">
            <Skeleton className="h-4 w-24" />
            <Skeleton className="h-8 w-16" />
            <Skeleton className="h-3 w-20" />
          </div>
          <Skeleton className="h-12 w-12 rounded-full" />
        </div>
      </CardContent>
    </Card>
  );

  return (
    <div className="space-y-6 overflow-x-hidden">
      {/* Header */}
      <div className="flex flex-col gap-4">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-xl sm:text-2xl font-bold">Panel de Administración</h1>
            <p className="text-[#64748B] text-sm sm:text-base">
              {isRoot
                ? (selectedTenantId ? `Estadísticas de: ${selectedTenant?.name || 'Organización seleccionada'}` : 'Vista general de todas las organizaciones')
                : `Estadísticas de ${currentTenant?.name || 'tu organización'}`}
            </p>
          </div>
          <div className="flex gap-2 sm:gap-3 flex-wrap">
            <Button
              variant="outline"
              className="h-9 sm:h-10 px-3 sm:px-4 gap-2"
              onClick={handleRefresh}
              disabled={isLoadingDashboard}
            >
              <RefreshCw className={`w-4 h-4 ${isLoadingDashboard ? 'animate-spin' : ''}`} />
              <span className="hidden xs:inline">Actualizar</span>
            </Button>
            <Button
              variant="outline"
              className="h-9 sm:h-10 px-3 sm:px-4 gap-2"
              onClick={handleExport}
              disabled={isExporting}
            >
              <Download className="w-4 h-4" />
              <span className="hidden xs:inline">Exportar</span>
            </Button>
          </div>
        </div>

        {/* Tenant selector for root users */}
        {isRoot && (
          <div className="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3">
            <span className="text-sm text-gray-500">Filtrar por organización:</span>
            <div className="w-full sm:w-80">
              <TenantAutocompleteSelector
                value={selectedTenantId}
                onChange={handleTenantChange}
                selectedTenant={selectedTenant}
                placeholder="Todas las organizaciones"
              />
            </div>
          </div>
        )
        }

        {/* Date range filter */}
        <div className="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3">
          <span className="text-sm text-gray-500">Período:</span>
          <Button
            variant={allTime ? "default" : "outline"}
            size="sm"
            onClick={() => setAllTime(true)}
          >
            Todo el tiempo
          </Button>
          <DateRangePicker
            initialDateFrom={dateRange.from}
            initialDateTo={dateRange.to}
            onUpdate={handleDateRangeChange}
            showCompare={false}
            align="start"
            compact
          />
        </div>
      </div >

      {/* Error message */}
      {
        error && (
          <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
            {error}
          </div>
        )
      }

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
        {isLoadingDashboard ? (
          <>
            <StatsCardSkeleton />
            <StatsCardSkeleton />
            <StatsCardSkeleton />
            <StatsCardSkeleton />
            <StatsCardSkeleton />
          </>
        ) : (
          <>
            <StatsCard
              title="Total Documentos"
              value={documentStats?.total?.toLocaleString() ?? '0'}
              icon={FileText}
              color="#2563EB"
            />
            <StatsCard
              title="Usuarios Activos"
              value={userStats?.active?.toLocaleString() ?? '0'}
              icon={Users}
              color="#10B981"
            />
            <StatsCard
              title="Documentos Firmados"
              value={documentStats?.signed?.toLocaleString() ?? '0'}
              icon={CheckCircle}
              color="#10B981"
            />
            <StatsCard
              title="Pendientes"
              value={documentStats?.pending?.toLocaleString() ?? '0'}
              icon={Clock}
              color="#F59E0B"
            />
            <StatsCard
              title="Huérfanos"
              value={documentStats?.orphan?.toLocaleString() ?? '0'}
              icon={AlertTriangle}
              color="#F97316"
            />
          </>
        )}
      </div>

      {/* Vacation Stats Row */}
      {
        vacationStats && (
          <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
            <Card className="col-span-full md:col-span-4">
              <CardHeader className="pb-2">
                <CardTitle className="text-base font-medium flex items-center gap-2">
                  <Calendar className="w-4 h-4" />
                  Vacaciones {vacationStats.current_year}
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                  <div className="text-center p-3 bg-amber-50 rounded-lg">
                    <p className="text-2xl font-bold text-amber-600">{vacationStats.pending}</p>
                    <p className="text-sm text-amber-700">Pendientes</p>
                  </div>
                  <div className="text-center p-3 bg-green-50 rounded-lg">
                    <p className="text-2xl font-bold text-green-600">{vacationStats.approved}</p>
                    <p className="text-sm text-green-700">Aprobadas</p>
                  </div>
                  <div className="text-center p-3 bg-red-50 rounded-lg">
                    <p className="text-2xl font-bold text-red-600">{vacationStats.rejected}</p>
                    <p className="text-sm text-red-700">Rechazadas</p>
                  </div>
                  <div className="text-center p-3 bg-blue-50 rounded-lg">
                    <p className="text-2xl font-bold text-blue-600">{vacationStats.total_days_used}</p>
                    <p className="text-sm text-blue-700">Días Usados</p>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        )
      }

      {/* Charts Row */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Documents Trend */}
        <Card>
          <CardHeader>
            <CardTitle>Documentos Cargados (Últimos 6 meses)</CardTitle>
          </CardHeader>
          <CardContent>
            {isLoadingDashboard ? (
              <div className="h-[300px] flex items-center justify-center">
                <RefreshCw className="w-8 h-8 animate-spin text-gray-400" />
              </div>
            ) : documentsChartData.length > 0 ? (
              <ResponsiveContainer width="100%" height={300}>
                <BarChart data={documentsChartData}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#F1F5F9" />
                  <XAxis dataKey="name" stroke="#64748B" />
                  <YAxis stroke="#64748B" />
                  <Tooltip
                    contentStyle={{
                      backgroundColor: '#fff',
                      border: '1px solid #E2E8F0',
                      borderRadius: '8px'
                    }}
                  />
                  <Bar dataKey="value" fill="#2563EB" radius={[8, 8, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            ) : (
              <div className="h-[300px] flex items-center justify-center text-gray-500">
                No hay datos disponibles
              </div>
            )}
          </CardContent>
        </Card>

        {/* Status Distribution */}
        <Card>
          <CardHeader>
            <CardTitle>Estado de Documentos</CardTitle>
          </CardHeader>
          <CardContent>
            {isLoadingDashboard ? (
              <div className="h-[300px] flex items-center justify-center">
                <RefreshCw className="w-8 h-8 animate-spin text-gray-400" />
              </div>
            ) : statusDistributionData.length > 0 && statusDistributionData.some(d => d.value > 0) ? (
              <div className="flex items-center justify-center">
                <ResponsiveContainer width="100%" height={300}>
                  <PieChart>
                    <Pie
                      data={statusDistributionData}
                      cx="50%"
                      cy="50%"
                      labelLine={false}
                      label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
                      outerRadius={100}
                      fill="#8884d8"
                      dataKey="value"
                    >
                      {statusDistributionData.map((entry, index) => (
                        <Cell key={`cell-${index}`} fill={entry.color} />
                      ))}
                    </Pie>
                    <Tooltip />
                  </PieChart>
                </ResponsiveContainer>
              </div>
            ) : (
              <div className="h-[300px] flex items-center justify-center text-gray-500">
                No hay datos disponibles
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Recent Activity */}
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>Actividad Reciente</CardTitle>
          <Button variant="outline" size="sm" onClick={() => navigate("/audit-logs")}>
            Ver todo
          </Button>
        </CardHeader>
        <CardContent>
          {isLoadingDashboard ? (
            <div className="space-y-3">
              {[1, 2, 3, 4, 5].map((i) => (
                <div key={i} className="flex items-center gap-4">
                  <Skeleton className="h-8 w-8 rounded-full" />
                  <div className="flex-1 space-y-2">
                    <Skeleton className="h-4 w-3/4" />
                    <Skeleton className="h-3 w-1/2" />
                  </div>
                </div>
              ))}
            </div>
          ) : recentActivity.length > 0 ? (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-12"></TableHead>
                  <TableHead>Usuario</TableHead>
                  <TableHead>Acción</TableHead>
                  <TableHead>Tiempo</TableHead>
                  <TableHead>Tipo</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {recentActivity.map((activity) => (
                  <TableRow key={activity.id}>
                    <TableCell>
                      {getActionIcon(activity.action)}
                    </TableCell>
                    <TableCell className="font-medium">{activity.user}</TableCell>
                    <TableCell>{activity.description}</TableCell>
                    <TableCell className="text-[#64748B]">{activity.time_ago}</TableCell>
                    <TableCell>
                      {getActionBadge(activity.category)}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          ) : (
            <div className="py-8 text-center text-gray-500">
              <FileText className="w-12 h-12 mx-auto mb-3 text-gray-300" />
              <p>No hay actividad reciente registrada</p>
              <p className="text-sm">Las acciones de usuarios aparecerán aquí</p>
            </div>
          )}
        </CardContent>
      </Card>
    </div >
  );
}

export default AdminDashboardView;
