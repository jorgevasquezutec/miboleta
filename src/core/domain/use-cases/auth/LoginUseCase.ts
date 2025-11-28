// Use Case - Login
import { User } from '../../entities';
import { IUserRepository } from '../../repositories';

export interface LoginCredentials {
  email: string;
  password: string;
}

export interface LoginResponse {
  user: User;
  token: string;
}

export class LoginUseCase {
  constructor(private userRepository: IUserRepository) {}

  async execute(credentials: LoginCredentials): Promise<LoginResponse> {
    // Validar credenciales
    if (!credentials.email || !credentials.password) {
      throw new Error('Email y contraseña son requeridos');
    }

    // Buscar usuario por email
    const user = await this.userRepository.findByEmail(credentials.email);
    
    if (!user) {
      throw new Error('Credenciales inválidas');
    }

    if (user.status !== 'active') {
      throw new Error('Usuario inactivo o suspendido');
    }

    // En producción, aquí verificarías la contraseña con bcrypt
    // Por ahora, aceptamos cualquier contraseña en desarrollo
    
    // Generar token (en producción esto vendría del backend)
    const token = `mock-token-${user.id}-${Date.now()}`;

    return {
      user,
      token,
    };
  }
}
