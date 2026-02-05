/**
 * Shared Utilities - Cross-layer utilities
 */

import { type ClassValue, clsx } from "clsx";
import { twMerge } from "tailwind-merge";

// Tailwind CSS class merger
export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

// Re-export validators
export * from './validators';

// Re-export helpers
export * from './helpers';

// Re-export cookie utilities
export * from './cookies';
