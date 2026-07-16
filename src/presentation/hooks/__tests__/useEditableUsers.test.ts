import { describe, it, expect, beforeEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useEditableUsers } from '../useEditableUsers';
import type { ValidationError, ValidationWarning } from '@/domain/types/bulkUserUpload.types';

describe('useEditableUsers', () => {
  describe('initial state', () => {
    it('should start with empty users array', () => {
      const { result } = renderHook(() => useEditableUsers());
      expect(result.current.users).toEqual([]);
    });

    it('should start with isValid false (no users)', () => {
      const { result } = renderHook(() => useEditableUsers());
      expect(result.current.isValid).toBe(false);
    });

    it('should have zero counts initially', () => {
      const { result } = renderHook(() => useEditableUsers());
      expect(result.current.errorCount).toBe(0);
      expect(result.current.warningCount).toBe(0);
      expect(result.current.validCount).toBe(0);
    });
  });

  describe('loadUsers', () => {
    it('should load users from data array', () => {
      const { result } = renderHook(() => useEditableUsers());

      const data = [
        {
          row_number: 2,
          nombre: 'Juan',
          apellido: 'Perez',
          email: 'juan@test.com',
          tipo_documento: 'dni',
          numero_documento: '12345678',
          estado: 'active',
        },
      ];

      act(() => {
        result.current.loadUsers(data, [], []);
      });

      expect(result.current.users).toHaveLength(1);
      expect(result.current.users[0].nombre).toBe('Juan');
    });

    it('should assign errors to users', () => {
      const { result } = renderHook(() => useEditableUsers());

      const data = [
        {
          row_number: 2,
          nombre: '',
          apellido: 'Perez',
          email: 'juan@test.com',
          tipo_documento: 'dni',
          numero_documento: '12345678',
          estado: 'active',
        },
      ];

      const errors: ValidationError[] = [
        { row: 2, field: 'nombre', message: 'Nombre es requerido' },
      ];

      act(() => {
        result.current.loadUsers(data, errors, []);
      });

      expect(result.current.users[0]._errors.nombre).toBe('Nombre es requerido');
      expect(result.current.users[0]._isValid).toBe(false);
    });

    it('should assign warnings to users', () => {
      const { result } = renderHook(() => useEditableUsers());

      const data = [
        {
          row_number: 2,
          nombre: 'Juan',
          apellido: 'Perez',
          email: 'juan@test.com',
          tipo_documento: 'dni',
          numero_documento: '12345678',
          estado: 'active',
          organizaciones: [],
        },
      ];

      const warnings: ValidationWarning[] = [
        { row: 2, field: 'organizaciones', message: 'Sin organizaciones asignadas' },
      ];

      act(() => {
        result.current.loadUsers(data, [], warnings);
      });

      expect(result.current.users[0]._warnings.organizaciones).toBe('Sin organizaciones asignadas');
    });
  });

  describe('validateUser', () => {
    it('should validate required fields', () => {
      const { result } = renderHook(() => useEditableUsers());

      const user = {
        id: '1',
        row_number: 2,
        nombre: '',
        apellido: '',
        email: '',
        tipo_documento: 'dni',
        numero_documento: '',
        estado: 'active',
        telefono: '',
        organizaciones: [],
        _errors: {},
        _warnings: {},
        _isValid: false,
        _isNew: false,
        _isModified: false,
      };

      const validation = result.current.validateUser(user);

      expect(validation.errors.nombre).toBe('Nombre es requerido');
      expect(validation.errors.apellido).toBe('Apellido es requerido');
      expect(validation.errors.email).toBe('Email es requerido');
      expect(validation.errors.numero_documento).toBe('Número de documento es requerido');
    });

    it('should validate DNI format (8 digits)', () => {
      const { result } = renderHook(() => useEditableUsers());

      const user = {
        id: '1',
        row_number: 2,
        nombre: 'Juan',
        apellido: 'Perez',
        email: 'juan@test.com',
        tipo_documento: 'DNI',
        numero_documento: '123', // Invalid - too short
        estado: 'active',
        telefono: '',
        organizaciones: [],
        _errors: {},
        _warnings: {},
        _isValid: false,
        _isNew: false,
        _isModified: false,
      };

      const validation = result.current.validateUser(user);

      expect(validation.errors.numero_documento).toContain('8 dígitos');
    });

    it('should validate RUC format (11 digits)', () => {
      const { result } = renderHook(() => useEditableUsers());

      const user = {
        id: '1',
        row_number: 2,
        nombre: 'Juan',
        apellido: 'Perez',
        email: 'juan@test.com',
        tipo_documento: 'RUC',
        numero_documento: '123456789', // Invalid - too short
        estado: 'active',
        telefono: '',
        organizaciones: [],
        _errors: {},
        _warnings: {},
        _isValid: false,
        _isNew: false,
        _isModified: false,
      };

      const validation = result.current.validateUser(user);

      expect(validation.errors.numero_documento).toContain('11 dígitos');
    });

    it('should validate email format', () => {
      const { result } = renderHook(() => useEditableUsers());

      const user = {
        id: '1',
        row_number: 2,
        nombre: 'Juan',
        apellido: 'Perez',
        email: 'invalid-email',
        tipo_documento: 'dni',
        numero_documento: '12345678',
        estado: 'active',
        telefono: '',
        organizaciones: [],
        _errors: {},
        _warnings: {},
        _isValid: false,
        _isNew: false,
        _isModified: false,
      };

      const validation = result.current.validateUser(user);

      expect(validation.errors.email).toBe('Email inválido');
    });

    it('should pass validation for valid user', () => {
      const { result } = renderHook(() => useEditableUsers());

      const user = {
        id: '1',
        row_number: 2,
        nombre: 'Juan',
        apellido: 'Perez',
        email: 'juan@test.com',
        tipo_documento: 'dni',
        numero_documento: '12345678',
        estado: 'active',
        telefono: '999888777',
        // Un usuario válido necesita al menos una empresa con al menos un rol:
        // el rol vive en la empresa, no en la fila.
        organizaciones: [{ ruc: '20123456789', supervisor_email: '', roles: ['client'] }],
        _errors: {},
        _warnings: {},
        _isValid: false,
        _isNew: false,
        _isModified: false,
      };

      const validation = result.current.validateUser(user);

      expect(Object.keys(validation.errors)).toHaveLength(0);
    });
  });

  describe('updateUser', () => {
    it('should update user field', () => {
      const { result } = renderHook(() => useEditableUsers());

      const data = [
        {
          row_number: 2,
          nombre: 'Juan',
          apellido: 'Perez',
          email: 'juan@test.com',
          tipo_documento: 'dni',
          numero_documento: '12345678',
          estado: 'active',
        },
      ];

      act(() => {
        result.current.loadUsers(data, [], []);
      });

      const userId = result.current.users[0].id;

      act(() => {
        result.current.updateUser(userId, 'nombre', 'Pedro');
      });

      expect(result.current.users[0].nombre).toBe('Pedro');
      expect(result.current.users[0]._isModified).toBe(true);
    });

    it('should revalidate user after update', () => {
      const { result } = renderHook(() => useEditableUsers());

      const data = [
        {
          row_number: 2,
          nombre: '',
          apellido: 'Perez',
          email: 'juan@test.com',
          tipo_documento: 'dni',
          numero_documento: '12345678',
          estado: 'active',
          organizaciones: [{ ruc: '20123456789', supervisor_email: '', roles: ['client'] }],
        },
      ];

      const errors: ValidationError[] = [
        { row: 2, field: 'nombre', message: 'Nombre es requerido' },
      ];

      act(() => {
        result.current.loadUsers(data, errors, []);
      });

      expect(result.current.users[0]._isValid).toBe(false);

      const userId = result.current.users[0].id;

      act(() => {
        result.current.updateUser(userId, 'nombre', 'Juan');
      });

      expect(result.current.users[0]._isValid).toBe(true);
    });
  });

  describe('addNewRow', () => {
    it('should add new user row with defaults', () => {
      const { result } = renderHook(() => useEditableUsers());

      act(() => {
        result.current.addNewRow();
      });

      expect(result.current.users).toHaveLength(1);
      expect(result.current.users[0]._isNew).toBe(true);
      expect(result.current.users[0].estado).toBe('active');
      expect(result.current.users[0].tipo_documento).toBe('dni');
    });

    it('should mark new row as invalid (empty required fields)', () => {
      const { result } = renderHook(() => useEditableUsers());

      act(() => {
        result.current.addNewRow();
      });

      expect(result.current.users[0]._isValid).toBe(false);
      expect(result.current.users[0]._errors.nombre).toBeTruthy();
      expect(result.current.users[0]._errors.email).toBeTruthy();
    });
  });

  describe('deleteRow', () => {
    it('should delete user row', () => {
      const { result } = renderHook(() => useEditableUsers());

      act(() => {
        result.current.addNewRow();
        result.current.addNewRow();
      });

      expect(result.current.users).toHaveLength(2);

      const userIdToDelete = result.current.users[0].id;

      act(() => {
        result.current.deleteRow(userIdToDelete);
      });

      expect(result.current.users).toHaveLength(1);
      expect(result.current.users.find(u => u.id === userIdToDelete)).toBeUndefined();
    });
  });

  // El rol es SIEMPRE por empresa (user_tenant_roles): no existe un rol de
  // nivel de fila. El único rol global es 'root', que no se da de alta por
  // carga masiva.
  describe('validación de rol por empresa', () => {
    const baseUser = {
      id: '1',
      row_number: 2,
      nombre: 'Juan',
      apellido: 'Perez',
      email: 'juan@test.com',
      tipo_documento: 'dni',
      numero_documento: '12345678',
      estado: 'active',
      telefono: '',
      organizaciones: [],
      _errors: {},
      _warnings: {},
      _isValid: false,
      _isNew: false,
      _isModified: false,
    };

    const withOrg = (roles: string[]) => ({
      ...baseUser,
      organizaciones: [{ ruc: '20123456789', supervisor_email: '', roles }],
    });

    it('should accept admin_tenant as a valid org role', () => {
      const { result } = renderHook(() => useEditableUsers());

      const validation = result.current.validateUser(withOrg(['admin_tenant']) as any);

      expect(validation.errors['organizaciones.0.roles']).toBeUndefined();
    });

    it('should accept aprobador as a valid org role', () => {
      const { result } = renderHook(() => useEditableUsers());

      const validation = result.current.validateUser(withOrg(['aprobador']) as any);

      expect(validation.errors['organizaciones.0.roles']).toBeUndefined();
    });

    it('should accept several roles in the same company', () => {
      const { result } = renderHook(() => useEditableUsers());

      const validation = result.current.validateUser(withOrg(['admin', 'aprobador']) as any);

      expect(validation.errors['organizaciones.0.roles']).toBeUndefined();
    });

    it('should reject root as an org role', () => {
      const { result } = renderHook(() => useEditableUsers());

      const validation = result.current.validateUser(withOrg(['root']) as any);

      expect(validation.errors['organizaciones.0.roles']).toBe(
        'Rol inválido en organización 1: root'
      );
    });

    it('should require a role in every company', () => {
      const { result } = renderHook(() => useEditableUsers());

      const validation = result.current.validateUser(withOrg([]) as any);

      expect(validation.errors['organizaciones.0.roles']).toBe('Rol requerido en organización 1');
    });

    it('should require at least one company per user', () => {
      const { result } = renderHook(() => useEditableUsers());

      const validation = result.current.validateUser(baseUser as any);

      expect(validation.errors.organizaciones).toBe('Debes asignar al menos una empresa');
    });
  });

  describe('birth_date validation (P1)', () => {
    const baseUser = {
      id: '1',
      row_number: 2,
      nombre: 'Juan',
      apellido: 'Perez',
      email: 'juan@test.com',
      tipo_documento: 'dni',
      numero_documento: '12345678',
      estado: 'active',
      telefono: '',
      organizaciones: [],
      _errors: {},
      _warnings: {},
      _isValid: false,
      _isNew: false,
      _isModified: false,
    };

    it('should accept an empty birth_date (optional field)', () => {
      const { result } = renderHook(() => useEditableUsers());
      const user = { ...baseUser, birth_date: '' };

      const validation = result.current.validateUser(user as any);

      expect(validation.errors.birth_date).toBeUndefined();
    });

    it('should accept a valid past birth_date', () => {
      const { result } = renderHook(() => useEditableUsers());
      const user = { ...baseUser, birth_date: '1990-05-15' };

      const validation = result.current.validateUser(user as any);

      expect(validation.errors.birth_date).toBeUndefined();
    });

    it('should reject an unparseable birth_date', () => {
      const { result } = renderHook(() => useEditableUsers());
      const user = { ...baseUser, birth_date: 'not-a-date' };

      const validation = result.current.validateUser(user as any);

      expect(validation.errors.birth_date).toBe('Fecha de nacimiento inválida');
    });

    it('should reject a future birth_date', () => {
      const { result } = renderHook(() => useEditableUsers());
      const futureYear = new Date().getFullYear() + 5;
      const user = { ...baseUser, birth_date: `${futureYear}-01-01` };

      const validation = result.current.validateUser(user as any);

      expect(validation.errors.birth_date).toBe('Fecha de nacimiento inválida');
    });
  });

  describe('organization helpers (P2 - múltiples organizaciones por fila)', () => {
    it('should add an empty organization', () => {
      const { result } = renderHook(() => useEditableUsers());

      const data = [
        { row_number: 2, nombre: 'Juan', apellido: 'Perez', email: 'juan@test.com', tipo_documento: 'dni', numero_documento: '12345678', estado: 'active', organizaciones: [] },
      ];

      act(() => {
        result.current.loadUsers(data, [], []);
      });

      const userId = result.current.users[0].id;

      act(() => {
        result.current.addOrganization(userId);
      });

      expect(result.current.users[0].organizaciones).toHaveLength(1);
      expect(result.current.users[0].organizaciones[0].ruc).toBe('');
      expect(result.current.users[0]._isModified).toBe(true);
    });

    it('should add multiple organizations by calling addOrganization repeatedly', () => {
      const { result } = renderHook(() => useEditableUsers());

      const data = [
        { row_number: 2, nombre: 'Juan', apellido: 'Perez', email: 'juan@test.com', tipo_documento: 'dni', numero_documento: '12345678', estado: 'active', organizaciones: [{ ruc: '11111111111', supervisor_email: '' }] },
      ];

      act(() => {
        result.current.loadUsers(data, [], []);
      });

      const userId = result.current.users[0].id;

      act(() => {
        result.current.addOrganization(userId, { ruc: '22222222222' });
      });

      expect(result.current.users[0].organizaciones).toHaveLength(2);
      expect(result.current.users[0].organizaciones[1].ruc).toBe('22222222222');
    });

    it('should remove an organization by index', () => {
      const { result } = renderHook(() => useEditableUsers());

      const data = [
        {
          row_number: 2,
          nombre: 'Juan',
          apellido: 'Perez',
          email: 'juan@test.com',
          tipo_documento: 'dni',
          numero_documento: '12345678',
          estado: 'active',
          organizaciones: [
            { ruc: '11111111111', supervisor_email: '' },
            { ruc: '22222222222', supervisor_email: '' },
          ],
        },
      ];

      act(() => {
        result.current.loadUsers(data, [], []);
      });

      const userId = result.current.users[0].id;

      act(() => {
        result.current.removeOrganization(userId, 0);
      });

      expect(result.current.users[0].organizaciones).toHaveLength(1);
      expect(result.current.users[0].organizaciones[0].ruc).toBe('22222222222');
    });
  });

  describe('stats calculation', () => {
    it('should calculate error count correctly', () => {
      const { result } = renderHook(() => useEditableUsers());

      const data = [
        { row_number: 2, nombre: '', apellido: 'A', email: 'a@test.com', tipo_documento: 'dni', numero_documento: '12345678', estado: 'active' },
        { row_number: 3, nombre: 'B', apellido: '', email: 'b@test.com', tipo_documento: 'dni', numero_documento: '87654321', estado: 'active' },
        { row_number: 4, nombre: 'C', apellido: 'C', email: 'c@test.com', tipo_documento: 'dni', numero_documento: '11111111', estado: 'active' },
      ];

      const errors: ValidationError[] = [
        { row: 2, field: 'nombre', message: 'Nombre es requerido' },
        { row: 3, field: 'apellido', message: 'Apellido es requerido' },
      ];

      act(() => {
        result.current.loadUsers(data, errors, []);
      });

      expect(result.current.errorCount).toBe(2);
      expect(result.current.validCount).toBe(1);
    });

    it('should update isValid based on users', () => {
      const { result } = renderHook(() => useEditableUsers());

      const data = [
        { row_number: 2, nombre: 'Juan', apellido: 'Perez', email: 'juan@test.com', tipo_documento: 'dni', numero_documento: '12345678', estado: 'active' },
      ];

      act(() => {
        result.current.loadUsers(data, [], []);
      });

      expect(result.current.isValid).toBe(true);
    });
  });
});
