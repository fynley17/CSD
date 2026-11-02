import {
    COLOURS,
    FOOTER_SPACE,
    MARGIN,
    UNSAFE_FILE_CHARACTERS,
    drawFooters,
    formatDate,
    formatPercentage,
    pdfTools,
} from './exportTestPdf';

// "RevisionHub results - Ada Lovelace.pdf"
export function reportFileName(studentName) {
    const name = `RevisionHub results - ${studentName}`.replace(UNSAFE_FILE_CHARACTERS, '').trim();
    return `${name}.pdf`;
}

// One row per test with its average across every attempt, highest first.
export function testAverages(tests) {
    const byTest = new Map();

    for (const result of tests) {
        const entry = byTest.get(result.testID) ?? {
            testName: result.testName,
            subjectName: result.subjectName,
            attempts: 0,
            total: 0,
        };
        entry.attempts += 1;
        entry.total += result.percentageCorrect;
        byTest.set(result.testID, entry);
    }

    return [...byTest.values()]
        .map(({ testName, subjectName, attempts, total }) => ({
            testName,
            subjectName,
            attempts,
            average: total / attempts,
        }))
        .sort((a, b) => b.average - a.average || a.testName.localeCompare(b.testName));
}

// Tests that tie are all named, since picking one would be arbitrary. When every test has the
// same average there's nothing to call worst, so worst is left empty.
export function bestAndWorstTests(averages) {
    const highest = averages[0].average;
    const lowest = averages[averages.length - 1].average;

    return {
        best: averages.filter((test) => test.average === highest),
        worst: highest === lowest ? [] : averages.filter((test) => test.average === lowest),
    };
}

// Draws the report: a header, the totals, the best and worst tests, the average for each test,
// then every attempt. JsPDF is passed in so the layout can be built without downloading the
// library up front.
export function buildResultsReport(JsPDF, summary, tests) {
    const doc = new JsPDF({ unit: 'mm', format: 'a4' });
    const { wrap, drawLines } = pdfTools(doc);
    const pageWidth = doc.internal.pageSize.getWidth();
    const contentWidth = pageWidth - MARGIN * 2;
    const contentBottom = doc.internal.pageSize.getHeight() - FOOTER_SPACE;
    const averages = testAverages(tests);
    const { best, worst } = bestAndWorstTests(averages);
    let y = 0;

    // Header band across the top of the first page.
    const headerHeight = 34;
    doc.setFillColor(COLOURS.brand);
    doc.rect(0, 0, pageWidth, headerHeight, 'F');
    drawLines('REVISIONHUB  |  TEST DATA EXPORT', MARGIN, 10, 8, 'bold', COLOURS.highlight);
    drawLines('Your results', MARGIN, 15, 20, 'bold', COLOURS.surface);
    drawLines(`${summary.studentName}  |  ${summary.studentNumber}`, MARGIN, 25, 11, 'normal', COLOURS.infoLight);
    y = headerHeight + 8;

    // Three cards, the same figures as the top of the results page.
    const stats = [
        ['TOTAL POINTS', String(summary.totalPoints)],
        ['AVERAGE SCORE', formatPercentage(summary.averageScore)],
        ['TESTS COMPLETED', String(summary.testsCompleted)],
    ];
    const gap = 4;
    const statWidth = (contentWidth - gap * (stats.length - 1)) / stats.length;
    const statHeight = 20;
    stats.forEach(([label, value], index) => {
        const x = MARGIN + index * (statWidth + gap);
        doc.setFillColor(COLOURS.primaryLight);
        doc.roundedRect(x, y, statWidth, statHeight, 2, 2, 'F');
        drawLines(label, x + 5, y + 4, 8, 'bold', COLOURS.secondary);
        drawLines(value, x + 5, y + 9, 16, 'bold', COLOURS.brand);
    });
    y += statHeight + 10;

    drawLines('Best and worst tests', MARGIN, y, 14, 'bold', COLOURS.text);
    y += 6;
    drawLines('Based on the average score across every attempt at a test.', MARGIN, y, 9, 'normal', COLOURS.secondary);
    y += 7;

    // Side by side and labelled in words, so they still read when printed in black and white.
    const cardWidth = (contentWidth - gap) / 2;
    const cardPadding = 5;
    const namesWidth = cardWidth - cardPadding * 2;
    const highlights = [
        { label: 'BEST TEST', tests: best, colour: COLOURS.success, background: COLOURS.successLight },
        worst.length > 0
            ? { label: 'WORST TEST', tests: worst, colour: COLOURS.error, background: COLOURS.errorLight }
            : { label: 'WORST TEST', tests: [], colour: COLOURS.secondary, background: COLOURS.surfaceAlt },
    ].map((highlight) => ({
        ...highlight,
        text: wrap(
            highlight.tests.length > 0
                ? highlight.tests.map((test) => test.testName).join(', ')
                : 'Every test has the same average score, so none stands out as the worst.',
            namesWidth,
            10,
        ),
    }));
    const cardHeight = cardPadding * 2 + 13 + Math.max(...highlights.map((highlight) => highlight.text.height));
    highlights.forEach((highlight, index) => {
        const x = MARGIN + index * (cardWidth + gap);
        doc.setFillColor(highlight.background);
        doc.roundedRect(x, y, cardWidth, cardHeight, 2, 2, 'F');
        drawLines(highlight.label, x + cardPadding, y + cardPadding, 8, 'bold', highlight.colour);
        const score = highlight.tests.length > 0 ? formatPercentage(highlight.tests[0].average) : '–';
        drawLines(score, x + cardPadding, y + cardPadding + 4, 16, 'bold', highlight.colour);
        drawLines(highlight.text.lines, x + cardPadding, y + cardPadding + 13, 10, 'normal', COLOURS.text);
    });
    y += cardHeight + 10;

    // The heading row is repeated on each page, and a row is measured first so it's never split.
    function drawTable(title, columns, rows) {
        const cellPadding = 2;
        const headerRowHeight = 7;

        function drawHeaderRow() {
            doc.setFillColor(COLOURS.primaryLight);
            doc.rect(MARGIN, y, contentWidth, headerRowHeight, 'F');
            let x = MARGIN;
            for (const column of columns) {
                drawLines(column.label, x + cellPadding, y + 2, 8, 'bold', COLOURS.secondary);
                x += column.width;
            }
            y += headerRowHeight;
        }

        // keeps the title with the start of its table
        if (y + 30 > contentBottom) {
            doc.addPage();
            y = MARGIN;
        }
        drawLines(title, MARGIN, y, 14, 'bold', COLOURS.text);
        y += 8;
        drawHeaderRow();

        for (const row of rows) {
            const cells = row.cells.map((cell, index) => wrap(cell, columns[index].width - cellPadding * 2, 9));
            const rowHeight = Math.max(...cells.map((cell) => cell.height)) + cellPadding * 2;

            if (y + rowHeight > contentBottom) {
                doc.addPage();
                y = MARGIN;
                drawHeaderRow();
            }

            if (row.background) {
                doc.setFillColor(row.background);
                doc.rect(MARGIN, y, contentWidth, rowHeight, 'F');
            }

            let x = MARGIN;
            cells.forEach((cell, index) => {
                drawLines(cell.lines, x + cellPadding, y + cellPadding, 9, 'normal', COLOURS.text);
                x += columns[index].width;
            });

            y += rowHeight;
            doc.setDrawColor(COLOURS.divider);
            doc.setLineWidth(0.2);
            doc.line(MARGIN, y, MARGIN + contentWidth, y);
        }

        y += 10;
    }

    drawTable(
        'Average score by test',
        [
            { label: 'TEST', width: contentWidth - 86 },
            { label: 'SUBJECT', width: 46 },
            { label: 'ATTEMPTS', width: 20 },
            { label: 'AVERAGE', width: 20 },
        ],
        averages.map((test) => {
            let background;
            if (best.includes(test)) {
                background = COLOURS.successLight;
            } else if (worst.includes(test)) {
                background = COLOURS.errorLight;
            }
            return {
                background,
                cells: [test.testName, test.subjectName, String(test.attempts), formatPercentage(test.average)],
            };
        }),
    );

    drawTable(
        'Every attempt, newest first',
        [
            { label: 'TEST', width: contentWidth - 126 },
            { label: 'SUBJECT', width: 34 },
            { label: 'COMPLETED', width: 38 },
            { label: 'QUESTIONS', width: 20 },
            { label: 'POINTS', width: 17 },
            { label: 'SCORE', width: 17 },
        ],
        tests.map((result) => ({
            cells: [
                result.testName,
                result.subjectName,
                formatDate(result.completedDate),
                String(result.questionCount),
                String(result.pointsEarned),
                formatPercentage(result.percentageCorrect),
            ],
        })),
    );

    drawFooters(doc, `RevisionHub  |  Test data export  |  ${summary.studentName}`);

    return doc;
}

// Builds the report and downloads it. tests is newest first, as the API sends them.
export async function exportResultsReport(summary, tests) {
    // jsPDF is large, so it's only downloaded the first time someone exports
    const { jsPDF } = await import('jspdf');
    buildResultsReport(jsPDF, summary, tests).save(reportFileName(summary.studentName));
}
