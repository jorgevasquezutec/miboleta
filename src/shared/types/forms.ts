/**
 * Common Form Types
 */

export interface FormErrors {
  [key: string]: string;
}

export interface FormState<T = any> {
  data: T;
  errors: FormErrors;
  isSubmitting: boolean;
  isDirty: boolean;
}

export interface ValidationRule {
  required?: boolean;
  minLength?: number;
  maxLength?: number;
  pattern?: RegExp;
  custom?: (value: any) => boolean | string;
}

export type ValidationRules<T> = {
  [K in keyof T]?: ValidationRule;
};
