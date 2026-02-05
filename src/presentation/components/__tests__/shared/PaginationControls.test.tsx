import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/utils';
import userEvent from '@testing-library/user-event';
import { PaginationControls } from '../../shared/PaginationControls';

describe('PaginationControls', () => {
  const defaultProps = {
    currentPage: 1,
    totalPages: 10,
    total: 100,
    perPage: 10,
    onPageChange: vi.fn(),
  };

  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('rendering', () => {
    it('should display page info by default', () => {
      render(<PaginationControls {...defaultProps} />);

      expect(screen.getByText(/Página 1 de 10/)).toBeInTheDocument();
      expect(screen.getByText(/100 registros/)).toBeInTheDocument();
    });

    it('should hide page info when showInfo is false', () => {
      render(<PaginationControls {...defaultProps} showInfo={false} />);

      expect(screen.queryByText(/Página 1 de 10/)).not.toBeInTheDocument();
    });

    it('should display per page selector when onPerPageChange provided', () => {
      render(
        <PaginationControls
          {...defaultProps}
          onPerPageChange={vi.fn()}
        />
      );

      expect(screen.getByRole('combobox')).toBeInTheDocument();
    });

    it('should hide per page selector when showPerPageSelector is false', () => {
      render(
        <PaginationControls
          {...defaultProps}
          showPerPageSelector={false}
          onPerPageChange={vi.fn()}
        />
      );

      expect(screen.queryByRole('combobox')).not.toBeInTheDocument();
    });
  });

  describe('navigation', () => {
    it('should call onPageChange when clicking next', async () => {
      const user = userEvent.setup();
      const onPageChange = vi.fn();

      render(
        <PaginationControls
          {...defaultProps}
          currentPage={1}
          onPageChange={onPageChange}
        />
      );

      await user.click(screen.getByLabelText(/página siguiente/i));

      expect(onPageChange).toHaveBeenCalledWith(2);
    });

    it('should call onPageChange when clicking previous', async () => {
      const user = userEvent.setup();
      const onPageChange = vi.fn();

      render(
        <PaginationControls
          {...defaultProps}
          currentPage={5}
          onPageChange={onPageChange}
        />
      );

      await user.click(screen.getByLabelText(/página anterior/i));

      expect(onPageChange).toHaveBeenCalledWith(4);
    });

    it('should disable previous on first page', () => {
      render(
        <PaginationControls {...defaultProps} currentPage={1} />
      );

      const prevButton = screen.getByLabelText(/página anterior/i);
      expect(prevButton).toHaveClass('pointer-events-none');
    });

    it('should disable next on last page', () => {
      render(
        <PaginationControls {...defaultProps} currentPage={10} />
      );

      const nextButton = screen.getByLabelText(/página siguiente/i);
      expect(nextButton).toHaveClass('pointer-events-none');
    });

    it('should not call onPageChange when clicking disabled previous', async () => {
      const user = userEvent.setup();
      const onPageChange = vi.fn();

      render(
        <PaginationControls
          {...defaultProps}
          currentPage={1}
          onPageChange={onPageChange}
        />
      );

      await user.click(screen.getByLabelText(/página anterior/i));

      expect(onPageChange).not.toHaveBeenCalled();
    });
  });

  describe('per page selection', () => {
    it('should call onPerPageChange when selecting a new value', async () => {
      const user = userEvent.setup();
      const onPerPageChange = vi.fn();

      render(
        <PaginationControls
          {...defaultProps}
          onPerPageChange={onPerPageChange}
        />
      );

      await user.click(screen.getByRole('combobox'));
      await user.click(screen.getByRole('option', { name: '20' }));

      expect(onPerPageChange).toHaveBeenCalledWith(20);
    });

    it('should display custom perPage options', async () => {
      const user = userEvent.setup();

      render(
        <PaginationControls
          {...defaultProps}
          perPageOptions={[25, 50, 100]}
          onPerPageChange={vi.fn()}
        />
      );

      await user.click(screen.getByRole('combobox'));

      expect(screen.getByRole('option', { name: '25' })).toBeInTheDocument();
      expect(screen.getByRole('option', { name: '50' })).toBeInTheDocument();
      expect(screen.getByRole('option', { name: '100' })).toBeInTheDocument();
    });
  });

  describe('disabled state', () => {
    it('should disable all controls when disabled is true', () => {
      render(
        <PaginationControls
          {...defaultProps}
          currentPage={5}
          disabled={true}
        />
      );

      const prevButton = screen.getByLabelText(/página anterior/i);
      const nextButton = screen.getByLabelText(/página siguiente/i);

      expect(prevButton).toHaveClass('pointer-events-none');
      expect(nextButton).toHaveClass('pointer-events-none');
    });
  });

  describe('edge cases', () => {
    it('should handle zero pages gracefully', () => {
      render(
        <PaginationControls
          {...defaultProps}
          currentPage={1}
          totalPages={0}
          total={0}
        />
      );

      expect(screen.getByText(/Página 1 de 1/)).toBeInTheDocument();
      expect(screen.getByText(/0 registros/)).toBeInTheDocument();
    });

    it('should handle single page', () => {
      render(
        <PaginationControls
          {...defaultProps}
          currentPage={1}
          totalPages={1}
          total={5}
        />
      );

      const prevButton = screen.getByLabelText(/página anterior/i);
      const nextButton = screen.getByLabelText(/página siguiente/i);

      expect(prevButton).toHaveClass('pointer-events-none');
      expect(nextButton).toHaveClass('pointer-events-none');
    });
  });
});
