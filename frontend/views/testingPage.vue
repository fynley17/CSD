<template>
    <section class="testingPage" aria-labelledby="questionText">
        <!-- Progress through the test and the countdown timer -->
        <header class="testingHeader">
            <div>
                <p class="testingLabel">Progress</p>
                <p class="testingProgress" aria-live="polite">
                    {{ currentQuestionNumber }}/ {{ questions.length }} Questions
                </p>
            </div>

            <div class="text-end">
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
                <h1 id="questionText" class="questionText">{{ currentQuestion.question }}</h1>
            </div>

            <!-- aria-pressed tells screen readers which answer is currently chosen -->
            <div class="answerGrid">
                <button
                    v-for="(answer, index) in currentQuestion.answers"
                    :key="answer.answerID"
                    type="button"
                    class="answerButton"
                    :class="answerStyles[index].className"
                    :aria-pressed="selectedAnswerID === answer.answerID"
                    @click="selectAnswer(answer)"
                >
                    <span class="answerIcon" aria-hidden="true">
                        <i class="bi" :class="answerStyles[index].icon"></i>
                    </span>
                    <span class="answerText">{{ answer.answerText }}</span>
                </button>
            </div>
        </div>

        <!-- Test details on the left, the student's points and streak on the right -->
        <footer class="testingFooter">
            <div class="d-flex align-items-center gap-3">
                <span class="testIcon" aria-hidden="true"><i class="bi bi-controller"></i></span>
                <div>
                    <p class="testName">{{ test.testName }}</p>
                    <span class="subjectBadge">{{ test.subjectName }}</span>
                </div>
            </div>

            <div class="testingScore">
                <p class="testingPoints">{{ points.toLocaleString('en-GB') }} <span>PTS</span></p>
                <span class="streakPill">Streak <span aria-hidden="true">🔥</span> x{{ streak }}</span>
            </div>
        </footer>
    </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

// Sample data shaped like the Tests, Questions and Answers tables until the
// questions endpoint exists.
const test = {
    testName: 'Webapps basics',
    subjectName: 'Webapps',
    // time limit in minutes, the same unit the Tests table uses
    timeLimit: 5,
};

const questions = [
    {
        questionID: 'question-1',
        question: 'What is not a framework for JS?',
        answers: [
            { answerID: 'answer-1', answerText: 'Django' },
            { answerID: 'answer-2', answerText: 'Vue' },
            { answerID: 'answer-3', answerText: 'Angular' },
            { answerID: 'answer-4', answerText: 'React' },
        ],
    },
];

// Each answer position keeps the same colour and shape so students can
// recognise options without relying on colour alone.
const answerStyles = [
    { className: 'answerRed', icon: 'bi-triangle-fill' },
    { className: 'answerBlue', icon: 'bi-diamond-fill' },
    { className: 'answerYellow', icon: 'bi-circle-fill' },
    { className: 'answerGreen', icon: 'bi-square-fill' },
];

// Where the student is in the test and what they have chosen so far.
const currentIndex = ref(0);
const selectedAnswerID = ref(null);
const points = ref(0);
const streak = ref(0);
const secondsRemaining = ref(test.timeLimit * 60);

const currentQuestion = computed(() => questions[currentIndex.value]);
// questions are numbered from 1 for students, the array starts at 0
const currentQuestionNumber = computed(() => currentIndex.value + 1);

// Show the countdown as mm:ss.
const formattedTime = computed(() => {
    const minutes = Math.floor(secondsRemaining.value / 60);
    const seconds = secondsRemaining.value % 60;
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

// Remember the chosen answer, scoring happens once the questions endpoint exists.
function selectAnswer(answer) {
    selectedAnswerID.value = answer.answerID;
}

let timerID;

// Count down once a second and stop at zero.
onMounted(() => {
    timerID = setInterval(() => {
        if (secondsRemaining.value > 0) {
            secondsRemaining.value -= 1;
        } else {
            clearInterval(timerID);
        }
    }, 1000);
});

// Stop the timer if the student leaves the page so it doesn't keep running.
onBeforeUnmount(() => clearInterval(timerID));
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

/* Outline the chosen answer in the same colour as the site's focus ring. */
.answerButton[aria-pressed='true'] {
    outline: 4px solid var(--colorFocus);
    outline-offset: 3px;
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

/* Test details, points and streak along the bottom. */
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

/* Points and streak sit side by side, set here as bootstrap's gap utility uses !important. */
.testingScore {
    display: flex;
    align-items: center;
    gap: var(--spaceLg);
}

.testingPoints {
    color: var(--colorText);
    font-size: var(--fontSizeSmall);
    font-weight: 700;
    white-space: nowrap;
}

.testingPoints span {
    color: var(--colorTextSecondary);
}

.streakPill {
    padding: var(--spaceSm) var(--spaceMd);
    border-radius: var(--radiusPill);
    background: var(--colorWarning);
    color: var(--colorSurface);
    font-size: var(--fontSizeSmall);
    font-weight: 700;
    white-space: nowrap;
}

/* Phones: stack the answers and tighten the spacing. */
@media (max-width: 767.98px) {
    .testingHeader,
    .testingBody,
    .testingFooter {
        padding-inline: var(--spaceMd);
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

    .testingScore {
        gap: var(--spaceMd);
    }
}

/* Very narrow phones: the test name gets its own row above points and streak. */
@media (max-width: 399.98px) {
    .testingFooter {
        flex-wrap: wrap;
        gap: var(--spaceSm);
    }

    .streakPill {
        padding: var(--spaceXs) var(--spaceSm);
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
