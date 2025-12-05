import { create } from "zustand";
import { User } from "@/core/domain/entities";
import { 
  CreateUserUseCase,
  UpdateUserUseCase,
  DeleteUserUseCase
} from "@/core/domain/use-cases/users";
import { userRepository } from "@/infrastructure/persistence/repositories";
import type { PaginationMeta, PaginationLinks, GetUsersParams } from "@/infrastructure/persistence/repositories/UserRepository";

// Instanciar use cases (ya no necesitamos GetUsersUseCase porque llamamos directamente al repository)
const createUserUseCase = new CreateUserUseCase(userRepository);
const updateUserUseCase = new UpdateUserUseCase(userRepository);
const deleteUserUseCase = new DeleteUserUseCase(userRepository);

interface UsersState {
  users: User[];
  isLoading: boolean;
  error: string | null;
  
  // Pagination state
  pagination: PaginationMeta | null;
  links: PaginationLinks | null;

  // Actions
  fetchUsers: (params?: GetUsersParams) => Promise<void>;
  goToPage: (page: number) => Promise<void>;
  changePerPage: (perPage: number) => Promise<void>;
  setSearch: (search: string) => Promise<void>;
  setStatusFilter: (status: string) => Promise<void>;
  createUser: (userData: Omit<User, "id" | "createdAt" | "updatedAt">) => Promise<User>;
  updateUser: (id: string, updates: Partial<User>) => Promise<User>;
  deleteUser: (id: string) => Promise<void>;
  getUsersByTenant: (tenantId?: string) => User[];
  clearError: () => void;
  
  // Current filters
  currentFilters: GetUsersParams;
}

export const useUsersStore = create<UsersState>((set, get) => ({
  users: [],
  isLoading: false,
  error: null,
  pagination: null,
  links: null,
  currentFilters: {
    page: 1,
    per_page: 10,
  },

  fetchUsers: async (params?: GetUsersParams) => {
    set({ isLoading: true, error: null });

    try {
      // Merge with current filters
      const filters = { ...get().currentFilters, ...params };
      
      const response = await userRepository.getUsers(filters);
      
      console.log('📊 Pagination response:', {
        data_length: response.data.length,
        meta: response.meta,
        links: response.links
      });

      set({
        users: response.data,
        pagination: response.meta,
        links: response.links,
        currentFilters: filters,
        isLoading: false,
      });
    } catch (error) {
      console.error('❌ Error fetching users:', error);
      set({
        error: error instanceof Error ? error.message : "Error al cargar usuarios",
        isLoading: false,
      });
    }
  },

  goToPage: async (page: number) => {
    await get().fetchUsers({ page });
  },

  changePerPage: async (perPage: number) => {
    await get().fetchUsers({ per_page: perPage, page: 1 });
  },

  setSearch: async (search: string) => {
    await get().fetchUsers({ search, page: 1 });
  },

  setStatusFilter: async (status: string) => {
    await get().fetchUsers({ status, page: 1 });
  },

  createUser: async (userData: Omit<User, "id" | "createdAt" | "updatedAt">) => {
    set({ isLoading: true, error: null });

    try {
      const newUser = await createUserUseCase.execute(userData);

      set((state) => ({
        users: [...state.users, newUser],
        isLoading: false,
      }));

      return newUser;
    } catch (error) {
      set({
        error: error instanceof Error ? error.message : "Error al crear usuario",
        isLoading: false,
      });
      throw error;
    }
  },

  updateUser: async (id: string, updates: Partial<User>) => {
    set({ isLoading: true, error: null });

    try {
      const updatedUser = await updateUserUseCase.execute(id, updates);

      set((state) => ({
        users: state.users.map((u) => (u.id === id ? updatedUser : u)),
        isLoading: false,
      }));

      return updatedUser;
    } catch (error) {
      set({
        error: error instanceof Error ? error.message : "Error al actualizar usuario",
        isLoading: false,
      });
      throw error;
    }
  },

  deleteUser: async (id: string) => {
    set({ isLoading: true, error: null });

    try {
      await deleteUserUseCase.execute(id);

      set((state) => ({
        users: state.users.filter((u) => u.id !== id),
        isLoading: false,
      }));
    } catch (error) {
      set({
        error: error instanceof Error ? error.message : "Error al eliminar usuario",
        isLoading: false,
      });
      throw error;
    }
  },

  getUsersByTenant: (tenantId?: string) => {
    const { users } = get();

    if (!tenantId) return users;

    return users.filter((u) =>
      u.tenants?.some(tenant => tenant.id === tenantId) ||
      u.primary_tenant?.id === tenantId
    );
  },

  clearError: () => set({ error: null }),
}));
