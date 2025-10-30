import { Building2, Shield, Palette, Users, Database, Zap } from "lucide-react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "./ui/card";

export function MultitenantInfo() {
  const features = [
    {
      icon: Building2,
      title: "Espacios Virtuales Independientes",
      description:
        "Cada empresa opera en un entorno lógico completamente aislado, garantizando privacidad total de datos y configuraciones.",
      color: "#2563EB",
    },
    {
      icon: Palette,
      title: "Branding Personalizado",
      description:
        "Las organizaciones pueden configurar colores institucionales, logotipo, favicon y nombre comercial, reforzando su identidad corporativa.",
      color: "#10B981",
    },
    {
      icon: Shield,
      title: "Seguridad y Aislamiento",
      description:
        "Arquitectura que garantiza que los datos de cada empresa estén completamente separados y protegidos de accesos no autorizados.",
      color: "#F59E0B",
    },
    {
      icon: Users,
      title: "Jerarquía de Usuarios",
      description:
        "Roles diferenciados: Administrador de empresa (acceso completo) y Empleado (acceso restringido a sus documentos).",
      color: "#8B5CF6",
    },
    {
      icon: Database,
      title: "Gestión Independiente",
      description:
        "Cada empresa administra sus propios usuarios, documentos, configuraciones y políticas de forma totalmente autónoma.",
      color: "#EC4899",
    },
    {
      icon: Zap,
      title: "Escalabilidad",
      description:
        "Agregar nuevas empresas sin afectar el rendimiento o la operación de las organizaciones existentes en la plataforma.",
      color: "#06B6D4",
    },
  ];

  return (
    <div className="space-y-6">
      <div className="text-center space-y-3">
        <h2 className="text-[#1E40AF]">Arquitectura Multitenant</h2>
        <p className="text-[#64748B] max-w-3xl mx-auto">
          MiBoleta está diseñado bajo una arquitectura multitenant de última generación
          que permite crear espacios virtuales independientes para cada empresa u
          organización, garantizando privacidad, personalización y escalabilidad.
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {features.map((feature, index) => {
          const Icon = feature.icon;
          return (
            <Card key={index} className="hover:shadow-lg transition-shadow">
              <CardHeader>
                <div
                  className="w-12 h-12 rounded-lg flex items-center justify-center mb-3"
                  style={{ backgroundColor: `${feature.color}15` }}
                >
                  <Icon className="w-6 h-6" style={{ color: feature.color }} />
                </div>
                <CardTitle className="text-[#1E40AF]">{feature.title}</CardTitle>
              </CardHeader>
              <CardContent>
                <CardDescription>{feature.description}</CardDescription>
              </CardContent>
            </Card>
          );
        })}
      </div>

      <Card className="border-[#2563EB] border-2">
        <CardHeader>
          <CardTitle className="text-[#2563EB]">Roles y Permisos</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-3">
            <div className="flex items-start gap-3">
              <div className="w-8 h-8 rounded-full bg-[#2563EB] flex items-center justify-center flex-shrink-0">
                <span className="text-white">1</span>
              </div>
              <div>
                <h4 className="text-[#1E40AF]">Administrador de Empresa</h4>
                <p className="text-[#64748B]">
                  Acceso completo a la gestión de documentos, carga masiva, organización,
                  firma digital, seguimiento de archivos, gestión de usuarios y
                  configuración de branding.
                </p>
              </div>
            </div>

            <div className="flex items-start gap-3">
              <div className="w-8 h-8 rounded-full bg-[#10B981] flex items-center justify-center flex-shrink-0">
                <span className="text-white">2</span>
              </div>
              <div>
                <h4 className="text-[#1E40AF]">Empleado</h4>
                <p className="text-[#64748B]">
                  Acceso restringido limitado a la visualización y firma de sus propios
                  documentos. Esta segmentación asegura que cada usuario interactúe
                  únicamente con la información que le corresponde.
                </p>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
