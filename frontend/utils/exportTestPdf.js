// Page layout in millimetres, A4 portrait.
const MARGIN = 18;
const LINE_HEIGHT = 6;
const COLOURS = {
    text: [33, 37, 41],
    secondary: [108, 117, 125],
    correct: [25, 135, 84],
    wrong: [220, 53, 69],
};

// Characters Windows and macOS won't allow in a file name.
const UNSAFE_FILE_CHARACTERS = /[\\/:*?"<>|]/g;

// "Web Apps Test - Ada Lovelace.pdf", the test and the student who completed it.
export function pdfFileName(review) {
    const name = `${review.testName} - ${review.studentName}`.replace(UNSAFE_FILE_CHARACTERS, '').trim();
    return `${name}.pdf`;
}

// Same wording as the testing page's results review.
function questionStatus(question) {
    if (question.isCorrect) {
        return 'Correct';
    }
    return question.selectedAnswerID === null ? 'Not answered' : 'Incorrect';
}

function formatPercentage(value) {
    return `${Number.isInteger(value) ? value : value.toFixed(1)}%`;
}

function formatDate(value) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value;
    }
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(date);
}

// Builds a PDF like the results screen: the mark, then every question with the
// student's answer and the correct answer, and downloads it.
export async function exportTestPdf(review) {
    // jsPDF is large, so it's only downloaded the first time someone exports
    const { jsPDF } = await import('jspdf');
    const doc = new jsPDF({ unit: 'mm', format: 'a4' });
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    const textWidth = pageWidth - MARGIN * 2;
    let y = MARGIN;

    // Wraps the text to the page width and starts a new page when it would run off the bottom.
    function write(text, { size = 11, style = 'normal', colour = COLOURS.text, indent = 0 } = {}) {
        doc.setFont('helvetica', style);
        doc.setFontSize(size);
        doc.setTextColor(...colour);

        const lines = doc.splitTextToSize(text, textWidth - indent);
        const height = lines.length * LINE_HEIGHT * (size / 11);
        if (y + height > pageHeight - MARGIN) {
            doc.addPage();
            y = MARGIN;
        }

        doc.text(lines, MARGIN + indent, y, { baseline: 'top' });
        y += height;
    }

    write(`${review.testName} results`, { size: 18, style: 'bold' });
    y += 2;
    write(`Student: ${review.studentName}`);
    write(`Subject: ${review.subjectName}`);
    write(`Completed: ${formatDate(review.completedDate)}`);
    write(`Mark: ${review.correctCount}/${review.questionCount} (${formatPercentage(review.percentageCorrect)})`, {
        style: 'bold',
    });
    write(`Points: ${review.pointsEarned > 0 ? '+' : ''}${review.pointsEarned} PTS`);

    y += 4;
    write('Question review', { size: 14, style: 'bold' });
    y += 2;

    review.questions.forEach((question, index) => {
        write(`${index + 1}. ${question.question}`, { style: 'bold' });
        write(questionStatus(question), {
            size: 10,
            style: 'bold',
            colour: question.isCorrect ? COLOURS.correct : COLOURS.wrong,
        });

        // text labels as well as colour, so the export still reads when printed in black and white
        for (const answer of question.answers) {
            const labels = [];
            if (answer.answerID === question.selectedAnswerID) {
                labels.push('Your answer');
            }
            if (answer.isCorrect) {
                labels.push('Correct answer');
            }

            let colour = COLOURS.secondary;
            if (answer.isCorrect) {
                colour = COLOURS.correct;
            } else if (answer.answerID === question.selectedAnswerID) {
                colour = COLOURS.wrong;
            }

            const suffix = labels.length > 0 ? `  (${labels.join(', ')})` : '';
            write(`- ${answer.answerText}${suffix}`, {
                colour,
                style: labels.length > 0 ? 'bold' : 'normal',
                indent: 5,
            });
        }

        y += 4;
    });

    doc.save(pdfFileName(review));
}
