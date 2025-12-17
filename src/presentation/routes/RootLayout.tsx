import { Outlet, useNavigate, useLocation } from "react-router-dom";
import { useAuthStore } from "@/presentation/stores";
import { Navbar } from "@/presentation/components/layout";
import { Toaster } from "@/presentation/components/ui/sonner";
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
} from "lucide-react";
import { USER_ROLE_DISPLAY_LABELS, NAV_LABELS, ROUTES } from "@/shared/constants";
import { cn } from "@/presentation/components/ui/utils";

interface SidebarProps {
  isExpanded: boolean;
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
    }
  };

  if (!hasChildren) {
    // Regular item without children
    const isItemActive = isActive(item.path);
    return (
      <button
        type="button"
        onClick={() => navigate(item.path)}
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
                onClick={() => navigate(child.path)}
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

function Sidebar({ isExpanded }: SidebarProps) {
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

  // Auto-collapse sidebar on smaller screens
  useEffect(() => {
    const COLLAPSE_BREAKPOINT = 1024;

    const handleResize = () => {
      if (window.innerWidth < COLLAPSE_BREAKPOINT) {
        setIsSidebarExpanded(false);
      } else {
        setIsSidebarExpanded(true);
      }
    };

    // Check on mount
    handleResize();

    window.addEventListener("resize", handleResize);
    return () => window.removeEventListener("resize", handleResize);
  }, []);

  const handleLogout = async () => {
    await logout();
    navigate("/login");
  };

  const handleToggleSidebar = () => {
    setIsSidebarExpanded(!isSidebarExpanded);
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
        <Sidebar isExpanded={isSidebarExpanded} />
        <main className={cn(
          "flex-1 p-6 min-h-[calc(100vh-73px)] transition-all duration-300 bg-[#F8FAFC]",
          isSidebarExpanded ? "ml-64" : "ml-16"
        )}>
          <Outlet />
        </main>
      </div>
      <Toaster />
    </div>
  );
}
