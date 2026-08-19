import { Eye, LogOut } from "lucide-react";
import { useAuthStore } from "@/presentation/stores/authStore";
import { Button } from "@/presentation/components/ui/button";
import { showApiError } from "@/presentation/utils/showApiError";

/**
 * Alto fijo de la franja, en px. RootLayout lo usa para correr Navbar y
 * Sidebar hacia abajo cuando el banner está montado (ver RootLayout.tsx) —
 * cambiarlo aquí sin actualizar allá los desalinea.
 */
export const IMPERSONATION_BANNER_HEIGHT = 40;

/**
 * Aviso PERMANENTE de que la sesión activa es una impersonation (root
 * operando como otro usuario, ver CONTRATO-IMPERSONATION del backend).
 *
 * A propósito NO es discreto: `user_id` de todo (documentos, vacaciones,
 * auditoría) sigue siendo el del empleado impersonado, así que quien
 * accione algo aquí lo hace a nombre de esa persona. Un color de alerta y
 * una franja fija en TODAS las páginas (se monta una sola vez en
 * RootLayout, no en cada página suelta) es la forma de que nadie confunda
 * esto con su propia sesión.
 *
 * Estilo con `style` en vez de clases de color: mismo criterio que los
 * badges de UsersListPage (getRoleBadgeStyle) para no depender de cómo
 * theme/tailwind-merge resuelvan la precedencia de clases.
 */
export function ImpersonationBanner() {
  const impersonator = useAuthStore((s) => s.impersonator);
  const user = useAuthStore((s) => s.user);
  const isLoading = useAuthStore((s) => s.isLoading);
  const leaveImpersonation = useAuthStore((s) => s.leaveImpersonation);

  if (!impersonator) return null;

  const targetName =
    user?.full_name || `${user?.name ?? ""} ${user?.last_name ?? ""}`.trim() || user?.email || "este usuario";

  const handleLeave = async () => {
    try {
      await leaveImpersonation();
      // Nada más que hacer aquí: leaveImpersonation() ya dispara la recarga
      // dura (window.location.href) en cuanto el backend confirma.
    } catch (error) {
      showApiError(error, "No se pudo volver a tu cuenta");
    }
  };

  return (
    <div
      role="alert"
      className="fixed top-0 left-0 right-0 z-[60] flex items-center justify-center gap-2 sm:gap-3 px-3 sm:px-4 shadow-md"
      style={{ height: IMPERSONATION_BANNER_HEIGHT, backgroundColor: "#F59E0B", color: "#451A03" }}
    >
      <Eye className="h-4 w-4 flex-shrink-0" />
      <span className="truncate text-xs sm:text-sm font-medium">
        Estás viendo la aplicación como <strong>{targetName}</strong>
        <span className="hidden sm:inline"> · conectado como {impersonator.full_name}</span>
      </span>
      <Button
        type="button"
        size="sm"
        onClick={handleLeave}
        disabled={isLoading}
        className="h-6 sm:h-7 gap-1.5 px-2 sm:px-3 flex-shrink-0"
        style={{ backgroundColor: "#451A03", color: "#FEF3C7" }}
      >
        <LogOut className="h-3.5 w-3.5" />
        <span className="hidden xs:inline">Volver a mi cuenta</span>
      </Button>
    </div>
  );
}

export default ImpersonationBanner;
