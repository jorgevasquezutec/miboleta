import { create } from "zustand";
import { User } from "@/core/domain/entities";
import { 
  GetUsersUseCase,
  CreateUserUseCase,
  UpdateUserUseCase,
  DeleteUserUseCase
} from "@/core/domain/use-cases/users";
import { userRepository } from "@/infrastructure/persistence/repositories";

// Instanciar use cases
const getUsersUseCase = new GetUsersUseCase(userRepository);
const createUserUseCase = new CreateUserUseCase(userRepository);
const updateUserUseCase = new UpdateUserUseCase(userRepository);
const deleteUserUseCase = new DeleteUserUseCase(userRepository);

interface UsersState {
  users: User[];
  isLoading: boolean;
  error: string | null;

  // Actions
  fetchUsers: () => Promise<void>;
  createUser: (userData: Omit<User, "id" | "createdAt" | "updatedAt">) => Promise<User>;
  updateUser: (id: string, updates: Partial<User>) => Promise<User>;
  deleteUser: (id: string) => Promise<void>;
  getUsersByTenant: (tenantId?: string) => User[];
  clearError: () => void;
}

export const useUsersStore = create<UsersState>((set, get) => ({
  users: [],
  isLoading: false,
  error: null,

  fetchUsers: async () => {
    set({ isLoading: true, error: null });

    try {
      const users = await getUsersUseCase.execute();

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
