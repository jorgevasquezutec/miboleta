/**
 * Zustand Stores - State Management
 *
 * Todos los stores simulan peticiones HTTP con setTimeout
 * Para usar APIs reales, solo reemplaza mockApi por fetch/axios
 */

export { useAuthStore } from "./authStore";
export { useDocumentsStore } from "./documentsStore";
export { useUsersStore } from "./usersStore";
export { useTenantsStore } from "./tenantsStore";

// Re-export tipos para conveniencia
export type { User, Tenant, Document, LoginResponse, ApiResponse, PaginatedResponse } from "../services/mockApi";
