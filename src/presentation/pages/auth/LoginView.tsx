import { useState } from "react";
import { useDocumentTitle } from "@/presentation/hooks";
import { useNavigate, Link } from "react-router-dom";
import { Building2, Mail, Lock, Loader2 } from "lucide-react";
import { Button } from "@/presentation/components/ui/button";
import { Input } from "@/presentation/components/ui/input";
import { Label } from "@/presentation/components/ui/label";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/presentation/components/ui/card";
import { useAuth } from "@/presentation/hooks/useAuth";
import { useAuthStore } from "@/presentation/stores/authStore";
import { toast } from "sonner";

export default function LoginView() {
  useDocumentTitle('Iniciar Sesión');
  const navigate = useNavigate();
  const { login, isLoading } = useAuth();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    try {
      await login(email, password);

      // Check if user needs to change password
      const currentUser = useAuthStore.getState().user;
      if (currentUser?.must_change_password) {
        toast.info("Por favor, establece una nueva contraseña");
        navigate("/force-change-password");
        return;
      }

      toast.success("¡Bienvenido!");
      navigate("/");
    } catch (error) {
      toast.error(error instanceof Error ? error.message : "Error al iniciar sesión");
    }
  };

  // Quick login helper
  const quickLogin = (testEmail: string) => {
    setEmail(testEmail);
    setPassword("password");
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
                    disabled={isLoading}
                  />
                </div>
              </div>

              <div className="space-y-2">
                <div className="flex justify-between items-center">
                  <Label htmlFor="password">Contraseña</Label>
                  <Link
                    to="/forgot-password"
                    className="text-sm text-blue-600 hover:underline"
                  >
                    ¿Olvidaste tu contraseña?
                  </Link>
                </div>
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
                    disabled={isLoading}
                  />
                </div>
              </div>

              <Button
                type="submit"
                className="w-full h-11 text-white"
                style={{
                  backgroundColor: primaryColor,
                }}
                disabled={isLoading}
              >
                {isLoading ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    Iniciando sesión...
                  </>
                ) : (
                  'Iniciar Sesión'
                )}
              </Button>
            </form>

            {/* Development Mode - Show Test Credentials */}
            {(import.meta.env.DEV || import.meta.env.VITE_SHOW_TEST_USERS) && (
              <div className="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <p className="text-sm font-semibold text-blue-900 mb-3">🔧 Usuarios de prueba:</p>
                <div className="space-y-2">
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="w-full justify-start text-left"
                    onClick={() => quickLogin('root@miboleta.com')}
                  >
                    <span className="text-xs">
                      <strong>Root Admin</strong> - root@miboleta.com
                    </span>
                  </Button>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="w-full justify-start text-left"
                    onClick={() => quickLogin('admin@corporacionabc.com')}
                  >
                    <span className="text-xs">
                      <strong>Admin ABC</strong> - admin@corporacionabc.com
                    </span>
                  </Button>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="w-full justify-start text-left"
                    onClick={() => quickLogin('juan.perez@corporacionabc.com')}
                  >
                    <span className="text-xs">
                      <strong>Cliente ABC</strong> - juan.perez@corporacionabc.com
                    </span>
                  </Button>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="w-full justify-start text-left"
                    onClick={() => quickLogin('ana.torres@email.com')}
                  >
                    <span className="text-xs">
                      <strong>Multi-tenant</strong> - ana.torres@email.com
                    </span>
                  </Button>
                  <p className="text-xs text-blue-600 mt-2">Contraseña para todos: <strong>password</strong></p>
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
