/**
 * EJEMPLOS DE USO DE PAGINACIÓN DE TABLAS
 *
 * Este archivo muestra cómo usar el sistema de paginación tanto en modo
 * client-side como server-side.
 */

import { useState } from "react";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
  TablePagination,
} from "../components/ui/table";
import { Card, CardContent, CardHeader, CardTitle } from "../components/ui/card";
import { useTablePagination } from "../hooks/useTablePagination";

// ============================================================================
// EJEMPLO 1: PAGINACIÓN CLIENT-SIDE (Datos en memoria)
// ============================================================================

interface User {
  id: string;
  name: string;
  email: string;
  role: string;
}

// Datos de ejemplo
const mockUsers: User[] = Array.from({ length: 95 }, (_, i) => ({
  id: `user-${i + 1}`,
  name: `Usuario ${i + 1}`,
  email: `usuario${i + 1}@ejemplo.com`,
  role: i % 3 === 0 ? "admin" : i % 3 === 1 ? "manager" : "employee",
}));

export function ClientSidePaginationExample() {
  const {
    paginatedData,
    currentPage,
    pageSize,
    totalPages,
    totalItems,
    setCurrentPage,
    setPageSize,
  } = useTablePagination({
    data: mockUsers,
    initialPageSize: 10,
    mode: "client",
  });

  return (
    <Card>
      <CardHeader>
        <CardTitle>Paginación Client-Side</CardTitle>
      </CardHeader>
      <CardContent>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>ID</TableHead>
              <TableHead>Nombre</TableHead>
              <TableHead>Email</TableHead>
              <TableHead>Rol</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {paginatedData.map((user) => (
              <TableRow key={user.id}>
                <TableCell>{user.id}</TableCell>
                <TableCell>{user.name}</TableCell>
                <TableCell>{user.email}</TableCell>
                <TableCell>{user.role}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>

        <TablePagination
          currentPage={currentPage}
          totalPages={totalPages}
          totalItems={totalItems}
          pageSize={pageSize}
          onPageChange={setCurrentPage}
          onPageSizeChange={setPageSize}
          mode="client"
          showPageSizeSelector
          showPageInfo
          pageSizeOptions={[5, 10, 20, 50]}
        />
      </CardContent>
    </Card>
  );
}

// ============================================================================
// EJEMPLO 2: PAGINACIÓN SERVER-SIDE (Datos del servidor)
// ============================================================================

interface ServerResponse {
  data: User[];
  page: number;
  pageSize: number;
  totalItems: number;
  totalPages: number;
}

export function ServerSidePaginationExample() {
  const [currentPage, setCurrentPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);
  const [serverData, setServerData] = useState<User[]>([]);
  const [totalItems, setTotalItems] = useState(0);
  const [totalPages, setTotalPages] = useState(0);
  const [isLoading, setIsLoading] = useState(false);

  // Simular llamada al servidor
  const fetchData = async (page: number, size: number) => {
    setIsLoading(true);

    // Simular delay de red
    await new Promise((resolve) => setTimeout(resolve, 500));

    // Simular respuesta del servidor
    const startIndex = (page - 1) * size;
    const endIndex = startIndex + size;
    const mockResponse: ServerResponse = {
      data: mockUsers.slice(startIndex, endIndex),
      page,
      pageSize: size,
      totalItems: mockUsers.length,
      totalPages: Math.ceil(mockUsers.length / size),
    };

    setServerData(mockResponse.data);
    setTotalItems(mockResponse.totalItems);
    setTotalPages(mockResponse.totalPages);
    setIsLoading(false);
  };

  // Cargar datos iniciales
  useState(() => {
    fetchData(currentPage, pageSize);
  });

  const handlePageChange = (page: number) => {
    setCurrentPage(page);
    fetchData(page, pageSize);
  };

  const handlePageSizeChange = (size: number) => {
    setPageSize(size);
    setCurrentPage(1);
    fetchData(1, size);
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>Paginación Server-Side</CardTitle>
      </CardHeader>
      <CardContent>
        {isLoading ? (
          <div className="py-12 text-center text-[#64748B]">Cargando...</div>
        ) : (
          <>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>ID</TableHead>
                  <TableHead>Nombre</TableHead>
                  <TableHead>Email</TableHead>
                  <TableHead>Rol</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {serverData.map((user) => (
                  <TableRow key={user.id}>
                    <TableCell>{user.id}</TableCell>
                    <TableCell>{user.name}</TableCell>
                    <TableCell>{user.email}</TableCell>
                    <TableCell>{user.role}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>

            <TablePagination
              currentPage={currentPage}
              totalPages={totalPages}
              totalItems={totalItems}
              pageSize={pageSize}
              onPageChange={handlePageChange}
              onPageSizeChange={handlePageSizeChange}
              mode="server"
              showPageSizeSelector
              showPageInfo
              pageSizeOptions={[10, 20, 50, 100]}
            />
          </>
        )}
      </CardContent>
    </Card>
  );
}

// ============================================================================
// EJEMPLO 3: PAGINACIÓN SIMPLE (Sin selector de tamaño de página)
// ============================================================================

export function SimplePaginationExample() {
  const {
    paginatedData,
    currentPage,
    totalPages,
    setCurrentPage,
  } = useTablePagination({
    data: mockUsers,
    initialPageSize: 5,
    mode: "client",
  });

  return (
    <Card>
      <CardHeader>
        <CardTitle>Paginación Simple</CardTitle>
      </CardHeader>
      <CardContent>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Nombre</TableHead>
              <TableHead>Email</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {paginatedData.map((user) => (
              <TableRow key={user.id}>
                <TableCell>{user.name}</TableCell>
                <TableCell>{user.email}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>

        <TablePagination
          currentPage={currentPage}
          totalPages={totalPages}
          pageSize={5}
          onPageChange={setCurrentPage}
          mode="client"
          showPageSizeSelector={false}
          showPageInfo={false}
        />
      </CardContent>
    </Card>
  );
}

// ============================================================================
// EJEMPLO 4: CÓMO INTEGRAR EN TUS VISTAS EXISTENTES
// ============================================================================

/**
 * Para usar en TenantManagementView o UserManagementView:
 *
 * 1. Importar el hook y componente:
 *
 * import { useTablePagination } from "../../hooks/useTablePagination";
 * import { TablePagination } from "../ui/table";
 *
 * 2. Usar el hook:
 *
 * const {
 *   paginatedData,
 *   currentPage,
 *   pageSize,
 *   totalPages,
 *   totalItems,
 *   setCurrentPage,
 *   setPageSize,
 * } = useTablePagination({
 *   data: tenants, // o users, o filteredUsers
 *   initialPageSize: 10,
 *   mode: "client",
 * });
 *
 * 3. Usar paginatedData en lugar de la data original:
 *
 * {paginatedData.map((tenant) => (
 *   <TableRow key={tenant.id}>
 *     ...
 *   </TableRow>
 * ))}
 *
 * 4. Agregar el componente de paginación después de </Table>:
 *
 * <TablePagination
 *   currentPage={currentPage}
 *   totalPages={totalPages}
 *   totalItems={totalItems}
 *   pageSize={pageSize}
 *   onPageChange={setCurrentPage}
 *   onPageSizeChange={setPageSize}
 *   mode="client"
 * />
 */
