import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import { Mail } from 'lucide-react'; // Import corrigé (Mail vient de lucide, pas FormInput)
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
            // Utilisation de getByRole('textbox') pour cibler l'input réel
            expect(screen.getByRole('textbox')).toHaveClass('input-error');
        });

        it('gère les changements de valeur', () => {
            const handleChange = vi.fn();
            render(<FormInput onChange={handleChange} placeholder="Entrez un texte" />);

            // On cible l'élément textbox (l'input)
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

            // Utilisation du TestId que nous avons ajouté au bouton de suppression
            const removeBtn = screen.getByTestId('remove-image-button');
            fireEvent.click(removeBtn);
            expect(handleRemove).toHaveBeenCalled();
        });

        it('déclenche l\'input file au clic', () => {
            render(<ImageDropzone />);

            // On cherche par le label "Zone d'upload" défini dans le composant
            const dropzone = screen.getByRole('button', { name: /zone d'upload/i });
            const fileInput = dropzone.querySelector('input[type="file"]');

            // On mock la méthode click de l'élément HTML input
            const clickSpy = vi.spyOn(fileInput, 'click');

            fireEvent.click(dropzone);
            expect(clickSpy).toHaveBeenCalled();
        });
    });
});