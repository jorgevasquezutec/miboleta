import { useState } from "react";
import { Building2, Mail, Lock } from "lucide-react";
import { Button } from "../ui/button";
import { Input } from "../ui/input";
import { Label } from "../ui/label";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "../ui/card";
import { useTenant } from "../../contexts/TenantContext";

interface LoginViewProps {
  onLogin: (email: string, password: string) => void;
}

export function LoginView({ onLogin }: LoginViewProps) {
  const { tenant } = useTenant();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    onLogin(email, password);
  };

  // Generate gradient colors based on tenant branding
  const gradientFrom = tenant.branding.primaryColor;
  const gradientTo = tenant.branding.secondaryColor;

  return (
    <div 
      className="min-h-screen bg-gradient-to-br flex items-center justify-center p-4"
      style={{
        background: `linear-gradient(to bottom right, ${gradientFrom}, ${gradientTo})`,
      }}
    >
      <div className="w-full max-w-md">
        {/* Logo and Branding */}
        <div className="text-center mb-8">
          {tenant.branding.logoUrl ? (
            <div className="inline-flex items-center justify-center w-20 h-20 bg-white rounded-2xl shadow-lg mb-4 p-3">
              <img 
                src={tenant.branding.logoUrl} 
                alt={tenant.name}
                className="w-full h-full object-contain"
              />
            </div>
          ) : (
            <div className="inline-flex items-center justify-center w-20 h-20 bg-white rounded-2xl shadow-lg mb-4">
              <Building2 
                className="w-10 h-10" 
                style={{ color: tenant.branding.primaryColor }}
              />
            </div>
          )}
          <h1 className="text-white mb-2">{tenant.name}</h1>
          <p className="text-white opacity-90">Sistema de Gestión Documental</p>
        </div>

        {/* Login Card */}
        <Card className="shadow-2xl">
          <CardHeader>
            <CardTitle>Iniciar Sesión</CardTitle>
            <CardDescription>
              Ingresa tus credenciales para acceder a la plataforma
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-6">
              <div className="space-y-2">
                <Label htmlFor="email">Correo Electrónico</Label>
                <div className="relative">
                  <Mail className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-[#64748B]" />
                  <Input
                    id="email"
                    type="email"
                    placeholder="usuario@empresa.com"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    className="pl-10 h-11"
                    required
                  />
                </div>
              </div>

              <div className="space-y-2">
                <Label htmlFor="password">Contraseña</Label>
                <div className="relative">
                  <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-[#64748B]" />
                  <Input
                    id="password"
                    type="password"
                    placeholder="••••••••"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    className="pl-10 h-11"
                    required
                  />
                </div>
              </div>

              <div className="flex items-center justify-between">
                <label className="flex items-center gap-2 cursor-pointer">
                  <input type="checkbox" className="rounded" />
                  <span className="text-[#64748B]">Recordarme</span>
                </label>
                <Button 
                  variant="link" 
                  className="p-0"
                  style={{ color: tenant.branding.primaryColor }}
                >
                  ¿Olvidaste tu contraseña?
                </Button>
              </div>

              <Button
                type="submit"
                className="w-full h-11 text-white"
                style={{ 
                  backgroundColor: tenant.branding.primaryColor,
                }}
                onMouseEnter={(e) => {
                  e.currentTarget.style.backgroundColor = tenant.branding.secondaryColor;
                }}
                onMouseLeave={(e) => {
                  e.currentTarget.style.backgroundColor = tenant.branding.primaryColor;
                }}
              >
                Iniciar Sesión
              </Button>
            </form>

            <div className="mt-6 text-center">
              <p className="text-[#64748B]">
                ¿Tu empresa no está registrada?{" "}
                <Button 
                  variant="link" 
                  className="p-0"
                  style={{ color: tenant.branding.primaryColor }}
                >
                  Crear cuenta empresarial
                </Button>
              </p>
            </div>
          </CardContent>
        </Card>

        {/* Footer */}
        <p className="text-center text-white opacity-75 mt-8">
          © 2025 {tenant.name}. Todos los derechos reservados.
        </p>
      </div>
    </div>
  );
}
