/**
 * Common Types and Enums
 */

export type UserRole = 'root' | 'admin' | 'client';

export type UserStatus = 'active' | 'inactive' | 'pending';

export type DocumentStatus =
  | 'pending'
  | 'processing'
  | 'completed'
  | 'failed'
  | 'signed';

export type BatchStatus =
  | 'pending'
  | 'processing'
  | 'completed'
  | 'failed'
  | 'partial';

export type DocumentType =
  | 'dni'
  | 'ruc'
  | 'ce'
  | 'passport';

export interface SelectOption<T = string> {
  label: string;
  value: T;
  disabled?: boolean;
}

export interface BadgeVariant {
  variant: 'default' | 'destructive' | 'outline' | 'secondary';
  label: string;
  className?: string;
}

export type SortDirection = 'asc' | 'desc';

export interface SortConfig {
  key: string;
  direction: SortDirection;
}

export interface FilterConfig {
  [key: string]: any;
}
