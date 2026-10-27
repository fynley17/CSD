<script setup>
import QuestionManagementTable from '../components/tables/questionManagementTable.vue';
</script>

<template>
    <main class="questionManagement">
        <div class="pageHeading">
            <div>
                <h1>Question Management</h1>
                <p>Manage questions</p>
            </div>
        </div>

        <div class="tableToolbar">
            <label>
                <span class="visuallyHidden">Search questions</span>
                <input type="search" placeholder="Search Questions..." />
            </label>

            <label>
                <span class="visuallyHidden">Filter questions by subject</span>
                <select>
                    <option value="">Subject</option>
                </select>
            </label>

            <label>
                <span class="visuallyHidden">Sort questions</span>
                <select>
                    <option value="">Sort by</option>
                    <option value="idAsc">Question ID (ascending)</option>
                    <option value="idDesc">Question ID (descending)</option>
                    <option value="subject">Subject</option>
                </select>
            </label>

            <button type="button" class="primaryButton"><span aria-hidden="true">＋</span> Add Question</button>
        </div>

        <QuestionManagementTable />
    </main>
</template>

<style scoped>
.questionManagement {
    max-width: var(--contentMaxWidth);
    min-height: 100%;
    margin: 0 auto;
    padding: var(--space2xl) var(--spaceLg);
    color: var(--colorText);
    font-family: var(--fontBody);
}

.pageHeading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--spaceMd);
    margin-bottom: var(--space2xl);
}

.pageHeading h1 {
    margin: 0;
    color: var(--colorBrand);
    font-family: var(--fontHeading);
    font-size: var(--fontSizeH1);
}

.pageHeading p {
    margin: var(--spaceXs) 0 0;
    color: var(--colorTextSecondary);
    font-size: var(--fontSizeSmall);
}

.tableToolbar {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr) minmax(0, 1fr) auto;
    align-items: center;
    gap: var(--spaceLg);
    margin-bottom: var(--spaceMd);
}

.tableToolbar label {
    min-width: 0;
}

.tableToolbar input,
.tableToolbar select {
    width: 100%;
    min-width: 0;
    min-height: 44px;
    padding: var(--spaceSm);
    border: 1px solid var(--colorBorderInput);
    border-radius: var(--radiusSm);
    background: var(--colorSurface);
    color: var(--colorText);
    font: inherit;
}

.primaryButton,
.secondaryButton {
    min-height: 44px;
    padding: var(--spaceSm) var(--spaceMd);
    border: 1px solid var(--colorPrimary);
    border-radius: var(--radiusSm);
    font: inherit;
    font-weight: 600;
    cursor: pointer;
}

.primaryButton {
    background: var(--colorPrimary);
    color: var(--colorSurface);
}

.primaryButton:hover {
    background: var(--colorPrimaryHover);
}

.secondaryButton {
    background: var(--colorSurface);
    color: var(--colorPrimary);
}

button:focus-visible,
input:focus-visible,
select:focus-visible {
    outline: 3px solid var(--colorFocus);
    outline-offset: 2px;
}

.visuallyHidden {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    overflow: hidden;
    clip-path: inset(50%);
    white-space: nowrap;
    border: 0;
}

@media (max-width: 639px) {
    .questionManagement {
        padding: var(--spaceLg) var(--spaceMd);
    }

    .pageHeading {
        align-items: flex-start;
        flex-direction: column;
        margin-bottom: var(--spaceXl);
    }

    .tableToolbar {
        grid-template-columns: 1fr;
        gap: var(--spaceMd);
    }
}
</style>
