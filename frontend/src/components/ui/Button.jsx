import './Button.css';

export default function Button({
    children,
    variant = 'primary',
    size = 'md',
    icon: Icon,
    iconPosition = 'left',
    fullWidth = false,
    disabled = false,
    loading = false,
    className = '',
    ...props
}) {
    const classes = [
        'btn',
        `btn-${variant}`,
        `btn-${size}`,
        fullWidth && 'btn-full',
        loading && 'btn-loading',
        className
    ].filter(Boolean).join(' ');

    return (
        <button className={classes} disabled={disabled || loading} {...props}>
            {loading ? (
                <span className="btn-spinner" />
            ) : (
                <>
                    {Icon && iconPosition === 'left' && <Icon size={size === 'sm' ? 16 : 18} />}
                    {children && <span>{children}</span>}
                    {Icon && iconPosition === 'right' && <Icon size={size === 'sm' ? 16 : 18} />}
                </>
            )}
        </button>
    );
}
