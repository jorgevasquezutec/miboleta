import { create } from "zustand";
import { mockApi, type User } from "../services/mockApi";

interface UsersState {
  users: User[];
  isLoading: boolean;
  error: string | null;

  // Actions
  fetchUsers: (tenantId?: string) => Promise<void>;
  createUser: (userData: Omit<User, "id">) => Promise<User>;
  updateUser: (id: string, updates: Partial<User>) => Promise<User>;
  deleteUser: (id: string) => Promise<void>;
  getUsersByTenant: (tenantId?: string) => User[];
  clearError: () => void;
}

export const useUsersStore = create<UsersState>((set, get) => ({
  users: [],
  isLoading: false,
  error: null,

  fetchUsers: async (tenantId?: string) => {
    set({ isLoading: true, error: null });

    try {
      // Simular petición HTTP a /api/users
      const users = await mockApi.getUsers(tenantId);

      set({
        users,
        isLoading: false,
      });
    } catch (error) {
      set({
        error: error instanceof Error ? error.message : "Error al cargar usuarios",
        isLoading: false,
      });
    }
  },

  createUser: async (userData: Omit<User, "id">) => {
    set({ isLoading: true, error: null });

    try {
      // Simular petición HTTP POST /api/users
      const newUser = await mockApi.createUser(userData);

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
      // Simular petición HTTP PUT /api/users/:id
      const updatedUser = await mockApi.updateUser(id, updates);

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
      // Simular petición HTTP DELETE /api/users/:id
      await mockApi.deleteUser(id);

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

    return users.filter((u) => u.tenantId === tenantId);
  },

  clearError: () => set({ error: null }),
}));
