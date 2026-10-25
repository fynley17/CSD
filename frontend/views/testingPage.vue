<template>
    <section class="testingPage" aria-labelledby="questionText">
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

        <div class="testingBody">
            <div class="questionBox">
                <h1 id="questionText" class="questionText">{{ currentQuestion.question }}</h1>
            </div>

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

        <footer class="testingFooter">
            <div class="d-flex align-items-center gap-3">
                <span class="testIcon" aria-hidden="true"><i class="bi bi-controller"></i></span>
                <div>
                    <p class="testName">{{ test.testName }}</p>
                    <span class="subjectBadge">{{ test.subjectName }}</span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-4">
                <p class="testingPoints">{{ points.toLocaleString() }} <span>PTS</span></p>
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

const currentIndex = ref(0);
const selectedAnswerID = ref(null);
const points = ref(0);
const streak = ref(0);
const secondsRemaining = ref(test.timeLimit * 60);

const currentQuestion = computed(() => questions[currentIndex.value]);
const currentQuestionNumber = computed(() => currentIndex.value + 1);

// Show the countdown as mm:ss.
const formattedTime = computed(() => {
    const minutes = Math.floor(secondsRemaining.value / 60);
    const seconds = secondsRemaining.value % 60;
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

function selectAnswer(answer) {
    selectedAnswerID.value = answer.answerID;
}

let timerID;

onMounted(() => {
    timerID = setInterval(() => {
        if (secondsRemaining.value > 0) {
            secondsRemaining.value -= 1;
        } else {
            clearInterval(timerID);
        }
    }, 1000);
});

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
    background: var(--colorSurface);
}

/* Progress and timer sit above the question. */
.testingHeader {
    display: flex;
    flex-shrink: 0;
    justify-content: space-between;
    align-items: flex-start;
    gap: var(--spaceMd);
    padding: clamp(0.75rem, 2.5vh, 1.5rem) 6% clamp(0.5rem, 1.5vh, 1rem);
    border-bottom: 1px solid var(--colorText);
}

.testingHeader p {
    margin: 0;
}

.testingLabel {
    color: var(--colorTextSecondary);
    font-size: clamp(0.6875rem, 1.2vw, var(--fontSizeSmall));
    font-weight: 600;
    letter-spacing: 0.02em;
    text-transform: uppercase;
}

.testingProgress {
    padding: var(--spaceSm) 0 0 clamp(0rem, 2.5vw, var(--spaceXl));
    color: var(--colorTextSecondary);
    font-size: clamp(1rem, 1.8vw, var(--fontSizeH3));
    font-weight: 700;
}

.testingTimer {
    display: inline-flex;
    align-items: center;
    gap: var(--spaceSm);
    margin-top: var(--spaceSm) !important;
    padding: 0 var(--spaceXs);
    border: 1px solid var(--colorText);
    color: var(--colorText);
    font-size: clamp(1.5rem, 3.2vw, 2.25rem);
    font-weight: 800;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
}

.testingTimer i {
    font-size: 0.65em;
}

/* Question and answers share the leftover height, spaced evenly. */
.testingBody {
    display: flex;
    flex: 1;
    min-height: 0;
    flex-direction: column;
    justify-content: space-evenly;
    gap: clamp(1rem, 4vh, 3rem);
    width: 100%;
    max-width: var(--contentMaxWidthLarge);
    margin: 0 auto;
    padding: clamp(1rem, 4vh, 3rem) 6%;
    overflow-y: auto;
}

.questionBox {
    padding: clamp(1.25rem, 5vh, 3rem) clamp(1rem, 3vw, 2rem);
    border-radius: clamp(12px, 1.5vw, 20px);
    background: var(--colorSurface);
    box-shadow: 0 8px 20px rgba(17, 24, 39, 0.25);
    text-align: center;
}

.questionText {
    margin: 0;
    color: var(--colorText);
    font-family: var(--fontBody);
    font-size: clamp(1.25rem, 2.4vw, 2rem);
    font-weight: 700;
    overflow-wrap: anywhere;
}

/* Two by two grid of answers, stacked on small screens. */
.answerGrid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: clamp(0.75rem, 1.5vw, 1.25rem);
}

.answerButton {
    display: flex;
    align-items: center;
    gap: clamp(0.75rem, 1.5vw, 1.25rem);
    min-height: clamp(64px, 11vh, 110px);
    padding: var(--spaceMd) clamp(1rem, 2vw, 1.5rem);
    border: 0;
    border-radius: 14px;
    box-shadow: 0 4px 8px rgba(17, 24, 39, 0.15);
    color: var(--colorSurface);
    font-size: clamp(1.1rem, 1.8vw, 1.5rem);
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

.answerButton[aria-pressed='true'] {
    outline: 4px solid var(--colorText);
    outline-offset: 3px;
}

.answerIcon {
    display: inline-flex;
    flex-shrink: 0;
    justify-content: center;
    align-items: center;
    width: clamp(38px, 4vw, 50px);
    height: clamp(38px, 4vw, 50px);
    border-radius: var(--radiusMd);
    background: rgba(255, 255, 255, 0.15);
    font-size: clamp(1.1rem, 2vw, 1.5rem);
}

.answerRed {
    background: #d13340;
}

.answerBlue {
    background: #3066c9;
}

/* Darker than a pure yellow so the white text stays readable. */
.answerYellow {
    background: #b8861f;
}

.answerGreen {
    background: #46892a;
}

/* Test details, points and streak along the bottom. */
.testingFooter {
    display: flex;
    flex-shrink: 0;
    justify-content: space-between;
    align-items: center;
    gap: var(--spaceMd);
    padding: clamp(0.5rem, 1.5vh, 1rem) 4%;
    border-top: 1px solid var(--colorText);
}

.testingFooter p {
    margin: 0;
}

.testIcon {
    display: inline-flex;
    flex-shrink: 0;
    justify-content: center;
    align-items: center;
    width: 38px;
    height: 38px;
    border-radius: var(--radiusMd);
    background: #8b3dff;
    color: var(--colorSurface);
    font-size: 1.25rem;
}

.testName {
    font-size: clamp(1rem, 1.6vw, var(--fontSizeH3));
    font-weight: 700;
    line-height: 1.2;
}

.subjectBadge {
    display: inline-block;
    padding: 0 var(--spaceXs);
    border: 1px solid var(--colorText);
    border-radius: 4px;
    font-size: var(--fontSizeFooter);
    font-weight: 700;
    text-transform: uppercase;
}

.testingPoints {
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
    background: #c2410c;
    color: var(--colorSurface);
    font-size: var(--fontSizeSmall);
    font-weight: 700;
    white-space: nowrap;
}

/* Phones: stack the answers and tighten the spacing. */
@media (max-width: 767.98px) {
    .testingHeader,
    .testingBody {
        padding-inline: var(--spaceMd);
    }

    .testingBody {
        justify-content: flex-start;
    }

    .answerGrid {
        grid-template-columns: 1fr;
    }

    .answerButton {
        min-height: 60px;
    }

    .testingFooter {
        padding-inline: var(--spaceMd);
    }

    .testingFooter > div:last-child {
        gap: var(--spaceMd) !important;
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
        font-size: 1.1rem;
    }

    .answerButton {
        min-height: 48px;
        padding-block: var(--spaceSm);
        font-size: 1rem;
    }

    .answerIcon {
        width: 32px;
        height: 32px;
        font-size: 1rem;
    }
}
</style>
