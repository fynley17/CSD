<template>
    <!-- Loading the test, or it couldn't be loaded -->
    <section v-if="phase === 'loading' || phase === 'loadError'" class="testingScreen">
        <div class="testingPanel text-center">
            <p v-if="phase === 'loading'" class="text-secondary mb-0" role="status">Loading test...</p>
            <template v-else>
                <p class="alert alert-danger" role="alert">{{ loadError }}</p>
                <RouterLink :to="{ name: 'testSelection' }" class="btn testingPrimaryButton">Back to tests</RouterLink>
            </template>
        </div>
    </section>

    <!-- Start screen, the timer doesn't run until the student is ready -->
    <section v-else-if="phase === 'intro'" class="testingScreen" aria-labelledby="introTitle">
        <div class="testingPanel">
            <span class="subjectBadge">{{ test.subjectName }}</span>
            <h1 id="introTitle" class="introTitle">{{ test.testName }}</h1>
            <p v-if="test.testDescription" class="text-secondary">{{ test.testDescription }}</p>

            <dl class="introDetails">
                <div>
                    <dt>Questions</dt>
                    <dd>{{ test.questionCount }}</dd>
                </div>
                <div>
                    <dt>Time limit</dt>
                    <dd>{{ hasTimeLimit ? formatTimeLimit(test.timeLimit) : 'No time limit' }}</dd>
                </div>
            </dl>

            <p class="introHint">
                Pick an answer to move on to the next question, you can't go back.
                <template v-if="hasTimeLimit">When the time runs out the test is handed in automatically.</template>
            </p>

            <div class="introActions">
                <RouterLink :to="{ name: 'testSelection' }" class="btn testingSecondaryButton">Back</RouterLink>
                <button type="button" class="btn testingPrimaryButton" @click="startTest">Start Test</button>
            </div>
        </div>
    </section>

    <!-- Marking the test, or the answers couldn't be saved -->
    <section v-else-if="phase === 'submitting'" class="testingScreen">
        <div class="testingPanel text-center">
            <template v-if="submitError">
                <p class="alert alert-danger" role="alert">{{ submitError }}</p>
                <!-- the answers are kept, so trying again doesn't lose them -->
                <button type="button" class="btn testingPrimaryButton" @click="finishTest">Try again</button>
            </template>
            <p v-else class="text-secondary mb-0" role="status">Marking your test...</p>
        </div>
    </section>

    <!-- Mark, then a run through of every question with the correct answers -->
    <section v-else-if="phase === 'results'" class="testingScreen resultsScreen" aria-labelledby="resultsTitle">
        <div class="testingPanel resultsSummary">
            <span class="subjectBadge">{{ test.subjectName }}</span>
            <h1 id="resultsTitle" ref="resultsHeading" class="introTitle" tabindex="-1">{{ test.testName }} results</h1>

            <p class="resultsMark">
                {{ result.correctCount }}/{{ result.questionCount }}
                <span>({{ formatPercentage(result.percentageCorrect) }})</span>
            </p>

            <dl class="introDetails">
                <div>
                    <dt>Points earned</dt>
                    <dd>{{ result.pointsEarned > 0 ? '+' : '' }}{{ result.pointsEarned }} PTS</dd>
                </div>
                <div>
                    <dt>Best streak</dt>
                    <dd>x{{ bestStreak }}</dd>
                </div>
            </dl>

            <RouterLink :to="{ name: 'testSelection' }" class="btn testingPrimaryButton">Back to tests</RouterLink>
        </div>

        <h2 class="reviewTitle">Question review</h2>
        <ol class="reviewList">
            <li
                v-for="(question, index) in result.questions"
                :key="question.questionID"
                class="reviewQuestion"
                :class="question.isCorrect ? 'reviewQuestionCorrect' : 'reviewQuestionWrong'"
            >
                <div class="reviewQuestionHeader">
                    <h3 class="reviewQuestionText">{{ index + 1 }}. {{ question.question }}</h3>
                    <span class="reviewStatus">
                        <i
                            class="bi"
                            :class="question.isCorrect ? 'bi-check-circle-fill' : 'bi-x-circle-fill'"
                            aria-hidden="true"
                        ></i>
                        {{ reviewStatus(question) }}
                    </span>
                </div>

                <ul class="reviewAnswers">
                    <li
                        v-for="answer in question.answers"
                        :key="answer.answerID"
                        class="reviewAnswer"
                        :class="reviewAnswerClass(question, answer)"
                    >
                        <span>{{ answer.answerText }}</span>
                        <!-- text labels as well as colour, so the review doesn't rely on colour alone -->
                        <span class="reviewAnswerLabels">
                            <span v-if="answer.answerID === question.selectedAnswerID" class="reviewLabel">
                                Your answer
                            </span>
                            <span v-if="answer.isCorrect && !question.isCorrect" class="reviewLabel">
                                Correct answer
                            </span>
                        </span>
                    </li>
                </ul>
            </li>
        </ol>
    </section>

    <!-- The test itself, one question at a time -->
    <section v-else class="testingPage" aria-labelledby="questionText">
        <!-- Progress through the test and the countdown timer -->
        <header class="testingHeader">
            <div>
                <p class="testingLabel">Progress</p>
                <p class="testingProgress" aria-live="polite">
                    {{ currentQuestionNumber }}/ {{ questions.length }} Questions
                </p>
            </div>

            <div v-if="hasTimeLimit" class="text-end">
                <p class="testingLabel">Time remaining</p>
                <p class="testingTimer" role="timer" :aria-label="`${formattedTime} remaining`">
                    <i class="bi bi-clock" aria-hidden="true"></i>
                    <span>{{ formattedTime }}</span>
                </p>
            </div>
        </header>

        <!-- The current question and its answer options -->
        <div class="testingBody">
            <div class="questionBox">
                <!-- focused on each new question so screen readers read it out -->
                <h1 id="questionText" ref="questionHeading" class="questionText" tabindex="-1">
                    {{ currentQuestion.question }}
                </h1>
            </div>

            <div class="answerGrid">
                <button
                    v-for="(answer, index) in currentQuestion.answers"
                    :key="answer.answerID"
                    type="button"
                    class="answerButton"
                    :class="answerStyle(index).className"
                    @click="selectAnswer(answer)"
                >
                    <span class="answerIcon" aria-hidden="true">
                        <i class="bi" :class="answerStyle(index).icon"></i>
                    </span>
                    <span class="answerText">{{ answer.answerText }}</span>
                </button>
            </div>
        </div>

        <!-- Test details along the bottom -->
        <footer class="testingFooter">
            <div class="d-flex align-items-center gap-3">
                <span class="testIcon" aria-hidden="true"><i class="bi bi-controller"></i></span>
                <div>
                    <p class="testName">{{ test.testName }}</p>
                    <span class="subjectBadge">{{ test.subjectName }}</span>
                </div>
            </div>
        </footer>
    </section>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { getTest, submitTest } from '../services/testService';
import { formatTimeLimit } from '../utils/formatTimeLimit';

const route = useRoute();

// Each answer position keeps the same colour and shape so students can
// recognise options without relying on colour alone.
const answerStyles = [
    { className: 'answerRed', icon: 'bi-triangle-fill' },
    { className: 'answerBlue', icon: 'bi-diamond-fill' },
    { className: 'answerYellow', icon: 'bi-circle-fill' },
    { className: 'answerGreen', icon: 'bi-square-fill' },
];

// Which screen is showing: loading, loadError, intro, question, submitting or results.
const phase = ref('loading');
const loadError = ref('');
const submitError = ref('');

// The test from the backend, shaped like the Tests, Questions and Answers tables.
const test = ref(null);
// The marked test the backend sends back once it's handed in.
const result = ref(null);

// Where the student is in the test and what they have chosen so far.
const currentIndex = ref(0);
// questionID -> the answerID the student picked
const chosenAnswers = ref({});
const secondsRemaining = ref(0);

const questionHeading = ref(null);
const resultsHeading = ref(null);

const questions = computed(() => test.value?.questions ?? []);
const currentQuestion = computed(() => questions.value[currentIndex.value]);
// questions are numbered from 1 for students, the array starts at 0
const currentQuestionNumber = computed(() => currentIndex.value + 1);
// time limit is in minutes, the same unit the Tests table uses, null means untimed
const hasTimeLimit = computed(() => Number.isInteger(test.value?.timeLimit) && test.value.timeLimit > 0);

// Show the countdown as mm:ss.
const formattedTime = computed(() => {
    const minutes = Math.floor(secondsRemaining.value / 60);
    const seconds = secondsRemaining.value % 60;
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

// The most questions answered correctly in a row.
const bestStreak = computed(() => {
    let best = 0;
    let current = 0;
    for (const question of result.value?.questions ?? []) {
        current = question.isCorrect ? current + 1 : 0;
        best = Math.max(best, current);
    }
    return best;
});

// Repeat the four styles if a question ever has more than four answers.
function answerStyle(index) {
    return answerStyles[index % answerStyles.length];
}

function formatPercentage(percentage) {
    return `${Number(percentage).toLocaleString('en-GB', { maximumFractionDigits: 2 })}%`;
}

function reviewStatus(question) {
    if (question.isCorrect) {
        return 'Correct';
    }
    return question.selectedAnswerID === null ? 'Not answered' : 'Incorrect';
}

// Green for the correct answer, red for the wrong one the student picked.
function reviewAnswerClass(question, answer) {
    if (answer.isCorrect) {
        return 'reviewAnswerCorrect';
    }
    if (answer.answerID === question.selectedAnswerID) {
        return 'reviewAnswerWrong';
    }
    return '';
}

let timerID;

function stopTimer() {
    clearInterval(timerID);
    timerID = undefined;
}

async function focusQuestion() {
    await nextTick();
    questionHeading.value?.focus();
}

// Starts the timer (if the test has one) and shows the first question.
function startTest() {
    currentIndex.value = 0;
    chosenAnswers.value = {};
    phase.value = 'question';

    if (hasTimeLimit.value) {
        secondsRemaining.value = test.value.timeLimit * 60;
        // Count down once a second, hand the test in when it reaches zero.
        timerID = setInterval(() => {
            secondsRemaining.value -= 1;
            if (secondsRemaining.value <= 0) {
                finishTest();
            }
        }, 1000);
    }

    focusQuestion();
}

// Remember the chosen answer and move straight on, the last answer hands the test in.
function selectAnswer(answer) {
    if (phase.value !== 'question') {
        return;
    }

    chosenAnswers.value[currentQuestion.value.questionID] = answer.answerID;

    if (currentIndex.value === questions.value.length - 1) {
        finishTest();
        return;
    }

    currentIndex.value += 1;
    focusQuestion();
}

// Sends every question to be marked, ones the timer cut off go as null (unanswered).
async function finishTest() {
    stopTimer();
    phase.value = 'submitting';
    submitError.value = '';

    const answers = {};
    for (const question of questions.value) {
        answers[question.questionID] = chosenAnswers.value[question.questionID] ?? null;
    }

    try {
        result.value = await submitTest(test.value.testID, answers);
        phase.value = 'results';
        await nextTick();
        resultsHeading.value?.focus();
    } catch (error) {
        submitError.value = error.response?.data?.error || 'Unable to save your test. Please try again.';
    }
}

onMounted(async () => {
    try {
        test.value = await getTest(route.params.testID);
        phase.value = 'intro';
    } catch (error) {
        loadError.value = error.response?.data?.error || 'Unable to load this test. Please try again later.';
        phase.value = 'loadError';
    }
});

// Stop the timer if the student leaves the page so it doesn't keep running.
onBeforeUnmount(stopTimer);
</script>

<style scoped>
/* The page fills exactly one screen: header and footer keep their height and
   the question area takes whatever is left. dvh accounts for mobile browser bars. */
.testingPage {
    display: flex;
    height: 100vh;
    height: 100dvh;
    flex-direction: column;
    overflow: hidden;
    background: var(--colorPageBackground);
}

/* Start, loading and results screens scroll normally with a centred card. */
.testingScreen {
    display: flex;
    min-height: 100vh;
    min-height: 100dvh;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: var(--spaceLg);
    padding: var(--spaceXl) var(--contentPadding);
    background: var(--colorPageBackground);
}

/* The results page is long, so it starts at the top rather than the middle. */
.resultsScreen {
    justify-content: flex-start;
}

.testingPanel {
    width: 100%;
    max-width: 40rem;
    padding: var(--spaceXl);
    border: 1px solid var(--colorDivider);
    border-radius: var(--radiusMd);
    background: var(--colorSurface);
    box-shadow: var(--shadowModal);
}

.introTitle {
    margin: var(--spaceSm) 0;
    color: var(--colorBrand);
    font-family: var(--fontHeading);
    font-size: var(--fontSizeH1);
    font-weight: 700;
    overflow-wrap: anywhere;
}

/* Same summary layout as the test card details. */
.introDetails {
    display: grid;
    gap: var(--spaceSm);
    margin: var(--spaceLg) 0;
}

.introDetails div {
    display: flex;
    justify-content: space-between;
    gap: var(--spaceMd);
    padding-bottom: var(--spaceSm);
    border-bottom: 1px solid var(--colorDivider);
}

.introDetails dt {
    color: var(--colorTextSecondary);
    font-weight: 400;
}

.introDetails dd {
    margin: 0;
    font-weight: 700;
    text-align: right;
}

.introHint {
    color: var(--colorTextSecondary);
    font-size: var(--fontSizeSmall);
}

.introActions {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: var(--spaceSm);
}

.testingPrimaryButton,
.testingSecondaryButton {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    min-height: var(--minTouchTarget);
    font-weight: 700;
}

.testingPrimaryButton {
    border-color: var(--colorBrand);
    background: var(--colorBrand);
    color: var(--colorSurface);
}

.testingPrimaryButton:hover {
    border-color: var(--colorPrimaryHover);
    background: var(--colorPrimaryHover);
    color: var(--colorSurface);
}

.testingSecondaryButton {
    border-color: var(--colorDivider);
    background: var(--colorSurface);
    color: var(--colorText);
}

.testingSecondaryButton:hover {
    background: var(--colorSurfaceAlt);
}

.resultsSummary {
    text-align: center;
}

.resultsSummary .introDetails {
    text-align: left;
}

.resultsMark {
    margin: var(--spaceMd) 0 0;
    color: var(--colorText);
    font-size: var(--fontSizeHeroTitleMobile);
    font-weight: 700;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
}

.resultsMark span {
    color: var(--colorTextSecondary);
    font-size: var(--fontSizeH3);
}

.reviewTitle {
    width: 100%;
    max-width: 40rem;
    margin: var(--spaceMd) 0 0;
    color: var(--colorBrand);
    font-family: var(--fontHeading);
    font-size: var(--fontSizeH2);
}

.reviewList {
    display: grid;
    gap: var(--spaceMd);
    width: 100%;
    max-width: 40rem;
    margin: 0;
    padding: 0;
    list-style: none;
}

/* Coloured left edge shows right or wrong at a glance, the status text says it too. */
.reviewQuestion {
    padding: var(--spaceLg);
    border: 1px solid var(--colorDivider);
    border-left-width: 6px;
    border-radius: var(--radiusMd);
    background: var(--colorSurface);
    box-shadow: var(--shadowCard);
}

.reviewQuestionCorrect {
    border-left-color: var(--colorSuccess);
}

.reviewQuestionWrong {
    border-left-color: var(--colorError);
}

.reviewQuestionHeader {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: var(--spaceMd);
    margin-bottom: var(--spaceMd);
}

.reviewQuestionText {
    margin: 0;
    color: var(--colorText);
    font-size: var(--fontSizeH3);
    font-weight: 700;
    overflow-wrap: anywhere;
}

.reviewStatus {
    flex-shrink: 0;
    font-size: var(--fontSizeSmall);
    font-weight: 700;
    white-space: nowrap;
}

.reviewQuestionCorrect .reviewStatus {
    color: var(--colorSuccess);
}

.reviewQuestionWrong .reviewStatus {
    color: var(--colorError);
}

.reviewAnswers {
    display: grid;
    gap: var(--spaceSm);
    margin: 0;
    padding: 0;
    list-style: none;
}

.reviewAnswer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--spaceSm);
    padding: var(--spaceSm) var(--spaceMd);
    border: 1px solid var(--colorDivider);
    border-radius: var(--radiusSm);
    overflow-wrap: anywhere;
}

.reviewAnswerCorrect {
    border-color: var(--colorSuccess);
    background: var(--colorSuccessLight);
}

.reviewAnswerWrong {
    border-color: var(--colorError);
    background: var(--colorErrorLight);
}

.reviewAnswerLabels {
    display: flex;
    gap: var(--spaceXs);
}

.reviewLabel {
    padding: 0 var(--spaceSm);
    border-radius: var(--radiusPill);
    background: var(--colorSurface);
    color: var(--colorText);
    font-size: var(--fontSizeSmall);
    font-weight: 700;
    white-space: nowrap;
}

/* Progress and timer sit above the question. */
.testingHeader {
    display: flex;
    flex-shrink: 0;
    justify-content: space-between;
    align-items: flex-start;
    gap: var(--spaceMd);
    padding: var(--spaceMd) var(--contentPadding);
    border-bottom: 1px solid var(--colorDivider);
    background: var(--colorSurface);
}

/* Small uppercase labels above the progress and timer values. */
.testingLabel {
    color: var(--colorTextSecondary);
    font-size: var(--fontSizeSmall);
    font-weight: 600;
    letter-spacing: 0.02em;
    text-transform: uppercase;
}

.testingLabel,
.testingProgress {
    margin: 0;
}

.testingProgress {
    padding-top: var(--spaceXs);
    color: var(--colorText);
    font-size: var(--fontSizeH3);
    font-weight: 700;
}

/* tabular-nums stops the digits shifting width as the timer counts down. */
.testingTimer {
    display: inline-flex;
    align-items: center;
    gap: var(--spaceSm);
    margin: var(--spaceXs) 0 0;
    padding: 0 var(--spaceSm);
    border: 1px solid var(--colorDivider);
    border-radius: var(--radiusSm);
    background: var(--colorSurfaceAlt);
    color: var(--colorBrand);
    font-size: var(--fontSizeStat);
    font-weight: 700;
    line-height: 1.2;
    font-variant-numeric: tabular-nums;
}

.testingTimer i {
    font-size: var(--fontSizeH3);
}

/* Question and answers share the leftover height, spaced evenly. */
.testingBody {
    display: flex;
    flex: 1;
    min-height: 0;
    flex-direction: column;
    justify-content: space-evenly;
    gap: var(--spaceXl);
    width: 100%;
    max-width: var(--contentMaxWidthLarge);
    margin: 0 auto;
    padding: var(--spaceXl) var(--contentPadding);
    overflow-y: auto;
}

/* White card that matches the rest of the site's cards. */
.questionBox {
    padding: var(--space2xl) var(--spaceXl);
    border: 1px solid var(--colorDivider);
    border-radius: var(--radiusMd);
    background: var(--colorSurface);
    box-shadow: var(--shadowModal);
    text-align: center;
}

/* Uses the heading font and brand colour like the other page headings. */
.questionText {
    margin: 0;
    color: var(--colorBrand);
    font-family: var(--fontHeading);
    font-size: var(--fontSizeH2);
    font-weight: 700;
    overflow-wrap: anywhere;
}

/* The heading is only focused from code to move screen readers, so no outline is needed. */
.questionText:focus,
.introTitle:focus {
    outline: none;
}

/* Two by two grid of answers, stacked on small screens. */
.answerGrid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--spaceMd);
}

.answerButton {
    display: flex;
    align-items: center;
    gap: var(--spaceLg);
    min-height: clamp(64px, 11vh, 110px);
    padding: var(--spaceMd) var(--spaceLg);
    border: 0;
    border-radius: var(--radiusMd);
    box-shadow: var(--shadowCard);
    color: var(--colorSurface);
    font-family: var(--fontBody);
    font-size: var(--fontSizeH3);
    font-weight: 700;
    text-align: left;
    overflow-wrap: anywhere;
    transition:
        transform 0.15s ease,
        filter 0.15s ease;
}

.answerButton:hover {
    filter: brightness(1.08);
    transform: translateY(-2px);
}

/* White outlined square around each shape icon. */
.answerIcon {
    display: inline-flex;
    flex-shrink: 0;
    justify-content: center;
    align-items: center;
    width: var(--minTouchTarget);
    height: var(--minTouchTarget);
    border-radius: var(--radiusMd);
    border: 2px solid var(--colorSurface);
    font-size: var(--fontSizeH3);
}

/* Answer colours are set in main.css. */
.answerRed {
    background: var(--colorAnswerRed);
}

.answerBlue {
    background: var(--colorAnswerBlue);
}

.answerYellow {
    background: var(--colorAnswerYellow);
}

.answerGreen {
    background: var(--colorAnswerGreen);
}

/* Test details along the bottom. */
.testingFooter {
    display: flex;
    flex-shrink: 0;
    justify-content: space-between;
    align-items: center;
    gap: var(--spaceMd);
    padding: var(--spaceSm) var(--contentPadding);
    border-top: 1px solid var(--colorDivider);
    background: var(--colorSurface);
}

.testingFooter p {
    margin: 0;
}

.testIcon {
    display: inline-flex;
    flex-shrink: 0;
    justify-content: center;
    align-items: center;
    width: var(--minTouchTarget);
    height: var(--minTouchTarget);
    border-radius: var(--radiusMd);
    background: var(--colorPrimary);
    color: var(--colorSurface);
    font-size: var(--fontSizeH3);
}

.testName {
    color: var(--colorText);
    font-size: var(--fontSizeH3);
    font-weight: 700;
    line-height: 1.2;
}

.subjectBadge {
    display: inline-block;
    padding: 0 var(--spaceXs);
    border-radius: var(--radiusSm);
    background: var(--colorPrimaryLight);
    color: var(--colorBrand);
    font-size: var(--fontSizeFooter);
    font-weight: 700;
    text-transform: uppercase;
}

/* Phones: stack the answers and tighten the spacing. */
@media (max-width: 767.98px) {
    .testingHeader,
    .testingBody,
    .testingFooter,
    .testingScreen {
        padding-inline: var(--spaceMd);
    }

    .testingPanel {
        padding: var(--spaceLg) var(--spaceMd);
    }

    .testingBody {
        justify-content: flex-start;
        gap: var(--spaceLg);
        padding-block: var(--spaceLg);
    }

    .questionBox {
        padding: var(--spaceLg) var(--spaceMd);
    }

    .answerGrid {
        grid-template-columns: 1fr;
        gap: var(--spaceSm);
    }

    .answerButton {
        min-height: 60px;
        gap: var(--spaceMd);
        padding-inline: var(--spaceMd);
    }

    .reviewQuestion {
        padding: var(--spaceMd);
    }

    .reviewQuestionHeader {
        flex-direction: column;
        gap: var(--spaceXs);
    }
}

/* Short landscape screens: keep the 2x2 grid and shrink everything vertically. */
@media (max-height: 500px) and (orientation: landscape) {
    .answerGrid {
        grid-template-columns: 1fr 1fr;
    }

    .testingBody {
        justify-content: flex-start;
        gap: var(--spaceSm);
        padding-block: var(--spaceSm);
    }

    .questionBox {
        padding: var(--spaceSm) var(--spaceMd);
    }

    .questionText {
        font-size: var(--fontSizeHeroText);
    }

    .answerButton {
        min-height: var(--minTouchTarget);
        padding-block: var(--spaceSm);
        font-size: var(--fontSizeBody);
    }

    .answerIcon {
        width: var(--spaceXl);
        height: var(--spaceXl);
        font-size: var(--fontSizeBody);
    }
}

/* Turn off the hover lift for people who have asked their device for less motion. */
@media (prefers-reduced-motion: reduce) {
    .answerButton {
        transition: none;
    }

    .answerButton:hover {
        transform: none;
    }
}
</style>
