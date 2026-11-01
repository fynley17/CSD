import { describe, expect, it } from 'vitest';
import { pdfFileName } from '../utils/exportTestPdf';

describe('pdfFileName', () => {
    it('names the PDF after the test and the student who completed it', () => {
        // Arrange
        const test = { testName: 'Web Apps Test', studentName: 'Ada Lovelace' };

        // Act
        const fileName = pdfFileName(test);

        // Assert
        expect(fileName).toBe('Web Apps Test - Ada Lovelace.pdf');
    });

    it('removes characters that are not allowed in file names', () => {
        // Arrange
        const test = { testName: 'HTML/CSS: Basics?', studentName: 'Ada Lovelace' };

        // Act
        const fileName = pdfFileName(test);

        // Assert
        expect(fileName).toBe('HTMLCSS Basics - Ada Lovelace.pdf');
    });
});
