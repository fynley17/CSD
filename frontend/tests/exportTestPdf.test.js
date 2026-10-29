import { describe, expect, it } from 'vitest';
import { pdfFileName } from '../utils/exportTestPdf';

describe('pdfFileName', () => {
    it('names the PDF after the test and the student who completed it', () => {
        expect(pdfFileName({ testName: 'Web Apps Test', studentName: 'Ada Lovelace' })).toBe(
            'Web Apps Test - Ada Lovelace.pdf',
        );
    });

    it('removes characters that are not allowed in file names', () => {
        expect(pdfFileName({ testName: 'HTML/CSS: Basics?', studentName: 'Ada Lovelace' })).toBe(
            'HTMLCSS Basics - Ada Lovelace.pdf',
        );
    });
});
