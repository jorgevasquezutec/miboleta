import { Outlet, useNavigate, useLocation } from "react-router-dom";
import { useAuthStore } from "@/presentation/stores";
import { Navbar } from "@/presentation/components/layout";
import { useState, useEffect, useMemo } from "react";
import {
  Users,
  FileText,
  Building2,
  LayoutDashboard,
  FileStack,
  Calendar,
  ChevronDown,
  ChevronRight,
  LucideIcon,
  History,
  ClipboardList,
  Upload,
  X,
  FileKey,
  Settings,
  ShieldCheck,
} from "lucide-react";
import { APP_VERSION, NAV_LABELS, ROUTES } from "@/shared/constants";
import { cn } from "@/presentation/components/ui/utils";

interface SidebarProps {
  isExpanded: boolean;
  isMobile: boolean;
  onClose?: () => void;
  onNavigate?: () => void;
}

interface NavItem {
  label: string;
  path: string;
  icon: LucideIcon;
  children?: NavItem[];
  /**
   * Abilities de la Matriz de Accesos que habilitan este ítem (basta con UNA).
   * El menú se filtra con ellas contra el mismo mapa que usa el backend para
   * autorizar, de modo que no puede mostrar algo que luego devuelva 403.
   */
  abilities?: string[];
}

interface CollapsibleSectionProps {
  item: NavItem;
  isExpanded: boolean;
  isActive: (path: string) => boolean;
  navigate: (path: string) => void;
  primaryColor: string;
  secondaryColor: string;
  openSections: string[];
  toggleSection: (label: string) => void;
  onNavigate?: () => void;
}

function CollapsibleSection({
  item,
  isExpanded,
  isActive,
  navigate,
  primaryColor,
  secondaryColor,
  openSections,
  toggleSection,
  onNavigate,
}: CollapsibleSectionProps) {
  const hasChildren = item.children && item.children.length > 0;
  const Icon = item.icon;

  // Check if any child is active (for visual styling only)
  const isChildActive = hasChildren && item.children?.some(child => isActive(child.path));
  // Section is open only based on openSections state (controlled by toggle)
  const isSectionOpen = openSections.includes(item.label);

  const handleClick = () => {
    if (hasChildren) {
      toggleSection(item.label);
    } else {
      navigate(item.path);
      onNavigate?.();
    }
  };

  if (!hasChildren) {
    // Regular item without children
    const isItemActive = isActive(item.path);
    return (
      <button
        type="button"
        onClick={() => {
          navigate(item.path);
          onNavigate?.();
        }}
        className={cn(
          "w-full flex items-center gap-3 py-3 rounded-lg transition-colors",
          isExpanded ? "justify-start px-4" : "justify-center px-2",
          isItemActive ? "text-white" : "hover:bg-[#F1F5F9]"
        )}
        style={{
          backgroundColor: isItemActive ? primaryColor : undefined,
          color: isItemActive ? "#FFFFFF" : secondaryColor,
        }}
        title={item.label}
      >
        <Icon className="w-5 h-5 flex-shrink-0" />
        {isExpanded && <span className="text-sm font-medium">{item.label}</span>}
      </button>
    );
  }

  // Collapsible section with children
  return (
    <div className="space-y-1">
      {/* Section Header */}
      <button
        type="button"
        onClick={handleClick}
        className={cn(
          "w-full flex items-center gap-3 py-3 rounded-lg transition-colors",
          isExpanded ? "justify-start px-4" : "justify-center px-2",
          isChildActive ? "bg-blue-50 text-blue-700" : "hover:bg-[#F1F5F9]"
        )}
        style={{
          color: isChildActive ? primaryColor : secondaryColor,
        }}
        title={item.label}
      >
        <Icon className="w-5 h-5 flex-shrink-0" />
        {isExpanded && (
          <>
            <span className="flex-1 text-left text-sm font-medium">{item.label}</span>
            <span className="transition-transform duration-200">
              {isSectionOpen ? (
                <ChevronDown className="w-4 h-4" />
              ) : (
                <ChevronRight className="w-4 h-4" />
              )}
            </span>
          </>
        )}
      </button>

      {/* Children - Show only when section is open */}
      {isExpanded && isSectionOpen && (
        <div
          className="mt-1 space-y-1"
          style={{
            paddingLeft: '24px', // 1.5rem - entre pl-5 (20px) y pl-8 (32px)
            animation: 'slideDown 0.2s ease-out'
          }}
        >
          {item.children?.map((child) => {
            const ChildIcon = child.icon;
            const isChildItemActive = isActive(child.path);

            return (
              <button
                key={child.path}
                type="button"
                onClick={() => {
                  navigate(child.path);
                  onNavigate?.();
                }}
                className={cn(
                  "w-full flex items-center gap-3 py-3 px-4 rounded-lg transition-all duration-150 text-sm font-medium",
                  isChildItemActive
                    ? "text-white"
                    : "hover:bg-[#F1F5F9]"
                )}
                style={{
                  backgroundColor: isChildItemActive ? primaryColor : undefined,
                  color: isChildItemActive ? "#FFFFFF" : secondaryColor,
                }}
                title={child.label}
              >
                <ChildIcon className="w-5 h-5 flex-shrink-0" />
                <span>{child.label}</span>
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
}

/**
 * Versión anclada al pie del menú, visible en todas las pantallas del sistema.
 * Con el menú plegado (16 unidades de ancho) no cabe la palabra "Versión", así
 * que se abrevia a "v1.0" y el texto completo queda en el `title`.
 */
function SidebarVersion({ isExpanded }: { isExpanded: boolean }) {
  return (
    <div
      className="border-t border-[rgba(0,0,0,0.1)] px-4 py-3 text-center"
      title={`Versión ${APP_VERSION}`}
    >
      <p className="text-xs text-[#64748B]">
        {isExpanded ? `Versión ${APP_VERSION}` : `v${APP_VERSION}`}
      </p>
    </div>
  );
}

function Sidebar({ isExpanded, isMobile, onClose, onNavigate }: SidebarProps) {
  const navigate = useNavigate();
  const location = useLocation();
  const [openSections, setOpenSections] = useState<string[]>(['Vacaciones']); // Default open

  const isActive = (path: string) => location.pathname === path;

  const primaryColor = "#2563EB";
  const secondaryColor = "#1E40AF";

  const toggleSection = (label: string) => {
    setOpenSections(prev =>
      prev.includes(label)
        ? prev.filter(s => s !== label)
        : [...prev, label]
    );
  };

  // Menú declarativo ÚNICO, filtrado por las abilities de la Matriz de Accesos
  // (config/access_matrix.php, servida por el backend). Antes había 5 arrays
  // por rol + un switch, que había que mantener en sync a mano con el router y
  // con el backend — de ahí la deriva que hacía visible "Auditoría" a un
  // aprobador al que el backend luego rechazaba con 403.
  //
  // Cada ítem declara las abilities que lo habilitan (basta con UNA). Al usar
  // el mismo mapa que autoriza el backend, el menú no puede volver a mostrar
  // algo que el backend no permita. Las abilities deben coincidir con las
  // `requires` de la ruta correspondiente en routes/index.tsx.
  const ALL_NAV_ITEMS: NavItem[] = [
    { label: "Dashboard", path: "/admin", icon: LayoutDashboard, abilities: ["dashboard.global_metrics", "dashboard.org_metrics"] },
    { label: NAV_LABELS.TENANTS, path: ROUTES.TENANTS, icon: Building2, abilities: ["tenants.manage"] },
    { label: "Mis Documentos", path: "/dashboard", icon: FileText, abilities: ["documents.view_own"] },
    { label: "Cargar Documentos", path: "/upload", icon: FileText, abilities: ["documents.bulk_upload_zip"] },
    { label: "Usuarios", path: "/users", icon: Users, abilities: ["users.view_list"] },
    { label: "Carga Masiva", path: "/users/batch", icon: Upload, abilities: ["users.bulk_upload"] },
    { label: "Lotes de Carga", path: "/batches", icon: FileStack, abilities: ["documents.view_batches"] },
    { label: "Documentos", path: "/documents", icon: FileText, abilities: ["documents.view_org"] },
    {
      label: "Vacaciones",
      path: "/vacations",
      icon: Calendar,
      abilities: ["vacations.request_own", "vacations.view_own_requests", "vacations.approve_reject_team", "vacations.view_history"],
      children: [
        { label: "Mis Vacaciones", path: "/vacations", icon: Calendar, abilities: ["vacations.request_own", "vacations.view_own_requests"] },
        { label: "Mi Equipo", path: "/team-vacations", icon: Users, abilities: ["vacations.approve_reject_team", "vacations.view_team_calendar"] },
        { label: "Histórico General", path: "/vacation-history", icon: History, abilities: ["vacations.view_history"] },
      ],
    },
    {
      // Un padre con hijos visibles solo despliega, nunca navega a su propio
      // `path` (ver CollapsibleSection), así que la página del padre se declara
      // también como hijo — misma convención que "Vacaciones", cuyo /vacations
      // se repite en "Mis Vacaciones". Sin esto, quien SÍ ve "Configuración"
      // (solo root, por platform.manage) convertía "Auditoría" en un grupo y se
      // quedaba sin forma de llegar al registro: más permisos, menos acceso.
      label: "Auditoría",
      path: "/audit-logs",
      icon: ClipboardList,
      abilities: ["audit.view", "platform.manage"],
      children: [
        { label: "Registro de Actividad", path: "/audit-logs", icon: ClipboardList, abilities: ["audit.view"] },
        { label: "Configuración", path: "/audit-settings", icon: ShieldCheck, abilities: ["platform.manage"] },
      ],
    },
    { label: "Firma Digital", path: "/signature-settings", icon: FileKey, abilities: ["platform.manage"] },
    { label: "Configuración", path: "/platform-settings", icon: Settings, abilities: ["platform.manage"] },
  ];

  // El set de abilities del rol activo se calcula FUERA del selector.
  //
  // Ojo al construirlo: los selectores de zustand se comparan con Object.is
  // sobre lo que devuelven, así que un `useAuthStore(s => new Set(...))` produce
  // un valor nuevo en cada pasada, useSyncExternalStore lo lee como "cambió"
  // eternamente y React aborta con "Maximum update depth exceeded" — en este
  // layout, o sea en TODAS las páginas. Los selectores deben devolver algo
  // estable (un primitivo o una referencia del store) y derivar aparte.
  const accessMatrix = useAuthStore((s) => s.accessMatrix);
  const currentRole = useAuthStore((s) => s.currentRole);

  const grantedAbilities = useMemo(
    () =>
      new Set(
        Object.entries(accessMatrix ?? {})
          .filter(([, roles]) => currentRole !== null && roles.includes(currentRole))
          .map(([ability]) => ability)
      ),
    [accessMatrix, currentRole]
  );

  // Sin abilities declaradas el ítem es visible para cualquier sesión.
  const isVisible = (item: NavItem) =>
    !item.abilities || item.abilities.some((a) => grantedAbilities.has(a));

  const navItems: NavItem[] = ALL_NAV_ITEMS.filter(isVisible).map((item) => ({
    ...item,
    children: item.children?.filter(isVisible),
  }));


  // Mobile sidebar with overlay
  if (isMobile) {
    return (
      <>
        {/* Backdrop */}
        {isExpanded && (
          <div
            className="fixed inset-0 bg-black/50 z-40 transition-opacity"
            onClick={onClose}
          />
        )}

        {/* Mobile Sidebar */}
        <aside
          className={cn(
            "fixed top-0 left-0 bg-white h-full w-72 z-50 flex flex-col transform transition-transform duration-300 ease-in-out shadow-xl",
            isExpanded ? "translate-x-0" : "-translate-x-full"
          )}
        >
          {/* Mobile Sidebar Header */}
          <div className="flex items-center justify-between p-4 border-b border-gray-100">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                <FileText className="w-5 h-5 text-white" />
              </div>
              <div>
                <h2 className="font-semibold text-gray-900">MiBoleta</h2>
                <p className="text-xs text-gray-500">Sistema de Gestión</p>
              </div>
            </div>
            <button
              type="button"
              onClick={onClose}
              className="p-2 rounded-lg hover:bg-gray-100 transition-colors"
            >
              <X className="w-5 h-5 text-gray-500" />
            </button>
          </div>

          {/* Navigation
              flex-1 en vez de la altura calculada a mano que había antes: el
              alto de la cabecera dejó de ser el único descuento cuando se
              añadió el pie de versión, y una resta fija habría vuelto a
              descuadrarse al tocar cualquiera de los dos. */}
          <nav className="flex-1 overflow-y-auto p-4 space-y-1">
            {navItems.map((item) => (
              <CollapsibleSection
                key={item.path + item.label}
                item={item}
                isExpanded={true}
                isActive={isActive}
                navigate={navigate}
                primaryColor={primaryColor}
                secondaryColor={secondaryColor}
                openSections={openSections}
                toggleSection={toggleSection}
                onNavigate={onNavigate}
              />
            ))}
          </nav>

          <SidebarVersion isExpanded={true} />
        </aside>
      </>
    );
  }

  // Desktop sidebar
  return (
    <aside className={cn(
      // El scroll pasa al <nav>: si se queda en el <aside>, el pie de versión
      // scrollea con el menú en vez de quedarse anclado abajo.
      "fixed top-[73px] left-0 bg-white border-r border-[rgba(0,0,0,0.1)] h-[calc(100vh-73px)] flex flex-col z-40 transition-all duration-300",
      isExpanded ? "w-64" : "w-16"
    )}>
      <nav className={cn("flex-1 overflow-y-auto pt-4 space-y-1", isExpanded ? "p-4" : "p-2")}>
        {navItems.map((item) => (
          <CollapsibleSection
            key={item.path + item.label}
            item={item}
            isExpanded={isExpanded}
            isActive={isActive}
            navigate={navigate}
            primaryColor={primaryColor}
            secondaryColor={secondaryColor}
            openSections={openSections}
            toggleSection={toggleSection}
          />
        ))}
      </nav>

      <SidebarVersion isExpanded={isExpanded} />
    </aside>
  );
}

export function RootLayout() {
  const navigate = useNavigate();
  const location = useLocation();
  const { user, logout } = useAuthStore();
  const [isSidebarExpanded, setIsSidebarExpanded] = useState(true);
  const [isMobile, setIsMobile] = useState(false);

  // Redirect to login if not authenticated
  useEffect(() => {
    if (!user) {
      navigate("/login", { replace: true });
    }
  }, [user, navigate]);

  // Handle responsive behavior
  useEffect(() => {
    const MOBILE_BREAKPOINT = 768; // md breakpoint

    const handleResize = () => {
      const mobile = window.innerWidth < MOBILE_BREAKPOINT;
      setIsMobile(mobile);

      // On mobile, sidebar starts closed; on desktop, it's open
      if (mobile) {
        setIsSidebarExpanded(false);
      } else if (window.innerWidth >= 1024) {
        setIsSidebarExpanded(true);
      }
    };

    // Check on mount
    handleResize();

    window.addEventListener("resize", handleResize);
    return () => window.removeEventListener("resize", handleResize);
  }, []);

  // Close mobile sidebar on route change
  useEffect(() => {
    if (isMobile) {
      setIsSidebarExpanded(false);
    }
  }, [location.pathname, isMobile]);

  // Sin sesión no se pinta el layout (evita el flash antes de que el efecto de
  // arriba redirija al login).
  //
  // Va DESPUÉS de todos los hooks a propósito: estaba justo tras el primer
  // useEffect, por encima de los otros dos, así que al hacer logout
  // (user -> null) el render salía temprano y React veía menos hooks que en el
  // render anterior ("Rendered fewer hooks than expected"). Un return temprano
  // nunca puede quedar por encima de un hook.
  if (!user) {
    return null;
  }

  const handleLogout = async () => {
    await logout();
    navigate("/login");
  };

  const handleToggleSidebar = () => {
    setIsSidebarExpanded(!isSidebarExpanded);
  };

  const handleCloseSidebar = () => {
    setIsSidebarExpanded(false);
  };

  return (
    <div className="min-h-screen bg-[#F8FAFC] pt-[73px]">
      <Navbar
        user={user}
        onLogout={handleLogout}
        onToggleSidebar={handleToggleSidebar}
        isSidebarExpanded={isSidebarExpanded}
      />
      <div className="flex">
        <Sidebar
          isExpanded={isSidebarExpanded}
          isMobile={isMobile}
          onClose={handleCloseSidebar}
          onNavigate={handleCloseSidebar}
        />
        <main className={cn(
          // min-w-0: sin esto, un flex item con contenido ancho (p.ej. una
          // tabla) fuerza su propio ancho en vez de encogerse al espacio
          // disponible; como `body { overflow-x: hidden }` (index.css) recorta
          // cualquier desborde sin dejar scroll alcanzable, el contenido que
          // no entraba (incluyendo botones de "Acciones" de las tablas)
          // quedaba fuera de pantalla y sin forma de llegar a él. Con
          // min-w-0, main se ajusta al ancho real y es el overflow-x-auto
          // interno de cada tabla el que scrollea.
          "flex-1 min-w-0 p-4 sm:p-6 min-h-[calc(100vh-73px)] transition-all duration-300 bg-[#F8FAFC] max-w-full",
          // On mobile, no margin; on desktop, margin based on sidebar width
          isMobile ? "ml-0" : (isSidebarExpanded ? "ml-64" : "ml-16")
        )}>
          <Outlet />
        </main>
      </div>
    </div>
  );
}

