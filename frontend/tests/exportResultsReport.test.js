import { jsPDF } from 'jspdf';
import { describe, expect, it } from 'vitest';
import { buildTestPdf } from '../utils/exportTestPdf';
import { bestAndWorstTests, buildResultsReport, reportFileName, testAverages } from '../utils/exportResultsReport';

function attempt(testID, testName, percentageCorrect) {
    return {
        completedID: `${testID}-${percentageCorrect}`,
        testID,
        testName,
        testDescription: 'Review',
        subjectName: 'Web Apps',
        completedDate: '2026-10-20 09:00:00',
        questionCount: 10,
        pointsEarned: 30,
        percentageCorrect,
    };
}

const summary = {
    studentName: 'Ada Lovelace',
    studentNumber: 'WS000001',
    totalPoints: 800,
    averageScore: 60.3,
    testsCompleted: 3,
};

function pageText(doc) {
    return doc.output();
}

describe('testAverages', () => {
    it('averages every attempt at the same test, highest first', () => {
        // Arrange
        const tests = [attempt('a', 'Algebra', 40), attempt('b', 'Biology', 70), attempt('a', 'Algebra', 60)];

        // Act
        const averages = testAverages(tests);

        // Assert
        expect(averages.map((test) => [test.testName, test.attempts, test.average])).toEqual([
            ['Biology', 1, 70],
            ['Algebra', 2, 50],
        ]);
    });

    it('keeps two tests with the same name apart', () => {
        // Arrange
        const tests = [attempt('a', 'Quiz', 40), attempt('b', 'Quiz', 80)];

        // Act
        const averages = testAverages(tests);

        // Assert
        expect(averages).toHaveLength(2);
    });
});

describe('bestAndWorstTests', () => {
    it('picks by the average rather than a single attempt', () => {
        // Arrange
        // Algebra has the single highest score, but Biology has the higher average
        const tests = [attempt('a', 'Algebra', 100), attempt('a', 'Algebra', 10), attempt('b', 'Biology', 70)];

        // Act
        const { best, worst } = bestAndWorstTests(testAverages(tests));

        // Assert
        expect(best.map((test) => test.testName)).toEqual(['Biology']);
        expect(worst.map((test) => test.testName)).toEqual(['Algebra']);
    });

    it('names every test that ties', () => {
        // Arrange
        const tests = [attempt('a', 'Algebra', 90), attempt('b', 'Biology', 90), attempt('c', 'Chemistry', 20)];

        // Act
        const { best } = bestAndWorstTests(testAverages(tests));

        // Assert
        expect(best.map((test) => test.testName)).toEqual(['Algebra', 'Biology']);
    });

    it.each([
        ['one test', [attempt('a', 'Algebra', 90)]],
        ['tests with the same average', [attempt('a', 'Algebra', 90), attempt('b', 'Biology', 90)]],
    ])('has no worst test with %s', (_, tests) => {
        // Arrange: tests comes from the list above

        // Act
        const { worst } = bestAndWorstTests(testAverages(tests));

        // Assert
        expect(worst).toEqual([]);
    });
});

describe('reportFileName', () => {
    it('names the file after the student without characters file names can not use', () => {
        // Arrange
        const studentName = 'Ada/Lovelace?';

        // Act
        const fileName = reportFileName(studentName);

        // Assert
        expect(fileName).toBe('RevisionHub results - AdaLovelace.pdf');
    });
});

describe('buildResultsReport', () => {
    it('includes the student, the highlights and the attempts', () => {
        // Arrange
        const tests = [attempt('a', 'Algebra', 40), attempt('b', 'Biology', 80.5)];

        // Act
        const text = pageText(buildResultsReport(jsPDF, summary, tests));

        // Assert
        expect(text).toContain('Ada Lovelace');
        expect(text).toContain('BEST TEST');
        expect(text).toContain('WORST TEST');
        expect(text).toContain('Biology');
        expect(text).toContain('80.5%');
        expect(text).toContain('40%');
    });

    it('explains the missing worst test when there is only one', () => {
        // Arrange
        const tests = [attempt('a', 'Algebra', 40)];

        // Act
        const text = pageText(buildResultsReport(jsPDF, summary, tests));

        // Assert
        expect(text).toContain('Every test has the same average score');
    });

    it('runs onto more pages rather than cutting off a long history', () => {
        // Arrange
        const tests = Array.from({ length: 120 }, (_, index) => attempt(`test-${index}`, `Test ${index}`, index % 100));

        // Act
        const doc = buildResultsReport(jsPDF, summary, tests);

        // Assert
        expect(doc.getNumberOfPages()).toBeGreaterThan(1);
        expect(pageText(doc)).toContain('Test 119');
    });
});

describe('buildTestPdf', () => {
    it('still draws a single test now its helpers are shared with the report', () => {
        // Arrange
        const review = {
            testName: 'Web Apps Test',
            studentName: 'Ada Lovelace',
            subjectName: 'Web Apps',
            completedDate: '2026-10-20 09:00:00',
            questionCount: 1,
            correctCount: 1,
            pointsEarned: 30,
            percentageCorrect: 100,
            questions: [
                {
                    question: 'What does HTML stand for?',
                    selectedAnswerID: 'a1',
                    isCorrect: true,
                    answers: [{ answerID: 'a1', answerText: 'HyperText Markup Language', isCorrect: true }],
                },
            ],
        };

        // Act
        const text = pageText(buildTestPdf(jsPDF, review));

        // Assert
        expect(text).toContain('What does HTML stand for?');
        expect(text).toContain('Page 1 of 1');
    });
});
