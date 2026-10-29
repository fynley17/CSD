// Page layout in millimetres, A4 portrait.
const MARGIN = 16;
const FOOTER_SPACE = 20;
const POINTS_TO_MM = 0.3528;
const LINE_HEIGHT_FACTOR = 1.3;

// The same colours as main.css, so the export looks like the rest of RevisionHub.
const COLOURS = {
    brand: '#1e3a8a',
    highlight: '#93c5fd',
    infoLight: '#dbeafe',
    primaryLight: '#eef2ff',
    surface: '#ffffff',
    surfaceAlt: '#f9fafb',
    text: '#111827',
    secondary: '#4b5563',
    divider: '#d1d5db',
    success: '#15803d',
    successLight: '#dcfce7',
    error: '#b91c1c',
    errorLight: '#fee2e2',
    warning: '#b45309',
    warningLight: '#fef3c7',
};

// Characters Windows and macOS won't allow in a file name.
const UNSAFE_FILE_CHARACTERS = /[\\/:*?"<>|]/g;

// "Web Apps Test - Ada Lovelace.pdf", the test and the student who completed it.
export function pdfFileName(review) {
    const name = `${review.testName} - ${review.studentName}`.replace(UNSAFE_FILE_CHARACTERS, '').trim();
    return `${name}.pdf`;
}

// Same wording as the testing page's results review, unanswered gets amber so it
// stands apart from a wrong answer.
function questionStatus(question) {
    if (question.isCorrect) {
        return { text: 'Correct', colour: COLOURS.success, background: COLOURS.successLight };
    }
    if (question.selectedAnswerID === null) {
        return { text: 'Not answered', colour: COLOURS.warning, background: COLOURS.warningLight };
    }
    return { text: 'Incorrect', colour: COLOURS.error, background: COLOURS.errorLight };
}

// Green for the correct answer, red for the wrong one the student picked, with a
// text label as well so it still reads when printed in black and white.
function answerStyle(question, answer) {
    const isSelected = answer.answerID === question.selectedAnswerID;

    if (answer.isCorrect) {
        return {
            label: isSelected ? 'Your answer - correct' : 'Correct answer',
            colour: COLOURS.success,
            background: COLOURS.successLight,
        };
    }
    if (isSelected) {
        return { label: 'Your answer', colour: COLOURS.error, background: COLOURS.errorLight };
    }
    return { label: '', colour: COLOURS.text, background: COLOURS.surface };
}

function formatPercentage(value) {
    return `${Number.isInteger(value) ? value : value.toFixed(1)}%`;
}

function formatPoints(value) {
    return `${value > 0 ? '+' : ''}${value} PTS`;
}

function formatDate(value) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value;
    }
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(date);
}

// Draws the results PDF: a header, the mark, then a card per question with the
// student's answer and the correct answer. JsPDF is passed in so the layout can be
// built without downloading the library up front.
export function buildTestPdf(JsPDF, review) {
    const doc = new JsPDF({ unit: 'mm', format: 'a4' });
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    const contentWidth = pageWidth - MARGIN * 2;
    const contentBottom = pageHeight - FOOTER_SPACE;
    let y = 0;

    function setFont(size, style = 'normal', colour = COLOURS.text) {
        doc.setFont('helvetica', style);
        doc.setFontSize(size);
        doc.setTextColor(colour);
    }

    function lineHeight(size) {
        return size * POINTS_TO_MM * LINE_HEIGHT_FACTOR;
    }

    // Wrapped lines and their height, so blocks can be measured before they're drawn.
    function wrap(text, width, size, style = 'normal') {
        setFont(size, style);
        const lines = doc.splitTextToSize(String(text), width);
        return { lines, height: lines.length * lineHeight(size) };
    }

    function drawLines(lines, x, top, size, style, colour, align = 'left') {
        setFont(size, style, colour);
        doc.text(lines, x, top, { baseline: 'top', lineHeightFactor: LINE_HEIGHT_FACTOR, align });
    }

    // Starts a new page when the next block won't fit, unless the page is still empty.
    function ensureSpace(height) {
        if (y + height > contentBottom && y > MARGIN) {
            doc.addPage();
            y = MARGIN;
        }
    }

    // Header band across the top of the first page.
    const title = wrap(review.testName, contentWidth, 20, 'bold');
    const headerHeight = 30 + title.height;
    doc.setFillColor(COLOURS.brand);
    doc.rect(0, 0, pageWidth, headerHeight, 'F');
    drawLines('REVISIONHUB  |  TEST RESULTS', MARGIN, 10, 8, 'bold', COLOURS.highlight);
    drawLines(title.lines, MARGIN, 16, 20, 'bold', COLOURS.surface);
    drawLines(`Completed by ${review.studentName}`, MARGIN, 18 + title.height, 11, 'normal', COLOURS.infoLight);
    y = headerHeight + 8;

    // Subject and date as small labelled columns.
    const details = [
        ['SUBJECT', review.subjectName],
        ['COMPLETED', formatDate(review.completedDate)],
    ];
    const detailWidth = contentWidth / details.length;
    details.forEach(([label, value], index) => {
        const x = MARGIN + index * detailWidth;
        drawLines(label, x, y, 8, 'bold', COLOURS.secondary);
        drawLines(value, x, y + 4.5, 11, 'normal', COLOURS.text);
    });
    y += 14;

    // Three score cards, the same figures as the results screen.
    let pointsColour = COLOURS.brand;
    if (review.pointsEarned > 0) {
        pointsColour = COLOURS.success;
    } else if (review.pointsEarned < 0) {
        pointsColour = COLOURS.error;
    }
    const stats = [
        ['MARK', `${review.correctCount}/${review.questionCount}`, COLOURS.brand],
        ['SCORE', formatPercentage(review.percentageCorrect), COLOURS.brand],
        ['POINTS', formatPoints(review.pointsEarned), pointsColour],
    ];
    const statGap = 4;
    const statWidth = (contentWidth - statGap * (stats.length - 1)) / stats.length;
    const statHeight = 20;
    stats.forEach(([label, value, colour], index) => {
        const x = MARGIN + index * (statWidth + statGap);
        doc.setFillColor(COLOURS.primaryLight);
        doc.roundedRect(x, y, statWidth, statHeight, 2, 2, 'F');
        drawLines(label, x + 5, y + 4, 8, 'bold', COLOURS.secondary);
        drawLines(value, x + 5, y + 9, 16, 'bold', colour);
    });
    y += statHeight + 10;

    drawLines('Question review', MARGIN, y, 14, 'bold', COLOURS.text);
    y += 9;

    // One card per question, measured first so a card is never split across pages.
    const cardPadding = 5;
    const accentWidth = 1.5;
    const innerX = MARGIN + accentWidth + cardPadding;
    const innerWidth = contentWidth - accentWidth - cardPadding * 2;
    const rowPadding = 2.5;
    const rowGap = 2;
    const pillHeight = 6;

    review.questions.forEach((question, index) => {
        const status = questionStatus(question);
        setFont(8, 'bold');
        const pillWidth = doc.getTextWidth(status.text) + 6;
        const questionText = wrap(`${index + 1}. ${question.question}`, innerWidth - pillWidth - 4, 11, 'bold');

        const rows = question.answers.map((answer, answerIndex) => {
            const style = answerStyle(question, answer);
            setFont(8, 'bold');
            const labelWidth = style.label ? doc.getTextWidth(style.label) + 4 : 0;
            const letter = `${String.fromCharCode(65 + answerIndex)}.`;
            const text = wrap(answer.answerText, innerWidth - rowPadding * 2 - 6 - labelWidth, 10);
            return { ...style, letter, text, height: text.height + rowPadding * 2 };
        });

        const answersHeight = rows.reduce((total, row) => total + row.height + rowGap, 0) - rowGap;
        const headerRowHeight = Math.max(questionText.height, pillHeight);
        const cardHeight = cardPadding * 2 + headerRowHeight + 4 + Math.max(answersHeight, 0);

        ensureSpace(cardHeight);

        doc.setFillColor(COLOURS.surfaceAlt);
        doc.setDrawColor(COLOURS.divider);
        doc.setLineWidth(0.2);
        doc.rect(MARGIN, y, contentWidth, cardHeight, 'FD');
        doc.setFillColor(status.colour);
        doc.rect(MARGIN, y, accentWidth, cardHeight, 'F');

        let cardY = y + cardPadding;
        drawLines(questionText.lines, innerX, cardY, 11, 'bold', COLOURS.text);

        const pillX = innerX + innerWidth - pillWidth;
        doc.setFillColor(status.background);
        doc.roundedRect(pillX, cardY, pillWidth, pillHeight, 3, 3, 'F');
        drawLines(status.text, pillX + pillWidth / 2, cardY + 1.5, 8, 'bold', status.colour, 'center');

        cardY += headerRowHeight + 4;

        for (const row of rows) {
            doc.setFillColor(row.background);
            doc.setDrawColor(row.label ? row.colour : COLOURS.divider);
            doc.roundedRect(innerX, cardY, innerWidth, row.height, 1.5, 1.5, 'FD');

            const textY = cardY + rowPadding;
            drawLines(row.letter, innerX + rowPadding, textY, 10, 'bold', COLOURS.secondary);
            drawLines(row.text.lines, innerX + rowPadding + 6, textY, 10, row.label ? 'bold' : 'normal', row.colour);
            if (row.label) {
                drawLines(row.label, innerX + innerWidth - rowPadding, textY + 0.5, 8, 'bold', row.colour, 'right');
            }

            cardY += row.height + rowGap;
        }

        y += cardHeight + 5;
    });

    // Footer on every page, added last so the page count is known.
    const pageCount = doc.getNumberOfPages();
    for (let page = 1; page <= pageCount; page++) {
        doc.setPage(page);
        doc.setDrawColor(COLOURS.divider);
        doc.setLineWidth(0.2);
        doc.line(MARGIN, pageHeight - 13, pageWidth - MARGIN, pageHeight - 13);
        setFont(8);
        const footer = doc.splitTextToSize(
            `RevisionHub  |  ${review.testName}  |  ${review.studentName}`,
            contentWidth - 30,
        );
        drawLines(footer[0], MARGIN, pageHeight - 10, 8, 'normal', COLOURS.secondary);
        drawLines(
            `Page ${page} of ${pageCount}`,
            pageWidth - MARGIN,
            pageHeight - 10,
            8,
            'normal',
            COLOURS.secondary,
            'right',
        );
    }

    return doc;
}

// Builds the PDF and downloads it, named after the test and the student.
export async function exportTestPdf(review) {
    // jsPDF is large, so it's only downloaded the first time someone exports
    const { jsPDF } = await import('jspdf');
    buildTestPdf(jsPDF, review).save(pdfFileName(review));
}
