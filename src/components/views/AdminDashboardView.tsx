import {
  FileText,
  Users,
  CheckCircle,
  Clock,
  TrendingUp,
  Upload,
  Settings,
  FileBarChart,
} from "lucide-react";
import { StatsCard } from "../StatsCard";
import { DocumentCard } from "../DocumentCard";
import { Button } from "../ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "../ui/card";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "../ui/table";
import { Badge } from "../ui/badge";
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell } from "recharts";

interface AdminDashboardViewProps {
  onNavigate: (view: string) => void;
}

const documentsData = [
  { name: "Ene", value: 45 },
  { name: "Feb", value: 52 },
  { name: "Mar", value: 61 },
  { name: "Abr", value: 58 },
  { name: "May", value: 70 },
  { name: "Jun", value: 68 },
];

const statusData = [
  { name: "Firmados", value: 156, color: "#10B981" },
  { name: "Pendientes", value: 24, color: "#F59E0B" },
  { name: "Vencidos", value: 8, color: "#EF4444" },
];

const recentActivity = [
  { user: "María García", action: "Firmó contrato laboral", time: "Hace 5 min", status: "success" },
  { user: "Carlos Ruiz", action: "Visualizó boleta de pago", time: "Hace 12 min", status: "info" },
  { user: "Ana Martínez", action: "Documento pendiente por firmar", time: "Hace 1 hora", status: "warning" },
  { user: "Luis Torres", action: "Descargó certificado", time: "Hace 2 horas", status: "success" },
  { user: "Sofia López", action: "Documento vencido", time: "Hace 3 horas", status: "error" },
];

export function AdminDashboardView({ onNavigate }: AdminDashboardViewProps) {
  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1>Panel de Administración</h1>
          <p className="text-[#64748B]">
            Bienvenido de vuelta, aquí está el resumen de tu plataforma
          </p>
        </div>
        <div className="flex gap-3">
          <Button
            variant="outline"
            className="gap-2"
            onClick={() => onNavigate("reports")}
          >
            <FileBarChart className="w-4 h-4" />
            Reportes
          </Button>
          <Button
            className="gap-2 bg-[#2563EB] hover:bg-[#1E40AF]"
            onClick={() => onNavigate("upload")}
          >
            <Upload className="w-4 h-4" />
            Cargar Documentos
          </Button>
        </div>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <StatsCard
          title="Total Documentos"
          value="1,247"
          icon={FileText}
          trend={{ value: "12% este mes", isPositive: true }}
          color="#2563EB"
        />
        <StatsCard
          title="Usuarios Activos"
          value="342"
          icon={Users}
          trend={{ value: "8% este mes", isPositive: true }}
          color="#10B981"
        />
        <StatsCard
          title="Documentos Firmados"
          value="156"
          icon={CheckCircle}
          trend={{ value: "5% este mes", isPositive: true }}
          color="#10B981"
        />
        <StatsCard
          title="Pendientes"
          value="24"
          icon={Clock}
          trend={{ value: "3% este mes", isPositive: false }}
          color="#F59E0B"
        />
      </div>

      {/* Charts Row */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Documents Trend */}
        <Card>
          <CardHeader>
            <CardTitle>Documentos Cargados</CardTitle>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={300}>
              <BarChart data={documentsData}>
                <CartesianGrid strokeDasharray="3 3" stroke="#F1F5F9" />
                <XAxis dataKey="name" stroke="#64748B" />
                <YAxis stroke="#64748B" />
                <Tooltip />
                <Bar dataKey="value" fill="#2563EB" radius={[8, 8, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>

        {/* Status Distribution */}
        <Card>
          <CardHeader>
            <CardTitle>Estado de Documentos</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="flex items-center justify-center">
              <ResponsiveContainer width="100%" height={300}>
                <PieChart>
                  <Pie
                    data={statusData}
                    cx="50%"
                    cy="50%"
                    labelLine={false}
                    label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
                    outerRadius={100}
                    fill="#8884d8"
                    dataKey="value"
                  >
                    {statusData.map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={entry.color} />
                    ))}
                  </Pie>
                  <Tooltip />
                </PieChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Recent Activity */}
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>Actividad Reciente</CardTitle>
          <Button variant="outline" size="sm">
            Ver todo
          </Button>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Usuario</TableHead>
                <TableHead>Acción</TableHead>
                <TableHead>Tiempo</TableHead>
                <TableHead>Estado</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {recentActivity.map((activity, index) => (
                <TableRow key={index}>
                  <TableCell>{activity.user}</TableCell>
                  <TableCell>{activity.action}</TableCell>
                  <TableCell className="text-[#64748B]">{activity.time}</TableCell>
                  <TableCell>
                    {activity.status === "success" && (
                      <Badge className="bg-[#10B981] text-white">Completado</Badge>
                    )}
                    {activity.status === "warning" && (
                      <Badge className="bg-[#F59E0B] text-white">Pendiente</Badge>
                    )}
                    {activity.status === "error" && (
                      <Badge className="bg-[#EF4444] text-white">Vencido</Badge>
                    )}
                    {activity.status === "info" && (
                      <Badge className="bg-[#3B82F6] text-white">Información</Badge>
                    )}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      {/* Quick Actions */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <Card className="hover:shadow-md transition-shadow cursor-pointer" onClick={() => onNavigate("upload")}>
          <CardContent className="p-6 flex items-center gap-4">
            <div className="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
              <Upload className="w-6 h-6 text-[#2563EB]" />
            </div>
            <div>
              <h3>Cargar Documentos</h3>
              <p className="text-[#64748B]">Carga masiva de archivos</p>
            </div>
          </CardContent>
        </Card>

        <Card className="hover:shadow-md transition-shadow cursor-pointer" onClick={() => onNavigate("users")}>
          <CardContent className="p-6 flex items-center gap-4">
            <div className="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
              <Users className="w-6 h-6 text-[#10B981]" />
            </div>
            <div>
              <h3>Gestionar Usuarios</h3>
              <p className="text-[#64748B]">Administrar empleados</p>
            </div>
          </CardContent>
        </Card>

        <Card className="hover:shadow-md transition-shadow cursor-pointer" onClick={() => onNavigate("settings")}>
          <CardContent className="p-6 flex items-center gap-4">
            <div className="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
              <Settings className="w-6 h-6 text-[#8B5CF6]" />
            </div>
            <div>
              <h3>Configuración</h3>
              <p className="text-[#64748B]">Ajustes de empresa</p>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
