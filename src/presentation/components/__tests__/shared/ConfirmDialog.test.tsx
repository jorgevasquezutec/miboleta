import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@/test/utils';
import userEvent from '@testing-library/user-event';
import { ConfirmDialog } from '../../shared/ConfirmDialog';

describe('ConfirmDialog', () => {
  const defaultProps = {
    open: true,
    onOpenChange: vi.fn(),
    title: 'Confirm Action',
    description: 'Are you sure you want to continue?',
    onConfirm: vi.fn(),
  };

  it('should render when open', () => {
    render(<ConfirmDialog {...defaultProps} />);

    expect(screen.getByText('Confirm Action')).toBeInTheDocument();
    expect(screen.getByText('Are you sure you want to continue?')).toBeInTheDocument();
  });

  it('should not render when closed', () => {
    render(<ConfirmDialog {...defaultProps} open={false} />);

    expect(screen.queryByText('Confirm Action')).not.toBeInTheDocument();
  });

  it('should display custom confirm and cancel text', () => {
    render(
      <ConfirmDialog
        {...defaultProps}
        confirmText="Yes, delete"
        cancelText="No, keep it"
      />
    );

    expect(screen.getByText('Yes, delete')).toBeInTheDocument();
    expect(screen.getByText('No, keep it')).toBeInTheDocument();
  });

  it('should display default confirm and cancel text', () => {
    render(<ConfirmDialog {...defaultProps} />);

    expect(screen.getByText('Confirmar')).toBeInTheDocument();
    expect(screen.getByText('Cancelar')).toBeInTheDocument();
  });

  it('should call onConfirm when confirm button is clicked', async () => {
    const user = userEvent.setup();
    const onConfirm = vi.fn();

    render(<ConfirmDialog {...defaultProps} onConfirm={onConfirm} />);

    await user.click(screen.getByText('Confirmar'));

    expect(onConfirm).toHaveBeenCalledTimes(1);
  });

  it('should call onOpenChange(false) when confirm is clicked', async () => {
    const user = userEvent.setup();
    const onOpenChange = vi.fn();

    render(<ConfirmDialog {...defaultProps} onOpenChange={onOpenChange} />);

    await user.click(screen.getByText('Confirmar'));

    expect(onOpenChange).toHaveBeenCalledWith(false);
  });

  it('should call onOpenChange(false) when cancel is clicked', async () => {
    const user = userEvent.setup();
    const onOpenChange = vi.fn();

    render(<ConfirmDialog {...defaultProps} onOpenChange={onOpenChange} />);

    await user.click(screen.getByText('Cancelar'));

    expect(onOpenChange).toHaveBeenCalledWith(false);
  });

  it('should not call onConfirm when cancel is clicked', async () => {
    const user = userEvent.setup();
    const onConfirm = vi.fn();

    render(<ConfirmDialog {...defaultProps} onConfirm={onConfirm} />);

    await user.click(screen.getByText('Cancelar'));

    expect(onConfirm).not.toHaveBeenCalled();
  });

  it('should render with destructive variant', () => {
    render(<ConfirmDialog {...defaultProps} variant="destructive" />);

    const confirmButton = screen.getByText('Confirmar');
    expect(confirmButton).toBeInTheDocument();
    // Destructive variant should have specific styling
    expect(confirmButton).toHaveClass('bg-destructive');
  });

  it('should render with default variant', () => {
    render(<ConfirmDialog {...defaultProps} variant="default" />);

    const confirmButton = screen.getByText('Confirmar');
    expect(confirmButton).toBeInTheDocument();
  });
});
