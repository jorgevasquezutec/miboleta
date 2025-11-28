import { useState } from "react";
import { Building2, Mail, Lock } from "lucide-react";
import { Button } from "@/presentation/components/ui/button";
import { Input } from "@/presentation/components/ui/input";
import { Label } from "@/presentation/components/ui/label";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/presentation/components/ui/card";

interface LoginViewProps {
  onLogin: (email: string, password: string) => void;
}

export default function LoginView({ onLogin }: LoginViewProps) {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    onLogin(email, password);
  };

  // Mock tenant branding
  const gradientFrom = "#2563EB";
  const gradientTo = "#1E40AF";
  const primaryColor = "#2563EB";
  const tenantName = "MiBoleta";

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
          <div className="inline-flex items-center justify-center w-20 h-20 bg-white rounded-2xl shadow-lg mb-4">
            <Building2 
              className="w-10 h-10" 
              style={{ color: primaryColor }}
            />
          </div>
          <h1 className="text-white mb-2">{tenantName}</h1>
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
                  style={{ color: primaryColor }}
                >
                  ¿Olvidaste tu contraseña?
                </Button>
              </div>

              <Button
                type="submit"
                className="w-full h-11 text-white"
                style={{ 
                  backgroundColor: primaryColor,
                }}
                onMouseEnter={(e: React.MouseEvent<HTMLButtonElement>) => {
                  e.currentTarget.style.backgroundColor = gradientTo;
                }}
                onMouseLeave={(e: React.MouseEvent<HTMLButtonElement>) => {
                  e.currentTarget.style.backgroundColor = primaryColor;
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
                  style={{ color: primaryColor }}
                >
                  Crear cuenta empresarial
                </Button>
              </p>
            </div>

            {/* Development Mode - Show Test Credentials */}
            {import.meta.env.DEV && (
              <div className="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <p className="text-sm font-semibold text-blue-900 mb-2">🔧 Credenciales de prueba:</p>
                <div className="text-xs text-blue-800 space-y-1">
                  <p><strong>Admin Plataforma:</strong> platform@miboleta.com</p>
                  <p><strong>Admin Empresa:</strong> carlos@empresa1.com</p>
                  <p><strong>Empleado:</strong> maria@empresa1.com</p>
                  <p className="text-blue-600 mt-2">Contraseña: cualquiera (en desarrollo)</p>
                </div>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Footer */}
        <p className="text-center text-white opacity-75 mt-8">
          © 2025 {tenantName}. Todos los derechos reservados.
        </p>
      </div>
    </div>
  );
}
