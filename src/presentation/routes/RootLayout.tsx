import { Outlet, useNavigate, useLocation } from "react-router-dom";
import { useAuthStore } from "@/presentation/stores";
import { Navbar } from "@/presentation/components/layout";
import { useState, useEffect } from "react";
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
} from "lucide-react";
import { NAV_LABELS, ROUTES } from "@/shared/constants";
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

function Sidebar({ isExpanded, isMobile, onClose, onNavigate }: SidebarProps) {
  const navigate = useNavigate();
  const location = useLocation();
  const { user } = useAuthStore();
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

  // Define navigation items by role
  const rootNavItems: NavItem[] = [
    { label: "Dashboard", path: "/admin", icon: LayoutDashboard },
    { label: NAV_LABELS.TENANTS, path: ROUTES.TENANTS, icon: Building2 },
    { label: "Usuarios", path: "/users", icon: Users },
    { label: "Carga Masiva", path: "/users/batch", icon: Upload },
    { label: "Documentos", path: "/documents", icon: FileText },
    {
      label: "Vacaciones",
      path: "/vacations",
      icon: Calendar,
      children: [
        { label: "Histórico General", path: "/vacation-history", icon: History },
      ],
    },
    { label: "Auditoría", path: "/audit-logs", icon: ClipboardList },
  ];

  const adminNavItems: NavItem[] = [
    { label: "Dashboard", path: "/admin", icon: LayoutDashboard },
    { label: "Mis Documentos", path: "/dashboard", icon: FileText },
    { label: "Cargar Documentos", path: "/upload", icon: FileText },
    { label: "Usuarios", path: "/users", icon: Users },
    { label: "Lotes de Carga", path: "/batches", icon: FileStack },
    { label: "Documentos", path: "/documents", icon: FileText },
    {
      label: "Vacaciones",
      path: "/vacations",
      icon: Calendar,
      children: [
        { label: "Mis Vacaciones", path: "/vacations", icon: Calendar },
        { label: "Mi Equipo", path: "/team-vacations", icon: Users },
        { label: "Histórico General", path: "/vacation-history", icon: History },
      ],
    },
    { label: "Auditoría", path: "/audit-logs", icon: ClipboardList },
  ];

  const clientNavItems: NavItem[] = [
    { label: "Mis Documentos", path: "/dashboard", icon: FileText },
    { label: "Mis Vacaciones", path: "/vacations", icon: Calendar },
  ];

  const getNavItems = (): NavItem[] => {
    switch (user?.role) {
      case "root":
        return rootNavItems;
      case "admin":
        return adminNavItems;
      case "client":
        return clientNavItems;
      default:
        return [];
    }
  };

  const navItems = getNavItems();

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
            "fixed top-0 left-0 bg-white h-full w-72 z-50 transform transition-transform duration-300 ease-in-out shadow-xl",
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

          {/* Navigation */}
          <nav className="p-4 space-y-1 overflow-y-auto h-[calc(100vh-80px)]">
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
        </aside>
      </>
    );
  }

  // Desktop sidebar
  return (
    <aside className={cn(
      "fixed top-[73px] left-0 bg-white border-r border-[rgba(0,0,0,0.1)] h-[calc(100vh-73px)] overflow-y-auto z-40 transition-all duration-300",
      isExpanded ? "w-64" : "w-16"
    )}>
      <nav className={cn("pt-4 space-y-1", isExpanded ? "p-4" : "p-2")}>
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
    </aside>
  );
}

export function RootLayout() {
  const navigate = useNavigate();
  const location = useLocation();
  const { user, logout } = useAuthStore();
  const [isSidebarExpanded, setIsSidebarExpanded] = useState(true);
  const [isMobile, setIsMobile] = useState(false);

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
          "flex-1 p-4 sm:p-6 min-h-[calc(100vh-73px)] transition-all duration-300 bg-[#F8FAFC] max-w-full",
          // On mobile, no margin; on desktop, margin based on sidebar width
          isMobile ? "ml-0" : (isSidebarExpanded ? "ml-64" : "ml-16")
        )}>
          <Outlet />
        </main>
      </div>
    </div>
  );
}

