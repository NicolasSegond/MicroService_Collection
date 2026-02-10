import { render, screen } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import Badge from '../../../../components/ui/Badge.jsx';

const MockIcon = ({ size }) => <svg data-testid="badge-icon" data-size={size} />;

describe('Badge', () => {
    it('renders children', () => {
        render(<Badge>Neuf</Badge>);
        expect(screen.getByText('Neuf')).toBeInTheDocument();
    });

    it('applies default variant and size classes', () => {
        render(<Badge>Tag</Badge>);
        const badge = screen.getByText('Tag');
        expect(badge).toHaveClass('badge', 'badge-default', 'badge-md');
    });

    it('applies custom variant and size classes', () => {
        render(<Badge variant="success" size="sm">OK</Badge>);
        const badge = screen.getByText('OK');
        expect(badge).toHaveClass('badge-success', 'badge-sm');
    });

    it('appends custom className', () => {
        render(<Badge className="extra">Tag</Badge>);
        expect(screen.getByText('Tag')).toHaveClass('badge', 'extra');
    });

    it('renders icon with correct size for default (md)', () => {
        render(<Badge icon={MockIcon}>Tag</Badge>);
        const icon = screen.getByTestId('badge-icon');
        expect(icon).toHaveAttribute('data-size', '14');
    });

    it('renders icon with size 12 for sm', () => {
        render(<Badge icon={MockIcon} size="sm">Tag</Badge>);
        const icon = screen.getByTestId('badge-icon');
        expect(icon).toHaveAttribute('data-size', '12');
    });

    it('does not render icon when not provided', () => {
        render(<Badge>Tag</Badge>);
        expect(screen.queryByTestId('badge-icon')).not.toBeInTheDocument();
    });

    it('passes extra props to the span element', () => {
        render(<Badge data-testid="custom-badge">Tag</Badge>);
        expect(screen.getByTestId('custom-badge')).toBeInTheDocument();
    });
});
