/**
 * Validation utilities
 */

export const isValidEmail = (email: string): boolean => {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
};

export const isValidDNI = (dni: string): boolean => {
  return /^\d{8}$/.test(dni);
};

export const isValidRUC = (ruc: string): boolean => {
  return /^\d{11}$/.test(ruc);
};

export const isValidPhone = (phone: string): boolean => {
  // Formato: +51 999 999 999 o 999999999
  return /^(\+51\s?)?\d{9}$/.test(phone.replace(/\s/g, ''));
};
