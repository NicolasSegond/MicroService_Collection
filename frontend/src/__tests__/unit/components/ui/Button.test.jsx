import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import Button from '../../../../components/ui/Button.jsx';

const MockIcon = ({ size }) => <svg data-testid="btn-icon" data-size={size} />;

describe('Button', () => {
    it('renders children', () => {
        render(<Button>Acheter</Button>);
        expect(screen.getByRole('button', { name: 'Acheter' })).toBeInTheDocument();
    });

    it('applies default variant and size classes', () => {
        render(<Button>OK</Button>);
        const button = screen.getByRole('button');
        expect(button).toHaveClass('btn', 'btn-primary', 'btn-md');
    });

    it('applies custom variant and size classes', () => {
        render(<Button variant="secondary" size="sm">OK</Button>);
        const button = screen.getByRole('button');
        expect(button).toHaveClass('btn-secondary', 'btn-sm');
    });

    it('applies fullWidth class when enabled', () => {
        render(<Button fullWidth>OK</Button>);
        expect(screen.getByRole('button')).toHaveClass('btn-full');
    });

    it('does not apply fullWidth class by default', () => {
        render(<Button>OK</Button>);
        expect(screen.getByRole('button')).not.toHaveClass('btn-full');
    });

    it('appends custom className', () => {
        render(<Button className="extra">OK</Button>);
        expect(screen.getByRole('button')).toHaveClass('btn', 'extra');
    });

    it('is disabled when disabled prop is true', () => {
        render(<Button disabled>OK</Button>);
        expect(screen.getByRole('button')).toBeDisabled();
    });

    it('shows spinner and is disabled when loading', () => {
        render(<Button loading>Envoyer</Button>);
        const button = screen.getByRole('button');
        expect(button).toBeDisabled();
        expect(button).toHaveClass('btn-loading');
        expect(button.querySelector('.btn-spinner')).toBeInTheDocument();
        expect(screen.queryByText('Envoyer')).not.toBeInTheDocument();
    });

    it('is disabled when both disabled and loading are true', () => {
        render(<Button disabled loading>OK</Button>);
        expect(screen.getByRole('button')).toBeDisabled();
    });

    it('renders icon on the left by default', () => {
        render(<Button icon={MockIcon}>OK</Button>);
        const button = screen.getByRole('button');
        const icon = screen.getByTestId('btn-icon');
        const textSpan = screen.getByText('OK');
        expect(button.firstChild).toBe(icon);
        expect(icon.compareDocumentPosition(textSpan) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    });

    it('renders icon on the right when iconPosition is right', () => {
        render(<Button icon={MockIcon} iconPosition="right">OK</Button>);
        const icon = screen.getByTestId('btn-icon');
        const textSpan = screen.getByText('OK');
        expect(icon.compareDocumentPosition(textSpan) & Node.DOCUMENT_POSITION_PRECEDING).toBeTruthy();
    });

    it('renders icon with size 18 for default (md) size', () => {
        render(<Button icon={MockIcon}>OK</Button>);
        expect(screen.getByTestId('btn-icon')).toHaveAttribute('data-size', '18');
    });

    it('renders icon with size 16 for sm size', () => {
        render(<Button icon={MockIcon} size="sm">OK</Button>);
        expect(screen.getByTestId('btn-icon')).toHaveAttribute('data-size', '16');
    });

    it('does not render icon when loading', () => {
        render(<Button icon={MockIcon} loading>OK</Button>);
        expect(screen.queryByTestId('btn-icon')).not.toBeInTheDocument();
    });

    it('calls onClick handler when clicked', () => {
        const handleClick = vi.fn();
        render(<Button onClick={handleClick}>OK</Button>);
        fireEvent.click(screen.getByRole('button'));
        expect(handleClick).toHaveBeenCalledOnce();
    });

    it('does not call onClick when disabled', () => {
        const handleClick = vi.fn();
        render(<Button onClick={handleClick} disabled>OK</Button>);
        fireEvent.click(screen.getByRole('button'));
        expect(handleClick).not.toHaveBeenCalled();
    });
});
