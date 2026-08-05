import { describe, it, expect } from "vitest";
import { canEditTarget } from "../userPermissions";
import { User, TenantAssociation } from "@/core/domain/entities";

const TENANT_A = "tenant-a";
const TENANT_B = "tenant-b";
const CURRENT_USER_ID = "actor-1";

function tenant(id: string, role: string): TenantAssociation {
  return { id, name: id, ruc: "1", is_primary: true, role };
}

function buildUser(tenants: TenantAssociation[], id = "target-1"): User {
  return {
    id,
    name: "Target",
    email: "target@example.com",
    role: "admin",
    status: "active",
    tenants,
  };
}

// Espeja UserService::canManageUser (Decisión C1, endurecida por
// Observación 1 2026-08 — solo root administra cuentas admin). Ver
// backend/tests/Unit/Services/UserServiceTest.php para la contraparte
// backend de estos mismos casos.
describe("canEditTarget", () => {
  it("root siempre puede editar, sin importar el rol del target", () => {
    const target = buildUser([tenant(TENANT_A, "admin")]);
    expect(canEditTarget(target, CURRENT_USER_ID, "root", TENANT_A)).toBe(true);
  });

  it("nadie puede editarse a sí mismo desde esta pantalla", () => {
    const self = buildUser([tenant(TENANT_A, "admin_tenant")], CURRENT_USER_ID);
    expect(canEditTarget(self, CURRENT_USER_ID, "admin_tenant", TENANT_A)).toBe(false);
  });

  it("sin currentRole no se puede editar a nadie", () => {
    const target = buildUser([tenant(TENANT_A, "client")]);
    expect(canEditTarget(target, CURRENT_USER_ID, null, TENANT_A)).toBe(false);
  });

  it("Observación 1: admin_tenant ya NO puede administrar a un target admin en la empresa activa", () => {
    const target = buildUser([tenant(TENANT_A, "admin")]);
    expect(canEditTarget(target, CURRENT_USER_ID, "admin_tenant", TENANT_A)).toBe(false);
  });

  it("admin_tenant sigue pudiendo administrar a un target client en la empresa activa", () => {
    const target = buildUser([tenant(TENANT_A, "client")]);
    expect(canEditTarget(target, CURRENT_USER_ID, "admin_tenant", TENANT_A)).toBe(true);
  });

  it("admin_tenant sigue pudiendo administrar a un target aprobador en la empresa activa", () => {
    const target = buildUser([tenant(TENANT_A, "aprobador")]);
    expect(canEditTarget(target, CURRENT_USER_ID, "admin_tenant", TENANT_A)).toBe(true);
  });

  it("blindaje cross-tenant: target con rol admin en OTRA empresa también es intocable para admin_tenant", () => {
    const target = buildUser([
      tenant(TENANT_A, "client"),
      tenant(TENANT_B, "admin"),
    ]);
    expect(canEditTarget(target, CURRENT_USER_ID, "admin_tenant", TENANT_A)).toBe(false);
  });

  it("blindaje cross-tenant: target con rol admin_tenant en OTRA empresa sigue siendo intocable", () => {
    const target = buildUser([
      tenant(TENANT_A, "client"),
      tenant(TENANT_B, "admin_tenant"),
    ]);
    expect(canEditTarget(target, CURRENT_USER_ID, "admin", TENANT_A)).toBe(false);
  });

  it("admin no puede administrar a un target admin_tenant en la empresa activa", () => {
    const target = buildUser([tenant(TENANT_A, "admin_tenant")]);
    expect(canEditTarget(target, CURRENT_USER_ID, "admin", TENANT_A)).toBe(false);
  });

  it("admin sigue pudiendo administrar a un target client en la empresa activa", () => {
    const target = buildUser([tenant(TENANT_A, "client")]);
    expect(canEditTarget(target, CURRENT_USER_ID, "admin", TENANT_A)).toBe(true);
  });

  it("un actor sin rol administrable (client/aprobador) no puede editar a nadie", () => {
    const target = buildUser([tenant(TENANT_A, "client")]);
    expect(canEditTarget(target, CURRENT_USER_ID, "client", TENANT_A)).toBe(false);
  });

  it("target sin rol en la empresa activa no es editable", () => {
    const target = buildUser([tenant(TENANT_B, "client")]);
    expect(canEditTarget(target, CURRENT_USER_ID, "admin_tenant", TENANT_A)).toBe(false);
  });
});
