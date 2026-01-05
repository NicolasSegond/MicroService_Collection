import React from 'react';

const FormInput = ({ label, icon: Icon, error, id, ...props }) => {
    const inputId = id || props.name;

    return (
        <div className="form-group">
            {label && (
                <label
                    htmlFor={inputId}
                    style={{ display: 'flex', alignItems: 'center', gap: '8px' }}
                >
                    {Icon && <Icon size={16} />}
                    {label}
                </label>
            )}
            <input
                id={inputId}
                className={`modern-input ${error ? 'input-error' : ''}`}
                {...props}
            />
            {error && <span className="field-error" style={{color: '#d32f2f', fontSize: '0.85rem', marginTop: '0.25rem', display: 'block'}}>{error}</span>}
        </div>
    );
};

export default FormInput;