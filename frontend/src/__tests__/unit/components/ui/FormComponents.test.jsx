import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import { Mail } from 'lucide-react';
import FormInput from "../../../../components/ui/FormInput.jsx";
import FormTextarea from "../../../../components/ui/FormTextarea.jsx";
import ImageDropzone from "../../../../components/ui/ImageDropzone.jsx";

describe('Composants UI', () => {

    describe('FormInput', () => {
        it('affiche le label et l\'icône correctement', () => {
            render(<FormInput label="Email" icon={Mail} name="email" />);
            expect(screen.getByText('Email')).toBeInTheDocument();
            expect(document.querySelector('.lucide-mail')).toBeInTheDocument();
        });

        it('affiche une erreur si fournie', () => {
            render(<FormInput label="Email" error="Email invalide" />);
            expect(screen.getByText('Email invalide')).toBeInTheDocument();
            expect(screen.getByRole('textbox')).toHaveClass('input-error');
        });

        it('gère les changements de valeur', () => {
            const handleChange = vi.fn();
            render(<FormInput onChange={handleChange} placeholder="Entrez un texte" />);

            const input = screen.getByRole('textbox');
            fireEvent.change(input, { target: { value: 'test' } });

            expect(handleChange).toHaveBeenCalled();
        });
    });

    describe('FormTextarea', () => {
        it('affiche le label et le textarea', () => {
            render(<FormTextarea label="Description" name="desc" />);
            expect(screen.getByText('Description')).toBeInTheDocument();
            expect(screen.getByRole('textbox')).toBeInTheDocument();
        });

        it('affiche les erreurs', () => {
            render(<FormTextarea error="Requis" />);
            expect(screen.getByText('Requis')).toBeInTheDocument();
        });
    });

    describe('ImageDropzone', () => {
        it('affiche l\'état initial (placeholder)', () => {
            render(<ImageDropzone label="Photo" placeholder="Ajouter" />);
            expect(screen.getByText('Photo')).toBeInTheDocument();
            expect(screen.getByText('Ajouter')).toBeInTheDocument();
            expect(screen.queryByAltText('Preview')).not.toBeInTheDocument();
        });

        it('affiche l\'aperçu quand une image est fournie', () => {
            const handleRemove = vi.fn();
            render(
                <ImageDropzone
                    imagePreview="data:image/png;base64,fake"
                    onRemove={handleRemove}
                />
            );

            const img = screen.getByAltText('Preview');
            expect(img).toBeInTheDocument();
            expect(img).toHaveAttribute('src', 'data:image/png;base64,fake');

            const removeBtn = screen.getByTestId('remove-image-button');
            fireEvent.click(removeBtn);
            expect(handleRemove).toHaveBeenCalled();
        });

        it('déclenche l\'input file au clic', () => {
            render(<ImageDropzone />);

            const dropzone = screen.getByRole('button', { name: /zone d'upload/i });
            const fileInput = dropzone.querySelector('input[type="file"]');

            const clickSpy = vi.spyOn(fileInput, 'click');

            fireEvent.click(dropzone);
            expect(clickSpy).toHaveBeenCalled();
        });
    });
});