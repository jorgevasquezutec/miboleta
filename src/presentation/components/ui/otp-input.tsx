import { useRef, useEffect, KeyboardEvent, ClipboardEvent } from "react";
import { cn } from "./utils";

interface OTPInputProps {
  value: string;
  onChange: (value: string) => void;
  length?: number;
  disabled?: boolean;
  autoFocus?: boolean;
  className?: string;
}

export function OTPInput({
  value,
  onChange,
  length = 6,
  disabled = false,
  autoFocus = false,
  className,
}: OTPInputProps) {
  const inputRefs = useRef<(HTMLInputElement | null)[]>([]);

  useEffect(() => {
    if (autoFocus && inputRefs.current[0]) {
      inputRefs.current[0].focus();
    }
  }, [autoFocus]);

  const handleChange = (index: number, digit: string) => {
    if (disabled) return;

    // Only allow digits
    const sanitized = digit.replace(/\D/g, "");
    if (sanitized.length === 0) return;

    const newValue = value.split("");
    newValue[index] = sanitized[sanitized.length - 1]; // Take last digit if multiple pasted
    const updatedValue = newValue.join("").slice(0, length);

    onChange(updatedValue);

    // Auto-focus next input
    if (sanitized && index < length - 1) {
      inputRefs.current[index + 1]?.focus();
    }
  };

  const handleKeyDown = (index: number, e: KeyboardEvent<HTMLInputElement>) => {
    if (disabled) return;

    // Backspace: clear current or move to previous
    if (e.key === "Backspace") {
      e.preventDefault();
      const newValue = value.split("");

      if (newValue[index]) {
        // Clear current digit
        newValue[index] = "";
        onChange(newValue.join(""));
      } else if (index > 0) {
        // Move to previous and clear it
        newValue[index - 1] = "";
        onChange(newValue.join(""));
        inputRefs.current[index - 1]?.focus();
      }
    }

    // Left arrow: move to previous
    if (e.key === "ArrowLeft" && index > 0) {
      e.preventDefault();
      inputRefs.current[index - 1]?.focus();
    }

    // Right arrow: move to next
    if (e.key === "ArrowRight" && index < length - 1) {
      e.preventDefault();
      inputRefs.current[index + 1]?.focus();
    }

    // Home: move to first
    if (e.key === "Home") {
      e.preventDefault();
      inputRefs.current[0]?.focus();
    }

    // End: move to last
    if (e.key === "End") {
      e.preventDefault();
      inputRefs.current[length - 1]?.focus();
    }
  };

  const handlePaste = (e: ClipboardEvent<HTMLInputElement>) => {
    e.preventDefault();
    if (disabled) return;

    const pastedData = e.clipboardData.getData("text").replace(/\D/g, "");
    const newValue = pastedData.slice(0, length);

    onChange(newValue);

    // Focus the next empty input or last input
    const nextIndex = Math.min(newValue.length, length - 1);
    inputRefs.current[nextIndex]?.focus();
  };

  const handleFocus = (index: number) => {
    // Select all on focus for easy replacement
    inputRefs.current[index]?.select();
  };

  return (
    <div className={cn("flex gap-2 justify-center", className)}>
      {Array.from({ length }).map((_, index) => (
        <input
          key={index}
          ref={(el) => {
            inputRefs.current[index] = el;
          }}
          type="text"
          inputMode="numeric"
          maxLength={1}
          value={value[index] || ""}
          onChange={(e) => handleChange(index, e.target.value)}
          onKeyDown={(e) => handleKeyDown(index, e)}
          onPaste={handlePaste}
          onFocus={() => handleFocus(index)}
          disabled={disabled}
          className={cn(
            "w-10 h-12 text-center text-xl font-mono font-bold rounded-lg border-2 transition-all",
            "focus:outline-none focus:ring-2 focus:ring-[#2563EB] focus:border-[#2563EB]",
            value[index]
              ? "border-[#2563EB] bg-blue-50 text-[#1E40AF]"
              : "border-gray-300 bg-white text-gray-900",
            disabled && "opacity-50 cursor-not-allowed bg-gray-100",
            "hover:border-gray-400",
            "sm:w-11 sm:h-13 sm:text-2xl"
          )}
          aria-label={`Digit ${index + 1}`}
        />
      ))}
    </div>
  );
}
